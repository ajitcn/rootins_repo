<?php

namespace Admin\Models;

use CodeIgniter\Model;

class MedicineInventoryBatchModel extends Model
{
    protected $table = 'medicine_inventory_batches';

    protected $primaryKey = 'id';

    protected $returnType = 'array';

    protected $allowedFields = ['clinic_id', 'medicine_id', 'batch_number', 'expiry_date', 'quantity_available', 'status', 'created_at', 'updated_at'];

    protected $useTimestamps = true;
}
