<?php

namespace Admin\Models\Clinic;

use CodeIgniter\Model;
class ClinicModel extends Model
{
    protected $table = 'clinics';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $allowedFields = ['country_id', 'clinic_code', 'state_id', 'city_id', 'clinic_name', 'contact_person', 'email', 'mobile', 'address', 'pincode', 'status'];
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
     * Get active clinics
     */
    public function getActive()
    {
        return $this->where('status', 1)->findAll();
    }
    /**
     * Dropdown list
     */
    public function dropdown()
    {
        return $this->select('id, clinic_name')->where('status', 1)->orderBy('clinic_name')->findAll();
    }
    /**
     * Clinic with location
     */
    public function getClinic(int $id)
    {
        return $this->select('
                    clinics.*,
                    countries.name AS country_name,
                    states.name AS state_name,
                    cities.name AS city_name
                ')->join('countries', 'countries.id = clinics.country_id')->join('states', 'states.id = clinics.state_id')->join('cities', 'cities.id = clinics.city_id')->find($id);
    }
    /**
     * Clinic listing
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
