<?php
$pager = $pager ?? null;
$group = $group ?? 'default';
$filters = $filters ?? [];
$page = $pager?->getCurrentPage($group) ?? 1;
$pages = $pager?->getPageCount($group) ?? 1;
$total = $pager?->getTotal($group) ?? 0;
$queryFilters = array_filter($filters, static fn ($value) => $value !== '' && $value !== null);
$url = static fn (int $number): string => current_url() . '?' . http_build_query($queryFilters + ['page_' . $group => $number]);
?>
<div class="card-footer d-flex flex-wrap justify-content-between align-items-center">
    <form method="get" action="<?= current_url() ?>" class="form-inline mb-2 mb-md-0" aria-label="Page size">
        <?php foreach ($queryFilters as $name => $value): ?>
            <?php if ($name !== 'page_size'): ?>
                <input type="hidden" name="<?= esc($name) ?>" value="<?= esc((string) $value) ?>">
            <?php endif; ?>
        <?php endforeach; ?>
        <label class="small mr-2" for="admin-page-size-<?= esc($group) ?>">Rows per page</label>
        <select class="custom-select custom-select-sm" id="admin-page-size-<?= esc($group) ?>" name="page_size" onchange="this.form.submit()">
            <?php foreach ([10, 15, 25, 50] as $size): ?>
                <option value="<?= $size ?>" <?= (int) ($filters['page_size'] ?? 15) === $size ? 'selected' : '' ?>><?= $size ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <nav aria-label="Pagination">
        <span class="small text-muted mr-3">Page <?= esc((string) $page) ?> of <?= esc((string) max(1, $pages)) ?> (<?= esc((string) $total) ?> total)</span>
        <a class="btn btn-sm btn-outline-secondary <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= $page > 1 ? esc($url($page - 1)) : '#' ?>" aria-label="Previous page">Previous</a>
        <a class="btn btn-sm btn-outline-secondary <?= $page >= $pages ? 'disabled' : '' ?>" href="<?= $page < $pages ? esc($url($page + 1)) : '#' ?>" aria-label="Next page">Next</a>
    </nav>
</div>
