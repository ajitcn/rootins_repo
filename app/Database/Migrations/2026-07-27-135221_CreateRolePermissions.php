<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
class CreateRolePermissions extends Migration
{
    public function up()
    {
        $this->forge->addField(['id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true], 'role_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true], 'permission_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true], 'created_at' => ['type' => 'DATETIME', 'null' => true]]);
        $this->forge->addKey('id', true);
        // Prevent duplicate permissions for same role
        $this->forge->addUniqueKey(['role_id', 'permission_id']);
        $this->forge->addKey('role_id');
        $this->forge->addKey('permission_id');
        $this->forge->addForeignKey('role_id', 'roles', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('permission_id', 'permissions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('role_permissions');
    }
    public function down()
    {
        $this->forge->dropTable('role_permissions');
    }
}
