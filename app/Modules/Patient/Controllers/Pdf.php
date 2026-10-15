<?php

namespace Patient\Controllers;

use App\Controllers\BaseController;
use App\Services\Common\PatientPdfService;
use App\Services\Common\PrescriptionPdfService;
use App\Services\PatientScope;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

final class Pdf extends BaseController
{
    public function invoice(string $reference): ResponseInterface
    {
        return $this->invoicePdf($reference, false);
    }

    public function receipt(string $reference): ResponseInterface
    {
        return $this->invoicePdf($reference, true);
    }

    public function prescription(string $reference): ResponseInterface
    {
        $context = $this->scope();
        if ($context === null) {
            return $this->error(403, 'Patient scope is unavailable.');
        }

        $db = db_connect();
        $record = $db->table('prescriptions')
            ->select('prescriptions.*, doctors.first_name AS doctor_first_name, doctors.last_name AS doctor_last_name, doctors.qualification, clinics.clinic_name, clinics.address AS clinic_address, clinics.mobile AS clinic_mobile, clinics.email AS clinic_email, patients.first_name AS patient_first_name, patients.last_name AS patient_last_name, patients.registration_number')
            ->join('doctors', 'doctors.id = prescriptions.doctor_id')
            ->join('clinics', 'clinics.id = prescriptions.clinic_id')
            ->join('patients', 'patients.id = prescriptions.patient_id')
            ->where('prescriptions.prescription_number', $reference)
            ->where('prescriptions.patient_id', $context['patient_id'])
            ->where('prescriptions.clinic_id', $context['clinic_id'])
            ->where('doctors.clinic_id', $context['clinic_id'])
            ->where('patients.clinic_id', $context['clinic_id'])
            ->where('clinics.status', 1)
            ->get(1)->getRowArray();

        if ($record === null) {
            return $this->error(404, 'Prescription not found.');
        }

        try {
            $items = $db->table('prescription_items')->where('prescription_id', (int) $record['id'])->orderBy('id')->get()->getResultArray();
            $record['prescription_date'] = $this->date($record['prescription_date'] ?? null);
            $record['clinic_name'] = $record['clinic_name'] ?: 'ROOTINS Clinic';
            $pdf = (new PrescriptionPdfService())->generate($record, $items);
            $this->audit('patient.prescription_pdf_downloaded', $context, ['prescription_id' => (int) $record['id']]);

            return $this->download($pdf, $this->filename('prescription', $record['prescription_number'] ?? 'prescription'));
        } catch (Throwable $e) {
            log_message('error', 'Patient prescription PDF failed: ' . $e->getMessage());

            return $this->error(500, 'Prescription PDF could not be generated.');
        }
    }

    public function order(string $reference): ResponseInterface
    {
        $context = $this->scope();
        if ($context === null) {
            return $this->error(403, 'Patient scope is unavailable.');
        }

        $db = db_connect();
        $order = $db->table('medicine_orders AS orders')
            ->select('orders.*, prescriptions.prescription_number, patients.first_name AS patient_first_name, patients.last_name AS patient_last_name, patients.registration_number, clinics.clinic_name, clinics.address AS clinic_address, clinics.mobile AS clinic_mobile, clinics.email AS clinic_email, providers.provider_name AS delivery_provider_name')
            ->join('prescriptions', 'prescriptions.id = orders.prescription_id')
            ->join('patients', 'patients.id = orders.patient_id')
            ->join('clinics', 'clinics.id = orders.clinic_id')
            ->join('delivery_providers providers', 'providers.id = orders.delivery_provider_id', 'left')
            ->where('orders.order_number', $reference)
            ->where('orders.patient_id', $context['patient_id'])
            ->where('orders.clinic_id', $context['clinic_id'])
            ->where('prescriptions.patient_id', $context['patient_id'])
            ->where('prescriptions.clinic_id', $context['clinic_id'])
            ->where('patients.clinic_id', $context['clinic_id'])
            ->where('clinics.status', 1)
            ->get(1)->getRowArray();

        if ($order === null) {
            return $this->error(404, 'Order not found.');
        }

        try {
            $items = $db->table('medicine_order_items')->select('medicine_name, quantity, unit_price, line_total')->where('order_id', (int) $order['id'])->orderBy('id')->get()->getResultArray();
            $itemRows = [];
            $orderTotal = 0.0;
            foreach ($items as $index => $item) {
                $lineTotal = (float) ($item['line_total'] ?? 0);
                $orderTotal += $lineTotal;
                $itemRows[] = ['Medicine ' . ($index + 1), trim((string) ($item['medicine_name'] ?? 'Medicine')) . ' | Quantity: ' . (int) ($item['quantity'] ?? 0) . ' | Total: ' . number_format($lineTotal, 2)];
            }
            $sections = [
                ['title' => 'ORDER SUMMARY', 'rows' => [['Order number', $order['order_number'] ?? ''], ['Order date', $this->dateTime($order['created_at'] ?? null)], ['Order status', $this->label($order['status'] ?? '')], ['Prescription', $order['prescription_number'] ?? '']]],
                ['title' => 'MEDICINES / ITEMS', 'rows' => $itemRows],
                ['title' => 'PAYMENT', 'rows' => [['Payment status', $this->label($order['payment_status'] ?? '')], ['Order total', number_format($orderTotal, 2)]]],
                ['title' => 'DELIVERY', 'rows' => [['Delivery status', $this->label($order['delivery_status'] ?? '')], ['Provider', $order['delivery_provider_name'] ?? $order['delivery_provider'] ?? ''], ['Tracking reference', $order['tracking_reference'] ?? ''], ['Delivery address', $order['delivery_address_snapshot'] ?? ''], ['Delivery contact', $order['delivery_contact_snapshot'] ?? '']]],
            ];
            $pdf = (new PatientPdfService())->generate('MEDICINE ORDER', (string) ($order['order_number'] ?? 'Order'), $this->clinic($order), $this->patient($order), $sections);
            $this->audit('patient.order_pdf_downloaded', $context, ['order_id' => (int) $order['id']]);

            return $this->download($pdf, $this->filename('order', $order['order_number'] ?? 'order'));
        } catch (Throwable $e) {
            log_message('error', 'Patient order PDF failed: ' . $e->getMessage());

            return $this->error(500, 'Order PDF could not be generated.');
        }
    }

