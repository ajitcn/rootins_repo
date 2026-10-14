<?= $this->extend(PATIENT_VIEW . 'layout') ?>

<?= $this->section('main-content') ?>
<main class="patient-page patient-orders-page" aria-labelledby="patient-orders-title">
    <header class="patient-page-heading">
        <div>
            <span class="patient-eyebrow">Patient portal / medicines</span>
            <h1 id="patient-orders-title">My medicine orders</h1>
            <p class="text-muted mb-0">Track your prescription-based medicine orders.</p>
        </div>
        <span class="badge badge-light"><i class="bi bi-box-seam mr-1" aria-hidden="true"></i><?= (int) ($pagination['total'] ?? 0) ?> total</span>
    </header>

    <section class="card patient-list-toolbar" aria-label="Order filters">
        <form method="get" action="<?= current_url() ?>" class="patient-filter-form">
            <input type="hidden" name="view" value="<?= esc($filters['view']) ?>">
            <div class="patient-filter-search"><label class="sr-only" for="patient-order-search">Search orders</label><i class="bi bi-search" aria-hidden="true"></i><input id="patient-order-search" class="form-control" type="search" name="search" value="<?= esc($filters['search']) ?>" placeholder="Order, prescription, medicine, payment or tracking"></div>
            <select class="form-control form-control-sm" name="status" aria-label="Order status"><option value="">All order statuses</option><?php foreach (['pending', 'fulfilled', 'cancelled'] as $option): ?><option value="<?= esc($option) ?>" <?= $filters['status'] === $option ? 'selected' : '' ?>><?= esc(ucwords(str_replace('_', ' ', $option))) ?></option><?php endforeach; ?></select>
            <select class="form-control form-control-sm" name="payment_status" aria-label="Payment status"><option value="">All payment statuses</option><?php foreach (['pending', 'authorized', 'paid', 'failed', 'refunded'] as $option): ?><option value="<?= esc($option) ?>" <?= $filters['payment_status'] === $option ? 'selected' : '' ?>><?= esc(ucwords(str_replace('_', ' ', $option))) ?></option><?php endforeach; ?></select>
            <select class="form-control form-control-sm" name="delivery_status" aria-label="Delivery status"><option value="">All delivery statuses</option><?php foreach (['pending', 'packed', 'dispatched', 'out_for_delivery', 'delivered', 'failed', 'returned'] as $option): ?><option value="<?= esc($option) ?>" <?= $filters['delivery_status'] === $option ? 'selected' : '' ?>><?= esc(ucwords(str_replace('_', ' ', $option))) ?></option><?php endforeach; ?></select>
            <input class="form-control form-control-sm patient-date-filter" type="text" name="date_from" value="<?= esc($filters['date_from']) ?>" placeholder="From dd-mm-yyyy" aria-label="Order date from">
            <input class="form-control form-control-sm patient-date-filter" type="text" name="date_to" value="<?= esc($filters['date_to']) ?>" placeholder="To dd-mm-yyyy" aria-label="Order date to">
            <select class="form-control form-control-sm" name="page_size" aria-label="Page size"><?php foreach ([10, 15, 25, 50] as $size): ?><option value="<?= $size ?>" <?= (int) $filters['page_size'] === $size ? 'selected' : '' ?>><?= $size ?> / page</option><?php endforeach; ?></select>
            <div class="patient-filter-actions"><button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-funnel mr-1" aria-hidden="true"></i>Apply</button><a class="btn btn-light btn-sm" href="<?= current_url() ?>">Clear</a></div>
            <div class="patient-view-toggle" role="group" aria-label="Order view"><a class="btn btn-sm <?= $filters['view'] === 'list' ? 'btn-primary' : 'btn-outline-secondary' ?>" href="<?= current_url() . '?' . http_build_query(array_merge($filters, ['view' => 'list', 'page' => 1])) ?>" aria-pressed="<?= $filters['view'] === 'list' ? 'true' : 'false' ?>"><i class="bi bi-list-ul mr-1" aria-hidden="true"></i>List</a><a class="btn btn-sm <?= $filters['view'] === 'grid' ? 'btn-primary' : 'btn-outline-secondary' ?>" href="<?= current_url() . '?' . http_build_query(array_merge($filters, ['view' => 'grid', 'page' => 1])) ?>" aria-pressed="<?= $filters['view'] === 'grid' ? 'true' : 'false' ?>"><i class="bi bi-grid mr-1" aria-hidden="true"></i>Grid</a></div>
        </form>
    </section>

    <section class="card patient-orders-content">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between"><div><h2 class="h5 mb-1">Order history</h2><span class="small text-muted">Patient-safe order and delivery information</span></div><span class="small text-muted"><i class="fa-solid fa-circle-exclamation mr-1" aria-hidden="true"></i>Created and updated metadata</span></div>
        <?php if ($filters['view'] === 'grid'): ?><?= view(PATIENT_VIEW . 'pages/orders_grid', compact('orders')) ?><?php else: ?><?= view(PATIENT_VIEW . 'pages/orders_list', compact('orders')) ?><?php endif; ?>
    </section>

    <?php $values = ['search' => $filters['search'], 'status' => $filters['status'], 'payment_status' => $filters['payment_status'], 'delivery_status' => $filters['delivery_status'], 'date_from' => $filters['date_from'], 'date_to' => $filters['date_to'], 'view' => $filters['view'], 'page_size' => $filters['page_size']]; ?>
    <div class="card pagination-card mb-4"><div class="card-body py-3 d-flex flex-wrap align-items-center justify-content-between"><span class="small text-muted">Showing page <?= (int) $pagination['page'] ?> of <?= (int) $pagination['pages'] ?> · <?= (int) $pagination['total'] ?> orders</span><nav aria-label="Order pagination"><?php $values['page'] = max(1, (int) $pagination['page'] - 1); ?><a class="btn btn-sm btn-outline-secondary <?= (int) $pagination['page'] <= 1 ? 'disabled' : '' ?>" href="<?= (int) $pagination['page'] > 1 ? esc(current_url() . '?' . http_build_query($values)) : '#' ?>">Previous</a><?php $values['page'] = min((int) $pagination['pages'], (int) $pagination['page'] + 1); ?><a class="btn btn-sm btn-outline-secondary <?= (int) $pagination['page'] >= (int) $pagination['pages'] ? 'disabled' : '' ?>" href="<?= (int) $pagination['page'] < (int) $pagination['pages'] ? esc(current_url() . '?' . http_build_query($values)) : '#' ?>">Next</a></nav></div></div>

    <div class="modal fade patient-order-modal" id="patient-order-detail-modal" tabindex="-1" role="dialog" aria-labelledby="patient-order-detail-title" aria-hidden="true"><div class="modal-dialog modal-dialog-scrollable modal-lg" role="document"><div class="modal-content"><div class="modal-header"><div><span class="patient-eyebrow">Patient-safe order</span><h2 class="modal-title h5" id="patient-order-detail-title">Order Details</h2></div><button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div><div class="modal-body" id="patient-order-detail-body" aria-live="polite"><div class="patient-modal-loading" role="status"><span class="spinner-border spinner-border-sm mr-2" aria-hidden="true"></span>Loading order details…</div></div><div class="modal-footer"><button class="btn btn-light" type="button" data-dismiss="modal">Close</button></div></div></div></div>
