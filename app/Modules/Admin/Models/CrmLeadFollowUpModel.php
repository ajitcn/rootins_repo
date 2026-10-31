<?php
namespace Admin\Models;
use CodeIgniter\Model;
class CrmLeadFollowUpModel extends Model { protected $table = 'crm_lead_followups'; protected $returnType = 'array'; protected $useSoftDeletes = true; protected $allowedFields = ['follow_up_number','lead_id','clinic_id','patient_id','follow_up_date','follow_up_time','follow_up_type_id','follow_up_status','priority','assigned_user_id','remarks','completed_at','completed_by','created_by','updated_by','created_at','updated_at','deleted_at']; protected $useTimestamps = true; }
