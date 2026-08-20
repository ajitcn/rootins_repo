<?php

namespace Admin\Models\Doctor;

use CodeIgniter\Model;

class DoctorLeaveModel extends Model
{
    protected $table            = 'doctor_leaves';
    protected $primaryKey       = 'id';

    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $protectFields    = true;

    protected $allowedFields = [
        'doctor_id',
        'leave_type',
        'start_date',
        'end_date',
        'reason',
        'status',
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    // Timestamps
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

  

    protected $validationMessages = [];

    protected $skipValidation = false;
    protected $cleanValidationRules = true;


    public function getLeaves()
    {
        return $this->db->table('doctor_leaves dl')
                        ->select('dl.*, doctors.doctor_code, doctors.first_name, doctors.last_name')
                        ->join('doctors', 'doctors.id=dl.doctor_id')
                        ->orderBy('dl.id', 'DESC')
                        ->get()
                        ->getResultArray();
    }


    /**
     * Check if doctor is on approved leave on a given date.
     */
    public function isOnLeave(int $doctorId, string $date): bool
    {
        return $this->where('doctor_id', $doctorId)
            ->where('status', 1)
            ->where('start_date <=', $date)
            ->where('end_date >=', $date)
            ->countAllResults() > 0;
    }

    /**
     * Get all upcoming approved leaves.
     */
    public function getUpcomingLeaves(int $doctorId)
    {
        return $this->where('doctor_id', $doctorId)
            ->where('status', 1)
            ->where('end_date >=', date('Y-m-d'))
            ->orderBy('start_date', 'ASC')
            ->findAll();
    }

    /**
     * Get approved leaves between two dates.
     */
    public function getLeavesBetween(int $doctorId, string $from, string $to)
    {
        return $this->where('doctor_id', $doctorId)
            ->where('status', 1)
            ->groupStart()
                ->where('start_date <=', $to)
                ->where('end_date >=', $from)
            ->groupEnd()
            ->findAll();
    }



}