<?php

namespace Admin\Models;

use CodeIgniter\Model;
final class AuditLogModel extends Model
{
    protected $table = 'audit_logs';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['actor_user_id', 'clinic_id', 'action', 'correlation_id', 'route', 'occurred_at', 'context'];
}
