<?php

namespace Pharmacy\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use Pharmacy\Services\PharmacyScope;
use Pharmacy\Services\OrderFulfilmentService;
use Pharmacy\Services\OrderLabelService;
use Pharmacy\Services\DeliveryProviderCatalog;
use Pharmacy\Services\PaymentStatusCatalog;
use Pharmacy\Services\PaymentProviderCatalog;

final class Orders extends BaseController
{
    private PharmacyScope $scope;

    private OrderFulfilmentService $orders;

    private OrderLabelService $labels;
    private DeliveryProviderCatalog $providerCatalog;
    private PaymentStatusCatalog $paymentStatusCatalog;
    private PaymentProviderCatalog $paymentProviderCatalog;

    public function __construct()
    {
        $this->scope = new PharmacyScope();
        $this->orders = new OrderFulfilmentService($this->scope);
        $this->labels = new OrderLabelService($this->scope);
        $this->providerCatalog = new DeliveryProviderCatalog();
        $this->paymentStatusCatalog = new PaymentStatusCatalog();
        $this->paymentProviderCatalog = new PaymentProviderCatalog();
    }

    public function index(): string|ResponseInterface
    {
        if ($this->scope->clinicId() === null || !$this->scope->can('pharmacy.order.view')) {
            return redirect()->to(base_url('permission-denied'));
        }

        try {
            $input = $this->request->getGet();
            $data = $this->orders->list(is_array($input) ? $input : []);
            $data['deliveryProviders'] = $this->providerCatalog->active();
            $data['paymentStatuses'] = $this->paymentStatusCatalog->active();
            $data['paymentProviders'] = $this->paymentProviderCatalog->active();
            $view = in_array(($data['view'] ?? 'list'), ['list', 'grid'], true) ? $data['view'] : 'list';

            return view(PHARMACY_VIEW . 'pages/orders/' . $view, $data);
        } catch (\Throwable $exception) {
            log_message('error', 'Pharmacy order view failed: ' . $exception->getMessage());

            return $this->response->setStatusCode(500)->setBody('Orders could not be loaded safely.');
        }
    }

    public function detail(int $orderId): ResponseInterface
    {
        if (!$this->scope->can('pharmacy.order.view')) {
            $this->scope->audit('pharmacy.permission_denied', ['permission' => 'pharmacy.order.view', 'order_id' => $orderId]);

            return $this->response->setStatusCode(403)->setJSON(['status' => false, 'msg' => 'You are not allowed to view this pharmacy order.']);
        }

        try {
            $order = $this->orders->detail($orderId);

            return $order === null ? $this->response->setStatusCode(404)->setJSON(['status' => false, 'msg' => 'Order not found.']) : $this->response->setJSON(['status' => true, 'data' => ['order' => $order]]);
        } catch (\Throwable $exception) {
            log_message('error', 'Pharmacy order detail failed: ' . $exception->getMessage());

            return $this->response->setStatusCode(500)->setJSON(['status' => false, 'msg' => 'Order details could not be loaded safely.']);
        }
    }

    public function labels(int $orderId): ResponseInterface
    {
        if (!$this->scope->can('pharmacy.order.label.view')) {
            $this->scope->audit('pharmacy.permission_denied', [
                'permission' => 'pharmacy.order.label.view',
                'order_id' => $orderId,
            ]);

            return $this->response->setStatusCode(403)->setJSON([
                'status' => false,
                'msg' => 'You are not allowed to view order labels.',
            ]);
        }

        try {
            $result = $this->labels->prepare($orderId, 'all');

            if ($result['http'] === 200) {
                $this->scope->audit('pharmacy.order_label_preview', [
                    'order_id' => $orderId,
                    'label_type' => 'all',
                ]);
            }

            return $this->response
                ->setStatusCode($result['http'])
                ->setJSON($result['body']);
        } catch (\Throwable $exception) {
            log_message(
                'error',
                'Pharmacy label preview failed route=' . uri_string()
                . ' controller=Orders::labels method=' . $this->request->getMethod()
                . ' order_id=' . $orderId
                . ' file=' . $exception->getFile()
                . ' line=' . $exception->getLine()
                . ' error=' . $exception->getMessage()
            );

            return $this->response->setStatusCode(500)->setJSON([
                'status' => false,
                'msg' => 'Order labels could not be loaded safely.',
            ]);
        }
    }

