<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
class CreateStates extends Migration
{
    public function up()
    {
        $this->forge->addField(['id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true], 'country_id' => ['type' => 'SMALLINT', 'unsigned' => true], 'name' => ['type' => 'VARCHAR', 'constraint' => 100], 'code' => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true, 'comment' => 'MH, GJ, DL etc.'], 'status' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1], 'created_at' => ['type' => 'DATETIME', 'null' => true], 'updated_at' => ['type' => 'DATETIME', 'null' => true]]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('country_id');
        $this->forge->addKey('status');
        $this->forge->addUniqueKey(['country_id', 'name']);
        $this->forge->addForeignKey('country_id', 'countries', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('states');
    }
    public function down()
    {
        $this->forge->dropTable('states');
    }
}
