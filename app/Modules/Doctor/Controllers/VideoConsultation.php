<?php

namespace Doctor\Controllers;

use Admin\Models\Doctor\DoctorModel;
use Admin\Models\User;
use App\Controllers\BaseController;
use App\Services\AppointmentConsultationEligibility;
use App\Services\Video\VideoProviderBoundary;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Doctor\Models\VideoConsultationSessionModel;
use Throwable;

final class VideoConsultation extends BaseController
{
    private const ACTIVE_STATUSES = ['waiting', 'active'];

    private VideoConsultationSessionModel $sessions;

    private AppointmentConsultationEligibility $eligibility;

    private VideoProviderBoundary $provider;

    public function __construct()
    {
        $this->sessions = new VideoConsultationSessionModel();
        $this->eligibility = new AppointmentConsultationEligibility();
        $this->provider = new VideoProviderBoundary();
    }

    public function view(int $appointmentId): string|RedirectResponse
    {
        $context = $this->context();
        $appointment = $context === null ? null : $this->appointment($appointmentId, $context);

        if ($appointment === null || ! $this->canUseAppointment($appointment, $context)) {
            return redirect()->to(base_url('permission-denied'));
        }
        $payload = $this->payload($appointment, $context);
        service('audit')->record('doctor.video_consultation_view', ['appointment_id' => $appointmentId, 'patient_id' => (int) $appointment['patient_id'], 'doctor_id' => $context['doctor_id'], 'clinic_id' => $context['clinic_id']]);

        if ($this->request->getGet('fragment') === '1' || $this->request->isAJAX()) {
            return view(DOCTOR_VIEW . 'pages/video_panel', $payload);
        }

        return view(DOCTOR_VIEW . 'pages/video', $payload);
    }

    public function start(int $appointmentId): ResponseInterface
    {
        $context = $this->context();
        $appointment = $context === null ? null : $this->appointment($appointmentId, $context);

        if ($appointment === null || ! $this->canUseAppointment($appointment, $context)) {
            return $this->json(403, false, 'This appointment is not eligible for video consultation.');
        }

        if (! $this->allowedRequest($context, $appointmentId)) {
            return $this->json(429, false, 'Too many video consultation requests. Please try again shortly.');
        }

        try {
            $existing = $this->sessions->where('appointment_id', $appointmentId)->first();

            if ($existing !== null && in_array($existing['status'], self::ACTIVE_STATUSES, true)) {
                return $this->json(200, true, 'Video consultation is ready.', ['session' => $this->publicSession($existing)]);
            }

            if ($existing !== null && $existing['status'] === 'ended') {
                return $this->json(409, false, 'This video consultation has ended and cannot be restarted.');
            }
            $now = date('Y-m-d H:i:s');
            $data = ['appointment_id' => $appointmentId, 'patient_id' => (int) $appointment['patient_id'], 'doctor_id' => $context['doctor_id'], 'clinic_id' => $context['clinic_id'], 'provider' => $this->provider->providerName(), 'room_reference' => 'rootins-' . bin2hex(random_bytes(24)), 'status' => 'waiting', 'recording_enabled' => 0, 'recording_consent_state' => 'not_requested', 'created_at' => $now, 'updated_at' => $now];

            if ($existing !== null) {
                $this->sessions->update((int) $existing['id'], $data);
                $session = $this->sessions->find((int) $existing['id']);
            } else {
                $session = $this->sessions->find((int) $this->sessions->insert($data, true));
            }

            if ($session === null) {
                throw new \RuntimeException('Video session could not be created.');
            }
            service('audit')->record('doctor.video_consultation_started', ['appointment_id' => $appointmentId, 'patient_id' => (int) $appointment['patient_id'], 'doctor_id' => $context['doctor_id'], 'clinic_id' => $context['clinic_id'], 'session_id' => (int) $session['id']]);

            return $this->json(200, true, 'Video consultation started.', ['session' => $this->publicSession($session)]);
        } catch (Throwable $exception) {
            log_message('error', 'Doctor video session start failed: ' . $exception->getMessage());

            return $this->json(500, false, 'Video consultation could not be started safely.');
        }
    }

