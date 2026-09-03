<?php

namespace Doctor\Controllers;

use Admin\Models\Doctor\DoctorModel;
use Admin\Models\User;
use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;
class Dashboard extends BaseController
{
    public function index(): string|RedirectResponse
    {
        $sessionUser = session('user') ?? [];
        $userId = (int) ($sessionUser['id'] ?? 0);
        if ($userId <= 0) {
            return redirect()->to(base_url('system/login'));
        }
        if (!in_array('Doctor', session('roles') ?? [], true)) {
            return redirect()->to(base_url('permission-denied'));
        }
        $user = (new User())->select('id, doctor_id, status')->where('id', $userId)->where('status', 1)->first();
        $doctorId = (int) ($user['doctor_id'] ?? 0);
        if ($doctorId <= 0) {
            return redirect()->to(base_url('permission-denied'));
        }
        $doctor = (new DoctorModel())->select('id, clinic_id, first_name, last_name, doctor_code')->where('id', $doctorId)->where('status', 1)->first();
        if ($doctor === null || (int) $doctor['clinic_id'] <= 0) {
            return redirect()->to(base_url('permission-denied'));
        }
        $clinicId = (int) $doctor['clinic_id'];
        $today = date('Y-m-d');
        $db = db_connect();
        $appointments = $db->table('appointments')->select(['appointments.id', 'appointments.appointment_number', 'appointments.appointment_date', 'appointments.appointment_time', 'appointments.appointment_type', 'appointments.status', 'patients.registration_number', 'patients.first_name AS patient_first_name', 'patients.middle_name AS patient_middle_name', 'patients.last_name AS patient_last_name'])->join('patients', 'patients.id = appointments.patient_id')->where('appointments.doctor_id', $doctorId)->where('appointments.clinic_id', $clinicId)->where('appointments.appointment_date >=', $today)->orderBy('appointments.appointment_date', 'ASC')->orderBy('appointments.appointment_time', 'ASC')->limit(25)->get()->getResultArray();
        $appointmentCount = $db->table('appointments')->where('doctor_id', $doctorId)->where('clinic_id', $clinicId)->where('appointment_date', $today)->countAllResults();
        $consultationCount = $db->table('consultations')->where('doctor_id', $doctorId)->where('clinic_id', $clinicId)->where('consultation_date', $today)->countAllResults();
        $prescriptionCount = $db->table('prescriptions')->where('doctor_id', $doctorId)->where('clinic_id', $clinicId)->where('prescription_date', $today)->countAllResults();
        $patientCount = $db->table('appointments')->select('patient_id')->where('doctor_id', $doctorId)->where('clinic_id', $clinicId)->groupBy('patient_id')->countAllResults();
        $followUpCount = $db->table('consultations')->where('doctor_id', $doctorId)->where('clinic_id', $clinicId)->where('follow_up_date >=', $today)->countAllResults();
        $billingCount = $db->table('invoices')->join('appointments', 'appointments.id = invoices.appointment_id')->where('appointments.doctor_id', $doctorId)->where('invoices.clinic_id', $clinicId)->countAllResults();
        return view(DOCTOR_VIEW . 'pages/dashboard', ['doctor' => $doctor, 'appointments' => $appointments, 'appointmentCount' => $appointmentCount, 'consultationCount' => $consultationCount, 'prescriptionCount' => $prescriptionCount, 'patientCount' => $patientCount, 'followUpCount' => $followUpCount, 'billingCount' => $billingCount]);
    }
}
