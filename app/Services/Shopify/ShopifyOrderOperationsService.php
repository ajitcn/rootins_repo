<?php

namespace App\Services\Shopify;

use Admin\Models\MedicineModel;
use Admin\Models\PatientModel;
use App\Modules\Shopify\Models\ShopifyMedicineMappingModel;
use App\Modules\Shopify\Models\ShopifyMappingIgnoreModel;
use App\Modules\Shopify\Models\ShopifyOrderImportLineModel;
use App\Modules\Shopify\Models\ShopifyOrderImportModel;
use App\Modules\Shopify\Models\ShopifyPatientIdentityLinkModel;
use InvalidArgumentException;

/** Shared Shopify order reconciliation workflow for Admin and Pharmacy surfaces. */
final class ShopifyOrderOperationsService
{
    public function __construct(
        private readonly ?ShopifyClient $shopifyClient = null,
        private readonly ?ShopifyConfiguration $shopifyConfiguration = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function syncProducts(): array
    {
        $configuration = $this->shopifyConfiguration ?? new ShopifyConfiguration();
        $store = strtolower(trim($configuration->storeDomain()));
        $startedAt = date('Y-m-d H:i:s');
        $syncId = null;
        $db = db_connect();

        if ($db->tableExists('shopify_product_syncs')) {
            $db->table('shopify_product_syncs')->insert([
                'store_domain' => $store,
                'status' => 'running',
                'started_at' => $startedAt,
            ]);
            $syncId = (int) $db->insertID();
        }

        $counts = [
            'products_fetched' => 0, 'variants_fetched' => 0, 'created' => 0,
            'updated' => 0, 'unchanged' => 0, 'deactivated' => 0,
            'already_mapped' => 0, 'needs_mapping' => 0, 'failed' => 0,
        ];

        try {
            if (! $configuration->isConfigured()) {
                throw new ShopifyConfigurationException('Shopify configuration is missing.', 'missing_configuration');
            }
            $client = $this->shopifyClient ?? new ShopifyClient($configuration);
            $scopes = array_map('strtolower', $client->accessScopes());
            if (! in_array('read_products', $scopes, true)) {
                throw new ShopifyProviderException('The Shopify app does not have the read_products scope.', 403, 'missing_scope', 'permission_denied');
            }

            $query = <<<'GRAPHQL'
query Products($first: Int!, $after: String) {
  products(first: $first, after: $after) {
    nodes {
      id title handle status vendor updatedAt
      featuredImage { url }
      metafield(namespace: "rootins", key: "prescription_required") { value }
      variants(first: 250) {
        nodes { id title sku barcode price updatedAt }
      }
    }
    pageInfo { hasNextPage endCursor }
  }
}
GRAPHQL;

            $cursor = null;
            do {
                $payload = $client->graphql($query, ['first' => 100, 'after' => $cursor]);
                $connection = is_array($payload['products'] ?? null) ? $payload['products'] : [];
                foreach ((array) ($connection['nodes'] ?? []) as $product) {
                    if (! is_array($product) || trim((string) ($product['id'] ?? '')) === '') continue;
                    $counts['products_fetched']++;
                    $productId = (string) $product['id'];
                    $prescription = $this->booleanSnapshot($product['metafield']['value'] ?? null);
                    $productData = [
                        'store_domain' => $store,
                        'shopify_product_id' => $productId,
                        'product_title' => trim((string) ($product['title'] ?? '')) ?: null,
                        'product_handle' => trim((string) ($product['handle'] ?? '')) ?: null,
                        'product_status' => trim((string) ($product['status'] ?? '')) ?: null,
                        'product_image_url' => filter_var((string) ($product['featuredImage']['url'] ?? ''), FILTER_VALIDATE_URL) ? (string) $product['featuredImage']['url'] : null,
                        'prescription_required' => $prescription,
                        'shopify_updated_at' => $this->shopifyDate($product['updatedAt'] ?? null),
                        'is_active' => 1,
                        'last_seen_at' => $startedAt,
                        'updated_at' => $startedAt,
                    ];
                    $this->upsertCatalogue('shopify_product_catalogue', ['store_domain' => $store, 'shopify_product_id' => $productId], $productData, $counts);

                    foreach ((array) ($product['variants']['nodes'] ?? []) as $variant) {
                        if (! is_array($variant) || trim((string) ($variant['id'] ?? '')) === '') continue;
                        $counts['variants_fetched']++;
                        $variantData = [
                            'store_domain' => $store,
                            'shopify_product_id' => $productId,
                            'shopify_variant_id' => (string) $variant['id'],
                            'product_title' => $productData['product_title'],
                            'product_handle' => $productData['product_handle'],
                            'product_status' => $productData['product_status'],
                            'product_image_url' => $productData['product_image_url'],
                            'variant_title' => trim((string) ($variant['title'] ?? '')) ?: null,
                            'sku' => trim((string) ($variant['sku'] ?? '')) ?: null,
                            'barcode' => trim((string) ($variant['barcode'] ?? '')) ?: null,
                            'price_snapshot' => is_numeric($variant['price'] ?? null) ? (float) $variant['price'] : null,
                            'prescription_required' => $prescription,
                            'shopify_updated_at' => $this->shopifyDate($variant['updatedAt'] ?? null),
                            'is_active' => 1,
                            'last_seen_at' => $startedAt,
                            'updated_at' => $startedAt,
                        ];
                        $this->upsertCatalogue('shopify_variant_catalogue', ['store_domain' => $store, 'shopify_product_id' => $productId, 'shopify_variant_id' => (string) $variant['id']], $variantData, $counts);
                    }
                }
                $pageInfo = is_array($connection['pageInfo'] ?? null) ? $connection['pageInfo'] : [];
                $cursor = ! empty($pageInfo['hasNextPage']) ? (string) ($pageInfo['endCursor'] ?? '') : null;
            } while ($cursor !== null && $cursor !== '');

            if ($db->tableExists('shopify_product_catalogue')) {
                $counts['deactivated'] += $db->table('shopify_product_catalogue')->where('store_domain', $store)->where('is_active', 1)->where('last_seen_at <', $startedAt)->update(['is_active' => 0, 'updated_at' => date('Y-m-d H:i:s')]) ? $db->affectedRows() : 0;
            }
            if ($db->tableExists('shopify_variant_catalogue')) {
                $counts['deactivated'] += $db->table('shopify_variant_catalogue')->where('store_domain', $store)->where('is_active', 1)->where('last_seen_at <', $startedAt)->update(['is_active' => 0, 'updated_at' => date('Y-m-d H:i:s')]) ? $db->affectedRows() : 0;
            }
            $counts['already_mapped'] = $db->table('shopify_variant_catalogue v')->join('shopify_medicine_mappings m', 'm.store_domain = v.store_domain AND m.shopify_product_id = v.shopify_product_id AND m.shopify_variant_id = v.shopify_variant_id AND m.status = \'active\'', 'inner')->where('v.store_domain', $store)->where('v.is_active', 1)->countAllResults();
            $counts['needs_mapping'] = max(0, $counts['variants_fetched'] - $counts['already_mapped']);
            $this->finishSync($syncId, $store, 'success', null, $counts);
            service('audit')->record('shopify.product_catalogue_synced', ['store_domain' => $store, 'counts' => $counts]);
            return $counts;
        } catch (ShopifyConfigurationException|ShopifyProviderException $exception) {
            $category = $this->syncErrorCategory($exception);
            $counts['failed'] = 1;
            $this->finishSync($syncId, $store, 'failed', $category, $counts);
            service('audit')->record('shopify.product_catalogue_sync_failed', ['store_domain' => $store, 'error_category' => $category]);
            throw new ShopifyProductSyncException($category, $exception);
        } catch (\Throwable $exception) {
            $counts['failed'] = 1;
            $this->finishSync($syncId, $store, 'failed', 'SHOPIFY_PRODUCTS_SYNC_FAILED', $counts);
            service('audit')->record('shopify.product_catalogue_sync_failed', ['store_domain' => $store, 'error_category' => 'SHOPIFY_PRODUCTS_SYNC_FAILED']);
            throw new ShopifyProductSyncException('SHOPIFY_PRODUCTS_SYNC_FAILED', $exception);
        }
    }

    /** @param array<string, int> $counts */
    private function upsertCatalogue(string $table, array $identity, array $data, array &$counts): void
    {
        $builder = db_connect()->table($table);
        $existing = $builder->where($identity)->get(1)->getRowArray();
        if ($existing === null) {
            $data['created_at'] = $data['updated_at'];
            $builder->insert($data);
            $counts['created']++;
            return;
        }
        $changed = false;
        foreach ($data as $key => $value) if ($key !== 'updated_at' && ($existing[$key] ?? null) != $value) { $changed = true; break; }
        $builder->where('id', (int) $existing['id'])->update($data);
        $counts[$changed ? 'updated' : 'unchanged']++;
    }

    private function finishSync(?int $id, string $store, string $status, ?string $error, array $counts): void
    {
        if ($id === null || ! db_connect()->tableExists('shopify_product_syncs')) return;
        db_connect()->table('shopify_product_syncs')->where('id', $id)->update([
            'status' => $status, 'error_category' => $error, 'products_fetched' => $counts['products_fetched'],
            'variants_fetched' => $counts['variants_fetched'], 'created_count' => $counts['created'], 'updated_count' => $counts['updated'],
            'unchanged_count' => $counts['unchanged'], 'deactivated_count' => $counts['deactivated'], 'already_mapped_count' => $counts['already_mapped'],
            'needs_mapping_count' => $counts['needs_mapping'], 'failed_count' => $counts['failed'], 'completed_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function syncErrorCategory(\Throwable $exception): string
    {
        $code = $exception instanceof ShopifyConfigurationException ? $exception->errorCode : ($exception instanceof ShopifyProviderException ? $exception->errorCode : '');
        return match ($code) {
            'missing_configuration' => 'SHOPIFY_CONFIGURATION_MISSING',
            'invalid_api_version' => 'SHOPIFY_API_VERSION_INVALID',
            'invalid_credentials' => 'SHOPIFY_INVALID_CREDENTIALS',
            'missing_scope' => 'SHOPIFY_PRODUCTS_SCOPE_MISSING',
            'shopify_http_403' => 'SHOPIFY_PRODUCTS_SCOPE_MISSING',
            'shopify_http_404' => 'SHOPIFY_API_VERSION_INVALID',
            'shopify_http_429' => 'SHOPIFY_RATE_LIMITED',
            default => 'SHOPIFY_PRODUCTS_SYNC_FAILED',
        };
    }

    private function shopifyDate(mixed $value): ?string
    {
        $timestamp = is_string($value) ? strtotime($value) : false;
        return $timestamp === false ? null : date('Y-m-d H:i:s', $timestamp);
    }

    private function booleanSnapshot(mixed $value): ?int
    {
        if ($value === null || $value === '') return null;
        return in_array(strtolower((string) $value), ['1', 'true', 'yes'], true) ? 1 : 0;
    }

    /** @return array<string, mixed> */
    public function mappingDashboard(array $filters = []): array
    {
        $db = db_connect();
        $configuration = $this->shopifyConfiguration ?? new ShopifyConfiguration();
        $store = strtolower(trim((string) ($filters['store_domain'] ?? $configuration->storeDomain())));
        $selectedStatus = in_array(($filters['mapping_status'] ?? ''), ['needs', 'mapped', 'conflicts', 'inactive'], true) ? (string) $filters['mapping_status'] : 'needs';
        $allMappings = $this->mappings(array_merge($filters, ['store_domain' => $store]));
        $mappingKey = static fn (array $row): string => (string) $row['store_domain'] . ':' . (string) $row['shopify_variant_id'];
        $duplicateKeys = [];
        foreach ($db->table('shopify_medicine_mappings')->select('store_domain, shopify_variant_id')->groupBy('store_domain, shopify_variant_id')->having('COUNT(*) >', 1)->get()->getResultArray() as $duplicate) {
            $duplicateKeys[(string) $duplicate['store_domain'] . ':' . (string) $duplicate['shopify_variant_id']] = true;
        }
        $ignoredKeys = [];
        if ($db->tableExists('shopify_mapping_ignores')) {
            foreach ($db->table('shopify_mapping_ignores')->select('store_domain, shopify_product_id, shopify_variant_id')->where('store_domain', $store)->get()->getResultArray() as $ignored) {
                $ignoredKeys[(string) $ignored['store_domain'] . ':' . (string) $ignored['shopify_product_id'] . ':' . (string) $ignored['shopify_variant_id']] = true;
            }
        }
        $isIgnored = static fn (array $row): bool => isset($ignoredKeys[(string) $row['store_domain'] . ':' . (string) $row['shopify_product_id'] . ':' . (string) ($row['shopify_variant_id'] ?? '')]);
        $conflictMappings = array_values(array_filter($allMappings, fn (array $row): bool => isset($duplicateKeys[$mappingKey($row)]) && ! $isIgnored($row)));
        $inactiveMappings = array_values(array_filter($allMappings, static fn (array $row): bool => ($row['medicine_status'] ?? 'active') !== 'active' || ($row['status'] ?? '') !== 'active'));
        $mappedRows = array_values(array_filter($allMappings, fn (array $row): bool => ($row['medicine_status'] ?? 'active') === 'active' && ($row['status'] ?? '') === 'active' && ! isset($duplicateKeys[$mappingKey($row)]) && ! $isIgnored($row)));
        $mappedKeys = [];
        foreach ($mappedRows as $row) $mappedKeys[(string) $row['shopify_product_id'] . ':' . (string) ($row['shopify_variant_id'] ?? '')] = true;

        $items = [];
        if ($db->tableExists('shopify_variant_catalogue')) {
            $query = $db->table('shopify_variant_catalogue v')
                ->select('v.*')
                ->where('v.store_domain', $store)
                ->where('v.is_active', 1)
                ->orderBy('v.product_title', 'ASC');
            $this->like($query, 'v.product_title', $filters['product'] ?? '');
            $this->like($query, 'v.variant_title', $filters['variant'] ?? '');
            $this->like($query, 'v.sku', $filters['sku'] ?? '');
            $items = $query->get(500)->getResultArray();
            foreach ($items as &$item) {
                $key = (string) $item['shopify_product_id'] . ':' . (string) $item['shopify_variant_id'];
                $impact = $db->table('shopify_order_import_lines l')
                    ->select('COUNT(DISTINCT l.import_id) AS pending_orders, MIN(i.created_at) AS earliest_order_date, SUM(CASE WHEN i.status IN (\'quarantined\', \'pending_mapping\', \'failed\') THEN 1 ELSE 0 END) AS quarantined_orders')
                    ->join('shopify_order_imports i', 'i.id = l.import_id', 'inner')
                    ->where('i.store_domain', $store)->where('l.shopify_product_id', $item['shopify_product_id'])->where('l.shopify_variant_id', $item['shopify_variant_id'])->get(1)->getRowArray() ?: [];
                $item['pending_orders'] = (int) ($impact['pending_orders'] ?? 0);
                $item['earliest_order_date'] = $impact['earliest_order_date'] ?? null;
                $item['quarantined_orders'] = (int) ($impact['quarantined_orders'] ?? 0);
                $item['key'] = $key;
                $item['shopify_sku'] = (string) ($item['sku'] ?? '');
                $item['shopify_barcode'] = (string) ($item['barcode'] ?? '');
                $item['title_snapshot'] = (string) ($item['product_title'] ?? '');
                $item['image_url'] = (string) ($item['product_image_url'] ?? '');
                $item['prescription_required'] = (bool) $item['prescription_required'];
            }
            unset($item);
        } else {
            $items = $db->table('shopify_order_import_lines l')->select('i.store_domain, l.shopify_product_id, l.shopify_variant_id, l.title_snapshot, l.shopify_sku, l.shopify_barcode, l.product_image_url, COUNT(DISTINCT l.import_id) AS pending_orders, MIN(i.created_at) AS earliest_order_date, SUM(CASE WHEN i.status IN (\'quarantined\', \'pending_mapping\', \'failed\') THEN 1 ELSE 0 END) AS quarantined_orders')->join('shopify_order_imports i', 'i.id = l.import_id', 'inner')->where('i.store_domain', $store)->groupStart()->where('l.medicine_id', null)->orWhere('l.status', 'pending_mapping')->groupEnd()->groupBy('i.store_domain, l.shopify_product_id, l.shopify_variant_id, l.title_snapshot, l.shopify_sku, l.shopify_barcode, l.product_image_url')->orderBy('earliest_order_date', 'ASC')->get(250)->getResultArray();
        }
        $ignoreModel = new ShopifyMappingIgnoreModel();
        foreach ($items as &$item) {
            $item['shopify_product_id'] = (string) ($item['shopify_product_id'] ?? '');
            $item['shopify_variant_id'] = (string) ($item['shopify_variant_id'] ?? '');
            $item['key'] = $item['shopify_product_id'] . ':' . $item['shopify_variant_id'];
            $item['ignored'] = $ignoreModel->where('store_domain', $store)->where('shopify_product_id', $item['shopify_product_id'])->where('shopify_variant_id', $item['shopify_variant_id'])->first() !== null;
            $item['sku'] = (string) ($item['sku'] ?? $item['shopify_sku'] ?? '');
            $item['barcode'] = (string) ($item['shopify_barcode'] ?? '');
            $item['image_url'] = (string) ($item['product_image_url'] ?? '');
            $item['variant_title'] = (string) ($item['variant_title'] ?? '');
            $item['suggestions'] = $this->suggestions((string) ($item['title_snapshot'] ?? ''), (string) ($item['sku'] ?? ''), (string) ($item['barcode'] ?? ''));
        }
        unset($item);
        $items = array_values(array_filter($items, static fn (array $item): bool => empty($item['ignored']) && ! isset($mappedKeys[$item['key']])));

        $total = count($mappedRows) + count(array_filter($items, static fn (array $item): bool => ! isset($mappedKeys[$item['key']])));
        if ($db->tableExists('shopify_product_catalogue')) {
            $total = (int) $db->table('shopify_product_catalogue')->where('store_domain', $store)->where('is_active', 1)->countAllResults();
            $catalogueProducts = $db->table('shopify_product_catalogue')->select('shopify_product_id')->where('store_domain', $store)->where('is_active', 1)->get()->getResultArray();
            $knownProducts = array_fill_keys(array_map(static fn (array $row): string => (string) $row['shopify_product_id'], $catalogueProducts), true);
            foreach ($mappedRows as $mapping) if (! isset($knownProducts[(string) $mapping['shopify_product_id']])) { $knownProducts[(string) $mapping['shopify_product_id']] = true; $total++; }
        }
        $inactive = count($inactiveMappings);
        $conflicts = count($conflictMappings);
        $tabCounts = ['needs' => count($items), 'mapped' => count($mappedRows), 'conflicts' => $conflicts, 'inactive' => $inactive];
        $displayItems = $selectedStatus === 'needs' ? $items : [];
        $displayMappings = match ($selectedStatus) {
            'mapped' => $mappedRows,
            'conflicts' => $conflictMappings,
            'inactive' => $inactiveMappings,
            default => [],
        };

        return [
            'mappings' => $displayMappings,
            'items' => $displayItems,
            'conflicts' => $conflictMappings,
            'inactive_mappings' => $inactiveMappings,
            'active_mapping_status' => $selectedStatus,
            'tab_counts' => $tabCounts,
            'medicines' => $this->activeMedicines(),
            'filters' => $filters,
            'summary' => ['total_products' => $total, 'mapped' => count($mappedRows), 'unmapped' => count($items), 'conflicts' => $conflicts, 'inactive' => $inactive],
            'store_domain' => $store,
            'last_sync' => $db->tableExists('shopify_product_syncs') ? $db->table('shopify_product_syncs')->where('store_domain', $store)->orderBy('id', 'DESC')->get(1)->getRowArray() : null,
        ];
    }

    public function mappings(array $filters = []): array
    {
        $query = db_connect()->table('shopify_medicine_mappings m')
            ->select('m.*, med.medicine_name, med.generic_name, med.strength, med.unit, med.status AS medicine_status, COALESCE(v.prescription_required, p.prescription_required) AS prescription_required')
            ->join('medicines med', 'med.id = m.medicine_id', 'left')
            ->join('shopify_variant_catalogue v', 'v.store_domain = m.store_domain AND v.shopify_variant_id = m.shopify_variant_id', 'left')
            ->join('shopify_product_catalogue p', 'p.store_domain = m.store_domain AND p.shopify_product_id = m.shopify_product_id', 'left')
            ->orderBy('m.updated_at', 'DESC');
        if (($filters['store_domain'] ?? '') !== '') $query->where('m.store_domain', strtolower(trim((string) $filters['store_domain'])));
        $this->like($query, 'm.shopify_product_title', $filters['product'] ?? '');
        $this->like($query, 'm.shopify_variant_title', $filters['variant'] ?? '');
        $this->like($query, 'm.shopify_sku', $filters['sku'] ?? '');
        $this->like($query, 'med.medicine_name', $filters['medicine'] ?? '');
        if (in_array(($filters['status'] ?? ''), ['active', 'inactive'], true)) $query->where('m.status', $filters['status']);

        return $query->get(250)->getResultArray();
    }

    public function mapping(int $id): ?array
    {
        return (new ShopifyMedicineMappingModel())->find($id) ?: null;
    }

    public function saveMapping(array $input, ?int $id = null): array
    {
        $configuration = $this->shopifyConfiguration ?? new ShopifyConfiguration();
        $configuredStore = strtolower(trim($configuration->storeDomain()));
        $store = strtolower(trim((string) ($input['store_domain'] ?? '')));
        $productId = trim((string) ($input['shopify_product_id'] ?? ''));
        $variantId = trim((string) ($input['shopify_variant_id'] ?? ''));
        $medicineId = (int) ($input['medicine_id'] ?? 0);
        $status = strtolower(trim((string) ($input['status'] ?? 'active')));
        $fields = [];
        if ($store === '') $fields['store_domain'] = 'Store domain is required.';
        if ($variantId === '') $fields['shopify_variant_id'] = 'Shopify variant ID is required.';
        if ($medicineId < 1) $fields['medicine_id'] = 'ROOTINS medicine is required.';
        if (! in_array($status, ['active', 'inactive'], true)) $fields['status'] = 'Mapping status is invalid.';
        if ($fields !== []) throw new ShopifyMappingException('SHOPIFY_MAPPING_UPDATE_FAILED', 'The mapping could not be updated.', $fields);
        if ($configuredStore === '' || ! hash_equals($configuredStore, $store)) throw new ShopifyMappingException('SHOPIFY_MAPPING_STORE_MISMATCH', 'The Shopify store does not match the configured store.', ['store_domain' => 'The configured Shopify store is required.']);

        $db = db_connect();
        if (! $db->tableExists('shopify_medicine_mappings')) throw new ShopifyMappingException('SHOPIFY_MAPPING_TABLE_MISSING', 'The Shopify mapping table is unavailable.');
        $requiredColumns = ['store_domain', 'shopify_product_id', 'shopify_variant_id', 'medicine_id', 'status'];
        $missingColumns = array_values(array_filter($requiredColumns, static fn (string $column): bool => ! $db->fieldExists($column, 'shopify_medicine_mappings')));
        if ($missingColumns !== []) throw new ShopifyMappingException('SHOPIFY_MAPPING_COLUMN_MISSING', 'The Shopify mapping schema is incomplete.', ['columns' => implode(', ', $missingColumns)]);
        if (! $db->tableExists('shopify_variant_catalogue')) throw new ShopifyMappingException('SHOPIFY_VARIANT_NOT_FOUND', 'The Shopify variant was not found.');
        // Variant identity is store-scoped and comes from the local catalogue.
        // Product metadata submitted by the browser is display-only.
        $variant = $db->table('shopify_variant_catalogue')->where('store_domain', $store)->where('shopify_variant_id', $variantId)->get(1)->getRowArray();
        if ($variant === null) {
            $sameVariant = $db->table('shopify_variant_catalogue')->where('shopify_variant_id', $variantId)->get(1)->getRowArray();
            throw new ShopifyMappingException($sameVariant !== null ? 'SHOPIFY_MAPPING_STORE_MISMATCH' : 'SHOPIFY_VARIANT_NOT_FOUND', $sameVariant !== null ? 'The Shopify variant belongs to another store.' : 'The Shopify variant was not found.', ['shopify_variant_id' => 'Synchronize the Shopify variant before mapping.']);
        }
        $productId = (string) ($variant['shopify_product_id'] ?? '');
        $medicine = (new MedicineModel())->find($medicineId);
        if ($medicine === null) throw new ShopifyMappingException('ROOTINS_MEDICINE_NOT_FOUND', 'The ROOTINS medicine was not found.', ['medicine_id' => 'Select an existing ROOTINS medicine.']);
        if (! in_array('shopify.order.operations', session('permissions') ?? [], true)) throw new ShopifyMappingException('SHOPIFY_MAPPING_PERMISSION_DENIED', 'You are not authorized to update Shopify mappings.');

        $now = date('Y-m-d H:i:s');
        $mappingStatus = (string) ($medicine['status'] ?? '') === 'active' ? $status : 'inactive';
        $db->transBegin();
        try {
            $mappingTable = $db->table('shopify_medicine_mappings');
            $existingForVariant = $mappingTable->where('store_domain', $store)->where('shopify_variant_id', $variantId)->get(1)->getRowArray();
            $existingById = $id !== null && $id > 0 ? $mappingTable->where('id', $id)->get(1)->getRowArray() : null;
            if ($existingById !== null && (string) $existingById['store_domain'] !== $store) throw new ShopifyMappingException('SHOPIFY_MAPPING_STORE_MISMATCH', 'The mapping belongs to another store.');
            if ($existingForVariant !== null && $existingById !== null && (int) $existingForVariant['id'] !== (int) $existingById['id']) throw new ShopifyMappingException('SHOPIFY_MAPPING_DUPLICATE', 'This Shopify variant is already mapped to another medicine.');
            // Updating the current mapping may intentionally change its medicine. Only
            // reject a different row claiming the same store-scoped variant.
            $targetId = (int) ($existingById['id'] ?? $existingForVariant['id'] ?? 0);
            $data = [
                'store_domain' => $store, 'shopify_product_id' => $productId, 'shopify_variant_id' => $variantId,
                'shopify_product_title' => $variant['product_title'] ?? null, 'shopify_variant_title' => $variant['variant_title'] ?? null,
                'shopify_sku' => $variant['sku'] ?? null, 'shopify_barcode' => $variant['barcode'] ?? null,
                'product_image_url' => $variant['product_image_url'] ?? null, 'medicine_id' => $medicineId,
                'status' => $mappingStatus,
            ];
            $data = array_filter($data, fn (mixed $value, string $column): bool => $db->fieldExists($column, 'shopify_medicine_mappings'), ARRAY_FILTER_USE_BOTH);
            if ($db->fieldExists('updated_at', 'shopify_medicine_mappings')) $data['updated_at'] = $now;
            if ($targetId > 0) {
                if ($mappingTable->where('id', $targetId)->update($data) === false) throw new \RuntimeException('mapping update failed');
                $event = 'shopify.order_mapping_updated';
            } else {
                if ($db->fieldExists('created_at', 'shopify_medicine_mappings')) $data['created_at'] = $now;
                if ($mappingTable->insert($data) === false) throw new \RuntimeException('mapping insert failed');
                $targetId = (int) $db->insertID();
                $event = 'shopify.order_mapping_created';
            }
            service('audit')->record($event, ['mapping_id' => $targetId, 'store_domain' => $store, 'product_id' => $productId, 'variant_id' => $variantId, 'medicine_id' => $medicineId]);
            if (! $db->transStatus()) throw new \RuntimeException('mapping transaction failed');
            $db->transCommit();
            $persisted = $mappingTable
                ->where('id', $targetId)
                ->where('store_domain', $store)
                ->where('shopify_product_id', $productId)
                ->where('shopify_variant_id', $variantId)
                ->where('medicine_id', $medicineId)
                ->get(1)->getRowArray();
            if ($persisted === null || (string) ($persisted['status'] ?? '') !== $mappingStatus) {
                throw new ShopifyMappingException('SHOPIFY_MAPPING_NOT_PERSISTED', 'The mapping was not verified after saving. Please try again.');
            }
            $recheck = ['processed' => 0];
            try {
                $recheck = $this->recheckMappings(['store_domain' => $store, 'shopify_product_id' => $productId, 'shopify_variant_id' => $variantId]);
            } catch (\Throwable $exception) {
                log_message('error', 'Shopify mapping save recheck failed: ' . get_class($exception));
            }
            return ['mapping_id' => (string) $targetId, 'variant_id' => $variantId, 'medicine_id' => (string) $medicineId, 'mapping_status' => $mappingStatus, 'medicine_status' => (string) ($medicine['status'] ?? ''), 'verified' => true, 'rechecked_orders' => (int) ($recheck['processed'] ?? 0), 'message' => 'Product mapping saved.'];
        } catch (ShopifyMappingException $exception) {
            $db->transRollback();
            throw $exception;
        } catch (\Throwable $exception) {
            $db->transRollback();
            throw $this->classifyMappingDatabaseFailure($exception);
        }
    }

    private function classifyMappingDatabaseFailure(\Throwable $exception): ShopifyMappingException
    {
        $message = strtolower($exception->getMessage());
        if (str_contains($message, 'duplicate entry') || str_contains($message, '1062')) return new ShopifyMappingException('SHOPIFY_MAPPING_DUPLICATE', 'This Shopify variant is already mapped to another medicine.', [], $exception);
        if (str_contains($message, 'foreign key') || str_contains($message, '1451') || str_contains($message, '1452')) return new ShopifyMappingException('SHOPIFY_MAPPING_FOREIGN_KEY_FAILURE', 'The mapping references an unavailable record.', [], $exception);
        if (str_contains($message, 'unknown column') || str_contains($message, '1054')) return new ShopifyMappingException('SHOPIFY_MAPPING_COLUMN_MISSING', 'The Shopify mapping schema is incomplete.', [], $exception);
        if (str_contains($message, 'doesn\'t exist') || str_contains($message, '1146')) return new ShopifyMappingException('SHOPIFY_MAPPING_TABLE_MISSING', 'The Shopify mapping table is unavailable.', [], $exception);
        return new ShopifyMappingException('SHOPIFY_MAPPING_DATABASE_FAILURE', 'The mapping could not be updated.', [], $exception);
    }

    public function toggleMapping(int $id): bool
    {
        $model = new ShopifyMedicineMappingModel();
        $mapping = $model->find($id);
        if ($mapping === null) throw new InvalidArgumentException('Mapping not found.');
        $status = ($mapping['status'] ?? '') === 'active' ? 'inactive' : 'active';
        if ($status === 'active' && (new MedicineModel())->where('id', (int) $mapping['medicine_id'])->where('status', 'active')->first() === null) throw new InvalidArgumentException('An inactive medicine cannot be activated in a mapping.');
        $saved = $model->update($id, ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')]);
        if ($saved) service('audit')->record('shopify.order_mapping_status_changed', ['mapping_id' => $id, 'status' => $status]);
        return (bool) $saved;
    }

    public function activeMedicines(): array
    {
        $medicines = (new MedicineModel())->select('id, medicine_name, generic_name, strength, unit, manufacturer, medicine_code, status')->where('status', 'active')->orderBy('medicine_name', 'ASC')->findAll(500);
        log_message('debug', 'Shopify mapping active medicine query result count: ' . count($medicines));
        return $medicines;
    }

    /** @return array<int, array<string, mixed>> */
    public function searchMedicines(string $query): array
    {
        $query = trim($query);
        $model = new MedicineModel();
        if ($query !== '') $model->groupStart()->like('medicine_name', $query)->orLike('strength', $query)->orLike('unit', $query)->orLike('manufacturer', $query)->orLike('medicine_code', $query)->groupEnd();
        return $model->select('id, medicine_name, strength, unit, manufacturer, medicine_code, status')->where('status', 'active')->orderBy('medicine_name')->findAll(50);
    }

    /** @return array<int, array<string, mixed>> */
    private function suggestions(string $title, string $sku, string $barcode): array
    {
        $normalized = $this->normalized($title);
        $suggestions = [];
        foreach ((new MedicineModel())->where('status', 'active')->findAll(500) as $medicine) {
            $name = $this->normalized((string) ($medicine['medicine_name'] ?? ''));
            $strength = $this->normalized((string) ($medicine['strength'] ?? ''));
            $score = 0; $confidence = 'Manual selection required';
            if ($sku !== '' && $sku === (string) ($medicine['medicine_code'] ?? '')) { $score = 100; $confidence = 'Exact match'; }
            elseif ($normalized !== '' && $normalized === $name) { $score = 90; $confidence = 'Strong match'; }
            elseif ($normalized !== '' && $strength !== '' && str_contains($normalized, $name) && str_contains($normalized, $strength)) { $score = 75; $confidence = 'Possible match'; }
            if ($score > 0) $suggestions[] = ['medicine' => $medicine, 'score' => $score, 'confidence' => $confidence];
        }
        usort($suggestions, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);
        return array_slice($suggestions, 0, 5);
    }

    private function normalized(string $value): string { return trim((string) preg_replace('/[^a-z0-9]+/i', ' ', strtolower($value))); }

    /** @return array<string, mixed> */
    public function bulkSaveMappings(array $input, int $actor): array
    {
        $variants = $input['variants'] ?? $input['variant_ids'] ?? [];
        if (is_string($variants)) $variants = json_decode($variants, true) ?: array_filter(array_map('trim', explode(',', $variants)));
        if (! is_array($variants) || $variants === []) throw new InvalidArgumentException('Select at least one Shopify variant.');
        $medicineId = (int) ($input['medicine_id'] ?? 0);
        if ($medicineId < 1 || (new MedicineModel())->find($medicineId) === null) throw new InvalidArgumentException('Select an existing ROOTINS medicine.');
        $saved = 0;
        foreach ($variants as $variant) {
            $productId = is_array($variant) ? (string) ($variant['product_id'] ?? '') : '';
            $variantId = is_array($variant) ? (string) ($variant['variant_id'] ?? '') : (string) $variant;
            if ($variantId === '') continue;
            $this->saveMapping(['store_domain' => $input['store_domain'] ?? '', 'shopify_product_id' => $productId, 'shopify_variant_id' => $variantId, 'shopify_product_title' => is_array($variant) ? ($variant['title'] ?? '') : '', 'shopify_variant_title' => is_array($variant) ? ($variant['variant_title'] ?? '') : '', 'shopify_sku' => is_array($variant) ? ($variant['sku'] ?? '') : '', 'medicine_id' => $medicineId], null);
            $saved++;
        }
        service('audit')->record('shopify.order_mapping_bulk_created', ['actor' => $actor, 'count' => $saved, 'medicine_id' => $medicineId]);
        return ['saved' => $saved];
    }

    public function ignoreMapping(array $input, int $actor): bool
    {
        $store = strtolower(trim((string) ($input['store_domain'] ?? (new ShopifyConfiguration())->storeDomain())));
        $product = trim((string) ($input['shopify_product_id'] ?? '')); $variant = trim((string) ($input['shopify_variant_id'] ?? '')); $reason = trim((string) ($input['reason'] ?? ''));
        if ($store === '' || $product === '' || $variant === '' || $reason === '') throw new InvalidArgumentException('Product, variant, and an ignore reason are required.');
        $model = new ShopifyMappingIgnoreModel();
        $existing = $model->where('store_domain', $store)->where('shopify_product_id', $product)->where('shopify_variant_id', $variant)->first();
        $data = ['store_domain' => $store, 'shopify_product_id' => $product, 'shopify_variant_id' => $variant, 'reason' => $reason, 'created_by' => $actor, 'updated_at' => date('Y-m-d H:i:s')];
        if ($existing) $model->update((int) $existing['id'], $data); else { $data['created_at'] = date('Y-m-d H:i:s'); $model->insert($data); }
        service('audit')->record('shopify.order_mapping_ignored', ['store_domain' => $store, 'product_id' => $product, 'variant_id' => $variant, 'actor' => $actor]);
        return true;
    }

    /** @return array<string, int|array<int, string>> */
    public function recheckMappings(array $input): array
    {
        $store = strtolower(trim((string) ($input['store_domain'] ?? ''))); $variant = trim((string) ($input['shopify_variant_id'] ?? '')); $product = trim((string) ($input['shopify_product_id'] ?? ''));
        if ($store === '' || $variant === '') throw new InvalidArgumentException('A store and Shopify variant are required.');
        $variantId = preg_match('#^gid://shopify/[A-Za-z]+/([0-9]+)$#i', $variant, $variantMatch) === 1 ? $variantMatch[1] : $variant;
        $productId = preg_match('#^gid://shopify/[A-Za-z]+/([0-9]+)$#i', $product, $productMatch) === 1 ? $productMatch[1] : $product;
        $variantAliases = $variantId !== '' ? array_values(array_unique([$variant, $variantId, 'gid://shopify/ProductVariant/' . $variantId])) : [];
        $productAliases = $productId !== '' ? array_values(array_unique([$product, $productId, 'gid://shopify/Product/' . $productId])) : [];
        $query = db_connect()->table('shopify_order_imports i')->select('i.id')->join('shopify_webhook_events e', 'e.id = i.last_event_id', 'left')->where('i.store_domain', $store);
        if ($variant !== 'all' && $variant !== '') {
            $query->join('shopify_order_import_lines l', 'l.import_id = i.id', 'left')
                ->groupStart()->whereIn('l.shopify_variant_id', $variantAliases)->orLike('e.payload_json', $variantId)->groupEnd();
        } elseif ($product !== '') {
            $query->join('shopify_order_import_lines l', 'l.import_id = i.id', 'left')
                ->groupStart()->whereIn('l.shopify_product_id', $productAliases)->orLike('e.payload_json', $productId)->groupEnd();
        }
        $imports = $query->groupBy('i.id')->get()->getResultArray();
        $totals = ['orders_checked' => 0, 'lines_checked' => 0, 'lines_mapped' => 0, 'lines_unmapped' => 0, 'orders_updated' => 0, 'errors' => []];
        foreach ($imports as $import) {
            $before = (new ShopifyOrderImportModel())->find((int) $import['id']);
            $result = $this->recheckImportMapping((int) $import['id'], null, 0);
            $counts = (array) ($result['data']['mapping_recheck'] ?? []);
            $totals['orders_checked']++;
            $totals['lines_checked'] += (int) ($counts['lines_checked'] ?? 0);
            $totals['lines_mapped'] += (int) ($counts['lines_mapped'] ?? 0);
            $totals['lines_unmapped'] += (int) ($counts['lines_unmapped'] ?? 0);
            if (($before['mapping_status'] ?? '') !== (($result['data']['mapping_status'] ?? $before['mapping_status'] ?? ''))) $totals['orders_updated']++;
            if (($result['status'] ?? false) !== true) $totals['errors'][] = 'Import ' . (int) $import['id'] . ' could not be reconciled.';
        }
        service('audit')->record('shopify.order_mapping_recheck', ['store_domain' => $store, 'product_id' => $product, 'variant_id' => $variant, 'orders_checked' => $totals['orders_checked'], 'lines_checked' => $totals['lines_checked']]);
        return $totals;
    }

    public function recheckImportMapping(int $importId, ?int $clinicId = null, int $actor = 0): array
    {
        $import = (new ShopifyOrderImportModel())->find($importId);
        if ($import === null || ($clinicId !== null && (int) ($import['clinic_id'] ?? 0) !== $clinicId)) {
            throw new InvalidArgumentException('Shopify order was not found.');
        }

        $result = (new ShopifyOrderImportService())->recheckMapping($importId);
        $updated = (new ShopifyOrderImportModel())->find($importId) ?? $import;
        $counts = $this->mappingCounts($importId);
        $this->history($importId, 'recheck_mapping', (string) ($import['status'] ?? ''), (string) ($updated['status'] ?? ''), null, (string) ($result['body']['msg'] ?? ''), $actor);
        service('audit')->record('shopify.order_mapping_rechecked', ['import_id' => $importId]);

        return [
            'status' => (bool) ($result['body']['status'] ?? false),
            'msg' => (string) ($result['body']['msg'] ?? 'Shopify order mapping recheck completed.'),
            'data' => [
                'import_id' => $importId,
                'mapping_status' => (string) ($updated['mapping_status'] ?? ''),
                'mapping_recheck' => $counts,
            ],
        ];
    }

    /** @return array{lines_checked:int, lines_mapped:int, lines_unmapped:int, errors:int} */
    private function mappingCounts(int $importId): array
    {
        $lines = db_connect()->table('shopify_order_import_lines')->select('status, medicine_id, mapping_reason')->where('import_id', $importId)->get()->getResultArray();
        $mapped = 0; $errors = 0;
        foreach ($lines as $line) {
            if ((int) ($line['medicine_id'] ?? 0) > 0 && in_array((string) ($line['status'] ?? ''), ['mapped', 'imported'], true)) $mapped++;
            if (in_array((string) ($line['status'] ?? ''), ['conflict', 'medicine_inactive'], true)) $errors++;
        }
        return ['lines_checked' => count($lines), 'lines_mapped' => $mapped, 'lines_unmapped' => max(0, count($lines) - $mapped), 'errors' => $errors];
    }

    public function readiness(array $filters = []): array
    {
        $query = db_connect()->table('shopify_patient_identity_links l')
            ->select('l.*, p.registration_number, p.first_name, p.last_name, p.email AS patient_email, p.mobile_number, p.status AS patient_status, p.date_of_birth')
            ->join('patients p', 'p.id = l.patient_id', 'left')->orderBy('l.updated_at', 'DESC');
        if (($filters['store'] ?? '') !== '') $query->where('l.shopify_store_domain', strtolower(trim((string) $filters['store'])));
        if (($filters['status'] ?? '') !== '') $query->where('l.link_status', trim((string) $filters['status']));
        $rows = $query->get(500)->getResultArray();
        foreach ($rows as &$row) {
            $missing = [];
            foreach (['first_name' => 'first name', 'last_name' => 'last name', 'date_of_birth' => 'date of birth', 'mobile_number' => 'mobile'] as $field => $label) if (trim((string) ($row[$field] ?? '')) === '') $missing[] = $label;
            $row['missing_fields'] = $missing;
            $row['readiness_status'] = $this->readinessStatus($row);
            $row['patient_public_reference'] = (string) ($row['registration_number'] ?? '');
        }
        unset($row);
        return $rows;
    }

    public function queue(array $filters = [], ?int $clinicId = null): array
    {
        $query = db_connect()->table('shopify_order_imports i')
            ->select('i.*, p.registration_number, p.first_name, p.last_name, p.status AS rootins_patient_status, l.link_status')
            ->join('patients p', 'p.id = i.patient_id', 'left')
            ->join('shopify_patient_identity_links l', 'l.shopify_store_domain = i.store_domain AND l.shopify_customer_id = i.shopify_customer_id', 'left')
            ->orderBy('i.updated_at', 'DESC');
        if ($clinicId !== null) $query->where('i.clinic_id', $clinicId);
        foreach (['store_domain' => 'i.store_domain', 'status' => 'i.status', 'prescription_status' => 'i.prescription_status', 'mapping_status' => 'i.mapping_status', 'financial_status' => 'i.financial_status', 'fulfillment_status' => 'i.fulfillment_status', 'patient_status' => 'i.patient_status'] as $key => $column) if (($filters[$key] ?? '') !== '') $query->where($column, trim((string) $filters[$key]));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', trim((string) ($filters['date_from'] ?? '')))) $query->where('i.created_at >=', trim((string) $filters['date_from']) . ' 00:00:00');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', trim((string) ($filters['date_to'] ?? '')))) $query->where('i.created_at <=', trim((string) $filters['date_to']) . ' 23:59:59');
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') $query->groupStart()->like('i.shopify_order_number', $search)->orLike('i.shopify_order_id', $search)->orLike('i.customer_name_snapshot', $search)->orLike('i.customer_email_snapshot', $search)->orLike('p.registration_number', $search)->orLike('i.shopify_customer_id', $search)->groupEnd();
        $imports = $query->get(500)->getResultArray();
        $ids = array_values(array_filter(array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), $imports)));
        if ($ids === []) return $imports;
        $lineRows = db_connect()->table('shopify_order_import_lines l')->select('l.import_id, l.title_snapshot, l.quantity, l.medicine_id, l.status, m.medicine_name, m.strength, m.unit')->join('medicines m', 'm.id = l.medicine_id', 'left')->whereIn('l.import_id', $ids)->orderBy('l.id', 'ASC')->get()->getResultArray();
        $summaries = [];
        foreach ($lineRows as $line) {
            $importId = (int) $line['import_id'];
            $name = trim((string) ($line['medicine_name'] ?? '')) ?: trim((string) ($line['title_snapshot'] ?? '')) ?: 'Unmapped medicine';
            $summaries[$importId]['count'] = ($summaries[$importId]['count'] ?? 0) + 1;
            $summaries[$importId]['names'][] = $name;
            $summaries[$importId]['mapped'] = ($summaries[$importId]['mapped'] ?? 0) + (in_array((string) ($line['status'] ?? ''), ['mapped', 'imported'], true) && (int) ($line['medicine_id'] ?? 0) > 0 ? 1 : 0);
        }
        foreach ($imports as &$import) {
            if (($import['link_status'] ?? '') === 'linked'
                && ((int) ($import['patient_id'] ?? 0) <= 0 || (int) ($import['rootins_patient_status'] ?? 0) !== 1)) {
                $import['link_status'] = 'link_pending';
            }
            $summary = $summaries[(int) $import['id']] ?? ['count' => 0, 'names' => []];
            if ((int) ($summary['count'] ?? 0) === 0 && ! empty($import['last_event_id'])) {
                $event = (new ShopifyWebhookEventService())->find((int) $import['last_event_id']);
                $payload = is_array($event) ? json_decode((string) ($event['payload_json'] ?? ''), true) : null;
                foreach ((array) ($payload['line_items'] ?? []) as $item) {
                    if (! is_array($item)) continue;
                    $summary['count'] = ($summary['count'] ?? 0) + 1;
                    $summary['names'][] = trim((string) ($item['title'] ?? '')) ?: 'Unmapped medicine';
                }
            }
            $names = array_values(array_unique($summary['names'] ?? []));
            $import['medicine_count'] = (int) ($summary['count'] ?? 0);
            $import['medicine_summary'] = implode(', ', array_slice($names, 0, 2)) . (count($names) > 2 ? ' +' . (count($names) - 2) : '');
            if ((int) ($summary['count'] ?? 0) > 0 && (int) ($summary['mapped'] ?? 0) === (int) ($summary['count'] ?? 0)) {
                $import['mapping_status'] = 'mapped';
                if (($import['status'] ?? '') === 'pending_mapping') $import['status'] = 'ready_to_import';
            }
        }
        unset($import);
        return $imports;
    }

    public function counters(?int $clinicId = null): array
    {
        $db = db_connect();
        $today = date('Y-m-d');
        $result = [];
        $total = $db->table('shopify_order_imports');
        if ($clinicId !== null) $total->where('clinic_id', $clinicId);
        $result['total_shopify_orders'] = $total->countAllResults();
        foreach (['awaiting_review' => ['prescription_status' => 'awaiting_review'], 'quarantined' => ['status' => 'quarantined'], 'unmapped' => ['mapping_status' => 'unmapped'], 'prescription_review' => ['prescription_status' => 'awaiting_review'], 'ready_to_process' => ['status' => 'ready_to_import']] as $name => $conditions) {
            $q = $db->table('shopify_order_imports');
            if ($clinicId !== null) $q->where('clinic_id', $clinicId);
            foreach ($conditions as $field => $value) $q->where($field, $value);
            $result[$name] = $q->countAllResults();
        }
        foreach (['orders_received_today' => ['created_at >=' => $today], 'orders_awaiting_patient' => ['patient_status' => 'link_pending'], 'orders_awaiting_prescription_review' => ['prescription_status' => 'awaiting_review'], 'orders_with_unmapped_medicines' => ['mapping_status' => 'unmapped'], 'orders_ready_to_import' => ['status' => 'ready_to_import'], 'failed_imports' => ['status' => 'failed'], 'quarantined_orders' => ['status' => 'quarantined'], 'successfully_imported' => ['status' => 'imported'], 'cancelled_refunded_orders' => ['status' => 'cancelled']] as $name => $conditions) { $q = $db->table('shopify_order_imports'); if ($clinicId !== null) $q->where('clinic_id', $clinicId); foreach ($conditions as $field => $value) $q->where($field, $value); $result[$name] = $q->countAllResults(); }
        return $result;
    }

    public function detail(int $id, ?int $clinicId = null): ?array
    {
        $import = db_connect()->table('shopify_order_imports i')->select('i.*, p.registration_number, p.first_name, p.last_name, p.email, p.mobile_number, p.status AS rootins_patient_status, l.link_status')->join('patients p', 'p.id = i.patient_id', 'left')->join('shopify_patient_identity_links l', 'l.shopify_store_domain = i.store_domain AND l.shopify_customer_id = i.shopify_customer_id', 'left')->where('i.id', $id)->get(1)->getRowArray();
        if ($import === null || ($clinicId !== null && (int) ($import['clinic_id'] ?? 0) !== $clinicId)) return null;
        if (($import['link_status'] ?? '') === 'linked'
            && ((int) ($import['patient_id'] ?? 0) <= 0 || (int) ($import['rootins_patient_status'] ?? 0) !== 1)) {
            $import['link_status'] = 'link_pending';
        }
        $lines = db_connect()->table('shopify_order_import_lines l')->select('l.*, m.medicine_name, m.strength, m.unit, v.prescription_required')->join('medicines m', 'm.id = l.medicine_id', 'left')->join('shopify_order_imports oi', 'oi.id = l.import_id', 'inner')->join('shopify_variant_catalogue v', 'v.store_domain = oi.store_domain AND v.shopify_variant_id = l.shopify_variant_id', 'left')->where('l.import_id', $id)->orderBy('l.id', 'ASC')->get()->getResultArray();
        $history = db_connect()->table('shopify_order_import_history')->where('import_id', $id)->orderBy('created_at', 'DESC')->get()->getResultArray();
        $event = ! empty($import['last_event_id']) ? (new ShopifyWebhookEventService())->find((int) $import['last_event_id']) : null;
        $webhookHistory = db_connect()->table('shopify_webhook_events')->select('id, external_event_id, topic, status, error_message, retry_count, created_at, updated_at')->where('store_domain', $import['store_domain'])->like('payload_json', (string) $import['shopify_order_id'])->orderBy('created_at', 'DESC')->get(100)->getResultArray();
        $auditHistory = db_connect()->table('audit_logs')->select('id, action, route, occurred_at, context')->like('context', '"import_id":' . $id)->orderBy('occurred_at', 'DESC')->get(100)->getResultArray();
        $prescription = ! empty($import['prescription_reference']) ? db_connect()->table('patient_documents')->select('id')->where('source_type', 'shopify')->where('external_upload_reference', $import['prescription_reference'])->where('shopify_store_domain', $import['store_domain'])->where('shopify_customer_id', $import['shopify_customer_id'])->get(1)->getRowArray() : null;
        $import['prescription_document_id'] = $prescription['id'] ?? null;
        $import['lines'] = $lines; $import['history'] = $history; $import['last_webhook'] = $event; $import['webhook_history'] = $webhookHistory; $import['audit_history'] = $auditHistory;
        $import['intent_present'] = $this->appointmentIntentPresent($event);
        $latestHistory = $history[0] ?? [];
        $import['last_processing_stage'] = (string) ($latestHistory['action'] ?? '');
        $import['last_failure_category'] = (string) ($import['failure_reason'] ?? $import['error_message'] ?? $latestHistory['reason'] ?? '');
        return $import;
    }

    public function action(int $id, string $action, array $input = [], int $actor = 0, ?int $clinicId = null): array
    {
        $import = (new ShopifyOrderImportModel())->find($id);
        if ($import === null || ($clinicId !== null && (int) ($import['clinic_id'] ?? 0) !== $clinicId)) throw new InvalidArgumentException('Shopify order was not found.');
        $old = (string) ($import['status'] ?? '');
        $model = new ShopifyOrderImportModel();
        if ($action === 'reprocess_paid_appointment') {
            $event = ! empty($import['last_event_id']) ? (new ShopifyWebhookEventService())->find((int) $import['last_event_id']) : null;
            if (! $this->appointmentIntentPresent($event)) {
                return ['status' => 'skipped', 'reason' => 'NO_APPOINTMENT_INTENT', 'import_id' => $id];
            }
            $result = (new ShopifyAppointmentIntentService())->reprocessPaidOrder((string) $import['store_domain'], (string) $import['shopify_order_id']);
            service('audit')->record('shopify.order_operation_reprocess_paid_appointment', ['import_id' => $id, 'shopify_order_id' => (string) $import['shopify_order_id']]);
            return $result;
        }
        if ($action === 'recheck_mapping') {
            return $this->recheckImportMapping($id, $clinicId, $actor);
        }
        if ($action === 'retry_validation' || $action === 'retry_import' || $action === 'retry_payment' || $action === 'reprocess_webhook') {
            // Retry is a complete reconciliation, not a mapping-only check.
            // Record the attempt before invoking the canonical importer so a
            // failed request remains visible and retryable.
            $this->history($id, 'retry_started', $old, $old, null, null, $actor);
            try {
                $result = (new ShopifyOrderImportService())->importExisting($id, null);
                $afterRow = $model->find($id) ?? [];
                $after = (string) ($afterRow['status'] ?? '');
                $body = (array) ($result['body'] ?? []);
                $data = (array) ($body['data'] ?? []);
                $success = (bool) ($body['status'] ?? false) && in_array($after, ['imported', 'processed'], true);

                if ($success) {
                    $alreadyProcessed = $old === $after;
                    $this->history($id, 'retry_succeeded', $old, $after, null, null, $actor);
                    service('audit')->record('shopify.order_operation_retry_succeeded', ['import_id' => $id, 'before_status' => $old, 'after_status' => $after]);
                    return [
                        'status' => true,
                        'before_status' => $old,
                        'after_status' => $after,
                        'message' => $alreadyProcessed ? 'Order was already imported.' : 'Order imported successfully.',
                        'already_processed' => $alreadyProcessed,
                        'import_id' => $id,
                    ];
                }

                $code = (string) ($data['error_category'] ?? $afterRow['failure_reason'] ?? $afterRow['quarantine_reason'] ?? 'SHOPIFY_ORDER_RETRY_FAILED');
                $message = (string) ($body['msg'] ?? 'Shopify order could not be imported.');
                $this->history($id, 'retry_failed', $old, $after, $code, $message, $actor);
                service('audit')->record('shopify.order_operation_retry_failed', ['import_id' => $id, 'before_status' => $old, 'after_status' => $after, 'failure_category' => $code]);
                return [
                    'status' => false,
                    'code' => $code,
                    'message' => $message,
                    'current_status' => $after,
                    'before_status' => $old,
                    'after_status' => $after,
                    'import_id' => $id,
                ];
            } catch (\Throwable $exception) {
                $after = (string) (($model->find($id) ?? [])['status'] ?? $old);
                $code = 'SHOPIFY_ORDER_RETRY_FAILED';
                $message = 'Shopify order import could not be completed safely.';
                $this->history($id, 'retry_failed', $old, $after, $code, $message, $actor);
                service('audit')->record('shopify.order_operation_retry_failed', ['import_id' => $id, 'before_status' => $old, 'after_status' => $after, 'failure_category' => $code]);
                log_message('error', 'Shopify order retry failed safely. Import ID: ' . $id . ' Exception: ' . get_class($exception));
                return ['status' => false, 'code' => $code, 'message' => $message, 'current_status' => $after, 'before_status' => $old, 'after_status' => $after, 'import_id' => $id];
            }
        }
        if (in_array($action, ['quarantine', 'release_quarantine', 'mark_ready'], true)) {
            $status = $action === 'quarantine' ? 'quarantined' : ($action === 'mark_ready' ? 'ready_to_import' : 'received');
            $reason = trim((string) ($input['reason'] ?? '')) ?: null;
            $allowedStatuses = [
                'quarantine' => ['received', 'pending_mapping', 'ready_to_import', 'failed', 'quarantined'],
                'release_quarantine' => ['quarantined'],
                'mark_ready' => ['received', 'pending_mapping', 'failed', 'ready_to_import'],
            ];
            if (! in_array($old, $allowedStatuses[$action], true)) {
                throw new InvalidArgumentException('The order cannot transition from ' . ($old !== '' ? $old : 'unknown') . ' using this action.');
            }
            $updated = $model->update($id, ['status' => $status, 'quarantine_reason' => $reason, 'failure_reason' => $reason, 'updated_at' => date('Y-m-d H:i:s')]);
            $afterRow = $model->find($id);
            if (! $updated && (($afterRow['status'] ?? null) !== $status)) {
                throw new \RuntimeException('The Shopify order state could not be saved.');
            }
            if ($afterRow === null || (string) ($afterRow['status'] ?? '') !== $status) {
                throw new \RuntimeException('The Shopify order state could not be verified after saving.');
            }
            $this->history($id, $action, $old, $status, $reason, null, $actor);
            service('audit')->record('shopify.order_operation_' . $action, ['import_id' => $id, 'reason' => $reason]);
            return ['status' => $status, 'import_id' => $id, 'affected_rows' => $updated ? 1 : 0];
        }
        if ($action === 'select_patient') {
            $linkId = (int) ($input['link_id'] ?? 0); $patientId = (int) ($input['patient_id'] ?? 0);
            $link = (new ShopifyPatientIdentityLinkModel())->where('id', $linkId)->where('shopify_store_domain', $import['store_domain'])->where('shopify_customer_id', $import['shopify_customer_id'])->first();
            $patient = (new PatientModel())->where('id', $patientId)->where('status', 1)->first();
            if ($link === null || $patient === null) throw new InvalidArgumentException('The selected patient identity is invalid.');
            (new ShopifyPatientIdentityLinkModel())->update($linkId, ['patient_id' => $patientId, 'link_status' => 'linked', 'matched_by' => 'admin', 'active_patient_key' => 'shopify:' . $patientId, 'linked_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
            (new ShopifyOrderImportModel())->update($id, ['patient_id' => $patientId, 'patient_status' => 'linked', 'status' => 'received', 'updated_at' => date('Y-m-d H:i:s')]);
            $this->history($id, $action, $old, 'received', null, null, $actor); service('audit')->record('shopify.order_patient_selected', ['import_id' => $id]); return ['status' => 'received', 'import_id' => $id];
        }
        if ($action === 'select_prescription') { return (new ShopifyOrderImportService())->importExisting($id, (int) ($input['prescription_id'] ?? 0)); }
        if ($action === 'map_line') {
            $line = (new ShopifyOrderImportLineModel())->where('id', (int) ($input['line_id'] ?? 0))->where('import_id', $id)->first();
            if ($line === null) throw new InvalidArgumentException('The selected Shopify line is invalid.');
            $mapping = (new ShopifyMedicineMappingModel())->where('id', (int) ($input['mapping_id'] ?? 0))->where('store_domain', $import['store_domain'])->where('status', 'active')->first();
            if ($mapping === null) throw new InvalidArgumentException('The selected medicine mapping is invalid or inactive.');
            (new ShopifyOrderImportLineModel())->update((int) $line['id'], ['medicine_id' => (int) $mapping['medicine_id'], 'status' => 'mapped', 'updated_at' => date('Y-m-d H:i:s')]);
            (new ShopifyOrderImportModel())->update($id, ['mapping_status' => 'mapped', 'status' => 'received', 'updated_at' => date('Y-m-d H:i:s')]);
            $this->history($id, $action, $old, 'received', null, null, $actor); service('audit')->record('shopify.order_line_mapped', ['import_id' => $id, 'line_id' => (int) $line['id']]); return ['status' => 'received', 'import_id' => $id];
        }
        if ($action === 'cancel_import') {
            if (in_array($old, ['imported', 'completed'], true)) throw new InvalidArgumentException('An imported order cannot be cancelled from reconciliation.');
            $model->update($id, ['status' => 'cancelled', 'quarantine_reason' => 'manual_cancelled', 'updated_at' => date('Y-m-d H:i:s')]); $this->history($id, $action, $old, 'cancelled', 'Manual cancellation', null, $actor); service('audit')->record('shopify.order_import_cancelled', ['import_id' => $id]); return ['status' => 'cancelled', 'import_id' => $id];
        }
        throw new InvalidArgumentException('Unsupported Shopify order operation.');
    }

    private function readinessStatus(array $row): string
    {
        if (in_array((string) ($row['link_status'] ?? ''), ['ambiguous', 'conflict'], true)) return 'Cross-store conflict';
        if ((string) ($row['patient_status'] ?? '') === '0' || (int) ($row['patient_status'] ?? 0) === 0 && ! empty($row['patient_id'])) return 'Inactive';
        if (($row['link_status'] ?? '') === 'linked' && empty($row['missing_fields'])) return 'Linked';
        if (($row['link_status'] ?? '') === 'linked') return 'Incomplete';
        return 'Not registered';
    }

    private function history(int $id, string $action, string $from, string $to, ?string $reason, ?string $error, int $actor): void
    { if (db_connect()->tableExists('shopify_order_import_history')) db_connect()->table('shopify_order_import_history')->insert(['import_id' => $id, 'action' => $action, 'from_status' => $from ?: null, 'to_status' => $to ?: null, 'reason' => $reason, 'error_message' => $error, 'actor_user_id' => $actor > 0 ? $actor : null, 'created_at' => date('Y-m-d H:i:s')]); }

    private function appointmentIntentPresent(?array $event): bool
    {
        if ($event === null) return false;
        $payload = json_decode((string) ($event['payload_json'] ?? ''), true);
        return is_array($payload)
            && (new ShopifyAppointmentIntentService())->appointmentIntentReferenceForOrder($payload) !== '';
    }

    private function like(object $query, string $column, mixed $value): void { if (trim((string) $value) !== '') $query->like($column, trim((string) $value)); }
}