    public function printLabels(): ResponseInterface
    {
        if (!$this->scope->can('pharmacy.order.label.print')) {
            $this->scope->audit('pharmacy.permission_denied', [
                'permission' => 'pharmacy.order.label.print',
            ]);

            return $this->response->setStatusCode(403)->setJSON([
                'status' => false,
                'msg' => 'You are not allowed to print order labels.',
            ]);
        }

        $orderId = (int) $this->request->getPost('order_id');
        $labelType = trim((string) $this->request->getPost('label_type'));
        $size = trim((string) $this->request->getPost('size'));

        if (!in_array($labelType, ['order', 'medicine', 'patient', 'all'], true)) {
            return $this->response->setStatusCode(422)->setJSON([
                'status' => false,
                'msg' => 'Select a valid label type.',
                'errors' => ['label_type' => 'Select a valid label type.'],
            ]);
        }

        if (!in_array($size, ['a4', 'thermal', 'small_package'], true)) {
            return $this->response->setStatusCode(422)->setJSON([
                'status' => false,
                'msg' => 'Select a valid label size.',
                'errors' => ['size' => 'Select a valid label size.'],
            ]);
        }

        try {
            $result = $this->labels->prepare($orderId, $labelType);

            if ($result['http'] === 200) {
                $this->scope->audit('pharmacy.order_label_printed', [
                    'order_id' => $orderId,
                    'label_type' => $labelType,
                    'size' => $size,
                ]);
            }

            return $this->response
                ->setStatusCode($result['http'])
                ->setJSON($result['body']);
        } catch (\Throwable $exception) {
            log_message(
                'error',
                'Pharmacy label print failed route=' . uri_string()
                . ' controller=Orders::printLabels method=' . $this->request->getMethod()
                . ' order_id=' . $orderId
                . ' label_type=' . $labelType
                . ' file=' . $exception->getFile()
                . ' line=' . $exception->getLine()
                . ' error=' . $exception->getMessage()
            );

            return $this->response->setStatusCode(500)->setJSON([
                'status' => false,
                'msg' => 'Order labels could not be prepared safely.',
            ]);
        }
    }

    public function create(): ResponseInterface
    {
        if (!$this->scope->can('pharmacy.order.create')) {
            $this->scope->audit('pharmacy.permission_denied', ['permission' => 'pharmacy.order.create']);

            return $this->response->setStatusCode(403)->setJSON(['status' => false, 'msg' => 'You are not allowed to create orders.']);
        }

        try {
            $result = $this->orders->create((int) $this->request->getPost('prescription_id'));

            return $this->response->setStatusCode($result['http'])->setJSON($result['body']);
        } catch (\Throwable $exception) {
            log_message('error', 'Pharmacy order creation endpoint failed route=' . uri_string() . ' controller=Orders::create method=' . $this->request->getMethod() . ' prescription_id=' . (string) $this->request->getPost('prescription_id') . ' file=' . $exception->getFile() . ' line=' . $exception->getLine() . ' error=' . $exception->getMessage());

            return $this->response->setStatusCode(500)->setJSON(['status' => false, 'msg' => 'Medicine order could not be created safely.']);
        }
    }

    public function cancel(): ResponseInterface
    {
        if (!$this->scope->can('pharmacy.order.cancel')) {
            $this->scope->audit('pharmacy.permission_denied', ['permission' => 'pharmacy.order.cancel']);

            return $this->response->setStatusCode(403)->setJSON(['status' => false, 'msg' => 'You are not allowed to cancel orders.']);
        }

        try {
            $confirmation = trim((string) $this->request->getPost('confirmation'));
            $result = $this->orders->cancel((int) $this->request->getPost('order_id'), (string) $this->request->getPost('reason'), $confirmation === 'I confirm this order cancellation');

            return $this->response->setStatusCode($result['http'])->setJSON($result['body']);
        } catch (\Throwable $exception) {
            log_message('error', 'Pharmacy order cancellation endpoint failed route=' . uri_string() . ' controller=Orders::cancel order_id=' . (string) $this->request->getPost('order_id') . ' file=' . $exception->getFile() . ' line=' . $exception->getLine() . ' error=' . $exception->getMessage());

            return $this->response->setStatusCode(500)->setJSON(['status' => false, 'msg' => 'Order could not be cancelled safely.']);
        }
    }
}
