<?php

namespace Doctor\Models;

use CodeIgniter\Model;

class Doctor extends Model
{
    protected $table = 'doctors';

    protected $primaryKey = 'id';

    protected $returnType = 'array';

    protected $useSoftDeletes = true;

    protected $allowedFields = ['clinic_id', 'specialization_id', 'country_code_id', 'telephone_country_code_id', 'doctor_code', 'first_name', 'last_name', 'gender', 'dob', 'qualification', 'experience', 'registration_number', 'mobile', 'mobile_normalized', 'email', 'address', 'consultation_duration', 'signature', 'profile_photo', 'notes', 'online_booking', 'display_order', 'status'];

    protected $useTimestamps = true;

    protected $createdField = 'created_at';

    protected $updatedField = 'updated_at';

    protected $deletedField = 'deleted_at';
}
