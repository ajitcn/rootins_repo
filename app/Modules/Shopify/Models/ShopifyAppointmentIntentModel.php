<?php

namespace App\Modules\Shopify\Models;

use CodeIgniter\Model;

final class ShopifyAppointmentIntentModel extends Model
{
    protected $table = 'shopify_appointment_intents';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'public_reference', 'shopify_store_domain', 'shopify_customer_id', 'patient_id', 'patient_reference',
        'clinic_id', 'doctor_id', 'service_id', 'appointment_date', 'appointment_time', 'consultation_mode', 'duration_minutes',
        'amount', 'currency', 'idempotency_key', 'status', 'shopify_order_id', 'shopify_order_number',
        'appointment_id', 'expires_at', 'paid_at', 'failure_reason', 'created_at', 'updated_at',
    ];
    protected $useTimestamps = false;
}
