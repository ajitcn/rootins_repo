<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCities extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],

            'state_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],

            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
            ],

            'status' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
                'comment'    => '1=Active,0=Inactive',
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
        $this->forge->addKey('state_id');
        $this->forge->addKey('status');

        // Prevent duplicate city names within the same state
        $this->forge->addUniqueKey(['state_id', 'name']);

        $this->forge->addForeignKey(
            'state_id',
            'states',
            'id',
            'CASCADE',   // Update state id
            'RESTRICT'   // Don't allow deleting a state with cities
        );

        $this->forge->createTable('cities');
    }

    public function down()
    {
        $this->forge->dropTable('cities');
    }
}