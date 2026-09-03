<?php

namespace Admin\Models;

use CodeIgniter\Model;
class AdminMasterRecordModel extends Model
{
    protected $table = 'admin_master_records';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['master_type', 'name', 'code', 'description', 'amount', 'status', 'clinic_id', 'created_by', 'created_at', 'updated_at'];
    protected string $masterType = '';
    public function forScope(?int $clinicId = null): self
    {
        if ($this->masterType !== '') {
            $this->where('master_type', $this->masterType);
        }
        if ($clinicId !== null) {
            $this->groupStart()->where('clinic_id', $clinicId)->orWhere('clinic_id', null)->groupEnd();
        }
        return $this;
    }
    public function type(): string
    {
        return $this->masterType;
    }
}
