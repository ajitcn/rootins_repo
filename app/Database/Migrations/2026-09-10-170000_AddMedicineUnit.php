<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddMedicineUnit extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('unit', 'medicines')) {
            $this->forge->addColumn('medicines', [
                'unit' => [
                    'type' => 'VARCHAR',
                    'constraint' => 100,
                    'null' => true,
                    'after' => 'strength',
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('unit', 'medicines')) {
            $this->forge->dropColumn('medicines', 'unit');
        }
    }
}
