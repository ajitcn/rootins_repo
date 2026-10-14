<?php

namespace App\Database\Migrations;

use Admin\Models\PrescriptionOptionModel;
use CodeIgniter\Database\Migration;

class SeedPrescriptionOptions extends Migration
{
    public function up()
    {
        $now = date('Y-m-d H:i:s');

        foreach (PrescriptionOptionModel::defaultOptions() as $type => $options) {
            foreach ($options as $option) {
                $exists = $this->db->table('admin_master_records')
                    ->where('master_type', $type)
                    ->where('code', $option['code'])
                    ->where('clinic_id', null)
                    ->countAllResults();

                if ($exists === 0) {
                    $this->db->table('admin_master_records')->insert([
                        'master_type' => $type,
                        'name' => $option['name'],
                        'code' => $option['code'],
                        'status' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }

        $legacyFields = [
            'duration' => static fn (array $row): array => ['code' => (string) $row['duration'], 'name' => (string) $row['duration'] . ' days'],
            'frequency' => static fn (array $row): array => ['code' => self::code($row['frequency']), 'name' => (string) $row['frequency']],
            'dose' => static fn (array $row): array => ['code' => self::code($row['dosage']), 'name' => (string) $row['dosage']],
            'intake' => static fn (array $row): array => ['code' => self::code($row['instructions']), 'name' => (string) $row['instructions']],
        ];

        foreach ($legacyFields as $field => $makeOption) {
            $column = $field === 'dose' ? 'dosage' : ($field === 'intake' ? 'instructions' : $field);
            $rows = $this->db->table('prescription_items')
                ->select($column)
                ->where($column . ' !=', '')
                ->groupBy($column)
                ->get()
                ->getResultArray();

            foreach ($rows as $row) {
                $option = $makeOption($row);

                if ($option['code'] === '' || mb_strlen($option['name']) > 255) {
                    continue;
                }
                $exists = $this->db->table('admin_master_records')
                    ->where('master_type', PrescriptionOptionModel::TYPES[$field])
                    ->where('code', $option['code'])
                    ->where('clinic_id', null)
                    ->countAllResults();

                if ($exists === 0) {
                    $this->db->table('admin_master_records')->insert([
                        'master_type' => PrescriptionOptionModel::TYPES[$field],
                        'name' => $option['name'],
                        'code' => $option['code'],
                        'status' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    public function down()
    {
        foreach (PrescriptionOptionModel::defaultOptions() as $type => $options) {
            foreach ($options as $option) {
                $this->db->table('admin_master_records')
                    ->where('master_type', $type)
                    ->where('code', $option['code'])
                    ->where('clinic_id', null)
                    ->delete();
            }
        }
    }

    private static function code(?string $value): string
    {
        return strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '_', (string) $value), '_'));
    }
}
