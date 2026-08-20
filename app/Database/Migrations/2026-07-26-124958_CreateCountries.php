<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCountries extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'SMALLINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],

            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],

            'iso2' => [
                'type'       => 'CHAR',
                'constraint' => 2,
            ],

            'iso3' => [
                'type'       => 'CHAR',
                'constraint' => 3,
            ],

            'phone_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 10,
                'null'       => true,
            ],

            'currency_code' => [
                'type'       => 'CHAR',
                'constraint' => 3,
                'null'       => true,
            ],

            'status' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
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
        $this->forge->addUniqueKey('iso2');
        $this->forge->addUniqueKey('iso3');
        $this->forge->addKey('status');

        $this->forge->createTable('countries');
    }

    public function down()
    {
        $this->forge->dropTable('countries');
    }
}