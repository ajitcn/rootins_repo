<?php

namespace App\Modules\Shopify\Controllers;

use App\Services\Shopify\ShopifyHairAnalysisService;
use App\Services\Shopify\ShopifyProxyAuthenticationException;
use App\Services\Shopify\ShopifyProxyService;
use App\Services\Shopify\ShopifySkinAnalysisService;
use CodeIgniter\HTTP\ResponseInterface;
use InvalidArgumentException;
use RuntimeException;

final class AiAnalysis extends \App\Controllers\BaseController
{
    public function status(): ResponseInterface
    {
        $query = $this->request->getGet();
        $query = is_array($query) ? $query : [];
        $reference = trim((string) ($query['reference'] ?? ''));

        try {
            if ($reference === '') {
                throw new InvalidArgumentException('An AI analysis reference is required.');
            }

            $type = str_starts_with(strtoupper($reference), 'HAI-') ? 'hair' : (str_starts_with(strtoupper($reference), 'SAI-') ? 'skin' : '');
            if ($type === '') {
                throw new InvalidArgumentException('Use a valid hair or skin analysis reference.');
            }

            $operation = $type . '_analysis.status';
            $context = (new ShopifyProxyService())->verify($operation, $query, (string) $this->request->getUri()->getQuery());
            $data = $type === 'hair'
                ? (new ShopifyHairAnalysisService())->status($context, $reference)
                : (new ShopifySkinAnalysisService())->status($context, $reference);

            return $this->response->setJSON(['status' => true, 'data' => $data]);
        } catch (ShopifyProxyAuthenticationException $exception) {
            return $this->response->setStatusCode($exception->httpStatus)->setJSON(['status' => false, 'code' => $exception->errorCode, 'message' => $exception->getMessage()]);
        } catch (InvalidArgumentException $exception) {
            return $this->response->setStatusCode(422)->setJSON(['status' => false, 'code' => 'SHOPIFY_AI_ANALYSIS_INVALID_REQUEST', 'message' => $exception->getMessage()]);
        } catch (RuntimeException $exception) {
            $code = $exception->getMessage();
            $notFound = in_array($code, ['SHOPIFY_HAIR_ANALYSIS_NOT_FOUND', 'SHOPIFY_SKIN_ANALYSIS_NOT_FOUND'], true);
            return $this->response->setStatusCode($notFound ? 404 : 503)->setJSON(['status' => false, 'code' => $code]);
        } catch (\Throwable $exception) {
            log_message('error', 'Shopify AI analysis status failure ' . json_encode([
                'exception_class' => get_class($exception),
                'exception_message' => preg_replace('/[^A-Za-z0-9_ .:-]/', '', $exception->getMessage()),
            ], JSON_UNESCAPED_SLASHES));
            return $this->response->setStatusCode(503)->setJSON(['status' => false, 'code' => 'SHOPIFY_AI_ANALYSIS_UNAVAILABLE']);
        }
    }
}
