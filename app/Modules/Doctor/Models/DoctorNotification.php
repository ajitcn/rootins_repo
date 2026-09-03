<?php

namespace Doctor\Models;

use CodeIgniter\Model;
final class DoctorNotification extends Model
{
    protected $table = 'doctor_notifications';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['doctor_id', 'clinic_id', 'title', 'message', 'channel', 'status', 'read_at', 'created_at', 'updated_at'];
    protected $useTimestamps = false;
}
