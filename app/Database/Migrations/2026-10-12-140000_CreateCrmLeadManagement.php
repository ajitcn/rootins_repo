<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateCrmLeadManagement extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('crm_lead_statuses')) {
            $this->forge->addField([
                'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
                'code' => ['type' => 'VARCHAR', 'constraint' => 50], 'name' => ['type' => 'VARCHAR', 'constraint' => 100],
                'description' => ['type' => 'TEXT', 'null' => true], 'sort_order' => ['type' => 'INT', 'constraint' => 10, 'default' => 0],
                'status' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1], 'created_at' => ['type' => 'DATETIME', 'null' => true], 'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true); $this->forge->addUniqueKey('code'); $this->forge->addUniqueKey('name'); $this->forge->addKey(['status', 'sort_order']); $this->forge->createTable('crm_lead_statuses');
        }
        if (! $this->db->tableExists('crm_lead_sources')) {
            $this->forge->addField([
                'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true], 'code' => ['type' => 'VARCHAR', 'constraint' => 50], 'name' => ['type' => 'VARCHAR', 'constraint' => 100], 'sort_order' => ['type' => 'INT', 'constraint' => 10, 'default' => 0], 'status' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1], 'created_at' => ['type' => 'DATETIME', 'null' => true], 'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true); $this->forge->addUniqueKey('code'); $this->forge->addUniqueKey('name'); $this->forge->addKey(['status', 'sort_order']); $this->forge->createTable('crm_lead_sources');
        }
        $this->seedMasters();

        if (! $this->db->tableExists('crm_leads')) {
            $this->forge->addField([
                'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true], 'lead_number' => ['type' => 'VARCHAR', 'constraint' => 32], 'clinic_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true], 'full_name' => ['type' => 'VARCHAR', 'constraint' => 160], 'mobile_number' => ['type' => 'VARCHAR', 'constraint' => 30], 'mobile_normalized' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true], 'email' => ['type' => 'VARCHAR', 'constraint' => 191, 'null' => true], 'gender' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true], 'date_of_birth' => ['type' => 'DATE', 'null' => true], 'source_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true], 'interested_service' => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true], 'preferred_doctor_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true], 'preferred_specialization_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true], 'lead_status_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true], 'priority' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'normal'], 'assigned_user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true], 'assigned_doctor_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true], 'next_follow_up_date' => ['type' => 'DATE', 'null' => true], 'next_follow_up_time' => ['type' => 'TIME', 'null' => true], 'remarks' => ['type' => 'TEXT', 'null' => true], 'converted_patient_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true], 'converted_appointment_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true], 'converted_at' => ['type' => 'DATETIME', 'null' => true], 'converted_by' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true], 'created_by' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true], 'updated_by' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true], 'created_at' => ['type' => 'DATETIME', 'null' => true], 'updated_at' => ['type' => 'DATETIME', 'null' => true], 'deleted_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true); $this->forge->addUniqueKey('lead_number'); $this->forge->addKey(['clinic_id', 'lead_status_id']); $this->forge->addKey(['clinic_id', 'assigned_user_id']); $this->forge->addKey(['clinic_id', 'next_follow_up_date']); $this->forge->addKey('mobile_normalized'); $this->forge->addForeignKey('clinic_id', 'clinics', 'id', 'RESTRICT', 'CASCADE'); $this->forge->addForeignKey('source_id', 'crm_lead_sources', 'id', 'SET NULL', 'CASCADE'); $this->forge->addForeignKey('lead_status_id', 'crm_lead_statuses', 'id', 'RESTRICT', 'CASCADE'); $this->forge->createTable('crm_leads');
        }
        if (! $this->db->tableExists('crm_lead_followups')) {
            $this->forge->addField([
                'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true], 'follow_up_number' => ['type' => 'VARCHAR', 'constraint' => 32], 'lead_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true], 'clinic_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true], 'patient_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true], 'follow_up_date' => ['type' => 'DATE'], 'follow_up_time' => ['type' => 'TIME'], 'follow_up_type_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true], 'follow_up_status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'scheduled'], 'assigned_user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true], 'remarks' => ['type' => 'TEXT', 'null' => true], 'completed_at' => ['type' => 'DATETIME', 'null' => true], 'completed_by' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true], 'created_by' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true], 'updated_by' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true], 'created_at' => ['type' => 'DATETIME', 'null' => true], 'updated_at' => ['type' => 'DATETIME', 'null' => true], 'deleted_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true); $this->forge->addUniqueKey('follow_up_number'); $this->forge->addKey(['clinic_id', 'follow_up_date']); $this->forge->addKey(['lead_id', 'follow_up_status']); $this->forge->addForeignKey('lead_id', 'crm_leads', 'id', 'CASCADE', 'CASCADE'); $this->forge->addForeignKey('clinic_id', 'clinics', 'id', 'RESTRICT', 'CASCADE'); $this->forge->createTable('crm_lead_followups');
        }
        if (! $this->db->tableExists('crm_lead_history')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true], 'lead_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true], 'clinic_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true], 'event_type' => ['type' => 'VARCHAR', 'constraint' => 60], 'previous_value' => ['type' => 'TEXT', 'null' => true], 'new_value' => ['type' => 'TEXT', 'null' => true], 'remarks' => ['type' => 'TEXT', 'null' => true], 'follow_up_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true], 'actor_user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true], 'created_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true); $this->forge->addKey(['lead_id', 'created_at']); $this->forge->addKey('clinic_id'); $this->forge->addForeignKey('lead_id', 'crm_leads', 'id', 'CASCADE', 'CASCADE'); $this->forge->addForeignKey('clinic_id', 'clinics', 'id', 'RESTRICT', 'CASCADE'); $this->forge->createTable('crm_lead_history');
        }
    }

    public function down()
    {
        $this->forge->dropTable('crm_lead_history', true); $this->forge->dropTable('crm_lead_followups', true); $this->forge->dropTable('crm_leads', true); $this->forge->dropTable('crm_lead_sources', true); $this->forge->dropTable('crm_lead_statuses', true);
    }

    private function seedMasters(): void
    {
        $now = date('Y-m-d H:i:s');
        foreach ([['new', 'New'], ['contacted', 'Contacted'], ['interested', 'Interested'], ['appointment_booked', 'Appointment booked'], ['converted', 'Converted'], ['not_interested', 'Not interested'], ['lost', 'Lost'], ['closed', 'Closed']] as $i => [$code, $name]) {
            if ($this->db->table('crm_lead_statuses')->where('code', $code)->countAllResults() === 0) $this->db->table('crm_lead_statuses')->insert(['code' => $code, 'name' => $name, 'sort_order' => $i + 1, 'status' => 1, 'created_at' => $now, 'updated_at' => $now]);
        }
        foreach (['website', 'referral', 'phone', 'walk_in', 'social_media', 'campaign', 'other'] as $i => $code) {
            if ($this->db->table('crm_lead_sources')->where('code', $code)->countAllResults() === 0) $this->db->table('crm_lead_sources')->insert(['code' => $code, 'name' => ucwords(str_replace('_', ' ', $code)), 'sort_order' => $i + 1, 'status' => 1, 'created_at' => $now, 'updated_at' => $now]);
        }
    }
}
