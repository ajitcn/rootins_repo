<?php

namespace Pharmacy\Models;

use CodeIgniter\Model;

final class AuditLogModel extends Model
{
    protected $table = 'audit_logs';

    protected $primaryKey = 'id';

    protected $returnType = 'array';

    protected $allowedFields = ['action', 'correlation_id', 'actor_user_id', 'clinic_id', 'route', 'occurred_at', 'context', 'created_at'];
}
