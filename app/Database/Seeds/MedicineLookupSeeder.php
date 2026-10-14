<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

final class MedicineLookupSeeder extends Seeder
{
    public function run()
    {
        $this->seedNames('medicine_forms', ['Tablet', 'Capsule', 'Syrup', 'Injection', 'Cream', 'Drops']);
        $this->seedNames('medicine_units', ['mg', 'g', 'ml', 'tablet', 'capsule', 'piece']);
        $this->seedNames('medicine_manufacturers', ['Generic']);

        foreach ([['active', 'Active'], ['inactive', 'Inactive']] as [$code, $name]) {
            $normalized = strtolower($code);

            if ($this->db->table('medicine_status_options')->where('normalized_code', $normalized)->countAllResults() === 0) {
                $this->db->table('medicine_status_options')->insert(['code' => $code, 'name' => $name, 'normalized_code' => $normalized, 'status' => 1]);
            }
        }
    }

    private function seedNames(string $table, array $names): void
    {
        foreach ($names as $name) {
            $normalized = strtolower(trim($name));

            if ($this->db->table($table)->where('normalized_name', $normalized)->countAllResults() === 0) {
                $this->db->table($table)->insert(['name' => $name, 'normalized_name' => $normalized, 'status' => 1]);
            }
        }
    }
}
