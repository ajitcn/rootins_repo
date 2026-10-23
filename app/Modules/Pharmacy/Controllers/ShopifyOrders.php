<?php

namespace Pharmacy\Controllers;

use App\Services\Shopify\ShopifyOrderOperationsService;
use App\Services\Shopify\ShopifyPrescriptionUploadService;
use CodeIgniter\HTTP\ResponseInterface;
use Pharmacy\Services\PharmacyScope;
use InvalidArgumentException;

final class ShopifyOrders extends \App\Controllers\BaseController
{
    private PharmacyScope $scope;
    private ShopifyOrderOperationsService $operations;

    public function __construct()
    {
        $this->scope = new PharmacyScope();
        $this->operations = new ShopifyOrderOperationsService();
    }

    public function index(): string|ResponseInterface
    {
        $clinicId = $this->clinicId('pharmacy.shopify_orders.view');
        if ($clinicId === null) return redirect()->to(base_url('permission-denied'));

        try {
            $filters = $this->request->getGet();
            return view(PHARMACY_VIEW . 'pages/shopify_orders/index', [
                'imports' => $this->operations->queue(is_array($filters) ? $filters : [], $clinicId),
                'counters' => $this->operations->counters($clinicId),
                'filters' => is_array($filters) ? $filters : [],
                'canManage' => $this->scope->can('pharmacy.shopify_orders.manage'),
            ]);
        } catch (\Throwable $exception) {
            log_message('error', 'Pharmacy Shopify order queue failed: ' . $exception->getMessage());
            return $this->response->setStatusCode(500)->setBody('Shopify orders could not be loaded safely.');
        }
    }

    public function detail(int $id): string|ResponseInterface
    {
        $clinicId = $this->clinicId('pharmacy.shopify_orders.view');
        if ($clinicId === null) return $this->response->setStatusCode(403)->setBody('Shopify order access denied.');

        try {
            $import = $this->operations->detail($id, $clinicId);
            if ($import === null) return $this->response->setStatusCode(404)->setBody('Shopify order was not found.');
            return view(PHARMACY_VIEW . 'pages/shopify_orders/detail', [
                'import' => $import,
                'canManage' => $this->scope->can('pharmacy.shopify_orders.manage'),
            ]);
        } catch (\Throwable $exception) {
            log_message('error', 'Pharmacy Shopify order detail failed: ' . $exception->getMessage());
            return $this->response->setStatusCode(500)->setBody('Shopify order details could not be loaded safely.');
        }
    }

    public function detailJson(int $id): ResponseInterface
    {
        $clinicId = $this->clinicId('pharmacy.shopify_orders.view');
        if ($clinicId === null) return $this->json(false, 'Shopify order access denied.', 403);

        try {
            $import = $this->operations->detail($id, $clinicId);
            if ($import === null) return $this->json(false, 'Shopify order was not found.', 404);
            return $this->json(true, 'Shopify order loaded.', 200, ['import' => $import]);
        } catch (\Throwable $exception) {
            log_message('error', 'Pharmacy Shopify order JSON detail failed: ' . $exception->getMessage());
            return $this->json(false, 'Shopify order details could not be loaded safely.', 500);
        }
    }

    public function viewPrescription(int $id): ResponseInterface
    {
        $clinicId = $this->clinicId('pharmacy.shopify_orders.view');
        if ($clinicId === null) return $this->response->setStatusCode(403)->setBody('Prescription access denied.');

        try {
            $import = $this->operations->detail($id, $clinicId);
            $documentId = (int) ($import['prescription_document_id'] ?? 0);
            $document = $documentId > 0 ? (new ShopifyPrescriptionUploadService())->find($documentId) : null;
            if ($import === null || $document === null || (int) ($document['clinic_id'] ?? 0) !== $clinicId) {
                return $this->response->setStatusCode(404)->setBody('Prescription was not found.');
            }
            $this->scope->audit('pharmacy.shopify_prescription_viewed', ['import_id' => $id, 'document_id' => $documentId]);
            return redirect()->to((new ShopifyPrescriptionUploadService())->temporaryUrl($document, 5));
        } catch (\Throwable) {
            return $this->response->setStatusCode(503)->setBody('Prescription could not be loaded safely.');
        }
    }

