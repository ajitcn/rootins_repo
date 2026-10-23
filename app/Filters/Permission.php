<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Database;

class Permission implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // Permission not passed in route
        if (empty($arguments) || empty($arguments[0])) {
            return;
        }
        $requiredPermission = $arguments[0];
        // Refresh authorization from current database mappings so sessions
        // created before a permission migration do not retain stale grants.
        $this->refreshSessionPermissions();
        $permissions = session('permissions') ?? [];

        if (!in_array($requiredPermission, $permissions, true)) {
            // AJAX Request
            if ($request->isAJAX()) {
                $path = $request->getUri()->getPath();
                if (str_contains($path, '/admin/integrations/shopify/order-mappings/') || str_contains($path, '/pharmacy/shopify-orders/')) {
                    $correlationId = bin2hex(random_bytes(16));
                    log_message('warning', 'Shopify mapping permission denied. Correlation ID: ' . $correlationId);
                    return service('response')->setStatusCode(403)->setJSON([
                        'status' => false,
                        'error' => ['code' => 'SHOPIFY_MAPPING_PERMISSION_DENIED', 'message' => 'You do not have permission to recheck mappings.', 'correlation_id' => $correlationId],
                        'correlation_id' => $correlationId,
                        'csrf' => ['name' => csrf_token(), 'hash' => csrf_hash()],
                    ]);
                }
                return service('response')->setStatusCode(403)->setJSON(['status' => false, 'msg' => 'You do not have permission to perform this action.']);
            }

            // Normal Request
            return redirect()->to(base_url('permission-denied'));
        }
    }

    /**
     * Refresh the current user's role-derived authorization state.
     */
    private function refreshSessionPermissions(): void
    {
        $user = session('user');
        $userId = is_array($user) ? (int) ($user['id'] ?? 0) : 0;

        if ($userId <= 0) {
            return;
        }

        try {
            // Authorization is application data and must not follow the test
            // connection selected by db_connect() in CIUnit tests.
            $db = Database::connect('default');
            $rows = $db->table('user_roles')
                ->select('permissions.permission_key')
                ->join('role_permissions', 'role_permissions.role_id = user_roles.role_id')
                ->join('permissions', 'permissions.id = role_permissions.permission_id')
                ->where('user_roles.user_id', $userId)
                ->where('permissions.status', 1)
                ->get()->getResultArray();
            $permissions = array_column($rows, 'permission_key');

            $roleRows = $db->table('user_roles')
                ->select('roles.role_name, roles.role_code')
                ->join('roles', 'roles.id = user_roles.role_id')
                ->where('user_roles.user_id', $userId)
                ->get()->getResultArray();
            $sessionRoles = array_map(static function (array $role): string {
                return in_array((string) ($role['role_name'] ?? ''), ['FarmAssist', 'Farmassist'], true)
                    ? 'Pharmacy Assistant'
                    : (string) ($role['role_name'] ?? '');
            }, $roleRows);
            session()->set([
                'roles' => array_values(array_unique($sessionRoles)),
                'permissions' => array_values(array_unique($permissions)),
            ]);
        } catch (\Throwable $exception) {
            log_message('error', 'Permission session refresh failed safely.');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        //
    }
}
