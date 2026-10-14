<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use Admin\Models\Location\State;
use Admin\Models\Location\City;
use Admin\Models\Location\Locality;

class Location extends BaseController
{
    public function getStates($countryId)
    {
        $state = new State();
        $records = $state->where('country_id', $countryId)->findAll();
        $options = '';

        foreach ($records as $row) {
            $options .= '<option value="' . $row['id'] . '">' . htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') . '</option>';
        }

        return $this->response->setJSON(['status' => true, 'data' => $options]);
    }

    public function getCities($stateId)
    {
        $city = new City();
        $records = $city->where('state_id', $stateId)->findAll();
        $options = '';

        foreach ($records as $row) {
            $options .= '<option value="' . $row['id'] . '">' . htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') . '</option>';
        }

        return $this->response->setJSON(['status' => true, 'data' => $options]);
    }

    public function getLocalities($cityId)
    {
        $search = trim((string) $this->request->getGet('search'));
        $query = (new Locality())->where('city_id', (int) $cityId)->where('status', 1)->orderBy('name');

        if ($search !== '') {
            $query->like('normalized_name', strtolower($search));
        }
        $records = $query->findAll(100);
        $options = '';

        foreach ($records as $row) {
            $options .= '<option value="' . (int) $row['id'] . '">' . htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') . '</option>';
        }

        return $this->response->setJSON(['status' => true, 'data' => $options]);
    }
}
