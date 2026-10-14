<?php
$page = min(max(1, (int) ($pagination['page'] ?? 1)), max(1, (int) ($pagination['pages'] ?? 1)));
$pages = max(1, (int) ($pagination['pages'] ?? 1));
$start = max(1, $page - 2);
$end = min($pages, $page + 2);
$query = array_merge($filters ?? [], ['view' => $view ?? 'list']);
$url = static fn (int $number): string => base_url('pharmacy/billing?' . http_build_query(array_merge($query, ['page' => $number])));
?>
<nav class="ri-pagination pharmacy-pagination" aria-label="Billing pagination"><span class="ri-pagination-summary">Page <?= $page ?> of <?= $pages ?> · <?= (int) ($pagination['total'] ?? 0) ?> records</span><div class="ri-pagination-controls"><a class="ri-page-link <?= $page <= 1 ? 'is-disabled' : '' ?>" href="<?= $page > 1 ? esc($url($page - 1)) : '#' ?>" aria-label="Previous page" <?= $page <= 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?>><i class="bi bi-chevron-left" aria-hidden="true"></i><span class="sr-only">Previous</span></a><?php if ($start > 1): ?><a class="ri-page-link" href="<?= esc($url(1)) ?>">1</a><?php if ($start > 2): ?><span class="ri-page-ellipsis">…</span><?php endif; ?><?php endif; ?><?php for ($number = $start; $number <= $end; $number++): ?><a class="ri-page-link <?= $number === $page ? 'is-active' : '' ?>" href="<?= esc($url($number)) ?>" <?= $number === $page ? 'aria-current="page"' : '' ?>><?= $number ?></a><?php endfor; ?><?php if ($end < $pages): ?><?php if ($end < $pages - 1): ?><span class="ri-page-ellipsis">…</span><?php endif; ?><a class="ri-page-link" href="<?= esc($url($pages)) ?>"><?= $pages ?></a><?php endif; ?><a class="ri-page-link <?= $page >= $pages ? 'is-disabled' : '' ?>" href="<?= $page < $pages ? esc($url($page + 1)) : '#' ?>" aria-label="Next page" <?= $page >= $pages ? 'aria-disabled="true" tabindex="-1"' : '' ?>><i class="bi bi-chevron-right" aria-hidden="true"></i><span class="sr-only">Next</span></a></div></nav>

