<?php

namespace Patient\Controllers;

use App\Controllers\BaseController;
use App\Services\PatientScope;
use CodeIgniter\HTTP\RedirectResponse;

final class Pages extends BaseController
{
    private PatientScope $scope;

    public function __construct()
    {
        $this->scope = new PatientScope();
    }

    public function profile(): string|RedirectResponse
    {
        $context = $this->scope->current();

        if ($context === null) {
            return redirect()->to(base_url('permission-denied'));
        }

        $patient = db_connect()->table('patients')
            ->select(
                'patients.*, clinics.clinic_name, countries.name AS country_name, '
                . 'states.name AS state_name, cities.name AS city_name, '
                . 'localities.name AS locality_name, telephone_country_codes.normalized_code AS telephone_code',
            )
            ->join('clinics', 'clinics.id = patients.clinic_id')
            ->join('countries', 'countries.id = patients.country_id', 'left')
            ->join('states', 'states.id = patients.state_id', 'left')
            ->join('cities', 'cities.id = patients.city_id', 'left')
            ->join('localities', 'localities.id = patients.locality_id', 'left')
            ->join('telephone_country_codes', 'telephone_country_codes.id = patients.telephone_country_code_id', 'left')
            ->where('patients.id', $context['patient_id'])
            ->where('patients.clinic_id', $context['clinic_id'])
            ->where('patients.status', 1)
            ->where('clinics.status', 1)
            ->get(1)
            ->getRowArray();

        if ($patient === null) {
            return redirect()->to(base_url('permission-denied'));
        }

        return view(PATIENT_VIEW . 'pages/profile', [
            'patient' => $patient,
            'countries' => (new \Admin\Models\Location\Country())->dropdown(),
            'telephoneCodes' => (new \Admin\Models\Location\TelephoneCountryCode())->dropdown(),
        ]);
    }

    public function appointments(): string|RedirectResponse
    {
        $context = $this->scope->current();

        if ($context === null) {
            return redirect()->to(base_url('permission-denied'));
        }

        $db = db_connect();
        $pageSize = $this->pageSize();
        $page = $this->page();
        $view = in_array($this->request->getGet('view'), ['list', 'grid'], true)
            ? (string) $this->request->getGet('view')
            : 'list';
        $search = trim((string) $this->request->getGet('search'));
        $status = trim((string) $this->request->getGet('status'));
        $doctorId = (int) $this->request->getGet('doctor_id');
        $service = trim((string) $this->request->getGet('service'));
        $dateFromInput = trim((string) $this->request->getGet('date_from'));
        $dateToInput = trim((string) $this->request->getGet('date_to'));
        $dateFrom = $this->parsePatientDate($dateFromInput);
        $dateTo = $this->parsePatientDate($dateToInput);
        $allowedStatuses = ['scheduled', 'confirmed', 'checked_in', 'in_queue', 'in_consultation', 'completed', 'cancelled', 'no_show'];
        $query = $db->table('appointments')
            ->select(
                'appointments.id, appointments.appointment_number, appointments.appointment_date, '
                . 'appointments.appointment_time, appointments.appointment_type, appointments.service_name, '
                . 'appointments.queue_position, appointments.status, appointments.reason_for_visit, appointments.created_at, '
                . 'appointments.updated_at, doctors.id AS doctor_id, doctors.first_name AS doctor_first_name, '
                . 'doctors.last_name AS doctor_last_name, doctors.qualification, patients.first_name AS patient_first_name, '
                . 'patients.last_name AS patient_last_name, patients.registration_number, patients.mobile_number',
            )
            ->join('doctors', 'doctors.id = appointments.doctor_id')
            ->join('patients', 'patients.id = appointments.patient_id')
            ->where('appointments.patient_id', $context['patient_id'])
            ->where('appointments.clinic_id', $context['clinic_id'])
            ->where('doctors.clinic_id', $context['clinic_id'])
            ->where('patients.clinic_id', $context['clinic_id']);

        if ($search !== '') {
            $query->groupStart()
                ->like('appointments.appointment_number', $search)
                ->orLike('appointments.service_name', $search)
                ->orLike('appointments.queue_position', $search)
                ->orLike('appointments.status', $search)
                ->orLike('doctors.first_name', $search)
                ->orLike('doctors.last_name', $search)
                ->groupEnd();
        }

        if (in_array($status, $allowedStatuses, true)) {
            $query->where('appointments.status', $status);
        } else {
            $status = '';
        }

        if ($doctorId > 0) {
            $query->where('appointments.doctor_id', $doctorId);
        }

        if ($service !== '') {
            $query->where('appointments.service_name', $service);
        }

        if ($dateFrom !== null) {
            $query->where('appointments.appointment_date >=', $dateFrom);
        } else {
            $dateFromInput = '';
        }

        if ($dateTo !== null) {
            $query->where('appointments.appointment_date <=', $dateTo);
        } else {
            $dateToInput = '';
        }

        $total = (clone $query)->countAllResults();
        $pages = max(1, (int) ceil($total / $pageSize));
        $page = min($page, $pages);
        $records = $query->orderBy('appointments.appointment_date', 'DESC')->orderBy('appointments.appointment_time', 'DESC')->limit($pageSize, ($page - 1) * $pageSize)->get()->getResultArray();

        $today = (new \DateTimeImmutable('now', new \DateTimeZone((string) config('App')->appTimezone)))->format('Y-m-d');

        foreach ($records as &$record) {
            $appointmentDate = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $record['appointment_date']);
            $record['appointment_date_display'] = $appointmentDate ? $appointmentDate->format('d-m-Y') : '—';
            $record['appointment_time_display'] = $this->formatPatientTime($record['appointment_time'] ?? null);
            $record['created_at_display'] = $this->formatPatientDateTime($record['created_at'] ?? null);
            $record['updated_at_display'] = $this->formatPatientDateTime($record['updated_at'] ?? null);
            $record['appointment_type_label'] = ucwords(str_replace('_', ' ', (string) ($record['appointment_type'] ?? '')));
            $record['status_label'] = ucwords(str_replace('_', ' ', (string) ($record['status'] ?? '')));
            $record['patient_can_edit'] = in_array($record['status'], ['scheduled', 'confirmed'], true)
                && (string) $record['appointment_date'] >= $today;
            $record['specialization'] = $db->table('doctor_specialization_map')
                ->select('doctor_specializations.specialization_name')
                ->join('doctor_specializations', 'doctor_specializations.id = doctor_specialization_map.specialization_id')
                ->where('doctor_specialization_map.doctor_id', (int) $record['doctor_id'])
                ->where('doctor_specializations.status', 1)
                ->orderBy('doctor_specializations.specialization_name')
                ->get()
                ->getResultArray();
            $record['specialization'] = implode(', ', array_column($record['specialization'], 'specialization_name'));
            $record['prescription_id'] = 0;
            $record['invoice_id'] = 0;
            $record['order_id'] = 0;
            $linkedPrescription = $db->table('prescriptions')
                ->select('prescriptions.id')
                ->join('consultations', 'consultations.id = prescriptions.consultation_id')
                ->where('consultations.appointment_id', (int) $record['id'])
                ->where('prescriptions.patient_id', $context['patient_id'])
                ->where('prescriptions.clinic_id', $context['clinic_id'])
                ->get(1)
                ->getRowArray();

            if ($linkedPrescription !== null) {
                $record['prescription_id'] = (int) $linkedPrescription['id'];
                $linkedOrder = $db->table('medicine_orders')
                    ->select('id')
                    ->where('prescription_id', $record['prescription_id'])
                    ->where('patient_id', $context['patient_id'])
                    ->where('clinic_id', $context['clinic_id'])
                    ->get(1)
                    ->getRowArray();
                $record['order_id'] = (int) ($linkedOrder['id'] ?? 0);
            }

            $linkedInvoice = $db->table('invoices')
                ->select('id')
                ->where('appointment_id', (int) $record['id'])
                ->where('patient_id', $context['patient_id'])
                ->where('clinic_id', $context['clinic_id'])
                ->get(1)
                ->getRowArray();
            $record['invoice_id'] = (int) ($linkedInvoice['id'] ?? 0);
        }
        unset($record);

