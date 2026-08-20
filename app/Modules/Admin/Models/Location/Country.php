<?php

namespace Admin\Models\Location;

use CodeIgniter\Model;

class Country extends Model
{
    protected $table = 'countries';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    public function dropdown()
    {
        return $this->where('status', 1)
                    ->orderBy('name')
                    ->findAll();
    }
}