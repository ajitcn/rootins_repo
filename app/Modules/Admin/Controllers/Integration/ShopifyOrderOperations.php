<?php

namespace Admin\Controllers\Integration;

use App\Controllers\BaseAdminController;
use App\Services\Shopify\ShopifyOrderOperationsService;
use App\Services\Shopify\ShopifyPrescriptionUploadService;
use App\Services\Shopify\ShopifyProductSyncException;
use App\Services\Shopify\ShopifyMappingException;
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

    public function saveMapping(): ResponseInterface
    {
        $correlationId = bin2hex(random_bytes(16));
        $input = $this->request->getPost();
        if (! in_array('shopify.order.operations', session('permissions') ?? [], true)) {
            return $this->mappingError('SHOPIFY_MAPPING_PERMISSION_DENIED', 'You are not authorized to update Shopify mappings.', $correlationId, 403);
        }
        try {
            $data = $this->operations->saveMapping($input, (int) ($input['id'] ?? 0) > 0 ? (int) $input['id'] : null);
            return $this->response->setJSON(['status' => true, 'data' => $data, 'correlation_id' => $correlationId, 'csrf' => ['name' => csrf_token(), 'hash' => csrf_hash()]]);
        } catch (ShopifyMappingException $exception) {
            $cause = $exception->getPrevious()?->getMessage() ?? $exception->getMessage();
            log_message('error', 'Shopify mapping update category: ' . $exception->errorCode . ' Correlation ID: ' . $correlationId . ' Cause: ' . $cause);
            $status = in_array($exception->errorCode, ['SHOPIFY_MAPPING_PERMISSION_DENIED', 'SHOPIFY_MAPPING_CSRF_INVALID'], true) ? 403 : (in_array($exception->errorCode, ['SHOPIFY_MAPPING_TABLE_MISSING', 'SHOPIFY_MAPPING_COLUMN_MISSING', 'SHOPIFY_MAPPING_FOREIGN_KEY_FAILURE', 'SHOPIFY_MAPPING_DATABASE_FAILURE'], true) ? 500 : 422);
            return $this->mappingError($exception->errorCode, $exception->getMessage(), $correlationId, $status, $exception->fields);
        } catch (\Throwable) {
            log_message('error', 'Shopify mapping update failed safely. Correlation ID: ' . $correlationId);
            return $this->mappingError('SHOPIFY_MAPPING_DATABASE_FAILURE', 'The mapping could not be updated.', $correlationId, 500);
        }
    }
    public function searchMedicines(): ResponseInterface
    {
        $rows = $this->operations->searchMedicines((string) $this->request->getGet('q'));
        $medicines = array_map(static fn (array $medicine): array => [
            'id' => (int) ($medicine['id'] ?? 0),
            'name' => (string) ($medicine['medicine_name'] ?? ''),
            'strength' => (string) ($medicine['strength'] ?? ''),
            'unit' => (string) ($medicine['unit'] ?? ''),
        ], $rows);
        log_message('debug', 'Shopify mapping medicine response count: ' . count($medicines));
        return $this->response->setJSON(['status' => true, 'data' => ['medicines' => $medicines]]);
    }
    public function syncProducts(): ResponseInterface
    {
        try {
            return $this->response->setJSON(['status' => true, 'data' => $this->operations->syncProducts(), 'csrf' => ['name' => csrf_token(), 'hash' => csrf_hash()]]);
        } catch (ShopifyProductSyncException $exception) {
            $status = $exception->category === 'SHOPIFY_RATE_LIMITED' ? 429 : 422;
            return $this->response->setStatusCode($status)->setJSON(['status' => false, 'error_category' => $exception->category, 'msg' => $exception->category, 'csrf' => ['name' => csrf_token(), 'hash' => csrf_hash()]]);
        } catch (\Throwable) {
            log_message('error', 'Shopify product catalogue sync failed safely.');
            return $this->response->setStatusCode(500)->setJSON(['status' => false, 'error_category' => 'SHOPIFY_PRODUCTS_SYNC_FAILED', 'msg' => 'SHOPIFY_PRODUCTS_SYNC_FAILED', 'csrf' => ['name' => csrf_token(), 'hash' => csrf_hash()]]);
        }
    }
    public function bulkSaveMappings(): ResponseInterface { return $this->run(fn () => $this->operations->bulkSaveMappings($this->request->getPost(), (int) ($this->user['id'] ?? 0)), 'Shopify medicine mappings saved.'); }
    public function ignoreMapping(): ResponseInterface { return $this->run(fn () => $this->operations->ignoreMapping($this->request->getPost(), (int) ($this->user['id'] ?? 0)), 'Shopify product ignored for mapping.'); }
    public function recheckMappings(): ResponseInterface
    {
        $correlationId = bin2hex(random_bytes(16));
        if (! in_array('shopify.order.operations', session('permissions') ?? [], true)) {
            return $this->mappingError('SHOPIFY_MAPPING_PERMISSION_DENIED', 'You are not authorized to recheck Shopify mappings.', $correlationId, 403);
        }
        try {
            $data = $this->operations->recheckMappings($this->request->getPost());
            return $this->response->setJSON([
                'status' => true,
                'msg' => 'Affected orders rechecked.',
                'data' => [
                    'orders_checked' => (int) ($data['orders_checked'] ?? 0),
                    'orders_updated' => (int) ($data['orders_updated'] ?? 0),
                    'lines_checked' => (int) ($data['lines_checked'] ?? 0),
                    'lines_mapped' => (int) ($data['lines_mapped'] ?? 0),
                    'lines_unmapped' => (int) ($data['lines_unmapped'] ?? 0),
                    'errors' => array_values(array_map('strval', (array) ($data['errors'] ?? []))),
                ],
                'correlation_id' => $correlationId,
                'csrf' => ['name' => csrf_token(), 'hash' => csrf_hash()],
            ]);
        } catch (InvalidArgumentException $exception) {
            log_message('warning', 'Shopify mapping recheck validation failed. Correlation ID: ' . $correlationId . ' Exception: ' . get_class($exception));
            return $this->mappingError('SHOPIFY_MAPPING_RECHECK_FAILED', $exception->getMessage(), $correlationId, 422);
        } catch (\Throwable $exception) {
            $code = $this->isDatabaseException($exception) ? 'SHOPIFY_MAPPING_DATABASE_FAILURE' : 'SHOPIFY_MAPPING_RECHECK_FAILED';
            log_message('error', 'Shopify mapping recheck failed. Correlation ID: ' . $correlationId . ' Category: ' . $code . ' Exception: ' . get_class($exception) . ' Code: ' . (string) $exception->getCode());
            $message = $code === 'SHOPIFY_MAPPING_DATABASE_FAILURE'
                ? 'The Shopify mapping database could not be read or updated safely.'
                : 'Affected Shopify orders could not be rechecked safely.';
            return $this->mappingError($code, $message, $correlationId, 500);
        }
    }
    public function toggleMapping(int $id): ResponseInterface { return $this->run(fn () => $this->operations->toggleMapping($id), 'Shopify medicine mapping status updated.'); }

    public function action(int $id): ResponseInterface
    {
        return $this->run(fn () => $this->operations->action($id, trim((string) $this->request->getPost('action')), $this->request->getPost(), (int) ($this->user['id'] ?? 0)), 'Shopify order operation completed.');
    }

    /** @param array<string, string> $fields */
    private function mappingError(string $code, string $message, string $correlationId, int $status, array $fields = []): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON([
            'status' => false,
            'error' => ['code' => $code, 'message' => $message, 'fields' => $fields, 'correlation_id' => $correlationId],
            'correlation_id' => $correlationId,
            'csrf' => ['name' => csrf_token(), 'hash' => csrf_hash()],
        ]);
    }

    private function run(callable $callback, string $message): ResponseInterface
    {
        try { $data = $callback(); return $this->response->setJSON(['status' => true, 'msg' => $message, 'data' => $data, 'csrf' => ['name' => csrf_token(), 'hash' => csrf_hash()]]); }
        catch (InvalidArgumentException $exception) { return $this->response->setStatusCode(422)->setJSON(['status' => false, 'msg' => $exception->getMessage(), 'csrf' => ['name' => csrf_token(), 'hash' => csrf_hash()]]); }
        catch (\Throwable $exception) { log_message('error', 'Shopify order operations action failed safely.'); return $this->response->setStatusCode(500)->setJSON(['status' => false, 'msg' => 'Shopify order operation could not be completed safely.', 'csrf' => ['name' => csrf_token(), 'hash' => csrf_hash()]]); }
    }

    private function isDatabaseException(\Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());
        return str_contains($message, 'unknown column')
            || str_contains($message, 'doesn\'t exist')
            || str_contains($message, 'base table')
            || str_contains($message, 'sqlstate')
            || str_contains($message, 'database')
            || str_contains($message, 'query');
    }
}
