<?php
namespace App\Modules\Shopify\Models;
use CodeIgniter\Model;
final class ShopifySkinAnalysisLeadModel extends Model { protected $table='shopify_skin_analysis_leads'; protected $returnType='array'; protected $useTimestamps=true; protected $allowedFields=['lead_reference','store_domain','shopify_customer_id','idempotency_key','first_name','last_name','age','gender','email','mobile_number','country_code','date_of_birth','concern','health_list','questionnaire_json','status','result_json','error_code','error_message','patient_id','processed_at','created_at','updated_at']; }
