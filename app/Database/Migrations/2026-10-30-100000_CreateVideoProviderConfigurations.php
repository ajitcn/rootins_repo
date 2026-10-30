<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateVideoProviderConfigurations extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'provider' => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'twilio'],
            'account_sid_encrypted' => ['type' => 'TEXT'],
            'api_key_sid_encrypted' => ['type' => 'TEXT'],
            'api_key_secret_encrypted' => ['type' => 'TEXT'],
            'status' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_by' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'updated_by' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('provider');
        $this->forge->addKey('status');
        $this->forge->createTable('video_provider_configurations');
    }

    public function down()
    {
        $this->forge->dropTable('video_provider_configurations', true);
    }
}
