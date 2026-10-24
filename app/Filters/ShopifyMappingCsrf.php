<?php

namespace App\Filters;

use CodeIgniter\Filters\CSRF;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Security\Exceptions\SecurityException;

/** Returns the Admin mapping API's safe JSON shape for framework CSRF failures. */
final class ShopifyMappingCsrf extends CSRF
{
    public function before(RequestInterface $request, $arguments = null)
    {
        try {
            return parent::before($request, $arguments);
        } catch (SecurityException $exception) {
            $path = $request->getUri()->getPath();
            $isShopifyMappingAction = $path !== '' && (
                str_contains($path, '/admin/integrations/shopify/order-mappings/')
                || str_contains($path, '/admin/integrations/shopify/order-operations/')
                || str_contains($path, '/pharmacy/shopify-orders/')
            );
            if ($isShopifyMappingAction) {
                $correlationId = bin2hex(random_bytes(16));
                log_message('warning', 'Shopify mapping CSRF validation failed. Correlation ID: ' . $correlationId);

                return service('response')->setStatusCode(403)->setJSON([
                    'status' => false,
                    'message' => 'Your session expired. Refresh the page and try again.',
                    'msg' => 'Your session expired. Refresh the page and try again.',
                    'data' => [],
                    'error' => [
                        'code' => 'SHOPIFY_MAPPING_CSRF_INVALID',
                        'message' => 'Your session expired. Refresh the page and try again.',
                        'correlation_id' => $correlationId,
                    ],
                    'correlation_id' => $correlationId,
                    'csrf' => ['name' => csrf_token(), 'hash' => csrf_hash()],
                ]);
            }

            throw $exception;
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return parent::after($request, $response, $arguments);
    }
}
