<?php
namespace Admin\Models;
use CodeIgniter\Model;
class CrmLeadStatusModel extends Model { protected $table = 'crm_lead_statuses'; protected $returnType = 'array'; protected $allowedFields = ['code','name','description','sort_order','status','created_at','updated_at']; protected $useTimestamps = true; }
