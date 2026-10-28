<?php

namespace Admin\Controllers\Access;

use Admin\Models\Permission;
use Admin\Models\Role;
use Admin\Models\RolePermission;
use Admin\Models\User;
use Admin\Models\UserRole;
use Admin\Models\Location\TelephoneCountryCode;
use App\Controllers\BaseAdminController;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\HTTP\RedirectResponse;
use Throwable;

class Access extends BaseAdminController
{
    private const MIN_PASSWORD_LENGTH = 10;

    public function index(): RedirectResponse
    {
        return redirect()->to(base_url('admin/access/users'));
    }

    public function users(): string
    {
        $db = db_connect();
        $usersQuery = $db->table('users')->select("users.id, users.username, users.email, users.mobile, users.status, users.deleted_at, users.doctor_id, users.telephone_country_code_id, telephone_country_codes.normalized_code AS telephone_country_code, COALESCE(countries.name, telephone_country_codes.label) AS telephone_country_name, GROUP_CONCAT(DISTINCT roles.role_name ORDER BY roles.role_name SEPARATOR ', ') AS role_names, GROUP_CONCAT(DISTINCT user_roles.role_id) AS role_ids", false)
            ->join('user_roles', 'user_roles.user_id = users.id', 'left')
            ->join('roles', 'roles.id = user_roles.role_id', 'left')
            ->join('telephone_country_codes', 'telephone_country_codes.id = users.telephone_country_code_id', 'left')
            ->join('countries', 'countries.id = telephone_country_codes.country_id', 'left')
            ->where('users.status', 1)
            ->where('users.deleted_at IS NULL', null, false)
            ->groupBy('users.id')
            ->orderBy('users.username');
        $clinicId = (int) (($this->user['clinic_id'] ?? 0 ?: session('clinic_id')) ?: 0);

        if ($clinicId > 0) {
            $doctorIds = array_column($db->table('doctors')->select('id')->where('clinic_id', $clinicId)->get()->getResultArray(), 'id');
            $patientUserIds = array_column($db->table('patients')->select('user_id')->where('clinic_id', $clinicId)->where('user_id IS NOT NULL', null, false)->get()->getResultArray(), 'user_id');
            $usersQuery->groupStart()
                ->whereIn('users.doctor_id', $doctorIds ?: [0])
                ->orWhereIn('users.id', $patientUserIds ?: [0])
                ->orWhere('users.doctor_id', 0)
                ->orWhere('users.doctor_id IS NULL', null, false)
                ->groupEnd();
        }
        $users = $usersQuery->get()->getResultArray();

        foreach ($users as $listedUser) {
            log_message('debug', 'Access user list diagnostic: ' . json_encode([
                'user_id' => (int) ($listedUser['id'] ?? 0),
                'username' => (string) ($listedUser['username'] ?? ''),
                'email' => (string) ($listedUser['email'] ?? ''),
                'active_status' => (int) ($listedUser['status'] ?? 0),
                'deleted_status' => trim((string) ($listedUser['deleted_at'] ?? '')) === '' ? 'not_deleted' : 'deleted',
                'role_mapping' => trim((string) ($listedUser['role_ids'] ?? '')) !== '' ? 'present' : 'not_present',
                'clinic_mapping' => (int) ($listedUser['doctor_id'] ?? 0) > 0 || in_array((int) ($listedUser['id'] ?? 0), $patientUserIds ?? [], true) ? 'present' : 'not_present',
                'list_query_result' => 'included',
            ], JSON_UNESCAPED_SLASHES));
        }

        return view(ADMIN_VIEW . 'pages/access/users', ['users' => $users, 'telephoneCodes' => (new TelephoneCountryCode())->dropdown(), 'roles' => (new Role())->where('status', 1)->orderBy('role_name')->findAll(), 'allRoles' => (new Role())->orderBy('role_name')->findAll(), 'permissions' => (new Permission())->orderBy('module')->orderBy('permission_key')->findAll(), 'rolePermissions' => $db->table('role_permissions')->select('role_id, permission_id')->get()->getResultArray(), 'doctors' => $this->scopedDoctors(), 'canCreateUser' => $this->hasPermission('user.create'), 'canEditUser' => $this->hasPermission('user.edit'), 'canDeleteUser' => $this->hasPermission('user.delete')]);
    }

