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
     * Upload from local file path
     */
    public function upload(string $key, string $filePath)
    {
        return $this->client->putObject(['Bucket' => $this->bucket, 'Key' => $key, 'SourceFile' => $filePath]);
    }
    /**
     * Upload CI4 UploadedFile directly
     */
    public function uploadFile(string $key, $file)
    {
        return $this->client->putObject(['Bucket' => $this->bucket, 'Key' => $key, 'SourceFile' => $file->getTempName(), 'ContentType' => $file->getMimeType()]);
    }
    /**
     * Delete file from R2
     */
    public function delete(string $key)
    {
        return $this->client->deleteObject(['Bucket' => $this->bucket, 'Key' => $key]);
    }
    /**
     * Get public URL
     */
    public function getUrl(string $key): string
    {
        return rtrim(env('R2_PUBLIC_URL'), '/') . '/' . ltrim($key, '/');
    }
    /** Generate a short-lived private download URL for an authorized caller. */
    public function getTemporaryUrl(string $key, int $minutes = 10): string
    {
        $command = $this->client->getCommand('GetObject', ['Bucket' => $this->bucket, 'Key' => $key]);
        return (string) $this->client->createPresignedRequest($command, '+' . max(1, $minutes) . ' minutes')->getUri();
    }
}
