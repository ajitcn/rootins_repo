<?php

namespace App\Filters;

use App\Modules\CoreApi\Services\CoreApiAuthenticationService;
use App\Modules\CoreApi\Services\CoreApiException;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

final class CoreApiAuthentication implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! $request instanceof \CodeIgniter\HTTP\IncomingRequest) {
            return service('response')->setStatusCode(401)->setJSON(['status' => false, 'error' => ['code' => 'API_AUTH_REQUIRED', 'message' => 'API authentication is required.']]);
        }

        try {
            (new CoreApiAuthenticationService())->authenticate($request);
        } catch (CoreApiException $exception) {
            service('audit')->record('core_api.authentication_denied', ['error_code' => $exception->errorCode]);

            $response = service('response')->setStatusCode($exception->httpStatus);
            if ($exception->retryAfter !== null) {
                $response->setHeader('Retry-After', (string) $exception->retryAfter);
            }

            $error = ['code' => $exception->errorCode, 'message' => $exception->getMessage()];
            if ($exception->retryAfter !== null) {
                $error['retry_after'] = $exception->retryAfter;
            }

            return $response->setJSON(['status' => false, 'error' => $error]);
        }

        if ($request->getMethod(true) === 'POST' && trim($request->getHeaderLine('Idempotency-Key')) === '') {
            return service('response')->setStatusCode(422)->setJSON(['status' => false, 'error' => ['code' => 'IDEMPOTENCY_KEY_REQUIRED', 'message' => 'A valid Idempotency-Key header is required.']]);
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
    }
}