    public function roles(): string
    {
        $db = db_connect();

        return view(ADMIN_VIEW . 'pages/access/roles', ['roles' => (new Role())->orderBy('role_name')->findAll(), 'permissions' => (new Permission())->where('status', 1)->orderBy('module')->orderBy('permission_key')->findAll(), 'rolePermissions' => $db->table('role_permissions')->select('role_id, permission_id')->get()->getResultArray(), 'canManageRoles' => $this->hasPermission('role.manage')]);
    }

    public function permissions(): string
    {
        return view(ADMIN_VIEW . 'pages/access/permissions', ['permissions' => (new Permission())->orderBy('module')->orderBy('permission_key')->findAll(), 'canManageRoles' => $this->hasPermission('role.manage')]);
    }

    public function createUser(): ResponseInterface
    {
        if (!$this->isAjaxPost()) {
            return $this->json(405, false, 'Invalid request.');
        }
        $data = $this->userData();
        $errors = $this->validateUser($data);

        if ($errors !== []) {
            return $this->validation($errors);
        }
        $roleIds = $this->ids('role_ids');

        if ($roleIds === []) {
            return $this->validation(['role_ids' => 'At least one role is required.']);
        }

        if (!$this->validRoles($roleIds)) {
            return $this->json(422, false, 'One or more roles are invalid or inactive.');
        }

        if (!$this->validDoctorScope($data['doctor_id'])) {
            return $this->json(422, false, 'The selected doctor is outside the permitted clinic scope.');
        }
        $db = db_connect();
        $db->transBegin();

        try {
            $user = new User();
            $userId = $user->insert(['doctor_id' => $data['doctor_id'], 'country_code_id' => $data['country_code_id'], 'telephone_country_code_id' => $data['telephone_country_code_id'], 'mobile_normalized' => $data['mobile_normalized'], 'username' => $data['username'], 'password' => password_hash($data['password'], PASSWORD_DEFAULT), 'email' => $data['email'], 'mobile' => $data['mobile'], 'status' => $data['status']], true);

            if (!$userId) {
                throw new \RuntimeException('User could not be created.');
            }

            foreach ($roleIds as $roleId) {
                if (!(new UserRole())->insert(['user_id' => $userId, 'role_id' => $roleId])) {
                    throw new \RuntimeException('User roles could not be assigned.');
                }
            }

            if ($db->transStatus() === false) {
                throw new \RuntimeException('User transaction failed.');
            }
            $db->transCommit();
            $this->audit->record('admin.access_user_created', ['target_user_id' => $userId, 'role_ids' => $roleIds, 'doctor_id' => $data['doctor_id']]);

            return $this->json(200, true, 'User created successfully.');
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Access user creation failed: ' . $e->getMessage());

            return $this->json(500, false, 'User could not be created safely.');
        }
    }

