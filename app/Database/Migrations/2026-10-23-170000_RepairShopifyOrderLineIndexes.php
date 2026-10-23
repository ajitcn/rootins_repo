<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Same product/variant values remain reusable across orders; order identity plus line ID is unique. */
final class RepairShopifyOrderLineIndexes extends Migration
{
    private const TABLE = 'shopify_order_import_lines';
    private const IDENTITY_INDEX = 'shopify_order_line_identity';
    private const IMPORT_FK_INDEX = 'shopify_order_line_import';
    private const LEGACY_UNIQUE_INDEX = 'shopify_product_id_shopify_variant_id';
    private const PRODUCT_INDEX = 'shopify_order_line_product';
    private const PRODUCT_ID_INDEX = 'shopify_order_line_product_id';
    private const STORE_INDEX = 'shopify_order_line_store';
    private const ORDER_INDEX = 'shopify_order_line_order';
    private const VARIANT_INDEX = 'shopify_order_line_variant';
    private const MEDICINE_INDEX = 'shopify_order_line_medicine';

    public function up(): void
    {
        if (! $this->db->tableExists(self::TABLE)) return;

        $indexes = $this->indexes();
        foreach ($indexes as $name => $parts) {
            $columns = array_column($parts, 'column');
            $unique = ! (bool) ($parts[0]['non_unique'] ?? true);
            if ($unique && $columns === ['shopify_product_id', 'shopify_variant_id']) {
                $this->dropIndex($name);
            }
        }

        if (! $this->db->fieldExists('store_domain', self::TABLE)) {
            $this->db->query('ALTER TABLE ' . self::TABLE . ' ADD COLUMN store_domain VARCHAR(190) NULL AFTER import_id');
        }
        if (! $this->db->fieldExists('shopify_order_id', self::TABLE)) {
            $this->db->query('ALTER TABLE ' . self::TABLE . ' ADD COLUMN shopify_order_id VARCHAR(100) NULL AFTER store_domain');
        }

        $this->db->query(
            'UPDATE ' . self::TABLE . ' l
             INNER JOIN shopify_order_imports i ON i.id = l.import_id
             SET l.store_domain = LOWER(TRIM(i.store_domain)),
                 l.shopify_order_id = CAST(i.shopify_order_id AS CHAR(100))
             WHERE l.store_domain IS NULL OR l.store_domain = \'\'
                OR l.shopify_order_id IS NULL OR l.shopify_order_id = \'\''
        );

        $duplicate = $this->db->query(
            'SELECT store_domain, shopify_order_id, shopify_line_item_id, COUNT(*) AS duplicate_count
             FROM ' . self::TABLE . '
             GROUP BY store_domain, shopify_order_id, shopify_line_item_id
             HAVING COUNT(*) > 1 LIMIT 1'
        )->getRowArray();
        if ($duplicate !== null) {
            throw new \RuntimeException('Shopify order-line identity duplicates must be reviewed before the identity index can be added.');
        }

        if ($this->db->fieldExists('store_domain', self::TABLE)) {
            $this->db->query('ALTER TABLE ' . self::TABLE . ' MODIFY COLUMN store_domain VARCHAR(190) NOT NULL');
        }
        if ($this->db->fieldExists('shopify_order_id', self::TABLE)) {
            $this->db->query('ALTER TABLE ' . self::TABLE . ' MODIFY COLUMN shopify_order_id VARCHAR(100) NOT NULL');
        }

        // MySQL may be using the old identity index to support the import_id
        // foreign key. Add a dedicated non-unique supporting index first so
        // the foreign key remains intact while the old index is replaced.
        if (! $this->hasLeadingNonUniqueIndex($this->indexes(), ['import_id'])) {
            $this->addIndexIfMissing(self::IMPORT_FK_INDEX, ['import_id']);
        }

        $indexes = $this->indexes();
        $targetIdentityExists = $this->hasIndex($indexes, ['store_domain', 'shopify_order_id', 'shopify_line_item_id'], true);
        if (isset($indexes[self::IDENTITY_INDEX]) && ! $targetIdentityExists) {
            $this->dropIndex(self::IDENTITY_INDEX);
        }
        if (! $targetIdentityExists) {
            $this->db->query(
                'ALTER TABLE ' . self::TABLE . ' ADD UNIQUE INDEX ' . self::IDENTITY_INDEX
                . ' (store_domain, shopify_order_id, shopify_line_item_id)'
            );
        }

        $this->addIndexIfMissing(self::STORE_INDEX, ['store_domain']);
        $this->addIndexIfMissing(self::ORDER_INDEX, ['shopify_order_id']);
        $this->addIndexIfMissing(self::PRODUCT_ID_INDEX, ['shopify_product_id']);
        $this->addIndexIfMissing(self::VARIANT_INDEX, ['shopify_variant_id']);
        $this->addIndexIfMissing(self::MEDICINE_INDEX, ['medicine_id']);
        if (! $this->hasIndex($this->indexes(), ['shopify_product_id', 'shopify_variant_id'], false)) {
            $this->db->query('ALTER TABLE ' . self::TABLE . ' ADD INDEX ' . self::PRODUCT_INDEX . '_variant (shopify_product_id, shopify_variant_id)');
        }
    }

