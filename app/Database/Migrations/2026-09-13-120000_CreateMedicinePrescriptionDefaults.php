<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Stores editable prescription defaults for a medicine per clinic.
 * A NULL clinic_id with scope_key=global is the shared fallback; a clinic
 * row takes precedence when both records exist.
 */
class CreateMedicinePrescriptionDefaults extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('medicine_prescription_defaults')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'medicine_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'clinic_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'scope_key' => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'global'],
            'duration_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'frequency_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'dose_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'intake_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'note' => ['type' => 'TEXT', 'null' => true],
            'status' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_by' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['medicine_id', 'scope_key']);
        $this->forge->addKey(['medicine_id', 'status']);
        $this->forge->addKey(['clinic_id', 'status']);
        $this->forge->addForeignKey('medicine_id', 'medicines', 'id', 'RESTRICT', 'CASCADE', 'medicine_prescription_defaults_medicine_fk');
        $this->forge->addForeignKey('clinic_id', 'clinics', 'id', 'SET NULL', 'CASCADE', 'medicine_prescription_defaults_clinic_fk');
        $this->forge->addForeignKey('duration_id', 'prescription_durations', 'id', 'SET NULL', 'CASCADE', 'medicine_prescription_defaults_duration_fk');
        $this->forge->addForeignKey('frequency_id', 'prescription_frequencies', 'id', 'SET NULL', 'CASCADE', 'medicine_prescription_defaults_frequency_fk');
        $this->forge->addForeignKey('dose_id', 'prescription_doses', 'id', 'SET NULL', 'CASCADE', 'medicine_prescription_defaults_dose_fk');
        $this->forge->addForeignKey('intake_id', 'prescription_intakes', 'id', 'SET NULL', 'CASCADE', 'medicine_prescription_defaults_intake_fk');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'SET NULL', 'CASCADE', 'medicine_prescription_defaults_created_by_fk');
        $this->forge->createTable('medicine_prescription_defaults');
    }

    public function down()
    {
        $this->forge->dropTable('medicine_prescription_defaults', true);
    }
}
