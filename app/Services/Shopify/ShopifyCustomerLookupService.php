<?php

namespace App\Services\Shopify;

use Throwable;

final class ShopifyCustomerLookupService
{
    public function __construct(
        private readonly ?ShopifyConfigurationService $configurationService = null,
        private readonly ?ShopifyClient $client = null,
        private readonly ?ShopifyCustomerIdentityService $identityService = null,
        private readonly ?ShopifyConfiguration $runtimeConfiguration = null,
    ) {
    }

    public static function normalizeCustomerId(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^\d{1,30}$/', $value) === 1) {
            return 'gid://shopify/Customer/' . $value;
        }

        if (preg_match('#^gid://shopify/Customer/(\d{1,30})$#', $value, $matches) === 1) {
            return 'gid://shopify/Customer/' . $matches[1];
        }

        throw new ShopifyCustomerLookupException('SHOPIFY_CUSTOMER_ID_INVALID', 422, 'Enter a valid Shopify customer ID.', null, null);
    }

    /** @return array<string, mixed> */
    public function lookup(string $input): array
    {
        $gid = self::normalizeCustomerId($input);
        $publicId = substr($gid, strrpos($gid, '/') + 1);
        $configuration = null;

        try {
            $configuration = $this->runtimeConfiguration ?? ($this->configurationService ?? new ShopifyConfigurationService())->runtime();
        } catch (ShopifyConfigurationException $exception) {
            throw new ShopifyCustomerLookupException('SHOPIFY_CONFIGURATION_MISSING', 422, 'Shopify configuration is missing.', $publicId, null, $exception);
        }

        $storeDomain = strtolower(trim($configuration->storeDomain()));
        $client = $this->client ?? new ShopifyClient($configuration);

        try {
            $connection = $client->testConnection();
            $scopes = array_values(array_filter(array_map(
                static fn (mixed $scope): string => is_array($scope) ? trim((string) ($scope['handle'] ?? '')) : trim((string) $scope),
                $connection['access_scopes'] ?? [],
            )));
            if (! in_array('read_customers', $scopes, true)) {
                throw new ShopifyCustomerLookupException('SHOPIFY_SCOPE_MISSING', 403, 'The Shopify connection does not have the read_customers scope.', $publicId, $storeDomain);
            }

            $data = $client->graphql(
                'query CustomerLookup($id: ID!) { customer(id: $id) { id firstName lastName email phone state createdAt updatedAt } }',
                ['id' => $gid],
            );
        } catch (ShopifyCustomerLookupException $exception) {
            throw $exception;
        } catch (ShopifyConfigurationException $exception) {
            throw new ShopifyCustomerLookupException('SHOPIFY_CONFIGURATION_MISSING', 422, 'Shopify configuration is missing.', $publicId, $storeDomain, $exception);
        } catch (ShopifyProviderException $exception) {
            throw $this->mapProviderException($exception, $publicId, $storeDomain);
        } catch (\Throwable $exception) {
            throw new ShopifyCustomerLookupException('SHOPIFY_API_UNAVAILABLE', 503, 'Shopify customer details are temporarily unavailable.', $publicId, $storeDomain, $exception);
        }

        $customer = is_array($data['customer'] ?? null) ? $data['customer'] : null;
        if ($customer === null) {
            throw new ShopifyCustomerLookupException('SHOPIFY_CUSTOMER_NOT_FOUND', 404, 'The Shopify customer was not found.', $publicId, $storeDomain);
        }

        $returnedGid = (string) ($customer['id'] ?? '');
        if ($returnedGid !== '' && $returnedGid !== $gid) {
            throw new ShopifyCustomerLookupException('SHOPIFY_STORE_MISMATCH', 409, 'The Shopify customer did not belong to the configured store.', $publicId, $storeDomain);
        }

        $identity = ($this->identityService ?? new ShopifyCustomerIdentityService(configuration: $configuration))->identityMatch($publicId);

        return [
            'shopify_customer_id' => $publicId,
            'first_name' => $this->nullableString($customer['firstName'] ?? null),
            'last_name' => $this->nullableString($customer['lastName'] ?? null),
            'email' => $this->nullableString($customer['email'] ?? null),
            'mobile' => $this->nullableString($customer['phone'] ?? null),
            'state' => $this->nullableString($customer['state'] ?? null),
            'created_at' => $this->nullableString($customer['createdAt'] ?? null),
            'updated_at' => $this->nullableString($customer['updatedAt'] ?? null),
            'store_domain' => $storeDomain,
            'match_status' => $identity['match_status'],
            'linked_patient_reference' => $identity['linked_patient_reference'],
        ];
    }

    private function mapProviderException(ShopifyProviderException $exception, string $publicId, string $storeDomain): ShopifyCustomerLookupException
    {
        return match ($exception->errorCode) {
            'missing_scope' => new ShopifyCustomerLookupException('SHOPIFY_SCOPE_MISSING', 403, 'The Shopify connection does not have the read_customers scope.', $publicId, $storeDomain, $exception),
            'invalid_credentials' => new ShopifyCustomerLookupException('SHOPIFY_AUTHENTICATION_FAILED', 401, 'Shopify credentials were rejected.', $publicId, $storeDomain, $exception),
            'shopify_http_401' => new ShopifyCustomerLookupException('SHOPIFY_TOKEN_REFRESH_FAILED', 502, 'The Shopify access token could not be refreshed.', $publicId, $storeDomain, $exception),
            'store_organization_mismatch', 'shopify_organization_restriction' => new ShopifyCustomerLookupException('SHOPIFY_STORE_MISMATCH', 409, 'The configured Shopify store could not be verified.', $publicId, $storeDomain, $exception),
            'network_timeout', 'network_error' => new ShopifyCustomerLookupException('SHOPIFY_API_UNAVAILABLE', 503, 'Shopify customer details are temporarily unavailable.', $publicId, $storeDomain, $exception),
            default => new ShopifyCustomerLookupException('SHOPIFY_API_UNAVAILABLE', 503, 'Shopify customer details could not be loaded safely.', $publicId, $storeDomain, $exception),
        };
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
