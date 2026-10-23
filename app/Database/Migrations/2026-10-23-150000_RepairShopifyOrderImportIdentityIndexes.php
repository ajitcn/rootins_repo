<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Repairs schema drift on the Shopify order import identity indexes.
 *
 * The import identity is the Shopify order itself, not its current status.
 * Existing rows are never removed by this migration.
 */
final class RepairShopifyOrderImportIdentityIndexes extends Migration
{
    private const TABLE = 'shopify_order_imports';
    private const IDENTITY_INDEX = 'shopify_order_import_identity';
    private const STATUS_INDEX = 'shopify_order_import_status';
    private const LEGACY_UNIQUE_STATUS_INDEX = 'store_domain_status';

    public function up(): void
    {
        if (! $this->db->tableExists(self::TABLE)) {
            return;
        }

        $indexes = $this->indexes();

        if (isset($indexes[self::LEGACY_UNIQUE_STATUS_INDEX])) {
            $this->db->query('ALTER TABLE ' . self::TABLE . ' DROP INDEX ' . self::LEGACY_UNIQUE_STATUS_INDEX);
            unset($indexes[self::LEGACY_UNIQUE_STATUS_INDEX]);
        }

        if (! $this->hasIndex($indexes, ['store_domain', 'status'], false)) {
            $this->db->query('ALTER TABLE ' . self::TABLE . ' ADD INDEX ' . self::STATUS_INDEX . ' (store_domain, status)');
        }

        if (! $this->hasIndex($indexes, ['store_domain', 'shopify_order_id'], true)) {
            $duplicates = $this->db->query(
                'SELECT store_domain, shopify_order_id, COUNT(*) AS duplicate_count
                 FROM ' . self::TABLE . '
                 GROUP BY store_domain, shopify_order_id
                 HAVING COUNT(*) > 1
                 LIMIT 1'
            )->getRowArray();

            if ($duplicates !== null) {
                throw new \RuntimeException('Shopify order import identity duplicates must be reviewed before the unique identity index can be created.');
            }

            $this->db->query(
                'ALTER TABLE ' . self::TABLE . ' ADD UNIQUE INDEX ' . self::IDENTITY_INDEX . ' (store_domain, shopify_order_id)'
            );
        }
    }

    public function down(): void
    {
        if (! $this->db->tableExists(self::TABLE)) {
            return;
        }

        $indexes = $this->indexes();
        if (isset($indexes[self::STATUS_INDEX])) {
            $this->db->query('ALTER TABLE ' . self::TABLE . ' DROP INDEX ' . self::STATUS_INDEX);
        }

        // Restore the legacy index only when existing data can satisfy it.
        if (! isset($indexes[self::LEGACY_UNIQUE_STATUS_INDEX])) {
            $duplicates = $this->db->query(
                'SELECT store_domain, status, COUNT(*) AS duplicate_count
                 FROM ' . self::TABLE . '
                 GROUP BY store_domain, status
                 HAVING COUNT(*) > 1
                 LIMIT 1'
            )->getRowArray();

            if ($duplicates === null) {
                $this->db->query(
                    'ALTER TABLE ' . self::TABLE . ' ADD UNIQUE INDEX ' . self::LEGACY_UNIQUE_STATUS_INDEX . ' (store_domain, status)'
                );
            }
        }
    }

    /** @return array<string, list<array{column: string, non_unique: bool}>> */
    private function indexes(): array
    {
        $grouped = [];
        foreach ($this->db->query('SHOW INDEX FROM ' . self::TABLE)->getResultArray() as $row) {
            $name = (string) ($row['Key_name'] ?? '');
            if ($name === '') {
                continue;
            }
            $grouped[$name][] = [
                'column' => (string) ($row['Column_name'] ?? ''),
                'non_unique' => (int) ($row['Non_unique'] ?? 1) !== 0,
            ];
        }

        return $grouped;
    }

    /** @param array<string, list<array{column: string, non_unique: bool}>> $indexes */
    private function hasIndex(array $indexes, array $columns, bool $unique): bool
    {
        foreach ($indexes as $parts) {
            if (count($parts) !== count($columns)) {
                continue;
            }

            $actualColumns = array_column($parts, 'column');
            $isUnique = ! (bool) ($parts[0]['non_unique'] ?? true);
            if ($actualColumns === $columns && $isUnique === $unique) {
                return true;
            }
        }

        return false;
    }
}
