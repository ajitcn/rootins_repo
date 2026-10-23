<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Removes accidental single-column UNIQUE indexes from reusable medicine
 * attributes while retaining foreign keys and normal lookup indexes.
 */
final class RepairMedicineMasterIndexes extends Migration
{
    /** @var list<string> */
    private const REUSABLE_COLUMNS = [
        'strength_id',
        'unit_id',
        'manufacturer_id',
        'form_id',
        'dosage_form',
        'strength',
        'unit',
        'manufacturer',
        'default_duration',
        'default_frequency',
        'default_dose',
        'default_intake',
        'prescription_duration_id',
        'prescription_frequency_id',
        'prescription_dose_id',
        'prescription_intake_id',
    ];

    public function up(): void
    {
        if (! $this->db->tableExists('medicines')) {
            return;
        }

        foreach ($this->singleColumnUniqueIndexes() as $index) {
            $column = $index['column'];

            if (! in_array($column, self::REUSABLE_COLUMNS, true)) {
                continue;
            }

            $normalIndex = 'idx_medicines_' . $column;

            if ($index['name'] === $normalIndex) {
                $temporaryIndex = 'repair_medicines_' . $column;

                if (! $this->hasIndex($temporaryIndex)) {
                    $this->addNormalIndex($temporaryIndex, $column);
                }
                $this->dropIndex($index['name']);
                $this->db->query(sprintf(
                    'ALTER TABLE medicines RENAME INDEX %s TO %s',
                    $this->identifier($temporaryIndex),
                    $this->identifier($normalIndex),
                ));
            } else {
                if (! $this->hasIndex($normalIndex)) {
                    $this->addNormalIndex($normalIndex, $column);
                }
                $this->dropIndex($index['name']);
            }
            log_message('notice', sprintf('Removed unintended unique medicine index %s on %s.', $index['name'], $column));
        }

        foreach (self::REUSABLE_COLUMNS as $column) {
            if (! $this->db->fieldExists($column, 'medicines')) {
                continue;
            }

            $index = 'idx_medicines_' . $column;

            if (! $this->hasIndex($index)) {
                $this->addNormalIndex($index, $column);
            }
        }

        $this->reportExistingExactDuplicates();
    }

    public function down(): void
    {
        if (! $this->db->tableExists('medicines')) {
            return;
        }

        foreach (self::REUSABLE_COLUMNS as $column) {
            if (! $this->db->fieldExists($column, 'medicines') || $this->hasUniqueSingleColumnIndex($column)) {
                continue;
            }

            if ($this->hasDuplicateValues($column)) {
                log_message('warning', sprintf('Skipped restoring unique medicine index for %s because duplicate values exist.', $column));

                continue;
            }

            $index = 'legacy_medicines_' . $column . '_unique';
            $this->db->query(sprintf(
                'ALTER TABLE medicines ADD UNIQUE INDEX %s (%s)',
                $this->identifier($index),
                $this->identifier($column),
            ));
        }
    }

    /** @return list<array{name: string, column: string}> */
    private function singleColumnUniqueIndexes(): array
    {
        $indexes = [];
        $current = null;

        foreach ($this->db->query('SHOW INDEX FROM medicines')->getResultArray() as $row) {
            $name = (string) ($row['Key_name'] ?? '');

            if ($name === 'PRIMARY' || (int) ($row['Non_unique'] ?? 1) !== 0) {
                continue;
            }

            if ($current === null || $current['name'] !== $name) {
                if ($current !== null && $current['count'] === 1) {
                    $indexes[] = ['name' => $current['name'], 'column' => $current['column']];
                }
                $current = ['name' => $name, 'count' => 0, 'column' => ''];
            }

            $current['count']++;
            $current['column'] = (string) ($row['Column_name'] ?? '');
        }

        if ($current !== null && $current['count'] === 1) {
            $indexes[] = ['name' => $current['name'], 'column' => $current['column']];
        }

        return $indexes;
    }

    private function hasIndex(string $name): bool
    {
        foreach ($this->db->query('SHOW INDEX FROM medicines')->getResultArray() as $row) {
            if ((string) ($row['Key_name'] ?? '') === $name) {
                return true;
            }
        }

        return false;
    }

    private function addNormalIndex(string $name, string $column): void
    {
        $this->db->query(sprintf(
            'ALTER TABLE medicines ADD INDEX %s (%s)',
            $this->identifier($name),
            $this->identifier($column),
        ));
    }

    private function dropIndex(string $name): void
    {
        $this->db->query(sprintf(
            'ALTER TABLE medicines DROP INDEX %s',
            $this->identifier($name),
        ));
    }

    private function hasUniqueSingleColumnIndex(string $column): bool
    {
        foreach ($this->singleColumnUniqueIndexes() as $index) {
            if ($index['column'] === $column) {
                return true;
            }
        }

        return false;
    }

    private function hasDuplicateValues(string $column): bool
    {
        return (int) $this->db->query(sprintf(
            'SELECT 1 FROM medicines WHERE %s IS NOT NULL GROUP BY %s HAVING COUNT(*) > 1 LIMIT 1',
            $this->identifier($column),
            $this->identifier($column),
        ))->getNumRows() > 0;
    }

    private function reportExistingExactDuplicates(): void
    {
        if (! $this->db->fieldExists('medicine_name', 'medicines')) {
            return;
        }

        $rows = $this->db->query(
            "SELECT LOWER(TRIM(COALESCE(medicine_name, ''))) AS medicine_name_normalized,
                    LOWER(TRIM(COALESCE(dosage_form, ''))) AS form_normalized,
                    LOWER(TRIM(COALESCE(strength, ''))) AS strength_normalized,
                    LOWER(TRIM(COALESCE(unit, ''))) AS unit_normalized,
                    LOWER(TRIM(COALESCE(manufacturer, ''))) AS manufacturer_normalized,
                    COUNT(*) AS duplicate_count
             FROM medicines
             GROUP BY medicine_name_normalized, form_normalized, strength_normalized, unit_normalized, manufacturer_normalized
             HAVING COUNT(*) > 1"
        )->getResultArray();

        foreach ($rows as $row) {
            log_message('warning', 'Existing exact medicine duplicate combination detected; no records were deleted: ' . json_encode($row));
        }
    }

    private function identifier(string $value): string
    {
        return '`' . str_replace('`', '``', $value) . '`';
    }
}
