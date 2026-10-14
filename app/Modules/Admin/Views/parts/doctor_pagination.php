<?php
$pager = $pager ?? null;
$group = $group ?? 'default';
$filters = $filters ?? [];
$page = $pager?->getCurrentPage($group) ?? 1;
$pages = $pager?->getPageCount($group) ?? 1;
$total = $pager?->getTotal($group) ?? 0;
$pages = max(1, (int) $pages);
$page = min(max(1, (int) $page), $pages);
$start = max(1, $page - 2);
$end = min($pages, $page + 2);
$queryFilters = array_filter($filters, static fn ($value) => $value !== '' && $value !== null);
$url = static fn (int $number): string => current_url() . '?' . http_build_query($queryFilters + ['page_' . $group => $number]);
?>
<div class="ri-pagination card-footer">
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
    <nav class="ri-pagination-controls" aria-label="Pagination">
        <span class="ri-pagination-summary">Page <?= esc((string) $page) ?> of <?= esc((string) $pages) ?> (<?= esc((string) $total) ?> total)</span>
        <a class="ri-page-link <?= $page <= 1 ? 'is-disabled' : '' ?>" href="<?= $page > 1 ? esc($url($page - 1)) : '#' ?>" aria-label="Previous page" <?= $page <= 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?>>Previous</a>
        <?php if ($start > 1): ?><a class="ri-page-link" href="<?= esc($url(1)) ?>">1</a><?php if ($start > 2): ?><span class="ri-page-ellipsis" aria-hidden="true">…</span><?php endif; ?><?php endif; ?>
        <?php for ($number = $start; $number <= $end; $number++): ?><a class="ri-page-link <?= $number === $page ? 'is-active' : '' ?>" href="<?= esc($url($number)) ?>" <?= $number === $page ? 'aria-current="page"' : '' ?>><?= $number ?></a><?php endfor; ?>
        <?php if ($end < $pages): ?><?php if ($end < $pages - 1): ?><span class="ri-page-ellipsis" aria-hidden="true">…</span><?php endif; ?><a class="ri-page-link" href="<?= esc($url($pages)) ?>"><?= $pages ?></a><?php endif; ?>
        <a class="ri-page-link <?= $page >= $pages ? 'is-disabled' : '' ?>" href="<?= $page < $pages ? esc($url($page + 1)) : '#' ?>" aria-label="Next page" <?= $page >= $pages ? 'aria-disabled="true" tabindex="-1"' : '' ?>>Next</a>
    </nav>
</div>