</main>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?><script>
    $(function () {
        var records = <?= json_encode($orders, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        var escapeHtml = function (value) { return $('<div>').text(value == null ? '' : value).html(); };
        $('.patient-date-filter').datepicker({ format: 'dd-mm-yyyy', autoclose: true, todayHighlight: true, orientation: 'bottom auto', container: '.patient-orders-page' });
        $(document).on('show.bs.dropdown.patientOrders', '.patient-orders-page .dropdown', function () {
            var dropdown = $(this);
            var menu = dropdown.children('.dropdown-menu');

            if (! menu.length) return;

            menu.data('patient-dropdown-parent', dropdown);
            menu.appendTo('body');
        });
        $(document).on('hidden.bs.dropdown.patientOrders', '.patient-orders-page .dropdown', function () {
            var dropdown = $(this);
            var menu = $('body > .dropdown-menu').filter(function () {
                var parent = $(this).data('patient-dropdown-parent');

                return parent && parent.is(dropdown);
            }).first();

            if (menu.length) {
                menu.appendTo(dropdown);
                menu.removeData('patient-dropdown-parent');
            }
        });
        $(document).on('click.patientOrderDetails', '.patient-order-view', function () {
            var order = records.find(function (item) { return Number(item.id) === Number($(this).data('id')); }.bind(this));
            var body = $('#patient-order-detail-body');
            body.html('<div class="patient-modal-loading" role="status"><span class="spinner-border spinner-border-sm mr-2" aria-hidden="true"></span>Loading order details…</div>');
            if (! order) { body.html('<div class="patient-modal-state patient-modal-state-error" role="alert">Order details could not be found.</div>'); return; }
            var items = (order.items || []).map(function (item) { return '<tr><td data-label="Medicine">' + escapeHtml(item.medicine_name || 'Medicine unavailable') + '</td><td data-label="Quantity">' + escapeHtml(item.quantity || '—') + '</td></tr>'; }).join('');
            $('#patient-order-detail-title').text('Order Details · ' + (order.order_number || '—'));
            body.html('<section class="patient-modal-section"><h3><i class="bi bi-box-seam mr-2" aria-hidden="true"></i>Order summary</h3><div class="patient-detail-grid"><div><span>Order number</span><strong>' + escapeHtml(order.order_number || '—') + '</strong></div><div><span>Order date</span><strong>' + escapeHtml(order.order_date_display || '—') + '</strong></div><div><span>Prescription</span><strong>' + escapeHtml(order.prescription_number || 'Not linked') + '</strong></div><div><span>Total</span><strong>' + escapeHtml(Number(order.total_amount || 0).toFixed(2)) + '</strong></div><div><span>Payment</span><strong>' + escapeHtml(order.payment_status_label || '—') + '</strong></div><div><span>Delivery</span><strong>' + escapeHtml(order.delivery_status_label || '—') + '</strong></div></div></section><section class="patient-modal-section"><h3><i class="bi bi-capsule mr-2" aria-hidden="true"></i>Medicines</h3><div class="table-responsive patient-order-medicine-table-wrap"><table class="table table-sm patient-order-medicine-table"><thead><tr><th>Medicine</th><th>Quantity</th></tr></thead><tbody>' + (items || '<tr><td colspan="2">No medicine details recorded.</td></tr>') + '</tbody></table></div></section><section class="patient-modal-section"><h3><i class="bi bi-truck mr-2" aria-hidden="true"></i>Delivery</h3><div class="patient-detail-grid"><div><span>Provider</span><strong>' + escapeHtml(order.delivery_provider || 'Not recorded') + '</strong></div><div><span>Tracking reference</span><strong>' + escapeHtml(order.tracking_reference || 'Not available') + '</strong></div><div><span>Contact</span><strong>' + escapeHtml(order.delivery_contact_snapshot || 'Not recorded') + '</strong></div><div class="patient-detail-wide"><span>Address</span><strong>' + escapeHtml(order.delivery_address_snapshot || 'Not recorded') + '</strong></div></div></section><p class="patient-modal-metadata"><i class="fa-solid fa-circle-exclamation mr-1" aria-hidden="true"></i>Created: ' + escapeHtml(order.created_at || '—') + '<br>Updated: ' + escapeHtml(order.updated_at || '—') + '</p>');
        });
    });
</script><?= $this->endSection() ?>