    public function updateUser(int $userId): ResponseInterface
    {
        if (!$this->isAjaxPost()) {
            return $this->json(405, false, 'Invalid request.');
        }

        if ($userId <= 0) {
            return $this->json(404, false, 'User not found.');
        }
        $user = (new User())->find($userId);

        if ($user === null) {
            return $this->json(404, false, 'User not found.');
        }
        $data = $this->userData(false);
        $errors = $this->validateUser($data, $userId);

        if ($errors !== []) {
            return $this->validation($errors);
        }
        $roleIds = $this->ids('role_ids');

        if ($roleIds === [] || !$this->validRoles($roleIds)) {
            return $this->json(422, false, 'At least one valid role is required.');
        }

        if (!$this->validDoctorScope($data['doctor_id'])) {
            return $this->json(422, false, 'The selected doctor is outside the permitted clinic scope.');
        }

        if ($userId === (int) ($this->user['id'] ?? 0) && $data['status'] === 0) {
            return $this->json(422, false, 'Your own account cannot be disabled.');
        }
        $db = db_connect();
        $db->transBegin();

        try {
            $update = ['doctor_id' => $data['doctor_id'], 'country_code_id' => $data['country_code_id'], 'telephone_country_code_id' => $data['telephone_country_code_id'], 'mobile_normalized' => $data['mobile_normalized'], 'username' => $data['username'], 'email' => $data['email'], 'mobile' => $data['mobile'], 'status' => $data['status']];

            if ($data['password'] !== '') {
                $update['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
            }

            if (!(new User())->update($userId, $update)) {
                throw new \RuntimeException('User update failed.');
            }
            $db->table('user_roles')->where('user_id', $userId)->delete();

            foreach ($roleIds as $roleId) {
                if (!(new UserRole())->insert(['user_id' => $userId, 'role_id' => $roleId])) {
                    throw new \RuntimeException('User roles could not be assigned.');
                }
            }

            if ($db->transStatus() === false) {
                throw new \RuntimeException('User transaction failed.');
            }
            $db->transCommit();
            $this->audit->record('admin.access_user_updated', ['target_user_id' => $userId, 'role_ids' => $roleIds, 'doctor_id' => $data['doctor_id'], 'status' => $data['status']]);

            return $this->json(200, true, 'User updated successfully.');
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Access user update failed: ' . $e->getMessage());

            return $this->json(500, false, 'User could not be updated safely.');
        }
    }

    public function deleteUser(int $userId): ResponseInterface
    {
        if (!$this->isAjaxPost()) {
            return $this->json(405, false, 'Invalid request.');
        }

        if ($userId === (int) ($this->user['id'] ?? 0)) {
            return $this->json(422, false, 'Your own account cannot be deleted.');
        }

        if (!(new User())->delete($userId)) {
            return $this->json(404, false, 'User could not be deleted.');
        }
        $this->audit->record('admin.access_user_deleted', ['target_user_id' => $userId]);

        return $this->json(200, true, 'User disabled.');
    }

    public function saveRole(): ResponseInterface
    {
        if (!$this->isAjaxPost()) {
            return $this->json(405, false, 'Invalid request.');
        }
        $id = (int) $this->request->getPost('id');
        $role = (new Role())->find($id);
        $name = trim((string) $this->request->getPost('role_name'));
        $code = strtoupper(trim((string) $this->request->getPost('role_code')));
        $description = trim((string) $this->request->getPost('description'));
        $status = $this->request->getPost('status') ? 1 : 0;
        $errors = [];

        if ($name === '' || strlen($name) > 100) {
            $errors['role_name'] = 'Role name is required and must be at most 100 characters.';
        }

        if (!preg_match('/^[A-Z][A-Z0-9_]{1,49}$/', $code)) {
            $errors['role_code'] = 'Role code must use uppercase letters, numbers, and underscores.';
        }

        if (strlen($description) > 1000) {
            $errors['description'] = 'Description is too long.';
        }

        if ($errors !== []) {
            return $this->validation($errors);
        }

        if ($id > 0 && $role === null) {
            return $this->json(404, false, 'Role not found.');
        }

        if ($id > 0 && (int) $role['is_system'] === 1) {
            return $this->json(422, false, 'System roles cannot be changed here.');
        }
        $model = new Role();
        $ok = $id > 0 ? $model->update($id, ['role_name' => $name, 'role_code' => $code, 'description' => $description, 'status' => $status]) : $model->insert(['role_name' => $name, 'role_code' => $code, 'description' => $description, 'status' => $status, 'is_system' => 0]);

        if (!$ok) {
            return $this->json(422, false, 'Role code may already exist.');
        }
        $this->audit->record('admin.access_role_saved', ['role_id' => $id ?: $model->getInsertID()]);

        return $this->json(200, true, 'Role saved successfully.');
    }

    public function deleteRole(int $roleId): ResponseInterface
    {
        if (!$this->isAjaxPost()) {
            return $this->json(405, false, 'Invalid request.');
        }
        $role = (new Role())->find($roleId);

        if ($role === null) {
            return $this->json(404, false, 'Role not found.');
        }

        if ((int) $role['is_system'] === 1) {
            return $this->json(422, false, 'System roles cannot be deleted.');
        }

        if (!(new Role())->delete($roleId)) {
            return $this->json(500, false, 'Role could not be disabled safely.');
        }
        $this->audit->record('admin.access_role_deleted', ['role_id' => $roleId]);

        return $this->json(200, true, 'Role disabled.');
    }

    public function savePermission(): ResponseInterface
    {
        if (!$this->isAjaxPost()) {
            return $this->json(405, false, 'Invalid request.');
        }
        $id = (int) $this->request->getPost('id');
        $permission = (new Permission())->find($id);
        $module = trim((string) $this->request->getPost('module'));
        $name = trim((string) $this->request->getPost('permission_name'));
        $key = strtolower(trim((string) $this->request->getPost('permission_key')));
        $description = trim((string) $this->request->getPost('description'));
        $status = $this->request->getPost('status') ? 1 : 0;
        $errors = [];

        if ($module === '' || strlen($module) > 100) {
            $errors['module'] = 'Module is required.';
        }

        if ($name === '' || strlen($name) > 150) {
            $errors['permission_name'] = 'Permission name is required.';
        }

        if (!preg_match('/^[a-z][a-z0-9_.-]{2,99}$/', $key)) {
            $errors['permission_key'] = 'Permission key must use lowercase resource.action format.';
        }

        if (strlen($description) > 1000) {
            $errors['description'] = 'Description is too long.';
        }

        if ($errors !== []) {
            return $this->validation($errors);
        }

        if ($id > 0 && $permission === null) {
            return $this->json(404, false, 'Permission not found.');
        }

        if ($id > 0 && $key !== (string) $permission['permission_key']) {
            return $this->json(422, false, 'Permission keys cannot be changed after creation.');
        }
        $model = new Permission();
        $ok = $id > 0 ? $model->update($id, ['module' => $module, 'permission_name' => $name, 'description' => $description, 'status' => $status]) : $model->insert(['module' => $module, 'permission_name' => $name, 'permission_key' => $key, 'description' => $description, 'status' => $status]);

        if (!$ok) {
            return $this->json(422, false, 'Permission key may already exist.');
        }
        $this->audit->record('admin.access_permission_saved', ['permission_id' => $id ?: $model->getInsertID(), 'permission_key' => $key]);

        return $this->json(200, true, 'Permission saved successfully.');
    }

    public function assignPermissions(int $roleId): ResponseInterface
    {
        if (!$this->isAjaxPost()) {
            return $this->json(405, false, 'Invalid request.');
        }

        if ((new Role())->find($roleId) === null) {
            return $this->json(404, false, 'Role not found.');
        }
        $ids = $this->ids('permission_ids');

        if ($ids !== [] && count($ids) !== (new Permission())->whereIn('id', $ids)->where('status', 1)->countAllResults()) {
            return $this->json(422, false, 'One or more permissions are invalid or inactive.');
        }
        $db = db_connect();
        $db->transBegin();

        try {
            $db->table('role_permissions')->where('role_id', $roleId)->delete();

            foreach ($ids as $permissionId) {
                if (!(new RolePermission())->insert(['role_id' => $roleId, 'permission_id' => $permissionId])) {
                    throw new \RuntimeException('Permission assignment failed.');
                }
            }

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Permission assignment transaction failed.');
            }
            $db->transCommit();
            $this->audit->record('admin.access_role_permissions_assigned', ['role_id' => $roleId, 'permission_ids' => $ids]);

            return $this->json(200, true, 'Role permissions updated.');
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Role permission assignment failed: ' . $e->getMessage());

            return $this->json(500, false, 'Role permissions could not be updated safely.');
        }
    }

