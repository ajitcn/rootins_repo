<?php

namespace Patient\Services;

use Patient\Models\PatientNotificationModel;

final class PatientNotificationService
{
    public function __construct(private readonly PatientNotificationModel $model = new PatientNotificationModel())
    {
    }

    public function forOrder(int $orderId, string $type, string $title, string $message, string $eventKey): ?int
    {
        $order = db_connect()->table('medicine_orders')->select('id, patient_id, clinic_id')->where('id', $orderId)->get()->getRowArray();

        if (!$order || (int) $order['patient_id'] <= 0 || (int) $order['clinic_id'] <= 0) {
            return null;
        }

        return $this->create((int) $order['patient_id'], (int) $order['clinic_id'], $title, $message, $eventKey, 'order', $orderId);
    }

    public function create(int $patientId, int $clinicId, string $title, string $message, string $eventKey, ?string $relatedType = null, ?int $relatedId = null): ?int
    {
        if ($patientId <= 0 || $clinicId <= 0 || trim($eventKey) === '') {
            return null;
        }
        $existing = $this->model->where('patient_id', $patientId)->where('clinic_id', $clinicId)->where('event_key', substr($eventKey, 0, 190))->first();

        if ($existing) {
            return (int) $existing['id'];
        }

        try {
            $id = (int) ($this->model->insert(['patient_id' => $patientId, 'clinic_id' => $clinicId, 'title' => substr(trim($title), 0, 160), 'message' => trim($message), 'channel' => 'in_app', 'status' => 'unread', 'event_key' => substr($eventKey, 0, 190), 'related_entity_type' => $relatedType, 'related_entity_id' => $relatedId, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')], true) ?: 0);

            if ($id > 0) {
                service('audit')->record('patient.notification_created', ['patient_id' => $patientId, 'clinic_id' => $clinicId, 'notification_id' => $id, 'event_key' => substr($eventKey, 0, 190)]);
            }

            return $id > 0 ? $id : null;
        } catch (\Throwable $e) {
            log_message('error', 'Patient notification create failed: ' . $e->getMessage());

            return null;
        }
    }
}
