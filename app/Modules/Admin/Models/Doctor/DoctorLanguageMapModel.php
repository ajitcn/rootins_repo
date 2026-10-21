<?php

namespace Admin\Models\Doctor;

use CodeIgniter\Model;

final class DoctorLanguageMapModel extends Model
{
    protected $table = 'doctor_language_map';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['doctor_id', 'language_id'];
    protected $useTimestamps = true;
    protected $skipValidation = true;

    public function getDoctorLanguages(int $doctorId): array { return array_map('intval', $this->where('doctor_id', $doctorId)->findColumn('language_id') ?? []); }
}
