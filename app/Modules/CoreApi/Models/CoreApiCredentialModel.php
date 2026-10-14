<?php

namespace App\Modules\CoreApi\Models;

use CodeIgniter\Model;

final class CoreApiCredentialModel extends Model
{
    protected $table = 'core_api_credentials';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['key_id', 'secret_encrypted', 'status', 'rate_limit_per_minute', 'last_used_at', 'created_at', 'updated_at'];
    protected $useTimestamps = false;
}
