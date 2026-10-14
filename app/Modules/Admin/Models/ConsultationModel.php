<?php

namespace Admin\Models;

use CodeIgniter\Model;

class ConsultationModel extends Model
{
    protected $table = 'consultations';

    protected $primaryKey = 'id';

    protected $returnType = 'array';

    protected $useSoftDeletes = false;

    protected $protectFields = true;

    protected $allowedFields = ['consultation_number', 'patient_id', 'doctor_id', 'clinic_id', 'appointment_id', 'consultation_date', 'consultation_time', 'chief_complaint', 'clinical_notes', 'diagnosis', 'treatment_plan', 'diagnostic_tests', 'diet_plan', 'follow_up_date', 'follow_up_after_days', 'follow_up_time', 'follow_up_type', 'follow_up_type_id', 'follow_up_remarks', 'next_appointment_after_days', 'next_appointment_date', 'next_appointment_time', 'next_appointment_type', 'status', 'follow_up_status_id', 'created_at', 'updated_at'];

    protected $useTimestamps = true;

    protected $dateFormat = 'datetime';

    protected $createdField = 'created_at';

    protected $updatedField = 'updated_at';
}
