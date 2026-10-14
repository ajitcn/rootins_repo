<?php

namespace Admin\Models;

use CodeIgniter\Model;

final class PaymentStatusModel extends Model
{
    protected $table = 'payment_statuses';

    protected $primaryKey = 'id';

    protected $returnType = 'array';

    protected $useSoftDeletes = false;

    protected $useTimestamps = true;

    protected $createdField = 'created_at';

    protected $updatedField = 'updated_at';

    protected $allowedFields = [
        'status_name',
        'status_code',
        'description',
        'sort_order',
        'status',
        'archived_at',
        'created_at',
        'updated_at',
    ];

    public function activeOptions(): array
    {
        return $this->where('status', 1)
            ->where('archived_at', null)
            ->orderBy('sort_order', 'ASC')
            ->orderBy('status_name', 'ASC')
            ->findAll();
    }
}
