<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddCrossModuleOrderIntegrationFields extends Migration
{
    public function up(): void
    {
        if (!$this->db->fieldExists('unit_price', 'medicines')) {
            $this->forge->addColumn('medicines', ['unit_price' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0, 'after' => 'description']]);
        }

        if (!$this->db->fieldExists('event_key', 'patient_notifications')) {
            $this->forge->addColumn('patient_notifications', ['event_key' => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true, 'after' => 'message'], 'related_entity_type' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true, 'after' => 'event_key'], 'related_entity_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'related_entity_type']]);
            $this->db->query('ALTER TABLE patient_notifications ADD UNIQUE KEY patient_notifications_event_key_unique (event_key)');
            $this->db->query('ALTER TABLE patient_notifications ADD KEY patient_notifications_related_entity (related_entity_type, related_entity_id)');
        }
    }

    public function down(): void
    {
        if ($this->db->fieldExists('event_key', 'patient_notifications')) {
            try {
                $this->db->query('ALTER TABLE patient_notifications DROP INDEX patient_notifications_event_key_unique');
            } catch (\Throwable) {
            }

            try {
                $this->db->query('ALTER TABLE patient_notifications DROP INDEX patient_notifications_related_entity');
            } catch (\Throwable) {
            }
            $this->forge->dropColumn('patient_notifications', ['event_key', 'related_entity_type', 'related_entity_id']);
        }

        if ($this->db->fieldExists('unit_price', 'medicines')) {
            $this->forge->dropColumn('medicines', 'unit_price');
        }
    }
}
