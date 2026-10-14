<?php

namespace Admin\Models;

use CodeIgniter\Model;

class MedicineOrderModel extends Model
{
    protected $table = 'medicine_orders';

    protected $primaryKey = 'id';

    protected $returnType = 'array';

    protected $allowedFields = ['order_number', 'prescription_id', 'patient_id', 'clinic_id', 'status', 'payment_status', 'delivery_status', 'idempotency_key', 'payment_provider', 'payment_reference', 'last_reconciled_at', 'delivery_provider', 'delivery_provider_id', 'delivery_provider_name_snapshot', 'tracking_reference', 'delivery_address_snapshot', 'delivery_contact_snapshot', 'dispatched_at', 'delivered_at', 'failure_reason', 'return_reason', 'source_type', 'external_store_domain', 'external_order_id', 'external_order_number', 'created_at', 'updated_at'];

    protected $useTimestamps = true;
}
