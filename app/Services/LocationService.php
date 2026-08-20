<?php

namespace App\Services;

use Admin\Models\Location\Country;
use Admin\Models\Location\State;
use Admin\Models\Location\City;

class LocationService
{
    protected Country $country;
    protected State $state;
    protected City $city;

    public function __construct()
    {
        $this->country = new Country();
        $this->state   = new State();
        $this->city    = new City();
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
}