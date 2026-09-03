<?php

namespace App\Services;

use Admin\Models\Location\Country;
use Admin\Models\Location\State;
use Admin\Models\Location\City;
use Admin\Models\Location\Locality;
use InvalidArgumentException;
class LocationService
{
    protected Country $country;
    protected State $state;
    protected City $city;
    protected Locality $locality;
    public function __construct()
    {
        $this->country = new Country();
        $this->state = new State();
        $this->city = new City();
        $this->locality = new Locality();
    }
    /**
     * Country List
     */
    public function countries(): array
    {
        return $this->country->dropdown();
    }
    /**
     * State List
     */
    public function states(int $countryId): array
    {
        return $this->state->byCountry($countryId);
    }
    /**
     * City List
     */
    public function cities(int $stateId): array
    {
        return $this->city->byState($stateId);
    }
    public function localities(int $cityId, string $search = ''): array
    {
        $query = $this->locality->where('city_id', $cityId)->where('status', 1)->orderBy('name');
        if ($search !== '') {
            $query->like('normalized_name', $this->normalize($search));
        }
        return $query->findAll(100);
    }
    /** Resolve selected IDs or normalized manual values in Country > State > City > Locality order. */
    public function resolvePatientLocation(array $input): array
    {
        $country = $this->resolve('countries', (int) ($input['country_id'] ?? 0), $input['country_name'] ?? '', [], 'country');
        $state = $this->resolve('states', (int) ($input['state_id'] ?? 0), $input['state_name'] ?? '', $country ? ['country_id' => $country['id']] : [], 'state');
        $city = $this->resolve('cities', (int) ($input['city_id'] ?? 0), $input['city_name'] ?? '', $state ? ['state_id' => $state['id']] : [], 'city');
        $locality = $this->resolve('localities', (int) ($input['locality_id'] ?? 0), $input['locality_name'] ?? '', $city ? ['city_id' => $city['id']] : [], 'locality');
        return ['country_id' => $country['id'] ?? null, 'state_id' => $state['id'] ?? null, 'city_id' => $city['id'] ?? null, 'locality_id' => $locality['id'] ?? null];
    }
    private function resolve(string $table, int $id, string $name, array $parent, string $label): ?array
    {
        // A selected lookup ID is authoritative. Manual fallback fields may
        // be empty when no value is selected, so normalize only real input.
        if ($id > 0) {
            $name = '';
        } else {
            $name = trim((string) $name);
            if ($name !== '') {
                $name = $this->normalize($name);
            }
        }
        if ($table !== 'countries' && $parent === [] && ($id > 0 || $name !== '')) {
            throw new InvalidArgumentException("A parent is required for the selected {$label}.");
        }
        $db = db_connect();
        if ($id > 0) {
            $row = $db->table($table)->where('id', $id)->where('status', 1)->get()->getRowArray();
            if ($row === null || $parent !== [] && (int) ($row[array_key_first($parent)] ?? 0) !== (int) reset($parent)) {
                throw new InvalidArgumentException("The selected {$label} is invalid for its parent.");
            }
            return $row;
        }
        if ($name === '') {
            return null;
        }
        if ($parent === []) {
            $row = $db->table($table)->where('normalized_name', $name)->where('status', 1)->get()->getRowArray();
        } else {
            $row = $db->table($table)->where($parent)->where('normalized_name', $name)->where('status', 1)->get()->getRowArray();
        }
        if ($row !== null) {
            return $row;
        }
        if ($parent === [] && $table === 'countries') {
            $letters = strtoupper(preg_replace('/[^A-Z]/i', '', $name));
            $iso2 = str_pad(substr($letters, 0, 2), 2, 'X');
            $iso3 = str_pad(substr($letters, 0, 3), 3, 'X');
            $data = array_merge(['name' => $name, 'normalized_name' => $name, 'iso2' => $iso2, 'iso3' => $iso3, 'status' => 1], $parent);
        } else {
            $data = array_merge($parent, ['name' => $name, 'normalized_name' => $name, 'status' => 1]);
        }
        try {
            $db->table($table)->insert($data);
            $newId = (int) $db->insertID();
        } catch (\Throwable $e) {
            $row = $db->table($table)->where($parent)->where('normalized_name', $name)->get()->getRowArray();
            if ($row !== null) {
                return $row;
            }
            throw new InvalidArgumentException("The {$label} could not be saved safely.", 0, $e);
        }
        if ($newId < 1) {
            throw new InvalidArgumentException("The {$label} could not be saved safely.");
        }
        return $db->table($table)->where('id', $newId)->get()->getRowArray();
    }
    private function normalize(string $value): string
    {
        $value = trim((string) preg_replace('/\s+/u', ' ', $value));
        if ($value === '' || strlen($value) > 191) {
            throw new InvalidArgumentException('Location value is invalid.');
        }
        return strtolower($value);
    }
}
