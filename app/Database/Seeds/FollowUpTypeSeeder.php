<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

final class FollowUpTypeSeeder extends Seeder
{
    public function run()
    {
        if (! $this->db->tableExists('follow_up_types')) {
            return;
        }

        foreach (['Routine', 'Review', 'Other'] as $name) {
            $normalized = mb_strtolower(trim($name));

            if ($this->db->table('follow_up_types')->where('scope_key', 'global')->where('normalized_name', $normalized)->countAllResults() > 0) {
                continue;
            }
            $now = date('Y-m-d H:i:s');
            $this->db->table('follow_up_types')->insert([
                'name' => $name,
                'normalized_name' => $normalized,
                'status' => 1,
                'scope_key' => 'global',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
