<?php

namespace Admin\Models\Clinic;

use CodeIgniter\Model;

class ClinicModel extends Model
{
    protected $table = 'clinics';

    protected $primaryKey = 'id';

    protected $returnType = 'array';

    protected $useSoftDeletes = false;

    protected $allowedFields = ['country_id', 'country_code_id', 'telephone_country_code_id', 'clinic_code', 'state_id', 'city_id', 'clinic_name', 'contact_person', 'email', 'mobile', 'mobile_normalized', 'address', 'pincode', 'status'];

    protected bool $allowEmptyInserts = false;

    protected bool $updateOnlyChanged = true;

    protected $useTimestamps = true;

    protected $dateFormat = 'datetime';

    protected $createdField = 'created_at';

    protected $updatedField = 'updated_at';

    protected $deletedField = 'deleted_at';

    protected $validationRules = ['clinic_name' => 'required|min_length[3]|max_length[150]', 'country_id' => 'required|integer', 'state_id' => 'required|integer', 'city_id' => 'required|integer', 'mobile' => 'permit_empty|max_length[20]', 'email' => 'permit_empty|valid_email|max_length[150]', 'status' => 'required|in_list[0,1]'];

    protected $validationMessages = [];

    protected $skipValidation = false;

    /**
     * Get active clinics.
     */
    public function getActive()
    {
        return $this->where('status', 1)->findAll();
    }

    /**
     * Dropdown list.
     */
    public function dropdown()
    {
        return $this->select('id, clinic_name')->where('status', 1)->orderBy('clinic_name')->findAll();
    }

    /**
     * Clinic with location.
     */
    public function getClinic(int $id)
    {
        return $this->select('
                    clinics.*,
                    countries.name AS country_name,
                    states.name AS state_name,
                    cities.name AS city_name,
                    (SELECT COUNT(*) FROM doctors WHERE doctors.clinic_id = clinics.id) AS doctors_count,
                    (SELECT COUNT(*) FROM patients WHERE patients.clinic_id = clinics.id) AS patients_count,
                    (SELECT COUNT(DISTINCT appointment_services.service_id) FROM appointment_services INNER JOIN appointments ON appointments.id = appointment_services.appointment_id WHERE appointments.clinic_id = clinics.id AND appointment_services.service_id IS NOT NULL) AS services_count
                ')->join('countries', 'countries.id = clinics.country_id')->join('states', 'states.id = clinics.state_id')->join('cities', 'cities.id = clinics.city_id')->find($id);
    }

    /**
     * Clinic listing.
     */
    public function getList()
    {
        return $this->select('
                    clinics.*,
                    countries.name AS country_name,
                    states.name AS state_name,
                    cities.name AS city_name
                ')->join('countries', 'countries.id = clinics.country_id')->join('states', 'states.id = clinics.state_id')->join('cities', 'cities.id = clinics.city_id')->orderBy('clinic_name')->findAll();
    }
}
