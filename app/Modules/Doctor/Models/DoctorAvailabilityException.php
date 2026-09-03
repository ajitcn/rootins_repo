<?php

namespace Doctor\Models;

use CodeIgniter\Model;
class DoctorAvailabilityException extends Model
{
    protected $table = 'doctor_availability_exceptions';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['doctor_id', 'available_date', 'start_time', 'end_time', 'is_available', 'reason'];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
