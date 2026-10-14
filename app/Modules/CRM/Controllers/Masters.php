<?php

namespace CRM\Controllers;

use App\Controllers\BaseAdminController;
use CRM\Services\CrmMasterService;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;
use Throwable;

final class Masters extends BaseAdminController
{
    private CrmMasterService $masters;
    public function initController(\CodeIgniter\HTTP\RequestInterface $request, ResponseInterface $response, \Psr\Log\LoggerInterface $logger) { parent::initController($request, $response, $logger); $user = session('user') ?? []; $this->masters = new CrmMasterService(null, (int) ($user['clinic_id'] ?? 0)); }
    public function index(string $slug): string { $d = $this->masters->definition($slug); $this->assertPermission($d, 'view'); [$rows, $pager, $total] = $this->masters->rows($slug, (string) $this->request->getGet('search'), max(1, (int) $this->request->getGet('page')), 15); return view(CRM_VIEW . 'pages/masters', ['definition' => $d, 'slug' => $slug, 'rows' => $rows, 'pager' => $pager, 'total' => $total, 'search' => (string) $this->request->getGet('search')]); }
    public function save(string $slug, ?int $id = null): ResponseInterface { try { $d = $this->masters->definition($slug); $this->assertPermission($d, $id ? 'edit' : 'create'); $newId = $this->masters->save($slug, $this->request->getPost(), $id, (int) ($this->user['id'] ?? 0)); return $this->json(200, true, 'CRM master saved.', ['id' => $newId]); } catch (RuntimeException $e) { $decoded = json_decode($e->getMessage(), true); return is_array($decoded) ? $this->response->setStatusCode(422)->setJSON(['status' => false, 'msg' => (string) reset($decoded), 'errors' => $decoded]) : $this->json(422, false, $e->getMessage()); } catch (Throwable $e) { log_message('error', 'CRM master save failed: ' . $e->getMessage()); return $this->json(500, false, 'CRM master could not be saved safely.'); } }
    public function toggle(string $slug, int $id): ResponseInterface { $d = $this->masters->definition($slug); $this->assertPermission($d, 'edit'); return $this->action(fn () => $this->masters->toggle($slug, $id, (int) ($this->user['id'] ?? 0)), 'CRM master status updated.'); }
    public function archive(string $slug, int $id): ResponseInterface { $d = $this->masters->definition($slug); $this->assertPermission($d, 'archive'); return $this->action(fn () => $this->masters->archive($slug, $id, (int) ($this->user['id'] ?? 0)), 'CRM master archived.'); }
    private function action(callable $callback, string $message): ResponseInterface { try { $callback(); return $this->json(200, true, $message); } catch (Throwable $e) { return $this->json(422, false, $e->getMessage()); } }
    private function assertPermission(array $definition, string $action): void { if (! $this->hasPermission('crm.' . $definition['permission'] . '.' . $action) && ! $this->hasPermission('crm.master.' . $action)) { throw new RuntimeException('You are not authorized to manage this CRM master.'); } }
    private function json(int $code, bool $status, string $message, array $data = []): ResponseInterface { return $this->response->setStatusCode($code)->setJSON(array_merge(['status' => $status, 'msg' => $message], $data)); }
}
