<?php
$idPrefix = trim((string) ($idPrefix ?? 'telephone'));
$mobileName = (string) ($mobileName ?? 'mobile');
$mobileId = (string) ($mobileId ?? $idPrefix . '-mobile');
$selectedId = (int) ($selectedId ?? 0);
$manualCode = (string) ($manualCode ?? '');
$countryName = (string) ($countryName ?? '');
$manual = (bool) ($manual ?? false);
$mobileRequired = (bool) ($mobileRequired ?? false);
$requiredMark = $mobileRequired ? '<span class="text-danger" aria-hidden="true">*</span>' : '';
$telephoneCodes = $telephoneCodes ?? [];
?>
<div class="col-md-4 col-12 telephone-country-code-field">
    <label for="<?= esc($idPrefix) ?>-country-code">Country code <?= $requiredMark ?></label>
    <select
        class="form-control btSelect"
        data-live-search="true"
        title="Select country code"
        name="telephone_country_code_id"
        id="<?= esc($idPrefix) ?>-country-code"
        <?= $manual ? 'disabled' : ($mobileRequired ? 'required' : '') ?>
    >
        <option value="">Select country code</option>
        <?php foreach ($telephoneCodes as $telephoneCode): ?>
            <?php $code = preg_replace('/^\+/', '', trim((string) ($telephoneCode['normalized_code'] ?? ''))); ?>
            <?php if ($code !== ''): ?>
                <option
                    value="<?= (int) ($telephoneCode['id'] ?? 0) ?>"
                    data-phone-code="<?= esc($code) ?>"
                    <?= $selectedId === (int) ($telephoneCode['id'] ?? 0) ? 'selected' : '' ?>
                ><?= esc((string) ($telephoneCode['country_name'] ?? $telephoneCode['name'] ?? 'Country')) ?> (+<?= esc($code) ?>)</option>
            <?php endif; ?>
        <?php endforeach; ?>
    </select>
    <div class="custom-control custom-switch mt-2">
        <input
            type="checkbox"
            class="custom-control-input country-code-manual-toggle"
            id="<?= esc($idPrefix) ?>-country-code-manual"
            name="country_code_manual"
            value="1"
            data-select="#<?= esc($idPrefix) ?>-country-code"
            data-input="#<?= esc($idPrefix) ?>-country-code-input"
            data-name-input="#<?= esc($idPrefix) ?>-country-name"
            <?= $manual ? 'checked' : '' ?>
        >
        <label class="custom-control-label small" for="<?= esc($idPrefix) ?>-country-code-manual">Enter code manually</label>
    </div>
    <div class="validation-error" id="country_code_manualErr"></div>
    <div class="country-code-manual-fields <?= $manual ? '' : 'd-none' ?>">
        <label class="small mb-1" for="<?= esc($idPrefix) ?>-country-name">Country name <?= $requiredMark ?></label>
        <input
            type="text"
            class="form-control country-code-manual-name"
            name="telephone_country_name"
            id="<?= esc($idPrefix) ?>-country-name"
            value="<?= esc($countryName) ?>"
            placeholder="Country name"
            maxlength="191"
            <?= $manual && $mobileRequired ? 'required' : 'disabled' ?>
        >
        <div class="validation-error" id="telephone_country_nameErr"></div>
        <label class="small mb-1 mt-2" for="<?= esc($idPrefix) ?>-country-code-input">Telephone country code <?= $requiredMark ?></label>
        <input
            type="text"
            class="form-control country-code-manual-input"
            name="country_code"
            id="<?= esc($idPrefix) ?>-country-code-input"
            value="<?= esc($manualCode) ?>"
            placeholder="Example: +91"
            inputmode="tel"
            maxlength="5"
            pattern="\+?[0-9][0-9]{0,3}"
            title="Enter 1 to 4 digits, optionally prefixed with +."
            <?= $manual && $mobileRequired ? 'required' : 'disabled' ?>
        >
    </div>
    <div class="validation-error" id="country_codeErr"></div>
</div>
<div class="col-md-4 col-12 telephone-mobile-field">
    <label for="<?= esc($mobileId) ?>">Mobile number</label>
    <input type="text" class="form-control" name="<?= esc($mobileName) ?>" id="<?= esc($mobileId) ?>" value="<?= esc((string) ($mobileValue ?? '')) ?>" maxlength="20" inputmode="tel" <?= $mobileRequired ? 'required' : '' ?>>
    <div class="validation-error" id="<?= esc($mobileName) ?>Err"></div>
</div>
