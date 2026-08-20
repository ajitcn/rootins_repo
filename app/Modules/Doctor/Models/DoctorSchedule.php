<?php

namespace Doctor\Models;

use CodeIgniter\Model;

class DoctorSchedule extends Model
{
    protected $table = 'doctor_schedules';

    protected $primaryKey = 'id';

    protected $returnType = 'array';

    protected $allowedFields = [
        'doctor_id',
        'day_of_week',
        'session_name',
        'start_time',
        'end_time',
        'slot_duration',
        'max_patients',
        'status',
    ];

    protected $useTimestamps = true;

    protected $createdField = 'created_at';

    protected $updatedField = 'updated_at';
}