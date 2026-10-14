<?php

namespace Admin\Models;

use CodeIgniter\Model;

class User extends Model
{
    protected $table = 'users';

    protected $primaryKey = 'id';

    protected $returnType = 'array';

    protected $useSoftDeletes = true;

    protected $allowedFields = ['doctor_id', 'country_code_id', 'telephone_country_code_id', 'username', 'password', 'email', 'mobile', 'mobile_normalized', 'last_login', 'last_login_ip', 'failed_login_attempts', 'locked_until', 'status'];

    protected $useTimestamps = true;

    protected $createdField = 'created_at';

    protected $updatedField = 'updated_at';

    protected $deletedField = 'deleted_at';
}
