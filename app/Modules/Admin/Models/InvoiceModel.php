<?php

namespace Admin\Models;

use CodeIgniter\Model;
class InvoiceModel extends Model
{
    protected $table = 'invoices';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['invoice_number', 'clinic_id', 'patient_id', 'appointment_id', 'currency', 'subtotal', 'tax', 'discount', 'total', 'status', 'payment_status', 'idempotency_key', 'created_by', 'created_at', 'updated_at'];
    protected $useTimestamps = true;
}
