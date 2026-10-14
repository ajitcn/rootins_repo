<?php

namespace Pharmacy\Models;

use CodeIgniter\Model;

final class PharmacyNotificationModel extends Model
{
    protected $table = 'pharmacy_notifications';

    protected $primaryKey = 'id';

    protected $returnType = 'array';

    protected $useTimestamps = true;

    protected $allowedFields = ['clinic_id', 'recipient_user_id', 'recipient_role', 'type', 'title', 'message', 'related_entity_type', 'related_entity_id', 'event_key', 'read_at', 'created_at', 'updated_at'];
}
