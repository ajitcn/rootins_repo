<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddAppointmentWaitingQueue extends Migration
{
    private const QUEUE_INDEX = 'appointments_clinic_date_queue_position_index';

    public function up()
    {
        $this->forge->addColumn('appointments', [
            'queue_position' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'checked_in_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey(
            ['clinic_id', 'appointment_date', 'queue_position'],
            false,
            false,
            self::QUEUE_INDEX
        );
        $this->forge->processIndexes('appointments');
    }

    public function down()
    {
        $this->forge->dropKey('appointments', self::QUEUE_INDEX);
        $this->forge->dropColumn('appointments', ['queue_position', 'checked_in_at']);
    }
}
