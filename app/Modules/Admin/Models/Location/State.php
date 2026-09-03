<?php

namespace Admin\Models\Location;

use CodeIgniter\Model;
class State extends Model
{
    protected $table = 'states';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    public function byCountry($countryId)
    {
        return $this->where('country_id', $countryId)->where('status', 1)->orderBy('name')->findAll();
    }
}
