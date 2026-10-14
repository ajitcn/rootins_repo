<?php
$pagination = $pagination ?? [];
$filters = $filters ?? [];
$pageSize = (int) ($pageSize ?? 15);
$isAppointmentPagination = isset($pagination['pages']) && ! is_array($pagination['pages']);
$buildUrl = static function (array $values): string {
    return current_url() . '?' . http_build_query(array_filter($values, static fn ($value) => $value !== '' && $value !== null));
};
?>
<div class="card pagination-card mb-4">
    <div class="card-body py-3">
        <?php if ($isAppointmentPagination): ?>
            <?php
            $appointmentValues = [
                'search' => $filters['search'] ?? '',
                'status' => $filters['status'] ?? '',
                'doctor_id' => $filters['doctor_id'] ?? '',
                'service' => $filters['service'] ?? '',
                'date_from' => $filters['date_from'] ?? '',
                'date_to' => $filters['date_to'] ?? '',
                'view' => $filters['view'] ?? 'list',
                'page_size' => $pageSize,
            ];
            $appointmentPage = (int) ($pagination['page'] ?? 1);
            $appointmentPages = (int) ($pagination['pages'] ?? 1);
            ?>
            <div class="d-flex flex-wrap align-items-center justify-content-between">
                <span class="small text-muted">Showing page <?= esc((string) $appointmentPage) ?> of <?= esc((string) $appointmentPages) ?> · <?= esc((string) ($pagination['total'] ?? 0)) ?> appointments</span>
                <nav aria-label="Appointments pagination">
                    <?php $appointmentValues['page'] = $appointmentPage - 1; ?>
                    <a class="btn btn-sm btn-outline-secondary <?= $appointmentPage <= 1 ? 'disabled' : '' ?>" href="<?= $appointmentPage > 1 ? esc($buildUrl($appointmentValues)) : '#' ?>">Previous</a>
                    <?php $appointmentValues['page'] = $appointmentPage + 1; ?>
                    <a class="btn btn-sm btn-outline-secondary <?= $appointmentPage >= $appointmentPages ? 'disabled' : '' ?>" href="<?= $appointmentPage < $appointmentPages ? esc($buildUrl($appointmentValues)) : '#' ?>">Next</a>
                </nav>
            </div>
        <?php else: ?>
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
                <strong class="small">Record navigation</strong>
                <span class="small text-muted">Search and filters apply across your clinic-scoped records.</span>
            </div>
            <div class="d-flex flex-wrap">
                <?php foreach (['appointments' => 'Appointments', 'orders' => 'Orders', 'visits' => 'Visits', 'prescriptions' => 'Prescriptions', 'invoices' => 'Invoices', 'documents' => 'Documents'] as $key => $title): ?>
                    <?php
                    $currentPage = (int) ($pagination[$key]['page'] ?? 1);
                    $totalPages = (int) ($pagination[$key]['pages'] ?? 1);
                    $values = [
                        'search' => $filters['search'] ?? '',
                        'appointment_status' => $filters['appointment_status'] ?? '',
                        'order_status' => $filters['order_status'] ?? '',
                        'payment_status' => $filters['payment_status'] ?? '',
                        'delivery_status' => $filters['delivery_status'] ?? '',
                        'page_size' => $pageSize,
                        $key . '_page' => $currentPage,
                    ];
                    ?>
                    <nav class="mr-3 mb-2" aria-label="<?= esc($title) ?> pagination">
                        <span class="small text-muted mr-2"><?= esc($title) ?>: <?= $currentPage ?>/<?= $totalPages ?></span>
                        <?php $values[$key . '_page'] = $currentPage - 1; ?>
                        <a class="btn btn-sm btn-outline-secondary <?= $currentPage <= 1 ? 'disabled' : '' ?>" href="<?= $currentPage > 1 ? esc($buildUrl($values)) : '#' ?>" aria-label="Previous <?= esc(strtolower($title)) ?> page">Previous</a>
                        <?php $values[$key . '_page'] = $currentPage + 1; ?>
                        <a class="btn btn-sm btn-outline-secondary <?= $currentPage >= $totalPages ? 'disabled' : '' ?>" href="<?= $currentPage < $totalPages ? esc($buildUrl($values)) : '#' ?>" aria-label="Next <?= esc(strtolower($title)) ?> page">Next</a>
                    </nav>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
