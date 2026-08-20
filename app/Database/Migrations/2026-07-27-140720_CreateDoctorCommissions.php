<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDoctorCommissions extends Migration
{
    public function up()
    {
        $this->forge->addField([

            'id' => [
                'type'           => 'INT',
                'constraint'     => 10,
                'unsigned'       => true,
                'auto_increment' => true,
            ],

            'doctor_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],

            'commission_for' => [
                'type'       => 'ENUM',
                'constraint' => [
                    'CONSULTATION',
                    'SERVICE',
                    'PROCEDURE'
                ],
                'default' => 'CONSULTATION',
            ],

            'reference_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
                'comment'    => 'Service/Procedure ID when applicable',
            ],

            'commission_type' => [
                'type'       => 'ENUM',
                'constraint' => [
                    'PERCENTAGE',
                    'FIXED'
                ],
            ],

            'commission_value' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
            ],

            'effective_from' => [
                'type' => 'DATE',
            ],

            'effective_to' => [
                'type' => 'DATE',
                'null' => true,
            ],

            'status' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],

            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],

            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],

        ]);

        $this->forge->addKey('id', true);

        $this->forge->addKey('doctor_id');
         

        $this->forge->addForeignKey(
            'doctor_id',
            'doctors',
            'id',
            'CASCADE',
            'CASCADE'
        );

        

        $this->forge->createTable('doctor_commissions');
    }

    public function down()
    {
        $this->forge->dropTable('doctor_commissions');
    }
}