<?php

namespace App\Modules\Shopify\Controllers;

use App\Controllers\BaseController;
use App\Modules\CoreApi\Services\CoreApiException;
use App\Services\Shopify\ShopifyOperationIdempotencyService;
use App\Services\Shopify\ShopifyProxyAuthenticationException;
use App\Services\Shopify\ShopifyProxyService;
use CodeIgniter\HTTP\ResponseInterface;
use InvalidArgumentException;
use Throwable;

final class Proxy extends BaseController
{
    public function availability(): ResponseInterface { return $this->handle('appointments.availability', false); }
    public function book(): ResponseInterface { return $this->handle('appointments.book', true); }
    public function register(): ResponseInterface { return $this->handle('patients.register', true); }
    public function upload(): ResponseInterface { return $this->handle('prescriptions.upload', true); }

    private function handle(string $operation, bool $write): ResponseInterface
    {
        $key = trim($this->request->getHeaderLine('Idempotency-Key'));
        if ($write && ($key === '' || strlen($key) > 191)) return $this->json(422, false, 'A valid Idempotency-Key header is required.', [], ['idempotency_key' => 'A valid Idempotency-Key header is required.']);
        $claimed = false;
        try {
            $queryString = (string) $this->request->getUri()->getQuery();
            $query = (new \App\Services\Shopify\ShopifyProxySignatureService())->query($queryString);
            $context = (new ShopifyProxyService())->verify($operation, $query, $queryString);
            $payload = strtoupper($this->request->getMethod()) === 'GET' ? $this->request->getGet() : ($this->request->getJSON(true) ?: $this->request->getPost());
            if (! is_array($payload)) $payload = [];
            $idempotency = new ShopifyOperationIdempotencyService();
            if ($write) {
                if (! $idempotency->claim($key, $operation)) return $this->json(409, false, 'This Shopify operation has already been submitted.');
                $claimed = true;
            }
            $data = (new ShopifyProxyService())->execute($operation, $context, $payload, $this->request->getFile('document'));
            if ($claimed) $idempotency->complete($key, hash('sha256', json_encode($data, JSON_UNESCAPED_SLASHES) ?: ''));
            service('audit')->record('shopify.proxy_request_succeeded', ['operation' => $operation, 'store_domain' => $context['store_domain']]);
            return $this->json($write && $operation === 'patients.register' ? 201 : 200, true, 'Shopify proxy operation completed.', $data);
        } catch (CoreApiException $exception) {
            if ($claimed) $idempotency->fail($key, $exception->errorCode);
            if ($exception->retryAfter !== null) $this->response->setHeader('Retry-After', (string) $exception->retryAfter);
            return $this->json($exception->httpStatus, false, $exception->getMessage(), [], $exception->fields, $exception->errorCode);
        } catch (ShopifyProxyAuthenticationException $exception) {
            if ($claimed) $idempotency->fail($key, 'authentication');
            return $this->json($exception->httpStatus, false, $exception->getMessage());
        } catch (InvalidArgumentException $exception) {
            if ($claimed) $idempotency->fail($key, 'validation');
            service('audit')->record('shopify.proxy_request_rejected', ['operation' => $operation, 'reason' => 'validation']);
            return $this->json(401, false, $exception->getMessage());
        } catch (Throwable $exception) {
            if ($claimed) $idempotency->fail($key, 'internal');
            log_message('error', 'Shopify proxy request failed safely operation=' . $operation . ' exception=' . get_class($exception));
            return $this->json(500, false, 'The Shopify proxy request could not be processed safely.');
        }
    }

    private function json(int $status, bool $success, string $message, array $data = [], array $errors = [], string $code = ''): ResponseInterface
    {
        $body = ['status' => $success, 'msg' => $message];
        if ($code !== '') $body['code'] = $code;
        if ($data !== []) $body['data'] = $data;
        if ($errors !== []) $body['errors'] = $errors;
        return $this->response->setStatusCode($status)->setJSON($body);
    }
}
