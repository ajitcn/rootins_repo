<?php

namespace Admin\Models;

use CodeIgniter\Model;

class MedicinePrescriptionDefaultModel extends Model
{
    protected $table = 'medicine_prescription_defaults';

    protected $primaryKey = 'id';

    protected $returnType = 'array';

    protected $useSoftDeletes = false;

    protected $protectFields = true;

    protected $allowedFields = [
        'medicine_id',
        'clinic_id',
        'scope_key',
        'duration_id',
        'frequency_id',
        'dose_id',
        'intake_id',
        'note',
        'status',
        'created_by',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = true;

    protected $dateFormat = 'datetime';

    protected $createdField = 'created_at';

    protected $updatedField = 'updated_at';

    public function forMedicine(int $medicineId, int $clinicId): ?array
    {
        if ($medicineId <= 0 || $clinicId <= 0) {
            return null;
        }

        return $this->where('medicine_id', $medicineId)
            ->where('status', 1)
            ->groupStart()
            ->where('scope_key', 'clinic:' . $clinicId)
            ->orWhere('scope_key', 'global')
            ->groupEnd()
            ->orderBy('clinic_id', 'DESC')
            ->orderBy('id', 'DESC')
            ->first() ?: null;
    }
}
