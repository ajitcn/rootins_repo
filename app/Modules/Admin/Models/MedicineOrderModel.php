<?php

namespace Admin\Models;

use CodeIgniter\Model;
class MedicineOrderModel extends Model
{
    protected $table = 'medicine_orders';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['order_number', 'prescription_id', 'patient_id', 'clinic_id', 'status', 'payment_status', 'delivery_status', 'idempotency_key', 'payment_provider', 'payment_reference', 'last_reconciled_at', 'created_at', 'updated_at'];
    protected $useTimestamps = true;
}
