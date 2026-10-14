<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Moves the legacy Farmassist identity to Pharmacy without removing records.
 *
 * Generic legacy permissions are retained for other workspaces; Pharmacy gets
 * namespaced permission rows and the Pharmacy Assistant role receives them.
 */
final class RenameLegacyPharmacyIdentifiers extends Migration
{
    private const PERMISSION_MAP = [
        'farmassist.dashboard.view' => 'pharmacy.dashboard.view',
        'prescription.review.view' => 'pharmacy.prescription.review.view',
        'prescription.review.approve' => 'pharmacy.prescription.review.approve',
        'prescription.review.reject' => 'pharmacy.prescription.review.reject',
        'prescription.review.clarify' => 'pharmacy.prescription.review.clarify',
        'prescription.review.cancel' => 'pharmacy.prescription.review.cancel',
        'prescription.view' => 'pharmacy.prescription.view',
        'prescription.edit' => 'pharmacy.prescription.edit',
        'inventory.view' => 'pharmacy.inventory.view',
        'inventory.batch.create' => 'pharmacy.inventory.batch.create',
        'inventory.batch.update' => 'pharmacy.inventory.batch.update',
        'inventory.batch.adjust' => 'pharmacy.inventory.batch.adjust',
        'inventory.batch.delete' => 'pharmacy.inventory.batch.delete',
        'order.view' => 'pharmacy.order.view',
        'order.create' => 'pharmacy.order.create',
        'order.cancel' => 'pharmacy.order.cancel',
        'order.label.view' => 'pharmacy.order.label.view',
        'order.label.print' => 'pharmacy.order.label.print',
        'billing.view' => 'pharmacy.billing.view',
        'billing.payment.update' => 'pharmacy.billing.payment.update',
        'billing.refund' => 'pharmacy.billing.refund',
        'delivery.view' => 'pharmacy.delivery.view',
        'delivery.update' => 'pharmacy.delivery.update',
        'notification.view' => 'pharmacy.notification.view',
        'audit.view' => 'pharmacy.audit.view',
        'medicine.create' => 'pharmacy.medicine.create',
        'medicine.edit' => 'pharmacy.medicine.edit',
    ];

    public function up(): void
    {
        $this->db->transStart();

        $this->renameNotificationsTable('farmassist_notifications', 'pharmacy_notifications');
        $this->renameRole();
        $this->renamePermissions();
        $this->replaceStoredReferences('pharmacy');

        $this->db->transComplete();
    }

    public function down(): void
    {
        $this->db->transStart();

        $this->replaceStoredReferences('farmassist');
        $this->rollbackPermissions();
        $this->rollbackRole();
        $this->renameNotificationsTable('pharmacy_notifications', 'farmassist_notifications');

        $this->db->transComplete();
    }

    private function renameNotificationsTable(string $from, string $to): void
    {
        if ($this->db->tableExists($from) && !$this->db->tableExists($to)) {
            $this->forge->renameTable($from, $to);
        }

        if ($this->db->tableExists($to) && $this->db->DBDriver === 'MySQLi') {
            $this->renameForeignKeys($to);
        }
        if ($this->db->tableExists($to)) {
            $this->forge->modifyColumn($to, [
                'recipient_role' => [
                    'type' => 'VARCHAR',
                    'constraint' => 40,
                    'default' => $to === 'pharmacy_notifications' ? 'Pharmacy Assistant' : 'FarmAssist',
                ],
            ]);
        }
    }

    private function renameRole(): void
    {
        $this->db->table('roles')
            ->groupStart()
                ->whereIn('role_code', ['FARMASSIST', 'PHARMACY_ASSISTANT'])
                ->orWhereIn('role_name', ['FarmAssist', 'Farmassist', 'Pharmacy Assistant'])
            ->groupEnd()
            ->set(['role_name' => 'Pharmacy Assistant', 'role_code' => 'PHARMACY_ASSISTANT'])
            ->update();
    }

