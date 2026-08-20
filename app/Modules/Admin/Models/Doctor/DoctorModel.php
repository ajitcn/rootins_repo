<?php

namespace Admin\Models\Doctor;

use CodeIgniter\Model;

class DoctorModel extends Model
{
    protected $table            = 'doctors';
    protected $primaryKey       = 'id';

    protected $returnType       = 'array';

    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    protected $allowedFields = [
        'clinic_id',
        'city_id',
        'doctor_code',
        'first_name',
        'last_name',
        'gender',
        'dob',
        'qualification',
        'experience',
        'registration_number',
        'mobile',
        'email',
        'address',
        'consultation_duration',
        'signature',
        'profile_photo',
        'notes',
        'online_booking',
        'display_order',
        'status',
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';

    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules      = [];
    protected $validationMessages   = [];
    protected $skipValidation       = true;
    protected $cleanValidationRules = true;




    public function getDoctors()
    {
        return $this->db->table('doctors')
                    ->select("
                    doctors.*,
                    clinics.clinic_name,
                    cities.name AS city_name,
                    GROUP_CONCAT(
                        doctor_specializations.specialization_name
                        ORDER BY doctor_specializations.specialization_name
                        SEPARATOR ', '
                    ) AS specializations
                ")
                ->join('clinics', 'clinics.id = doctors.clinic_id')
                ->join('cities', 'cities.id = doctors.city_id')
                ->join(
                    'doctor_specialization_map',
                    'doctor_specialization_map.doctor_id = doctors.id',
                    'left'
                )
                ->join(
                    'doctor_specializations',
                    'doctor_specializations.id = doctor_specialization_map.specialization_id',
                    'left'
                )
                ->groupBy('doctors.id')
                ->orderBy('doctors.display_order', 'ASC')
                ->orderBy('doctors.first_name', 'ASC')
                ->get()
                ->getResultArray();
    }
}