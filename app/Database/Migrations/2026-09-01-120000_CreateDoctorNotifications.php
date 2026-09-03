<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
final class CreateDoctorNotifications extends Migration
{
    public function up()
    {
        $this->forge->addField(['id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true], 'doctor_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true], 'clinic_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true], 'title' => ['type' => 'VARCHAR', 'constraint' => 160], 'message' => ['type' => 'TEXT'], 'channel' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'in_app'], 'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'unread'], 'read_at' => ['type' => 'DATETIME', 'null' => true], 'created_at' => ['type' => 'DATETIME', 'null' => true], 'updated_at' => ['type' => 'DATETIME', 'null' => true]]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['doctor_id', 'status', 'created_at']);
        $this->forge->addKey(['clinic_id', 'created_at']);
        $this->forge->addForeignKey('doctor_id', 'doctors', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('clinic_id', 'clinics', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('doctor_notifications');
    }
    public function down()
    {
        $this->forge->dropTable('doctor_notifications', true);
    }
}
