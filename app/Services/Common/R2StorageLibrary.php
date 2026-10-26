<?php

namespace App\Services\Common;

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

class R2StorageLibrary
{
    protected S3Client $client;

    protected string $bucket;

    public function __construct()
    {
        $this->bucket = env('R2_BUCKET');
        $this->client = new S3Client(['version' => 'latest', 'region' => 'auto', 'endpoint' => env('R2_ENDPOINT'), 'credentials' => ['key' => env('R2_ACCESS_KEY'), 'secret' => env('R2_SECRET_KEY')], 'use_path_style_endpoint' => true]);
    }

    /**
     * Upload from local file path.
     */
    public function upload(string $key, string $filePath)
    {
        return $this->client->putObject(['Bucket' => $this->bucket, 'Key' => $key, 'SourceFile' => $filePath]);
    }

    /**
     * Upload CI4 UploadedFile directly.
     */
    public function uploadFile(string $key, $file)
    {
        return $this->client->putObject(['Bucket' => $this->bucket, 'Key' => $key, 'SourceFile' => $file->getTempName(), 'ContentType' => $file->getMimeType()]);
    }

    /**
     * Delete file from R2.
     */
    public function delete(string $key)
    {
        return $this->client->deleteObject(['Bucket' => $this->bucket, 'Key' => $key]);
    }

    /**
     * Get a short-lived URL for a stored object.
     *
     * Document callers historically used this method name. Keep the method
     * for compatibility, but never turn private application records into
     * permanent public object URLs.
     */
    public function getUrl(string $key): string
    {
        return $this->getTemporaryUrl($key, 10);
    }

    /**
     * Generate a short-lived private download URL for an authorized caller.
     */
    public function getTemporaryUrl(string $key, int $minutes = 10, ?string $contentType = null, ?string $contentDisposition = null): string
    {
        $parameters = ['Bucket' => $this->bucket, 'Key' => $this->normalizeKey($key)];

        if ($contentType !== null && $contentType !== '') {
            $parameters['ResponseContentType'] = $contentType;
        }

        if ($contentDisposition !== null && $contentDisposition !== '') {
            $parameters['ResponseContentDisposition'] = $contentDisposition;
        }

        $command = $this->client->getCommand('GetObject', $parameters);

        return (string) $this->client->createPresignedRequest($command, '+' . max(1, $minutes) . ' minutes')->getUri();
    }

    /** Read a private object for an already-authorized application response. */
    public function getObject(string $key): array
    {
        return $this->client->getObject(['Bucket' => $this->bucket, 'Key' => $this->normalizeKey($key)]);
    }

    public function objectExists(string $key): bool
    {
        try {
            $this->client->headObject(['Bucket' => $this->bucket, 'Key' => $this->normalizeKey($key)]);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function normalizeKey(string $key): string
    {
        $key = trim($key);
        if (preg_match('#^https?://#i', $key) === 1) $key = (string) parse_url($key, PHP_URL_PATH);
        $key = ltrim($key, '/');
        $bucket = trim($this->bucket, '/');
        if ($bucket !== '' && str_starts_with($key, $bucket . '/')) $key = substr($key, strlen($bucket) + 1);
        return ltrim($key, '/');
    }
}
