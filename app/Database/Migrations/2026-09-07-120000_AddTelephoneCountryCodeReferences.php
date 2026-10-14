<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddTelephoneCountryCodeReferences extends Migration
{
    private array $tables = ['clinics', 'doctors', 'users'];

    public function up()
    {
        foreach ($this->tables as $table) {
            if (! $this->db->fieldExists('telephone_country_code_id', $table)) {
                $this->forge->addColumn($table, [
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
                    $table . '_telephone_country_code_fk',
                );
                $this->forge->processIndexes($table);
            }
        }
    }

    public function down()
    {
        foreach ($this->tables as $table) {
            if ($this->db->fieldExists('telephone_country_code_id', $table)) {
                $this->forge->dropForeignKey($table, $table . '_telephone_country_code_fk');
                $this->forge->dropKey($table, 'telephone_country_code_id');
                $this->forge->dropColumn($table, 'telephone_country_code_id');
            }
        }
    }
}
