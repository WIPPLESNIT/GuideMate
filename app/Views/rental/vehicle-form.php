<?php
/**
 * @var array<string,mixed>|null $vehicle
 * @var array<int,string> $types
 * @var array<int,array<string,mixed>> $gallery
 * @var array<string,string> $errors
 */
$isEdit = $vehicle !== null;
$actionUrl = $isEdit ? url('/dashboard/vehicles/' . (int) $vehicle['id']) : url('/dashboard/vehicles');
$currentStatus = (string) ($vehicle['status'] ?? 'draft');
$isRejected = $isEdit && $currentStatus === 'rejected';
$isApproved = $isEdit && in_array($currentStatus, ['approved', 'unavailable'], true);

$currentSpecs = $isEdit ? \App\Models\Vehicle::parseSpecs($vehicle['specs'] ?? null) : [];
$commonSpecs = [
    'Automatic Transmission',
    'Manual Transmission',
    'Helmets Included',
    'Unlimited Mileage',
    'Air Conditioning',
    'Self-Drive',
    'With Driver',
    'Comprehensive Insurance Included',
    'Gasoline (Full to Full)',
    'Phone Mount & USB Charger',
    'Luggage Rack',
    'Off-Road Ready',
];
?>
<div class="container">
    <div class="dash-layout">
        <?= \App\Core\View::partial('partials/dash-nav') ?>
        <div>
            <div style="margin-bottom:1.5rem;">
                <a href="<?= e(url('/dashboard/vehicles')) ?>" class="btn btn-ghost btn-sm" style="margin-bottom:0.75rem;">← Back to My Vehicles</a>
                <div class="section-head" style="margin-bottom:0.25rem;">
                    <div>
                        <h1 class="mb-0"><?= $isEdit ? 'Edit Vehicle Listing' : 'List a Vehicle for Rent' ?></h1>
                        <p class="hint mb-0">Provide complete and accurate details about your rental unit.</p>
                    </div>
                    <?php if ($isEdit): ?>
                        <div>
                            <span class="pill <?= e(\App\Models\Vehicle::statusPillClass($currentStatus)) ?>">
                                <?= e(\App\Models\Vehicle::statusLabel($currentStatus)) ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($isRejected): ?>
                <div class="status-banner banner-red" style="margin-bottom:1.5rem;">
                    <span class="status-icon"><?= admin_icon('disputes', 24) ?></span>
                    <div>
                        <strong>Listing Returned with Feedback from Administrator:</strong>
                        <p style="margin:0.25rem 0 0.5rem 0;font-size:0.95rem;background:#ffffff;padding:0.6rem 0.8rem;border-radius:6px;border-left:3px solid var(--coral-600);color:#1f2937;">
                            <?= nl2br(e($vehicle['rejection_reason'] ?: 'Please review and update the listing details.')) ?>
                        </p>
                        <p class="mb-0 hint">
                            Update the information below to address the administrator's review notes, then click <strong>Resubmit for Approval</strong>.
                        </p>
                    </div>
                </div>
            <?php elseif ($isApproved): ?>
                <div class="status-banner banner-amber" style="margin-bottom:1.5rem;">
                    <span class="status-icon"><?= admin_icon('clock', 24) ?></span>
                    <div>
                        <strong>Notice on Editing Approved Listings</strong>
                        <p class="mb-0 hint">
                            If you modify significant rental specifications (such as make, model, year, vehicle type, rental price, location, photos, or description), this listing will return to <strong>Pending Approval</strong> until an administrator reviews it again.
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <div class="panel">
                <div class="panel-body">
                    <p class="hint" style="margin-top:0;margin-bottom:1.5rem;font-size:0.875rem;">
                        Fields marked with <span class="req" style="color:var(--red-600);font-weight:bold;">*</span> are required. Clear photos and accurate specs speed up administrator approval.
                    </p>

                    <form method="post" action="<?= e($actionUrl) ?>" enctype="multipart/form-data" novalidate id="vehicleForm">
                        <?= csrf_field() ?>

                        <!-- Section: Basic Information -->
                        <div style="margin-bottom:2rem;">
                            <h3 style="margin-bottom:1rem;border-bottom:1px solid var(--border);padding-bottom:0.5rem;">1. Vehicle Identification</h3>

                            <div class="form-grid-2">
                                <div class="field-row">
                                    <label for="v_make">Make / Brand <span class="req" style="color:var(--red-600);">*</span></label>
                                    <input class="input" id="v_make" name="make" value="<?= e(old('make', $vehicle['make'] ?? '')) ?>" placeholder="e.g. Honda, Toyota, Yamaha, Vespa" required>
                                    <small class="hint">The manufacturer of the vehicle.</small>
                                    <?php if (isset($errors['make'])): ?><div class="field-error"><?= e($errors['make']) ?></div><?php endif; ?>
                                </div>

                                <div class="field-row">
                                    <label for="v_model">Model <span class="req" style="color:var(--red-600);">*</span></label>
                                    <input class="input" id="v_model" name="model" value="<?= e(old('model', $vehicle['model'] ?? '')) ?>" placeholder="e.g. Click 125i, Vios, Aerox 155, Fortuner" required>
                                    <small class="hint">Specific model name and trim.</small>
                                    <?php if (isset($errors['model'])): ?><div class="field-error"><?= e($errors['model']) ?></div><?php endif; ?>
                                </div>
                            </div>

                            <div class="form-grid-2" style="margin-top:1rem;">
                                <div class="field-row">
                                    <label for="v_year">Model Year <span class="req" style="color:var(--red-600);">*</span></label>
                                    <input type="number" class="input" id="v_year" name="year" min="1980" max="<?= (int) date('Y') + 1 ?>" value="<?= e(old('year', (string) ($vehicle['year'] ?? date('Y')))) ?>" placeholder="e.g. 2023" required>
                                    <small class="hint">Manufacturing / registration year.</small>
                                    <?php if (isset($errors['year'])): ?><div class="field-error"><?= e($errors['year']) ?></div><?php endif; ?>
                                </div>

                                <div class="field-row">
                                    <label for="v_type">Vehicle Type <span class="req" style="color:var(--red-600);">*</span></label>
                                    <input type="text" class="input" id="v_type" name="type" value="<?= e(old('type', $vehicle['type'] ?? '')) ?>" placeholder="e.g. Scooter, Sedan, SUV, Van, Motorcycle" required maxlength="50">
                                    <small class="hint">Enter the vehicle type (e.g. Scooter, Sedan, SUV, Van, Motorcycle).</small>
                                    <?php if (isset($errors['type'])): ?><div class="field-error"><?= e($errors['type']) ?></div><?php endif; ?>
                                </div>
                            </div>

                            <div class="field-row" style="margin-top:1rem;">
                                <label for="v_name">Listing Display Title <small class="hint">(Optional - defaults to "Year Make Model")</small></label>
                                <input class="input" id="v_name" name="name" value="<?= e(old('name', $vehicle['name'] ?? '')) ?>" placeholder="e.g. 2023 Honda Click 125i (Automatic · Dual Helmet)">
                                <?php if (isset($errors['name'])): ?><div class="field-error"><?= e($errors['name']) ?></div><?php endif; ?>
                            </div>
                        </div>

                        <!-- Section: Pricing & Location -->
                        <div style="margin-bottom:2rem;">
                            <h3 style="margin-bottom:1rem;border-bottom:1px solid var(--border);padding-bottom:0.5rem;">2. Rates &amp; Pickup Location</h3>

                            <div class="form-grid-2">
                                <div class="field-row">
                                    <label for="v_price">Rental Rate per Day (PHP) <span class="req" style="color:var(--red-600);">*</span></label>
                                    <div style="position:relative;">
                                        <span style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#64748b;font-weight:600;">₱</span>
                                        <input type="number" step="10" min="50" class="input" id="v_price" name="price_per_day" style="padding-left:28px;" value="<?= e(old('price_per_day', (string) ($vehicle['price_per_day'] ?? ''))) ?>" placeholder="500" required>
                                    </div>
                                    <small class="hint">Daily rate paid by the tourist.</small>
                                    <?php if (isset($errors['price_per_day'])): ?><div class="field-error"><?= e($errors['price_per_day']) ?></div><?php endif; ?>
                                </div>

                                <div class="field-row">
                                    <label for="v_location">Pickup Area / Island Hub <span class="req" style="color:var(--red-600);">*</span></label>
                                    <input class="input" id="v_location" name="location" value="<?= e(old('location', $vehicle['location'] ?? '')) ?>" placeholder="e.g. Cebu City · IT Park, Mactan Island, Oslob" required>
                                    <small class="hint">General area or neighborhood where tourists can pick up or receive the unit.</small>
                                    <?php if (isset($errors['location'])): ?><div class="field-error"><?= e($errors['location']) ?></div><?php endif; ?>
                                </div>
                            </div>

                            <div class="field-row" style="margin-top:1rem;">
                                <label for="v_address">Pickup Street Address / Landmark <small class="hint">(Optional)</small></label>
                                <input class="input" id="v_address" name="address" value="<?= e(old('address', $vehicle['address'] ?? '')) ?>" placeholder="e.g. Beside J Centre Mall, A.S. Fortuna St, Mandaue City">
                                <?php if (isset($errors['address'])): ?><div class="field-error"><?= e($errors['address']) ?></div><?php endif; ?>
                            </div>
                        </div>

                        <!-- Section: Photos -->
                        <div style="margin-bottom:2rem;">
                            <h3 style="margin-bottom:1rem;border-bottom:1px solid var(--border);padding-bottom:0.5rem;">3. Vehicle Photos</h3>

                            <div class="field-row" style="background:#f8fafc;padding:0.85rem 1rem;border-radius:8px;border:1px dashed #cbd5e1;margin-bottom:0.5rem;">
                                <label style="font-weight:700;font-size:0.92rem;display:flex;align-items:center;justify-content:space-between;margin-bottom:0.25rem;color:var(--text, #0f172a);">
                                    <span>📸 Vehicle Photos (Upload up to 4 photos)</span>
                                    <span style="font-size:0.75rem;font-weight:600;background:#e0f2fe;color:#0284c7;padding:2px 8px;border-radius:12px;">Max 4 Photos</span>
                                </label>
                                <span class="hint" style="display:block;margin-bottom:0.75rem;font-size:0.8rem;color:#64748b;">
                                    Upload high-quality JPG, PNG, or WEBP images (up to 5MB each) or paste direct photo URLs. Photo 1 will be used as the primary cover photo.
                                </span>

                                <?php
                                $formPhotos = [];
                                if (!empty($vehicle['image'])) {
                                    $formPhotos[0] = $vehicle['image'];
                                }
                                if (!empty($vehicle['id'])) {
                                    $gal = \App\Models\Vehicle::gallery((int) $vehicle['id']);
                                    foreach ($gal as $idx => $g) {
                                        if ($idx < 3) {
                                            $formPhotos[$idx + 1] = is_array($g) ? ($g['image_path'] ?? '') : (string) $g;
                                        }
                                    }
                                }
                                ?>

                                <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(150px, 1fr));gap:0.75rem;">
                                    <?php for ($i = 0; $i < 4; $i++):
                                        $isCover = ($i === 0);
                                        $curPhoto = $formPhotos[$i] ?? '';
                                    ?>
                                        <div style="border:1px solid #e2e8f0;border-radius:8px;background:#ffffff;padding:0.7rem;display:flex;flex-direction:column;gap:0.4rem;">
                                            <div style="display:flex;justify-content:space-between;align-items:center;">
                                                <strong style="font-size:0.8rem;color:#0f172a;">Photo <?= $i + 1 ?> <?= $isCover ? '(Cover) <span class="req" style="color:var(--red-600);font-weight:bold;">*</span>' : '(Gallery)' ?></strong>
                                                <span style="font-size:0.68rem;background:<?= $isCover ? '#dcfce7' : '#f1f5f9' ?>;color:<?= $isCover ? '#166534' : '#64748b' ?>;font-weight:<?= $isCover ? '700' : '600' ?>;padding:1px 6px;border-radius:4px;">
                                                    <?= $isCover ? 'Cover' : 'Optional' ?>
                                                </span>
                                            </div>
                                            <div id="vform_preview_wrap_<?= $i ?>" style="position:relative;height:100px;background:#f1f5f9;border-radius:6px;overflow:hidden;display:flex;align-items:center;justify-content:center;border:1px solid #e2e8f0;">
                                                <img id="vform_preview_img_<?= $i ?>" src="<?= e($curPhoto ? (str_starts_with($curPhoto, 'http') ? $curPhoto : url('/' . ltrim($curPhoto, '/'))) : '') ?>" alt="Photo <?= $i + 1 ?>" style="width:100%;height:100%;object-fit:cover;<?= empty($curPhoto) ? 'display:none;' : '' ?>">
                                                <span id="vform_placeholder_<?= $i ?>" style="font-size:0.75rem;color:#94a3b8;text-align:center;padding:4px;<?= !empty($curPhoto) ? 'display:none;' : '' ?>">📷 No photo uploaded</span>
                                                <button type="button" id="vform_remove_btn_<?= $i ?>" onclick="removeFormPhoto(<?= $i ?>)" style="<?= empty($curPhoto) ? 'display:none;' : 'display:flex;' ?>position:absolute;top:4px;right:4px;background:rgba(0,0,0,0.65);color:#fff;border:none;border-radius:50%;width:22px;height:22px;font-size:12px;line-height:1;cursor:pointer;align-items:center;justify-content:center;" title="Remove Photo">&times;</button>
                                            </div>
                                            <input type="file" name="photo_file_<?= $i ?>" id="vform_file_<?= $i ?>" class="file-input" accept=".jpg,.jpeg,.png,.webp" style="font-size:0.75rem;width:100%;" onchange="handleFormFileChange(<?= $i ?>, this)">
                                            <input type="text" name="photo_url_<?= $i ?>" id="vform_url_<?= $i ?>" class="input" placeholder="…or photo URL" value="<?= (str_starts_with($curPhoto, 'http') ? e($curPhoto) : '') ?>" style="font-size:0.78rem;padding:0.35rem 0.5rem;" oninput="handleFormUrlChange(<?= $i ?>, this.value)">
                                            <input type="hidden" name="existing_photo_<?= $i ?>" id="vform_existing_<?= $i ?>" value="<?= e($curPhoto) ?>">
                                            <input type="hidden" name="photo_removed_<?= $i ?>" id="vform_removed_<?= $i ?>" value="0">
                                            <?php if ($isCover): ?>
                                                <input type="hidden" name="cover_image" id="vform_cover_image" value="<?= e($curPhoto) ?>">
                                            <?php endif; ?>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                                <?php if (isset($errors['image'])): ?><div class="field-error" style="margin-top:0.5rem;"><?= e($errors['image']) ?></div><?php endif; ?>
                            </div>
                        </div>

                        <!-- Section: Specs & Features -->
                        <div style="margin-bottom:2rem;">
                            <h3 style="margin-bottom:1rem;border-bottom:1px solid var(--border);padding-bottom:0.5rem;">4. Inclusions &amp; Features</h3>

                            <div class="hint" style="margin-bottom:0.75rem;">Check all features that apply to this vehicle:</div>
                            <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(220px, 1fr));gap:0.6rem;background:#f8fafc;padding:1rem;border-radius:8px;border:1px solid var(--border);">
                                <?php foreach ($commonSpecs as $spec):
                                    $checked = in_array($spec, $currentSpecs, true);
                                ?>
                                    <label style="display:flex;align-items:center;gap:0.5rem;font-size:0.875rem;cursor:pointer;">
                                        <input type="checkbox" name="specs[]" value="<?= e($spec) ?>" <?= $checked ? 'checked' : '' ?>>
                                        <span><?= e($spec) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>

                            <div class="field-row" style="margin-top:1rem;">
                                <label for="v_custom_spec">Custom Inclusions / Extra Feature <small class="hint">(Optional)</small></label>
                                <input class="input" id="v_custom_spec" name="custom_spec" placeholder="e.g. Free Airport Delivery, Top Box 45L">
                            </div>
                        </div>

                        <!-- Section: Description & Terms -->
                        <div style="margin-bottom:2rem;">
                            <h3 style="margin-bottom:1rem;border-bottom:1px solid var(--border);padding-bottom:0.5rem;">5. Description &amp; Requirements</h3>

                            <div class="field-row">
                                <label for="v_description">Vehicle Description &amp; Rental Terms <span class="req" style="color:var(--red-600);">*</span></label>
                                <textarea class="input" id="v_description" name="description" rows="5" minlength="20" maxlength="5000" placeholder="Describe the vehicle condition, driving requirements (e.g. valid driver's license, security deposit if applicable), delivery options, and operating guidelines..." required><?= e(old('description', $vehicle['description'] ?? '')) ?></textarea>
                                <div style="display:flex;justify-content:space-between;align-items:center;margin-top:0.25rem;">
                                    <small class="hint">At least 20 characters describing the unit, requirements, and pickup details.</small>
                                    <small id="v_desc_counter" class="hint" style="font-size:0.75rem;font-weight:500;">0 / 20 chars min</small>
                                </div>
                                <?php if (isset($errors['description'])): ?><div class="field-error"><?= e($errors['description']) ?></div><?php endif; ?>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div style="display:flex;gap:1rem;align-items:center;border-top:1px solid var(--border);padding-top:1.5rem;flex-wrap:wrap;">
                            <button type="submit" name="action" value="submit" class="btn btn-primary" onclick="return confirm('<?= $isRejected ? 'Resubmit this vehicle listing for administrator review?' : 'Submit this vehicle listing for administrator approval?' ?>');">
                                <?= $isRejected ? 'Resubmit for Approval' : ($isEdit ? 'Save &amp; Submit Changes' : 'Submit for Approval') ?>
                            </button>

                            <a href="<?= e(url('/dashboard/vehicles')) ?>" class="btn btn-ghost" style="color:var(--text-muted);margin-left:auto;">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var descIn = document.getElementById('v_description');
    var descCounter = document.getElementById('v_desc_counter');

    function updateDescCounter() {
        if (!descIn || !descCounter) return;
        var len = descIn.value.trim().length;
        descCounter.textContent = len + ' / 20 chars min';
        if (len >= 20) {
            descCounter.style.color = '#059669';
            descCounter.style.fontWeight = '700';
        } else {
            descCounter.style.color = '#64748b';
            descCounter.style.fontWeight = '500';
        }
    }

    if (descIn) {
        descIn.addEventListener('input', updateDescCounter);
        updateDescCounter();
    }

    function resolveImageUrl(path) {
        if (!path) return '';
        path = String(path).trim();
        if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('data:image/') || path.startsWith('data:')) {
            return path;
        }
        var base = <?= json_encode(rtrim(url('/'), '/')) ?> || '';
        if (base && (path === base || path.startsWith(base + '/'))) {
            return path.startsWith('/') ? path : '/' + path;
        }
        var clean = path.replace(/^\/+/, '');
        return (base ? base + '/' : '/') + clean;
    }

    window.handleFormFileChange = function(slot, input) {
        if (!input.files || !input.files[0]) return;
        var file = input.files[0];
        var reader = new FileReader();
        reader.onload = function(e) {
            setFormSlotPreview(slot, e.target.result);
            var remIn = document.getElementById('vform_removed_' + slot);
            if (remIn) remIn.value = '0';
        };
        reader.readAsDataURL(file);
    };

    window.handleFormUrlChange = function(slot, val) {
        val = (val || '').trim();
        var remIn = document.getElementById('vform_removed_' + slot);
        if (val) {
            setFormSlotPreview(slot, val);
            if (remIn) remIn.value = '0';
            if (slot === 0) {
                var cov = document.getElementById('vform_cover_image');
                if (cov) cov.value = val;
            }
        } else {
            var exist = document.getElementById('vform_existing_' + slot);
            if (exist && exist.value) {
                setFormSlotPreview(slot, exist.value);
            } else {
                clearFormSlotPreview(slot);
            }
        }
    };

    window.removeFormPhoto = function(slot) {
        var fileIn = document.getElementById('vform_file_' + slot);
        if (fileIn) fileIn.value = '';
        var urlIn = document.getElementById('vform_url_' + slot);
        if (urlIn) urlIn.value = '';
        var existingIn = document.getElementById('vform_existing_' + slot);
        if (existingIn) existingIn.value = '';
        var remIn = document.getElementById('vform_removed_' + slot);
        if (remIn) remIn.value = '1';
        if (slot === 0) {
            var cov = document.getElementById('vform_cover_image');
            if (cov) cov.value = '';
        }
        clearFormSlotPreview(slot);
    };

    function setFormSlotPreview(slot, src) {
        var img = document.getElementById('vform_preview_img_' + slot);
        var ph = document.getElementById('vform_placeholder_' + slot);
        var btn = document.getElementById('vform_remove_btn_' + slot);
        if (img && ph && btn) {
            var resolved = resolveImageUrl(src);
            img.onload = function() {
                img.style.display = 'block';
                ph.style.display = 'none';
                btn.style.display = 'flex';
            };
            img.onerror = function() {
                img.style.display = 'none';
                ph.textContent = '⚠️ Image not found';
                ph.style.display = 'block';
                btn.style.display = 'flex';
            };
            img.src = resolved;
            img.style.display = 'block';
            ph.style.display = 'none';
            btn.style.display = 'flex';
        }
    }

    function clearFormSlotPreview(slot) {
        var img = document.getElementById('vform_preview_img_' + slot);
        var ph = document.getElementById('vform_placeholder_' + slot);
        var btn = document.getElementById('vform_remove_btn_' + slot);
        if (img && ph && btn) {
            img.onload = null;
            img.onerror = null;
            img.src = '';
            img.style.display = 'none';
            ph.textContent = '📷 No photo uploaded';
            ph.style.display = 'block';
            btn.style.display = 'none';
        }
    }
})();
</script>
