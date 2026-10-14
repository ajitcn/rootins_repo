<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreatePaymentStatuses extends Migration
{
    private const STATUSES = [
        ['Pending', 'pending', 'Payment has not been completed.', 10],
        ['Authorized', 'authorized', 'Payment has been authorized and is awaiting capture.', 20],
        ['Paid', 'paid', 'Payment has been received.', 30],
        ['Failed', 'failed', 'Payment attempt failed.', 40],
        ['Refunded', 'refunded', 'Payment has been refunded.', 50],
    ];

    public function up(): void
    {
        if (!$this->db->tableExists('payment_statuses')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
                'status_name' => ['type' => 'VARCHAR', 'constraint' => 120],
                'status_code' => ['type' => 'VARCHAR', 'constraint' => 60],
                'description' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
                'sort_order' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'status' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'archived_at' => ['type' => 'DATETIME', 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('status_name');
            $this->forge->addUniqueKey('status_code');
            $this->forge->addKey(['status', 'archived_at']);
            $this->forge->createTable('payment_statuses');
        }

        $now = date('Y-m-d H:i:s');

        foreach (self::STATUSES as [$name, $code, $description, $sortOrder]) {
            $existing = $this->db->table('payment_statuses')->where('status_code', $code)->get(1)->getRowArray();

            if ($existing === null) {
                $this->db->table('payment_statuses')->insert([
                    'status_name' => $name,
                    'status_code' => $code,
                    'description' => $description,
                    'sort_order' => $sortOrder,
                    'status' => 1,
                    'archived_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        if ($this->db->tableExists('payment_statuses')) {
            $this->forge->dropTable('payment_statuses');
        }
    }
}
