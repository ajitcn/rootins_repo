<?php

namespace Admin\Controllers;

use App\Controllers\BaseController;
use Admin\Services\DashboardService;

class Dashboard extends BaseController
{
    private DashboardService $dashboard;

    public function __construct()
    {
        $this->dashboard = new DashboardService();
    }

    public function index()
    {
        $data = $this->dashboard->data($this->request->getGet());
        return view(ADMIN_VIEW . 'pages/dashboard', $data + ['rangeOptions' => $this->dashboard->rangeOptions()]);
    }

    public function data()
    {
        try {
            return $this->response->setJSON(['status' => true, 'data' => $this->dashboard->data($this->request->getGet())]);
        } catch (\Throwable $e) {
            log_message('error', 'Admin dashboard data failed: {message}', ['message' => $e->getMessage()]);
            return $this->response->setStatusCode(500)->setJSON(['status' => false, 'msg' => 'Dashboard data could not be loaded.']);
        }
    }
}
