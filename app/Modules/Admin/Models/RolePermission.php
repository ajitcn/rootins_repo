<?php

namespace Admin\Models;

use CodeIgniter\Model;

class RolePermission extends Model
{
    protected $table = 'role_permissions';

    protected $primaryKey = 'id';

    protected $returnType = 'array';

    protected $allowedFields = [
        'role_id',
        'permission_id'
    ];

    protected $useTimestamps = false;
}