    private function userData(bool $passwordRequired = true): array
    {
        $mobile = trim((string) $this->request->getPost('mobile')) ?: null;
        $telephoneCode = null;

        if ($mobile !== '' || (string) $this->request->getPost('country_code_manual') === '1' || trim((string) $this->request->getPost('telephone_country_code_id')) !== '') {
            try {
                $telephoneCode = service('telephoneContact')->resolve((int) $this->request->getPost('telephone_country_code_id') ?: null, (string) $this->request->getPost('country_code_manual') === '1', (string) $this->request->getPost('country_code'), (string) $this->request->getPost('telephone_country_name'));
            } catch (Throwable $e) {
                $telephoneCode = null;
            }
        }

        return ['username' => trim((string) $this->request->getPost('username')), 'password' => (string) $this->request->getPost('password'), 'email' => trim((string) $this->request->getPost('email')) ?: null, 'mobile' => $mobile, 'mobile_normalized' => service('telephoneContact')->normalizedMobile($mobile), 'country_code_id' => $telephoneCode['country_id'] ?? null, 'telephone_country_code_id' => $telephoneCode['id'] ?? null, 'telephone_country_name' => trim((string) $this->request->getPost('telephone_country_name')), 'country_code_manual' => (string) $this->request->getPost('country_code_manual') === '1', 'doctor_id' => max(0, (int) $this->request->getPost('doctor_id')) ?: null, 'status' => $this->request->getPost('status') ? 1 : 0, 'password_required' => $passwordRequired];
    }