    public function status(int $appointmentId): ResponseInterface
    {
        $context = $this->context();
        $appointment = $context === null ? null : $this->appointment($appointmentId, $context);

        if ($appointment === null || ! $this->canUseAppointment($appointment, $context)) {
            return $this->json(403, false, 'This appointment is not available.');
        }
        $session = $this->sessions->where('appointment_id', $appointmentId)->where('doctor_id', $context['doctor_id'])->where('clinic_id', $context['clinic_id'])->first();

        return $this->json(200, true, 'Video consultation status loaded.', ['session' => $session ? $this->publicSession($session) : null]);
    }

    public function token(int $appointmentId): ResponseInterface
    {
        $context = $this->context();
        $appointment = $context === null ? null : $this->appointment($appointmentId, $context);

        if ($appointment === null || ! $this->canUseAppointment($appointment, $context)) {
            return $this->json(403, false, 'This appointment is not available.');
        }

        if (! $this->allowedRequest($context, $appointmentId)) {
            return $this->json(429, false, 'Too many video consultation requests. Please try again shortly.');
        }
        $session = $this->sessions->where('appointment_id', $appointmentId)->where('doctor_id', $context['doctor_id'])->where('clinic_id', $context['clinic_id'])->first();

        if ($session === null || ! in_array($session['status'], self::ACTIVE_STATUSES, true)) {
            return $this->json(409, false, 'Start the consultation before joining.');
        }

        try {
            $token = $this->provider->issueShortLivedToken($session, 'doctor:' . $context['doctor_id']);
            $this->sessions->update((int) $session['id'], ['status' => 'active', 'started_at' => $session['started_at'] ?: date('Y-m-d H:i:s')]);

            return $this->json(200, true, 'Video access granted.', ['token' => $token, 'provider' => $this->provider->providerName()]);
        } catch (Throwable $exception) {
            log_message('error', 'Doctor video token issuance failed: ' . $exception->getMessage());
            $this->sessions->update((int) $session['id'], ['status' => 'failed', 'failure_reason' => 'Provider token unavailable.']);

            return $this->json(503, false, 'Video service is not available yet.');
        }
    }

    public function end(int $appointmentId): ResponseInterface
    {
        $context = $this->context();
        $appointment = $context === null ? null : $this->appointment($appointmentId, $context);

        if ($appointment === null || ! $this->canUseAppointment($appointment, $context)) {
            return $this->json(403, false, 'This appointment is not available.');
        }
        $session = $this->sessions->where('appointment_id', $appointmentId)->where('doctor_id', $context['doctor_id'])->where('clinic_id', $context['clinic_id'])->first();

        if ($session === null) {
            return $this->json(404, false, 'No video consultation has been started.');
        }
        $this->sessions->update((int) $session['id'], ['status' => 'ended', 'ended_at' => date('Y-m-d H:i:s')]);
        service('audit')->record('doctor.video_consultation_ended', ['appointment_id' => $appointmentId, 'patient_id' => (int) $appointment['patient_id'], 'doctor_id' => $context['doctor_id'], 'clinic_id' => $context['clinic_id'], 'session_id' => (int) $session['id']]);

        return $this->json(200, true, 'Video consultation ended.', ['session' => $this->publicSession($this->sessions->find((int) $session['id']) ?: $session)]);
    }

