<?php
namespace App\Modules\Shopify\Models;
use CodeIgniter\Model;

final class ShopifyAiAnalysisCallbackRequestModel extends Model
{
    protected $table='shopify_ai_analysis_callback_requests'; protected $returnType='array'; protected $useTimestamps=true;
    protected $allowedFields=['analysis_type','lead_id','lead_reference','analysis_id','store_domain','shopify_customer_id','preferred_time','note','status','priority','handled_by','handled_at','created_at','updated_at'];
}
