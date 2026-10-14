<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

final class FollowUpStatusSeeder extends Seeder
{
    public function run()
    {
        if (! $this->db->tableExists('follow_up_statuses')) {
            return;
        }

        foreach ([
            ['code' => 'draft', 'name' => 'Draft'],
            ['code' => 'in_progress', 'name' => 'In progress'],
            ['code' => 'completed', 'name' => 'Completed'],
            ['code' => 'cancelled', 'name' => 'Cancelled'],
        ] as $option) {
            $normalized = strtolower(trim($option['code']));

            if ($this->db->table('follow_up_statuses')->where('scope_key', 'global')->where('normalized_code', $normalized)->countAllResults() > 0) {
                continue;
            }
            $now = date('Y-m-d H:i:s');
            $this->db->table('follow_up_statuses')->insert([
                'code' => $option['code'],
                'name' => $option['name'],
                'normalized_code' => $normalized,
                'status' => 1,
                'scope_key' => 'global',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
