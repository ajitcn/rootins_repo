<?php

namespace Admin\Models;

use CodeIgniter\Model;

final class MedicineStatusOptionModel extends Model
{
    protected $table = 'medicine_status_options';

    protected $primaryKey = 'id';

    protected $returnType = 'array';

    protected $useSoftDeletes = false;

    protected $protectFields = true;

    protected $allowedFields = ['code', 'name', 'normalized_code', 'status', 'created_at', 'updated_at'];

    protected $useTimestamps = true;

    protected $dateFormat = 'datetime';

    protected $createdField = 'created_at';

    protected $updatedField = 'updated_at';

    public function activeOptions(): array
    {
        return $this->where('status', 1)->orderBy('name', 'ASC')->findAll();
    }
}
