<?php

namespace App\Database\Seeds;
 

use CodeIgniter\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run()
    {
        $permissions = [

            // User

            ['module'=>'User','permission_name'=>'View Users','permission_key'=>'user.view'],
            ['module'=>'User','permission_name'=>'Create User','permission_key'=>'user.create'],
            ['module'=>'User','permission_name'=>'Edit User','permission_key'=>'user.edit'],
            ['module'=>'User','permission_name'=>'Delete User','permission_key'=>'user.delete'],

            // Doctor

            ['module'=>'Doctor','permission_name'=>'View Doctor','permission_key'=>'doctor.view'],
            ['module'=>'Doctor','permission_name'=>'Create Doctor','permission_key'=>'doctor.create'],
            ['module'=>'Doctor','permission_name'=>'Edit Doctor','permission_key'=>'doctor.edit'],
            ['module'=>'Doctor','permission_name'=>'Delete Doctor','permission_key'=>'doctor.delete'],

            // Patient

            ['module'=>'Patient','permission_name'=>'View Patient','permission_key'=>'patient.view'],
            ['module'=>'Patient','permission_name'=>'Create Patient','permission_key'=>'patient.create'],
            ['module'=>'Patient','permission_name'=>'Edit Patient','permission_key'=>'patient.edit'],
            ['module'=>'Patient','permission_name'=>'Delete Patient','permission_key'=>'patient.delete'],

            // Appointment

            ['module'=>'Appointment','permission_name'=>'View Appointment','permission_key'=>'appointment.view'],
            ['module'=>'Appointment','permission_name'=>'Create Appointment','permission_key'=>'appointment.create'],
            ['module'=>'Appointment','permission_name'=>'Edit Appointment','permission_key'=>'appointment.edit'],
            ['module'=>'Appointment','permission_name'=>'Delete Appointment','permission_key'=>'appointment.delete'],

            // Role

            ['module'=>'Role','permission_name'=>'View Roles','permission_key'=>'role.view'],
            ['module'=>'Role','permission_name'=>'Manage Roles','permission_key'=>'role.manage'],

            // Settings

            ['module'=>'Settings','permission_name'=>'Manage Settings','permission_key'=>'settings.manage'],

        ];

        $this->db->table('permissions')->insertBatch($permissions);
    }
}