<?php
$eyebrow = $eyebrow ?? 'Pharmacy';
$title = $title ?? 'Pharmacy';
$description = $description ?? '';
$count = $count ?? null;
$countLabel = $countLabel ?? 'records';
$listUrl = $listUrl ?? '#';
$gridUrl = $gridUrl ?? '#';
$view = $view ?? 'list';
$filterTarget = $filterTarget ?? null;
$primaryLabel = $primaryLabel ?? null;
$primaryUrl = $primaryUrl ?? null;
$primaryTarget = $primaryTarget ?? null;
?>
<header class="pharmacy-page-header">
    <div class="pharmacy-page-heading">
        <nav class="pharmacy-breadcrumb" aria-label="Breadcrumb"><a href="<?= base_url('pharmacy') ?>">Pharmacy</a><span aria-hidden="true">/</span><span><?= esc($title) ?></span></nav>
        <div class="pharmacy-eyebrow"><?= esc($eyebrow) ?></div>
        <h1 class="pharmacy-page-title" id="<?= esc($titleId ?? '') ?>"><?= esc($title) ?></h1>
        <?php if ($description !== ''): ?><p class="pharmacy-page-description"><?= esc($description) ?></p><?php endif; ?>
        <?php if ($count !== null): ?><div class="pharmacy-page-count"><strong><?= (int) $count ?></strong> <?= esc($countLabel) ?></div><?php endif; ?>
    </div>
    <div class="pharmacy-page-actions" aria-label="Page actions">
        <div class="btn-group pharmacy-view-toggle" role="group" aria-label="View mode">
            <a class="btn btn-sm <?= $view === 'list' ? 'btn-primary' : 'btn-outline-primary' ?>" href="<?= esc($listUrl) ?>" aria-current="<?= $view === 'list' ? 'page' : 'false' ?>"><i class="bi bi-list-ul mr-1" aria-hidden="true"></i>List</a>
            <a class="btn btn-sm <?= $view === 'grid' ? 'btn-primary' : 'btn-outline-primary' ?>" href="<?= esc($gridUrl) ?>" aria-current="<?= $view === 'grid' ? 'page' : 'false' ?>"><i class="bi bi-grid-3x3-gap mr-1" aria-hidden="true"></i>Grid</a>
        </div>
        <?php if ($filterTarget): ?><button class="btn btn-sm btn-outline-secondary" type="button" data-toggle="modal" data-target="<?= esc($filterTarget) ?>"><i class="bi bi-funnel mr-1" aria-hidden="true"></i>Filters</button><?php endif; ?>
        <?php if ($primaryLabel && $primaryUrl): ?><a class="btn btn-sm btn-primary" href="<?= esc($primaryUrl) ?>"<?= $primaryTarget ? ' data-toggle="modal" data-target="' . esc($primaryTarget) . '"' : '' ?>><i class="bi bi-plus-lg mr-1" aria-hidden="true"></i><?= esc($primaryLabel) ?></a><?php endif; ?>
    </div>
</header>
