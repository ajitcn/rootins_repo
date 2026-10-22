<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddShopifyOrderOperationsPermission extends Migration
{
    private const PERMISSION_KEY = 'shopify.order.operations';
    private const ROLE_CODES = ['SUPER_ADMIN', 'ADMIN'];

    public function up(): void
    {
        $permission = $this->db->table('permissions')->where('permission_key', self::PERMISSION_KEY)->get()->getRowArray();
        if ($permission === null) {
            $this->insertExistingColumns('permissions', [
                'module' => 'Integration',
                'permission_name' => 'Shopify Order Operations',
                'permission_key' => self::PERMISSION_KEY,
                'status' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $permission = $this->db->table('permissions')->where('permission_key', self::PERMISSION_KEY)->get()->getRowArray();
        }
        if ($permission === null) return;

        foreach ($this->db->table('roles')->whereIn('role_code', self::ROLE_CODES)->get()->getResultArray() as $role) {
            $exists = $this->db->table('role_permissions')->where('role_id', (int) $role['id'])->where('permission_id', (int) $permission['id'])->countAllResults();
            if ($exists === 0) {
                $this->insertExistingColumns('role_permissions', [
                    'role_id' => (int) $role['id'],
                    'permission_id' => (int) $permission['id'],
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    public function down(): void
    {
        $permission = $this->db->table('permissions')->where('permission_key', self::PERMISSION_KEY)->get()->getRowArray();
        if ($permission === null) return;

        $roleIds = array_column(
            $this->db->table('roles')->select('id')->whereIn('role_code', self::ROLE_CODES)->get()->getResultArray(),
            'id',
        );
        if ($roleIds !== []) {
            // Remove only this migration's target permission mappings. Other
            // permissions and mappings for these roles remain untouched.
            $this->db->table('role_permissions')
                ->whereIn('role_id', array_map('intval', $roleIds))
                ->where('permission_id', (int) $permission['id'])
                ->delete();
        }

        // If another role still references an existing permission with this
        // key, preserve it rather than cascading into unrelated mappings.
        if ($this->db->table('role_permissions')->where('permission_id', (int) $permission['id'])->countAllResults() === 0) {
            $this->db->table('permissions')->where('id', (int) $permission['id'])->where('permission_key', self::PERMISSION_KEY)->delete();
        }
    }

    /** @param array<string, mixed> $data */
    private function insertExistingColumns(string $table, array $data): void
    {
        $columns = array_flip($this->db->getFieldNames($table));
        $insert = array_intersect_key($data, $columns);

        if ($insert !== []) {
            $this->db->table($table)->insert($insert);
        }
    }
}
