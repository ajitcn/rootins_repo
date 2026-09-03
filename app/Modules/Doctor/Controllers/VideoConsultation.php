<?php

namespace Doctor\Controllers;

use Admin\Models\Doctor\DoctorModel;
use Admin\Models\User;
use App\Controllers\BaseController;
final class VideoConsultation extends BaseController
{
    public function view(int $appointmentId): string|\CodeIgniter\HTTP\RedirectResponse
    {
        if (!in_array('Doctor', session('roles') ?? [], true)) {
            return redirect()->to(base_url('permission-denied'));
        }
        $user = (new User())->select('doctor_id')->where('id', (int) (session('user')['id'] ?? 0))->where('status', 1)->first();
        $doctor = (new DoctorModel())->select('id, clinic_id, first_name, last_name')->where('id', (int) ($user['doctor_id'] ?? 0))->where('status', 1)->first();
        if ($doctor === null || (int) $doctor['clinic_id'] <= 0) {
            return redirect()->to(base_url('permission-denied'));
        }
        $appointment = db_connect()->table('appointments')->select('appointments.*, patients.first_name AS patient_first_name, patients.last_name AS patient_last_name')->join('patients', 'patients.id = appointments.patient_id')->where('appointments.id', $appointmentId)->where('appointments.doctor_id', $doctor['id'])->where('appointments.clinic_id', $doctor['clinic_id'])->where('appointments.appointment_type', 'video')->where('patients.clinic_id', $doctor['clinic_id'])->get()->getRowArray();
        if ($appointment === null) {
            return redirect()->to(base_url('permission-denied'));
        }
        service('audit')->record('doctor.video_consultation_view', ['appointment_id' => $appointmentId, 'doctor_id' => $doctor['id'], 'clinic_id' => $doctor['clinic_id']]);
        return view(DOCTOR_VIEW . 'pages/video', ['doctor' => $doctor, 'appointment' => $appointment, 'provider' => 'Twilio Video', 'configured' => false]);
    }
}
