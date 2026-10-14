<?php

namespace Admin\Models;

use CodeIgniter\Model;

class InvoiceItemModel extends Model
{
    protected $table = 'invoice_items';

    protected $primaryKey = 'id';

    protected $returnType = 'array';

    protected $allowedFields = ['invoice_id', 'description', 'quantity', 'unit_price', 'line_total', 'created_at', 'updated_at'];

    protected $useTimestamps = true;
}
