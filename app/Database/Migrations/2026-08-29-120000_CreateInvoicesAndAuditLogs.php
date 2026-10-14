<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateInvoicesAndAuditLogs extends Migration
{
    public function up()
    {
        $this->forge->addField(['id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true], 'invoice_number' => ['type' => 'VARCHAR', 'constraint' => 40], 'clinic_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true], 'patient_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true], 'appointment_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true], 'currency' => ['type' => 'CHAR', 'constraint' => 3, 'default' => 'INR'], 'subtotal' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0], 'tax' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0], 'discount' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0], 'total' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0], 'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'draft'], 'payment_status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pending'], 'idempotency_key' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true], 'created_by' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true], 'created_at' => ['type' => 'DATETIME', 'null' => true], 'updated_at' => ['type' => 'DATETIME', 'null' => true]]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('invoice_number');
        $this->forge->addUniqueKey('idempotency_key');
        $this->forge->addKey(['clinic_id', 'status']);
        $this->forge->addKey(['clinic_id', 'payment_status']);
        $this->forge->addForeignKey('clinic_id', 'clinics', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('patient_id', 'patients', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('appointment_id', 'appointments', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('invoices');
        $this->forge->addField(['id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true], 'invoice_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true], 'description' => ['type' => 'VARCHAR', 'constraint' => 255], 'quantity' => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 1], 'unit_price' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0], 'line_total' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0], 'created_at' => ['type' => 'DATETIME', 'null' => true], 'updated_at' => ['type' => 'DATETIME', 'null' => true]]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('invoice_id');
        $this->forge->addForeignKey('invoice_id', 'invoices', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('invoice_items');
        $this->forge->addField(['id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true], 'action' => ['type' => 'VARCHAR', 'constraint' => 100], 'correlation_id' => ['type' => 'VARCHAR', 'constraint' => 64], 'actor_user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true], 'clinic_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true], 'route' => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true], 'occurred_at' => ['type' => 'DATETIME'], 'context' => ['type' => 'TEXT', 'null' => true], 'created_at' => ['type' => 'DATETIME', 'null' => true]]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['clinic_id', 'occurred_at']);
        $this->forge->addKey(['action', 'occurred_at']);
        $this->forge->createTable('audit_logs');

        foreach ([['Billing', 'View Billing', 'billing.view'], ['Billing', 'Create Invoice', 'billing.create'], ['Billing', 'Update Payment', 'billing.edit'], ['Audit', 'View Audit Logs', 'audit.view']] as [$module, $name, $key]) {
            $permission = $this->db->table('permissions')->where('permission_key', $key)->get()->getRowArray();

            if ($permission === null) {
                $this->db->table('permissions')->insert(['module' => $module, 'permission_name' => $name, 'permission_key' => $key, 'status' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
                $permission = ['id' => $this->db->insertID()];
            }

            foreach (['SUPER_ADMIN', 'ADMIN'] as $roleCode) {
                $role = $this->db->table('roles')->where('role_code', $roleCode)->get()->getRowArray();

                if ($role === null) {
                    continue;
                }
                $mapping = $this->db->table('role_permissions')->where('role_id', $role['id'])->where('permission_id', $permission['id'])->get()->getRowArray();

                if ($mapping === null) {
                    $this->db->table('role_permissions')->insert(['role_id' => $role['id'], 'permission_id' => $permission['id'], 'created_at' => date('Y-m-d H:i:s')]);
                }
            }
        }
    }

    public function down()
    {
        $this->forge->dropTable('audit_logs', true);
        $this->forge->dropTable('invoice_items', true);
        $this->forge->dropTable('invoices', true);
    }
}
