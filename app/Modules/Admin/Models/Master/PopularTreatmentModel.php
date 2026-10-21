<?php

namespace Admin\Models\Master;

use CodeIgniter\Model;

final class PopularTreatmentModel extends Model
{
    protected $table = 'popular_treatments';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['treatment_name', 'normalized_name', 'status'];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $skipValidation = true;

    public function dropdown(): array { return $this->where('status', 1)->orderBy('treatment_name', 'ASC')->findAll(); }
}
