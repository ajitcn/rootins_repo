<?php

namespace Admin\Models;

use CodeIgniter\Model;

class MedicineOrderItemModel extends Model
{
    protected $table = 'medicine_order_items';

    protected $primaryKey = 'id';

    protected $returnType = 'array';

    protected $allowedFields = ['order_id', 'prescription_item_id', 'medicine_id', 'medicine_name', 'batch_number', 'inventory_batch_id', 'dosage', 'frequency', 'duration', 'duration_unit', 'instructions', 'quantity', 'unit_price', 'line_total', 'created_at', 'updated_at'];

    protected $useTimestamps = true;
}
