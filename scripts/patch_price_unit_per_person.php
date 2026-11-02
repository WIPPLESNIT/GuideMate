<?php

// 1. Patch app/lang/en.php
$enPath = __DIR__ . '/../app/lang/en.php';
$enContent = file_get_contents($enPath);
$enContent = str_replace("\r\n", "\n", $enContent);
if (!str_contains($enContent, "'g_unit_per_group'")) {
    $enContent = str_replace(
        "'g_unit_per_person' => 'per person',",
        "'g_unit_per_person' => 'per person',\n    'g_unit_per_group' => 'per group',",
        $enContent
    );
    file_put_contents($enPath, $enContent);
    echo "app/lang/en.php patched.\n";
}

// 2. Patch app/Views/guide/listings.php
$listingsPath = __DIR__ . '/../app/Views/guide/listings.php';
$listingsContent = file_get_contents($listingsPath);
$listingsContent = str_replace("\r\n", "\n", $listingsContent);

// Update select dropdown in modal
$oldSelect = <<< 'HTML'
                    <div class="field-row">
                        <label for="nl_price_unit"><?= e(__('g_price_unit', 'Price unit')) ?> <span class="req">*</span></label>
                        <select class="input" id="nl_price_unit" name="price_unit">
                            <option value="per group" selected><?= e(__('g_unit_per_group', 'Per Group')) ?></option>
                        </select>
                        <span class="hint" style="font-size:0.75rem;color:#64748b;display:block;margin-top:2px;">Fixed rate for the entire group/tour.</span>
                    </div>
HTML;

$newSelect = <<< 'HTML'
                    <div class="field-row">
                        <label for="nl_price_unit"><?= e(__('g_price_unit', 'Price unit')) ?> <span class="req">*</span></label>
                        <?php $selectedUnit = strtolower((string) old('price_unit', 'per person')); ?>
                        <select class="input" id="nl_price_unit" name="price_unit" onchange="updateGuidePriceUnitHint(this.value)">
                            <option value="per person" <?= $selectedUnit === 'per person' ? 'selected' : '' ?>><?= e(__('g_unit_per_person', 'Per Person')) ?></option>
                            <option value="per group" <?= $selectedUnit === 'per group' ? 'selected' : '' ?>><?= e(__('g_unit_per_group', 'Per Group')) ?></option>
                        </select>
                        <span id="nl_price_unit_hint" class="hint" style="font-size:0.75rem;color:#64748b;display:block;margin-top:2px;"><?= $selectedUnit === 'per group' ? 'Fixed rate for the entire group/tour.' : 'Rate per individual tourist/guest.' ?></span>
                    </div>
HTML;

if (str_contains($listingsContent, $oldSelect)) {
    $listingsContent = str_replace($oldSelect, $newSelect, $listingsContent);
    echo "listings.php dropdown replaced.\n";
} else {
    echo "NOTE: oldSelect not found in listings.php or already updated.\n";
}

// Update JS openCreateListingModal
$oldCreatePu = "var puEl = document.getElementById('nl_price_unit'); if (puEl) puEl.value = 'per group';";
$newCreatePu = "var puEl = document.getElementById('nl_price_unit'); if (puEl) { puEl.value = 'per person'; updateGuidePriceUnitHint('per person'); }";

if (str_contains($listingsContent, $oldCreatePu)) {
    $listingsContent = str_replace($oldCreatePu, $newCreatePu, $listingsContent);
    echo "listings.php openCreateListingModal updated.\n";
}

// Update JS openEditListingModal
$oldEditPu = "var puEl = document.getElementById('nl_price_unit'); if (puEl) puEl.value = 'per group';";
$newEditPu = "var puEl = document.getElementById('nl_price_unit'); if (puEl) { var u = (listing.price_unit || 'per person').toLowerCase(); puEl.value = (u === 'per group') ? 'per group' : 'per person'; updateGuidePriceUnitHint(puEl.value); }";

if (str_contains($listingsContent, $oldEditPu)) {
    $listingsContent = str_replace($oldEditPu, $newEditPu, $listingsContent);
    echo "listings.php openEditListingModal updated.\n";
}

// Add window.updateGuidePriceUnitHint if not present
if (!str_contains($listingsContent, 'window.updateGuidePriceUnitHint')) {
    $hintFunc = <<< 'JS'

    window.updateGuidePriceUnitHint = function (val) {
        var hint = document.getElementById('nl_price_unit_hint');
        if (!hint) return;
        if (val === 'per group') {
            hint.textContent = 'Fixed rate for the entire group/tour.';
        } else {
            hint.textContent = 'Rate per individual tourist/guest.';
        }
    };
JS;
    $listingsContent = str_replace('window.openCreateListingModal = function () {', $hintFunc . "\n\n    window.openCreateListingModal = function () {", $listingsContent);
    echo "listings.php updateGuidePriceUnitHint added.\n";
}

file_put_contents($listingsPath, $listingsContent);

// 3. Patch app/Views/guide/form.php
$formPath = __DIR__ . '/../app/Views/guide/form.php';
$formContent = file_get_contents($formPath);
$formContent = str_replace("\r\n", "\n", $formContent);

