<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
class CreateAdminMasterSettings extends Migration
{
    public function up()
    {
        $this->forge->addField(['id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true], 'master_type' => ['type' => 'VARCHAR', 'constraint' => 60], 'name' => ['type' => 'VARCHAR', 'constraint' => 255], 'code' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true], 'description' => ['type' => 'TEXT', 'null' => true], 'amount' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'null' => true], 'status' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1], 'clinic_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true], 'created_by' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true], 'created_at' => ['type' => 'DATETIME', 'null' => true], 'updated_at' => ['type' => 'DATETIME', 'null' => true]]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['master_type', 'status']);
        $this->forge->addKey('clinic_id');
        $this->forge->createTable('admin_master_records');
        $this->forge->addField(['id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true], 'setting_group' => ['type' => 'VARCHAR', 'constraint' => 60], 'setting_key' => ['type' => 'VARCHAR', 'constraint' => 100], 'setting_value' => ['type' => 'TEXT', 'null' => true], 'is_secret' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0], 'clinic_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true], 'created_by' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true], 'created_at' => ['type' => 'DATETIME', 'null' => true], 'updated_at' => ['type' => 'DATETIME', 'null' => true]]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['setting_group', 'setting_key']);
        $this->forge->addKey('clinic_id');
        $this->forge->createTable('admin_setting_records');
    }
    public function down()
    {
        $this->forge->dropTable('admin_setting_records', true);
        $this->forge->dropTable('admin_master_records', true);
    }
}
