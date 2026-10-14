<?php

namespace CRM\Services;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

final class CrmMasterService
{
    private array $definitions = [
        'lead-statuses' => ['table' => 'crm_lead_statuses', 'label' => 'Lead Statuses', 'singular' => 'Lead Status', 'permission' => 'lead_status', 'fields' => ['code', 'name', 'description', 'sort_order', 'status'], 'archive' => true, 'scope' => false],
        'lead-sources' => ['table' => 'crm_lead_sources', 'label' => 'Lead Sources', 'singular' => 'Lead Source', 'permission' => 'lead_source', 'fields' => ['code', 'name', 'sort_order', 'status'], 'archive' => true, 'scope' => false],
        'lead-priorities' => ['table' => 'crm_lead_priorities', 'label' => 'Lead Priorities', 'singular' => 'Lead Priority', 'permission' => 'lead_priority', 'fields' => ['code', 'name', 'description', 'sort_order', 'status'], 'archive' => true, 'scope' => false],
        'follow-up-types' => ['table' => 'follow_up_types', 'label' => 'Follow-up Types', 'singular' => 'Follow-up Type', 'permission' => 'followup_type', 'fields' => ['code', 'name', 'description', 'sort_order', 'status'], 'archive' => true, 'scope' => true],
        'follow-up-statuses' => ['table' => 'follow_up_statuses', 'label' => 'Follow-up Statuses', 'singular' => 'Follow-up Status', 'permission' => 'followup_status', 'fields' => ['code', 'name', 'description', 'sort_order', 'status'], 'archive' => true, 'scope' => true],
    ];

    public function __construct(private ?BaseConnection $db = null, private ?int $clinicId = null) { $this->db ??= db_connect(); }
    public function definition(string $slug): array { if (! isset($this->definitions[$slug])) throw new RuntimeException('Unknown CRM master.'); return $this->definitions[$slug]; }
    public function allDefinitions(): array { return $this->definitions; }

    public function rows(string $slug, string $search = '', int $page = 1, int $perPage = 15): array
    {
        $d = $this->definition($slug); $q = $this->scoped($d); $search = trim($search);
        if ($search !== '') $q->groupStart()->like('name', $search)->orLike('code', $search)->orLike('description', $search)->groupEnd();
        $total = (clone $q)->countAllResults(); $offset = max(0, $page - 1) * $perPage;
        $rows = $q->orderBy('sort_order', 'ASC')->orderBy('name', 'ASC')->limit($perPage, $offset)->get()->getResultArray();
        $pager = service('pager'); $pager->makeLinks(max(1, $page), $perPage, $total, 'default_full', 0, 'crm_master_' . str_replace('-', '_', $slug));
        return [$rows, $pager, $total];
    }

    public function save(string $slug, array $input, ?int $id, int $actor): int
    {
        $d = $this->definition($slug); $name = trim((string) ($input['name'] ?? '')); $code = strtolower(trim((string) ($input['code'] ?? ''))); $errors = [];
        if ($name === '' || mb_strlen($name) > 120) $errors['name'] = 'Name is required and must be 120 characters or fewer.';
        if (! preg_match('/^[a-z0-9][a-z0-9_-]{1,59}$/', $code)) $errors['code'] = 'Use 2-60 lowercase letters, numbers, hyphens, or underscores.';
        if (mb_strlen(trim((string) ($input['description'] ?? ''))) > 500) $errors['description'] = 'Description must be 500 characters or fewer.';
        $sort = (int) ($input['sort_order'] ?? 0); if ($sort < 0) $errors['sort_order'] = 'Sort order must be zero or greater.';
        $q = $this->scoped($d); $q->groupStart()->where('LOWER(name)', strtolower($name), false)->orWhere('LOWER(code)', $code, false)->groupEnd(); if ($id) $q->where('id !=', $id);
        if ($q->get()->getRowArray()) { $errors['name'] = $errors['name'] ?? 'Name or code already exists.'; $errors['code'] = $errors['code'] ?? 'Name or code already exists.'; }
        if ($errors) throw new RuntimeException(json_encode($errors));
        $data = ['name' => $name, 'code' => $code, 'sort_order' => $sort, 'status' => $input['status'] === '0' ? 0 : 1, 'updated_at' => date('Y-m-d H:i:s')];
        if (in_array('description', $d['fields'], true)) $data['description'] = trim((string) ($input['description'] ?? '')) ?: null;
        if ($d['scope']) { $data['clinic_id'] = $this->clinicId ?: null; $data['scope_key'] = $this->clinicId ? 'clinic_' . $this->clinicId : 'global'; }
        $table = $this->db->table($d['table']);
        if ($id) { $old = $this->scoped($d)->where('id', $id)->get()->getRowArray(); if (! $old) throw new RuntimeException('CRM master record not found.'); $table->where('id', $id)->update($data); $event = 'updated'; } else { $data['created_at'] = date('Y-m-d H:i:s'); $table->insert($data); $id = (int) $this->db->insertID(); $event = 'created'; }
        service('audit')->record('crm.' . str_replace('-', '_', $slug) . '_' . $event, ['id' => $id, 'name' => $name, 'code' => $code, 'actor_id' => $actor]); return $id;
    }

    public function toggle(string $slug, int $id, int $actor): bool
    { $d = $this->definition($slug); $row = $this->scoped($d)->where('id', $id)->get()->getRowArray(); if (! $row) throw new RuntimeException('CRM master record not found.'); $status = (int) $row['status'] === 1 ? 0 : 1; $this->db->table($d['table'])->where('id', $id)->update(['status' => $status, 'updated_at' => date('Y-m-d H:i:s')]); service('audit')->record('crm.' . str_replace('-', '_', $slug) . ($status ? '_activated' : '_deactivated'), ['id' => $id, 'actor_id' => $actor]); return true; }
    public function archive(string $slug, int $id, int $actor): bool
    { $d = $this->definition($slug); $row = $this->scoped($d)->where('id', $id)->get()->getRowArray(); if (! $row) throw new RuntimeException('CRM master record not found.'); $this->db->table($d['table'])->where('id', $id)->update(['status' => 0, 'updated_at' => date('Y-m-d H:i:s')]); service('audit')->record('crm.' . str_replace('-', '_', $slug) . '_archived', ['id' => $id, 'actor_id' => $actor]); return true; }
    public function active(string $slug): array { $d = $this->definition($slug); return $this->scoped($d)->where('status', 1)->orderBy('sort_order')->orderBy('name')->get()->getResultArray(); }
    private function scoped(array $d) { $q = $this->db->table($d['table']); if ($d['scope']) { $q->groupStart()->where('clinic_id', $this->clinicId ?: 0)->orWhere('clinic_id IS NULL', null, false)->groupEnd(); } return $q; }
}