    private function validateUser(array $data, int $id = 0): array
    {
        $errors = [];

        if (!preg_match('/^[A-Za-z0-9_.@-]{3,100}$/', $data['username'])) {
            $errors['username'] = 'Username is invalid.';
        }
        $exists = (new User())->where('username', $data['username'])->where('id !=', $id)->first();

        if ($exists !== null) {
            $errors['username'] = 'Username is already in use.';
        }

        if ($data['password_required'] && strlen($data['password']) < self::MIN_PASSWORD_LENGTH) {
            $errors['password'] = 'Password must be at least 10 characters.';
        }

        if (!$data['password_required'] && $data['password'] !== '' && strlen($data['password']) < self::MIN_PASSWORD_LENGTH) {
            $errors['password'] = 'Password must be at least 10 characters.';
        }

        if ($data['email'] !== null && (!filter_var($data['email'], FILTER_VALIDATE_EMAIL) || strlen($data['email']) > 150)) {
            $errors['email'] = 'Email is invalid.';
        }

        if ($data['mobile'] !== null && !preg_match('/^[0-9+() -]{7,20}$/', $data['mobile'])) {
            $errors['mobile'] = 'Mobile number is invalid.';
        }

        if ($data['mobile'] !== null && $data['mobile'] !== '' && $data['telephone_country_code_id'] === null) {
            $errors['country_code'] = $data['country_code_manual'] ? 'Enter a valid telephone country code and country name.' : 'Select a valid telephone country code.';
        }

        if ($data['country_code_manual'] && $data['mobile'] !== '' && $data['telephone_country_name'] === '') {
            $errors['telephone_country_name'] = 'Country name is required when entering a code manually.';
        }

        return $errors;
    }

    private function ids(string $field): array
    {
        $values = $this->request->getPost($field);

        if (!is_array($values)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $values), static fn ($id) => $id > 0)));
    }

    private function validRoles(array $ids): bool
    {
        return count($ids) === (new Role())->whereIn('id', $ids)->where('status', 1)->countAllResults();
    }

    private function validDoctorScope(?int $doctorId): bool
    {
        if ($doctorId === null) {
            return true;
        }

        foreach ($this->scopedDoctors() as $doctor) {
            if ((int) $doctor['id'] === $doctorId) {
                return true;
            }
        }

        return false;
    }

    private function scopedDoctors(): array
    {
        $db = db_connect();
        $query = $db->table('doctors')->select('id, first_name, last_name, clinic_id')->where('status', 1);
        $clinicId = (int) (($this->user['clinic_id'] ?? 0 ?: session('clinic_id')) ?: 0);

        if ($clinicId > 0) {
            $query->where('clinic_id', $clinicId);
        }

        return $query->orderBy('first_name')->get()->getResultArray();
    }

    private function isAjaxPost(): bool
    {
        return $this->request->isAJAX() && $this->request->getMethod(true) === 'POST';
    }

    private function validation(array $errors): ResponseInterface
    {
        return $this->response->setStatusCode(422)->setJSON(['status' => false, 'msg' => (string) reset($errors), 'errors' => $errors]);
    }

    private function json(int $code, bool $status, string $message, array $data = []): ResponseInterface
    {
        $payload = ['status' => $status, 'msg' => $message];

        if ($data !== []) {
            $payload['data'] = $data;
        }

        return $this->response->setStatusCode($code)->setJSON($payload);
    }
}
