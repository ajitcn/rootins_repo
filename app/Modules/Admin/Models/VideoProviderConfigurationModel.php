<?php

namespace Admin\Models;

use CodeIgniter\Model;

final class VideoProviderConfigurationModel extends Model
{
    protected $table = 'video_provider_configurations';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $allowedFields = [
        'provider', 'account_sid_encrypted', 'api_key_sid_encrypted', 'api_key_secret_encrypted',
        'status', 'created_by', 'updated_by', 'created_at', 'updated_at',
    ];
}
