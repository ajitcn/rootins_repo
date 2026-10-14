<?php

namespace Doctor\Models;

use CodeIgniter\Model;

final class FollowUpTypeModel extends Model
{
    protected $table = 'follow_up_types';

    protected $primaryKey = 'id';

    protected $returnType = 'array';

    protected $useSoftDeletes = false;

    protected $protectFields = true;

    protected $allowedFields = ['clinic_id', 'name', 'normalized_name', 'status', 'scope_key', 'created_at', 'updated_at'];

    protected $useTimestamps = true;

    protected $dateFormat = 'datetime';

    protected $createdField = 'created_at';

    protected $updatedField = 'updated_at';

    public function activeForClinic(int $clinicId): array
    {
        $rows = $this->where('status', 1)
            ->groupStart()
            ->where('clinic_id', $clinicId)
            ->orWhere('clinic_id', null)
            ->groupEnd()
            ->orderBy('clinic_id', 'DESC')
            ->orderBy('name', 'ASC')
            ->findAll();
        $options = [];

        foreach ($rows as $row) {
            $key = (string) ($row['normalized_name'] ?? '');

            if ($key !== '' && ! isset($options[$key])) {
                $options[$key] = $row;
            }
        }

        return array_values($options);
    }

    public function activeForClinicById(int $id, int $clinicId): ?array
    {
        return $this->where('id', $id)
            ->where('status', 1)
            ->groupStart()
            ->where('clinic_id', $clinicId)
            ->orWhere('clinic_id', null)
            ->groupEnd()
            ->first();
    }
}
