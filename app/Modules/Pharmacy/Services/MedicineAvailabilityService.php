<?php

namespace Pharmacy\Services;

use DateTimeImmutable;
use DateTimeZone;

/** Read-only view of the centralized medicine stock pool. */
final class MedicineAvailabilityService
{
    public const LOW_STOCK_THRESHOLD = 10;

    public const NEAR_EXPIRY_DAYS = 30;

    public function __construct(private readonly PharmacyScope $scope)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function search(array $input): array
    {
        $requestedView = (string) ($input['view'] ?? 'list');
        $view = in_array($requestedView, ['list', 'grid'], true) ? $requestedView : 'list';
        $pageSize = (int) ($input['page_size'] ?? 15);
        $pageSize = in_array($pageSize, [10, 15, 25, 50], true) ? $pageSize : 15;
        $page = max(1, (int) ($input['page'] ?? 1));
        $search = trim((string) ($input['search'] ?? ''));
        $availability = trim((string) ($input['availability'] ?? ''));
        $availability = in_array($availability, ['', 'available', 'low', 'out_of_stock', 'inactive'], true) ? $availability : '';
        $expiryState = trim((string) ($input['expiry_state'] ?? ''));
        $expiryState = in_array($expiryState, ['', 'expired', 'near', 'valid'], true) ? $expiryState : '';
        $medicineId = (int) ($input['medicine_id'] ?? 0);
        $today = $this->today();
        $nearExpiry = $today->modify('+' . self::NEAR_EXPIRY_DAYS . ' days')->format('Y-m-d');
        $filters = ['search' => $search, 'availability' => $availability, 'expiry_state' => $expiryState, 'medicine_id' => $medicineId, 'page_size' => $pageSize];
        $empty = [
            'rows' => [],
            'total' => 0,
            'view' => $view,
            'filters' => $filters,
            'medicines' => [],
            'pagination' => [
                'page' => 1,
                'pages' => 1,
                'page_size' => $pageSize,
                'total' => 0,
            ],
            'summary' => $this->emptySummary(),
            'thresholds' => [
                'low_stock' => self::LOW_STOCK_THRESHOLD,
                'near_expiry_days' => self::NEAR_EXPIRY_DAYS,
            ],
            'today' => $today->format('Y-m-d'),
        ];

        if ($this->scope->clinicId() === null) {
            return $empty;
        }
        $query = db_connect()->table('medicine_inventory_batches AS batches')->select('batches.id AS batch_id, batches.medicine_id, batches.batch_number, batches.expiry_date, batches.quantity_available, batches.status AS batch_status, medicines.medicine_name, medicines.unit, medicines.dosage_form')->join('medicines', 'medicines.id = batches.medicine_id')->where('medicines.status', 'active');

        if ($search !== '') {
            $query->groupStart()->like('medicines.medicine_name', $search)->orLike('batches.batch_number', $search)->orLike('batches.status', $search)->groupEnd();
        }

        if ($medicineId > 0) {
            $query->where('batches.medicine_id', $medicineId);
        }

        if ($availability === 'available') {
            $query->where('batches.status', 'active')->where('batches.quantity_available >', 10)->where('batches.expiry_date >=', $today->format('Y-m-d'));
        }

        if ($availability === 'low') {
            $query->where('batches.status', 'active')->where('batches.quantity_available >', 0)->where('batches.quantity_available <=', self::LOW_STOCK_THRESHOLD)->where('batches.expiry_date >=', $today->format('Y-m-d'));
        }

        if ($availability === 'out_of_stock') {
            $query->where('batches.quantity_available <=', 0);
        }

        if ($availability === 'inactive') {
            $query->where('batches.status !=', 'active');
        }

        if ($expiryState === 'expired') {
            $query->where('batches.expiry_date <', $today->format('Y-m-d'));
        }

        if ($expiryState === 'near') {
            $query->where('batches.expiry_date >=', $today->format('Y-m-d'))->where('batches.expiry_date <=', $nearExpiry);
        }

        if ($expiryState === 'valid') {
            $query->where('batches.expiry_date >', $nearExpiry);
        }
        $summaryRows = (clone $query)->get()->getResultArray();
        $summary = $this->summarize($summaryRows, $today);
        $total = (clone $query)->countAllResults();
        $pages = max(1, (int) ceil($total / $pageSize));
        $page = min($page, $pages);
        $rows = $query->orderBy('batches.expiry_date', 'ASC')->orderBy('medicines.medicine_name', 'ASC')->orderBy('batches.id', 'ASC')->get($pageSize, ($page - 1) * $pageSize)->getResultArray();
        $rows = array_map(fn (array $row): array => $this->normalize($row, $today), $rows);
        $medicines = db_connect()->table('medicines')->select('id, medicine_name')->where('status', 'active')->orderBy('medicine_name', 'ASC')->get()->getResultArray();

        return [
            'rows' => $rows,
            'total' => $total,
            'view' => $view,
            'filters' => $filters,
            'medicines' => $medicines,
            'summary' => $summary,
            'thresholds' => [
                'low_stock' => self::LOW_STOCK_THRESHOLD,
                'near_expiry_days' => self::NEAR_EXPIRY_DAYS,
            ],
            'pagination' => [
                'page' => $page,
                'pages' => $pages,
                'page_size' => $pageSize,
                'total' => $total,
            ],
            'today' => $today->format('Y-m-d'),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, int>
     */
    private function summarize(array $rows, DateTimeImmutable $today): array
    {
        $summary = $this->emptySummary();

        foreach ($rows as $row) {
            $normalized = $this->normalize($row, $today);
            $status = $normalized['status'];

            if (array_key_exists($status, $summary)) {
                $summary[$status]++;
            }

            if ($normalized['is_near_expiry'] && !in_array($status, ['expired', 'inactive'], true)) {
                $summary['near_expiry']++;
            }
        }

        return $summary;
    }

    /**
     * @return array<string, int>
     */
    private function emptySummary(): array
    {
        return [
            'available' => 0,
            'low_stock' => 0,
            'near_expiry' => 0,
            'expired' => 0,
            'out_of_stock' => 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalize(array $row, DateTimeImmutable $today): array
    {
        $expiry = $this->parseDate((string) ($row['expiry_date'] ?? ''));
        $days = $expiry === null ? null : (int) $today->diff($expiry)->format('%r%a');
        $quantity = max(0, (int) ($row['quantity_available'] ?? 0));
        $batchStatus = strtolower(trim((string) ($row['batch_status'] ?? 'active')));
        $expired = $days !== null && $days < 0;
        $near = $days !== null && $days >= 0 && $days <= self::NEAR_EXPIRY_DAYS;
        $status = $batchStatus !== 'active' ? 'inactive' : ($expired ? 'expired' : ($quantity <= 0 ? 'out_of_stock' : ($quantity <= self::LOW_STOCK_THRESHOLD ? 'low_stock' : 'available')));
        $labels = ['available' => ['Available', 'success'], 'low_stock' => ['Low stock', 'warning'], 'out_of_stock' => ['Out of stock', 'danger'], 'expired' => ['Expired', 'danger'], 'inactive' => ['Inactive', 'secondary']];
        [$label, $class] = $labels[$status];

        return ['batch_id' => (int) ($row['batch_id'] ?? 0), 'medicine_id' => (int) ($row['medicine_id'] ?? 0), 'medicine_name' => trim((string) ($row['medicine_name'] ?? '')) ?: 'Unnamed medicine', 'batch_number' => trim((string) ($row['batch_number'] ?? '')) ?: 'Not recorded', 'expiry_date' => $expiry?->format('Y-m-d'), 'expiry_display' => $expiry?->format('d-m-Y') ?? 'Not recorded', 'days_to_expiry' => $days, 'quantity_available' => $quantity, 'unit' => trim((string) ($row['unit'] ?? '')) ?: trim((string) ($row['dosage_form'] ?? '')) ?: 'Unit not recorded', 'status' => $status, 'availability_label' => $label, 'availability_class' => $class, 'expiry_label' => $expired ? 'Expired' : ($near ? 'Near expiry' : 'Valid'), 'expiry_class' => $expired ? 'danger' : ($near ? 'warning' : 'success'), 'is_near_expiry' => $near, 'is_available' => in_array($status, ['available', 'low_stock'], true)];
    }

    private function today(): DateTimeImmutable
    {
        $timezone = (string) (config('App')->appTimezone ?: date_default_timezone_get());

        return new DateTimeImmutable('today', new DateTimeZone($timezone));
    }

    private function parseDate(string $value): ?DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value ? $date : null;
    }
}
