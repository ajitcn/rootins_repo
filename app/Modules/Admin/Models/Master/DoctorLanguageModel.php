<?php

namespace Admin\Models\Master;

use CodeIgniter\Model;

final class DoctorLanguageModel extends Model
{
    protected $table = 'doctor_languages';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['language_name', 'normalized_name', 'status'];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $skipValidation = true;

    public function dropdown(): array { return $this->where('status', 1)->orderBy('language_name', 'ASC')->findAll(); }
}
