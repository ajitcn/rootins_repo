<?php

namespace App\Controllers;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;
use App\Services\AuthService;
use App\Services\Audit\AuditLogger;

abstract class BaseAdminController extends BaseController
{
    /**
     * Logged-in user.
     *
     * @var array
     */
    protected array $user = [];

    /**
     * User roles.
     *
     * @var array
     */
    protected array $roles = [];

    /**
     * User permissions.
     *
     * @var array
     */
    protected array $permissions = [];

    /**
     * Auth Service.
     */
    protected AuthService $auth;

    protected AuditLogger $audit;

    /**
     * Called automatically by CI4.
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->auth = service('auth');

        // User not logged in
        if (!session('isLoggedIn')) {
            redirect()->to('system/login')->send();

            exit;
        }
        // Session Data
        $this->user = session('user') ?? [];
        $this->roles = session('roles') ?? [];
        $this->permissions = session('permissions') ?? [];
        $this->audit = service('audit');
        $this->audit->record('admin.route_access', ['controller' => static::class, 'method' => $this->request->getMethod()]);
    }

    /**
     * Check Role.
     */
    protected function hasRole(string $role): bool
    {
        return in_array($role, $this->roles);
    }

    /**
     * Check Permission.
     */
    protected function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions);
    }
}
