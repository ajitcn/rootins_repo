<?php

namespace App\Modules\Shopify\Models;

use CodeIgniter\Model;

final class ShopifyAppointmentBookingModel extends Model
{
    protected $table = 'shopify_appointment_bookings';

    protected $primaryKey = 'id';

    protected $returnType = 'array';

    protected $allowedFields = [
        'shopify_store_domain',
        'external_reference',
        'idempotency_key',
        'patient_id',
        'clinic_id',
        'appointment_id',
        'status',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = false;
}
