<?php

namespace Admin\Controllers\Doctor;

use Admin\Models\Master\DoctorLanguageModel;
use Admin\Models\Master\PopularTreatmentModel;
use App\Controllers\BaseAdminController;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

final class DoctorMaster extends BaseAdminController
{
    private const TYPES = [
        'languages' => ['title' => 'Doctor Languages', 'singular' => 'Language', 'field' => 'language_name', 'permission' => 'doctor_language', 'model' => DoctorLanguageModel::class, 'max' => 100],
        'treatments' => ['title' => 'Popular Treatments', 'singular' => 'Popular Treatment', 'field' => 'treatment_name', 'permission' => 'popular_treatment', 'model' => PopularTreatmentModel::class, 'max' => 150],
    ];

    public function languages(): string { return $this->index('languages'); }
    public function treatments(): string { return $this->index('treatments'); }

    public function save(string $type): ResponseInterface
    {
        if (! $this->request->isAJAX() || $this->request->getMethod(true) !== 'POST') return $this->json(405, false, 'Invalid request.');
        $definition = $this->definition($type);
        $model = $this->model($definition);
        $id = (int) $this->request->getPost('id');
        $name = trim((string) $this->request->getPost('name'));
        $errors = [];
        if ($name === '' || mb_strlen($name) > $definition['max']) $errors['name'] = 'Enter a valid ' . strtolower($definition['singular']) . '.';
        $existing = $id > 0 ? $model->find($id) : null;
        if ($id > 0 && $existing === null) return $this->json(404, false, 'Master value not found.');
        if ($model->where('normalized_name', mb_strtolower($name))->where('id !=', $id)->first() !== null) $errors['name'] = 'This value already exists.';
        if ($errors !== []) return $this->response->setStatusCode(422)->setJSON(['status' => false, 'msg' => reset($errors), 'errors' => $errors]);

        $data = [$definition['field'] => $name, 'normalized_name' => mb_strtolower($name), 'status' => $existing === null ? 1 : (int) $existing['status']];
        try {
            $savedId = $id > 0 ? ($model->update($id, $data) ? $id : 0) : (int) $model->insert($data, true);
            if ($savedId < 1) throw new \RuntimeException('Master save failed.');
            $this->audit->record('admin.doctor_master_' . ($id > 0 ? 'updated' : 'created'), ['master_type' => $type, 'master_id' => $savedId]);

            return $this->response->setJSON(['status' => true, 'msg' => $definition['singular'] . ($id > 0 ? ' updated.' : ' added.')]);
        } catch (Throwable $exception) {
            log_message('error', 'Doctor master save failed type=' . $type . ' exception=' . get_class($exception) . ' line=' . $exception->getLine());

            return $this->json(500, false, 'The master value could not be saved safely.');
        }
    }

    public function toggle(string $type, int $id): ResponseInterface
    {
        if (! $this->request->isAJAX() || $this->request->getMethod(true) !== 'POST') return $this->json(405, false, 'Invalid request.');
        $definition = $this->definition($type);
        $model = $this->model($definition);
        $row = $model->find($id);
        if ($row === null) return $this->json(404, false, 'Master value not found.');
        $status = (int) $this->request->getPost('status') === 1 ? 1 : 0;
        if (! $model->update($id, ['status' => $status])) return $this->json(500, false, 'The master status could not be updated.');
        $this->audit->record('admin.doctor_master_' . ($status ? 'activated' : 'deactivated'), ['master_type' => $type, 'master_id' => $id]);

        return $this->response->setJSON(['status' => true, 'msg' => $definition['singular'] . ($status ? ' activated.' : ' deactivated.')]);
    }

    private function index(string $type): string
    {
        $definition = $this->definition($type);
        $model = $this->model($definition);
        $search = trim((string) $this->request->getGet('search'));
        $pageSize = (int) $this->request->getGet('page_size');
        $pageSize = in_array($pageSize, [10, 15, 25, 50], true) ? $pageSize : 15;
        $page = max(1, (int) $this->request->getGet('page'));
        if ($search !== '') $model->like($definition['field'], $search);
        $total = $model->countAllResults(false);
        $pages = max(1, (int) ceil($total / $pageSize));
        $page = min($page, $pages);
        $rows = $model->orderBy($definition['field'], 'ASC')->limit($pageSize, ($page - 1) * $pageSize)->find();

        return view(ADMIN_VIEW . 'pages/doctor/master', ['type' => $type, 'definition' => $definition, 'rows' => $rows, 'search' => $search, 'pagination' => ['page' => $page, 'pages' => $pages, 'page_size' => $pageSize, 'total' => $total]]);
    }

    private function definition(string $type): array
    {
        if (! isset(self::TYPES[$type])) throw new \CodeIgniter\Exceptions\PageNotFoundException('Doctor master not found.');

        return self::TYPES[$type];
    }

    private function model(array $definition): object { return new $definition['model'](); }

    private function json(int $status, bool $success, string $message): ResponseInterface { return $this->response->setStatusCode($status)->setJSON(['status' => $success, 'msg' => $message]); }
}
