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
                                    <select class="input" id="v_type" name="type" required>
                                        <option value="">-- Select Vehicle Category --</option>
                                        <?php foreach ($types as $t):
                                            $selected = (string) old('type', $vehicle['type'] ?? '') === $t;
                                        ?>
                                            <option value="<?= e($t) ?>" <?= $selected ? 'selected' : '' ?>><?= e($t) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="hint">Helps tourists filter by transport preference.</small>
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

                            <div class="field-row">
                                <label for="v_image">Primary Cover Photo <?= !$isEdit ? '<span class="req" style="color:var(--red-600);">*</span>' : '' ?></label>
                                <?php if ($isEdit && !empty($vehicle['image'])): ?>
                                    <div style="margin-bottom:0.75rem;display:flex;align-items:center;gap:1rem;">
                                        <img src="<?= e(img_src($vehicle['image'], 'vehicle-' . $vehicle['id'])) ?>" alt="" style="width:140px;height:95px;object-fit:cover;border-radius:8px;border:1px solid var(--border);">
                                        <div class="hint" style="font-size:0.85rem;">
                                            Current cover photo. Choose a new file below if you wish to replace it.
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <input type="file" class="input" id="v_image" name="image" accept="image/jpeg,image/png,image/webp">
                                <small class="hint">Clear exterior photo showing the vehicle condition. JPG, PNG or WebP up to 5 MB.</small>
                                <?php if (isset($errors['image'])): ?><div class="field-error"><?= e($errors['image']) ?></div><?php endif; ?>
                            </div>

                            <div class="field-row" style="margin-top:1rem;">
                                <label for="v_gallery">Additional Photos <small class="hint">(Optional - up to 4 images)</small></label>
                                <input type="file" class="input" id="v_gallery" name="gallery[]" accept="image/jpeg,image/png,image/webp" multiple>
                                <small class="hint">Interior, dashboard, odometer, or extra angle views.</small>
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
                                <textarea class="input" id="v_description" name="description" rows="5" placeholder="Describe the vehicle condition, driving requirements (e.g. valid driver's license, security deposit if applicable), delivery options, and operating guidelines..." required><?= e(old('description', $vehicle['description'] ?? '')) ?></textarea>
                                <small class="hint">At least 20 characters describing the unit, requirements, and pickup details.</small>
                                <?php if (isset($errors['description'])): ?><div class="field-error"><?= e($errors['description']) ?></div><?php endif; ?>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div style="display:flex;gap:1rem;align-items:center;border-top:1px solid var(--border);padding-top:1.5rem;flex-wrap:wrap;">
                            <button type="submit" name="action" value="submit" class="btn btn-primary" onclick="return confirm('<?= $isRejected ? 'Resubmit this vehicle listing for administrator review?' : 'Submit this vehicle listing for administrator approval?' ?>');">
                                <?= $isRejected ? 'Resubmit for Approval' : ($isEdit ? 'Save &amp; Submit Changes' : 'Submit for Approval') ?>
                            </button>

                            <button type="submit" name="action" value="draft" class="btn btn-ghost" onclick="return confirm('Save this listing as draft? Drafts will not be sent for review until explicitly submitted.');">
                                Save as Draft
                            </button>

                            <a href="<?= e(url('/dashboard/vehicles')) ?>" class="btn btn-ghost" style="color:var(--text-muted);margin-left:auto;">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
