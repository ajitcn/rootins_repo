<?php

namespace Admin\Models\Configuration;

use CodeIgniter\Model;

/**
 * Common behavior for dedicated, clinic-aware administrator catalogs.
 */
abstract class ScopedCatalogModel extends Model
{
    protected $primaryKey = 'id';

    protected $returnType = 'array';

    protected $useSoftDeletes = false;

    protected $protectFields = true;

    protected $useTimestamps = true;

    protected $dateFormat = 'datetime';

    protected $createdField = 'created_at';

    protected $updatedField = 'updated_at';

    protected $allowedFields = [
        'name',
        'normalized_name',
        'code',
        'normalized_code',
        'description',
        'amount',
        'status',
        'clinic_id',
        'created_by',
        'legacy_master_id',
        'scope_key',
        'created_at',
        'updated_at',
    ];

    public function forScope(?int $clinicId = null): self
    {
        if ($clinicId !== null) {
            $this->groupStart()
                ->where('clinic_id', $clinicId)
                ->orWhere('clinic_id', null)
                ->groupEnd();
        }

        return $this;
    }

    public function normalized(string $value): string
    {
        return mb_strtolower(trim($value));
    }
}
