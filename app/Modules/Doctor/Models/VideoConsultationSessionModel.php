<?php

namespace Doctor\Models;

use CodeIgniter\Model;

final class VideoConsultationSessionModel extends Model
{
    protected $table = 'doctor_video_consultation_sessions';

    protected $primaryKey = 'id';

    protected $returnType = 'array';

    protected $allowedFields = [
        'appointment_id', 'patient_id', 'doctor_id', 'clinic_id', 'provider', 'room_reference', 'status',
        'started_at', 'ended_at', 'failure_reason', 'recording_enabled', 'recording_consent_state',
        'provider_recording_id', 'recording_status', 'recording_duration_seconds', 'recording_retention_expires_at',
        'recording_reference',
    ];

    protected $useTimestamps = true;

    protected $createdField = 'created_at';

    protected $updatedField = 'updated_at';
}
