<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePatients extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'clinic_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'first_name' => ['type' => 'VARCHAR', 'constraint' => 100],
            'middle_name' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'last_name' => ['type' => 'VARCHAR', 'constraint' => 100],
            'gender' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'date_of_birth' => ['type' => 'DATE', 'null' => true],
            'email' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'country_code' => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'mobile_number' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'address' => ['type' => 'TEXT', 'null' => true],
            // countries.id is SMALLINT UNSIGNED.
            'country_id' => ['type' => 'SMALLINT', 'unsigned' => true, 'null' => true],
            'state_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'city_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'postal_code' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'status' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME'],
            'updated_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('user_id');
        $this->forge->addKey('clinic_id');
        $this->forge->addKey('country_id');
        $this->forge->addKey('state_id');
        $this->forge->addKey('city_id');
        $this->forge->addKey('status');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('clinic_id', 'clinics', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('country_id', 'countries', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('state_id', 'states', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('city_id', 'cities', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('patients');
    }

    public function down()
    {
        $this->forge->dropTable('patients');
    }
}
