<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class MakeMedicineOrderPrescriptionNullable extends Migration
{
    public function up()
    {
        $this->forge->modifyColumn('medicine_orders', [
            'prescription_id' => [
                'type' => 'INT',
                'constraint' => 10,
                'unsigned' => true,
                'null' => true,
            ],
        ]);
    }

    public function down()
    {
        $this->forge->modifyColumn('medicine_orders', [
            'prescription_id' => [
                'type' => 'INT',
                'constraint' => 10,
                'unsigned' => true,
                'null' => false,
            ],
        ]);
    }
}
