<?php

namespace Admin\Controllers\Integration;

use App\Controllers\BaseAdminController;
use App\Services\Shopify\ShopifyOrderOperationsService;
use App\Services\Shopify\ShopifyPrescriptionUploadService;
use CodeIgniter\HTTP\ResponseInterface;
use InvalidArgumentException;

final class ShopifyOrderOperations extends BaseAdminController
{
    private ShopifyOrderOperationsService $operations;

    public function __construct()
    {
        $this->operations = new ShopifyOrderOperationsService();
    }

    public function mappings(): string
    {
        return view(ADMIN_VIEW . 'pages/integrations/shopify_order_mappings', $this->operations->mappingDashboard($this->request->getGet()));
    }

    public function readiness(): string
    {
        return view(ADMIN_VIEW . 'pages/integrations/shopify_patient_readiness', ['rows' => $this->operations->readiness($this->request->getGet()), 'filters' => $this->request->getGet()]);
    }

    public function queue(): string
    {
        return view(ADMIN_VIEW . 'pages/integrations/shopify_order_operations', ['imports' => $this->operations->queue($this->request->getGet()), 'counters' => $this->operations->counters(), 'filters' => $this->request->getGet()]);
    }

    public function detail(int $id): string|ResponseInterface
    {
        $detail = $this->operations->detail($id);
        if ($detail === null) return $this->response->setStatusCode(404)->setBody('Shopify order was not found.');
        return view(ADMIN_VIEW . 'pages/integrations/shopify_order_operation_detail', ['import' => $detail, 'readiness' => $this->operations->readiness(['store' => $detail['store_domain']]), 'medicines' => $this->operations->activeMedicines()]);
    }

    public function viewPrescription(int $documentId): ResponseInterface
    {
        try {
            $document = (new ShopifyPrescriptionUploadService())->find($documentId);
            if ($document === null) return $this->response->setStatusCode(404)->setBody('Prescription not found.');
            $this->audit->record('shopify.prescription_temporary_view', ['document_id' => $documentId, 'actor' => (int) ($this->user['id'] ?? 0)]);
            return redirect()->to((new ShopifyPrescriptionUploadService())->temporaryUrl($document, 5));
        } catch (\Throwable) {
            return $this->response->setStatusCode(503)->setBody('Prescription could not be loaded safely.');
        }
    }

    public function saveMapping(): ResponseInterface { return $this->run(fn () => $this->operations->saveMapping($this->request->getPost(), (int) $this->request->getPost('id') > 0 ? (int) $this->request->getPost('id') : null), 'Shopify medicine mapping saved.'); }
    public function searchMedicines(): ResponseInterface { return $this->response->setJSON(['status' => true, 'data' => $this->operations->searchMedicines((string) $this->request->getGet('q'))]); }
    public function bulkSaveMappings(): ResponseInterface { return $this->run(fn () => $this->operations->bulkSaveMappings($this->request->getPost(), (int) ($this->user['id'] ?? 0)), 'Shopify medicine mappings saved.'); }
    public function ignoreMapping(): ResponseInterface { return $this->run(fn () => $this->operations->ignoreMapping($this->request->getPost(), (int) ($this->user['id'] ?? 0)), 'Shopify product ignored for mapping.'); }
    public function recheckMappings(): ResponseInterface { return $this->run(fn () => $this->operations->recheckMappings($this->request->getPost()), 'Affected orders queued for reconciliation.'); }
    public function toggleMapping(int $id): ResponseInterface { return $this->run(fn () => $this->operations->toggleMapping($id), 'Shopify medicine mapping status updated.'); }

    public function action(int $id): ResponseInterface
    {
        return $this->run(fn () => $this->operations->action($id, trim((string) $this->request->getPost('action')), $this->request->getPost(), (int) ($this->user['id'] ?? 0)), 'Shopify order operation completed.');
    }

    private function run(callable $callback, string $message): ResponseInterface
    {
        try { $data = $callback(); return $this->response->setJSON(['status' => true, 'msg' => $message, 'data' => $data]); }
        catch (InvalidArgumentException $exception) { return $this->response->setStatusCode(422)->setJSON(['status' => false, 'msg' => $exception->getMessage()]); }
        catch (\Throwable $exception) { log_message('error', 'Shopify order operations action failed safely.'); return $this->response->setStatusCode(500)->setJSON(['status' => false, 'msg' => 'Shopify order operation could not be completed safely.']); }
    }
}
