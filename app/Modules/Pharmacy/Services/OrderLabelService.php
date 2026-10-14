<?php

namespace Pharmacy\Services;

use DateTimeImmutable;

final class OrderLabelService
{
    public function __construct(private readonly PharmacyScope $scope)
    {
    }

    /**
     * Builds only operational label data for one scoped pharmacy order.
     * Payment internals, audit data, credentials, and private clinical notes
     * are intentionally excluded from this response.
     *
     * @return array{http:int, body:array<string, mixed>}
     */
    public function prepare(int $orderId, string $labelType): array
    {
        $clinicId = $this->scope->clinicId();

        if ($clinicId === null || $orderId <= 0) {
            return $this->result(404, 'Order not found.');
        }

        $db = db_connect();
        $orderSelect = [
            'orders.id',
            'orders.order_number',
            'orders.status',
            'orders.created_at',
            'orders.delivery_provider',
            'orders.tracking_reference',
            'orders.delivery_address_snapshot',
            'orders.delivery_contact_snapshot',
            'clinics.clinic_name',
            'clinics.mobile AS clinic_mobile',
            'clinics.email AS clinic_email',
            'clinics.address AS clinic_address',
            'patients.first_name',
            'patients.middle_name',
            'patients.last_name',
            'patients.mobile_number',
            'patients.country_code',
            'patients.address AS patient_address',
            'patients.postal_code AS patient_postal_code',
            'patient_countries.name AS patient_country',
            'patient_states.name AS patient_state',
            'patient_cities.name AS patient_city',
            'prescriptions.prescription_number',
        ];

        $hasLocality = $db->fieldExists('locality_id', 'patients') && $db->tableExists('localities');

        if ($hasLocality) {
            $orderSelect[] = 'patient_localities.name AS patient_locality';
        }

        foreach (['delivery_provider', 'tracking_reference', 'delivery_address_snapshot', 'delivery_contact_snapshot'] as $field) {
            if (!$db->fieldExists($field, 'medicine_orders')) {
                $orderSelect = array_values(array_filter(
                    $orderSelect,
                    static fn (string $column): bool => !str_contains($column, 'orders.' . $field)
                ));
            }
        }

        $orderQuery = $db->table('medicine_orders AS orders')
            ->select(implode(', ', $orderSelect))
            ->join('clinics', 'clinics.id = orders.clinic_id')
            ->join('patients', 'patients.id = orders.patient_id')
            ->join('countries AS patient_countries', 'patient_countries.id = patients.country_id', 'left')
            ->join('states AS patient_states', 'patient_states.id = patients.state_id', 'left')
            ->join('cities AS patient_cities', 'patient_cities.id = patients.city_id', 'left');

        if ($hasLocality) {
            $orderQuery->join('localities AS patient_localities', 'patient_localities.id = patients.locality_id', 'left');
        }

        $order = $orderQuery
            ->join('prescriptions', 'prescriptions.id = orders.prescription_id', 'left')
            ->where('orders.id', $orderId)
            ->where('orders.clinic_id', $clinicId)
            ->where('patients.clinic_id', $clinicId)
            ->groupStart()
                ->where('prescriptions.id IS NULL', null, false)
                ->orWhere('prescriptions.clinic_id', $clinicId)
            ->groupEnd()
            ->get(1)
            ->getRowArray();

        if ($order === null) {
            return $this->result(404, 'Order not found.');
        }

        if (($order['status'] ?? '') === 'cancelled') {
            return $this->result(422, 'Labels are not available for cancelled orders.');
        }

        if (!in_array((string) ($order['status'] ?? ''), ['pending', 'fulfilled'], true)) {
            return $this->result(422, 'Labels are not available for this order status.');
        }

        $itemSelect = [
            'items.medicine_name',
            'items.quantity',
            'items.dosage',
            'items.frequency',
            'items.instructions',
            'items.batch_number',
        ];
        $itemQuery = $db->table('medicine_order_items AS items');

        $itemQuery->join('medicines', 'medicines.id = items.medicine_id', 'left');

        foreach (['strength', 'dosage_form', 'unit'] as $medicineField) {
            if ($db->fieldExists($medicineField, 'medicines')) {
                $itemSelect[] = 'medicines.' . $medicineField . ' AS medicine_' . $medicineField;
            }
        }

        if ($db->fieldExists('inventory_batch_id', 'medicine_order_items')) {
            $itemSelect[] = 'batches.expiry_date';
            $itemQuery->join(
                'medicine_inventory_batches AS batches',
                'batches.id = items.inventory_batch_id',
                'left'
            );
        }

        $items = $itemQuery
            ->select(implode(', ', $itemSelect))
            ->where('items.order_id', $orderId)
            ->orderBy('items.id', 'ASC')
            ->get()
            ->getResultArray();

        if ($items === []) {
            return $this->result(422, 'Labels cannot be prepared because the order has no medicines.');
        }

        $patientName = trim(implode(' ', array_filter([
            $order['first_name'] ?? '',
            $order['middle_name'] ?? '',
            $order['last_name'] ?? '',
        ]))) ?: 'Patient not recorded';
        $patientMobile = trim(implode(' ', array_filter([
            (string) ($order['country_code'] ?? ''),
            (string) ($order['mobile_number'] ?? ''),
        ]))) ?: 'Not recorded';
        $orderDate = $this->displayDate((string) ($order['created_at'] ?? ''));
        $deliveryAddress = $this->deliveryAddress($order);
        $medicineLabels = [];
        $itemCount = count($items);

        foreach ($items as $index => $item) {
            $strength = trim((string) ($item['medicine_strength'] ?? ''));
            $form = trim((string) ($item['medicine_dosage_form'] ?? ''));
            $unit = trim((string) ($item['medicine_unit'] ?? ''));

            $medicineLabels[] = [
                'clinic_name' => (string) ($order['clinic_name'] ?? 'Clinic'),
                'medicine_name' => (string) ($item['medicine_name'] ?? 'Medicine not recorded'),
                'strength' => $strength,
                'form' => $form,
                'unit' => $unit,
                'quantity' => (int) ($item['quantity'] ?? 0),
                'dosage' => (string) ($item['dosage'] ?? ''),
                'frequency' => (string) ($item['frequency'] ?? ''),
                'instructions' => (string) ($item['instructions'] ?? ''),
                'batch_number' => (string) ($item['batch_number'] ?? ''),
                'expiry_date' => $this->displayDate((string) ($item['expiry_date'] ?? '')),
                'sequence' => ($index + 1) . ' of ' . $itemCount,
                'order_number' => (string) ($order['order_number'] ?? ''),
            ];
        }

        $base = [
            'clinic_name' => (string) ($order['clinic_name'] ?? 'Clinic'),
            'clinic_contact' => trim(implode(' · ', array_filter([
                (string) ($order['clinic_mobile'] ?? ''),
                (string) ($order['clinic_email'] ?? ''),
                (string) ($order['clinic_address'] ?? ''),
            ]))),
            'order_number' => (string) ($order['order_number'] ?? ''),
            'patient_name' => $patientName,
            'patient_mobile' => $patientMobile,
            'delivery_address' => $deliveryAddress,
            'delivery_contact' => (string) ($order['delivery_contact_snapshot'] ?? $patientMobile),
            'order_date' => $orderDate,
            'provider' => (string) ($order['delivery_provider'] ?? ''),
            'tracking_reference' => (string) ($order['tracking_reference'] ?? ''),
            'prescription_number' => (string) ($order['prescription_number'] ?? ''),
            'barcode_value' => (string) ($order['order_number'] ?? ''),
            'sequence' => '1 of 1',
        ];

        $labels = [
            'order' => $base,
            'patient' => [
                'clinic_name' => $base['clinic_name'],
                'order_number' => $base['order_number'],
                'patient_name' => $base['patient_name'],
                'patient_mobile' => $base['patient_mobile'],
                'order_date' => $base['order_date'],
                'prescription_number' => $base['prescription_number'],
                'item_count' => $itemCount,
                'sequence' => '1 of 1',
            ],
            'medicine' => $medicineLabels,
        ];

        return [
            'http' => 200,
            'body' => [
                'status' => true,
                'data' => [
                    'order' => [
                        'order_number' => $base['order_number'],
                        'status' => (string) ($order['status'] ?? ''),
                        'order_date' => $base['order_date'],
                    ],
                    'labels' => $labels,
                    'can_print' => $this->scope->can('pharmacy.order.label.print'),
                    'selected_type' => $labelType,
                ],
            ],
        ];
    }

