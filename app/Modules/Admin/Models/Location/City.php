<?php

namespace Admin\Models\Location;

use CodeIgniter\Model;

class City extends Model
{
    protected $table = 'cities';

    protected $primaryKey = 'id';

    protected $returnType = 'array';

    public function byState($stateId)
    {
        return $this->where('state_id', $stateId)->where('status', 1)->orderBy('name')->findAll();
    }

    public function dropdown()
    {
        return $this->where('status', 1)->orderBy('name')->findAll();
    }
}
