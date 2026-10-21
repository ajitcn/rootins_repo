<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateDoctorLanguagesAndPopularTreatments extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('doctor_languages')) {
            $this->forge->addField([
                'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
                'language_name' => ['type' => 'VARCHAR', 'constraint' => 100],
                'normalized_name' => ['type' => 'VARCHAR', 'constraint' => 100],
                'status' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('normalized_name');
            $this->forge->addKey('status');
            $this->forge->createTable('doctor_languages', true);
        }

        if (! $this->db->tableExists('popular_treatments')) {
            $this->forge->addField([
                'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
                'treatment_name' => ['type' => 'VARCHAR', 'constraint' => 150],
                'normalized_name' => ['type' => 'VARCHAR', 'constraint' => 150],
                'status' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('normalized_name');
            $this->forge->addKey('status');
            $this->forge->createTable('popular_treatments', true);
        }

        $this->createMap('doctor_language_map', 'language_id', 'doctor_languages', 'doctor_language_map_language_fk');
        $this->createMap('doctor_treatment_map', 'treatment_id', 'popular_treatments', 'doctor_treatment_map_treatment_fk');
    }

    private function createMap(string $table, string $valueColumn, string $valueTable, string $valueForeignKey): void
    {
        if ($this->db->tableExists($table)) return;

        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'doctor_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            $valueColumn => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['doctor_id', $valueColumn]);
        $this->forge->addForeignKey('doctor_id', 'doctors', 'id', 'CASCADE', 'CASCADE', $table . '_doctor_fk');
        $this->forge->addForeignKey($valueColumn, $valueTable, 'id', 'CASCADE', 'CASCADE', $valueForeignKey);
        $this->forge->createTable($table, true);
    }

    public function down(): void
    {
        $this->forge->dropTable('doctor_treatment_map', true);
        $this->forge->dropTable('doctor_language_map', true);
        $this->forge->dropTable('popular_treatments', true);
        $this->forge->dropTable('doctor_languages', true);
    }
}
