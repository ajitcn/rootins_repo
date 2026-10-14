<?php

namespace Admin\Models;

use CodeIgniter\Model;

class MedicineModel extends Model
{
    protected $table = 'medicines';

    protected $primaryKey = 'id';

    protected $returnType = 'array';

    protected $useSoftDeletes = false;

    protected $protectFields = true;

    protected $allowedFields = ['medicine_name', 'generic_name', 'medicine_code', 'dosage_form', 'form_id', 'strength', 'strength_id', 'unit', 'unit_id', 'manufacturer', 'manufacturer_id', 'description', 'unit_price', 'status', 'status_id', 'prescription_duration_id', 'prescription_frequency_id', 'prescription_dose_id', 'prescription_intake_id', 'prescription_note', 'created_at', 'updated_at'];

    protected $validationRules = ['medicine_name' => 'required|max_length[255]', 'generic_name' => 'permit_empty|max_length[255]', 'medicine_code' => 'permit_empty|max_length[50]', 'dosage_form' => 'permit_empty|max_length[100]', 'strength' => 'permit_empty|max_length[100]', 'unit' => 'permit_empty|max_length[100]', 'manufacturer' => 'permit_empty|max_length[255]', 'status' => 'required|in_list[active,inactive]', 'prescription_duration_id' => 'permit_empty|is_natural_no_zero', 'prescription_frequency_id' => 'permit_empty|is_natural_no_zero', 'prescription_dose_id' => 'permit_empty|is_natural_no_zero', 'prescription_intake_id' => 'permit_empty|is_natural_no_zero', 'prescription_note' => 'permit_empty|max_length[2000]'];

    protected $validationMessages = [];

    protected $skipValidation = false;

    protected $useTimestamps = true;

    protected $dateFormat = 'datetime';

    protected $createdField = 'created_at';

    protected $updatedField = 'updated_at';
}
