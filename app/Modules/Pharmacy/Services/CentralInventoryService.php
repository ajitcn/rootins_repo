<?php

namespace Pharmacy\Services;

final class CentralInventoryService
{
    public function __construct(private readonly PharmacyScope $scope)
    {
    }

    /**
     * Central stock is global. The transaction creates the batch and its
     * opening movement together so inventory cannot exist without history.
     */
    /**
     * @return array{http:int,body:array<string,mixed>}
     */
    public function create(string $medicineId, string $batchNumber, string $expiryDate, string $quantity, string $status = 'active'): array
    {
        if ($this->scope->clinicId() === null) {
            return $this->result(403, 'Pharmacy clinic scope is not assigned.');
        }

        $errors = [];
        $medicine = filter_var(trim($medicineId), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($medicine === false) {
            $errors['medicine_id'] = 'Select an active medicine.';
        }
        $batchNumber = trim($batchNumber);

        if ($batchNumber === '') {
            $errors['batch_number'] = 'Batch number is required.';
        } elseif (mb_strlen($batchNumber) > 100) {
            $errors['batch_number'] = 'Batch number cannot exceed 100 characters.';
        }
        $expiry = $this->parseExpiry($expiryDate);

        if ($expiry === null) {
            $errors['expiry_date'] = 'Enter a valid expiry date in dd-mm-yyyy format.';
        } elseif ($expiry < $this->today()) {
            $errors['expiry_date'] = 'Expiry date must be today or later.';
        }
        $quantity = trim($quantity);

        if (!preg_match('/^[1-9][0-9]*$/', $quantity) || (int) $quantity <= 0) {
            $errors['quantity'] = 'Opening quantity must be a positive whole number.';
        }
        $status = strtolower(trim($status)) ?: 'active';

        if (!in_array($status, ['active', 'inactive'], true)) {
            $errors['status'] = 'Select a valid stock status.';
        }

        if ($errors !== []) {
            return ['http' => 422, 'body' => ['status' => false, 'msg' => 'Stock batch could not be created.', 'errors' => $errors]];
        }

        $db = db_connect();
        $db->transBegin();

        try {
            $medicineRow = $db->query('SELECT id, status FROM medicines WHERE id = ? LIMIT 1 FOR UPDATE', [(int) $medicine])->getRowArray();

            if ($medicineRow === null || strtolower((string) ($medicineRow['status'] ?? '')) !== 'active') {
                $db->transRollback();

                return ['http' => 422, 'body' => ['status' => false, 'msg' => 'Stock batch could not be created.', 'errors' => ['medicine_id' => 'Select an active medicine.']]];
            }

            $duplicate = $db->query('SELECT id FROM medicine_inventory_batches WHERE medicine_id = ? AND batch_number = ? AND expiry_date = ? LIMIT 1 FOR UPDATE', [(int) $medicine, $batchNumber, $expiry])->getRowArray();

            if ($duplicate !== null) {
                $db->transRollback();

                return ['http' => 422, 'body' => ['status' => false, 'msg' => 'Stock batch could not be created.', 'errors' => ['batch_number' => 'This medicine, batch number, and expiry date already exist.']]];
            }

            $now = $this->now();

            if (!$db->table('medicine_inventory_batches')->insert([
                'medicine_id' => (int) $medicine,
                'batch_number' => $batchNumber,
                'expiry_date' => $expiry,
                'quantity_available' => (int) $quantity,
                'status' => $status,
                'created_at' => $now,
                'updated_at' => $now,
            ])) {
                throw new \RuntimeException('Central inventory batch insert failed.');
            }
            $batchId = (int) $db->insertID();

            if ($batchId <= 0) {
                throw new \RuntimeException('Central inventory batch insert did not return an id.');
            }

            $userId = (int) (($this->scope->user() ?? [])['id'] ?? 0);

            if (!$db->table('inventory_stock_movements')->insert([
                'inventory_batch_id' => $batchId,
                'medicine_id' => (int) $medicine,
                'batch_number' => $batchNumber,
                'adjustment_quantity' => (int) $quantity,
                'previous_quantity' => 0,
                'new_quantity' => (int) $quantity,
                'reason' => 'Opening stock batch; expiry ' . $expiry,
                'actor_user_id' => $userId > 0 ? $userId : null,
                'actor_clinic_id' => $this->scope->clinicId(),
                'created_at' => $now,
                'updated_at' => $now,
            ])) {
                throw new \RuntimeException('Opening stock movement insert failed.');
            }

            if (!$db->transStatus()) {
                throw new \RuntimeException('Opening stock movement could not be recorded.');
            }
            $db->transCommit();
            $this->scope->audit('pharmacy.inventory_batch_created', [
                'inventory_batch_id' => $batchId,
                'medicine_id' => (int) $medicine,
                'batch_number' => $batchNumber,
                'expiry_date' => $expiry,
                'previous_quantity' => 0,
                'new_quantity' => (int) $quantity,
                'reason' => 'Opening stock batch',
            ]);

            return ['http' => 200, 'body' => ['status' => true, 'msg' => 'Central stock batch added.', 'data' => ['batch_id' => $batchId]]];
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'Pharmacy central inventory batch creation failed: ' . $e->getMessage());
            $message = strtolower($e->getMessage());

            if (str_contains($message, 'duplicate') || str_contains($message, 'unique') || str_contains($message, '1062')) {
                return ['http' => 422, 'body' => ['status' => false, 'msg' => 'Stock batch could not be created.', 'errors' => ['batch_number' => 'This medicine, batch number, and expiry date already exist.']]];
            }

            return $this->result(500, 'Central stock batch could not be created safely.');
        }
    }

