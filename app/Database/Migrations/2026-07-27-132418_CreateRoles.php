<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateRoles extends Migration
{
    public function up()
    {
        $this->forge->addField(['id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true], 'role_name' => ['type' => 'VARCHAR', 'constraint' => 100], 'role_code' => ['type' => 'VARCHAR', 'constraint' => 50], 'description' => ['type' => 'TEXT', 'null' => true], 'is_system' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0], 'status' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1], 'created_at DATETIME NULL', 'updated_at DATETIME NULL', 'deleted_at DATETIME NULL']);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('role_code');
        $this->forge->createTable('roles');
    }

    public function down()
    {
        $this->forge->dropTable('roles');
    }
}
