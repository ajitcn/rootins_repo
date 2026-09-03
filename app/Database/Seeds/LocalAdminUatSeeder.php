<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use RuntimeException;
final class LocalAdminUatSeeder extends Seeder
{
    public function run()
    {
        if (!defined('ENVIRONMENT') || ENVIRONMENT !== 'development') {
            throw new RuntimeException('Local Admin UAT seeding is allowed only in development.');
        }
        $email = trim((string) getenv('ROOTINS_TEST_ADMIN_EMAIL'));
        $password = (string) getenv('ROOTINS_TEST_ADMIN_PASSWORD');
        if ($email === '' || $password === '') {
            throw new RuntimeException('Set ROOTINS_TEST_ADMIN_EMAIL and ROOTINS_TEST_ADMIN_PASSWORD for local UAT.');
        }
        $db = $this->db;
        $clinic = $db->table('clinics')->select('id')->where('status', 1)->orderBy('id', 'ASC')->get(1)->getRowArray();
        if (!$clinic) {
            throw new RuntimeException('Create an active local clinic before seeding Admin UAT access.');
        }
        $doctor = $db->table('doctors')->select('id')->where('clinic_id', (int) $clinic['id'])->orderBy('id', 'ASC')->get(1)->getRowArray();
        if (!$doctor) {
            throw new RuntimeException('Create a doctor in the active local clinic before seeding Admin UAT access.');
        }
        $role = $db->table('roles')->select('id')->where('role_code', 'ADMIN')->where('status', 1)->get(1)->getRowArray();
        if (!$role) {
            throw new RuntimeException('Seed the baseline ADMIN role before seeding Admin UAT access.');
        }
        $permission = $db->table('permissions')->select('id')->where('permission_key', 'settings.manage')->get(1)->getRowArray();
        if (!$permission) {
            throw new RuntimeException('Seed settings.manage before seeding Admin UAT access.');
        }
        $users = $db->table('users');
        $user = $users->where('email', $email)->get(1)->getRowArray();
        $userData = ['username' => 'local_admin_uat', 'email' => $email, 'doctor_id' => (int) $doctor['id'], 'status' => 1, 'deleted_at' => null];
        if (!$user) {
            $userData['password'] = password_hash($password, PASSWORD_DEFAULT);
            $users->insert($userData);
            $userId = (int) $db->insertID();
        } else {
            $userId = (int) $user['id'];
            $users->where('id', $userId)->update($userData);
        }
        $db->table('user_roles')->ignore(true)->insert(['user_id' => $userId, 'role_id' => (int) $role['id']]);
        $db->table('role_permissions')->ignore(true)->insert(['role_id' => (int) $role['id'], 'permission_id' => (int) $permission['id'], 'created_at' => date('Y-m-d H:i:s')]);
    }
}
