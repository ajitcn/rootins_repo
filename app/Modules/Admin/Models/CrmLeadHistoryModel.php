<?php
namespace Admin\Models;
use CodeIgniter\Model;
class CrmLeadHistoryModel extends Model { protected $table = 'crm_lead_history'; protected $returnType = 'array'; protected $allowedFields = ['lead_id','clinic_id','event_type','previous_value','new_value','remarks','follow_up_id','actor_user_id','created_at']; protected $useTimestamps = false; }
