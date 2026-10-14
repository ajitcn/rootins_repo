<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddNormalizedMobileReferences extends Migration
{
    public function up()
    {
        foreach (['patients' => 'mobile_number', 'doctors' => 'mobile', 'users' => 'mobile', 'clinics' => 'mobile'] as $table => $mobileColumn) {
            if (!$this->db->fieldExists('country_code_id', $table)) {
                $this->forge->addColumn($table, ['country_code_id' => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'null' => true]]);
                $this->forge->addKey('country_code_id');
                $this->forge->addForeignKey('country_code_id', 'countries', 'id', 'RESTRICT', 'RESTRICT', $table . '_country_code_fk');
                $this->forge->processIndexes($table);
            }

            if (!$this->db->fieldExists('mobile_normalized', $table)) {
                $this->forge->addColumn($table, ['mobile_normalized' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true]]);
                $this->db->query("UPDATE {$table} SET mobile_normalized = REGEXP_REPLACE({$mobileColumn}, '[^0-9]', '') WHERE {$mobileColumn} IS NOT NULL AND {$mobileColumn} <> ''");
                $this->forge->addKey('mobile_normalized');
                $this->forge->processIndexes($table);
            }
        }

        if ($this->db->fieldExists('country_code', 'patients')) {
            $this->db->query("UPDATE patients p JOIN countries c ON c.phone_code = p.country_code SET p.country_code_id = c.id WHERE p.country_code_id IS NULL");
        }
    }

    public function down()
    {
        foreach (['patients', 'doctors', 'users', 'clinics'] as $table) {
            if ($this->db->fieldExists('country_code_id', $table)) {
                $this->forge->dropForeignKey($table, $table . '_country_code_fk');
                $this->forge->dropKey($table, 'country_code_id');
                $this->forge->dropColumn($table, 'country_code_id');
            }

            if ($this->db->fieldExists('mobile_normalized', $table)) {
                $this->forge->dropKey($table, 'mobile_normalized');
                $this->forge->dropColumn($table, 'mobile_normalized');
            }
        }
    }
}
