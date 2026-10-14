<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAppointmentServices extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 10,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'appointment_id' => [
                'type' => 'INT',
                'constraint' => 10,
                'unsigned' => true,
            ],
            'service_id' => [
                'type' => 'INT',
                'constraint' => 10,
                'unsigned' => true,
                'null' => true,
            ],
            'service_name' => [
                'type' => 'VARCHAR',
                'constraint' => 150,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('appointment_id');
        $this->forge->addKey('service_id');
        $this->forge->addUniqueKey(['appointment_id', 'service_name']);
        $this->forge->addForeignKey('appointment_id', 'appointments', 'id', 'CASCADE', 'CASCADE', 'appointment_services_appointment_fk');
        $this->forge->addForeignKey('service_id', 'admin_master_records', 'id', 'SET NULL', 'CASCADE', 'appointment_services_service_fk');
        $this->forge->createTable('appointment_services');

        $this->db->query(
            "INSERT INTO appointment_services (appointment_id, service_id, service_name, created_at)
             SELECT id, NULL, service_name, COALESCE(created_at, NOW())
             FROM appointments
             WHERE service_name IS NOT NULL AND TRIM(service_name) <> ''",
        );
    }

    public function down()
    {
        $this->forge->dropTable('appointment_services', true);
    }
}
