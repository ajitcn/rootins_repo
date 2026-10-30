<?php

namespace Patient\Controllers;

use App\Controllers\BaseController;
use App\Services\PatientScope;
use CodeIgniter\HTTP\RedirectResponse;

final class Dashboard extends BaseController
{
    public function index(): string|RedirectResponse
    {
        $context = (new PatientScope())->current();

        if ($context === null) {
            return redirect()->to(base_url('permission-denied'));
        }

        $patientId = (int) $context['patient_id'];
        $clinicId = (int) $context['clinic_id'];
        $isGlobalPatient = $clinicId <= 0;
        $today = (new \DateTimeImmutable('now', new \DateTimeZone((string) config('App')->appTimezone)))->format('Y-m-d');
        $db = db_connect();

        $patient = $db->table('patients')
            ->select(
                'patients.*, clinics.clinic_name, '
                . 'countries.name AS country_name, states.name AS state_name, cities.name AS city_name, '
                . 'telephone_country_codes.normalized_code AS telephone_code',
            )
            ->join('clinics', 'clinics.id = patients.clinic_id', 'left')
            ->join('countries', 'countries.id = patients.country_id', 'left')
            ->join('states', 'states.id = patients.state_id', 'left')
            ->join('cities', 'cities.id = patients.city_id', 'left')
            ->join('telephone_country_codes', 'telephone_country_codes.id = patients.telephone_country_code_id', 'left')
            ->where('patients.id', $patientId)
            ->where('patients.status', 1)
            ->get(1)
            ->getRowArray() ?? $context['patient'];

        $appointmentQuery = $db->table('appointments')
            ->select(
                'appointments.id, appointments.appointment_number, appointments.appointment_date, '
                . 'appointments.appointment_time, appointments.appointment_type, appointments.service_name, '
                . 'appointments.status, doctors.first_name AS doctor_first_name, doctors.last_name AS doctor_last_name',
            )
            ->join('doctors', 'doctors.id = appointments.doctor_id')
            ->where('appointments.patient_id', $patientId)
            ->where('doctors.status', 1)
            ->where('appointments.appointment_date >=', $today)
            ->whereNotIn('appointments.status', ['cancelled', 'no_show']);
        if (!$isGlobalPatient) {
            $appointmentQuery->where('appointments.clinic_id', $clinicId)->where('doctors.clinic_id', $clinicId);
        }

        $upcomingAppointmentsCount = (clone $appointmentQuery)->countAllResults();
        $appointments = $appointmentQuery
            ->orderBy('appointments.appointment_date', 'ASC')
            ->orderBy('appointments.appointment_time', 'ASC')
            ->limit(5)
            ->get()
            ->getResultArray();

        $prescriptionQuery = $db->table('prescriptions')
            ->select(
                'prescriptions.id, prescriptions.prescription_number, prescriptions.prescription_date, '
                . 'prescriptions.status, doctors.first_name AS doctor_first_name, doctors.last_name AS doctor_last_name, '
                . '(SELECT COUNT(*) FROM prescription_items WHERE prescription_items.prescription_id = prescriptions.id) AS medicine_count',
            )
            ->join('doctors', 'doctors.id = prescriptions.doctor_id')
            ->where('prescriptions.patient_id', $patientId)
            ->where('doctors.status', 1);
        if (!$isGlobalPatient) {
            $prescriptionQuery->where('prescriptions.clinic_id', $clinicId)->where('doctors.clinic_id', $clinicId);
        }

        $pendingPrescriptionsCount = (clone $prescriptionQuery)
            ->whereIn('prescriptions.status', ['draft', 'submitted', 'clarification_requested'])
            ->countAllResults();
        $prescriptions = $prescriptionQuery
            ->orderBy('prescriptions.prescription_date', 'DESC')
            ->orderBy('prescriptions.id', 'DESC')
            ->limit(5)
            ->get()
            ->getResultArray();

        $orderQuery = $db->table('medicine_orders AS orders')
            ->select(
                'orders.id, orders.order_number, orders.created_at, orders.updated_at, orders.status, '
                . 'orders.payment_status, orders.delivery_status, orders.payment_provider, orders.tracking_reference, '
                . 'prescriptions.prescription_number, prescriptions.prescription_date, '
                . '(SELECT COUNT(*) FROM medicine_order_items WHERE medicine_order_items.order_id = orders.id) AS medicine_count',
            )
            ->join('prescriptions', 'prescriptions.id = orders.prescription_id', 'left')
            ->where('orders.patient_id', $patientId)
            ->groupStart()
                ->where('prescriptions.patient_id', $patientId)
                ->orWhere('prescriptions.id', null)
            ->groupEnd();
        if (!$isGlobalPatient) {
            $orderQuery->where('orders.clinic_id', $clinicId)
                ->groupStart()->where('prescriptions.clinic_id', $clinicId)->orWhere('prescriptions.id', null)->groupEnd();
        }

        $activeOrdersCount = (clone $orderQuery)
            ->where('orders.status !=', 'cancelled')
            ->countAllResults();
        $orders = $orderQuery
            ->orderBy('orders.created_at', 'DESC')
            ->limit(5)
            ->get()
            ->getResultArray();

        $invoiceQuery = $db->table('invoices')
            ->select('invoices.id, invoices.invoice_number, invoices.currency, invoices.total, invoices.status, invoices.payment_status, invoices.created_at, invoices.updated_at')
            ->where('invoices.patient_id', $patientId);
        if (!$isGlobalPatient) {
            $invoiceQuery->where('invoices.clinic_id', $clinicId);
        }
        $pendingPaymentsCount = (clone $invoiceQuery)
            ->whereIn('payment_status', ['pending', 'authorized'])
            ->countAllResults();
        $invoices = $invoiceQuery
            ->orderBy('id', 'DESC')
            ->limit(5)
            ->get()
            ->getResultArray();

        $visitQuery = $db->table('consultations AS visits')
            ->select(
                'visits.id, visits.consultation_number, visits.consultation_date, visits.consultation_time, '
                . 'visits.status, visits.follow_up_date, doctors.first_name AS doctor_first_name, '
                . 'doctors.last_name AS doctor_last_name, appointments.appointment_number, appointments.service_name',
            )
            ->join('doctors', 'doctors.id = visits.doctor_id')
            ->join('appointments', 'appointments.id = visits.appointment_id', 'left')
            ->where('visits.patient_id', $patientId)
            ->where('doctors.status', 1)
            ->orderBy('visits.consultation_date', 'DESC')
            ->orderBy('visits.consultation_time', 'DESC');
        if (!$isGlobalPatient) {
            $visitQuery->where('visits.clinic_id', $clinicId)->where('doctors.clinic_id', $clinicId);
        }
        $visitCount = (clone $visitQuery)->countAllResults();
        $visits = $visitQuery->limit(5)->get()->getResultArray();

        $notificationQuery = $db->table('patient_notifications')
            ->select('id, title, message, status, read_at, created_at, related_entity_type, related_entity_id')
            ->where('patient_id', $patientId);
        if (!$isGlobalPatient) {
            $notificationQuery->where('clinic_id', $clinicId);
        }
        $unreadNotificationsCount = (clone $notificationQuery)
            ->where('status', 'unread')
            ->countAllResults();
        $notifications = $notificationQuery
            ->orderBy('created_at', 'DESC')
            ->limit(5)
            ->get()
            ->getResultArray();

        return view(
            PATIENT_VIEW . 'pages/dashboard',
            [
                'patient' => $patient,
                'appointments' => $appointments,
                'prescriptions' => $prescriptions,
                'orders' => $orders,
                'invoices' => $invoices,
                'visits' => $visits,
                'notifications' => $notifications,
                'kpis' => [
                    'appointments' => $upcomingAppointmentsCount,
                    'prescriptions' => $pendingPrescriptionsCount,
                    'orders' => $activeOrdersCount,
                    'payments' => $pendingPaymentsCount,
                    'notifications' => $unreadNotificationsCount,
                    'visits' => $visitCount,
                ],
            ],
        );
    }
}
