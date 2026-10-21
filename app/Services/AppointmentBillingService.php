<?php

namespace App\Services;

use RuntimeException;

final class AppointmentBillingService
{
    public function createOrUpdate(int $clinicId, int $patientId, int $appointmentId, float $amount, string $currency, string $paymentStatus, string $idempotencyKey): int
    {
        $db = db_connect();
        $existing = $db->table('invoices')->where('appointment_id', $appointmentId)->where('patient_id', $patientId)->where('clinic_id', $clinicId)->get(1)->getRowArray();
        if ($existing !== null) {
            if ($paymentStatus === 'paid' && (string) $existing['payment_status'] !== 'paid') {
                $db->table('invoices')->where('id', (int) $existing['id'])->update(['payment_status' => 'paid', 'updated_at' => date('Y-m-d H:i:s')]);
            }
            return (int) $existing['id'];
        }

        $now = date('Y-m-d H:i:s');
        $total = round(max(0, $amount), 2);
        if (! $db->table('invoices')->insert([
            'invoice_number' => 'TMP-INV-' . bin2hex(random_bytes(8)),
            'clinic_id' => $clinicId,
            'patient_id' => $patientId,
            'appointment_id' => $appointmentId,
            'currency' => strtoupper(substr(trim($currency) ?: 'INR', 0, 3)),
            'subtotal' => $total,
            'tax' => 0,
            'discount' => 0,
            'total' => $total,
            'status' => 'finalized',
            'payment_status' => $paymentStatus,
            'idempotency_key' => substr($idempotencyKey, 0, 100),
            'created_at' => $now,
            'updated_at' => $now,
        ])) throw new RuntimeException('Appointment invoice creation failed.');

        $invoiceId = (int) $db->insertID();
        if ($invoiceId < 1 || ! $db->table('invoices')->where('id', $invoiceId)->update(['invoice_number' => sprintf('INV-%06d', $invoiceId), 'updated_at' => $now])) throw new RuntimeException('Appointment invoice numbering failed.');
        if (! $db->table('invoice_items')->insert(['invoice_id' => $invoiceId, 'description' => 'Doctor appointment', 'quantity' => 1, 'unit_price' => $total, 'line_total' => $total, 'created_at' => $now, 'updated_at' => $now])) throw new RuntimeException('Appointment invoice item creation failed.');

        return $invoiceId;
    }
}
