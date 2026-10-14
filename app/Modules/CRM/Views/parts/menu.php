<?php
$permissions = session('permissions') ?? [];
if (in_array('crm.lead.view', $permissions, true)): ?><a href="<?= base_url('admin/crm/leads') ?>">Leads</a><?php endif;
if (in_array('crm.followup.view', $permissions, true)): ?><a href="<?= base_url('admin/crm/follow-ups') ?>">Follow-ups</a><?php endif;
