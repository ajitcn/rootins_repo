<?php
$pagination = $pagination ?? ['page' => 1, 'pages' => 1, 'page_size' => 15, 'total' => 0];
$filters = $filters ?? [];
$hidePageSize = ! empty($hidePageSize);
$page = min(max(1, (int) ($pagination['page'] ?? 1)), max(1, (int) ($pagination['pages'] ?? 1)));
$pages = max(1, (int) ($pagination['pages'] ?? 1));
$start = max(1, $page - 2);
$end = min($pages, $page + 2);
$fragment = trim((string) ($fragment ?? ''), '#');
$queryFilters = array_filter([
    'search' => $filters['search'] ?? '',
    'patient_name' => $filters['patient_name'] ?? '',
    'mobile_number' => $filters['mobile_number'] ?? '',
    'source_type' => $filters['source_type'] ?? '',
    'status' => $filters['status'] ?? '',
    'country_id' => $filters['country_id'] ?? 0,
    'state_id' => $filters['state_id'] ?? 0,
    'city_id' => $filters['city_id'] ?? 0,
    'locality_id' => $filters['locality_id'] ?? 0,
    'created_from' => $filters['created_from'] ?? '',
    'created_to' => $filters['created_to'] ?? '',
    'appointment_type' => $filters['appointment_type'] ?? '',
    'patient_id' => $filters['patient_id'] ?? 0,
    'service_id' => $filters['service_id'] ?? 0,
    'queue_number' => $filters['queue_number'] ?? 0,
    'date_from' => $filters['date_from'] ?? '',
    'date_to' => $filters['date_to'] ?? '',
    'follow_up_type_id' => $filters['follow_up_type_id'] ?? 0,
    'view' => $filters['view'] ?? '',
    'page_size' => $filters['page_size'] ?? 15,
], static fn ($value) => $value !== '' && $value !== null && $value !== 0);
$pageUrl = static function (int $page) use ($filters, $fragment): string {
    $query = array_filter([
        'search' => $filters['search'] ?? '',
        'patient_name' => $filters['patient_name'] ?? '',
        'mobile_number' => $filters['mobile_number'] ?? '',
        'source_type' => $filters['source_type'] ?? '',
        'status' => $filters['status'] ?? '',
        'country_id' => $filters['country_id'] ?? 0,
        'state_id' => $filters['state_id'] ?? 0,
        'city_id' => $filters['city_id'] ?? 0,
        'locality_id' => $filters['locality_id'] ?? 0,
        'created_from' => $filters['created_from'] ?? '',
        'created_to' => $filters['created_to'] ?? '',
        'appointment_type' => $filters['appointment_type'] ?? '',
        'patient_id' => $filters['patient_id'] ?? 0,
        'service_id' => $filters['service_id'] ?? 0,
        'queue_number' => $filters['queue_number'] ?? 0,
        'date_from' => $filters['date_from'] ?? '',
        'date_to' => $filters['date_to'] ?? '',
        'follow_up_type_id' => $filters['follow_up_type_id'] ?? 0,
        'view' => $filters['view'] ?? '',
        'page_size' => $filters['page_size'] ?? 15,
        'page' => $page,
    ], static fn ($value) => $value !== '' && $value !== null && $value !== 0);

    $url = current_url() . '?' . http_build_query($query);

    return $fragment === '' ? $url : $url . '#' . rawurlencode($fragment);
};
?>
<div class="ri-pagination card-footer">
    <?php if (! $hidePageSize): ?>
    <form method="get" action="<?= current_url() ?>" class="form-inline mb-2 mb-md-0" aria-label="Page size">
        <?php foreach ($queryFilters as $key => $value): ?>
            <?php if ($key !== 'page_size'): ?>
                <input type="hidden" name="<?= esc($key) ?>" value="<?= esc((string) $value) ?>">
            <?php endif; ?>
        <?php endforeach; ?>
        <label class="small mr-2" for="doctor-page-size">Rows per page</label>
        <select class="custom-select custom-select-sm" id="doctor-page-size" name="page_size" onchange="this.form.submit()">
            <?php foreach ([10, 15, 25, 50] as $size): ?>
                <option value="<?= $size ?>" <?= (int) ($pagination['page_size'] ?? 15) === $size ? 'selected' : '' ?>><?= $size ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <?php endif; ?>
    <nav class="ri-pagination-controls" aria-label="Pagination">
        <span class="ri-pagination-summary">Page <?= esc((string) $page) ?> of <?= esc((string) $pages) ?> (<?= esc((string) ($pagination['total'] ?? 0)) ?> total)</span>
        <a class="ri-page-link <?= $page <= 1 ? 'is-disabled' : '' ?>" href="<?= $page > 1 ? esc($pageUrl($page - 1)) : '#' ?>" aria-label="Previous page" <?= $page <= 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?>>Previous</a>
        <?php if ($start > 1): ?><a class="ri-page-link" href="<?= esc($pageUrl(1)) ?>">1</a><?php if ($start > 2): ?><span class="ri-page-ellipsis" aria-hidden="true">…</span><?php endif; ?><?php endif; ?>
        <?php for ($number = $start; $number <= $end; $number++): ?><a class="ri-page-link <?= $number === $page ? 'is-active' : '' ?>" href="<?= esc($pageUrl($number)) ?>" <?= $number === $page ? 'aria-current="page"' : '' ?>><?= $number ?></a><?php endfor; ?>
        <?php if ($end < $pages): ?><?php if ($end < $pages - 1): ?><span class="ri-page-ellipsis" aria-hidden="true">…</span><?php endif; ?><a class="ri-page-link" href="<?= esc($pageUrl($pages)) ?>"><?= $pages ?></a><?php endif; ?>
        <a class="ri-page-link <?= $page >= $pages ? 'is-disabled' : '' ?>" href="<?= $page < $pages ? esc($pageUrl($page + 1)) : '#' ?>" aria-label="Next page" <?= $page >= $pages ? 'aria-disabled="true" tabindex="-1"' : '' ?>>Next</a>
    </nav>
</div>