    public function reviewPrescription(): ResponseInterface
    {
        $clinicId = $this->clinicId('pharmacy.shopify_orders.manage');
        if ($clinicId === null) return $this->json(false, 'Shopify order management is not permitted.', 403);

        $documentId = (int) $this->request->getPost('upload_id');
        $document = $documentId > 0 ? (new ShopifyPrescriptionUploadService())->find($documentId) : null;
        if ($document === null || (int) ($document['clinic_id'] ?? 0) !== $clinicId) return $this->json(false, 'Prescription was not found.', 404);

        try {
            $data = (new ShopifyPrescriptionUploadService())->review(
                $documentId,
                (string) $this->request->getPost('decision'),
                (string) $this->request->getPost('reason'),
                filter_var($this->request->getPost('confirmation'), FILTER_VALIDATE_BOOLEAN),
            );
            return $this->json(true, 'Prescription review updated.', 200, $data);
        } catch (\Throwable $exception) {
            $message = $exception instanceof InvalidArgumentException ? $exception->getMessage() : 'Prescription review could not be processed safely.';
            return $this->json(false, $message, 422);
        }
    }

    public function action(int $id): ResponseInterface
    {
        $clinicId = $this->clinicId('pharmacy.shopify_orders.manage');
        if ($clinicId === null) return $this->shopifyActionError('SHOPIFY_MAPPING_PERMISSION_DENIED', 'You do not have permission to recheck mappings.', 403);
        $action = trim((string) $this->request->getPost('action'));
        if (!in_array($action, ['recheck_mapping', 'retry_validation', 'retry_import', 'retry_payment', 'reprocess_webhook', 'quarantine', 'release_quarantine', 'mark_ready', 'reprocess_paid_appointment'], true)) {
            return $this->json(false, 'This Shopify order operation is not available in the Pharmacy workspace.', 422);
        }

        try {
            $data = $this->operations->action($id, $action, $this->request->getPost(), (int) (session('user')['id'] ?? 0), $clinicId);
            $this->scope->audit('pharmacy.shopify_order_action', ['import_id' => $id, 'action' => $action]);
            return $this->json(true, 'Shopify order operation completed.', 200, $data);
        } catch (InvalidArgumentException $exception) {
            return $this->shopifyActionError('SHOPIFY_MAPPING_RECHECK_FAILED', $exception->getMessage(), 422);
        } catch (\Throwable $exception) {
            log_message('error', 'Pharmacy Shopify order action failed: ' . $exception->getMessage());
            return $this->shopifyActionError('SHOPIFY_MAPPING_DATABASE_FAILURE', 'Mapping recheck could not be completed.', 500);
        }
    }

    private function clinicId(string $permission): ?int
    {
        $clinicId = $this->scope->clinicId();
        return $clinicId !== null && $this->scope->can($permission) ? $clinicId : null;
    }

    /** @param array<string, mixed> $data */
    private function json(bool $status, string $message, int $code, array $data = []): ResponseInterface
    {
        $body = ['status' => $status, 'msg' => $message, 'csrf' => ['name' => csrf_token(), 'hash' => csrf_hash()]];
        if ($data !== []) $body['data'] = $data;
        return $this->response->setStatusCode($code)->setJSON($body);
    }

    private function shopifyActionError(string $errorCode, string $message, int $status): ResponseInterface
    {
        $correlationId = bin2hex(random_bytes(16));
        log_message('warning', 'Pharmacy Shopify mapping action failed. Code: ' . $errorCode . '. Correlation ID: ' . $correlationId);
        return $this->response->setStatusCode($status)->setJSON([
            'status' => false,
            'msg' => $message,
            'error' => ['code' => $errorCode, 'message' => $message, 'correlation_id' => $correlationId],
            'correlation_id' => $correlationId,
            'csrf' => ['name' => csrf_token(), 'hash' => csrf_hash()],
        ]);
    }
}
