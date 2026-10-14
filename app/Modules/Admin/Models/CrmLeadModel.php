<?php
namespace Admin\Models;
use CodeIgniter\Model;
class CrmLeadModel extends Model { protected $table = 'crm_leads'; protected $returnType = 'array'; protected $useSoftDeletes = true; protected $allowedFields = ['lead_number','clinic_id','full_name','mobile_number','mobile_normalized','telephone_country_code_id','country_code','email','gender','date_of_birth','source_id','interested_service','preferred_doctor_id','preferred_specialization_id','lead_status_id','priority','assigned_user_id','assigned_doctor_id','next_follow_up_date','next_follow_up_time','remarks','converted_patient_id','converted_appointment_id','converted_at','converted_by','created_by','updated_by','created_at','updated_at','deleted_at']; protected $useTimestamps = true; }
