<?php

namespace Doctor\Controllers;

use Admin\Models\Doctor\DoctorModel;
use Admin\Models\User;
use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;
class Appointment extends BaseController
{
    public function view(int $appointmentId): string|RedirectResponse
    {
        if (!in_array('Doctor', session('roles') ?? [], true)) {
            return redirect()->to(base_url('permission-denied'));
        }
        $sessionUser = session('user') ?? [];
        $userId = (int) ($sessionUser['id'] ?? 0);
        if ($userId <= 0 || $appointmentId <= 0) {
            return redirect()->to(base_url('permission-denied'));
        }
        $user = (new User())->select('id, doctor_id')->where('id', $userId)->where('status', 1)->first();
        $doctorId = (int) ($user['doctor_id'] ?? 0);
        if ($doctorId <= 0) {
            return redirect()->to(base_url('permission-denied'));
        }
        $doctor = (new DoctorModel())->select('id, clinic_id, first_name, last_name, doctor_code')->where('id', $doctorId)->where('status', 1)->first();
        if ($doctor === null || (int) $doctor['clinic_id'] <= 0) {
            return redirect()->to(base_url('permission-denied'));
        }
        $clinicId = (int) $doctor['clinic_id'];
        $db = db_connect();
        $appointment = $db->table('appointments')->select('appointments.id, appointments.appointment_number, appointments.appointment_date, appointments.appointment_time, appointments.duration_minutes, appointments.appointment_type, appointments.status, appointments.reason_for_visit, patients.registration_number, patients.first_name AS patient_first_name, patients.middle_name AS patient_middle_name, patients.last_name AS patient_last_name')->join('patients', 'patients.id = appointments.patient_id')->where('appointments.id', $appointmentId)->where('appointments.doctor_id', $doctorId)->where('appointments.clinic_id', $clinicId)->where('patients.clinic_id', $clinicId)->get()->getRowArray();
        if ($appointment === null) {
            return redirect()->to(base_url('permission-denied'));
        }
        $consultation = $db->table('consultations')->select('id, consultation_number, consultation_date, status')->where('appointment_id', $appointmentId)->where('doctor_id', $doctorId)->where('clinic_id', $clinicId)->get()->getRowArray();
        $prescription = $db->table('prescriptions')->select('prescription_number, prescription_date, status')->where('consultation_id', (int) ($consultation['id'] ?? 0))->where('doctor_id', $doctorId)->where('clinic_id', $clinicId)->get()->getRowArray();
        service('audit')->record('doctor.appointment_view', ['appointment_id' => $appointmentId, 'doctor_id' => $doctorId, 'clinic_id' => $clinicId]);
        return view(DOCTOR_VIEW . 'pages/appointment', ['doctor' => $doctor, 'appointment' => $appointment, 'consultation' => $consultation, 'prescription' => $prescription]);
    }
}
