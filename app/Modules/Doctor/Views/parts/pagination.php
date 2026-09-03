<?php
$pagination = $pagination ?? ['page' => 1, 'pages' => 1, 'page_size' => 15, 'total' => 0];
$filters = $filters ?? [];
$pageUrl = static function (int $page) use ($filters): string {
    $query = array_filter(['search' => $filters['search'] ?? '', 'page_size' => $filters['page_size'] ?? 15, 'page' => $page], static fn ($value) => $value !== '' && $value !== null);
    return current_url() . '?' . http_build_query($query);
};
?>
<div class="card-footer d-flex flex-wrap justify-content-between align-items-center">
    <form method="get" action="<?= current_url() ?>" class="form-inline mb-2 mb-md-0" aria-label="Page size">
        <input type="hidden" name="search" value="<?= esc($filters['search'] ?? '') ?>">
        <label class="small mr-2" for="doctor-page-size">Rows per page</label>
        <select class="custom-select custom-select-sm" id="doctor-page-size" name="page_size" onchange="this.form.submit()">
            <?php foreach ([10, 15, 25, 50] as $size): ?><option value="<?= $size ?>" <?= (int) ($pagination['page_size'] ?? 15) === $size ? 'selected' : '' ?>><?= $size ?></option><?php endforeach; ?>
        </select>
    </form>
    <nav aria-label="Pagination"><span class="small text-muted mr-3">Page <?= esc((string) $pagination['page']) ?> of <?= esc((string) $pagination['pages']) ?> (<?= esc((string) $pagination['total']) ?> total)</span><a class="btn btn-sm btn-outline-secondary <?= $pagination['page'] <= 1 ? 'disabled' : '' ?>" href="<?= $pagination['page'] > 1 ? esc($pageUrl($pagination['page'] - 1)) : '#' ?>" aria-label="Previous page">Previous</a> <a class="btn btn-sm btn-outline-secondary <?= $pagination['page'] >= $pagination['pages'] ? 'disabled' : '' ?>" href="<?= $pagination['page'] < $pagination['pages'] ? esc($pageUrl($pagination['page'] + 1)) : '#' ?>" aria-label="Next page">Next</a></nav>
</div>
