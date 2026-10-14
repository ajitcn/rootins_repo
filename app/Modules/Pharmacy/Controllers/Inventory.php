<?php

namespace Pharmacy\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use Pharmacy\Services\PharmacyScope;
use Pharmacy\Services\MedicineAvailabilityService;
use Pharmacy\Services\CentralInventoryService;

final class Inventory extends BaseController
{
    private PharmacyScope $scope;

    private MedicineAvailabilityService $availability;

    private CentralInventoryService $central;

    public function __construct()
    {
        $this->scope = new PharmacyScope();
        $this->availability = new MedicineAvailabilityService($this->scope);
        $this->central = new CentralInventoryService($this->scope);
    }

    public function index(): string|ResponseInterface
    {
        $clinicId = $this->scope->clinicId();

        if ($clinicId === null || !$this->scope->can('pharmacy.inventory.view')) {
            return redirect()->to(base_url('permission-denied'));
        }

        try {
            $data = $this->availability->search([
                'view' => (string) ($this->request->getGet('view') ?? 'list'),
                'page' => (int) ($this->request->getGet('page') ?? 1),
                'page_size' => (int) ($this->request->getGet('page_size') ?? 15),
                'search' => (string) ($this->request->getGet('search') ?? ''),
                'availability' => (string) ($this->request->getGet('availability') ?? ''),
                'expiry_state' => (string) ($this->request->getGet('expiry_state') ?? ''),
                'medicine_id' => (int) ($this->request->getGet('medicine_id') ?? 0),
            ]);
            $view = in_array(($data['view'] ?? 'list'), ['list', 'grid'], true) ? $data['view'] : 'list';

            return view(PHARMACY_VIEW . 'pages/inventory/' . $view, array_merge($data, ['clinicId' => $clinicId]));
        } catch (\Throwable $exception) {
            log_message('error', 'Pharmacy inventory view failed route=' . uri_string() . ' controller=Inventory::index file=' . $exception->getFile() . ' line=' . $exception->getLine() . ' data_keys=' . implode(',', array_keys($data ?? [])) . ' error=' . $exception->getMessage());

            return $this->response->setStatusCode(500)->setBody('Medicine availability could not be loaded safely.');
        }
    }

    public function adjust(): ResponseInterface
    {
        if (!$this->scope->can('pharmacy.inventory.batch.adjust')) {
            $this->scope->audit('pharmacy.permission_denied', ['permission' => 'pharmacy.inventory.batch.adjust']);

            return $this->response->setStatusCode(403)->setJSON(['status' => false, 'msg' => 'You are not allowed to adjust central stock.']);
        }
        $result = $this->central->adjust((int) $this->request->getPost('batch_id'), (int) $this->request->getPost('adjustment'), (string) $this->request->getPost('reason'), (string) $this->request->getPost('confirmation') === 'I confirm this central stock adjustment');

        return $this->response->setStatusCode($result['http'])->setJSON($result['body']);
    }

    public function create(): ResponseInterface
    {
        if (!$this->scope->can('pharmacy.inventory.batch.create')) {
            $this->scope->audit('pharmacy.permission_denied', ['permission' => 'pharmacy.inventory.batch.create']);

            return $this->response->setStatusCode(403)->setJSON(['status' => false, 'msg' => 'You are not allowed to add central stock batches.']);
        }
        $result = $this->central->create(
            (string) $this->request->getPost('medicine_id'),
            (string) $this->request->getPost('batch_number'),
            (string) $this->request->getPost('expiry_date'),
            (string) $this->request->getPost('quantity'),
            (string) ($this->request->getPost('status') ?? 'active'),
        );

        return $this->response->setStatusCode($result['http'])->setJSON($result['body']);
    }
}
