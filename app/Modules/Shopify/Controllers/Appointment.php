<?php

namespace App\Modules\Shopify\Controllers;

use App\Controllers\BaseController;
use App\Services\Shopify\ShopifyAppointmentConflictException;
use App\Services\Shopify\ShopifyAppointmentNotFoundException;
use App\Services\Shopify\ShopifyAppointmentService;
use App\Services\Shopify\ShopifyAppointmentValidationException;
use App\Services\Shopify\ShopifyHandoffService;
use CodeIgniter\HTTP\ResponseInterface;
use InvalidArgumentException;

/**
 * Shopify appointment availability and booking boundary.
 *
 * The signed handoff is the patient identity. Client-supplied patient, clinic,
 * appointment status, and ownership values are deliberately ignored.
 */
final class Appointment extends BaseController
{
    public function availability(): ResponseInterface
    {
        try {
            $handoff = (new ShopifyHandoffService())->verify((string) $this->request->getGet('handoff_token'));
            $doctorId = $this->positiveInt($this->request->getGet('doctor_id'));
            $serviceId = $this->positiveInt($this->request->getGet('service_id'));
            $date = trim((string) $this->request->getGet('date'));
            $data = (new ShopifyAppointmentService())->availability(
                $handoff,
                $doctorId > 0 ? $doctorId : null,
                $serviceId > 0 ? $serviceId : null,
                $date !== '' ? $date : null,
            );

            return $this->json(200, true, 'Appointment availability loaded.', $data);
        } catch (InvalidArgumentException $exception) {
            return $this->json(401, false, 'The patient handoff is invalid or expired.', [], ['handoff_token' => 'The patient handoff is invalid or expired.']);
        } catch (\Throwable $exception) {
            log_message('error', 'Shopify appointment availability failed safely.');

            return $this->json(500, false, 'Appointment availability could not be loaded safely.');
        }
    }

    public function book(): ResponseInterface
    {
        if (strtoupper($this->request->getMethod()) !== 'POST') {
            return $this->json(405, false, 'Invalid request method.');
        }

        $payload = $this->request->getPost();

        try {
            $token = trim((string) ($payload['handoff_token'] ?? ''));
            $handoff = (new ShopifyHandoffService())->verify($token);
            $payload['handoff_token'] = $token;
            $data = (new ShopifyAppointmentService())->book($handoff, $payload);

            return $this->json(200, true, 'Appointment booked.', $data);
        } catch (ShopifyAppointmentValidationException $exception) {
            return $this->json(422, false, 'Review the appointment details.', [], $exception->errors);
        } catch (ShopifyAppointmentConflictException $exception) {
            return $this->json(409, false, $exception->getMessage());
        } catch (ShopifyAppointmentNotFoundException $exception) {
            return $this->json(404, false, $exception->getMessage());
        } catch (InvalidArgumentException $exception) {
            return $this->json(401, false, 'The patient handoff is invalid or expired.', [], ['handoff_token' => 'The patient handoff is invalid or expired.']);
        } catch (\Throwable $exception) {
            log_message('error', 'Shopify appointment booking failed safely.');

            return $this->json(500, false, 'Appointment could not be booked safely.');
        }
    }

    public function cancel(int $appointmentId): ResponseInterface
    {
        if (strtoupper($this->request->getMethod()) !== 'POST') {
            return $this->json(405, false, 'Invalid request method.');
        }

        try {
            $payload = $this->request->getPost();
            $token = trim((string) ($payload['handoff_token'] ?? ''));
            $handoff = (new ShopifyHandoffService())->verify($token);
            $data = (new ShopifyAppointmentService())->cancel(
                $handoff,
                $appointmentId,
                trim((string) ($payload['reason'] ?? '')),
                $this->booleanInput($payload['confirmation'] ?? false),
                $token,
            );

            return $this->json(200, true, 'Appointment cancelled.', $data);
        } catch (ShopifyAppointmentValidationException $exception) {
            return $this->json(422, false, 'Review the cancellation details.', [], $exception->errors);
        } catch (ShopifyAppointmentConflictException $exception) {
            return $this->json(409, false, $exception->getMessage());
        } catch (ShopifyAppointmentNotFoundException $exception) {
            return $this->json(404, false, $exception->getMessage());
        } catch (InvalidArgumentException $exception) {
            return $this->json(401, false, 'The patient handoff is invalid or expired.', [], ['handoff_token' => 'The patient handoff is invalid or expired.']);
        } catch (\Throwable $exception) {
            log_message('error', 'Shopify appointment cancellation failed safely.');

            return $this->json(500, false, 'Appointment could not be cancelled safely.');
        }
    }

    public function reschedule(int $appointmentId): ResponseInterface
    {
        if (strtoupper($this->request->getMethod()) !== 'POST') {
            return $this->json(405, false, 'Invalid request method.');
        }

        try {
            $payload = $this->request->getPost();
            $token = trim((string) ($payload['handoff_token'] ?? ''));
            $handoff = (new ShopifyHandoffService())->verify($token);
            $data = (new ShopifyAppointmentService())->reschedule(
                $handoff,
                $appointmentId,
                trim((string) ($payload['appointment_date'] ?? '')),
                trim((string) ($payload['appointment_time'] ?? '')),
                $this->booleanInput($payload['confirmation'] ?? false),
                $token,
            );

            return $this->json(200, true, 'Appointment rescheduled.', $data);
        } catch (ShopifyAppointmentValidationException $exception) {
            return $this->json(422, false, 'Review the rescheduling details.', [], $exception->errors);
        } catch (ShopifyAppointmentConflictException $exception) {
            return $this->json(409, false, $exception->getMessage());
        } catch (ShopifyAppointmentNotFoundException $exception) {
            return $this->json(404, false, $exception->getMessage());
        } catch (InvalidArgumentException $exception) {
            return $this->json(401, false, 'The patient handoff is invalid or expired.');
        } catch (\Throwable $exception) {
            log_message('error', 'Shopify appointment rescheduling failed safely.');

            return $this->json(500, false, 'Appointment could not be rescheduled safely.');
        }
    }

    private function positiveInt(mixed $value): int
    {
        $value = trim((string) $value);

        return ctype_digit($value) ? max(0, (int) $value) : 0;
    }

    private function booleanInput(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /** @param array<string, mixed> $data @param array<string, string> $errors */
    private function json(int $status, bool $success, string $message, array $data = [], array $errors = []): ResponseInterface
    {
        $body = [
            'status' => $success,
            'msg' => $message,
        ];

        if ($data !== []) {
            $body['data'] = $data;
        }

        if ($errors !== []) {
            $body['errors'] = $errors;
        }

        return $this->response->setStatusCode($status)->setJSON($body);
    }
}
