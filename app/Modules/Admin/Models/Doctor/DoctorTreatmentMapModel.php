<?php

namespace Admin\Models\Doctor;

use CodeIgniter\Model;

final class DoctorTreatmentMapModel extends Model
{
    protected $table = 'doctor_treatment_map';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['doctor_id', 'treatment_id'];
    protected $useTimestamps = true;
    protected $skipValidation = true;

    public function getDoctorTreatments(int $doctorId): array { return array_map('intval', $this->where('doctor_id', $doctorId)->findColumn('treatment_id') ?? []); }
}
