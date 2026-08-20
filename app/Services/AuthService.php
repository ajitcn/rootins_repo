<?php

namespace App\Services;

use Admin\Models\User;
use Admin\Models\UserRole;

class AuthService
{
    protected User $user;
    protected UserRole $userRole;

    protected string $error = '';

    protected ?array $userData = null;

    protected array $roles = [];

    protected array $permissions = [];

    public function __construct()
    {
        $this->user = new User();
        $this->userRole = new UserRole();
    }

    /**
     * --------------------------------------------------------
     * Attempt Login
     * --------------------------------------------------------
     */
    public function attempt(string $userId, string $password): bool
    {
        $this->userData = $this->user
            ->where('email', $userId)
            ->first();

        if (! $this->userData) {
            $this->error = 'Invalid username or password.';
            return false;
        }

        if (! $this->verifyPassword($password, $this->userData['password'])) {
            return false;
        }

        if ($this->userData['status'] != 1) {
            $this->error = 'Your account is inactive.';
            return false;
        }

        $this->loadRoles();

        $this->loadPermissions();

        $this->createSession();

        return true;
    }

    /**
     * --------------------------------------------------------
     * Verify Password
     * --------------------------------------------------------
     */
    protected function verifyPassword(string $password, string $hash): bool
    {
        if (! password_verify($password, $hash)) {

            $this->error = 'Invalid username or password.';

            return false;
        }

        return true;
    }

    /**
     * --------------------------------------------------------
     * Load User Roles
     * --------------------------------------------------------
     */
    protected function loadRoles(): void
    {
        
        $this->roles = (new UserRole())
            ->select('roles.role_name')
            ->join('roles', 'roles.id=user_roles.role_id')
            ->where('user_id', $this->userData['id'])
            ->findColumn('role_name'); 
    }

    /**
     * --------------------------------------------------------
     * Load User Permissions
     * --------------------------------------------------------
     */
    protected function loadPermissions(): void
    {
        
        
    	$this->permissions = (new UserRole())
	        ->select('permissions.permission_key')
	        ->join('role_permissions', 'role_permissions.role_id = user_roles.role_id')
	        ->join('permissions', 'permissions.id = role_permissions.permission_id')
	        ->where('user_roles.user_id', $this->userData['id'])
	        ->findColumn('permission_key') ?? [];

	    // Remove duplicate permissions if user has multiple roles
	    $this->permissions = array_values(array_unique($this->permissions));
    }

    /**
     * --------------------------------------------------------
     * Create Session
     * --------------------------------------------------------
     */
    protected function createSession(): void
    {
        session()->set([

            'isLoggedIn' => true,

            'user' => [

                'id'       => $this->userData['id'],
                'username' => $this->userData['username'],
                'email'    => $this->userData['email'],

            ],

            'roles' => $this->roles,

            'permissions' => $this->permissions

        ]);
    }

    /**
     * --------------------------------------------------------
     * Redirect URL
     * --------------------------------------------------------
     */
    public function redirectUrl(): string
    {
        if (in_array('Admin', $this->roles)) {
            return '/admin';
        }

        if (in_array('Doctor', $this->roles)) {
            return '/doctor';
        }

        if (in_array('Patient', $this->roles)) {
            return '/patient';
        }

        if (in_array('Farmassist', $this->roles)) {
            return '/farmassist';
        }

        return 'system/login';
    }

    /**
     * --------------------------------------------------------
     * Logout
     * --------------------------------------------------------
     */
    public function logout(): void
    {
        session()->destroy();
    }

    /**
     * --------------------------------------------------------
     * Change Password
     * --------------------------------------------------------
     */
    public function changePassword(
        int $userId,
        string $currentPassword,
        string $newPassword
    ): bool
    {
        // TODO

        return true;
    }

    /**
     * --------------------------------------------------------
     * Forgot Password
     * --------------------------------------------------------
     */
    public function forgotPassword(string $email): bool
    {
        // TODO

        return true;
    }

    /**
     * --------------------------------------------------------
     * Get Error
     * --------------------------------------------------------
     */
    public function getError(): string
    {
        return $this->error;
    }

    /**
     * --------------------------------------------------------
     * Get Logged User
     * --------------------------------------------------------
     */
    public function user(): ?array
    {
        return $this->userData;
    }

    /**
     * --------------------------------------------------------
     * User Roles
     * --------------------------------------------------------
     */
    public function roles(): array
    {
        return $this->roles;
    }

    /**
     * --------------------------------------------------------
     * User Permissions
     * --------------------------------------------------------
     */
    public function permissions(): array
    {
        return $this->permissions;
    }

    /**
     * --------------------------------------------------------
     * Has Role
     * --------------------------------------------------------
     */
    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles);
    }

    /**
     * --------------------------------------------------------
     * Has Permission
     * --------------------------------------------------------
     */
    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions);
    }
}