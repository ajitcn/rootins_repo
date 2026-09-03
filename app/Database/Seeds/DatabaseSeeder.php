<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
class DatabaseSeeder extends Seeder
{
    public function run()
    {
        $this->call('RoleSeeder');
        $this->call('PermissionSeeder');
        $this->call('RolePermissionSeeder');
        $this->call('DoctorSpecializationSeeder');
        $this->call('AdminConfigurationSeeder');
    }
}
