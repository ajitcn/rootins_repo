<?php

namespace Admin\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;

class Reports extends BaseController
{
    public function overview(): string|RedirectResponse
    {
        $roles = session('roles') ?? [];

        if (!in_array('Admin', $roles, true) && !in_array('Super Admin', $roles, true)) {
            return redirect()->to(base_url('permission-denied'));
        }
        $db = db_connect();
        $today = date('Y-m-d');
        $clinicId = (int) ((session('user') ?? [])['clinic_id'] ?? session('clinic_id') ?? 0);
        $patientsQuery = $db->table('patients')->where('status', 1);
        $appointmentsQuery = $db->table('appointments');
        $consultationsQuery = $db->table('consultations');
        $prescriptionsQuery = $db->table('prescriptions');

        if ($clinicId > 0) {
            $patientsQuery->where('clinic_id', $clinicId);
            $appointmentsQuery->where('clinic_id', $clinicId);
            $consultationsQuery->where('clinic_id', $clinicId);
            $prescriptionsQuery->where('clinic_id', $clinicId);
        }
        $appointments = $db->table('appointments')->select('appointments.appointment_number, appointments.appointment_date, appointments.appointment_time, appointments.status, patients.first_name AS patient_first_name, patients.middle_name AS patient_middle_name, patients.last_name AS patient_last_name, doctors.first_name AS doctor_first_name, doctors.last_name AS doctor_last_name')->join('patients', 'patients.id = appointments.patient_id')->join('doctors', 'doctors.id = appointments.doctor_id')->where('appointments.appointment_date >=', $today)->where('patients.clinic_id = appointments.clinic_id', null, false)->where('doctors.clinic_id = appointments.clinic_id', null, false)->orderBy('appointments.appointment_date', 'ASC')->orderBy('appointments.appointment_time', 'ASC')->limit(50);

        if ($clinicId > 0) {
            $appointments->where('appointments.clinic_id', $clinicId);
        }

        return view(ADMIN_VIEW . 'pages/reports/overview', ['clinicId' => $clinicId, 'activePatients' => $patientsQuery->countAllResults(), 'appointmentsToday' => (clone $appointmentsQuery)->where('appointment_date', $today)->countAllResults(), 'completedToday' => (clone $appointmentsQuery)->where('appointment_date', $today)->where('status', 'completed')->countAllResults(), 'cancelledToday' => (clone $appointmentsQuery)->where('appointment_date', $today)->where('status', 'cancelled')->countAllResults(), 'consultationsToday' => (clone $consultationsQuery)->where('consultation_date', $today)->countAllResults(), 'pendingReviews' => (clone $prescriptionsQuery)->whereIn('status', ['submitted', 'clarification_requested'])->countAllResults(), 'appointments' => $appointments->get()->getResultArray()]);
    }
}
