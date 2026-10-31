<?php

namespace App\Modules\Shopify\Controllers;

use App\Services\Shopify\ShopifyHairAnalysisService;
use App\Services\Shopify\ShopifyProxyService;
use App\Services\Shopify\ShopifyProxyAuthenticationException;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\HTTP\ResponseInterface;
use InvalidArgumentException;
use RuntimeException;

final class HairAnalysis extends \App\Controllers\BaseController
{
    public function submit(): ResponseInterface
    {
        try {
            $key = trim($this->request->getHeaderLine('Idempotency-Key'));
            if ($key === '') throw new InvalidArgumentException('A valid Idempotency-Key header is required.');
            $query = $this->request->getGet();
            $query = is_array($query) ? $query : [];
            $rawQuery = (string) $this->request->getUri()->getQuery();
            $context = (new ShopifyProxyService())->verify('hair_analysis.submit', $query, $rawQuery);
            $files = $this->files();
            $data = (new ShopifyHairAnalysisService())->submit($context, $this->request->getPost(), $files, $key);
            return $this->response->setJSON(['status' => true, 'data' => $data]);
        } catch (ShopifyProxyAuthenticationException $exception) {
            return $this->response->setStatusCode($exception->httpStatus)->setJSON(['status' => false, 'code' => $exception->errorCode, 'message' => $exception->getMessage()]);
        } catch (InvalidArgumentException $exception) {
            return $this->response->setStatusCode(422)->setJSON(['status' => false, 'code' => 'SHOPIFY_HAIR_ANALYSIS_INVALID_REQUEST', 'message' => $exception->getMessage()]);
        } catch (RuntimeException $exception) {
            $code = $exception->getMessage();
            $status = in_array($code, ['SHOPIFY_HAIR_ANALYSIS_NOT_FOUND'], true) ? 404 : 503;
            return $this->response->setStatusCode($status)->setJSON(['status' => false, 'code' => $code, 'message' => 'Hair analysis could not be processed safely.']);
        } catch (\Throwable $exception) {
            log_message('error', 'Shopify hair analysis boundary failure ' . json_encode(['exception_class' => get_class($exception)], JSON_UNESCAPED_SLASHES));
            return $this->response->setStatusCode(503)->setJSON(['status' => false, 'code' => 'SHOPIFY_HAIR_ANALYSIS_UNAVAILABLE', 'message' => 'Hair analysis is temporarily unavailable.']);
        }
    }

    public function status(): ResponseInterface
    {
        try {
            $query = $this->request->getGet();
            $query = is_array($query) ? $query : [];
            $context = (new ShopifyProxyService())->verify('hair_analysis.status', $query, (string) $this->request->getUri()->getQuery());
            $reference = trim((string) ($query['reference'] ?? ''));
            if ($reference === '') throw new InvalidArgumentException('A hair analysis reference is required.');
            return $this->response->setJSON(['status' => true, 'data' => (new ShopifyHairAnalysisService())->status($context, $reference)]);
        } catch (ShopifyProxyAuthenticationException $exception) {
            return $this->response->setStatusCode($exception->httpStatus)->setJSON(['status' => false, 'code' => $exception->errorCode, 'message' => $exception->getMessage()]);
        } catch (InvalidArgumentException $exception) {
            return $this->response->setStatusCode(422)->setJSON(['status' => false, 'code' => 'SHOPIFY_HAIR_ANALYSIS_INVALID_REQUEST', 'message' => $exception->getMessage()]);
        } catch (RuntimeException $exception) {
            $code = $exception->getMessage();
            return $this->response->setStatusCode($code === 'SHOPIFY_HAIR_ANALYSIS_NOT_FOUND' ? 404 : 503)->setJSON(['status' => false, 'code' => $code]);
        } catch (\Throwable $exception) {
            log_message('error', 'Shopify hair analysis status failure ' . get_class($exception));
            return $this->response->setStatusCode(503)->setJSON(['status' => false, 'code' => 'SHOPIFY_HAIR_ANALYSIS_UNAVAILABLE']);
        }
    }

    /** @return list<UploadedFile> */
    private function files(): array
    {
        $files = $this->request->getFileMultiple('photos');
        if (! is_array($files) || $files === []) {
            $files = [];
            foreach (['front', 'top', 'crown', 'left', 'right', 'back'] as $angle) {
                $file = $this->request->getFile('photo_' . $angle);
                if ($file instanceof UploadedFile) $files[] = $file;
            }
        }
        return array_values(array_filter($files, static fn ($file): bool => $file instanceof UploadedFile));
    }
}
