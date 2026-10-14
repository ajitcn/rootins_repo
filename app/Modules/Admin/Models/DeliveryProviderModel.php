<?php

namespace Admin\Models;

use CodeIgniter\Model;

final class DeliveryProviderModel extends Model
{
    protected $table = 'delivery_providers';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['provider_name', 'provider_code', 'tracking_url_template', 'support_contact', 'status', 'sort_order', 'archived_at'];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
