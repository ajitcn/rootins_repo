<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Stores the external booking identity without changing the core appointment
 * schema or duplicating patient records.
 */
class CreateShopifyAppointmentBookings extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('shopify_appointment_bookings')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 10,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'shopify_store_domain' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
            ],
            'external_reference' => [
                'type' => 'VARCHAR',
                'constraint' => 191,
            ],
            'idempotency_key' => [
                'type' => 'VARCHAR',
                'constraint' => 191,
            ],
            'patient_id' => [
                'type' => 'INT',
                'constraint' => 10,
                'unsigned' => true,
            ],
            'clinic_id' => [
                'type' => 'INT',
                'constraint' => 10,
                'unsigned' => true,
            ],
            'appointment_id' => [
                'type' => 'INT',
                'constraint' => 10,
                'unsigned' => true,
                'null' => true,
            ],
            'status' => [
                'type' => 'VARCHAR',
                'constraint' => 30,
                'default' => 'created',
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
        $this->forge->addUniqueKey(['shopify_store_domain', 'external_reference'], 'shopify_booking_external_unique');
        $this->forge->addUniqueKey(['shopify_store_domain', 'idempotency_key'], 'shopify_booking_idempotency_unique');
        $this->forge->addKey(['patient_id', 'clinic_id']);
        $this->forge->addKey('appointment_id');
        $this->forge->addForeignKey('patient_id', 'patients', 'id', 'CASCADE', 'CASCADE', 'shopify_booking_patient_fk');
        $this->forge->addForeignKey('clinic_id', 'clinics', 'id', 'CASCADE', 'CASCADE', 'shopify_booking_clinic_fk');
        $this->forge->addForeignKey('appointment_id', 'appointments', 'id', 'SET NULL', 'CASCADE', 'shopify_booking_appointment_fk');
        $this->forge->createTable('shopify_appointment_bookings');
    }

    public function down()
    {
        $this->forge->dropTable('shopify_appointment_bookings', true);
    }
}
