<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class EnsurePatientPasswordHash extends Migration
{
    public function up(): void
    {
        if ($this->db->fieldExists('password_hash', 'patients')) {
            return;
        }

        $this->forge->addColumn(
            'patients',
            [
                'password_hash' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                    'null' => true,
                    'after' => 'email',
                ],
            ],
        );
    }

    public function down(): void
    {
        if ($this->db->fieldExists('password_hash', 'patients')) {
            $this->forge->dropColumn('patients', 'password_hash');
        }
    }
}
