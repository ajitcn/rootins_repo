<?php
namespace Admin\Controllers;
use Admin\Models\PaymentProviderModel;
use App\Controllers\BaseAdminController;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

final class PaymentProvider extends BaseAdminController
{
    public function index(): string
    {
        $model = new PaymentProviderModel(); $search = trim((string) $this->request->getGet('search')); $state = (string) $this->request->getGet('state');
        if ($search !== '') $model->groupStart()->like('provider_name', $search)->orLike('provider_code', $search)->orLike('provider_type', $search)->groupEnd();
        if ($state === 'active') $model->where('status', 1)->where('archived_at', null); elseif ($state === 'inactive') $model->where('status', 0)->where('archived_at', null); elseif ($state === 'archived') $model->where('archived_at IS NOT NULL', null, false);
        $table = db_connect()->table('payment_providers');
        $summary = ['total' => $table->countAllResults(), 'active' => db_connect()->table('payment_providers')->where('status', 1)->where('archived_at', null)->countAllResults(), 'inactive' => db_connect()->table('payment_providers')->where('status', 0)->where('archived_at', null)->countAllResults(), 'archived' => db_connect()->table('payment_providers')->where('archived_at IS NOT NULL', null, false)->countAllResults()];
        return view(ADMIN_VIEW . 'pages/payment_providers/index', ['rows' => $model->orderBy('sort_order', 'ASC')->orderBy('provider_name', 'ASC')->paginate(15, 'payment_providers'), 'pager' => $model->pager, 'search' => $search, 'state' => $state, 'summary' => $summary]);
    }
    public function save(): ResponseInterface
    {
        if (!$this->request->isAJAX()) return $this->json(405, false, 'Invalid request.');
        $id = (int) $this->request->getPost('id'); $name = trim((string) $this->request->getPost('provider_name')); $code = strtolower(trim((string) $this->request->getPost('provider_code'))); $type = strtolower(trim((string) $this->request->getPost('provider_type'))); $currency = strtoupper(trim((string) $this->request->getPost('default_currency'))); $sort = (int) $this->request->getPost('sort_order'); $status = $this->request->getPost('status') === '0' ? 0 : 1; $errors = [];
        if ($name === '' || mb_strlen($name) > 120) $errors['provider_name'] = 'Provider name is required and must be 120 characters or fewer.';
        if (!preg_match('/^[a-z0-9][a-z0-9_-]{1,59}$/', $code)) $errors['provider_code'] = 'Use 2-60 lowercase letters, numbers, hyphens, or underscores.';
        if (!in_array($type, ['manual', 'gateway', 'bank', 'wallet', 'other'], true)) $errors['provider_type'] = 'Select a valid provider type.';
        if ($currency !== '' && !preg_match('/^[A-Z]{3}$/', $currency)) $errors['default_currency'] = 'Currency must be a 3-letter ISO code.';
        if ($sort < 0) $errors['sort_order'] = 'Sort order must be zero or greater.';
        $model = new PaymentProviderModel(); if ($model->where('provider_name', $name)->where('id !=', $id)->first()) $errors['provider_name'] = 'Provider name already exists.'; if ($model->where('provider_code', $code)->where('id !=', $id)->first()) $errors['provider_code'] = 'Provider code already exists.'; if ($errors) return $this->validation($errors);
        try { $data = ['provider_name' => $name, 'provider_code' => $code, 'provider_type' => $type, 'default_currency' => $currency !== '' ? $currency : null, 'sort_order' => $sort, 'status' => $status]; if ($id) { $row = $model->find($id); if (!$row) return $this->json(404, false, 'Payment provider not found.'); if (!empty($row['archived_at'])) $data['status'] = 0; $model->update($id, $data); $event = 'updated'; } else { $data['archived_at'] = null; $id = (int) $model->insert($data, true); $event = 'created'; } $this->audit->record('admin.payment_provider_' . $event, ['id' => $id, 'provider_code' => $code]); return $this->json(200, true, 'Payment provider ' . $event . '.'); } catch (Throwable $e) { log_message('error', 'Payment provider save failed: ' . $e->getMessage()); $this->audit->record('admin.payment_provider_failure', ['id' => $id, 'provider_code' => $code]); return $this->json(500, false, 'Payment provider could not be saved safely.'); }
    }
    public function toggle(int $id): ResponseInterface { $model = new PaymentProviderModel(); $row = $model->find($id); if (!$row) return $this->json(404, false, 'Payment provider not found.'); if (!empty($row['archived_at'])) return $this->json(422, false, 'Archived payment providers cannot be activated.'); $status = (int) $row['status'] === 1 ? 0 : 1; $model->update($id, ['status' => $status]); $this->audit->record('admin.payment_provider_' . ($status ? 'activated' : 'deactivated'), ['id' => $id, 'provider_code' => $row['provider_code']]); return $this->json(200, true, 'Payment provider ' . ($status ? 'activated' : 'deactivated') . '.'); }
    public function archive(int $id): ResponseInterface { $model = new PaymentProviderModel(); $row = $model->find($id); if (!$row) return $this->json(404, false, 'Payment provider not found.'); $model->update($id, ['status' => 0, 'archived_at' => date('Y-m-d H:i:s')]); $this->audit->record('admin.payment_provider_archived', ['id' => $id, 'provider_code' => $row['provider_code']]); return $this->json(200, true, 'Payment provider archived.'); }
    private function validation(array $errors): ResponseInterface { return $this->response->setStatusCode(422)->setJSON(['status' => false, 'msg' => (string) (reset($errors) ?: 'Please correct the highlighted fields.'), 'errors' => $errors]); }
    private function json(int $code, bool $status, string $message, array $data = []): ResponseInterface { return $this->response->setStatusCode($code)->setJSON(array_merge(['status' => $status, 'msg' => $message], $data)); }
}
