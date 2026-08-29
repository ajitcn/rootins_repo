<?php

namespace Admin\Models;

use CodeIgniter\Model;

class MedicineModel extends Model
{
    protected $table          = 'medicines';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields  = true;

    protected $allowedFields = [
        'medicine_name',
        'generic_name',
        'medicine_code',
        'dosage_form',
        'strength',
        'manufacturer',
        'description',
        'status',
        'created_at',
        'updated_at',
    ];

    protected $validationRules = [
        'medicine_name' => 'required|max_length[255]',
        'generic_name'  => 'permit_empty|max_length[255]',
        'medicine_code' => 'permit_empty|max_length[50]',
        'dosage_form'   => 'permit_empty|max_length[100]',
        'strength'      => 'permit_empty|max_length[100]',
        'manufacturer'  => 'permit_empty|max_length[255]',
        'status'        => 'required|max_length[30]',
    ];

    protected $validationMessages = [];
    protected $skipValidation     = false;

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