    private function rollbackRole(): void
    {
        $this->db->table('roles')
            ->where('role_code', 'PHARMACY_ASSISTANT')
            ->set(['role_name' => 'Pharmacy Assistant', 'role_code' => 'FARMASSIST'])
            ->update();
    }

    private function renamePermissions(): void
    {
        $roleIds = $this->pharmacyRoleIds();

        foreach (self::PERMISSION_MAP as $legacyKey => $pharmacyKey) {
            $legacy = $this->db->table('permissions')->where('permission_key', $legacyKey)->get()->getRowArray();
            $target = $this->db->table('permissions')->where('permission_key', $pharmacyKey)->get()->getRowArray();

            if ($target === null) {
                $source = $legacy ?? [
                    'module' => 'Pharmacy',
                    'permission_name' => ucwords(str_replace(['.', '_'], ' ', $pharmacyKey)),
                    'description' => null,
                    'status' => 1,
                ];
                $this->db->table('permissions')->insert([
                    'module' => 'Pharmacy',
                    'permission_name' => $this->permissionLabel($pharmacyKey, $source['permission_name'] ?? ''),
                    'description' => $source['description'] ?? null,
                    'permission_key' => $pharmacyKey,
                    'status' => $source['status'] ?? 1,
                    'created_at' => $source['created_at'] ?? date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $target = $this->db->table('permissions')->where('permission_key', $pharmacyKey)->get()->getRowArray();
            }

            // Always repair the Pharmacy Assistant mapping. This covers databases
            // where the namespaced permission row already exists but the
            // role_permissions row was lost or the rename migration was only
            // partially applied.
            if ($target !== null) {
                foreach ($roleIds as $roleId) {
                    $has = $this->db->table('role_permissions')
                        ->where(['role_id' => $roleId, 'permission_id' => (int) $target['id']])
                        ->countAllResults();
                    if ($has === 0) {
                        $this->db->table('role_permissions')->insert([
                            'role_id' => $roleId,
                            'permission_id' => (int) $target['id'],
                            'created_at' => date('Y-m-d H:i:s'),
                        ]);
                    }
                }
            }

            if ($legacy !== null && $legacyKey === 'farmassist.dashboard.view' && $target !== null && (int) $legacy['id'] !== (int) $target['id']) {
                $this->db->table('role_permissions')->where('permission_id', (int) $legacy['id'])->delete();
                $this->db->table('permissions')->where('id', (int) $legacy['id'])->delete();
            }
        }
    }

    private function rollbackPermissions(): void
    {
        $roleIds = $this->pharmacyRoleIds();

        foreach (self::PERMISSION_MAP as $legacyKey => $pharmacyKey) {
            $target = $this->db->table('permissions')->where('permission_key', $pharmacyKey)->get()->getRowArray();
            if ($target === null) {
                continue;
            }
            foreach ($roleIds as $roleId) {
                $this->db->table('role_permissions')->where(['role_id' => $roleId, 'permission_id' => (int) $target['id']])->delete();
            }
            if ($legacyKey !== 'farmassist.dashboard.view') {
                $remaining = $this->db->table('role_permissions')->where('permission_id', (int) $target['id'])->countAllResults();
                if ($remaining === 0) {
                    $this->db->table('permissions')->where('id', (int) $target['id'])->delete();
                }
            } else {
                $this->db->table('permissions')->where('id', (int) $target['id'])->set([
                    'module' => 'Farmassist',
                    'permission_name' => 'View Farmassist Dashboard',
                    'permission_key' => $legacyKey,
                ])->update();
            }
        }
    }

    private function pharmacyRoleIds(): array
    {
        return array_map('intval', array_column(
            $this->db->table('roles')->select('id')->where('role_code', 'PHARMACY_ASSISTANT')->get()->getResultArray(),
            'id'
        ));
    }

    private function replaceStoredReferences(string $prefix): void
    {
        $notificationTable = $prefix === 'pharmacy' ? 'farmassist_notifications' : 'pharmacy_notifications';
        $fromEvent = $prefix === 'pharmacy' ? 'farmassist.' : 'pharmacy.';
        $toEvent = $prefix === 'pharmacy' ? 'pharmacy.' : 'farmassist.';
        $fromActor = $prefix === 'pharmacy' ? 'farmassist' : 'pharmacy';
        $toActor = $prefix === 'pharmacy' ? 'pharmacy' : 'farmassist';
        $recipientRole = $prefix === 'pharmacy' ? 'Pharmacy Assistant' : 'FarmAssist';
        if ($this->db->tableExists($notificationTable)) {
            $this->db->query("UPDATE `{$notificationTable}` SET `event_key` = REPLACE(`event_key`, '{$fromEvent}', '{$toEvent}'), `recipient_role` = '{$recipientRole}' WHERE `event_key` LIKE '%{$fromActor}%' OR `event_key` LIKE '%{$fromEvent}%' OR `recipient_role` IN ('FarmAssist', 'Farmassist', 'Pharmacy', 'Pharmacy Assistant')");
        }
        if ($this->db->tableExists('audit_logs')) {
            $this->db->query("UPDATE `audit_logs` SET `action` = REPLACE(`action`, '{$fromEvent}', '{$toEvent}') WHERE `action` LIKE '{$fromEvent}%'");
        }
        if ($this->db->tableExists('roles')) {
            $this->db->table('roles')->whereIn('role_name', ['FarmAssist', 'Farmassist', 'Pharmacy', 'Pharmacy Assistant'])->set('role_name', $recipientRole)->update();
        }
    }

    private function renameForeignKeys(string $table): void
    {
        $schema = $this->db->query('SELECT DATABASE() AS db')->getRowArray()['db'] ?? '';
        if ($schema === '') {
            return;
        }
        $rows = $this->db->query('SELECT kcu.CONSTRAINT_NAME, kcu.COLUMN_NAME, kcu.REFERENCED_TABLE_NAME, kcu.REFERENCED_COLUMN_NAME, rc.UPDATE_RULE, rc.DELETE_RULE FROM information_schema.KEY_COLUMN_USAGE kcu JOIN information_schema.REFERENTIAL_CONSTRAINTS rc ON rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA AND rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME AND rc.TABLE_NAME = kcu.TABLE_NAME WHERE kcu.CONSTRAINT_SCHEMA = ? AND kcu.TABLE_NAME = ?', [$schema, $table])->getResultArray();
        foreach ($rows as $row) {
            $newName = $table . '_' . $row['COLUMN_NAME'] . '_fk';
            if ($row['CONSTRAINT_NAME'] === $newName) {
                continue;
            }
            $this->db->query('ALTER TABLE `' . $table . '` DROP FOREIGN KEY `' . $row['CONSTRAINT_NAME'] . '`');
            $this->db->query('ALTER TABLE `' . $table . '` ADD CONSTRAINT `' . $newName . '` FOREIGN KEY (`' . $row['COLUMN_NAME'] . '`) REFERENCES `' . $row['REFERENCED_TABLE_NAME'] . '` (`' . $row['REFERENCED_COLUMN_NAME'] . '`) ON UPDATE ' . $row['UPDATE_RULE'] . ' ON DELETE ' . $row['DELETE_RULE']);
        }
    }

    private function permissionLabel(string $key, string $fallback): string
    {
        $labels = [
            'pharmacy.dashboard.view' => 'View Pharmacy Dashboard',
            'pharmacy.prescription.review.view' => 'View Prescription Review',
            'pharmacy.prescription.review.approve' => 'Approve Prescription Review',
            'pharmacy.prescription.review.reject' => 'Reject Prescription Review',
            'pharmacy.prescription.review.clarify' => 'Clarify Prescription Review',
            'pharmacy.prescription.review.cancel' => 'Cancel Prescription Review',
        ];
        return $labels[$key] ?? preg_replace('/^Pharmacy\s+/i', '', $fallback) ?: $fallback;
    }
}
