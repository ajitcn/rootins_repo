<?php

namespace Patient\Models;

use CodeIgniter\Model;
final class PatientNotificationModel extends Model
{
    protected $table = 'patient_notifications';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['patient_id', 'clinic_id', 'title', 'message', 'channel', 'status', 'read_at', 'created_at', 'updated_at'];
    protected $useTimestamps = false;
}
