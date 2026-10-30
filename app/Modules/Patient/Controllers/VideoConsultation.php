<?php

namespace Patient\Controllers;

use App\Controllers\BaseController;
use App\Services\AppointmentConsultationEligibility;
use App\Services\PatientScope;
use App\Services\Video\VideoProviderBoundary;
use CodeIgniter\HTTP\ResponseInterface;
use Doctor\Models\VideoConsultationSessionModel;
use Throwable;

final class VideoConsultation extends BaseController
{
    private const ACTIVE_STATUSES = ['waiting', 'active'];

    public function view(int $appointmentId): string|\CodeIgniter\HTTP\RedirectResponse
    {
        $context = (new PatientScope())->current();
        $appointment = $context === null ? null : $this->appointment($appointmentId, $context);

        if ($appointment === null) {
            return redirect()->to(base_url('permission-denied'));
        }

        $sessions = new VideoConsultationSessionModel();
        $sessionQuery = $sessions->where('appointment_id', $appointmentId)->where('patient_id', $context['patient_id']);
        if ((int) ($context['clinic_id'] ?? 0) > 0) $sessionQuery->where('clinic_id', $context['clinic_id']);
        $session = $sessionQuery->first();

        return view(PATIENT_VIEW . 'pages/video', [
            'appointment' => $appointment,
            'session' => $session,
            'configured' => (new VideoProviderBoundary())->isConfigured(),
        ]);
    }

    public function token(int $appointmentId): ResponseInterface
    {
        $context = (new PatientScope())->current();
        $appointment = $context === null ? null : $this->appointment($appointmentId, $context);

        if ($appointment === null) {
            return $this->json(403, false, 'This appointment is not available.');
        }

        $sessions = new VideoConsultationSessionModel();
        $sessionQuery = $sessions->where('appointment_id', $appointmentId)->where('patient_id', $context['patient_id']);
        if ((int) ($context['clinic_id'] ?? 0) > 0) $sessionQuery->where('clinic_id', $context['clinic_id']);
        $session = $sessionQuery->first();

        if ($session === null || ! in_array((string) $session['status'], self::ACTIVE_STATUSES, true)) {
            return $this->json(409, false, 'The doctor has not started the video consultation yet.');
        }

        try {
            $token = (new VideoProviderBoundary())->issueShortLivedToken($session, 'patient:' . $context['patient_id']);
            $sessions->update((int) $session['id'], ['status' => 'active', 'started_at' => $session['started_at'] ?: date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);

            return $this->json(200, true, 'Video access granted.', ['token' => $token, 'provider' => 'twilio']);
        } catch (Throwable $exception) {
            log_message('error', 'Patient video token issuance failed: ' . $exception->getMessage());

            return $this->json(503, false, 'Video service is not available yet.');
        }
    }

    public function status(int $appointmentId): ResponseInterface
    {
        $context = (new PatientScope())->current();
        $appointment = $context === null ? null : $this->appointment($appointmentId, $context);
        if ($appointment === null) return $this->json(403, false, 'This appointment is not available.');

        $sessions = new VideoConsultationSessionModel();
        $query = $sessions->where('appointment_id', $appointmentId)->where('patient_id', $context['patient_id']);
        if ((int) ($context['clinic_id'] ?? 0) > 0) $query->where('clinic_id', $context['clinic_id']);
        $session = $query->first();

        return $this->json(200, true, 'Video consultation status loaded.', ['session' => $session ? ['status' => (string) $session['status'], 'room_ready' => in_array((string) $session['status'], self::ACTIVE_STATUSES, true)] : null]);
    }

    private function appointment(int $appointmentId, array $context): ?array
    {
        $query = db_connect()->table('appointments')->select('appointments.*, doctors.first_name AS doctor_first_name, doctors.last_name AS doctor_last_name, doctors.qualification')->join('doctors', 'doctors.id = appointments.doctor_id')->where('appointments.id', $appointmentId)->where('appointments.patient_id', $context['patient_id'])->where('appointments.appointment_type', 'video');
        if ((int) ($context['clinic_id'] ?? 0) > 0) $query->where('appointments.clinic_id', $context['clinic_id']);
        return $query->get()->getRowArray() ?: null;
    }

    private function json(int $status, bool $ok, string $message, array $data = []): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON(['status' => $ok, 'msg' => $message] + ($data !== [] ? ['data' => $data] : []));
    }
}
