<?php

namespace App\Modules\Shopify\Models;

use CodeIgniter\Model;

final class ShopifyOperationIdempotencyModel extends Model
{
    protected $table = 'shopify_operation_idempotency';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'operation_key',
        'operation_type',
        'status',
        'response_hash',
        'completed_at',
        'error_message',
        'retry_count',
        'created_at',
        'updated_at',
    ];
    protected $useTimestamps = false;
}
