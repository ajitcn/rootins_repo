<?php

namespace Pharmacy\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use Pharmacy\Services\DeliveryService;
use Pharmacy\Services\PharmacyScope;

final class Delivery extends BaseController
{
    private PharmacyScope $scope;

    private DeliveryService $delivery;

    public function __construct()
    {
        $this->scope = new PharmacyScope();
        $this->delivery = new DeliveryService($this->scope);
    }

    public function index(): string|ResponseInterface
    {
        if ($this->scope->clinicId() === null || !$this->scope->can('pharmacy.delivery.view')) {
            return redirect()->to(base_url('permission-denied'));
        }
        $query = [];
        foreach (['search', 'delivery_status', 'page_size', 'from', 'to'] as $key) {
            $value = trim((string) $this->request->getGet($key));

            if ($value !== '') {
                $query[$key] = $value;
            }
        }
        $query['view'] = in_array((string) $this->request->getGet('view'), ['list', 'grid'], true) ? (string) $this->request->getGet('view') : 'list';

        return redirect()->to(base_url('pharmacy/orders' . ($query === [] ? '' : '?' . http_build_query($query))));
    }

    public function detail(int $orderId): ResponseInterface
    {
        if (!$this->scope->can('pharmacy.delivery.view')) {
            $this->scope->audit('pharmacy.permission_denied', ['permission' => 'pharmacy.delivery.view', 'order_id' => $orderId]);

            return $this->response->setStatusCode(403)->setJSON(['status' => false, 'msg' => 'You are not allowed to view delivery records.']);
        }

        try {
            $row = $this->delivery->detail($orderId);

            return $row === null ? $this->response->setStatusCode(404)->setJSON(['status' => false, 'msg' => 'Delivery record not found.']) : $this->response->setJSON(['status' => true, 'data' => ['delivery' => $row]]);
        } catch (\Throwable $exception) {
            log_message('error', 'Pharmacy delivery detail failed route=' . uri_string() . ' controller=Delivery::detail method=GET order_id=' . $orderId . ' file=' . $exception->getFile() . ' line=' . $exception->getLine() . ' error=' . $exception->getMessage());

            return $this->response->setStatusCode(500)->setJSON(['status' => false, 'msg' => 'Delivery details could not be loaded safely.']);
        }
    }

    public function update(): ResponseInterface
    {
        if (!$this->scope->can('pharmacy.delivery.update')) {
            $this->scope->audit('pharmacy.permission_denied', ['permission' => 'pharmacy.delivery.update']);

            return $this->response->setStatusCode(403)->setJSON(['status' => false, 'msg' => 'You are not allowed to update delivery records.']);
        }
        $result = $this->delivery->update((int) $this->request->getPost('order_id'), (string) $this->request->getPost('delivery_status'), (string) $this->request->getPost('provider'), (string) $this->request->getPost('tracking_reference'), (string) $this->request->getPost('reason'), (string) $this->request->getPost('confirmation') === 'I confirm this delivery update', $this->request->getPost('address_snapshot'), $this->request->getPost('contact_snapshot'), (int) $this->request->getPost('provider_id'));

        return $this->response->setStatusCode($result['http'])->setJSON($result['body']);
    }
}
