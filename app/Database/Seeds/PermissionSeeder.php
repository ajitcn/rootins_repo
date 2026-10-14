<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run()
    {
        $permissions = [
            // Clinic
            ['module' => 'Clinic', 'permission_name' => 'View Clinics', 'permission_key' => 'clinic.view'],
            ['module' => 'Clinic', 'permission_name' => 'Create Clinic', 'permission_key' => 'clinic.create'],
            ['module' => 'Clinic', 'permission_name' => 'Edit Clinic', 'permission_key' => 'clinic.edit'],
            ['module' => 'Clinic', 'permission_name' => 'Delete Clinic', 'permission_key' => 'clinic.delete'],
            // User
            ['module' => 'User', 'permission_name' => 'View Users', 'permission_key' => 'user.view'],
            ['module' => 'User', 'permission_name' => 'Create User', 'permission_key' => 'user.create'],
            ['module' => 'User', 'permission_name' => 'Edit User', 'permission_key' => 'user.edit'],
            ['module' => 'User', 'permission_name' => 'Delete User', 'permission_key' => 'user.delete'],
            // Doctor
            ['module' => 'Doctor', 'permission_name' => 'View Doctor', 'permission_key' => 'doctor.view'],
            ['module' => 'Doctor', 'permission_name' => 'Create Doctor', 'permission_key' => 'doctor.create'],
            ['module' => 'Doctor', 'permission_name' => 'Edit Doctor', 'permission_key' => 'doctor.edit'],
            ['module' => 'Doctor', 'permission_name' => 'Delete Doctor', 'permission_key' => 'doctor.delete'],
            // Patient
            ['module' => 'Patient', 'permission_name' => 'View Patient', 'permission_key' => 'patient.view'],
            ['module' => 'Patient', 'permission_name' => 'Create Patient', 'permission_key' => 'patient.create'],
            ['module' => 'Patient', 'permission_name' => 'Edit Patient', 'permission_key' => 'patient.edit'],
            ['module' => 'Patient', 'permission_name' => 'Delete Patient', 'permission_key' => 'patient.delete'],
            // Appointment
            ['module' => 'Appointment', 'permission_name' => 'View Appointment', 'permission_key' => 'appointment.view'],
            ['module' => 'Appointment', 'permission_name' => 'Create Appointment', 'permission_key' => 'appointment.create'],
            ['module' => 'Appointment', 'permission_name' => 'Edit Appointment', 'permission_key' => 'appointment.edit'],
            ['module' => 'Appointment', 'permission_name' => 'Delete Appointment', 'permission_key' => 'appointment.delete'],
            // Consultation
            ['module' => 'Consultation', 'permission_name' => 'View Consultation', 'permission_key' => 'consultation.view'],
            ['module' => 'Consultation', 'permission_name' => 'Create Consultation', 'permission_key' => 'consultation.create'],
            ['module' => 'Consultation', 'permission_name' => 'Edit Consultation', 'permission_key' => 'consultation.edit'],
            ['module' => 'Consultation', 'permission_name' => 'Delete Consultation', 'permission_key' => 'consultation.delete'],
            // Prescription
            ['module' => 'Prescription', 'permission_name' => 'View Prescription', 'permission_key' => 'prescription.view'],
            ['module' => 'Prescription', 'permission_name' => 'Create Prescription', 'permission_key' => 'prescription.create'],
            ['module' => 'Prescription', 'permission_name' => 'Edit Prescription', 'permission_key' => 'prescription.edit'],
            ['module' => 'Prescription', 'permission_name' => 'Delete Prescription', 'permission_key' => 'prescription.delete'],
            // Medicine
            ['module' => 'Medicine', 'permission_name' => 'View Medicines', 'permission_key' => 'medicine.view'],
            ['module' => 'Medicine', 'permission_name' => 'Create Medicine', 'permission_key' => 'medicine.create'],
            ['module' => 'Medicine', 'permission_name' => 'Edit Medicine', 'permission_key' => 'medicine.edit'],
            ['module' => 'Medicine', 'permission_name' => 'Delete Medicine', 'permission_key' => 'medicine.delete'],
            // Role
            ['module' => 'Role', 'permission_name' => 'View Roles', 'permission_key' => 'role.view'],
            ['module' => 'Role', 'permission_name' => 'Manage Roles', 'permission_key' => 'role.manage'],
            // Settings
            ['module' => 'Settings', 'permission_name' => 'Manage Settings', 'permission_key' => 'settings.manage'],
            ['module' => 'Delivery Provider', 'permission_name' => 'View Delivery Providers', 'permission_key' => 'delivery_provider.view'],
            ['module' => 'Delivery Provider', 'permission_name' => 'Manage Delivery Providers', 'permission_key' => 'delivery_provider.manage'],
            ['module' => 'Payment Status', 'permission_name' => 'View Payment Statuses', 'permission_key' => 'payment_status.view'],
            ['module' => 'Payment Status', 'permission_name' => 'Manage Payment Statuses', 'permission_key' => 'payment_status.manage'],
            ['module' => 'Payment Provider', 'permission_name' => 'View Payment Providers', 'permission_key' => 'payment_provider.view'],
            ['module' => 'Payment Provider', 'permission_name' => 'Manage Payment Providers', 'permission_key' => 'payment_provider.manage'],
        ];
        $this->db->table('permissions')->insertBatch($permissions);
    }
}
