<nav aria-label="breadcrumb" class="page-breadcrumb">
    <ol class="breadcrumb">

        <li class="breadcrumb-item">
            <a href="<?= base_url($module) ?>">
                <i class="bi bi-house-door-fill"></i> Dashboard
            </a>
        </li>

        <li class="breadcrumb-item"> 
            <?= $menu_title?>
        </li>

        <li class="breadcrumb-item active" aria-current="page">
            <?= $page?>
        </li>

    </ol>
</nav>
