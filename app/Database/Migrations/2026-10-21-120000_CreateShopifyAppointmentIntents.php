<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateShopifyAppointmentIntents extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('shopify_appointment_intents')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'public_reference' => ['type' => 'VARCHAR', 'constraint' => 80],
            'shopify_store_domain' => ['type' => 'VARCHAR', 'constraint' => 255],
            'shopify_customer_id' => ['type' => 'VARCHAR', 'constraint' => 191],
            'patient_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'patient_reference' => ['type' => 'VARCHAR', 'constraint' => 191],
            'clinic_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'doctor_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'service_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'appointment_date' => ['type' => 'DATE'],
            'appointment_time' => ['type' => 'TIME'],
            'duration_minutes' => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true],
            'amount' => ['type' => 'DECIMAL', 'constraint' => '12,2'],
            'currency' => ['type' => 'CHAR', 'constraint' => 3, 'default' => 'INR'],
            'idempotency_key' => ['type' => 'VARCHAR', 'constraint' => 191],
            'status' => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'pending_payment'],
            'shopify_order_id' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'shopify_order_number' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'appointment_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'expires_at' => ['type' => 'DATETIME'],
            'paid_at' => ['type' => 'DATETIME', 'null' => true],
            'failure_reason' => ['type' => 'VARCHAR', 'constraint' => 191, 'null' => true],
            'created_at' => ['type' => 'DATETIME'],
            'updated_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['shopify_store_domain', 'public_reference'], 'shopify_intent_reference_unique');
        $this->forge->addUniqueKey(['shopify_store_domain', 'idempotency_key'], 'shopify_intent_idempotency_unique');
        $this->forge->addUniqueKey(['shopify_store_domain', 'shopify_order_id'], 'shopify_intent_order_unique');
        $this->forge->addKey(['doctor_id', 'appointment_date', 'appointment_time', 'status']);
        $this->forge->addKey(['expires_at', 'status']);
        $this->forge->addForeignKey('patient_id', 'patients', 'id', 'CASCADE', 'CASCADE', 'shopify_intent_patient_fk');
        $this->forge->addForeignKey('clinic_id', 'clinics', 'id', 'CASCADE', 'CASCADE', 'shopify_intent_clinic_fk');
        $this->forge->addForeignKey('doctor_id', 'doctors', 'id', 'CASCADE', 'CASCADE', 'shopify_intent_doctor_fk');
        $this->forge->addForeignKey('service_id', 'admin_services', 'id', 'RESTRICT', 'CASCADE', 'shopify_intent_service_fk');
        $this->forge->addForeignKey('appointment_id', 'appointments', 'id', 'SET NULL', 'CASCADE', 'shopify_intent_appointment_fk');
        $this->forge->createTable('shopify_appointment_intents');
    }

    public function down()
    {
        $this->forge->dropTable('shopify_appointment_intents', true);
    }
}
