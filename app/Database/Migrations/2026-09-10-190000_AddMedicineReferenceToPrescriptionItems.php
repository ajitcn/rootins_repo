<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddMedicineReferenceToPrescriptionItems extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('medicine_id', 'prescription_items')) {
            $this->forge->addColumn('prescription_items', [
                'medicine_id' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                    'null' => true,
                    'after' => 'prescription_id',
                ],
            ]);
        }

        $this->db->query('ALTER TABLE prescription_items ADD INDEX idx_prescription_items_medicine_id (medicine_id)');
        $this->db->query('ALTER TABLE prescription_items ADD CONSTRAINT fk_prescription_items_medicine_id FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON UPDATE CASCADE ON DELETE RESTRICT');

        // Keep all legacy text values. Only rows that match an active medicine
        // are linked; unmatched historical text remains available for review.
        $this->db->query(
            'UPDATE prescription_items AS prescription_items
             INNER JOIN medicines AS medicines
               ON LOWER(TRIM(prescription_items.medicine_name)) = LOWER(TRIM(medicines.medicine_name))
             SET prescription_items.medicine_id = medicines.id
             WHERE prescription_items.medicine_id IS NULL',
        );
    }

    public function down()
    {
        $this->db->query('ALTER TABLE prescription_items DROP FOREIGN KEY fk_prescription_items_medicine_id');
        $this->db->query('ALTER TABLE prescription_items DROP INDEX idx_prescription_items_medicine_id');
        $this->forge->dropColumn('prescription_items', 'medicine_id');
    }
}
