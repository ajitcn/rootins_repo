<?php

namespace Admin\Models\Doctor;

use CodeIgniter\Model;
class DoctorModel extends Model
{
    protected $table = 'doctors';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;
    protected $allowedFields = ['clinic_id', 'city_id', 'doctor_code', 'first_name', 'last_name', 'gender', 'dob', 'qualification', 'experience', 'registration_number', 'mobile', 'email', 'address', 'consultation_duration', 'signature', 'profile_photo', 'notes', 'online_booking', 'display_order', 'status'];
    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';
    protected $validationRules = [];
    protected $validationMessages = [];
    protected $skipValidation = true;
    protected $cleanValidationRules = true;
    public function getDoctors()
    {
        return $this->db->table('doctors')->select("\r\n                    doctors.*,\r\n                    clinics.clinic_name,\r\n                    cities.name AS city_name,\r\n                    GROUP_CONCAT(\r\n                        doctor_specializations.specialization_name\r\n                        ORDER BY doctor_specializations.specialization_name\r\n                        SEPARATOR ', '\r\n                    ) AS specializations\r\n                ")->join('clinics', 'clinics.id = doctors.clinic_id')->join('cities', 'cities.id = doctors.city_id')->join('doctor_specialization_map', 'doctor_specialization_map.doctor_id = doctors.id', 'left')->join('doctor_specializations', 'doctor_specializations.id = doctor_specialization_map.specialization_id', 'left')->groupBy('doctors.id')->orderBy('doctors.display_order', 'ASC')->orderBy('doctors.first_name', 'ASC')->get()->getResultArray();
    }
    /**
     * Return a filtered, bounded doctor list for the Admin register.
     */
    public function getDoctorsPage(string $search, ?int $status, int $page, int $pageSize, int $clinicId = 0): array
    {
        $query = $this->doctorListQuery($search, $status, $clinicId);
        $total = $query->countAllResults(false);
        $pages = max(1, (int) ceil($total / $pageSize));
        $page = min(max(1, $page), $pages);
        return ['records' => $query->limit($pageSize, ($page - 1) * $pageSize)->get()->getResultArray(), 'pagination' => ['page' => $page, 'pages' => $pages, 'page_size' => $pageSize, 'total' => $total]];
    }
    public function findDoctorWithDetails(int $doctorId, int $clinicId = 0): ?array
    {
        $query = $this->doctorListQuery('', null, $clinicId)->where('doctors.id', $doctorId);
        $record = $query->get()->getRowArray();
        return $record ?: null;
    }
    private function doctorListQuery(string $search, ?int $status, int $clinicId = 0)
    {
        $query = $this->db->table('doctors')->select("doctors.*, clinics.clinic_name, cities.name AS city_name, GROUP_CONCAT(doctor_specializations.specialization_name ORDER BY doctor_specializations.specialization_name SEPARATOR ', ') AS specializations")->join('clinics', 'clinics.id = doctors.clinic_id')->join('cities', 'cities.id = doctors.city_id')->join('doctor_specialization_map', 'doctor_specialization_map.doctor_id = doctors.id', 'left')->join('doctor_specializations', 'doctor_specializations.id = doctor_specialization_map.specialization_id', 'left')->groupBy('doctors.id');
        if ($search !== '') {
            $query->groupStart()->like('doctors.first_name', $search)->orLike('doctors.last_name', $search)->orLike('doctors.doctor_code', $search)->orLike('doctors.email', $search)->orLike('clinics.clinic_name', $search)->groupEnd();
        }
        if ($status !== null) {
            $query->where('doctors.status', $status);
        }
        if ($clinicId > 0) {
            $query->where('doctors.clinic_id', $clinicId);
        }
        return $query->orderBy('doctors.display_order', 'ASC')->orderBy('doctors.first_name', 'ASC');
    }
}
