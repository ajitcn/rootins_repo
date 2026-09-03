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
        $this->userData = $this->user->where('email', $userId)->first();
        if (!$this->userData) {
            $this->error = 'Invalid username or password.';
            return false;
        }
        // The authenticated identity and all scope context must come from the
        // server-side record and its trusted relationships.
        $this->userData['id'] = (int) $this->userData['id'];
        if (!$this->verifyPassword($password, $this->userData['password'])) {
            return false;
        }
        if ($this->userData['status'] != 1) {
            $this->error = 'Your account is inactive.';
            return false;
        }
        $this->loadRoles();
        $this->loadPermissions();
        session()->regenerate(true);
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
        if (!password_verify($password, $hash)) {
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
        $this->roles = (new UserRole())->select('roles.role_name')->join('roles', 'roles.id=user_roles.role_id')->where('user_id', $this->userData['id'])->findColumn('role_name');
    }
    /**
     * --------------------------------------------------------
     * Load User Permissions
     * --------------------------------------------------------
     */
    protected function loadPermissions(): void
    {
        $this->permissions = (new UserRole())->select('permissions.permission_key')->join('role_permissions', 'role_permissions.role_id = user_roles.role_id')->join('permissions', 'permissions.id = role_permissions.permission_id')->where('user_roles.user_id', $this->userData['id'])->findColumn('permission_key') ?? [];
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
        $clinicId = $this->resolveClinicId();
        session()->set(['isLoggedIn' => true, 'user' => ['id' => $this->userData['id'], 'username' => $this->userData['username'], 'email' => $this->userData['email'], 'clinic_id' => $clinicId], 'clinic_id' => $clinicId, 'roles' => $this->roles, 'permissions' => $this->permissions]);
    }
    /**
     * Resolve the user's clinic from trusted server-side relationships.
     * Global administrators receive scope 0; controllers treat that as global.
     */
    protected function resolveClinicId(): int
    {
        $userId = (int) ($this->userData['id'] ?? 0);
        $doctorId = (int) ($this->userData['doctor_id'] ?? 0);
        $db = db_connect();
        if ($doctorId > 0) {
            $clinicId = $db->table('doctors')->select('clinic_id')->where('id', $doctorId)->get(1)->getRowArray();
            if ($clinicId !== null) {
                return (int) $clinicId['clinic_id'];
            }
        }
        if ($userId > 0) {
            $clinicId = $db->table('patients')->select('clinic_id')->where('user_id', $userId)->where('clinic_id IS NOT NULL', null, false)->orderBy('id', 'ASC')->get(1)->getRowArray();
            if ($clinicId !== null) {
                return (int) $clinicId['clinic_id'];
            }
        }
        return 0;
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
        if (in_array('Farmassist', $this->roles) || in_array('FarmAssist', $this->roles)) {
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
    public function changePassword(int $userId, string $currentPassword, string $newPassword): bool
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
        if ($this->userData === null) {
            return null;
        }
        return ['id' => (int) ($this->userData['id'] ?? 0), 'username' => $this->userData['username'] ?? null, 'email' => $this->userData['email'] ?? null, 'doctor_id' => isset($this->userData['doctor_id']) ? (int) $this->userData['doctor_id'] : null, 'clinic_id' => $this->resolveClinicId(), 'status' => (int) ($this->userData['status'] ?? 0)];
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
