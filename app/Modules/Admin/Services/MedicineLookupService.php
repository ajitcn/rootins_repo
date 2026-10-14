<?php

namespace Admin\Services;

use Admin\Models\MedicineFormModel;
use Admin\Models\MedicineManufacturerModel;
use Admin\Models\MedicineStrengthModel;
use Admin\Models\MedicineStatusOptionModel;
use Admin\Models\MedicineUnitModel;
use Admin\Models\PrescriptionOptionModel;

final class MedicineLookupService
{
    public function options(?int $clinicId = null): array
    {
        return [
            'forms' => (new MedicineFormModel())->activeOptions($clinicId),
            'strengths' => (new MedicineStrengthModel())->activeOptions($clinicId),
            'units' => (new MedicineUnitModel())->activeOptions($clinicId),
            'manufacturers' => (new MedicineManufacturerModel())->activeOptions($clinicId),
            'statuses' => (new MedicineStatusOptionModel())->activeOptions(),
            'duration' => (new PrescriptionOptionModel())->activeOptions(PrescriptionOptionModel::TYPES['duration'], $clinicId),
            'frequency' => (new PrescriptionOptionModel())->activeOptions(PrescriptionOptionModel::TYPES['frequency'], $clinicId),
            'dose' => (new PrescriptionOptionModel())->activeOptions(PrescriptionOptionModel::TYPES['dose'], $clinicId),
            'intake' => (new PrescriptionOptionModel())->activeOptions(PrescriptionOptionModel::TYPES['intake'], $clinicId),
        ];
    }

    /**
     * @return array{records: array<string, array<string, mixed>>, errors: array<string, string>}
     */
    public function resolve(array $ids, bool $statusRequired = true, ?int $clinicId = null, array $allowInactive = []): array
    {
        $records = [];
        $errors = [];
        $definitions = [
            'form_id' => ['model' => MedicineFormModel::class, 'key' => 'form'],
            'strength_id' => ['model' => MedicineStrengthModel::class, 'key' => 'strength'],
            'unit_id' => ['model' => MedicineUnitModel::class, 'key' => 'unit'],
            'manufacturer_id' => ['model' => MedicineManufacturerModel::class, 'key' => 'manufacturer'],
            'status_id' => ['model' => MedicineStatusOptionModel::class, 'key' => 'status'],
        ];

        foreach ($definitions as $field => $definition) {
            $value = trim((string) ($ids[$field] ?? ''));

            if ($value === '') {
                if ($field === 'status_id' && $statusRequired) {
                    $errors[$field] = 'Status is required.';
                }

                continue;
            }

            if (! ctype_digit($value) || (int) $value < 1) {
                $errors[$field] = 'Select a valid database option.';

                continue;
            }
            $lookup = new $definition['model']();
            if (method_exists($lookup, 'forScope')) {
                $lookup->forScope($clinicId);
            }
            $record = $lookup->where('id', (int) $value)->first();

            if ($record === null || ((int) ($record['status'] ?? 0) !== 1 && (int) $value !== (int) ($allowInactive[$field] ?? 0))) {
                $errors[$field] = 'Select a valid active database option.';

                continue;
            }
            $records[$definition['key']] = $record;
        }

        if (isset($records['status']) && ! in_array((string) $records['status']['code'], ['active', 'inactive'], true)) {
            $errors['status_id'] = 'Select a valid medicine status.';
        }

        return ['records' => $records, 'errors' => $errors];
    }
}
