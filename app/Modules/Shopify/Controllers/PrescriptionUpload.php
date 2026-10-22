<?php

namespace App\Modules\Shopify\Controllers;

use App\Controllers\BaseController;
use App\Services\Shopify\ShopifyHandoffService;
use App\Services\Shopify\ShopifyPrescriptionUploadAuthorizationException;
use App\Services\Shopify\ShopifyPrescriptionUploadConflictException;
use App\Services\Shopify\ShopifyPrescriptionUploadNotFoundException;
use App\Services\Shopify\ShopifyPrescriptionUploadService;
use App\Services\Shopify\ShopifyPrescriptionUploadValidationException;
use CodeIgniter\HTTP\ResponseInterface;
use InvalidArgumentException;

/**
 * Shopify prescription document boundary.
 *
 * The browser receives only safe upload metadata. Private R2 keys are resolved
 * server-side and are never included in JSON responses.
 */
final class PrescriptionUpload extends BaseController
{
    public function upload(): ResponseInterface
    {
        if (strtoupper($this->request->getMethod()) !== 'POST') {
            return $this->json(405, false, 'Invalid request method.');
        }

        try {
            $token = trim((string) $this->request->getPost('handoff_token'));
            $handoff = (new ShopifyHandoffService())->verify($token);
            $file = $this->request->getFile('document');

            if ($file === null) {
                return $this->json(422, false, 'A prescription file is required.', [], ['document' => 'A prescription file is required.']);
            }

            $data = (new ShopifyPrescriptionUploadService())->upload(
                $handoff,
                $token,
                $file,
                (string) $this->request->getPost('external_upload_reference'),
                (string) $this->request->getPost('title'),
            );

            return $this->json(200, true, 'Prescription upload received.', $data);
        } catch (ShopifyPrescriptionUploadValidationException $exception) {
            return $this->json(422, false, 'Review the prescription upload fields.', [], $exception->errors);
        } catch (InvalidArgumentException $exception) {
            return $this->json(401, false, 'The patient handoff is invalid or expired.', [], ['handoff_token' => 'The patient handoff is invalid or expired.']);
        } catch (\Throwable $exception) {
            service('audit')->record('shopify.prescription_upload_failed', ['source_type' => 'shopify']);
            log_message('error', 'Shopify prescription upload failed safely.');

            return $this->json(500, false, 'Prescription upload could not be processed safely.');
        }
    }

    public function download(int $documentId)
    {
        try {
            $token = trim((string) $this->request->getGet('handoff_token'));
            $authorized = (new ShopifyPrescriptionUploadService())->authorizeDownload($documentId, $token !== '' ? $token : null);

            // temporaryUrl() delegates to R2StorageLibrary::getTemporaryUrl only after authorization.
            return redirect()->to((new ShopifyPrescriptionUploadService())->temporaryUrl($authorized['document']));
        } catch (ShopifyPrescriptionUploadNotFoundException $exception) {
            return $this->response->setStatusCode(404)->setBody('Prescription upload not found.');
        } catch (ShopifyPrescriptionUploadAuthorizationException $exception) {
            return $this->response->setStatusCode(403)->setBody('Prescription upload access denied.');
        } catch (InvalidArgumentException $exception) {
            return $this->response->setStatusCode(401)->setBody('The patient handoff is invalid or expired.');
        } catch (\Throwable $exception) {
            log_message('error', 'Shopify prescription upload download failed safely.');

            return $this->response->setStatusCode(500)->setBody('Prescription upload could not be loaded safely.');
        }
    }

    public function review(): ResponseInterface
    {
        if (strtoupper($this->request->getMethod()) !== 'POST') {
            return $this->json(405, false, 'Invalid request method.');
        }

        try {
            $data = (new ShopifyPrescriptionUploadService())->review(
                (int) $this->request->getPost('upload_id'),
                (string) $this->request->getPost('decision'),
                (string) $this->request->getPost('reason'),
                filter_var($this->request->getPost('confirmation'), FILTER_VALIDATE_BOOLEAN),
            );

            return $this->json(200, true, 'Prescription upload review updated.', $data);
        } catch (ShopifyPrescriptionUploadValidationException $exception) {
            return $this->json(422, false, 'Review the upload review fields.', [], $exception->errors);
        } catch (ShopifyPrescriptionUploadAuthorizationException $exception) {
            return $this->json(403, false, 'Prescription upload review is not permitted.');
        } catch (ShopifyPrescriptionUploadNotFoundException $exception) {
            return $this->json(404, false, $exception->getMessage());
        } catch (ShopifyPrescriptionUploadConflictException $exception) {
            return $this->json(422, false, $exception->getMessage(), [], ['decision' => $exception->getMessage()]);
        } catch (\Throwable $exception) {
            service('audit')->record('shopify.prescription_upload_review_failed', ['source_type' => 'shopify']);
            log_message('error', 'Shopify prescription upload review failed safely.');

            return $this->json(500, false, 'Prescription upload review could not be processed safely.');
        }
    }

    /** @param array<string, mixed> $data @param array<string, string> $errors */
    private function json(int $status, bool $success, string $message, array $data = [], array $errors = []): ResponseInterface
    {
        $body = [
            'status' => $success,
            'msg' => $message,
        ];

        if ($data !== []) {
            $body['data'] = $data;
        }

        if ($errors !== []) {
            $body['errors'] = $errors;
        }

        return $this->response->setStatusCode($status)->setJSON($body);
    }
}
