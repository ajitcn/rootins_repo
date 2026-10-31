<?php
namespace App\Modules\Shopify\Models;
use CodeIgniter\Model;
final class ShopifySkinAnalysisImageModel extends Model { protected $table='shopify_skin_analysis_images'; protected $returnType='array'; protected $useTimestamps=false; protected $allowedFields=['lead_id','angle','storage_key','mime_type','file_size','created_at']; }
