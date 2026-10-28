<?php

namespace App\Modules\Shopify\Controllers;

use App\Controllers\BaseController;
use App\Services\Shopify\ShopifyProxyService;
use CodeIgniter\HTTP\ResponseInterface;

final class ActivePrescriptions extends BaseController
{
    public function show(): ResponseInterface
    {
        try {
            $query = $this->request->getGet();
            $query = is_array($query) ? $query : [];
            $context = (new ShopifyProxyService())->verify('prescriptions.active', $query, (string) $this->request->getUri()->getQuery());
            $data = (new ShopifyProxyService())->execute('prescriptions.active', $context, []);
            return $this->response->setJSON(['status' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            log_message('warning', 'Shopify active prescription request denied: ' . get_class($e));
            $status = (int) ($e->httpStatus ?? 401);
            return $this->response->setStatusCode($status >= 400 && $status < 600 ? $status : 401)->setJSON(['status' => false, 'code' => (string) ($e->errorCode ?? 'SHOPIFY_PROXY_AUTHENTICATION_FAILED')]);
        }
    }

    public function cartPushed(): ResponseInterface
    {
        return $this->cartStatus();
    }

    public function cartStatus(): ResponseInterface
    {
        try {
            $accept = strtolower($this->request->getHeaderLine('Accept'));
            $contentType = strtolower($this->request->getHeaderLine('Content-Type'));
            $idempotencyKey = trim($this->request->getHeaderLine('Idempotency-Key'));
            if (! str_contains($accept, 'application/json') || ! str_contains($contentType, 'application/json')) {
                throw new \RuntimeException('SHOPIFY_PRESCRIPTION_CART_STATUS_HEADERS_INVALID');
            }
            if ($idempotencyKey === '') {
                throw new \RuntimeException('IDEMPOTENCY_KEY_REQUIRED');
            }
            if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $idempotencyKey) !== 1) {
                throw new \RuntimeException('IDEMPOTENCY_KEY_REQUIRED');
            }
            $query = $this->request->getGet();
            $query = is_array($query) ? $query : [];
            $context = (new ShopifyProxyService())->verify('prescriptions.cart_status', $query, (string) $this->request->getUri()->getQuery());
            $payload = $this->request->getJSON(true);
            $payload = is_array($payload) ? $payload : [];
            $payload['_idempotency_key'] = $idempotencyKey;
            $data = (new ShopifyProxyService())->execute('prescriptions.cart_status', $context, $payload);
            return $this->response->setJSON(['status' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            log_message('warning', 'Shopify prescription cart acknowledgement denied: ' . get_class($e));
            $status = (int) ($e->httpStatus ?? 422);
            $messageCode = (string) $e->getMessage();
            $code = isset($e->errorCode) ? (string) $e->errorCode : (in_array($messageCode, ['IDEMPOTENCY_KEY_REQUIRED', 'PRESCRIPTION_NOT_FOUND', 'PRESCRIPTION_NOT_AVAILABLE', 'PRESCRIPTION_EXPIRED', 'PRESCRIPTION_VARIANT_INVALID', 'PRESCRIPTION_QUANTITY_INVALID'], true) ? $messageCode : 'SHOPIFY_PRESCRIPTION_CART_PUSH_FAILED');
            return $this->response->setStatusCode($status >= 400 && $status < 600 ? $status : 422)->setJSON(['status' => false, 'code' => $code]);
        }
    }
}
