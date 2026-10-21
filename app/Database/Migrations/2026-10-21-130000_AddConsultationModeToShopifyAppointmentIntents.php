<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddConsultationModeToShopifyAppointmentIntents extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('shopify_appointment_intents')) return;

        $fields = $this->db->getFieldNames('shopify_appointment_intents');
        if (! in_array('consultation_mode', $fields, true)) {
            $this->forge->addColumn('shopify_appointment_intents', [
                'consultation_mode' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'in_person', 'after' => 'appointment_time'],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->tableExists('shopify_appointment_intents') && in_array('consultation_mode', $this->db->getFieldNames('shopify_appointment_intents'), true)) {
            $this->forge->dropColumn('shopify_appointment_intents', 'consultation_mode');
        }
    }
}
