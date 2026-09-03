<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
class RoleSeeder extends Seeder
{
    public function run()
    {
        $roles = [['role_name' => 'Super Admin', 'role_code' => 'SUPER_ADMIN', 'is_system' => 1], ['role_name' => 'Admin', 'role_code' => 'ADMIN', 'is_system' => 1], ['role_name' => 'Doctor', 'role_code' => 'DOCTOR', 'is_system' => 1], ['role_name' => 'Receptionist', 'role_code' => 'RECEPTIONIST', 'is_system' => 1], ['role_name' => 'Telecaller', 'role_code' => 'TELECALLER', 'is_system' => 1], ['role_name' => 'FarmAssist', 'role_code' => 'FARMASSIST', 'is_system' => 1], ['role_name' => 'Accountant', 'role_code' => 'ACCOUNTANT', 'is_system' => 1]];
        $this->db->table('roles')->insertBatch($roles);
    }
}
