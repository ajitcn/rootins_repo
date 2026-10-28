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
        try {
            $query = $this->request->getGet();
            $query = is_array($query) ? $query : [];
            $context = (new ShopifyProxyService())->verify('prescriptions.cart_pushed', $query, (string) $this->request->getUri()->getQuery());
            $payload = $this->request->getJSON(true);
            $payload = is_array($payload) ? $payload : [];
            $data = (new ShopifyProxyService())->execute('prescriptions.cart_pushed', $context, $payload);
            return $this->response->setJSON(['status' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            log_message('warning', 'Shopify prescription cart acknowledgement denied: ' . get_class($e));
            $status = (int) ($e->httpStatus ?? 422);
            $messageCode = (string) $e->getMessage();
            $code = isset($e->errorCode) ? (string) $e->errorCode : (str_starts_with($messageCode, 'SHOPIFY_') ? $messageCode : 'SHOPIFY_PRESCRIPTION_CART_PUSH_FAILED');
            return $this->response->setStatusCode($status >= 400 && $status < 600 ? $status : 422)->setJSON(['status' => false, 'code' => $code]);
        }
    }
}
