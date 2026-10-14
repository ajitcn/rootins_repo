<?php
$actionId = (string) ($actionId ?? 'pharmacy-more-actions');
$label = (string) ($label ?? 'More Actions');
$ariaLabel = (string) ($ariaLabel ?? $label);
$items = (string) ($items ?? '');
$class = trim((string) ($class ?? ''));
$menuClass = trim((string) ($menuClass ?? ''));
?>
<div class="dropdown pharmacy-more-actions <?= esc($class, 'attr') ?>">
    <button
        class="btn btn-sm btn-outline-secondary dropdown-toggle pharmacy-more-actions-toggle"
        type="button"
        id="<?= esc($actionId, 'attr') ?>"
        data-toggle="dropdown"
        data-display="static"
        data-boundary="viewport"
        aria-haspopup="true"
        aria-expanded="false"
        aria-controls="<?= esc($actionId, 'attr') ?>-menu"
        aria-label="<?= esc($ariaLabel, 'attr') ?>"
    >
        <i class="fa-solid fa-ellipsis-vertical ri-actions-icon mr-1" aria-hidden="true"></i><?= esc($label) ?>
    </button>
    <div
        class="dropdown-menu dropdown-menu-right pharmacy-more-actions-menu <?= esc($menuClass, 'attr') ?>"
        id="<?= esc($actionId, 'attr') ?>-menu"
        role="menu"
        aria-labelledby="<?= esc($actionId, 'attr') ?>"
        aria-label="<?= esc($ariaLabel, 'attr') ?>"
    >
        <?= $items ?>
    </div>
</div>
