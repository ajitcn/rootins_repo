<?php
namespace Admin\Models;
use CodeIgniter\Model;
class CrmLeadSourceModel extends Model { protected $table = 'crm_lead_sources'; protected $returnType = 'array'; protected $allowedFields = ['code','name','sort_order','status','created_at','updated_at']; protected $useTimestamps = true; }
