<?php

namespace Admin\Controllers;

use Admin\Models\PaymentStatusModel;
use App\Controllers\BaseAdminController;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

final class PaymentStatus extends BaseAdminController
{
    public function index(): string
    {
        $model = new PaymentStatusModel();
        $search = trim((string) $this->request->getGet('search'));
        $state = (string) $this->request->getGet('state');

        if ($search !== '') {
            $model->groupStart()->like('status_name', $search)->orLike('status_code', $search)->orLike('description', $search)->groupEnd();
        }

        if ($state === 'active') {
            $model->where('status', 1)->where('archived_at', null);
        } elseif ($state === 'inactive') {
            $model->where('status', 0)->where('archived_at', null);
        } elseif ($state === 'archived') {
            $model->where('archived_at IS NOT NULL', null, false);
        }

        $summaryQuery = db_connect()->table('payment_statuses');
        $summary = [
            'total' => $summaryQuery->countAllResults(),
            'active' => db_connect()->table('payment_statuses')->where('status', 1)->where('archived_at', null)->countAllResults(),
            'inactive' => db_connect()->table('payment_statuses')->where('status', 0)->where('archived_at', null)->countAllResults(),
            'archived' => db_connect()->table('payment_statuses')->where('archived_at IS NOT NULL', null, false)->countAllResults(),
        ];

        return view(ADMIN_VIEW . 'pages/payment_statuses/index', [
            'rows' => $model->orderBy('sort_order', 'ASC')->orderBy('status_name', 'ASC')->paginate(15, 'payment_statuses'),
            'pager' => $model->pager,
            'search' => $search,
            'state' => $state,
            'summary' => $summary,
        ]);
    }

    public function save(): ResponseInterface
    {
        if (!$this->request->isAJAX()) {
            return $this->json(405, false, 'Invalid request.');
        }

        $id = (int) $this->request->getPost('id');
        $name = trim((string) $this->request->getPost('status_name'));
        $code = strtolower(trim((string) $this->request->getPost('status_code')));
        $description = trim((string) $this->request->getPost('description'));
        $sortOrder = (int) $this->request->getPost('sort_order');
        $requestedStatus = $this->request->getPost('status') === '0' ? 0 : 1;
        $errors = [];

        if ($name === '' || mb_strlen($name) > 120) {
            $errors['status_name'] = 'Status name is required and must be 120 characters or fewer.';
        }
        if (!preg_match('/^[a-z0-9][a-z0-9_-]{1,59}$/', $code)) {
            $errors['status_code'] = 'Use 2-60 lowercase letters, numbers, hyphens, or underscores.';
        }
        if (mb_strlen($description) > 500) {
            $errors['description'] = 'Description must be 500 characters or fewer.';
        }
        if ($sortOrder < 0) {
            $errors['sort_order'] = 'Sort order must be zero or greater.';
        }

        $model = new PaymentStatusModel();
        if ($model->where('status_name', $name)->where('id !=', $id)->first()) {
            $errors['status_name'] = 'Status name already exists.';
        }
        if ($model->where('status_code', $code)->where('id !=', $id)->first()) {
            $errors['status_code'] = 'Status code already exists.';
        }
        if ($errors) {
            return $this->validation($errors);
        }

        try {
            if ($id) {
                $row = $model->find($id);
                if ($row === null) {
                    return $this->json(404, false, 'Payment status not found.');
                }
                $model->update($id, ['status_name' => $name, 'status_code' => $code, 'description' => $description !== '' ? $description : null, 'sort_order' => $sortOrder, 'status' => !empty($row['archived_at']) ? 0 : $requestedStatus]);
                $event = 'updated';
            } else {
                $id = (int) $model->insert(['status_name' => $name, 'status_code' => $code, 'description' => $description !== '' ? $description : null, 'sort_order' => $sortOrder, 'status' => 1, 'archived_at' => null], true);
                $event = 'created';
            }
            $this->audit->record('admin.payment_status_' . $event, ['id' => $id, 'status_code' => $code]);

            return $this->json(200, true, 'Payment status ' . $event . '.');
        } catch (Throwable $exception) {
            log_message('error', 'Payment status save failed: ' . $exception->getMessage());

            return $this->json(500, false, 'Payment status could not be saved safely.');
        }
    }

    public function toggle(int $id): ResponseInterface
    {
        $model = new PaymentStatusModel();
        $row = $model->find($id);
        if ($row === null) {
            return $this->json(404, false, 'Payment status not found.');
        }
        if (!empty($row['archived_at'])) {
            return $this->json(422, false, 'Archived payment statuses cannot be activated.');
        }
        $status = (int) $row['status'] === 1 ? 0 : 1;
        $model->update($id, ['status' => $status]);
        $this->audit->record('admin.payment_status_' . ($status === 1 ? 'activated' : 'deactivated'), ['id' => $id, 'status_code' => $row['status_code']]);

        return $this->json(200, true, 'Payment status ' . ($status === 1 ? 'activated' : 'deactivated') . '.');
    }

    public function archive(int $id): ResponseInterface
    {
        $model = new PaymentStatusModel();
        $row = $model->find($id);
        if ($row === null) {
            return $this->json(404, false, 'Payment status not found.');
        }
        $model->update($id, ['status' => 0, 'archived_at' => date('Y-m-d H:i:s')]);
        $this->audit->record('admin.payment_status_archived', ['id' => $id, 'status_code' => $row['status_code']]);

        return $this->json(200, true, 'Payment status archived.');
    }

    private function validation(array $errors): ResponseInterface
    {
        $message = (string) (reset($errors) ?: 'Please correct the highlighted fields.');

        return $this->response
            ->setStatusCode(422)
            ->setJSON(['status' => false, 'msg' => $message, 'errors' => $errors]);
    }

    private function json(int $code, bool $status, string $message, array $data = []): ResponseInterface
    {
        return $this->response
            ->setStatusCode($code)
            ->setJSON(array_merge(['status' => $status, 'msg' => $message], $data));
    }
}
