<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddPatientLocationLocalities extends Migration
{
    public function up()
    {
        foreach (['countries' => null, 'states' => 'country_id', 'cities' => 'state_id'] as $table => $parent) {
            if (!$this->db->fieldExists('normalized_name', $table)) {
                $this->forge->addColumn($table, ['normalized_name' => ['type' => 'VARCHAR', 'constraint' => 191, 'null' => true]]);
                $this->db->query("UPDATE {$table} SET normalized_name = LOWER(TRIM(name)) WHERE normalized_name IS NULL");
                $this->forge->modifyColumn($table, ['normalized_name' => ['type' => 'VARCHAR', 'constraint' => 191, 'null' => false]]);
                $this->forge->addUniqueKey($parent === null ? ['normalized_name'] : [$parent, 'normalized_name'], $table . '_parent_normalized_unique');
                $this->forge->processIndexes($table);
            }
        }

        if (!$this->db->tableExists('localities')) {
            $this->forge->addField(['id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true], 'city_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true], 'name' => ['type' => 'VARCHAR', 'constraint' => 150], 'normalized_name' => ['type' => 'VARCHAR', 'constraint' => 191], 'status' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1], 'created_at' => ['type' => 'DATETIME', 'null' => true], 'updated_at' => ['type' => 'DATETIME', 'null' => true]]);
            $this->forge->addKey('id', true);
            $this->forge->addKey('city_id');
            $this->forge->addKey('status');
            $this->forge->addUniqueKey(['city_id', 'normalized_name'], 'localities_city_normalized_unique');
            $this->forge->addForeignKey('city_id', 'cities', 'id', 'CASCADE', 'RESTRICT', 'localities_city_fk');
            $this->forge->createTable('localities');
        }

        if (!$this->db->fieldExists('locality_id', 'patients')) {
            $this->forge->addColumn('patients', ['locality_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true]]);
            $this->forge->addKey('locality_id');
            $this->forge->addForeignKey('locality_id', 'localities', 'id', 'RESTRICT', 'RESTRICT', 'patients_locality_fk');
            $this->forge->processIndexes('patients');
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('locality_id', 'patients')) {
            $this->forge->dropForeignKey('patients', 'patients_locality_fk');
            $this->forge->dropKey('patients', 'locality_id');
            $this->forge->dropColumn('patients', 'locality_id');
        }

        if ($this->db->tableExists('localities')) {
            $this->forge->dropTable('localities', true);
        }

        foreach (['countries', 'states', 'cities'] as $table) {
            if ($this->db->fieldExists('normalized_name', $table)) {
                $this->forge->dropKey($table, $table . '_parent_normalized_unique');
                $this->forge->dropColumn($table, 'normalized_name');
            }
        }
    }
}
