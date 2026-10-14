<?php

namespace App\Modules\CoreApi\Models;

use CodeIgniter\Model;

final class CoreApiIdempotencyModel extends Model
{
    protected $table = 'core_api_idempotency';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['credential_id', 'idempotency_key', 'request_hash', 'status', 'response_status', 'response_json', 'expires_at', 'created_at', 'updated_at'];
    protected $useTimestamps = false;
}
