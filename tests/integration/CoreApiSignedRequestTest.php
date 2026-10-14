<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

final class CoreApiSignedRequestTest extends TestCase
{
    public function testClientStyleSignatureIsAcceptedByPatientRegistration(): void
    {
        $baseUrl = rtrim((string) (getenv('CORE_API_TEST_BASE_URL') ?: 'http://127.0.0.1/rootins/public'), '/');
        $apiKeyId = (string) getenv('CORE_API_TEST_KEY_ID');
        $secret = (string) getenv('CORE_API_TEST_SECRET');
        $clinicId = (int) getenv('CORE_API_TEST_CLINIC_ID');

        if ($apiKeyId === '' || $secret === '' || $clinicId < 1) {
            self::markTestSkipped('Set CORE_API_TEST_KEY_ID, CORE_API_TEST_SECRET, and CORE_API_TEST_CLINIC_ID for the local signed-request regression test.');
        }

        $path = '/api/v1/patients/register';
        $body = json_encode([
            'first_name' => 'CoreApi',
            'last_name' => 'Regression' . random_int(1000, 9999),
            'country_code' => '91',
            'mobile_number' => '9' . random_int(100000000, 999999999),
            'date_of_birth' => '01-01-1990',
            'clinic_id' => $clinicId,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $timestamp = (string) time();
        $canonical = $timestamp . "\nPOST\n" . $path . "\n" . $body;
        $signature = base64_encode(hash_hmac('sha256', $canonical, $secret, true));
        $handle = curl_init($baseUrl . $path);
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-API-Key-Id: ' . $apiKeyId,
                'X-API-Timestamp: ' . $timestamp,
                'X-API-Signature: ' . $signature,
                'Idempotency-Key: regression-' . bin2hex(random_bytes(12)),
            ],
            CURLOPT_POSTFIELDS => $body,
        ]);
        $responseBody = curl_exec($handle);
        $httpStatus = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);
        $response = json_decode((string) $responseBody, true);

        self::assertSame(201, $httpStatus, 'Client-style signed request was not accepted: ' . json_encode($response['error'] ?? ['code' => 'no-error-code'], JSON_UNESCAPED_SLASHES));
        self::assertTrue((bool) ($response['status'] ?? false));
        self::assertNotEmpty($response['data']['patient_reference'] ?? null);
    }
}
