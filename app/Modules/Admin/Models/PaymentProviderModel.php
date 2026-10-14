<?php

namespace Admin\Models;

use CodeIgniter\Model;

final class PaymentProviderModel extends Model
{
    protected $table = 'payment_providers';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['provider_name', 'provider_code', 'provider_type', 'default_currency', 'status', 'sort_order', 'archived_at'];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