    public function appointment(string $reference): ResponseInterface
    {
        $context = $this->scope();
        if ($context === null) {
            return $this->error(403, 'Patient scope is unavailable.');
        }

        $appointment = db_connect()->table('appointments')
            ->select('appointments.*, doctors.first_name AS doctor_first_name, doctors.last_name AS doctor_last_name, doctors.qualification, clinics.clinic_name, clinics.address AS clinic_address, clinics.mobile AS clinic_mobile, clinics.email AS clinic_email, patients.first_name AS patient_first_name, patients.last_name AS patient_last_name, patients.registration_number')
            ->join('doctors', 'doctors.id = appointments.doctor_id')
            ->join('clinics', 'clinics.id = appointments.clinic_id')
            ->join('patients', 'patients.id = appointments.patient_id')
            ->where('appointments.appointment_number', $reference)
            ->where('appointments.patient_id', $context['patient_id'])
            ->where('appointments.clinic_id', $context['clinic_id'])
            ->where('doctors.clinic_id', $context['clinic_id'])
            ->where('patients.clinic_id', $context['clinic_id'])
            ->where('clinics.status', 1)
            ->get(1)->getRowArray();

        if ($appointment === null) {
            return $this->error(404, 'Appointment not found.');
        }

        try {
            $sections = [
                ['title' => 'APPOINTMENT', 'rows' => [['Appointment number', $appointment['appointment_number'] ?? ''], ['Date', $this->date($appointment['appointment_date'] ?? null)], ['Time', $this->time($appointment['appointment_time'] ?? null)], ['Type', $this->label($appointment['appointment_type'] ?? '')], ['Status', $this->label($appointment['status'] ?? '')]]],
                ['title' => 'DOCTOR / SERVICE', 'rows' => [['Doctor', trim('Dr. ' . ($appointment['doctor_first_name'] ?? '') . ' ' . ($appointment['doctor_last_name'] ?? ''))], ['Qualification', $appointment['qualification'] ?? ''], ['Service', $appointment['service_name'] ?? '']]],
                ['title' => 'REMARKS', 'rows' => [['Reason for visit', $appointment['reason_for_visit'] ?? ''], ['Notes', $appointment['notes'] ?? '']]],
            ];
            $pdf = (new PatientPdfService())->generate('APPOINTMENT SUMMARY', (string) ($appointment['appointment_number'] ?? 'Appointment'), $this->clinic($appointment), $this->patient($appointment), $sections);
            $this->audit('patient.appointment_pdf_downloaded', $context, ['appointment_id' => (int) $appointment['id']]);

            return $this->download($pdf, $this->filename('appointment', $appointment['appointment_number'] ?? 'appointment'));
        } catch (Throwable $e) {
            log_message('error', 'Patient appointment PDF failed: ' . $e->getMessage());

            return $this->error(500, 'Appointment PDF could not be generated.');
        }
    }