    public function down(): void
    {
        if (! $this->db->tableExists(self::TABLE)) return;

        // Keep the medicine_id foreign key supported while removing indexes
        // introduced by this migration. The FK itself is never changed.
        if (! $this->hasLeadingNonUniqueIndex($this->indexes(), ['medicine_id'])) {
            $this->addIndexIfMissing(self::MEDICINE_INDEX, ['medicine_id']);
        }

        $indexes = $this->indexes();
        if (isset($indexes[self::IDENTITY_INDEX]) && ! $this->hasIndex($indexes, ['import_id', 'shopify_line_item_id'], true)) {
            $this->dropIndex(self::IDENTITY_INDEX);
        }

        foreach ([self::STORE_INDEX, self::ORDER_INDEX, self::PRODUCT_ID_INDEX, self::VARIANT_INDEX, self::PRODUCT_INDEX . '_variant'] as $index) {
            if (isset($this->indexes()[$index])) $this->dropIndex($index);
        }

        if (! $this->hasIndex($this->indexes(), ['import_id', 'shopify_line_item_id'], true)) {
            $this->db->query('ALTER TABLE ' . self::TABLE . ' ADD UNIQUE INDEX ' . self::IDENTITY_INDEX . ' (import_id, shopify_line_item_id)');
        }
        if (! isset($this->indexes()[self::LEGACY_UNIQUE_INDEX])) {
            $duplicate = $this->db->query(
                'SELECT shopify_product_id, shopify_variant_id, COUNT(*) AS duplicate_count
                 FROM ' . self::TABLE . '
                 GROUP BY shopify_product_id, shopify_variant_id
                 HAVING COUNT(*) > 1 LIMIT 1'
            )->getRowArray();
            if ($duplicate === null) $this->db->query('ALTER TABLE ' . self::TABLE . ' ADD UNIQUE INDEX ' . self::LEGACY_UNIQUE_INDEX . ' (shopify_product_id, shopify_variant_id)');
        }
        if ($this->db->fieldExists('shopify_order_id', self::TABLE)) $this->db->query('ALTER TABLE ' . self::TABLE . ' DROP COLUMN shopify_order_id');
        if ($this->db->fieldExists('store_domain', self::TABLE)) $this->db->query('ALTER TABLE ' . self::TABLE . ' DROP COLUMN store_domain');
    }

    private function dropIndex(string $name): void
    {
        $this->db->query('ALTER TABLE ' . self::TABLE . ' DROP INDEX ' . $name);
    }

    private function addIndexIfMissing(string $name, array $columns): void
    {
        if (! isset($this->indexes()[$name])) {
            $this->db->query('ALTER TABLE ' . self::TABLE . ' ADD INDEX ' . $name . ' (' . implode(', ', $columns) . ')');
        }
    }

    /** @return array<string, list<array{column: string, non_unique: bool}>> */
    private function indexes(): array
    {
        $grouped = [];
        foreach ($this->db->query('SHOW INDEX FROM ' . self::TABLE)->getResultArray() as $row) {
            $name = (string) ($row['Key_name'] ?? '');
            if ($name === '') continue;
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
            if (array_column($parts, 'column') !== $columns) continue;
            if ((! (bool) ($parts[0]['non_unique'] ?? true)) === $unique) return true;
        }
        return false;
    }

    /** @param array<string, list<array{column: string, non_unique: bool}>> $indexes */
    private function hasLeadingNonUniqueIndex(array $indexes, array $columns): bool
    {
        foreach ($indexes as $parts) {
            $indexedColumns = array_column($parts, 'column');
            if (array_slice($indexedColumns, 0, count($columns)) !== $columns) continue;
            if (($parts[0]['non_unique'] ?? false) === true) return true;
        }
        return false;
    }
}