$oldFormSelect = <<< 'HTML'
                            <div class="field-row">
                                <label for="price_unit"><?= e(__('g_price_unit', 'Price unit')) ?> <span class="req">*</span></label>
                                <select class="input" id="price_unit" name="price_unit">
                                    <option value="per group" selected><?= e(__('g_unit_per_group', 'Per Group')) ?></option>
                                </select>
                                <span class="hint" style="font-size:0.75rem;color:#64748b;display:block;margin-top:2px;">Fixed rate for the entire group/tour.</span>
                            </div>
HTML;

$newFormSelect = <<< 'HTML'
                            <div class="field-row">
                                <label for="price_unit"><?= e(__('g_price_unit', 'Price unit')) ?> <span class="req">*</span></label>
                                <?php $formUnit = strtolower((string) $val('price_unit', 'per person')); ?>
                                <select class="input" id="price_unit" name="price_unit" onchange="var h = document.getElementById('form_price_unit_hint'); if(h) h.textContent = this.value === 'per group' ? 'Fixed rate for the entire group/tour.' : 'Rate per individual tourist/guest.';">
                                    <option value="per person" <?= $formUnit === 'per person' ? 'selected' : '' ?>><?= e(__('g_unit_per_person', 'Per Person')) ?></option>
                                    <option value="per group" <?= $formUnit === 'per group' ? 'selected' : '' ?>><?= e(__('g_unit_per_group', 'Per Group')) ?></option>
                                </select>
                                <span id="form_price_unit_hint" class="hint" style="font-size:0.75rem;color:#64748b;display:block;margin-top:2px;"><?= $formUnit === 'per group' ? 'Fixed rate for the entire group/tour.' : 'Rate per individual tourist/guest.' ?></span>
                            </div>
HTML;

if (str_contains($formContent, $oldFormSelect)) {
    $formContent = str_replace($oldFormSelect, $newFormSelect, $formContent);
    file_put_contents($formPath, $formContent);
    echo "form.php dropdown updated.\n";
} else {
    echo "NOTE: oldFormSelect not found in form.php.\n";
}

// 4. Patch app/Controllers/GuideController.php
$gcPath = __DIR__ . '/../app/Controllers/GuideController.php';
$gcContent = file_get_contents($gcPath);
$gcContent = str_replace("\r\n", "\n", $gcContent);

$oldGcPriceUnit = <<< 'PHP'
        $priceUnit = trim((string) $this->input('price_unit', 'per group'));
        if ($priceUnit === '' || in_array(strtolower($priceUnit), ['per night', 'per tour', 'per day', 'per table'], true)) {
            $priceUnit = 'per group';
        }
PHP;

$newGcPriceUnit = <<< 'PHP'
        $priceUnit = strtolower(trim((string) $this->input('price_unit', 'per person')));
        $allowedUnits = ['per person', 'per group'];
        if (!in_array($priceUnit, $allowedUnits, true)) {
            $priceUnit = 'per person';
        }
PHP;

if (str_contains($gcContent, $oldGcPriceUnit)) {
    $gcContent = str_replace($oldGcPriceUnit, $newGcPriceUnit, $gcContent);
    file_put_contents($gcPath, $gcContent);
    echo "GuideController.php price_unit handling updated.\n";
} else {
    echo "NOTE: oldGcPriceUnit not found in GuideController.php.\n";
}

// 5. Patch app/Models/Listing.php
$listingModelPath = __DIR__ . '/../app/Models/Listing.php';
$listingModelContent = file_get_contents($listingModelPath);
$listingModelContent = str_replace("\r\n", "\n", $listingModelContent);

$oldLmDefault = "\$data['price_unit'] = \$data['price_unit'] ?? 'per group';";
$newLmDefault = "\$data['price_unit'] = !empty(\$data['price_unit']) ? (string) \$data['price_unit'] : 'per person';";

if (str_contains($listingModelContent, $oldLmDefault)) {
    $listingModelContent = str_replace($oldLmDefault, $newLmDefault, $listingModelContent);
    file_put_contents($listingModelPath, $listingModelContent);
    echo "Listing.php default price_unit updated.\n";
}

// 6. Patch app/listing/[slug].tsx
$slugPath = __DIR__ . '/../app/listing/[slug].tsx';
$slugContent = file_get_contents($slugPath);
$slugContent = str_replace("\r\n", "\n", $slugContent);

$oldSlugDurationDesc = "Pricing is per group{listing.price > 0 ? ` (\${formatPrice(listing.price)} total)` : ''}. Book once for your entire party.";
$newSlugDurationDesc = "{isPerGroup ? `Pricing is per group\${listing.price > 0 ? ` (\${formatPrice(listing.price)} total)` : ''}. Book once for your entire party.` : `Pricing is per person\${listing.price > 0 ? ` (\${formatPrice(listing.price)} each)` : ''}. Rate is calculated based on number of participants.`}";

if (str_contains($slugContent, $oldSlugDurationDesc)) {
    $slugContent = str_replace($oldSlugDurationDesc, $newSlugDurationDesc, $slugContent);
    file_put_contents($slugPath, $slugContent);
    echo "app/listing/[slug].tsx updated for dynamic per-person / per-group duration description.\n";
}

echo "All patches completed successfully!\n";
