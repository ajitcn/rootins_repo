<?php
$permissions = session('permissions') ?? [];
$recordJson = esc(json_encode($prescription, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE), 'attr');
$prescriptionId = (int) ($prescription['id'] ?? 0);
$actionId = 'review-actions-' . $prescriptionId;
ob_start();
?>
<?php if (in_array('pharmacy.prescription.review.view', $permissions, true)): ?>
    <button class="dropdown-item prescription-detail-action" role="menuitem" type="button" data-toggle="modal" data-target="#pharmacy-prescription-modal" data-record="<?= $recordJson ?>">
        <i class="bi bi-eye mr-2" aria-hidden="true"></i>View
    </button>
<?php endif; ?>
<?php if ($prescription['can_review'] && in_array('pharmacy.prescription.review.approve', $permissions, true)): ?>
    <button class="dropdown-item prescription-review-action" role="menuitem" type="button" data-decision="approve" data-toggle="modal" data-target="#pharmacy-prescription-modal" data-record="<?= $recordJson ?>">
        <i class="bi bi-check2-circle mr-2 text-success" aria-hidden="true"></i>Approve
    </button>
<?php endif; ?>
<?php if ($prescription['can_review'] && in_array('pharmacy.prescription.review.reject', $permissions, true)): ?>
    <button class="dropdown-item prescription-review-action text-danger" role="menuitem" type="button" data-decision="reject" data-toggle="modal" data-target="#pharmacy-prescription-modal" data-record="<?= $recordJson ?>">
        <i class="bi bi-x-circle mr-2" aria-hidden="true"></i>Reject
    </button>
<?php endif; ?>
<?php if ($prescription['can_review'] && in_array('pharmacy.prescription.review.clarify', $permissions, true)): ?>
    <button class="dropdown-item prescription-review-action" role="menuitem" type="button" data-decision="clarification" data-toggle="modal" data-target="#pharmacy-prescription-modal" data-record="<?= $recordJson ?>">
        <i class="bi bi-chat-left-text mr-2 text-warning" aria-hidden="true"></i>Request clarification
    </button>
<?php endif; ?>
<?php if ($prescription['can_review'] && in_array('pharmacy.prescription.review.cancel', $permissions, true)): ?>
    <button class="dropdown-item prescription-review-action text-danger" role="menuitem" type="button" data-decision="cancel" data-toggle="modal" data-target="#pharmacy-prescription-modal" data-record="<?= $recordJson ?>">
        <i class="bi bi-slash-circle mr-2" aria-hidden="true"></i>Cancel
    </button>
<?php endif; ?>
<?php $items = ob_get_clean(); ?>
<?= view(PHARMACY_VIEW . 'parts/more_actions', [
    'actionId' => $actionId,
    'ariaLabel' => 'More actions for prescription ' . (string) ($prescription['prescription_number'] ?? ''),
    'class' => 'pharmacy-review-actions',
    'items' => $items,
]) ?>
