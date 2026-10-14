<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateClinics extends Migration
{
    public function up()
    {
        $this->forge->addField(['id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true], 'clinic_name' => ['type' => 'VARCHAR', 'constraint' => 150], 'clinic_code' => ['type' => 'VARCHAR', 'constraint' => 20], 'contact_person' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true], 'mobile' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true], 'email' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true], 'address' => ['type' => 'TEXT', 'null' => true], 'city_id' => ['type' => 'INT', 'unsigned' => true, 'null' => false], 'state_id' => ['type' => 'INT', 'unsigned' => true, 'null' => false], 'country_id' => ['type' => 'SMALLINT', 'unsigned' => true, 'null' => false], 'pincode' => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true], 'logo' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true], 'gst_number' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true], 'registration_number' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true], 'status' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1, 'comment' => '1=Active,0=Inactive'], 'created_at' => ['type' => 'DATETIME', 'null' => true], 'updated_at' => ['type' => 'DATETIME', 'null' => true], 'deleted_at' => ['type' => 'DATETIME', 'null' => true]]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('clinic_code');
        $this->forge->addUniqueKey('email');
        $this->forge->addKey('status');
        $this->forge->addKey('country_id');
        $this->forge->addKey('city_id');
        $this->forge->addKey('state_id');
        $this->forge->addForeignKey('country_id', 'countries', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('state_id', 'states', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('city_id', 'cities', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('clinics');
    }

    public function down()
    {
        $this->forge->dropTable('clinics');
    }
}
