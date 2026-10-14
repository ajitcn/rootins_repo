<?php

namespace Admin\Models\Location;

use CodeIgniter\Model;

final class Locality extends Model
{
    protected $table = 'localities';

    protected $primaryKey = 'id';

    protected $returnType = 'array';

    protected $allowedFields = ['city_id', 'name', 'normalized_name', 'status', 'created_at', 'updated_at'];
}