        $doctors = $db->table('doctors')
            ->select('id, first_name, last_name')
            ->where('clinic_id', $context['clinic_id'])
            ->where('status', 1)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->getResultArray();
        $services = $db->table('appointments')
            ->distinct()
            ->select('service_name')
            ->where('patient_id', $context['patient_id'])
            ->where('clinic_id', $context['clinic_id'])
            ->where('service_name !=', '')
            ->orderBy('service_name')
            ->get()
            ->getResultArray();

        return view(PATIENT_VIEW . 'pages/appointments', [
            'patient' => $context['patient'],
            'appointments' => $records,
            'doctors' => $doctors,
            'services' => $services,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'doctor_id' => $doctorId > 0 ? $doctorId : '',
                'service' => $service,
                'date_from' => $dateFromInput,
                'date_to' => $dateToInput,
                'view' => $view,
                'page_size' => $pageSize,
            ],
            'pagination' => [
                'page' => $page,
                'pages' => $pages,
                'total' => $total,
                'page_size' => $pageSize,
            ],
        ]);
    }

    public function prescriptions(): string|RedirectResponse
    {
        $context = $this->scope->current();

        if ($context === null) {
            return redirect()->to(base_url('permission-denied'));
        }

        $db = db_connect();
        $pageSize = $this->pageSize();
        $page = $this->page();
        $view = in_array($this->request->getGet('view'), ['list', 'grid'], true)
            ? (string) $this->request->getGet('view')
            : 'list';
        $search = trim((string) $this->request->getGet('search'));
        $status = trim((string) $this->request->getGet('status'));
        $doctorId = (int) $this->request->getGet('doctor_id');
        $dateFromInput = trim((string) $this->request->getGet('date_from'));
        $dateToInput = trim((string) $this->request->getGet('date_to'));
        $dateFrom = $this->parsePatientDate($dateFromInput);
        $dateTo = $this->parsePatientDate($dateToInput);
        $allowedStatuses = ['draft', 'submitted', 'approved', 'rejected', 'clarification_requested', 'completed', 'cancelled'];
        $query = $db->table('prescriptions')
            ->select(
                'prescriptions.id, prescriptions.prescription_number, prescriptions.prescription_date, '
                . 'prescriptions.status, prescriptions.created_at, prescriptions.updated_at, '
                . 'doctors.id AS doctor_id, doctors.first_name AS doctor_first_name, doctors.last_name AS doctor_last_name, '
                . 'doctors.qualification, clinics.clinic_name, patients.first_name AS patient_first_name, '
                . 'patients.last_name AS patient_last_name, patients.registration_number, patients.mobile_number, '
                . '(SELECT COUNT(*) FROM prescription_items AS item_count WHERE item_count.prescription_id = prescriptions.id) AS medicine_count',
            )
            ->join('doctors', 'doctors.id = prescriptions.doctor_id')
            ->join('clinics', 'clinics.id = prescriptions.clinic_id')
            ->join('patients', 'patients.id = prescriptions.patient_id')
            ->where('prescriptions.patient_id', $context['patient_id'])
            ->where('prescriptions.clinic_id', $context['clinic_id'])
            ->where('doctors.clinic_id', $context['clinic_id'])
            ->where('patients.clinic_id', $context['clinic_id'])
            ->where('clinics.status', 1);

        if ($search !== '') {
            $safeSearch = $db->escapeLikeString($search);
            $query->groupStart()
                ->like('prescriptions.prescription_number', $search)
                ->orLike('doctors.first_name', $search)
                ->orLike('doctors.last_name', $search)
                ->orLike('prescriptions.status', $search)
                ->orWhere("EXISTS (SELECT 1 FROM prescription_items AS search_items WHERE search_items.prescription_id = prescriptions.id AND search_items.medicine_name LIKE '%{$safeSearch}%' ESCAPE '!')", null, false)
                ->groupEnd();
        }

        if (in_array($status, $allowedStatuses, true)) {
            $query->where('prescriptions.status', $status);
        } else {
            $status = '';
        }

        if ($doctorId > 0) {
            $query->where('prescriptions.doctor_id', $doctorId);
        }

        if ($dateFrom !== null) {
            $query->where('prescriptions.prescription_date >=', $dateFrom);
        } else {
            $dateFromInput = '';
        }

        if ($dateTo !== null) {
            $query->where('prescriptions.prescription_date <=', $dateTo);
        } else {
            $dateToInput = '';
        }

        $total = (clone $query)->countAllResults();
        $pages = max(1, (int) ceil($total / $pageSize));
        $page = min($page, $pages);
        $records = $query
            ->orderBy('prescriptions.prescription_date', 'DESC')
            ->orderBy('prescriptions.id', 'DESC')
            ->limit($pageSize, ($page - 1) * $pageSize)
            ->get()
            ->getResultArray();

        foreach ($records as &$record) {
            $record['specialization'] = $db->table('doctor_specialization_map')
                ->select('doctor_specializations.specialization_name')
                ->join('doctor_specializations', 'doctor_specializations.id = doctor_specialization_map.specialization_id')
                ->where('doctor_specialization_map.doctor_id', (int) $record['doctor_id'])
                ->where('doctor_specializations.status', 1)
                ->orderBy('doctor_specializations.specialization_name')
                ->get()
                ->getResultArray();
            $record['specialization'] = implode(', ', array_column($record['specialization'], 'specialization_name'));
            $record['prescription_date_display'] = $this->formatPatientDate($record['prescription_date'] ?? null);
            $record['created_at_display'] = $this->formatPatientDateTime($record['created_at'] ?? null);
            $record['updated_at_display'] = $this->formatPatientDateTime($record['updated_at'] ?? null);
            $record['status_label'] = ucwords(str_replace('_', ' ', (string) ($record['status'] ?? '')));
            $record['items'] = $db->table('prescription_items')
                ->select('medicine_name, duration, duration_unit, frequency, dosage, instructions, notes')
                ->where('prescription_id', (int) $record['id'])
                ->orderBy('id')
                ->get()
                ->getResultArray();
        }
        unset($record);

        $doctors = $db->table('doctors')
            ->select('id, first_name, last_name')
            ->where('clinic_id', $context['clinic_id'])
            ->where('status', 1)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->getResultArray();
        $clinic = $db->table('clinics')
            ->select('clinic_name, mobile, email, address')
            ->where('id', $context['clinic_id'])
            ->where('status', 1)
            ->get(1)
            ->getRowArray() ?? [];

        return view(PATIENT_VIEW . 'pages/prescriptions', [
            'patient' => $context['patient'],
            'clinic' => $clinic,
            'prescriptions' => $records,
            'doctors' => $doctors,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'doctor_id' => $doctorId > 0 ? $doctorId : '',
                'date_from' => $dateFromInput,
                'date_to' => $dateToInput,
                'view' => $view,
                'page_size' => $pageSize,
            ],
            'pagination' => ['page' => $page, 'pages' => $pages, 'total' => $total, 'page_size' => $pageSize],
        ]);
    }

    public function orders(): string|RedirectResponse
    {
        $context = $this->scope->current();

        if ($context === null) {
            return redirect()->to(base_url('permission-denied'));
        }

        $db = db_connect();
        $pageSize = $this->pageSize();
        $page = $this->page();
        $view = in_array($this->request->getGet('view'), ['list', 'grid'], true)
            ? (string) $this->request->getGet('view')
            : 'list';
        $search = trim((string) $this->request->getGet('search'));
        $status = trim((string) $this->request->getGet('status'));
        $paymentStatus = trim((string) $this->request->getGet('payment_status'));
        $deliveryStatus = trim((string) $this->request->getGet('delivery_status'));
        $dateFromInput = trim((string) $this->request->getGet('date_from'));
        $dateToInput = trim((string) $this->request->getGet('date_to'));
        $dateFrom = $this->parsePatientDate($dateFromInput);
        $dateTo = $this->parsePatientDate($dateToInput);
        $orderStatuses = ['pending', 'processing', 'fulfilled', 'cancelled', 'returned'];
        $paymentStatuses = ['pending', 'authorized', 'paid', 'failed', 'refunded'];
        $deliveryStatuses = ['pending', 'packed', 'dispatched', 'out_for_delivery', 'delivered', 'failed', 'returned'];
        $query = $db->table('medicine_orders AS orders')
            ->select(
                'orders.id, orders.order_number, orders.prescription_id, orders.status, orders.payment_status, '
                . 'orders.delivery_status, orders.payment_provider, orders.payment_reference, orders.delivery_provider, '
                . 'providers.provider_name AS trusted_delivery_provider, providers.tracking_url_template, '
                . 'orders.tracking_reference, orders.delivery_address_snapshot, orders.delivery_contact_snapshot, '
                . 'orders.dispatched_at, orders.delivered_at, orders.created_at, orders.updated_at, '
                . 'prescriptions.prescription_number, prescriptions.prescription_date, '
                . 'patients.first_name, patients.last_name, patients.registration_number, patients.mobile_number, '
                . 'patients.country_code, (SELECT COUNT(*) FROM medicine_order_items count_items '
                . 'WHERE count_items.order_id = orders.id) AS medicine_count, '
                . '(SELECT COALESCE(SUM(total_items.line_total), 0) FROM medicine_order_items total_items '
                . 'WHERE total_items.order_id = orders.id) AS order_total',
            )
            ->join('prescriptions', 'prescriptions.id = orders.prescription_id', 'left')
            ->join('patients', 'patients.id = orders.patient_id')
            ->join('delivery_providers providers', 'providers.id = orders.delivery_provider_id', 'left')
            ->where('orders.patient_id', $context['patient_id'])
            ->where('orders.clinic_id', $context['clinic_id'])
            ->groupStart()
                ->where('prescriptions.patient_id', $context['patient_id'])
                ->where('prescriptions.clinic_id', $context['clinic_id'])
                ->orWhere('prescriptions.id', null)
            ->groupEnd()
            ->where('patients.clinic_id', $context['clinic_id']);

        if ($search !== '') {
            $safeSearch = $db->escapeLikeString($search);
            $query->groupStart()
                ->like('orders.order_number', $search)
                ->orLike('prescriptions.prescription_number', $search)
                ->orLike('orders.payment_status', $search)
                ->orLike('orders.delivery_status', $search)
                ->orLike('orders.payment_provider', $search)
                ->orLike('orders.delivery_provider', $search)
                ->orLike('orders.payment_reference', $search)
                ->orLike('orders.tracking_reference', $search)
                ->orWhere("EXISTS (SELECT 1 FROM medicine_order_items search_items WHERE search_items.order_id = orders.id AND search_items.medicine_name LIKE '%{$safeSearch}%' ESCAPE '!')", null, false)
                ->groupEnd();
        }

        if (in_array($status, $orderStatuses, true)) {
            $query->where('orders.status', $status);
        } else {
            $status = '';
        }

        if (in_array($paymentStatus, $paymentStatuses, true)) {
            $query->where('orders.payment_status', $paymentStatus);
        } else {
            $paymentStatus = '';
        }

        if (in_array($deliveryStatus, $deliveryStatuses, true)) {
            $query->where('orders.delivery_status', $deliveryStatus);
        } else {
            $deliveryStatus = '';
        }

        if ($dateFrom !== null) {
            $query->where('orders.created_at >=', $dateFrom . ' 00:00:00');
        } else {
            $dateFromInput = '';
        }

        if ($dateTo !== null) {
            $query->where('orders.created_at <=', $dateTo . ' 23:59:59');
        } else {
            $dateToInput = '';
        }

        $total = (clone $query)->countAllResults();
        $pages = max(1, (int) ceil($total / $pageSize));
        $page = min($page, $pages);
        $orders = $query->orderBy('orders.created_at', 'DESC')
            ->orderBy('orders.id', 'DESC')
            ->limit($pageSize, ($page - 1) * $pageSize)
            ->get()
            ->getResultArray();

        foreach ($orders as &$order) {
            $order['patient_name'] = trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? '')) ?: 'Patient';
            $order['patient_mobile'] = trim(($order['country_code'] ?? '') . ' ' . ($order['mobile_number'] ?? '')) ?: 'Not recorded';
            $order['order_date_display'] = $this->formatPatientDate($order['created_at'] ?? null);
            $order['created_at_display'] = $this->formatPatientDateTime($order['created_at'] ?? null);
            $order['updated_at_display'] = $this->formatPatientDateTime($order['updated_at'] ?? null);
            $order['prescription_date_display'] = $this->formatPatientDate($order['prescription_date'] ?? null);
            $order['status_label'] = ucwords(str_replace('_', ' ', (string) ($order['status'] ?? '')));
            $order['payment_status_label'] = ucwords(str_replace('_', ' ', (string) ($order['payment_status'] ?? '')));
            $order['delivery_status_label'] = ucwords(str_replace('_', ' ', (string) ($order['delivery_status'] ?? '')));
            $order['tracking_url'] = $this->patientTrackingUrl($order['tracking_url_template'] ?? null, (string) ($order['tracking_reference'] ?? ''));
            unset($order['tracking_url_template'], $order['trusted_delivery_provider']);
            $order['items'] = $db->table('medicine_order_items')
                ->select('medicine_name, quantity')
                ->where('order_id', (int) $order['id'])
                ->orderBy('id', 'ASC')
                ->get()
                ->getResultArray();
            $order['medicine_summary'] = implode(', ', array_filter(array_column($order['items'], 'medicine_name')));
            $invoice = $db->table('invoices')
                ->select('id, invoice_number, currency, total, payment_status')
                ->where('order_id', (int) $order['id'])
                ->where('patient_id', $context['patient_id'])
                ->where('clinic_id', $context['clinic_id'])
                ->get(1)
                ->getRowArray();
            $order['invoice_id'] = $invoice['id'] ?? 0;
            $order['invoice_number'] = $invoice['invoice_number'] ?? '';
            $order['total_amount'] = $invoice !== null ? (float) ($invoice['total'] ?? 0) : (float) ($order['order_total'] ?? 0);
        }
        unset($order);

        return view(PATIENT_VIEW . 'pages/orders', [
            'patient' => $context['patient'],
            'orders' => $orders,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'payment_status' => $paymentStatus,
                'delivery_status' => $deliveryStatus,
                'date_from' => $dateFromInput,
                'date_to' => $dateToInput,
                'view' => $view,
                'page_size' => $pageSize,
            ],
            'pagination' => ['page' => $page, 'pages' => $pages, 'total' => $total, 'page_size' => $pageSize],
        ]);
    }

    public function invoices(): string|RedirectResponse
    {
        $context = $this->scope->current();

        if ($context === null) {
            return redirect()->to(base_url('permission-denied'));
        }

        $db = db_connect();
        $pageSize = $this->pageSize();
        $page = $this->page();
        $view = in_array($this->request->getGet('view'), ['list', 'grid'], true)
            ? (string) $this->request->getGet('view')
            : 'list';
        $search = trim((string) $this->request->getGet('search'));
        $paymentStatus = trim((string) $this->request->getGet('payment_status'));
        $orderStatus = trim((string) $this->request->getGet('order_status'));
        $dateFromInput = trim((string) $this->request->getGet('date_from'));
        $dateToInput = trim((string) $this->request->getGet('date_to'));
        $dateFrom = $this->parsePatientDate($dateFromInput);
        $dateTo = $this->parsePatientDate($dateToInput);
        $paymentStatuses = ['pending', 'authorized', 'paid', 'failed', 'refunded'];
        $orderStatuses = ['pending', 'processing', 'fulfilled', 'cancelled', 'returned'];
        $query = $db->table('invoices')
            ->select(
                'invoices.id, invoices.invoice_number, invoices.currency, invoices.subtotal, invoices.tax, '
                . 'invoices.discount, invoices.total, invoices.status, invoices.payment_status, invoices.created_at, '
                . 'invoices.updated_at, orders.id AS order_id, orders.order_number, orders.status AS order_status, '
                . 'orders.payment_provider, orders.payment_reference, orders.delivery_status, orders.delivery_provider, '
                . 'orders.tracking_reference, prescriptions.id AS prescription_id, prescriptions.prescription_number, '
                . 'prescriptions.prescription_date, patients.first_name, patients.last_name, '
                . 'patients.registration_number, patients.mobile_number, patients.country_code, clinics.clinic_name, '
                . 'clinics.mobile AS clinic_mobile, clinics.email AS clinic_email, clinics.address AS clinic_address',
            )
            ->join('medicine_orders AS orders', 'orders.id = invoices.order_id AND orders.patient_id = invoices.patient_id AND orders.clinic_id = invoices.clinic_id', 'left')
            ->join('prescriptions', 'prescriptions.id = orders.prescription_id AND prescriptions.patient_id = invoices.patient_id AND prescriptions.clinic_id = invoices.clinic_id', 'left')
            ->join('patients', 'patients.id = invoices.patient_id')
            ->join('clinics', 'clinics.id = invoices.clinic_id')
            ->where('invoices.patient_id', $context['patient_id'])
            ->where('invoices.clinic_id', $context['clinic_id'])
            ->where('patients.clinic_id', $context['clinic_id'])
            ->where('clinics.status', 1);

        if ($search !== '') {
            $safeSearch = $db->escapeLikeString($search);
            $query->groupStart()
                ->like('invoices.invoice_number', $search)
                ->orLike('orders.order_number', $search)
                ->orLike('prescriptions.prescription_number', $search)
                ->orLike('orders.payment_provider', $search)
                ->orLike('orders.payment_reference', $search)
                ->orLike('invoices.payment_status', $search)
                ->orWhere("EXISTS (SELECT 1 FROM invoice_items search_items WHERE search_items.invoice_id = invoices.id AND search_items.description LIKE '%{$safeSearch}%' ESCAPE '!')", null, false)
                ->groupEnd();
        }

        if (in_array($paymentStatus, $paymentStatuses, true)) {
            $query->where('invoices.payment_status', $paymentStatus);
        } else {
            $paymentStatus = '';
        }

        if (in_array($orderStatus, $orderStatuses, true)) {
            $query->where('orders.status', $orderStatus);
        } else {
            $orderStatus = '';
        }

        if ($dateFrom !== null) {
            $query->where('invoices.created_at >=', $dateFrom . ' 00:00:00');
        } else {
            $dateFromInput = '';
        }

        if ($dateTo !== null) {
            $query->where('invoices.created_at <=', $dateTo . ' 23:59:59');
        } else {
            $dateToInput = '';
        }

        $total = (clone $query)->countAllResults();
        $pages = max(1, (int) ceil($total / $pageSize));
        $page = min($page, $pages);
        $invoices = $query->orderBy('invoices.created_at', 'DESC')
            ->orderBy('invoices.id', 'DESC')
            ->limit($pageSize, ($page - 1) * $pageSize)
            ->get()
            ->getResultArray();

        foreach ($invoices as &$invoice) {
            $invoice['patient_name'] = trim(($invoice['first_name'] ?? '') . ' ' . ($invoice['last_name'] ?? '')) ?: 'Patient';
            $invoice['patient_mobile'] = trim(($invoice['country_code'] ?? '') . ' ' . ($invoice['mobile_number'] ?? '')) ?: 'Not recorded';
            $invoice['invoice_date_display'] = $this->formatPatientDate($invoice['created_at'] ?? null);
            $invoice['created_at_display'] = $this->formatPatientDateTime($invoice['created_at'] ?? null);
            $invoice['updated_at_display'] = $this->formatPatientDateTime($invoice['updated_at'] ?? null);
            $invoice['prescription_date_display'] = $this->formatPatientDate($invoice['prescription_date'] ?? null);
            $invoice['payment_status_label'] = ucwords(str_replace('_', ' ', (string) ($invoice['payment_status'] ?? '')));
            $invoice['order_status_label'] = ucwords(str_replace('_', ' ', (string) ($invoice['order_status'] ?? '')));
            $invoice['items'] = $db->table('invoice_items')
                ->select('description, quantity, unit_price, line_total')
                ->where('invoice_id', (int) $invoice['id'])
                ->orderBy('id', 'ASC')
                ->get()
                ->getResultArray();
            $invoice['paid_amount'] = in_array($invoice['payment_status'], ['paid', 'refunded'], true) ? (float) ($invoice['total'] ?? 0) : 0.0;
            $invoice['refunded_amount'] = $invoice['payment_status'] === 'refunded' ? (float) ($invoice['total'] ?? 0) : 0.0;
        }
        unset($invoice);

        return view(PATIENT_VIEW . 'pages/invoices', [
            'patient' => $context['patient'],
            'invoices' => $invoices,
            'filters' => [
                'search' => $search,
                'payment_status' => $paymentStatus,
                'order_status' => $orderStatus,
                'date_from' => $dateFromInput,
                'date_to' => $dateToInput,
                'view' => $view,
                'page_size' => $pageSize,
            ],
            'pagination' => ['page' => $page, 'pages' => $pages, 'total' => $total, 'page_size' => $pageSize],
        ]);
    }

    public function visits(): string|RedirectResponse
    {
        $context = $this->scope->current();

        if ($context === null) {
            return redirect()->to(base_url('permission-denied'));
        }

        $db = db_connect();
        $pageSize = $this->pageSize();
        $page = $this->page();
        $view = in_array($this->request->getGet('view'), ['list', 'grid'], true)
            ? (string) $this->request->getGet('view')
            : 'list';
        $search = trim((string) $this->request->getGet('search'));
        $status = trim((string) $this->request->getGet('status'));
        $doctorId = (int) $this->request->getGet('doctor_id');
        $service = trim((string) $this->request->getGet('service'));
        $appointmentType = trim((string) $this->request->getGet('appointment_type'));
        $dateFromInput = trim((string) $this->request->getGet('date_from'));
        $dateToInput = trim((string) $this->request->getGet('date_to'));
        $dateFrom = $this->parsePatientDate($dateFromInput);
        $dateTo = $this->parsePatientDate($dateToInput);
        $statuses = ['draft', 'completed', 'cancelled'];
        $types = ['in_person', 'online', 'home_visit'];
        $query = $db->table('consultations')
            ->select(
                'consultations.id, consultations.consultation_number, consultations.consultation_date, '
                . 'consultations.consultation_time, consultations.follow_up_date, consultations.status, '
                . 'consultations.diagnosis, consultations.appointment_id, consultations.created_at, consultations.updated_at, '
                . 'doctors.id AS doctor_id, doctors.first_name AS doctor_first_name, doctors.last_name AS doctor_last_name, '
                . 'doctors.qualification, appointments.appointment_number, appointments.appointment_type, '
                . 'appointments.service_name, patients.first_name AS patient_first_name, patients.last_name AS patient_last_name, '
                . 'patients.registration_number, patients.mobile_number, clinics.clinic_name',
            )
            ->join('doctors', 'doctors.id = consultations.doctor_id')
            ->join('appointments', 'appointments.id = consultations.appointment_id', 'left')
            ->join('patients', 'patients.id = consultations.patient_id')
            ->join('clinics', 'clinics.id = consultations.clinic_id')
            ->where('consultations.patient_id', $context['patient_id'])
            ->where('consultations.clinic_id', $context['clinic_id'])
            ->where('doctors.clinic_id', $context['clinic_id'])
            ->where('patients.clinic_id', $context['clinic_id'])
            ->where('clinics.status', 1);

        if ($search !== '') {
            $query->groupStart()
                ->like('consultations.consultation_number', $search)
                ->orLike('appointments.appointment_number', $search)
                ->orLike('appointments.service_name', $search)
                ->orLike('doctors.first_name', $search)
                ->orLike('doctors.last_name', $search)
                ->orLike('consultations.diagnosis', $search)
                ->groupEnd();
        }

        if (in_array($status, $statuses, true)) {
            $query->where('consultations.status', $status);
        } else {
            $status = '';
        }

        if ($doctorId > 0) {
            $query->where('consultations.doctor_id', $doctorId);
        }

        if ($service !== '') {
            $query->where('appointments.service_name', $service);
        }

        if (in_array($appointmentType, $types, true)) {
            $query->where('appointments.appointment_type', $appointmentType);
        } else {
            $appointmentType = '';
        }

        if ($dateFrom !== null) {
            $query->where('consultations.consultation_date >=', $dateFrom);
        } else {
            $dateFromInput = '';
        }

        if ($dateTo !== null) {
            $query->where('consultations.consultation_date <=', $dateTo);
        } else {
            $dateToInput = '';
        }

        $total = (clone $query)->countAllResults();
        $pages = max(1, (int) ceil($total / $pageSize));
        $page = min($page, $pages);
        $visits = $query->orderBy('consultations.consultation_date', 'DESC')
            ->orderBy('consultations.consultation_time', 'DESC')
            ->orderBy('consultations.id', 'DESC')
            ->limit($pageSize, ($page - 1) * $pageSize)
            ->get()
            ->getResultArray();

        foreach ($visits as &$visit) {
            $visit['visit_date_display'] = $this->formatPatientDate($visit['consultation_date'] ?? null);
            $visit['visit_time_display'] = $this->formatPatientTime($visit['consultation_time'] ?? null);
            $visit['created_at_display'] = $this->formatPatientDateTime($visit['created_at'] ?? null);
            $visit['updated_at_display'] = $this->formatPatientDateTime($visit['updated_at'] ?? null);
            $visit['status_label'] = ucwords(str_replace('_', ' ', (string) ($visit['status'] ?? '')));
            $visit['type_label'] = ucwords(str_replace('_', ' ', (string) ($visit['appointment_type'] ?? 'Consultation')));
            $visit['service_display'] = trim((string) ($visit['service_name'] ?? '')) ?: $visit['type_label'];
            $visit['specialization'] = $db->table('doctor_specialization_map')
                ->select('doctor_specializations.specialization_name')
                ->join('doctor_specializations', 'doctor_specializations.id = doctor_specialization_map.specialization_id')
                ->where('doctor_specialization_map.doctor_id', (int) $visit['doctor_id'])
                ->where('doctor_specializations.status', 1)
                ->orderBy('doctor_specializations.specialization_name')
                ->get()
                ->getResultArray();
            $visit['specialization'] = implode(', ', array_column($visit['specialization'], 'specialization_name'));
            $visit['follow_up_date_display'] = $this->formatPatientDate($visit['follow_up_date'] ?? null);
            $visit['diagnosis_summary'] = $visit['status'] === 'completed' ? trim((string) ($visit['diagnosis'] ?? '')) : '';
            $visit['prescription_id'] = 0;
            $visit['invoice_id'] = 0;
            $linkedPrescription = $db->table('prescriptions')
                ->select('id')
                ->where('consultation_id', (int) $visit['id'])
                ->where('patient_id', $context['patient_id'])
                ->where('clinic_id', $context['clinic_id'])
                ->get(1)
                ->getRowArray();

            if ($linkedPrescription !== null) {
                $visit['prescription_id'] = (int) $linkedPrescription['id'];
            }

            if ((int) ($visit['appointment_id'] ?? 0) > 0) {
                $linkedInvoice = $db->table('invoices')
                    ->select('id')
                    ->where('appointment_id', (int) $visit['appointment_id'])
                    ->where('patient_id', $context['patient_id'])
                    ->where('clinic_id', $context['clinic_id'])
                    ->get(1)
                    ->getRowArray();
                $visit['invoice_id'] = (int) ($linkedInvoice['id'] ?? 0);
            }
        }
        unset($visit);

        $doctors = $db->table('doctors')
            ->select('id, first_name, last_name')
            ->where('clinic_id', $context['clinic_id'])
            ->where('status', 1)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->getResultArray();
        $services = $db->table('appointments')
            ->distinct()
            ->select('service_name')
            ->where('patient_id', $context['patient_id'])
            ->where('clinic_id', $context['clinic_id'])
            ->where('service_name !=', '')
            ->orderBy('service_name')
            ->get()
            ->getResultArray();

        return view(PATIENT_VIEW . 'pages/visits', [
            'patient' => $context['patient'],
            'visits' => $visits,
            'doctors' => $doctors,
            'services' => $services,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'doctor_id' => $doctorId > 0 ? $doctorId : '',
                'service' => $service,
                'appointment_type' => $appointmentType,
                'date_from' => $dateFromInput,
                'date_to' => $dateToInput,
                'view' => $view,
                'page_size' => $pageSize,
            ],
            'pagination' => ['page' => $page, 'pages' => $pages, 'total' => $total, 'page_size' => $pageSize],
        ]);
    }

    public function documents(): string|RedirectResponse
    {
        return $this->recordsPage('Documents', 'Access your private uploaded documents.', 'documents', 'patient_documents', 'created_at');
    }

    public function support(): string|RedirectResponse
    {
        return $this->render('Support and Help', 'Contact your clinic for help with appointments, prescriptions, or orders.', 'support', []);
    }

    private function recordsPage(string $title, string $description, string $key, string $table, string $dateColumn): string|RedirectResponse
    {
        $context = $this->scope->current();

        if ($context === null) {
            return redirect()->to(base_url('permission-denied'));
        }

        $pageSize = $this->pageSize();
        $page = $this->page();
        $search = trim((string) $this->request->getGet('search'));
        $builder = db_connect()->table($table)->where($table . '.patient_id', $context['patient_id']);

        if (in_array($table, ['patient_documents', 'medicine_orders', 'invoices', 'consultations', 'prescriptions'], true)) {
            $builder->where($table . '.clinic_id', $context['clinic_id']);
        }

        if ($table === 'medicine_orders') {
            $builder->select('medicine_orders.*, prescriptions.prescription_number, prescriptions.prescription_date')
                ->join('prescriptions', 'prescriptions.id = medicine_orders.prescription_id', 'left')
                ->groupStart()
                    ->where('prescriptions.patient_id', $context['patient_id'])
                    ->where('prescriptions.clinic_id', $context['clinic_id'])
                    ->orWhere('prescriptions.id', null)
                ->groupEnd();
        }

        if ($table === 'consultations') {
            $builder->select('consultations.*, doctors.first_name AS doctor_first_name, doctors.last_name AS doctor_last_name')
                ->join('doctors', 'doctors.id = consultations.doctor_id')
                ->where('doctors.clinic_id', $context['clinic_id']);
        }

        $searchColumns = [
            'prescriptions' => ['prescription_number', 'status'],
            'medicine_orders' => ['medicine_orders.order_number', 'medicine_orders.status', 'medicine_orders.payment_status', 'medicine_orders.delivery_status'],
            'invoices' => ['invoice_number', 'status', 'payment_status'],
            'consultations' => ['consultation_number', 'status'],
            'patient_documents' => ['title', 'document_type'],
        ][$table] ?? [];

        if ($search !== '' && $searchColumns !== []) {
            $builder->groupStart();

            foreach ($searchColumns as $index => $column) {
                $index === 0 ? $builder->like($column, $search) : $builder->orLike($column, $search);
            }

            $builder->groupEnd();
        }

        $total = (clone $builder)->countAllResults();
        $pages = max(1, (int) ceil($total / $pageSize));
        $page = min($page, $pages);
        $records = $builder->orderBy($table . '.' . $dateColumn, 'DESC')->limit($pageSize, ($page - 1) * $pageSize)->get()->getResultArray();

        return $this->render($title, $description, $key, $records, $context, ['search' => $search, 'page' => $page, 'pages' => $pages, 'total' => $total, 'page_size' => $pageSize]);
    }

    private function render(string $title, string $description, string $key, array $records, ?array $context = null, array $pagination = []): string|RedirectResponse
    {
        $context ??= $this->scope->current();

        if ($context === null) {
            return redirect()->to(base_url('permission-denied'));
        }

        return view(PATIENT_VIEW . 'pages/section', [
            'title' => $title,
            'description' => $description,
            'sectionKey' => $key,
            'records' => $records,
            'patient' => $context['patient'],
            'clinic' => db_connect()->table('clinics')->select('clinic_name, mobile, email, address')->where('id', $context['clinic_id'])->where('status', 1)->get()->getRowArray() ?? [],
            'pagination' => $pagination,
        ]);
    }

    private function pageSize(): int
    {
        $pageSize = (int) ($this->request->getGet('page_size') ?? 15);

        return in_array($pageSize, [10, 15, 25, 50], true) ? $pageSize : 15;
    }

    private function page(): int
    {
        return max(1, (int) ($this->request->getGet('page') ?? 1));
    }

    private function parsePatientDate(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!d-m-Y', $value);
        $errors = \DateTimeImmutable::getLastErrors();

        if ($date === false || $date->format('d-m-Y') !== $value || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return null;
        }

        return $date->format('Y-m-d');
    }

    private function formatPatientTime(?string $value): string
    {
        $formatted = rootins_format_time($value);
        if ($formatted !== '') {
            return $formatted;
        }

        if (! $value) {
            return '—';
        }

        $time = \DateTimeImmutable::createFromFormat('!H:i:s', substr($value, 0, 8))
            ?: \DateTimeImmutable::createFromFormat('!H:i', substr($value, 0, 5));

        return $time ? $time->format('h:i A') : '—';
    }

    private function patientTrackingUrl(?string $template, string $reference): ?string
    {
        $template = trim((string) $template);
        $reference = trim($reference);

        if ($template === '' || filter_var($template, FILTER_VALIDATE_URL) === false || ! preg_match('#^https?://#i', $template)) {
            return null;
        }

        if ($reference !== '') {
            $template = str_replace(
                ['{{tracking_reference}}', '{tracking_reference}', '{tracking}'],
                rawurlencode($reference),
                $template,
            );
        }

        return filter_var($template, FILTER_VALIDATE_URL) !== false ? $template : null;
    }

    private function formatPatientDateTime(?string $value): string
    {
        if (! $value) {
            return '—';
        }

        $dateTime = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', substr($value, 0, 19));

        return $dateTime ? $dateTime->format('d-m-Y h:i A') : $this->formatPatientDate($value);
    }

    private function formatPatientDate(?string $value): string
    {
        if (! $value) {
            return '—';
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', substr($value, 0, 10));

        return $date ? $date->format('d-m-Y') : '—';
    }
}
