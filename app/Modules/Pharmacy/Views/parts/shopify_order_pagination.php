<?php
$pagination = $pagination ?? ['page' => 1, 'pages' => 1, 'page_size' => 15, 'total' => 0];
$filters = $filters ?? [];
$page = min(max(1, (int) ($pagination['page'] ?? 1)), max(1, (int) ($pagination['pages'] ?? 1)));
$pages = max(1, (int) ($pagination['pages'] ?? 1));
$start = max(1, $page - 2);
$end = min($pages, $page + 2);
$query = static fn (int $number): string => base_url('pharmacy/shopify-orders?' . http_build_query(array_filter([
    'search' => $filters['search'] ?? '',
    'status' => $filters['status'] ?? '',
    'financial_status' => $filters['financial_status'] ?? '',
    'prescription_status' => $filters['prescription_status'] ?? '',
    'mapping_status' => $filters['mapping_status'] ?? '',
    'fulfillment_status' => $filters['fulfillment_status'] ?? '',
    'date_from' => $filters['date_from'] ?? '',
    'date_to' => $filters['date_to'] ?? '',
    'page_size' => (int) ($pagination['page_size'] ?? 15),
    'page' => $number,
], static fn ($value): bool => $value !== '' && $value !== null)));
?>
<nav class="ri-pagination pharmacy-shopify-pagination" aria-label="Shopify order pagination">
    <form method="get" action="<?= base_url('pharmacy/shopify-orders') ?>" class="pharmacy-shopify-page-size">
        <?php foreach (['search', 'status', 'financial_status', 'prescription_status', 'mapping_status', 'fulfillment_status', 'date_from', 'date_to'] as $field): ?>
            <input type="hidden" name="<?= esc($field) ?>" value="<?= esc((string) ($filters[$field] ?? '')) ?>">
        <?php endforeach; ?>
        <label for="shopify-order-page-size">Rows</label>
        <select id="shopify-order-page-size" name="page_size" class="custom-select custom-select-sm" onchange="this.form.submit()">
            <?php foreach ([10, 15, 25, 50] as $size): ?><option value="<?= $size ?>" <?= (int) ($pagination['page_size'] ?? 15) === $size ? 'selected' : '' ?>><?= $size ?></option><?php endforeach; ?>
        </select>
    </form>
    <span class="ri-pagination-summary">Page <?= $page ?> of <?= $pages ?> · <?= (int) ($pagination['total'] ?? 0) ?> orders</span>
    <div class="ri-pagination-controls">
        <a class="ri-page-link <?= $page <= 1 ? 'is-disabled' : '' ?>" href="<?= $page > 1 ? esc($query($page - 1)) : '#' ?>" aria-label="Previous page" <?= $page <= 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?>><i class="bi bi-chevron-left" aria-hidden="true"></i><span class="sr-only">Previous</span></a>
        <?php if ($start > 1): ?><a class="ri-page-link" href="<?= esc($query(1)) ?>">1</a><?php if ($start > 2): ?><span class="ri-page-ellipsis" aria-hidden="true">…</span><?php endif; ?><?php endif; ?>
        <?php for ($number = $start; $number <= $end; $number++): ?><a class="ri-page-link <?= $number === $page ? 'is-active' : '' ?>" href="<?= esc($query($number)) ?>" <?= $number === $page ? 'aria-current="page"' : '' ?>><?= $number ?></a><?php endfor; ?>
        <?php if ($end < $pages): ?><?php if ($end < $pages - 1): ?><span class="ri-page-ellipsis" aria-hidden="true">…</span><?php endif; ?><a class="ri-page-link" href="<?= esc($query($pages)) ?>"><?= $pages ?></a><?php endif; ?>
        <a class="ri-page-link <?= $page >= $pages ? 'is-disabled' : '' ?>" href="<?= $page < $pages ? esc($query($page + 1)) : '#' ?>" aria-label="Next page" <?= $page >= $pages ? 'aria-disabled="true" tabindex="-1"' : '' ?>><i class="bi bi-chevron-right" aria-hidden="true"></i><span class="sr-only">Next</span></a>
    </div>
</nav>
