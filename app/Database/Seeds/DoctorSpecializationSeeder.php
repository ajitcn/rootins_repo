<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class DoctorSpecializationSeeder extends Seeder
{
    public function run()
    {
        $rows = [['specialization_name' => 'Dermatologist'], ['specialization_name' => 'Cosmetologist'], ['specialization_name' => 'Plastic Surgeon'], ['specialization_name' => 'Hair Transplant Surgeon'], ['specialization_name' => 'General Physician'], ['specialization_name' => 'Dentist'], ['specialization_name' => 'Gynecologist'], ['specialization_name' => 'Orthopedic'], ['specialization_name' => 'Pediatrician'], ['specialization_name' => 'ENT Specialist']];
        $this->db->table('doctor_specializations')->insertBatch($rows);
    }
}
