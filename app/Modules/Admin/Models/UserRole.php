<?php

namespace Admin\Models;

use CodeIgniter\Model;

class UserRole extends Model
{
    protected $table = 'user_roles';

    protected $primaryKey = 'id';

    protected $returnType = 'array';

    protected $protectFields = true;

    protected $allowedFields = ['user_id', 'role_id'];

    protected $useAutoIncrement = true;

    protected $useTimestamps = false;

    protected $validationRules = ['user_id' => 'required|integer', 'role_id' => 'required|integer'];

    protected $skipValidation = false;
}
