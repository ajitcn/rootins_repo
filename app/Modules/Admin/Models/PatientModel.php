<?php

namespace Admin\Models;

use CodeIgniter\Model;

class PatientModel extends Model
{
    protected $table = 'patients';

    protected $primaryKey = 'id';

    protected $returnType = 'array';

    protected $useSoftDeletes = false;

    protected $protectFields = true;

    protected $allowedFields = ['user_id', 'clinic_id', 'first_name', 'middle_name', 'last_name', 'gender', 'date_of_birth', 'email', 'password_hash', 'failed_login_attempts', 'locked_until', 'country_code', 'country_code_id', 'telephone_country_code_id', 'mobile_number', 'mobile_normalized', 'address', 'country_id', 'state_id', 'city_id', 'locality_id', 'postal_code', 'status', 'source_type', 'source_user_id', 'source_reference'];

    protected $useTimestamps = true;

    protected $dateFormat = 'datetime';

    protected $createdField = 'created_at';

    protected $updatedField = 'updated_at';
}