    private function invoicePdf(string $reference, bool $receipt): ResponseInterface
    {
        $context = $this->scope();
        if ($context === null) {
            return $this->error(403, 'Patient scope is unavailable.');
        }
        $db = db_connect();
        $invoice = $db->table('invoices')
            ->select('invoices.*, patients.first_name AS patient_first_name, patients.last_name AS patient_last_name, patients.registration_number, clinics.clinic_name, clinics.address AS clinic_address, clinics.mobile AS clinic_mobile, clinics.email AS clinic_email, orders.order_number, prescriptions.prescription_number')
            ->join('patients', 'patients.id = invoices.patient_id')
            ->join('clinics', 'clinics.id = invoices.clinic_id')
            ->join('medicine_orders orders', 'orders.id = invoices.order_id AND orders.patient_id = invoices.patient_id AND orders.clinic_id = invoices.clinic_id', 'left')
            ->join('prescriptions', 'prescriptions.id = orders.prescription_id AND prescriptions.patient_id = invoices.patient_id AND prescriptions.clinic_id = invoices.clinic_id', 'left')
            ->where('invoices.invoice_number', $reference)
            ->where('invoices.patient_id', $context['patient_id'])
            ->where('invoices.clinic_id', $context['clinic_id'])
            ->where('patients.clinic_id', $context['clinic_id'])
            ->where('clinics.status', 1)
            ->get(1)->getRowArray();

        if ($invoice === null) {
            return $this->error(404, 'Invoice not found.');
        }

        try {
            $items = $db->table('invoice_items')->select('description, quantity, line_total')->where('invoice_id', (int) $invoice['id'])->orderBy('id')->get()->getResultArray();
            $itemRows = [];
            foreach ($items as $index => $item) {
                $itemRows[] = ['Item ' . ($index + 1), trim((string) ($item['description'] ?? 'Item')) . ' | Quantity: ' . (int) ($item['quantity'] ?? 0) . ' | Total: ' . number_format((float) ($item['line_total'] ?? 0), 2)];
            }
            $sections = [
                ['title' => $receipt ? 'PAYMENT RECEIPT' : 'INVOICE', 'rows' => [['Invoice number', $invoice['invoice_number'] ?? ''], ['Date', $this->dateTime($invoice['created_at'] ?? null)], ['Invoice status', $this->label($invoice['status'] ?? '')], ['Payment status', $this->label($invoice['payment_status'] ?? '')], ['Order', $invoice['order_number'] ?? ''], ['Prescription', $invoice['prescription_number'] ?? '']]],
                ['title' => 'ITEMS', 'rows' => $itemRows],
                ['title' => 'TOTALS', 'rows' => [['Subtotal', number_format((float) ($invoice['subtotal'] ?? 0), 2)], ['Tax', number_format((float) ($invoice['tax'] ?? 0), 2)], ['Discount', number_format((float) ($invoice['discount'] ?? 0), 2)], ['Total', trim(($invoice['currency'] ?? 'INR') . ' ' . number_format((float) ($invoice['total'] ?? 0), 2))]]],
            ];
            $pdf = (new PatientPdfService())->generate($receipt ? 'PAYMENT RECEIPT' : 'INVOICE', (string) ($invoice['invoice_number'] ?? 'Invoice'), $this->clinic($invoice), $this->patient($invoice), $sections);
            $this->audit($receipt ? 'patient.receipt_pdf_downloaded' : 'patient.invoice_pdf_downloaded', $context, ['invoice_id' => (int) $invoice['id']]);

            return $this->download($pdf, $this->filename($receipt ? 'receipt' : 'invoice', $invoice['invoice_number'] ?? 'invoice'));
        } catch (Throwable $e) {
            log_message('error', 'Patient invoice PDF failed: ' . $e->getMessage());

            return $this->error(500, 'Invoice PDF could not be generated.');
        }
    }

    private function scope(): ?array
    {
        return (new PatientScope())->current();
    }

    private function clinic(array $record): array
    {
        return ['clinic_name' => $record['clinic_name'] ?? 'ROOTINS Clinic', 'address' => $record['clinic_address'] ?? '', 'mobile' => $record['clinic_mobile'] ?? '', 'email' => $record['clinic_email'] ?? ''];
    }

    private function patient(array $record): array
    {
        return ['name' => trim(($record['patient_first_name'] ?? '') . ' ' . ($record['patient_last_name'] ?? '')), 'registration_number' => $record['registration_number'] ?? ''];
    }

    private function date(?string $value): string
    {
        $date = $value ? \DateTimeImmutable::createFromFormat('!Y-m-d', substr($value, 0, 10)) : false;

        return $date ? $date->format('d-m-Y') : '';
    }

    private function time(?string $value): string
    {
        $time = $value ? (\DateTimeImmutable::createFromFormat('!H:i:s', substr($value, 0, 8)) ?: \DateTimeImmutable::createFromFormat('!H:i', substr($value, 0, 5))) : false;

        return $time ? $time->format('h:i A') : '';
    }

    private function dateTime(?string $value): string
    {
        $date = $value ? \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', substr($value, 0, 19)) : false;

        return $date ? $date->format('d-m-Y h:i A') : $this->date($value);
    }

    private function label(?string $value): string
    {
        return ucwords(str_replace('_', ' ', trim((string) $value)));
    }

    private function filename(string $prefix, string $reference): string
    {
        $safe = preg_replace('/[^A-Za-z0-9_-]/', '-', $reference) ?: 'document';

        return $prefix . '-' . $safe . '.pdf';
    }

    private function download(string $pdf, string $filename): ResponseInterface
    {
        return $this->response->setStatusCode(200)->setHeader('Content-Type', 'application/pdf')->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')->setHeader('Content-Length', (string) strlen($pdf))->setBody($pdf);
    }

    private function error(int $status, string $message): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setHeader('Content-Type', 'text/plain; charset=UTF-8')->setBody($message);
    }

    private function audit(string $action, array $context, array $data): void
    {
        service('audit')->record($action, array_merge(['patient_id' => $context['patient_id'], 'clinic_id' => $context['clinic_id']], $data));
    }
}
