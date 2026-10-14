<?php

namespace Admin\Controllers;

use App\Controllers\BaseController;

class Dashboard extends BaseController
{
    public function index()
    {
        $db = db_connect();
        $today = date('Y-m-d');
        $metrics = [
            'clinics' => $db->table('clinics')->where('status', 1)->countAllResults(),
            'doctors' => $db->table('doctors')->where('status', 1)->countAllResults(),
            'patients' => $db->table('patients')->where('status', 1)->countAllResults(),
            'appointments' => $db->table('appointments')->where('appointment_date', $today)->countAllResults(),
            'revenue' => (float) ($db->table('invoices')->selectSum('total')->where('status', 'finalized')->get()->getRow('total') ?? 0),
            'payments' => $db->table('invoices')->where('payment_status', 'paid')->countAllResults(),
            'orders' => $db->table('medicine_orders')->countAllResults(),
            'audit_alerts' => $db->table('audit_logs')->where('occurred_at >=', date('Y-m-d 00:00:00'))->groupStart()->like('action', 'failed')->orLike('action', 'denied')->groupEnd()->countAllResults(),
        ];

        return view(ADMIN_VIEW . 'pages/dashboard', ['metrics' => $metrics]);
    }
}
