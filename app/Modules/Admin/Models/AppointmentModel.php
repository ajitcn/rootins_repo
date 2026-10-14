<?php

namespace Admin\Models;

use CodeIgniter\Model;

class AppointmentModel extends Model
{
    protected $table = 'appointments';

    protected $primaryKey = 'id';

    protected $returnType = 'array';

    protected $useSoftDeletes = false;

    protected $protectFields = true;

    protected $allowedFields = ['appointment_number', 'patient_id', 'doctor_id', 'clinic_id', 'appointment_date', 'appointment_time', 'duration_minutes', 'appointment_type', 'service_name', 'status', 'queue_position', 'checked_in_at', 'created_by_type', 'created_by_id', 'booking_source', 'reason_for_visit', 'notes', 'cancelled_at', 'cancelled_by', 'cancellation_reason', 'created_at', 'updated_at'];

    protected $useTimestamps = true;

    protected $dateFormat = 'datetime';

    protected $createdField = 'created_at';

    protected $updatedField = 'updated_at';
}
