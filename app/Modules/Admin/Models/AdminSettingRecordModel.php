<?php

namespace Admin\Models;

use CodeIgniter\Model;
class AdminSettingRecordModel extends Model
{
    protected $table = 'admin_setting_records';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['setting_group', 'setting_key', 'setting_value', 'is_secret', 'clinic_id', 'created_by', 'created_at', 'updated_at'];
    protected string $settingGroup = '';
    public function forScope(?int $clinicId = null): self
    {
        if ($this->settingGroup !== '') {
            $this->where('setting_group', $this->settingGroup);
        }
        if ($clinicId !== null) {
            $this->groupStart()->where('clinic_id', $clinicId)->orWhere('clinic_id', null)->groupEnd();
        }
        return $this;
    }
    public function group(): string
    {
        return $this->settingGroup;
    }
}