    /** @return array{http:int, body:array<string, mixed>} */
    private function result(int $http, string $message): array
    {
        return [
            'http' => $http,
            'body' => [
                'status' => false,
                'msg' => $message,
            ],
        ];
    }

    private function displayDate(string $value): string
    {
        $iso = substr($value, 0, 10);

        if ($iso === '') {
            return '';
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $iso);

        return $date !== false && $date->format('Y-m-d') === $iso
            ? $date->format('d-m-Y')
            : '';
    }

    /**
     * Prefer the historical order snapshot and fall back to the patient's
     * current address only when the snapshot was not captured.
     */
    private function deliveryAddress(array $order): string
    {
        $snapshot = trim((string) ($order['delivery_address_snapshot'] ?? ''));

        if ($snapshot !== '') {
            return $snapshot;
        }

        $parts = array_filter([
            trim((string) ($order['patient_address'] ?? '')),
            trim((string) ($order['patient_locality'] ?? '')),
            trim((string) ($order['patient_city'] ?? '')),
            trim((string) ($order['patient_state'] ?? '')),
            trim((string) ($order['patient_country'] ?? '')),
            trim((string) ($order['patient_postal_code'] ?? '')),
        ]);

        return $parts === [] ? 'Address not recorded' : implode(', ', $parts);
    }
}