    public function startRecording(int $appointmentId): ResponseInterface
    {
        $context = $this->context();
        $appointment = $context === null ? null : $this->appointment($appointmentId, $context);

        if ($appointment === null || ! $this->canUseAppointment($appointment, $context)) {
            return $this->json(403, false, 'This appointment is not available.');
        }

        if ((string) env('VIDEO_RECORDING_ENABLED', 'false') !== 'true' || $this->request->getPost('recording_consent') !== 'accepted') {
            return $this->json(422, false, 'Recording requires enabled clinic configuration and explicit consent.');
        }
        $session = $this->sessions->where('appointment_id', $appointmentId)->where('doctor_id', $context['doctor_id'])->where('clinic_id', $context['clinic_id'])->first();

        if ($session === null || ! in_array($session['status'], self::ACTIVE_STATUSES, true)) {
            return $this->json(409, false, 'An active video consultation is required before recording.');
        }
        $this->sessions->update((int) $session['id'], ['recording_enabled' => 1, 'recording_consent_state' => 'accepted', 'recording_status' => 'requested']);
        service('audit')->record('doctor.video_recording_requested', ['appointment_id' => $appointmentId, 'patient_id' => (int) $appointment['patient_id'], 'doctor_id' => $context['doctor_id'], 'clinic_id' => $context['clinic_id'], 'session_id' => (int) $session['id']]);

        return $this->json(200, true, 'Recording request accepted.', ['recording_status' => 'requested']);
    }

    private function context(): ?array
    {
        if (! in_array('Doctor', session('roles') ?? [], true)) {
            return null;
        }
        $userId = (int) (session('user')['id'] ?? 0);
        $user = (new User())->select('doctor_id')->where('id', $userId)->where('status', 1)->first();
        $doctor = (new DoctorModel())->select('id, clinic_id')->where('id', (int) ($user['doctor_id'] ?? 0))->where('status', 1)->first();

        if ($doctor === null || (int) $doctor['clinic_id'] <= 0 || ! in_array('consultation.edit', session('permissions') ?? [], true)) {
            return null;
        }

        return ['user_id' => $userId, 'doctor_id' => (int) $doctor['id'], 'clinic_id' => (int) $doctor['clinic_id']];
    }

    private function appointment(int $appointmentId, array $context): ?array
    {
        if ($appointmentId <= 0) {
            return null;
        }

        return db_connect()->table('appointments')->select('appointments.*, patients.registration_number, patients.first_name AS patient_first_name, patients.last_name AS patient_last_name, patients.mobile_number AS patient_mobile_number')->join('patients', 'patients.id = appointments.patient_id')->where('appointments.id', $appointmentId)->where('appointments.doctor_id', $context['doctor_id'])->where('appointments.clinic_id', $context['clinic_id'])->where('appointments.appointment_type', 'video')->get()->getRowArray() ?: null;
    }

    private function canUseAppointment(array $appointment, ?array $context): bool
    {
        return $context !== null && (int) $appointment['doctor_id'] === $context['doctor_id'] && (int) $appointment['clinic_id'] === $context['clinic_id'] && (int) $appointment['patient_id'] > 0 && $this->eligibility->isConsultable($appointment) && in_array((string) $appointment['status'], ['scheduled', 'confirmed', 'checked_in', 'in_queue', 'in_consultation'], true);
    }

    private function payload(array $appointment, array $context): array
    {
        $session = $this->sessions->where('appointment_id', (int) $appointment['id'])->where('doctor_id', $context['doctor_id'])->where('clinic_id', $context['clinic_id'])->first();

        return ['doctor' => $context, 'appointment' => $appointment, 'session' => $session ? $this->publicSession($session) : null, 'provider' => $this->provider->providerName(), 'configured' => $this->provider->isConfigured()];
    }

    private function publicSession(array $session): array
    {
        return ['id' => (int) $session['id'], 'appointment_id' => (int) $session['appointment_id'], 'status' => (string) $session['status'], 'provider' => (string) $session['provider'], 'room_ready' => in_array($session['status'], self::ACTIVE_STATUSES, true), 'recording_enabled' => (bool) $session['recording_enabled'], 'recording_status' => $session['recording_status'] ?? null, 'started_at' => $session['started_at'] ?? null, 'ended_at' => $session['ended_at'] ?? null];
    }

    private function allowedRequest(array $context, int $appointmentId): bool
    {
        return service('throttler')->check('doctor-video-' . $context['user_id'] . '-' . $appointmentId, 30, MINUTE, 1);
    }

    private function json(int $status, bool $ok, string $message, array $data = []): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON(['status' => $ok, 'msg' => $message] + ($data !== [] ? ['data' => $data] : []));
    }
}
