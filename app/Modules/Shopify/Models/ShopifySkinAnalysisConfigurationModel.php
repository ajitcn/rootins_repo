<?php
namespace App\Modules\Shopify\Models;
use CodeIgniter\Model;
final class ShopifySkinAnalysisConfigurationModel extends Model { protected $table='shopify_skin_analysis_configurations'; protected $returnType='array'; protected $useTimestamps=true; protected $allowedFields=['version','prompt_text','schema_json','model','temperature','is_active','created_by','created_at','updated_at']; }
