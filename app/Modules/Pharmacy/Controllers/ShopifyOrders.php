<?php

namespace Pharmacy\Controllers;

use App\Services\Shopify\ShopifyOrderOperationsService;
use App\Services\Shopify\ShopifyPrescriptionUploadService;
use App\Services\Shopify\ShopifyPrescriptionPreviewException;
use App\Services\Shopify\ShopifyOrderReviewService;
use CodeIgniter\HTTP\ResponseInterface;
use Pharmacy\Services\PharmacyScope;
use InvalidArgumentException;

final class ShopifyOrders extends \App\Controllers\BaseController
{
    private PharmacyScope $scope;
    private ShopifyOrderOperationsService $operations;
    private ShopifyOrderReviewService $review;

    public function __construct()
    {
        $this->scope = new PharmacyScope();
        $this->operations = new ShopifyOrderOperationsService();
        $this->review = new ShopifyOrderReviewService();
    }

    public function index(): string|ResponseInterface
    {
        $clinicId = $this->clinicId('pharmacy.shopify_orders.view');
        if ($clinicId === null) return redirect()->to(base_url('permission-denied'));

        try {
            $filters = $this->request->getGet();
            $prescriptions = new ShopifyPrescriptionUploadService();
            $userId = (int) (session('user')['id'] ?? 0);
            $imports = $this->operations->queue(is_array($filters) ? $filters : [], $clinicId);
            foreach ($imports as &$import) {
                if (! empty($import['prescription_document_id'])) {
                    $token = $prescriptions->issueAccessToken((int) $import['id'], (int) $import['prescription_document_id'], $clinicId, $userId);
                    $import['prescription_url'] = base_url('pharmacy/shopify-orders/' . (int) $import['id'] . '/prescription?token=' . rawurlencode($token));
                }
            }
            unset($import);
            return view(PHARMACY_VIEW . 'pages/shopify_orders/index', [
                'imports' => $imports,
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
            if (! empty($import['prescription_document_id'])) {
                $token = (new ShopifyPrescriptionUploadService())->issueAccessToken($id, (int) $import['prescription_document_id'], $clinicId, (int) (session('user')['id'] ?? 0));
                $import['prescription_url'] = base_url('pharmacy/shopify-orders/' . $id . '/prescription?token=' . rawurlencode($token));
            }
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
            $token = trim((string) $this->request->getGet('token'));
            $userId = (int) (session('user')['id'] ?? 0);
            if ($import === null) return $this->response->setStatusCode(404)->setBody('PRESCRIPTION_LINKAGE_NOT_FOUND');
            $document = (new ShopifyPrescriptionUploadService())->resolvePharmacyPreview($token, $id, $clinicId, $userId, $import);
            $this->scope->audit('pharmacy.shopify_prescription_viewed', ['import_id' => $id, 'document_id' => (int) $document['id'], 'user_id' => $userId]);
            $temporaryUrl = (new ShopifyPrescriptionUploadService())->temporaryUrl($document);
            return $this->response->setStatusCode(302)->setHeader('Location', $temporaryUrl);
        } catch (ShopifyPrescriptionPreviewException $exception) {
            $status = $exception->category === 'PRESCRIPTION_ACCESS_DENIED' ? 403 : 404;
            return $this->response->setStatusCode($status)->setBody($exception->category);
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

    public function reviewLine(): ResponseInterface
    {
        $clinicId = $this->clinicId('pharmacy.shopify_orders.manage');
        if ($clinicId === null) return $this->json(false, 'Shopify order management is not permitted.', 403);
        try {
            $data = $this->review->reviewLine((int) $this->request->getPost('import_id'), (int) $this->request->getPost('line_id'), (string) $this->request->getPost('action'), $this->request->getPost(), (int) (session('user')['id'] ?? 0), $clinicId);
            return $this->json(true, 'Medicine line review saved.', 200, $data);
        } catch (InvalidArgumentException $e) { return $this->json(false, $e->getMessage(), 422); }
        catch (\Throwable $e) { log_message('error', 'Shopify line review failed: ' . get_class($e)); return $this->json(false, 'Medicine line review could not be saved safely.', 500); }
    }

    public function reviewOrder(): ResponseInterface
    {
        $clinicId = $this->clinicId('pharmacy.shopify_orders.manage');
        if ($clinicId === null) return $this->json(false, 'Shopify order management is not permitted.', 403);
        try {
            $data = $this->review->reviewOrder((int) $this->request->getPost('import_id'), (string) $this->request->getPost('action'), $this->request->getPost(), (int) (session('user')['id'] ?? 0), $clinicId);
            return $this->json(true, 'Shopify order review saved.', 200, $data);
        } catch (InvalidArgumentException $e) { return $this->json(false, $e->getMessage(), 422); }
        catch (\Throwable $e) { log_message('error', 'Shopify order review failed: ' . get_class($e)); return $this->json(false, 'Shopify order review could not be saved safely.', 500); }
    }

    public function action(int $id): ResponseInterface
    {
        $correlationId = bin2hex(random_bytes(16));
        $clinicId = $this->clinicId('pharmacy.shopify_orders.manage');
        if ($clinicId === null) return $this->shopifyActionError('SHOPIFY_MAPPING_PERMISSION_DENIED', 'You do not have permission to manage Shopify orders.', 403, $correlationId, $id, 'unknown');
        if ($id <= 0) return $this->shopifyActionError('SHOPIFY_ORDER_IMPORT_ID_INVALID', 'The Shopify import ID is invalid.', 422, $correlationId, $id, 'unknown');
        $action = trim((string) $this->request->getPost('action'));
        if (!in_array($action, ['recheck_mapping', 'retry_validation', 'retry_import', 'retry_payment', 'reprocess_webhook', 'quarantine', 'release_quarantine', 'mark_ready', 'reprocess_paid_appointment'], true)) {
            return $this->shopifyActionError('SHOPIFY_ORDER_ACTION_INVALID', 'This Shopify order operation is not available in the Pharmacy workspace.', 422, $correlationId, $id, $action);
        }

        try {
            $data = $this->operations->action($id, $action, $this->request->getPost(), (int) (session('user')['id'] ?? 0), $clinicId, $correlationId);
            $this->scope->audit('pharmacy.shopify_order_action', ['import_id' => $id, 'action' => $action]);
            if (in_array($action, ['retry_validation', 'retry_import', 'retry_payment', 'reprocess_webhook'], true) && array_key_exists('status', $data) && is_bool($data['status'])) {
                $http = !empty($data['status']) ? 200 : 422;
                $message = (string) ($data['message'] ?? $data['msg'] ?? ($data['status'] ? 'Shopify order action completed.' : 'Shopify order action failed.'));
                $code = $data['status'] ? null : (string) ($data['code'] ?? 'SHOPIFY_ORDER_ACTION_FAILED');
                return $this->actionJson((bool) $data['status'], $message, $http, $data, $correlationId, $id, $action, $code);
            }
            return $this->actionJson(true, 'Shopify order operation completed.', 200, $data, $correlationId, $id, $action);
        } catch (InvalidArgumentException $exception) {
            return $this->shopifyActionError('SHOPIFY_ORDER_ACTION_INVALID', $exception->getMessage(), 422, $correlationId, $id, $action);
        } catch (\Throwable $exception) {
            $affectedRows = (int) db_connect()->affectedRows();
            log_message('error', 'Pharmacy Shopify order action failed ' . json_encode(['correlation_id' => $correlationId, 'import_id' => $id, 'action' => $action, 'authenticated_user' => (int) (session('user')['id'] ?? 0), 'http_status' => 500, 'database_affected_rows' => $affectedRows, 'failure_category' => 'SHOPIFY_ORDER_ACTION_DATABASE_FAILURE', 'exception_class' => get_class($exception)], JSON_UNESCAPED_SLASHES));
            return $this->shopifyActionError('SHOPIFY_ORDER_ACTION_DATABASE_FAILURE', 'Shopify order action could not be completed safely.', 500, $correlationId, $id, $action);
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
        $body = ['status' => $status, 'message' => $message, 'msg' => $message, 'data' => $data, 'csrf' => ['name' => csrf_token(), 'hash' => csrf_hash()]];
        return $this->response->setStatusCode($code)->setJSON($body);
    }

    private function actionJson(bool $success, string $message, int $status, array $data, string $correlationId, int $id, string $action, ?string $errorCode = null): ResponseInterface
    {
        $affectedRows = (int) db_connect()->affectedRows();
        log_message($success ? 'info' : 'warning', 'Pharmacy Shopify order action ' . json_encode(['correlation_id' => $correlationId, 'import_id' => $id, 'action' => $action, 'authenticated_user' => (int) (session('user')['id'] ?? 0), 'http_status' => $status, 'database_affected_rows' => $affectedRows, 'failure_category' => $errorCode ?? 'none'], JSON_UNESCAPED_SLASHES));
        $body = ['status' => $success, 'message' => $message, 'msg' => $message, 'data' => $data, 'csrf' => ['name' => csrf_token(), 'hash' => csrf_hash()]];
        if (!$success) $body['error'] = ['code' => $errorCode ?? 'SHOPIFY_ORDER_ACTION_FAILED', 'message' => $message, 'correlation_id' => $correlationId];
        $body['correlation_id'] = $correlationId;
        return $this->response->setStatusCode($status)->setJSON($body);
    }

    private function shopifyActionError(string $errorCode, string $message, int $status, ?string $correlationId = null, int $id = 0, string $action = 'unknown'): ResponseInterface
    {
        $correlationId ??= bin2hex(random_bytes(16));
        return $this->actionJson(false, $message, $status, [], $correlationId, $id, $action, $errorCode);
    }
}
