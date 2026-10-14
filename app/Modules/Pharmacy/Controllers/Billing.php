<?php

namespace Pharmacy\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use Pharmacy\Services\BillingService;
use Pharmacy\Services\PharmacyScope;
use Pharmacy\Services\PaymentStatusCatalog;
use Pharmacy\Services\PaymentProviderCatalog;

final class Billing extends BaseController
{
    private PharmacyScope $scope;

    private BillingService $billing;

    private PaymentStatusCatalog $paymentStatusCatalog;
    private PaymentProviderCatalog $paymentProviderCatalog;

    public function __construct()
    {
        $this->scope = new PharmacyScope();
        $this->billing = new BillingService($this->scope);
        $this->paymentStatusCatalog = new PaymentStatusCatalog();
        $this->paymentProviderCatalog = new PaymentProviderCatalog();
    }

    public function index(): string|ResponseInterface
    {
        if ($this->scope->clinicId() === null || !$this->scope->can('pharmacy.billing.view')) {
            return redirect()->to(base_url('permission-denied'));
        }

        try {
            $input = $this->request->getGet();
            $data = $this->billing->list(is_array($input) ? $input : []);
            $data['paymentStatuses'] = $this->paymentStatusCatalog->active();
            $data['paymentProviders'] = $this->paymentProviderCatalog->active();
            $view = in_array(($data['view'] ?? 'list'), ['list', 'grid'], true) ? $data['view'] : 'list';

            return view(PHARMACY_VIEW . 'pages/billing/' . $view, $data);
        } catch (\Throwable $exception) {
            log_message('error', 'Pharmacy billing view failed: ' . $exception->getMessage());

            return $this->response->setStatusCode(500)->setBody('Billing could not be loaded safely.');
        }
    }

    public function detail(int $orderId): ResponseInterface
    {
        if (!$this->scope->can('pharmacy.billing.view')) {
            $this->scope->audit('pharmacy.permission_denied', ['permission' => 'pharmacy.billing.view', 'order_id' => $orderId]);

            return $this->response->setStatusCode(403)->setJSON(['status' => false, 'msg' => 'You are not allowed to view pharmacy.billing.']);
        }

        try {
            $row = $this->billing->detail($orderId);

            return $row === null ? $this->response->setStatusCode(404)->setJSON(['status' => false, 'msg' => 'Billing record not found.']) : $this->response->setJSON(['status' => true, 'data' => ['billing' => $row]]);
        } catch (\Throwable $exception) {
            log_message('error', 'Pharmacy billing detail failed: ' . $exception->getMessage());

            return $this->response->setStatusCode(500)->setJSON(['status' => false, 'msg' => 'Billing details could not be loaded safely.']);
        }
    }

    public function reconcile(): ResponseInterface
    {
        if (!$this->scope->can('pharmacy.billing.payment.update')) {
            $this->scope->audit('pharmacy.permission_denied', ['permission' => 'pharmacy.billing.payment.update']);

            return $this->response->setStatusCode(403)->setJSON(['status' => false, 'msg' => 'You are not allowed to update payment status.']);
        }
        $result = $this->billing->reconcile((int) $this->request->getPost('order_id'), (string) $this->request->getPost('payment_status'), (string) $this->request->getPost('provider'), (string) $this->request->getPost('reference'), (string) $this->request->getPost('reason'), (string) $this->request->getPost('confirmation') === 'I confirm this payment was verified manually');

        return $this->response->setStatusCode($result['http'])->setJSON($result['body']);
    }

    public function refund(): ResponseInterface
    {
        if (!$this->scope->can('pharmacy.billing.refund')) {
            $this->scope->audit('pharmacy.permission_denied', ['permission' => 'pharmacy.billing.refund']);

            return $this->response->setStatusCode(403)->setJSON(['status' => false, 'msg' => 'You are not allowed to request refunds.']);
        }
        $result = $this->billing->refund((int) $this->request->getPost('order_id'), (float) $this->request->getPost('amount'), (string) $this->request->getPost('provider'), (string) $this->request->getPost('reference'), (string) $this->request->getPost('reason'), (string) $this->request->getPost('confirmation') === 'I confirm this refund request');

        return $this->response->setStatusCode($result['http'])->setJSON($result['body']);
    }
}
