<?php

namespace Admin\Models;

use CodeIgniter\Database\BaseConnection;

/**
 * Database-backed prescription option catalog.
 *
 * Options are stored in dedicated tables so unrelated catalogs cannot share
 * ids or validation rules. Legacy rows remain available for reconciliation.
 */
class PrescriptionOptionModel
{
    public const TYPES = [
        'duration' => 'prescription-duration',
        'frequency' => 'prescription-frequency',
        'dose' => 'prescription-dose',
        'intake' => 'prescription-intake',
        'status' => 'prescription-statuses',
    ];

    private const TABLES = [
        'prescription-duration' => 'prescription_durations',
        'prescription-frequency' => 'prescription_frequencies',
        'prescription-dose' => 'prescription_doses',
        'prescription-intake' => 'prescription_intakes',
        'prescription-statuses' => 'prescription_status_options',
    ];

    public static function defaultOptions(): array
    {
        return [
            'prescription-duration' => [
                ['code' => '1', 'name' => '1 day'],
                ['code' => '3', 'name' => '3 days'],
                ['code' => '5', 'name' => '5 days'],
                ['code' => '7', 'name' => '7 days'],
                ['code' => '10', 'name' => '10 days'],
                ['code' => '14', 'name' => '14 days'],
                ['code' => '21', 'name' => '21 days'],
                ['code' => '30', 'name' => '30 days'],
                ['code' => '60', 'name' => '60 days'],
                ['code' => '90', 'name' => '90 days'],
            ],
            'prescription-frequency' => [
                ['code' => 'once_daily', 'name' => 'Once daily'],
                ['code' => 'twice_daily', 'name' => 'Twice daily'],
                ['code' => 'three_times_daily', 'name' => 'Three times daily'],
                ['code' => 'at_bedtime', 'name' => 'At bedtime'],
                ['code' => 'as_needed', 'name' => 'As needed'],
            ],
            'prescription-dose' => [
                ['code' => '1_tablet', 'name' => '1 tablet'],
                ['code' => '2_tablets', 'name' => '2 tablets'],
                ['code' => '1_capsule', 'name' => '1 capsule'],
                ['code' => '5_ml', 'name' => '5 ml'],
                ['code' => '10_ml', 'name' => '10 ml'],
            ],
            'prescription-intake' => [
                ['code' => 'before_food', 'name' => 'Before food'],
                ['code' => 'after_food', 'name' => 'After food'],
                ['code' => 'with_food', 'name' => 'With food'],
                ['code' => 'empty_stomach', 'name' => 'Empty stomach'],
                ['code' => 'at_bedtime', 'name' => 'At bedtime'],
            ],
            'prescription-statuses' => [
                ['code' => 'draft', 'name' => 'Draft'],
                ['code' => 'submitted', 'name' => 'Submitted'],
            ],
        ];
    }

    public function __construct(private ?BaseConnection $db = null)
    {
        $this->db ??= db_connect();
    }

    public function activeOptions(string $type, ?int $clinicId = null): array
    {
        $table = self::TABLES[$type] ?? null;

        if ($table === null) {
            return [];
        }

        $builder = $this->db->table($table)
            ->select('id, name, code, clinic_id, scope_key')
            ->where('status', 1);

        if ($clinicId !== null) {
            $builder->groupStart()
                ->where('clinic_id', $clinicId)
                ->orWhere('clinic_id', null)
                ->groupEnd();
        }

        $options = $builder
            ->orderBy('clinic_id', 'DESC')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        if ($type === self::TYPES['duration']) {
            $options = array_values(array_filter($options, static fn (array $option): bool => ctype_digit((string) $option['code']) && (int) $option['code'] >= 1 && (int) $option['code'] <= 3650));
        }

        return array_map(static function (array $option): array {
            $option['master_type'] = '';

            return $option;
        }, $options);
    }

    public function activeById(string $type, int $id, ?int $clinicId = null): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $table = self::TABLES[$type] ?? null;

        if ($table === null) {
            return null;
        }

        $builder = $this->db->table($table)
            ->where('id', $id)
            ->where('status', 1);

        if ($clinicId !== null) {
            $builder->groupStart()
                ->where('clinic_id', $clinicId)
                ->orWhere('clinic_id', null)
                ->groupEnd();
        }

        $option = $builder->get()->getRowArray() ?: null;

        if ($option !== null && $type === self::TYPES['duration'] && (! ctype_digit((string) $option['code']) || (int) $option['code'] < 1 || (int) $option['code'] > 3650)) {
            return null;
        }

        return $option;
    }

    public function activeByCode(string $type, string $code, ?int $clinicId = null): ?array
    {
        $code = trim($code);

        if ($code === '') {
            return null;
        }

        $table = self::TABLES[$type] ?? null;

        if ($table === null) {
            return null;
        }

        $builder = $this->db->table($table)
            ->where('normalized_code', strtolower($code))
            ->where('status', 1);

        if ($clinicId !== null) {
            $builder->groupStart()
                ->where('clinic_id', $clinicId)
                ->orWhere('clinic_id', null)
                ->groupEnd();
        }

        $option = $builder->orderBy('clinic_id', 'DESC')->get()->getRowArray() ?: null;

        if ($option !== null && $type === self::TYPES['duration'] && (! ctype_digit((string) $option['code']) || (int) $option['code'] < 1 || (int) $option['code'] > 3650)) {
            return null;
        }

        return $option;
    }
}
