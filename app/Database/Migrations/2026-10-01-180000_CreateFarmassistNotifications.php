<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateFarmassistNotifications extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'clinic_id' => ['type' => 'INT', 'unsigned' => true],
            'recipient_user_id' => ['type' => 'INT', 'unsigned' => true],
            'recipient_role' => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'FarmAssist'],
            'type' => ['type' => 'VARCHAR', 'constraint' => 80],
            'title' => ['type' => 'VARCHAR', 'constraint' => 160],
            'message' => ['type' => 'TEXT'],
            'related_entity_type' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'related_entity_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'event_key' => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
            'read_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['clinic_id', 'recipient_user_id', 'read_at', 'created_at']);
        $this->forge->addKey(['clinic_id', 'type', 'created_at']);
        $this->forge->addKey(['related_entity_type', 'related_entity_id']);
        $this->forge->addUniqueKey('event_key');
        $this->forge->addForeignKey('clinic_id', 'clinics', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('recipient_user_id', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('farmassist_notifications', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('farmassist_notifications', true);
    }
}
