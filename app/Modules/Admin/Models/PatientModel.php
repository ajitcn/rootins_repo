<?php

namespace Admin\Models;

use CodeIgniter\Model;

class PatientModel extends Model
{
    protected $table          = 'patients';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields  = true;

    protected $allowedFields = [
        'user_id',
        'clinic_id',
        'first_name',
        'middle_name',
        'last_name',
        'gender',
        'date_of_birth',
        'email',
        'country_code',
        'mobile_number',
        'address',
        'country_id',
        'state_id',
        'city_id',
        'postal_code',
        'status',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
