<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class BackfillTelephoneCountryCodeReferences extends Migration
{
    private array $tables = ['clinics', 'doctors', 'users'];

    public function up()
    {
        foreach ($this->tables as $table) {
            $this->db->query(
                'UPDATE ' . $table . ' entity '
                . 'JOIN countries country ON country.id = entity.country_code_id '
                . 'JOIN telephone_country_codes telephone_code '
                . 'ON telephone_code.normalized_code = TRIM(LEADING \'+\' FROM country.phone_code) '
                . 'SET entity.telephone_country_code_id = telephone_code.id '
                . 'WHERE entity.telephone_country_code_id IS NULL '
                . 'AND entity.country_code_id IS NOT NULL',
            );
        }
    }

    public function down()
    {
        // References are intentionally retained during rollback to avoid
        // discarding user-entered telephone-code associations.
    }
}
