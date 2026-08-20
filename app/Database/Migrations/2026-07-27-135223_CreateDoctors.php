<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDoctors extends Migration
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
 
            'clinic_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],

            'city_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],

            'doctor_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
            ],

            'first_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],

            'last_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],

            'gender' => [
                'type'       => 'ENUM',
                'constraint' => ['Male','Female','Other'],
                'null'       => true,
            ],

            'dob' => [
                'type' => 'DATE',
                'null' => true,
            ],

            'qualification' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],

            'experience' => [
                'type'       => 'SMALLINT',
                'constraint' => 3,
                'default'    => 0,
            ],

            'registration_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],

            'mobile' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
            ],

            'email' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
            ],

            'address' => [
                'type' => 'TEXT',
                'null' => true,
            ],

            'consultation_duration' => [
                'type'       => 'SMALLINT',
                'constraint' => 3,
                'default'    => 15,
            ],

            'signature' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],

            'profile_photo' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],

            'notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],

            'online_booking' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],

            'display_order' => [
                'type'       => 'SMALLINT',
                'constraint' => 5,
                'default'    => 0,
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

            'deleted_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],

        ]);

        $this->forge->addKey('id', true);

        $this->forge->addUniqueKey('doctor_code');
        $this->forge->addUniqueKey('registration_number');
 
        $this->forge->addKey('clinic_id');
        $this->forge->addKey('city_id');
        $this->forge->addKey('mobile');
        $this->forge->addKey('status');
 
 
        $this->forge->addForeignKey(
            'clinic_id',
            'clinics',
            'id',
            'RESTRICT',
            'CASCADE'
        );
        $this->forge->addForeignKey(
            'city_id',
            'cities',
            'id',
            'RESTRICT',
            'CASCADE'
        );

        $this->forge->createTable('doctors');
    }

    public function down()
    {
        $this->forge->dropTable('doctors');
    }
}