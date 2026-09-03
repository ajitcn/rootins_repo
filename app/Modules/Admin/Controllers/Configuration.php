<?php

namespace Admin\Controllers;

use Admin\Models\AdminMasterRecordModel;
use Admin\Models\AdminSettingRecordModel;
use Admin\Models\AuditLogModel;
use App\Controllers\BaseAdminController;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;
class Configuration extends BaseAdminController
{
    public const MASTERS = ['doctor-specializations' => 'Doctor Specializations', 'departments' => 'Departments', 'services' => 'Services', 'consultation-fees' => 'Consultation Fee Templates', 'commission-templates' => 'Commission Templates', 'payment-modes' => 'Payment Modes', 'tax-rates' => 'Tax Rates'];
    public const SETTINGS = ['general-settings' => 'General Settings', 'clinic-settings' => 'Clinic Settings', 'email-settings' => 'Email/SMTP Settings', 'sms-settings' => 'SMS Gateway Settings', 'whatsapp-settings' => 'WhatsApp API Settings', 'notification-settings' => 'Notification Settings', 'invoice-templates' => 'Invoice Templates', 'prescription-templates' => 'Prescription Templates', 'backup-restore' => 'Backup and Restore', 'system-logs' => 'System Logs'];
    private const MASTER_MODELS = ['doctor-specializations' => 'Admin\Models\Configuration\DoctorSpecializationModel', 'departments' => 'Admin\Models\Configuration\DepartmentModel', 'services' => 'Admin\Models\Configuration\ServiceModel', 'consultation-fees' => 'Admin\Models\Configuration\ConsultationFeeTemplateModel', 'commission-templates' => 'Admin\Models\Configuration\CommissionTemplateModel', 'payment-modes' => 'Admin\Models\Configuration\PaymentModeModel', 'tax-rates' => 'Admin\Models\Configuration\TaxRateModel'];
    private const SETTING_MODELS = ['general-settings' => 'Admin\Models\Configuration\GeneralSettingsModel', 'clinic-settings' => 'Admin\Models\Configuration\ClinicSettingsModel', 'email-settings' => 'Admin\Models\Configuration\EmailSettingsModel', 'sms-settings' => 'Admin\Models\Configuration\SmsSettingsModel', 'whatsapp-settings' => 'Admin\Models\Configuration\WhatsAppSettingsModel', 'notification-settings' => 'Admin\Models\Configuration\NotificationSettingsModel', 'invoice-templates' => 'Admin\Models\Configuration\InvoiceTemplatesModel', 'prescription-templates' => 'Admin\Models\Configuration\PrescriptionTemplatesModel'];
    private const MASTER_FIELDS = ['doctor-specializations' => ['code' => false, 'description' => false, 'amount' => false], 'departments' => ['code' => true, 'description' => true, 'amount' => false], 'services' => ['code' => true, 'description' => true, 'amount' => true], 'consultation-fees' => ['code' => true, 'description' => true, 'amount' => true], 'commission-templates' => ['code' => true, 'description' => true, 'amount' => true], 'payment-modes' => ['code' => true, 'description' => false, 'amount' => false], 'tax-rates' => ['code' => true, 'description' => true, 'amount' => true]];
    public function master(string $type): string
    {
        $title = self::MASTERS[$type] ?? 'Master';
        $search = trim((string) $this->request->getGet('search'));
        $status = (string) $this->request->getGet('status');
        $size = $this->pageSize();
        $model = $this->masterModel($type);
        $query = $model->forScope($this->clinic());
        if ($search !== '') {
            $query->groupStart()->like('name', $search)->orLike('code', $search)->groupEnd();
        }
        if (in_array($status, ['0', '1'], true)) {
            $query->where('status', (int) $status);
        }
        return view(ADMIN_VIEW . 'pages/configuration/master', ['title' => $title, 'type' => $type, 'rows' => $query->orderBy('name')->paginate($size, 'config_masters'), 'pager' => $model->pager, 'filters' => ['search' => $search, 'status' => $status, 'page_size' => $size]]);
    }
    public function settings(string $group): string
    {
        if ($group === 'system-logs') {
            return $this->systemLogs();
        }
        if ($group === 'backup-restore') {
            return $this->backupRestore();
        }
        $title = self::SETTINGS[$group] ?? 'Settings';
        $search = trim((string) $this->request->getGet('search'));
        $size = $this->pageSize();
        $model = $this->settingModel($group);
        $query = $model->forScope($this->clinic());
        if ($search !== '') {
            $query->like('setting_key', $search);
        }
        $rows = $query->orderBy('setting_key')->paginate($size, 'config_settings');
        foreach ($rows as &$row) {
            if ($row['is_secret']) {
                $row['setting_value'] = '[configured securely]';
            }
        }
        return view(ADMIN_VIEW . 'pages/configuration/settings', ['title' => $title, 'group' => $group, 'rows' => $rows, 'pager' => $model->pager, 'filters' => ['search' => $search, 'page_size' => $size]]);
    }
    private function systemLogs(): string
    {
        $search = trim((string) $this->request->getGet('search'));
        $action = trim((string) $this->request->getGet('action'));
        $size = $this->pageSize();
        $model = new AuditLogModel();
        $query = $model->orderBy('id', 'DESC');
        $current = $this->clinic();
        if ($current !== null) {
            $query->where('clinic_id', $current);
        }
        if ($search !== '') {
            $query->groupStart()->like('action', $search)->orLike('correlation_id', $search)->orLike('context', $search)->groupEnd();
        }
        if ($action !== '') {
            $query->like('action', $action);
        }
        $rows = $query->paginate($size, 'config_system_logs');
        return view(ADMIN_VIEW . 'pages/configuration/system_logs', ['rows' => $rows, 'pager' => $model->pager, 'filters' => ['search' => $search, 'action' => $action, 'page_size' => $size]]);
    }
    private function backupRestore(): string
    {
        return view(ADMIN_VIEW . 'pages/configuration/backup_restore', ['writePath' => WRITEPATH, 'writable' => is_writable(WRITEPATH), 'backupConfigured' => (bool) env('database.default.database')]);
    }
    public function saveMaster(): ResponseInterface
    {
        if (!$this->request->isAJAX()) {
            return $this->json(405, false, 'Invalid request.');
        }
        $type = trim((string) $this->request->getPost('master_type'));
        $name = trim((string) $this->request->getPost('name'));
        $id = (int) $this->request->getPost('id');
        $errors = [];
        if (!isset(self::MASTERS[$type])) {
            $errors['master_type'] = 'The master type is invalid.';
        }
        if ($name === '' || mb_strlen($name) > 255) {
            $errors['name'] = 'A valid name is required.';
        }
        if ($errors) {
            return $this->validation($errors);
        }
        $model = $this->masterModel($type);
        $fields = self::MASTER_FIELDS[$type];
        $clinic = $this->clinic();
        $duplicate = $model->forScope($clinic)->where('name', $name)->where('id !=', $id)->first();
        if ($duplicate) {
            return $this->validation(['name' => 'A record with this name already exists in the permitted scope.']);
        }
        $data = ['master_type' => $model->type(), 'name' => $name, 'code' => $fields['code'] ? trim((string) $this->request->getPost('code')) ?: null : null, 'description' => $fields['description'] ? trim((string) $this->request->getPost('description')) : null, 'amount' => $fields['amount'] ? $this->request->getPost('amount') ?: null : null, 'status' => $this->request->getPost('status') === '0' ? 0 : 1, 'clinic_id' => $clinic, 'created_by' => (int) ($this->user['id'] ?? 0)];
        try {
            if ($id) {
                $row = $model->find($id);
                if (!$row || !$this->sameScope($row['clinic_id'])) {
                    return $this->json(404, false, 'Record not found in permitted scope.');
                }
                $model->update($id, $data);
                $event = 'updated';
            } else {
                $id = $model->insert($data, true);
                if (!$id) {
                    throw new \RuntimeException('insert failed');
                }
                $event = 'created';
            }
            $this->audit->record('admin.configuration_master_' . $event, ['id' => $id, 'type' => $type, 'clinic_id' => $data['clinic_id']]);
            return $this->json(200, true, 'Master record ' . $event . '.');
        } catch (Throwable $e) {
            log_message('error', 'Configuration master failed: ' . $e->getMessage());
            return $this->json(500, false, 'The record could not be saved safely.');
        }
    }
    public function deleteMaster(int $id): ResponseInterface
    {
        if (!$this->request->isAJAX()) {
            return $this->json(405, false, 'Invalid request.');
        }
        $base = new AdminMasterRecordModel();
        $row = $base->find($id);
        if (!$row || !isset(self::MASTER_MODELS[$row['master_type']]) || !$this->sameScope($row['clinic_id'])) {
            return $this->json(404, false, 'Record not found in permitted scope.');
        }
        $model = $this->masterModel($row['master_type']);
        if (!$model->delete($id)) {
            return $this->json(500, false, 'The record could not be deleted safely.');
        }
        $this->audit->record('admin.configuration_master_deleted', ['id' => $id, 'clinic_id' => $row['clinic_id']]);
        return $this->json(200, true, 'Master record deleted.');
    }
    public function saveSetting(): ResponseInterface
    {
        if (!$this->request->isAJAX()) {
            return $this->json(405, false, 'Invalid request.');
        }
        $group = trim((string) $this->request->getPost('setting_group'));
        $key = trim((string) $this->request->getPost('setting_key'));
        if (!isset(self::SETTINGS[$group]) || $key === '' || mb_strlen($key) > 100) {
            return $this->validation(['setting_key' => 'A valid setting key is required.']);
        }
        $model = new AdminSettingRecordModel();
        $id = (int) $this->request->getPost('id');
        $row = $id ? $model->find($id) : null;
        if ($id && (!$row || !$this->sameScope($row['clinic_id']))) {
            return $this->json(404, false, 'Setting not found in permitted scope.');
        }
        $duplicate = $model->forScope($this->clinic())->where('setting_key', $key)->where('id !=', $id)->first();
        if ($duplicate) {
            return $this->validation(['setting_key' => 'A setting with this key already exists in the permitted scope.']);
        }
        $value = (string) $this->request->getPost('setting_value');
        if ($row && (int) $row['is_secret'] === 1 && $value === '') {
            $value = (string) $row['setting_value'];
        }
        $data = ['setting_group' => $group, 'setting_key' => $key, 'setting_value' => $value, 'is_secret' => $this->request->getPost('is_secret') === '1' ? 1 : 0, 'clinic_id' => $this->clinic(), 'created_by' => (int) ($this->user['id'] ?? 0)];
        try {
            if ($row) {
                $model->update($id, $data);
                $event = 'updated';
            } else {
                $id = $model->insert($data, true);
                if (!$id) {
                    throw new \RuntimeException('insert failed');
                }
                $event = 'created';
            }
            $this->audit->record('admin.configuration_setting_' . $event, ['id' => $id, 'group' => $group, 'key' => $key, 'clinic_id' => $data['clinic_id']]);
            return $this->json(200, true, 'Setting ' . $event . '.');
        } catch (Throwable $e) {
            log_message('error', 'Configuration setting failed: ' . $e->getMessage());
            return $this->json(500, false, 'The setting could not be saved safely.');
        }
    }
    private function pageSize(): int
    {
        $size = (int) $this->request->getGet('page_size');
        return in_array($size, [10, 15, 25, 50], true) ? $size : 15;
    }
    private function masterModel(string $type): AdminMasterRecordModel
    {
        require_once APPPATH . 'Modules/Admin/Models/Configuration/MasterModels.php';
        $class = self::MASTER_MODELS[$type] ?? AdminMasterRecordModel::class;
        return new $class();
    }
    private function settingModel(string $group): AdminSettingRecordModel
    {
        require_once APPPATH . 'Modules/Admin/Models/Configuration/SettingModels.php';
        $class = self::SETTING_MODELS[$group] ?? AdminSettingRecordModel::class;
        return new $class();
    }
    private function clinic(): ?int
    {
        $id = (int) ($this->user['clinic_id'] ?? session('clinic_id') ?? 0);
        return $id ?: null;
    }
    private function sameScope($id): bool
    {
        $current = $this->clinic();
        return $current === null || (int) $id === $current;
    }
    private function scope($query): void
    {
        $current = $this->clinic();
        if ($current !== null) {
            $query->groupStart()->where('clinic_id', $current)->orWhere('clinic_id', null)->groupEnd();
        }
    }
    private function validation(array $e): ResponseInterface
    {
        return $this->response->setStatusCode(422)->setJSON(['status' => false, 'msg' => (string) reset($e), 'errors' => $e]);
    }
    private function json(int $c, bool $s, string $m): ResponseInterface
    {
        return $this->response->setStatusCode($c)->setJSON(['status' => $s, 'msg' => $m]);
    }
}
