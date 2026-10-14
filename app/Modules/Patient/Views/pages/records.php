<?php
$date = static function (?string $value): string {
    return $value !== null && $value !== '' && strtotime($value) !== false ? date('d-m-Y', strtotime($value)) : 'Not recorded';
};
?>
<div class="card"><div class="table-responsive"><table class="table mb-0"><caption class="sr-only"><?= esc(ucfirst($sectionKey)) ?></caption><thead>
<?php if ($sectionKey === 'appointments'): ?><tr><th>Appointment</th><th>Doctor</th><th>Date/time</th><th>Type</th><th>Status</th></tr>
<?php elseif ($sectionKey === 'prescriptions'): ?><tr><th>Prescription</th><th>Date</th><th>Status</th><th>Notes</th></tr>
<?php elseif ($sectionKey === 'orders'): ?><tr><th>Order</th><th>Prescription</th><th>Order status</th><th>Payment</th><th>Delivery</th><th>Tracking/provider</th></tr>
<?php elseif ($sectionKey === 'invoices'): ?><tr><th>Invoice</th><th>Date</th><th>Total</th><th>Status</th><th>Payment</th></tr>
<?php elseif ($sectionKey === 'visits'): ?><tr><th>Visit</th><th>Doctor</th><th>Date</th><th>Status</th><th>Diagnosis</th></tr>
<?php else: ?><tr><th>Document</th><th>Type</th><th>Uploaded</th><th>Action</th></tr><?php endif; ?></thead><tbody>
<?php foreach ($records as $record): ?>
    <?php if ($sectionKey === 'appointments'): ?><tr><td><?= esc($record['appointment_number'] ?? '') ?></td><td><?= esc(trim(($record['doctor_first_name'] ?? '') . ' ' . ($record['doctor_last_name'] ?? ''))) ?></td><td><?= esc($date($record['appointment_date'] ?? null)) ?> <?= esc(substr((string) ($record['appointment_time'] ?? ''), 0, 5)) ?></td><td><?= esc(ucwords(str_replace('_', ' ', (string) ($record['appointment_type'] ?? '')))) ?></td><td><span class="badge badge-light"><?= esc(ucwords(str_replace('_', ' ', (string) ($record['status'] ?? '')))) ?></span></td></tr>
    <?php elseif ($sectionKey === 'prescriptions'): ?><tr><td><?= esc($record['prescription_number'] ?? '') ?></td><td><?= esc($date($record['prescription_date'] ?? null)) ?></td><td><span class="badge badge-light"><?= esc(ucwords(str_replace('_', ' ', (string) ($record['status'] ?? '')))) ?></span></td><td><?= esc($record['notes'] ?? 'No notes') ?></td></tr>
    <?php elseif ($sectionKey === 'orders'): ?><tr><td><?= esc($record['order_number'] ?? '') ?></td><td><?= esc($record['prescription_number'] ?? '') ?></td><td><span class="badge badge-light"><?= esc(ucwords(str_replace('_', ' ', (string) ($record['status'] ?? '')))) ?></span></td><td><?= esc(ucwords(str_replace('_', ' ', (string) ($record['payment_status'] ?? '')))) ?></td><td><?= esc(ucwords(str_replace('_', ' ', (string) ($record['delivery_status'] ?? '')))) ?></td><td><?= esc(trim(($record['payment_provider'] ?? '') . ' ' . ($record['tracking_reference'] ?? '')) ?: 'Not recorded') ?></td></tr>
    <?php elseif ($sectionKey === 'invoices'): ?><tr><td><?= esc($record['invoice_number'] ?? '') ?></td><td><?= esc($date($record['created_at'] ?? null)) ?></td><td><?= esc(($record['currency'] ?? '') . ' ' . ($record['total'] ?? '0.00')) ?></td><td><?= esc(ucwords(str_replace('_', ' ', (string) ($record['status'] ?? '')))) ?></td><td><?= esc(ucwords(str_replace('_', ' ', (string) ($record['payment_status'] ?? '')))) ?></td></tr>
    <?php elseif ($sectionKey === 'visits'): ?><tr><td><?= esc($record['consultation_number'] ?? '') ?></td><td><?= esc(trim(($record['doctor_first_name'] ?? '') . ' ' . ($record['doctor_last_name'] ?? ''))) ?></td><td><?= esc($date($record['consultation_date'] ?? null)) ?></td><td><?= esc(ucwords(str_replace('_', ' ', (string) ($record['status'] ?? '')))) ?></td><td><?= esc($record['diagnosis'] ?? 'Not recorded') ?></td></tr>
    <?php else: ?><tr><td><?= esc($record['title'] ?? $record['file_name'] ?? '') ?></td><td><?= esc($record['document_type'] ?? '') ?></td><td><?= esc($date($record['created_at'] ?? null)) ?></td><td><a class="btn btn-sm btn-outline-primary" href="<?= base_url('patient/documents/' . (int) ($record['id'] ?? 0) . '/download') ?>">Download</a></td></tr><?php endif; ?>
<?php endforeach; ?>
<?php if ($records === []): ?><tr><td colspan="6" class="text-center text-muted">No records found.</td></tr><?php endif; ?></tbody></table></div></div>
