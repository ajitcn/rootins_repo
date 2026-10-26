<?php

namespace App\Modules\Shopify\Controllers;

use App\Controllers\BaseController;
use App\Services\Shopify\ShopifyProxyService;
use CodeIgniter\HTTP\ResponseInterface;

final class CustomerOrderStatus extends BaseController
{
    public function show(): ResponseInterface
    {
        try {
            $query = $this->request->getGet();
            $context = (new ShopifyProxyService())->verify('orders.status', is_array($query) ? $query : [], (string) $this->request->getUri()->getQuery());
            $store = (string) ($context['store_domain'] ?? '');
            $customer = (string) ($context['customer_id'] ?? '');
            $orderId = trim((string) ($query['order_id'] ?? ''));
            if ($orderId === '') return $this->response->setStatusCode(422)->setJSON(['status' => false, 'code' => 'SHOPIFY_ORDER_SCOPE_INVALID']);
            $import = db_connect()->table('shopify_order_imports')->where(['store_domain' => $store, 'shopify_customer_id' => $customer, 'shopify_order_id' => $orderId])->get(1)->getRowArray();
            if ($import === null) return $this->response->setStatusCode(404)->setJSON(['status' => false, 'code' => 'SHOPIFY_ORDER_SCOPE_INVALID']);
            $lines = db_connect()->table('shopify_order_import_lines')->select('title_snapshot, quantity, review_status, status, review_reason')->where('import_id', (int) $import['id'])->get()->getResultArray();
            return $this->response->setJSON(['status' => true, 'data' => ['order_status' => $import['review_status'] ?? $import['status'], 'prescription_status' => $import['prescription_status'], 'refund_status' => $import['payment_status'], 'fulfillment_status' => $import['fulfillment_status'], 'lines' => $lines]]);
        } catch (\Throwable $e) { log_message('warning', 'Shopify customer order status denied: ' . get_class($e)); return $this->response->setStatusCode(401)->setJSON(['status' => false, 'code' => 'SHOPIFY_ORDER_SCOPE_INVALID']); }
    }
}
