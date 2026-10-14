<?php

namespace Admin\Controllers\Medicine;

use Admin\Models\Configuration\PrescriptionDoseModel;
use Admin\Models\Configuration\PrescriptionDurationModel;
use Admin\Models\Configuration\PrescriptionFrequencyModel;
use Admin\Models\Configuration\PrescriptionIntakeModel;
use Admin\Models\MedicineFormModel;
use Admin\Models\MedicineManufacturerModel;
use Admin\Models\MedicineStrengthModel;
use Admin\Models\MedicineUnitModel;
use App\Controllers\BaseAdminController;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

final class MedicineLookup extends BaseAdminController
{
    private const TYPES = [
        'forms' => ['title' => 'Medicine Forms/Types', 'model' => MedicineFormModel::class, 'code' => false, 'scoped' => false],
        'strengths' => ['title' => 'Strengths', 'model' => MedicineStrengthModel::class, 'code' => false, 'scoped' => false],
        'units' => ['title' => 'Units', 'model' => MedicineUnitModel::class, 'code' => false, 'scoped' => false],
        'manufacturers' => ['title' => 'Manufacturers', 'model' => MedicineManufacturerModel::class, 'code' => false, 'scoped' => false],
        'durations' => ['title' => 'Default Durations', 'model' => PrescriptionDurationModel::class, 'code' => true, 'scoped' => true],
        'frequencies' => ['title' => 'Default Frequencies', 'model' => PrescriptionFrequencyModel::class, 'code' => true, 'scoped' => true],
        'doses' => ['title' => 'Default Doses', 'model' => PrescriptionDoseModel::class, 'code' => true, 'scoped' => true],
        'intakes' => ['title' => 'Default Intakes', 'model' => PrescriptionIntakeModel::class, 'code' => true, 'scoped' => true],
    ];

    public function index(): string
    {
        $type = $this->validType((string) $this->request->getGet('type'));
        $search = trim((string) $this->request->getGet('search'));
        $pageSize = (int) $this->request->getGet('page_size');
        $pageSize = in_array($pageSize, [10, 15, 25, 50], true) ? $pageSize : 15;
        $definition = self::TYPES[$type];
        $model = $this->modelFor($type);
        $rows = [];
        $pager = null;
        $pageError = null;
        try {
            if ($definition['scoped']) $model->forScope($this->currentClinic());
            if ($search !== '') {
                $model->groupStart()->like('name', $search);
                if (self::TYPES[$type]['code']) $model->orLike('code', $search);
                $model->groupEnd();
            }
            $rows = ($definition['scoped'] ? $model->orderBy('clinic_id', 'DESC') : $model)->orderBy('name', 'ASC')->paginate($pageSize, 'medicine_lookups');
            $pager = $model->pager;
        } catch (Throwable $e) {
            log_message('error', 'Medicine lookup list failed for ' . $type . ': ' . $e->getMessage());
            $pageError = 'This lookup could not be loaded. Please contact an administrator.';
        }

        return view(ADMIN_VIEW . 'pages/medicine/lookups', [
            'type' => $type, 'lookupType' => $type, 'types' => self::TYPES,
            'rows' => $rows, 'pager' => $pager, 'pageError' => $pageError, 'filters' => ['search' => $search, 'page_size' => $pageSize],
            'canManage' => $this->hasPermission('settings.manage'),
        ]);
    }

    public function edit(string $type, int $id): ResponseInterface
    {
        if (! $this->isAjax('GET')) return $this->json(405, false, 'Invalid request.');
        if (! $this->isValidType($type)) return $this->json(404, false, 'Lookup type not found.');
        $type = $this->validType($type);
        $model = $this->modelFor($type);
        if (self::TYPES[$type]['scoped']) $model->forScope($this->currentClinic());
        $row = $model->where('id', $id)->first();

        return $row === null ? $this->json(404, false, 'Lookup option not found.') : $this->response->setJSON(['status' => true, 'msg' => '', 'data' => $row]);
    }

