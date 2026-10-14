<?php

namespace Admin\Models;

use CodeIgniter\Model;

abstract class MedicineLookupModel extends Model
{
    protected $primaryKey = 'id';

    protected $returnType = 'array';

    protected $useSoftDeletes = false;

    protected $protectFields = true;

    protected $allowedFields = ['name', 'normalized_name', 'status', 'created_at', 'updated_at'];

    protected $useTimestamps = true;

    protected $dateFormat = 'datetime';

    protected $createdField = 'created_at';

    protected $updatedField = 'updated_at';

    public function forScope(?int $clinicId = null): self
    {
        return $this;
    }

    public function activeOptions(?int $clinicId = null): array
    {
        return $this->where('status', 1)->orderBy('name', 'ASC')->findAll();
    }
}
