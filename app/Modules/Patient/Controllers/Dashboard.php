<?php

namespace Patient\Controllers;

use Admin\Models\PatientModel;
use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;
class Dashboard extends BaseController
{
    public function index(): string|RedirectResponse
    {
        if (!in_array('Patient', session('roles') ?? [], true)) {
            return redirect()->to(base_url('permission-denied'));
        }
        $userId = (int) ((session('user') ?? [])['id'] ?? 0);
        if ($userId <= 0) {
            return redirect()->to(base_url('system/login'));
        }
        $patient = (new PatientModel())->select('patients.id, patients.clinic_id, patients.registration_number, patients.first_name, patients.middle_name, patients.last_name, patients.status, clinics.clinic_name')->join('clinics', 'clinics.id = patients.clinic_id')->where('patients.user_id', $userId)->where('patients.status', 1)->where('clinics.status', 1)->first();
        if ($patient === null || (int) $patient['clinic_id'] <= 0) {
            return redirect()->to(base_url('permission-denied'));
        }
        $patientId = (int) $patient['id'];
        $clinicId = (int) $patient['clinic_id'];
        $today = date('Y-m-d');
        $db = db_connect();
        $appointments = $db->table('appointments')->select('appointments.appointment_number, appointments.appointment_date, appointments.appointment_time, appointments.appointment_type, appointments.status, doctors.first_name AS doctor_first_name, doctors.last_name AS doctor_last_name')->join('doctors', 'doctors.id = appointments.doctor_id')->where('appointments.patient_id', $patientId)->where('appointments.clinic_id', $clinicId)->where('doctors.clinic_id', $clinicId)->where('appointments.appointment_date >=', $today)->orderBy('appointments.appointment_date', 'ASC')->orderBy('appointments.appointment_time', 'ASC')->limit(10)->get()->getResultArray();
        $visits = $db->table('consultations')->select('consultations.consultation_number, consultations.consultation_date, consultations.status, doctors.first_name AS doctor_first_name, doctors.last_name AS doctor_last_name')->join('doctors', 'doctors.id = consultations.doctor_id')->where('consultations.patient_id', $patientId)->where('consultations.clinic_id', $clinicId)->where('doctors.clinic_id', $clinicId)->orderBy('consultations.consultation_date', 'DESC')->orderBy('consultations.id', 'DESC')->limit(10)->get()->getResultArray();
        $prescriptions = $db->table('prescriptions')->select('prescriptions.prescription_number, prescriptions.prescription_date, prescriptions.status')->where('prescriptions.patient_id', $patientId)->where('prescriptions.clinic_id', $clinicId)->orderBy('prescriptions.prescription_date', 'DESC')->orderBy('prescriptions.id', 'DESC')->limit(10)->get()->getResultArray();
        return view(PATIENT_VIEW . 'pages/dashboard', ['patient' => $patient, 'appointments' => $appointments, 'visits' => $visits, 'prescriptions' => $prescriptions]);
    }
}