    public function save(string $type): ResponseInterface
    {
        if (! $this->isAjax('POST')) return $this->json(405, false, 'Invalid request.');
        if (! $this->isValidType($type)) return $this->json(404, false, 'Lookup type not found.');
        $type = $this->validType($type);
        $definition = self::TYPES[$type];
        $model = $this->modelFor($type);
        $id = (int) $this->request->getPost('id');
        $name = trim((string) $this->request->getPost('name'));
        $code = trim((string) $this->request->getPost('code'));
        $errors = [];
        if ($name === '' || mb_strlen($name) > ($type === 'manufacturers' ? 255 : 100)) $errors['name'] = 'A valid lookup value is required.';
        if ($definition['code']) {
            $code = $this->normaliseCode($type, $name, $code);
            if ($code === '') $errors['code'] = 'A valid code is required.';
            if ($type === 'durations' && (! ctype_digit($code) || (int) $code < 1 || (int) $code > 3650)) $errors['code'] = 'Duration must be between 1 and 3650 days.';
        }
        $clinicId = $this->currentClinic();
        $scope = $clinicId === null ? 'global' : (string) $clinicId;
        $duplicate = $definition['scoped'] ? $model->where('scope_key', $scope) : $model;
        if ($duplicate->where('normalized_name', mb_strtolower($name))->where('id !=', $id)->first() !== null) $errors['name'] = 'This lookup option already exists.';
        if ($definition['code']) {
            $duplicateCode = $definition['scoped'] ? $model->where('scope_key', $scope) : $model;
            if ($duplicateCode->where('normalized_code', mb_strtolower($code))->where('id !=', $id)->first() !== null) $errors['code'] = 'This lookup code already exists in the selected scope.';
        }
        if ($errors !== []) return $this->validation($errors);

        $existing = $id ? $model->where('id', $id)->first() : null;
        if ($id && ($existing === null || ($definition['scoped'] && ! $this->sameScope($existing['clinic_id'] ?? null)))) return $this->json(404, false, 'Lookup option not found in the permitted scope.');
        $submittedStatus = $this->request->getPost('status');
        $status = $existing === null ? 1 : (int) $existing['status'];
        if ($existing !== null && in_array((string) $submittedStatus, ['0', '1'], true)) $status = (int) $submittedStatus;
        $data = ['name' => $name, 'normalized_name' => mb_strtolower($name), 'status' => $status];
        if ($definition['scoped']) $data += ['clinic_id' => $clinicId, 'scope_key' => $scope, 'created_by' => (int) ($this->user['id'] ?? 0) ?: null];
        if ($definition['code']) { $data['code'] = $code; $data['normalized_code'] = mb_strtolower($code); }

        $db = db_connect(); $db->transBegin();
        try {
            if ($existing !== null) { if (! $model->update($id, $data)) throw new \RuntimeException('Lookup update failed.'); $event = 'updated'; }
            else { $id = (int) $model->insert($data, true); if (! $id) throw new \RuntimeException('Lookup insert failed.'); $event = 'created'; }
            if ($db->transStatus() === false || ! $db->transCommit()) throw new \RuntimeException('Lookup transaction failed.');
            $this->audit->record('admin.medicine_lookup_' . $event, ['lookup_id' => $id, 'lookup_type' => $type, 'clinic_id' => $clinicId]);

            return $this->response->setJSON(['status' => true, 'msg' => 'Lookup value ' . $event . ' successfully.']);
        } catch (Throwable $e) {
            $db->transRollback(); log_message('error', 'Medicine lookup save failed: ' . $e->getMessage());

            return $this->json(500, false, 'The lookup value could not be saved safely.');
        }
    }

    public function status(string $type, int $id): ResponseInterface
    {
        if (! $this->isAjax('POST')) return $this->json(405, false, 'Invalid request.');
        if (! $this->isValidType($type)) return $this->json(404, false, 'Lookup type not found.');
        $type = $this->validType($type); $model = $this->modelFor($type);
        if (self::TYPES[$type]['scoped']) $model->forScope($this->currentClinic());
        $row = $model->where('id', $id)->first();
        if ($row === null) return $this->json(404, false, 'Lookup option not found.');
        $active = (int) $this->request->getPost('status') === 1;
        if (! $model->update($id, ['status' => $active ? 1 : 0])) return $this->json(500, false, 'The lookup status could not be updated.');
        $this->audit->record('admin.medicine_lookup_' . ($active ? 'activated' : 'deactivated'), ['lookup_id' => $id, 'lookup_type' => $type, 'clinic_id' => self::TYPES[$type]['scoped'] ? ($row['clinic_id'] ?? null) : null]);

        return $this->response->setJSON(['status' => true, 'msg' => 'Lookup value ' . ($active ? 'activated' : 'deactivated') . ' successfully.']);
    }

    public function delete(string $type, int $id): ResponseInterface
    {
        return $this->status($type, $id);
    }

    private function modelFor(string $type)
    {
        $class = self::TYPES[$this->validType($type)]['model'];

        return new $class();
    }

    private function validType(string $type): string { return array_key_exists($type, self::TYPES) ? $type : 'units'; }
    private function isValidType(string $type): bool { return array_key_exists($type, self::TYPES); }

    private function normaliseCode(string $type, string $name, string $code): string
    {
        if ($code === '') {
            if ($type === 'durations') { preg_match('/\d+/', $name, $matches); $code = $matches[0] ?? ''; }
            else $code = $name;
        }

        return preg_replace('/[^a-zA-Z0-9_-]+/', '_', strtolower(trim($code))) ?? '';
    }

    private function currentClinic(): ?int { $id = (int) ($this->user['clinic_id'] ?? session('clinic_id') ?? 0); return $id ?: null; }
    private function sameScope($rowClinic): bool { $current = $this->currentClinic(); return $current === null || $rowClinic === null || (int) $rowClinic === $current; }
    private function isAjax(string $method): bool { return $this->request->isAJAX() && $this->request->getMethod(true) === $method; }
    private function validation(array $errors): ResponseInterface { return $this->response->setStatusCode(422)->setJSON(['status' => false, 'msg' => (string) reset($errors), 'errors' => $errors]); }
    private function json(int $status, bool $success, string $message): ResponseInterface { return $this->response->setStatusCode($status)->setJSON(['status' => $success, 'msg' => $message]); }
}
