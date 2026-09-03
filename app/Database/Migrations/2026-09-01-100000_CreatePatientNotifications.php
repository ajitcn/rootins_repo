<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
final class CreatePatientNotifications extends Migration
{
    public function up()
    {
        $this->forge->addField(['id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true], 'patient_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true], 'clinic_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true], 'title' => ['type' => 'VARCHAR', 'constraint' => 160], 'message' => ['type' => 'TEXT'], 'channel' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'in_app'], 'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'unread'], 'read_at' => ['type' => 'DATETIME', 'null' => true], 'created_at' => ['type' => 'DATETIME', 'null' => true], 'updated_at' => ['type' => 'DATETIME', 'null' => true]]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['patient_id', 'status', 'created_at']);
        $this->forge->addKey(['clinic_id', 'created_at']);
        $this->forge->addForeignKey('patient_id', 'patients', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('clinic_id', 'clinics', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('patient_notifications');
    }
    public function down()
    {
        $this->forge->dropTable('patient_notifications', true);
    }
}