    /**
     * @return array{http:int,body:array<string,mixed>}
     */
    public function adjust(int $batchId, int $delta, string $reason, bool $confirmed): array
    {
        if ($this->scope->clinicId() === null) {
            return $this->result(403, 'Pharmacy clinic scope is not assigned.');
        }
        $errors = [];

        if ($batchId <= 0) {
            $errors['batch_id'] = 'A valid inventory batch is required.';
        }

        if ($delta === 0) {
            $errors['adjustment'] = 'Enter a positive or negative adjustment.';
        }

        if (trim($reason) === '') {
            $errors['reason'] = 'A reason is required.';
        }

        if (!$confirmed) {
            $errors['confirmation'] = 'Confirm this central stock adjustment before saving.';
        }

        if ($errors !== []) {
            return ['http' => 422, 'body' => ['status' => false, 'msg' => 'Stock adjustment could not be saved.', 'errors' => $errors]];
        }
        $db = db_connect();
        $db->transBegin();

        try {
            $batch = $db->query('SELECT * FROM medicine_inventory_batches WHERE id = ? LIMIT 1 FOR UPDATE', [$batchId])->getRowArray();

            if ($batch === null) {
                $db->transRollback();

                return $this->result(404, 'Inventory batch not found.');
            }

            if (strtolower((string) ($batch['status'] ?? 'active')) === 'merged') {
                $db->transRollback();

                return ['http' => 422, 'body' => ['status' => false, 'msg' => 'Merged inventory history rows cannot be adjusted.', 'errors' => ['batch_id' => 'Adjust the surviving central batch instead.']]];
            }
            $previous = max(0, (int) $batch['quantity_available']);
            $new = $previous + $delta;

            if ($new < 0) {
                $db->transRollback();

                return ['http' => 422, 'body' => ['status' => false, 'msg' => 'Stock adjustment would make quantity negative.', 'errors' => ['adjustment' => 'Central stock cannot be below zero.']]];
            }
            $updated = $db->query('UPDATE medicine_inventory_batches SET quantity_available = ? WHERE id = ? AND quantity_available = ?', [$new, $batchId, $previous]);

            if ($updated->affectedRows() !== 1) {
                throw new \RuntimeException('Central stock changed while saving the adjustment.');
            }
            $userId = (int) (($this->scope->user() ?? [])['id'] ?? 0);
            $now = $this->now();
            $db->table('inventory_stock_movements')->insert(['inventory_batch_id' => $batchId, 'medicine_id' => (int) $batch['medicine_id'], 'batch_number' => (string) ($batch['batch_number'] ?? ''), 'adjustment_quantity' => $delta, 'previous_quantity' => $previous, 'new_quantity' => $new, 'reason' => trim($reason), 'actor_user_id' => $userId > 0 ? $userId : null, 'actor_clinic_id' => $this->scope->clinicId(), 'created_at' => $now, 'updated_at' => $now]);

            if (!$db->transStatus()) {
                throw new \RuntimeException('Stock movement could not be recorded.');
            }
            $db->transCommit();
            $this->scope->audit('pharmacy.inventory_stock_adjusted', ['inventory_batch_id' => $batchId, 'medicine_id' => (int) $batch['medicine_id'], 'previous_quantity' => $previous, 'new_quantity' => $new, 'adjustment_quantity' => $delta, 'reason' => trim($reason)]);

            return ['http' => 200, 'body' => ['status' => true, 'msg' => 'Central stock adjusted.', 'data' => ['batch_id' => $batchId, 'previous_quantity' => $previous, 'new_quantity' => $new]]];
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'Pharmacy central inventory adjustment failed: ' . $e->getMessage());

            return $this->result(500, 'Central stock could not be adjusted safely.');
        }
    }

    private function parseExpiry(string $value): ?string
    {
        $timezone = new \DateTimeZone((string) config('App')->appTimezone);
        $date = \DateTimeImmutable::createFromFormat('!d-m-Y', trim($value), $timezone);
        $errors = \DateTimeImmutable::getLastErrors();

        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $date->format('d-m-Y') !== trim($value)) {
            return null;
        }

        return $date->format('Y-m-d');
    }

    private function today(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone((string) config('App')->appTimezone)))->format('Y-m-d');
    }

    private function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone((string) config('App')->appTimezone)))->format('Y-m-d H:i:s');
    }

    private function result(int $http, string $msg): array
    {
        return ['http' => $http, 'body' => ['status' => false, 'msg' => $msg]];
    }
}
