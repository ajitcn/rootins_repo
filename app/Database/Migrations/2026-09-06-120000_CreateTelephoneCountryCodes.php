<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateTelephoneCountryCodes extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('telephone_country_codes')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'SMALLINT',
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'country_id' => [
                    'type' => 'SMALLINT',
                    'unsigned' => true,
                    'null' => true,
                ],
                'code' => [
                    'type' => 'VARCHAR',
                    'constraint' => 4,
                ],
                'normalized_code' => [
                    'type' => 'VARCHAR',
                    'constraint' => 4,
                ],
                'label' => [
                    'type' => 'VARCHAR',
                    'constraint' => 100,
                    'null' => true,
                ],
                'status' => [
                    'type' => 'TINYINT',
                    'constraint' => 1,
                    'default' => 1,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'updated_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey('country_id');
            $this->forge->addKey('status');
            $this->forge->addUniqueKey('normalized_code');
            $this->forge->addForeignKey(
                'country_id',
                'countries',
                'id',
                'RESTRICT',
                'RESTRICT',
                'telephone_country_codes_country_fk',
            );
            $this->forge->createTable('telephone_country_codes');
        }

        $countries = $this->db->table('countries')->get()->getResultArray();

        foreach ($countries as $country) {
            $normalized = preg_replace(
                '/^\\+/',
                '',
                trim((string) ($country['phone_code'] ?? '')),
            );

            if (! is_string($normalized) || ! preg_match('/^[1-9][0-9]{0,3}$/', $normalized)) {
                continue;
            }

            $exists = $this->db->table('telephone_country_codes')
                ->where('normalized_code', $normalized)
                ->get()
                ->getRowArray();

            if ($exists !== null) {
                continue;
            }

            $this->db->table('telephone_country_codes')->insert([
                'country_id' => (int) $country['id'],
                'code' => $normalized,
                'normalized_code' => $normalized,
                'label' => $country['name'],
                'status' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        if (! $this->db->fieldExists('telephone_country_code_id', 'patients')) {
            $this->forge->addColumn('patients', [
                'telephone_country_code_id' => [
                    'type' => 'SMALLINT',
                    'unsigned' => true,
                    'null' => true,
                ],
            ]);
            $this->forge->addKey('telephone_country_code_id');
            $this->forge->addForeignKey(
                'telephone_country_code_id',
                'telephone_country_codes',
                'id',
                'RESTRICT',
                'RESTRICT',
                'patients_telephone_country_code_fk',
            );
            $this->forge->processIndexes('patients');
        }

        $this->db->query(
            'UPDATE patients p '
            . 'JOIN telephone_country_codes tcc '
            . 'ON tcc.normalized_code = TRIM(LEADING \'+\' FROM p.country_code) '
            . 'SET p.telephone_country_code_id = tcc.id '
            . 'WHERE p.telephone_country_code_id IS NULL '
            . 'AND p.country_code IS NOT NULL',
        );
    }

    public function down()
    {
        if ($this->db->fieldExists('telephone_country_code_id', 'patients')) {
            $this->forge->dropForeignKey(
                'patients',
                'patients_telephone_country_code_fk',
            );
            $this->forge->dropKey('patients', 'telephone_country_code_id');
            $this->forge->dropColumn('patients', 'telephone_country_code_id');
        }

        if ($this->db->tableExists('telephone_country_codes')) {
            $this->forge->dropTable('telephone_country_codes');
        }
    }
}
