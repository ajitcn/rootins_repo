<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddPatientAuthenticationFields extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('patients', [
            'password_hash' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
                'after' => 'email',
            ],
            'failed_login_attempts' => [
                'type' => 'INT',
                'constraint' => 5,
                'unsigned' => true,
                'default' => 0,
                'after' => 'password_hash',
            ],
            'locked_until' => [
                'type' => 'DATETIME',
                'null' => true,
                'after' => 'failed_login_attempts',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('patients', [
            'password_hash',
            'failed_login_attempts',
            'locked_until',
        ]);
    }
}
