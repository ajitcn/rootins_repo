<?php

namespace Admin\Models;

use CodeIgniter\Model;

class PrescriptionItemModel extends Model
{
    protected $table          = 'prescription_items';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields  = true;

    protected $allowedFields = [
        'prescription_id',
        'medicine_name',
        'dosage',
        'frequency',
        'duration',
        'duration_unit',
        'instructions',
        'quantity',
        'notes',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
