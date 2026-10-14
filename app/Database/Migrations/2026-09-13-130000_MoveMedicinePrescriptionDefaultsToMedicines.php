<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Makes prescription defaults part of the global medicine master record.
 *
 * The legacy medicine_prescription_defaults table is intentionally retained
 * as a compatibility/reconciliation source. This migration copies the best
 * active legacy row per medicine, preferring a global row and then the latest
 * row, without overwriting values already present on medicines.
 */
class MoveMedicinePrescriptionDefaultsToMedicines extends Migration
{
    private const DEFAULT_FIELDS = [
        'prescription_duration_id' => ['table' => 'prescription_durations', 'column' => 'duration_id', 'constraint' => 'fk_medicines_prescription_duration_id'],
        'prescription_frequency_id' => ['table' => 'prescription_frequencies', 'column' => 'frequency_id', 'constraint' => 'fk_medicines_prescription_frequency_id'],
        'prescription_dose_id' => ['table' => 'prescription_doses', 'column' => 'dose_id', 'constraint' => 'fk_medicines_prescription_dose_id'],
        'prescription_intake_id' => ['table' => 'prescription_intakes', 'column' => 'intake_id', 'constraint' => 'fk_medicines_prescription_intake_id'],
    ];

    public function up(): void
    {
        if (! $this->db->tableExists('medicines')) {
            return;
        }

        foreach (self::DEFAULT_FIELDS as $field => $definition) {
            if (! $this->db->fieldExists($field, 'medicines')) {
                $this->forge->addColumn('medicines', [
                    $field => [
                        'type' => 'INT',
                        'constraint' => 10,
                        'unsigned' => true,
                        'null' => true,
                    ],
                ]);
            }
        }

        if (! $this->db->fieldExists('prescription_note', 'medicines')) {
            $this->forge->addColumn('medicines', [
                'prescription_note' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
            ]);
        }

        foreach (self::DEFAULT_FIELDS as $field => $definition) {
            $index = 'idx_medicines_' . $field;

            if (! $this->hasIndex($index)) {
                $this->db->query(sprintf('ALTER TABLE medicines ADD INDEX %s (%s)', $index, $field));
            }

            if (! $this->hasForeignKey($definition['constraint'])) {
                $this->db->query(sprintf(
                    'ALTER TABLE medicines ADD CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s(id) ON UPDATE CASCADE ON DELETE SET NULL',
                    $definition['constraint'],
                    $field,
                    $definition['table'],
                ));
            }
        }

        $this->backfillFromLegacyDefaults();
    }

    public function down(): void
    {
        if (! $this->db->tableExists('medicines')) {
            return;
        }

        foreach (array_reverse(self::DEFAULT_FIELDS) as $field => $definition) {
            if ($this->hasForeignKey($definition['constraint'])) {
                $this->db->query(sprintf('ALTER TABLE medicines DROP FOREIGN KEY %s', $definition['constraint']));
            }
            $index = 'idx_medicines_' . $field;

            if ($this->hasIndex($index)) {
                $this->db->query(sprintf('ALTER TABLE medicines DROP INDEX %s', $index));
            }
        }

        $columns = array_keys(self::DEFAULT_FIELDS);

        if ($this->db->fieldExists('prescription_note', 'medicines')) {
            $columns[] = 'prescription_note';
        }

        if ($columns !== []) {
            $this->forge->dropColumn('medicines', $columns);
        }
    }

    private function backfillFromLegacyDefaults(): void
    {
        if (! $this->db->tableExists('medicine_prescription_defaults')) {
            return;
        }

        $rows = $this->db->table('medicine_prescription_defaults')
            ->where('status', 1)
            ->orderBy('medicine_id', 'ASC')
            ->get()
            ->getResultArray();
        $byMedicine = [];

        foreach ($rows as $row) {
            $medicineId = (int) ($row['medicine_id'] ?? 0);

            if ($medicineId > 0) {
                $byMedicine[$medicineId][] = $row;
            }
        }

        foreach ($byMedicine as $medicineId => $medicineRows) {
            usort($medicineRows, static function (array $left, array $right): int {
                $leftGlobal = ($left['scope_key'] ?? '') === 'global' ? 1 : 0;
                $rightGlobal = ($right['scope_key'] ?? '') === 'global' ? 1 : 0;

                if ($leftGlobal !== $rightGlobal) {
                    return $rightGlobal <=> $leftGlobal;
                }
                $leftUpdated = (string) ($left['updated_at'] ?? '');
                $rightUpdated = (string) ($right['updated_at'] ?? '');

                if ($leftUpdated !== $rightUpdated) {
                    return strcmp($rightUpdated, $leftUpdated);
                }

                return ((int) ($right['id'] ?? 0)) <=> ((int) ($left['id'] ?? 0));
            });

            $source = $medicineRows[0];
            $medicine = $this->db->table('medicines')->where('id', (int) $medicineId)->get()->getRowArray();

            if ($medicine === null) {
                continue;
            }

            $update = [];

            foreach (self::DEFAULT_FIELDS as $target => $definition) {
                $sourceId = (int) ($source[$definition['column']] ?? 0);

                if (($medicine[$target] ?? null) === null && $sourceId > 0 && $this->lookupExists($definition['table'], $sourceId)) {
                    $update[$target] = $sourceId;
                }
            }
            $sourceNote = trim((string) ($source['note'] ?? ''));

            if (trim((string) ($medicine['prescription_note'] ?? '')) === '' && $sourceNote !== '') {
                $update['prescription_note'] = $sourceNote;
            }

            if ($update !== []) {
                $this->db->table('medicines')->where('id', (int) $medicineId)->update($update);
            }
        }
    }

    private function lookupExists(string $table, int $id): bool
    {
        return $id > 0
            && $this->db->table($table)
                ->where('id', $id)
                ->where('clinic_id', null)
                ->countAllResults() > 0;
    }

    private function hasIndex(string $name): bool
    {
        foreach ($this->db->query('SHOW INDEX FROM medicines')->getResultArray() as $index) {
            if ((string) ($index['Key_name'] ?? '') === $name) {
                return true;
            }
        }

        return false;
    }

    private function hasForeignKey(string $name): bool
    {
        $row = $this->db->query(
            'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?',
            ['medicines', $name],
        )->getRowArray();

        return $row !== null;
    }
}
