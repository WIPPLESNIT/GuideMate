<?php
/**
 * @var array<int,array<string,mixed>> $vehicles
 * @var array<string,int> $stats
 * @var bool $isVerified
 * @var array<string,mixed> $user
 * @var array<int,string> $types
 * @var array<string,string> $errors
 */
$types = $types ?? \App\Models\Vehicle::TYPES;
$errors = $errors ?? [];
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
        <div class="dash-main">
            <div class="section-head" style="margin-bottom:1rem;">
                <div>
                    <h1 class="mb-0">My Vehicles</h1>
                    <p class="hint mb-0">Manage your rental fleet and track approval statuses.</p>
                </div>
                <?php if (!$isVerified): ?>
                    <div style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;">
                        <a href="<?= e(url('/documents')) ?>" class="btn btn-ghost"><?= admin_icon('guides', 16) ?> Complete Verification</a>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!$isVerified): ?>
                <div class="status-banner banner-amber" style="margin-bottom:1.2rem;">
                    <span class="status-icon"><?= admin_icon('clock', 22) ?></span>
                    <div>
                        <strong>Rental Partner Verification Pending</strong>
                        <p class="mb-0">
                            Your rental partner documents are currently under administrative review. Once your account is verified, you will be able to list vehicles and accept reservations.
                            <a href="<?= e(url('/documents')) ?>">View verification status →</a>
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (($stats['rejected'] ?? 0) > 0): ?>
                <div class="status-banner banner-red" style="margin-bottom:1.2rem;">
                    <span class="status-icon"><?= admin_icon('disputes', 22) ?></span>
                    <div class="status-content">
                        <strong class="status-title">Action Required: Rejected Vehicle Listing(s)</strong>
                        <p class="status-desc mb-0">
                            An administrator has requested changes on <?= (int) $stats['rejected'] ?> of your listings. Click <strong>Edit</strong> on the rejected vehicle to review the administrator's feedback and resubmit.
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <div class="panel">
                <div class="panel-head">
                    <h3>Vehicle Inventory (<?= count($vehicles) ?>)</h3>
                    <?php if ($isVerified): ?>
                        <button type="button" class="btn btn-primary btn-sm" onclick="openCreateVehicleModal()" data-action="new-listing" data-open-new-listing>+ List Vehicle</button>
                    <?php else: ?>
                        <a href="<?= e(url('/documents')) ?>" class="btn btn-primary btn-sm">+ List Vehicle</a>
                    <?php endif; ?>
                </div>
                <div class="panel-body rental-inventory-wrap" style="padding:0;">
                    <?php if ($vehicles === []): ?>
                        <div class="empty-state" style="padding:3rem 1rem;">
                            <div class="big">🚗</div>
                            <h3>No vehicle listings yet</h3>
                            <p>Add your scooters, motorcycles, cars, or vans so tourists traveling in Cebu can rent from you.</p>
                            <?php if ($isVerified): ?>
                                <button type="button" class="btn btn-primary mt-2" onclick="openCreateVehicleModal()" data-action="new-listing" data-open-new-listing>+ List your first vehicle</button>
                            <?php else: ?>
                                <a href="<?= e(url('/documents')) ?>" class="btn btn-primary mt-2">+ List your first vehicle</a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <table class="table rental-inventory-table">
                            <thead>
                                <tr>
                                    <th class="col-vehicle">Vehicle</th>
                                    <th class="col-type">Type</th>
                                    <th class="col-rate">Daily Rate</th>
                                    <th class="col-status">Status</th>
                                    <th class="col-availability">Availability</th>
                                    <th class="col-actions">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($vehicles as $v):
                                $vStatus = (string) ($v['status'] ?? 'draft');
                                $isAvail = (int) ($v['is_available'] ?? 0) === 1;
                                $canToggle = in_array($vStatus, ['approved', 'unavailable'], true);
                            ?>
                                <tr>
                                    <td class="col-vehicle">
                                        <div style="display:flex;align-items:center;gap:0.75rem;">
                                            <img src="<?= e(img_src($v['image'] ?? null, 'vehicle-' . $v['id'])) ?>" alt=""
                                                 style="width:52px;height:52px;border-radius:8px;object-fit:cover;flex-shrink:0;background:#f1f5f9;">
                                            <div style="min-width:0;flex:1 1 auto;">
                                                <strong style="display:block;font-weight:700;color:var(--text, #0f172a);line-height:1.35;word-break:break-word;"><?= e($v['name']) ?></strong>
                                                <div class="hint" style="font-size:0.8rem;margin-top:0.15rem;word-break:break-word;">
                                                    <?= e($v['location']) ?> · <?= (int) $v['year'] ?> <?= e($v['make']) ?>
                                                </div>
                                                <?php if ($vStatus === 'rejected' && !empty($v['rejection_reason'])):
                                                    $reasonText = (string) $v['rejection_reason'];
                                                    $lineCount = substr_count(trim($reasonText), "\n") + 1;
                                                    $charCount = mb_strlen(trim($reasonText));
                                                    $isLong = $lineCount >= 3 || $charCount > 90;
                                                ?>
                                                    <div class="admin-note-card <?= $isLong ? 'is-long' : '' ?>">
                                                        <div class="admin-note-header">
                                                            <span class="admin-note-label">
                                                                <svg class="admin-note-icon" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                                    <circle cx="12" cy="12" r="10"></circle>
                                                                    <line x1="12" y1="8" x2="12" y2="12"></line>
                                                                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                                                                </svg>
                                                                Admin Note:
                                                            </span>
                                                            <?php if ($isLong): ?>
                                                                <button type="button" class="admin-note-toggle" aria-expanded="false" title="Expand or collapse admin note">
                                                                    <span class="toggle-text">Read more</span>
                                                                    <svg class="toggle-arrow" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                                        <polyline points="6 9 12 15 18 9"></polyline>
                                                                    </svg>
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="admin-note-body <?= $isLong ? 'is-clamped' : '' ?>" tabindex="0"><?= nl2br(e($reasonText)) ?></div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="col-type"><?= e($v['type']) ?></td>
                                    <td class="col-rate">
                                        <strong style="color:var(--brand, #059669);font-weight:700;"><?= money((float) $v['price_per_day']) ?></strong>
                                        <small class="hint">/ day</small>
                                    </td>
                                    <td class="col-status">
                                        <span class="pill <?= e(\App\Models\Vehicle::statusPillClass($vStatus)) ?>">
                                            <?= e(\App\Models\Vehicle::statusLabel($vStatus)) ?>
                                        </span>
                                    </td>
                                    <td class="col-availability">
                                        <?php if ($canToggle): ?>
                                            <span class="pill <?= $isAvail ? 'pill-available' : 'pill-unavailable' ?>" style="font-size:0.75rem;padding:3px 10px;">
                                                <?= $isAvail ? '● Available' : '● Unavailable' ?>
                                            </span>
                                        <?php elseif ($vStatus === 'pending'): ?>
                                            <span class="hint" style="font-size:0.8rem;white-space:nowrap;">Pending Approval</span>
                                        <?php elseif ($vStatus === 'rejected'): ?>
                                            <span class="hint" style="font-size:0.8rem;color:var(--coral-600);white-space:nowrap;">Requires revision</span>
                                        <?php else: ?>
                                            <span class="hint" style="font-size:0.8rem;white-space:nowrap;">Draft</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-actions">
                                        <div class="res-action-wrap">
                                            <button type="button"
                                                    class="btn-res-action-trigger"
                                                    id="vehicleActionBtn_<?= (int) $v['id'] ?>"
                                                    aria-haspopup="true"
                                                    aria-expanded="false"
                                                    aria-controls="vehicleActionMenu_<?= (int) $v['id'] ?>"
                                                    title="Actions for <?= e($v['name']) ?>"
                                                    aria-label="Actions for <?= e($v['name']) ?>">
                                                <?= admin_icon('more-vertical', 16) ?>
                                            </button>
                                            <div class="res-action-dropdown"
                                                 id="vehicleActionMenu_<?= (int) $v['id'] ?>"
                                                 role="menu"
                                                 aria-labelledby="vehicleActionBtn_<?= (int) $v['id'] ?>">
                                                <button type="button" class="res-action-item" data-edit-url="<?= e(url('/dashboard/vehicles/' . (int) $v['id'] . '/edit')) ?>" onclick='openEditVehicleModal(<?= htmlspecialchars(json_encode($v, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, "UTF-8") ?>)' role="menuitem">
                                                    <?= admin_icon('edit', 14) ?> Edit Vehicle
                                                </button>
                                                <?php if ($canToggle): ?>
                                                    <form method="post" action="<?= e(url('/dashboard/vehicles/' . (int) $v['id'] . '/toggle-availability')) ?>" class="res-action-form">
                                                        <?= csrf_field() ?>
                                                        <?php if ($isAvail): ?>
                                                            <button type="submit" class="res-action-item action-cancel" role="menuitem">
                                                                <?= admin_icon('x-circle', 14) ?> Mark as Unavailable
                                                            </button>
                                                        <?php else: ?>
                                                            <button type="submit" class="res-action-item action-accept" role="menuitem">
                                                                <?= admin_icon('check-circle', 14) ?> Mark as Available
                                                            </button>
                                                        <?php endif; ?>
                                                    </form>
                                                <?php endif; ?>
                                                <?php if (in_array($vStatus, ['rejected', 'draft'], true)): ?>
                                                    <form method="post" action="<?= e(url('/dashboard/vehicles/' . (int) $v['id'] . '/resubmit')) ?>" class="res-action-form" onsubmit="return confirm('Submit this vehicle for administrator review?');">
                                                        <?= csrf_field() ?>
                                                        <button type="submit" class="res-action-item action-accept" role="menuitem">
                                                            <?= admin_icon('check-circle', 14) ?> Submit for Review
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                <?php if ($vStatus === 'approved'): ?>
                                                    <a href="<?= e(url('/rentals/' . ($v['slug'] ?? $v['id']))) ?>" target="_blank" class="res-action-item" role="menuitem">
                                                        <?= admin_icon('eye', 14) ?> View Live Listing
                                                    </a>
                                                <?php endif; ?>
                                                <div style="border-top:1px solid var(--line, #e2e8f0);margin:2px 0;"></div>
                                                <form method="post" action="<?= e(url('/dashboard/vehicles/' . (int) $v['id'] . '/delete')) ?>" class="res-action-form" onsubmit="return confirm('Delete this vehicle listing? This action cannot be undone.');">
                                                    <?= csrf_field() ?>
                                                    <button type="submit" class="res-action-item action-cancel" role="menuitem">
                                                        <?= admin_icon('trash', 14) ?> Delete Vehicle
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SHARED MODAL: CREATE & EDIT VEHICLE LISTING -->
<div class="gm-modal" id="vehicleModal" hidden aria-hidden="true">
    <div class="gm-modal-backdrop" onclick="closeVehicleModal()"></div>
    <div class="gm-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="vehicleModalTitle" style="max-width:760px;width:100%;max-height:92vh;overflow-y:auto;border-radius:14px;padding:1.5rem;background:#ffffff;box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);">
        <div class="gm-modal-head" style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1rem;border-bottom:1px solid var(--line, #e2e8f0);padding-bottom:0.75rem;">
            <div style="display:flex;align-items:center;gap:0.75rem;">
                <div style="width:42px;height:42px;border-radius:10px;background:rgba(5,150,105,0.1);color:#059669;display:flex;align-items:center;justify-content:center;font-size:1.35rem;flex-shrink:0;">
                    🚗
                </div>
                <div>
                    <h2 id="vehicleModalTitle" style="margin:0;font-size:1.25rem;font-weight:800;color:var(--text, #0f172a);">List a Vehicle for Rent</h2>
                    <p id="vehicleModalSubtitle" style="margin:0.15rem 0 0;font-size:0.85rem;color:var(--text-muted, #64748b);">Provide complete and accurate details about your rental unit.</p>
                </div>
            </div>
            <button type="button" class="gm-modal-close" onclick="closeVehicleModal()" aria-label="Close dialog" style="font-size:1.5rem;line-height:1;border:none;background:none;cursor:pointer;color:var(--text-muted, #64748b);">&times;</button>
        </div>

        <!-- Status Banner: Rejected Feedback (shown when editing a rejected listing) -->
        <div id="vf_rejected_banner" class="status-banner banner-red" style="display:none;margin-bottom:1.2rem;">
            <span class="status-icon"><?= admin_icon('disputes', 22) ?></span>
            <div>
                <strong>Listing Returned with Feedback from Administrator:</strong>
                <p id="vf_rejection_reason_text" style="margin:0.25rem 0 0.5rem 0;font-size:0.92rem;background:#ffffff;padding:0.6rem 0.8rem;border-radius:6px;border-left:3px solid var(--coral-600);color:#1f2937;"></p>
                <p class="mb-0 hint" style="font-size:0.82rem;">Update the information below to address the administrator's review notes, then click <strong>Resubmit for Approval</strong>.</p>
            </div>
        </div>

        <!-- Status Banner: Notice on Editing Approved Listings (shown when editing an approved listing) -->
        <div id="vf_approved_banner" class="status-banner banner-amber" style="display:none;margin-bottom:1.2rem;">
            <span class="status-icon"><?= admin_icon('clock', 22) ?></span>
            <div>
                <strong>Notice on Editing Approved Listings</strong>
                <p class="mb-0 hint" style="font-size:0.82rem;">Modifying rental specifications (rates, location, photos, vehicle type, or description) will return this listing to <strong>Pending Approval</strong> until reviewed again.</p>
            </div>
        </div>

        <p class="hint" style="margin-top:0;margin-bottom:1.2rem;font-size:0.85rem;">
            Fields marked with <span class="req" style="color:var(--red-600);font-weight:bold;">*</span> are required. Clear photos and accurate specs speed up administrator approval.
        </p>

        <form id="vehicleForm" method="post" action="<?= e(url('/dashboard/vehicles')) ?>" enctype="multipart/form-data" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" id="vf_vehicle_id" name="vehicle_id" value="">

            <!-- 1. Vehicle Identification -->
            <div style="margin-bottom:1.5rem;">
                <h3 style="margin-bottom:0.75rem;border-bottom:1px solid var(--border);padding-bottom:0.4rem;font-size:1.05rem;">1. Vehicle Identification</h3>
                <div class="form-grid-2">
                    <div class="field-row">
                        <label for="vf_make">Make / Brand <span class="req" style="color:var(--red-600);font-weight:bold;">*</span></label>
                        <input class="input" id="vf_make" name="make" placeholder="e.g. Honda, Toyota, Yamaha, Vespa" required maxlength="80">
                        <small class="hint">The manufacturer of the vehicle.</small>
                        <div class="field-error" id="vf_make_error" style="display:none;color:#dc2626;font-size:0.82rem;margin-top:0.25rem;font-weight:600;"></div>
                    </div>
                    <div class="field-row">
                        <label for="vf_model">Model <span class="req" style="color:var(--red-600);font-weight:bold;">*</span></label>
                        <input class="input" id="vf_model" name="model" placeholder="e.g. Click 125i, Vios, Aerox 155, Fortuner" required maxlength="80">
                        <small class="hint">Specific model name and trim.</small>
                        <div class="field-error" id="vf_model_error" style="display:none;color:#dc2626;font-size:0.82rem;margin-top:0.25rem;font-weight:600;"></div>
                    </div>
                </div>

                <div class="form-grid-2" style="margin-top:0.75rem;">
                    <div class="field-row">
                        <label for="vf_year">Model Year <span class="req" style="color:var(--red-600);font-weight:bold;">*</span></label>
                        <input type="number" class="input" id="vf_year" name="year" min="1980" max="<?= (int) date('Y') + 1 ?>" value="<?= (int) date('Y') ?>" placeholder="e.g. 2024" required>
                        <small class="hint">Manufacturing / registration year (1980 - <?= (int) date('Y') + 1 ?>).</small>
                        <div class="field-error" id="vf_year_error" style="display:none;color:#dc2626;font-size:0.82rem;margin-top:0.25rem;font-weight:600;"></div>
                    </div>
                    <div class="field-row">
                        <label for="vf_type">Vehicle Type <span class="req" style="color:var(--red-600);font-weight:bold;">*</span></label>
                        <input type="text" class="input" id="vf_type" name="type" placeholder="e.g. Scooter, Sedan, SUV, Van, Motorcycle" required maxlength="50">
                        <small class="hint">Enter the vehicle type (e.g. Scooter, Sedan, SUV, Van, Motorcycle).</small>
                        <div class="field-error" id="vf_type_error" style="display:none;color:#dc2626;font-size:0.82rem;margin-top:0.25rem;font-weight:600;"></div>
                    </div>
                </div>

                <div class="form-grid-2" style="margin-top:0.75rem;">
                    <div class="field-row">
                        <label for="vf_passenger_capacity">Passenger Capacity <span class="req" style="color:var(--red-600);font-weight:bold;">*</span></label>
                        <input type="number" class="input" id="vf_passenger_capacity" name="passenger_capacity" min="1" max="100" placeholder="e.g. 2 for scooter, 4 for sedan" required>
                        <small class="hint">Maximum number of passengers the vehicle accommodates.</small>
                        <div class="field-error" id="vf_passenger_capacity_error" style="display:none;color:#dc2626;font-size:0.82rem;margin-top:0.25rem;font-weight:600;"></div>
                    </div>
                    <div class="field-row">
                        <label for="vf_name">Listing Display Title <small class="hint">(Optional)</small></label>
                        <input class="input" id="vf_name" name="name" placeholder="e.g. 2024 Honda Click 125i (Automatic · Dual Helmet)" maxlength="160">
                        <div class="field-error" id="vf_name_error" style="display:none;color:#dc2626;font-size:0.82rem;margin-top:0.25rem;font-weight:600;"></div>
                    </div>
                </div>
            </div>

            <!-- 2. Rates & Pickup Location -->
            <div style="margin-bottom:1.5rem;">
                <h3 style="margin-bottom:0.75rem;border-bottom:1px solid var(--border);padding-bottom:0.4rem;font-size:1.05rem;">2. Rates &amp; Pickup Location</h3>
                <div class="form-grid-2">
                    <div class="field-row">
                        <label for="vf_price">Rental Rate per Day (PHP) <span class="req" style="color:var(--red-600);font-weight:bold;">*</span></label>
                        <div style="position:relative;">
                            <span style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#64748b;font-weight:600;">₱</span>
                            <input type="number" step="10" min="50" max="500000" class="input" id="vf_price" name="price_per_day" style="padding-left:28px;" placeholder="500" required>
                        </div>
                        <small class="hint">Daily rate paid by the tourist (minimum ₱50).</small>
                        <div class="field-error" id="vf_price_per_day_error" style="display:none;color:#dc2626;font-size:0.82rem;margin-top:0.25rem;font-weight:600;"></div>
                    </div>
                    <div class="field-row">
                        <label for="vf_location">Pickup Area / Island Hub <span class="req" style="color:var(--red-600);font-weight:bold;">*</span></label>
                        <input class="input" id="vf_location" name="location" placeholder="e.g. Cebu City · IT Park, Mactan Island, Oslob" required maxlength="160">
                        <small class="hint">General area or neighborhood where tourists pick up the unit.</small>
                        <div class="field-error" id="vf_location_error" style="display:none;color:#dc2626;font-size:0.82rem;margin-top:0.25rem;font-weight:600;"></div>
                    </div>
                </div>

                <div class="field-row" style="margin-top:0.75rem;">
                    <label for="vf_address">Pickup Street Address / Landmark <small class="hint">(Optional)</small></label>
                    <input class="input" id="vf_address" name="address" placeholder="e.g. Beside J Centre Mall, A.S. Fortuna St, Mandaue City" maxlength="255">
                    <div class="field-error" id="vf_address_error" style="display:none;color:#dc2626;font-size:0.82rem;margin-top:0.25rem;font-weight:600;"></div>
                </div>
            </div>

            <!-- 3. Vehicle Photos -->
            <div style="margin-bottom:1.5rem;">
                <h3 style="margin-bottom:0.75rem;border-bottom:1px solid var(--border);padding-bottom:0.4rem;font-size:1.05rem;">3. Vehicle Photos</h3>
                <div class="field-row" style="background:#f8fafc;padding:0.85rem 1rem;border-radius:8px;border:1px dashed #cbd5e1;margin-bottom:0.5rem;">
                    <label style="font-weight:700;font-size:0.92rem;display:flex;align-items:center;justify-content:space-between;margin-bottom:0.25rem;color:var(--text, #0f172a);">
                        <span>📸 Vehicle Photos (Upload up to 4 photos)</span>
                        <span style="font-size:0.75rem;font-weight:600;background:#e0f2fe;color:#0284c7;padding:2px 8px;border-radius:12px;">Max 4 Photos</span>
                    </label>
                    <span class="hint" style="display:block;margin-bottom:0.75rem;font-size:0.8rem;color:#64748b;">
                        Upload high-quality JPG, PNG, or WEBP images (up to 5MB each) or paste direct photo URLs. Photo 1 will be used as the primary cover photo.
                    </span>

                    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(150px, 1fr));gap:0.75rem;">
                        <!-- Slot 0: Primary Cover -->
                        <div style="border:1px solid #e2e8f0;border-radius:8px;background:#ffffff;padding:0.7rem;display:flex;flex-direction:column;gap:0.4rem;">
                            <div style="display:flex;justify-content:space-between;align-items:center;">
                                <strong style="font-size:0.8rem;color:#0f172a;">Photo 1 (Cover) <span id="vf_image_req" class="req" style="color:var(--red-600);font-weight:bold;">*</span></strong>
                                <span style="font-size:0.68rem;background:#dcfce7;color:#166534;font-weight:700;padding:1px 6px;border-radius:4px;">Cover</span>
                            </div>
                            <div id="vf_preview_wrap_0" style="position:relative;height:100px;background:#f1f5f9;border-radius:6px;overflow:hidden;display:flex;align-items:center;justify-content:center;border:1px solid #e2e8f0;">
                                <img id="vf_preview_img_0" src="" alt="Photo 1" style="width:100%;height:100%;object-fit:cover;display:none;">
                                <span id="vf_placeholder_0" style="font-size:0.75rem;color:#94a3b8;text-align:center;padding:4px;">📷 No photo uploaded</span>
                                <button type="button" id="vf_remove_btn_0" onclick="removeVehiclePhoto(0)" style="display:none;position:absolute;top:4px;right:4px;background:rgba(0,0,0,0.65);color:#fff;border:none;border-radius:50%;width:22px;height:22px;font-size:12px;line-height:1;cursor:pointer;align-items:center;justify-content:center;" title="Remove Photo">&times;</button>
                            </div>
                            <input type="file" name="photo_file_0" id="vf_file_0" class="file-input" accept=".jpg,.jpeg,.png,.webp" style="font-size:0.75rem;width:100%;" onchange="handleVehicleFileChange(0, this)">
                            <input type="text" name="photo_url_0" id="vf_url_0" class="input" placeholder="…or photo URL" style="font-size:0.78rem;padding:0.35rem 0.5rem;" oninput="handleVehicleUrlChange(0, this.value)">
                            <input type="hidden" name="existing_photo_0" id="vf_existing_0" value="">
                            <input type="hidden" name="photo_removed_0" id="vf_removed_0" value="0">
                            <input type="hidden" name="cover_image" id="vf_cover_image" value="">
                        </div>

                        <!-- Slot 1: Additional Photo 2 -->
                        <div style="border:1px solid #e2e8f0;border-radius:8px;background:#ffffff;padding:0.7rem;display:flex;flex-direction:column;gap:0.4rem;">
                            <div style="display:flex;justify-content:space-between;align-items:center;">
                                <strong style="font-size:0.8rem;color:#0f172a;">Photo 2 (Gallery)</strong>
                                <span style="font-size:0.68rem;background:#f1f5f9;color:#64748b;font-weight:600;padding:1px 6px;border-radius:4px;">Optional</span>
                            </div>
                            <div id="vf_preview_wrap_1" style="position:relative;height:100px;background:#f1f5f9;border-radius:6px;overflow:hidden;display:flex;align-items:center;justify-content:center;border:1px solid #e2e8f0;">
                                <img id="vf_preview_img_1" src="" alt="Photo 2" style="width:100%;height:100%;object-fit:cover;display:none;">
                                <span id="vf_placeholder_1" style="font-size:0.75rem;color:#94a3b8;text-align:center;padding:4px;">📷 No photo uploaded</span>
                                <button type="button" id="vf_remove_btn_1" onclick="removeVehiclePhoto(1)" style="display:none;position:absolute;top:4px;right:4px;background:rgba(0,0,0,0.65);color:#fff;border:none;border-radius:50%;width:22px;height:22px;font-size:12px;line-height:1;cursor:pointer;align-items:center;justify-content:center;" title="Remove Photo">&times;</button>
                            </div>
                            <input type="file" name="photo_file_1" id="vf_file_1" class="file-input" accept=".jpg,.jpeg,.png,.webp" style="font-size:0.75rem;width:100%;" onchange="handleVehicleFileChange(1, this)">
                            <input type="text" name="photo_url_1" id="vf_url_1" class="input" placeholder="…or photo URL" style="font-size:0.78rem;padding:0.35rem 0.5rem;" oninput="handleVehicleUrlChange(1, this.value)">
                            <input type="hidden" name="existing_photo_1" id="vf_existing_1" value="">
                            <input type="hidden" name="photo_removed_1" id="vf_removed_1" value="0">
                        </div>

                        <!-- Slot 2: Additional Photo 3 -->
                        <div style="border:1px solid #e2e8f0;border-radius:8px;background:#ffffff;padding:0.7rem;display:flex;flex-direction:column;gap:0.4rem;">
                            <div style="display:flex;justify-content:space-between;align-items:center;">
                                <strong style="font-size:0.8rem;color:#0f172a;">Photo 3 (Gallery)</strong>
                                <span style="font-size:0.68rem;background:#f1f5f9;color:#64748b;font-weight:600;padding:1px 6px;border-radius:4px;">Optional</span>
                            </div>
                            <div id="vf_preview_wrap_2" style="position:relative;height:100px;background:#f1f5f9;border-radius:6px;overflow:hidden;display:flex;align-items:center;justify-content:center;border:1px solid #e2e8f0;">
                                <img id="vf_preview_img_2" src="" alt="Photo 3" style="width:100%;height:100%;object-fit:cover;display:none;">
                                <span id="vf_placeholder_2" style="font-size:0.75rem;color:#94a3b8;text-align:center;padding:4px;">📷 No photo uploaded</span>
                                <button type="button" id="vf_remove_btn_2" onclick="removeVehiclePhoto(2)" style="display:none;position:absolute;top:4px;right:4px;background:rgba(0,0,0,0.65);color:#fff;border:none;border-radius:50%;width:22px;height:22px;font-size:12px;line-height:1;cursor:pointer;align-items:center;justify-content:center;" title="Remove Photo">&times;</button>
                            </div>
                            <input type="file" name="photo_file_2" id="vf_file_2" class="file-input" accept=".jpg,.jpeg,.png,.webp" style="font-size:0.75rem;width:100%;" onchange="handleVehicleFileChange(2, this)">
                            <input type="text" name="photo_url_2" id="vf_url_2" class="input" placeholder="…or photo URL" style="font-size:0.78rem;padding:0.35rem 0.5rem;" oninput="handleVehicleUrlChange(2, this.value)">
                            <input type="hidden" name="existing_photo_2" id="vf_existing_2" value="">
                            <input type="hidden" name="photo_removed_2" id="vf_removed_2" value="0">
                        </div>

                        <!-- Slot 3: Additional Photo 4 -->
                        <div style="border:1px solid #e2e8f0;border-radius:8px;background:#ffffff;padding:0.7rem;display:flex;flex-direction:column;gap:0.4rem;">
                            <div style="display:flex;justify-content:space-between;align-items:center;">
                                <strong style="font-size:0.8rem;color:#0f172a;">Photo 4 (Gallery)</strong>
                                <span style="font-size:0.68rem;background:#f1f5f9;color:#64748b;font-weight:600;padding:1px 6px;border-radius:4px;">Optional</span>
                            </div>
                            <div id="vf_preview_wrap_3" style="position:relative;height:100px;background:#f1f5f9;border-radius:6px;overflow:hidden;display:flex;align-items:center;justify-content:center;border:1px solid #e2e8f0;">
                                <img id="vf_preview_img_3" src="" alt="Photo 4" style="width:100%;height:100%;object-fit:cover;display:none;">
                                <span id="vf_placeholder_3" style="font-size:0.75rem;color:#94a3b8;text-align:center;padding:4px;">📷 No photo uploaded</span>
                                <button type="button" id="vf_remove_btn_3" onclick="removeVehiclePhoto(3)" style="display:none;position:absolute;top:4px;right:4px;background:rgba(0,0,0,0.65);color:#fff;border:none;border-radius:50%;width:22px;height:22px;font-size:12px;line-height:1;cursor:pointer;align-items:center;justify-content:center;" title="Remove Photo">&times;</button>
                            </div>
                            <input type="file" name="photo_file_3" id="vf_file_3" class="file-input" accept=".jpg,.jpeg,.png,.webp" style="font-size:0.75rem;width:100%;" onchange="handleVehicleFileChange(3, this)">
                            <input type="text" name="photo_url_3" id="vf_url_3" class="input" placeholder="…or photo URL" style="font-size:0.78rem;padding:0.35rem 0.5rem;" oninput="handleVehicleUrlChange(3, this.value)">
                            <input type="hidden" name="existing_photo_3" id="vf_existing_3" value="">
                            <input type="hidden" name="photo_removed_3" id="vf_removed_3" value="0">
                        </div>
                    </div>
                    <div class="field-error" id="vf_image_error" style="display:none;color:#dc2626;font-size:0.82rem;margin-top:0.5rem;font-weight:600;"></div>
                </div>
            </div>

            <!-- What's Included -->
            <div style="margin-bottom:1.5rem;">
                <h3 style="margin-bottom:0.75rem;border-bottom:1px solid var(--border);padding-bottom:0.4rem;font-size:1.05rem;">What’s Included</h3>
                <div class="hint" style="margin-bottom:0.5rem;font-size:0.85rem;">Select all features and items included in the rental price at no extra charge:</div>
                <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(210px, 1fr));gap:0.5rem;background:#f8fafc;padding:0.9rem;border-radius:8px;border:1px solid var(--border);">
                    <?php foreach ($commonSpecs as $spec): ?>
                        <label style="display:flex;align-items:center;gap:0.45rem;font-size:0.85rem;cursor:pointer;">
                            <input type="checkbox" name="specs[]" value="<?= e($spec) ?>" class="vf_spec_checkbox">
                            <span><?= e($spec) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div class="field-row" style="margin-top:0.75rem;">
                    <label for="vf_custom_spec">Additional Inclusions <small class="hint">(Optional)</small></label>
                    <input class="input" id="vf_custom_spec" name="custom_spec" placeholder="e.g. Free Airport Delivery, Top Box 45L" maxlength="100">
                    <div class="field-error" id="vf_custom_spec_error" style="display:none;color:#dc2626;font-size:0.82rem;margin-top:0.25rem;font-weight:600;"></div>
                </div>
            </div>

            <!-- Rental Requirements & Policies -->
            <div style="margin-bottom:1.5rem;">
                <h3 style="margin-bottom:0.75rem;border-bottom:1px solid var(--border);padding-bottom:0.4rem;font-size:1.05rem;">Rental Requirements &amp; Policies</h3>
                <div class="field-row">
                    <label for="vf_description">Vehicle Description &amp; Rental Terms <span class="req" style="color:var(--red-600);font-weight:bold;">*</span></label>
                    <textarea class="input" id="vf_description" name="description" rows="4" placeholder="Describe the vehicle condition, driving requirements (e.g. valid driver's license, security deposit if applicable), delivery options, and operating guidelines..." required minlength="20" maxlength="5000"></textarea>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:0.25rem;">
                        <small class="hint">Minimum 20 characters describing the unit, requirements, and pickup details.</small>
                        <small id="vf_desc_counter" class="hint" style="font-size:0.75rem;font-weight:500;">0 / 20 chars min</small>
                    </div>
                    <div class="field-error" id="vf_description_error" style="display:none;color:#dc2626;font-size:0.82rem;margin-top:0.25rem;font-weight:600;"></div>
                </div>
            </div>

            <!-- Modal Foot Actions -->
            <div class="gm-modal-foot" style="display:flex;justify-content:flex-end;gap:0.75rem;padding-top:1rem;border-top:1px solid var(--line, #e2e8f0);flex-wrap:wrap;align-items:center;">
                <button type="button" class="btn btn-ghost" onclick="closeVehicleModal()">Cancel</button>
                <button type="submit" name="action" value="submit" class="btn btn-primary" id="vf_submit_btn">Submit for Approval</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var modal = document.getElementById('vehicleModal');
    var form = document.getElementById('vehicleForm');
    var modalTitle = document.getElementById('vehicleModalTitle');
    var modalSubtitle = document.getElementById('vehicleModalSubtitle');
    var submitBtn = document.getElementById('vf_submit_btn');
    var vehicleIdIn = document.getElementById('vf_vehicle_id');
    var makeIn = document.getElementById('vf_make');
    var modelIn = document.getElementById('vf_model');
    var yearIn = document.getElementById('vf_year');
    var typeIn = document.getElementById('vf_type');
    var passengerCapacityIn = document.getElementById('vf_passenger_capacity');
    var nameIn = document.getElementById('vf_name');
    var priceIn = document.getElementById('vf_price');
    var locationIn = document.getElementById('vf_location');
    var addressIn = document.getElementById('vf_address');
    var imageReq = document.getElementById('vf_image_req');
    var customSpecIn = document.getElementById('vf_custom_spec');
    var descIn = document.getElementById('vf_description');
    var descCounter = document.getElementById('vf_desc_counter');
    var rejectedBanner = document.getElementById('vf_rejected_banner');
    var rejectionText = document.getElementById('vf_rejection_reason_text');
    var approvedBanner = document.getElementById('vf_approved_banner');
    var commonSpecs = <?= json_encode($commonSpecs) ?>;
    var allVehicles = <?= json_encode($vehicles) ?>;
    var validTypes = <?= json_encode($types) ?>;

    function resolveImageUrl(path) {
        if (!path) return '';
        path = String(path).trim();
        if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('data:image/') || path.startsWith('data:')) {
            return path;
        }
        var base = (window.APP_BASE_URL !== undefined ? window.APP_BASE_URL : <?= json_encode(rtrim(url('/'), '/')) ?>) || '';
        if (base && (path === base || path.startsWith(base + '/'))) {
            return path.startsWith('/') ? path : '/' + path;
        }
        var clean = path.replace(/^\/+/, '');
        return (base ? base + '/' : '/') + clean;
    }

    window.handleVehicleFileChange = function(slot, input) {
        if (!input.files || !input.files[0]) return;
        var file = input.files[0];
        var reader = new FileReader();
        reader.onload = function(e) {
            setVehicleSlotPreview(slot, e.target.result);
            var remIn = document.getElementById('vf_removed_' + slot);
            if (remIn) remIn.value = '0';
            clearFieldError('image');
        };
        reader.readAsDataURL(file);
    };

    window.handleVehicleUrlChange = function(slot, val) {
        val = (val || '').trim();
        var remIn = document.getElementById('vf_removed_' + slot);
        if (val) {
            setVehicleSlotPreview(slot, val);
            if (remIn) remIn.value = '0';
            if (slot === 0) {
                var cov = document.getElementById('vf_cover_image');
                if (cov) cov.value = val;
            }
            clearFieldError('image');
        } else {
            var exist = document.getElementById('vf_existing_' + slot);
            if (exist && exist.value) {
                setVehicleSlotPreview(slot, exist.value);
            } else {
                clearVehicleSlotPreview(slot);
            }
        }
    };

    window.removeVehiclePhoto = function(slot) {
        var fileIn = document.getElementById('vf_file_' + slot);
        if (fileIn) fileIn.value = '';
        var urlIn = document.getElementById('vf_url_' + slot);
        if (urlIn) urlIn.value = '';
        var existingIn = document.getElementById('vf_existing_' + slot);
        if (existingIn) existingIn.value = '';
        var remIn = document.getElementById('vf_removed_' + slot);
        if (remIn) remIn.value = '1';
        if (slot === 0) {
            var cov = document.getElementById('vf_cover_image');
            if (cov) cov.value = '';
        }
        clearVehicleSlotPreview(slot);
    };

    function setVehicleSlotPreview(slot, src) {
        var img = document.getElementById('vf_preview_img_' + slot);
        var ph = document.getElementById('vf_placeholder_' + slot);
        var btn = document.getElementById('vf_remove_btn_' + slot);
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

    function clearVehicleSlotPreview(slot) {
        var img = document.getElementById('vf_preview_img_' + slot);
        var ph = document.getElementById('vf_placeholder_' + slot);
        var btn = document.getElementById('vf_remove_btn_' + slot);
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

    var fieldErrorIds = {
        make: 'vf_make_error',
        model: 'vf_model_error',
        year: 'vf_year_error',
        type: 'vf_type_error',
        passenger_capacity: 'vf_passenger_capacity_error',
        name: 'vf_name_error',
        price_per_day: 'vf_price_per_day_error',
        location: 'vf_location_error',
        address: 'vf_address_error',
        image: 'vf_image_error',
        custom_spec: 'vf_custom_spec_error',
        description: 'vf_description_error'
    };

    var fieldInputs = {
        make: makeIn,
        model: modelIn,
        year: yearIn,
        type: typeIn,
        passenger_capacity: passengerCapacityIn,
        name: nameIn,
        price_per_day: priceIn,
        location: locationIn,
        address: addressIn,
        image: document.getElementById('vf_file_0'),
        custom_spec: customSpecIn,
        description: descIn
    };

    function clearFieldError(field) {
        var errEl = document.getElementById(fieldErrorIds[field]);
        if (errEl) {
            errEl.textContent = '';
            errEl.style.display = 'none';
        }
        var inputEl = fieldInputs[field];
        if (inputEl) {
            inputEl.style.borderColor = '';
            inputEl.style.boxShadow = '';
        }
    }

    function setFieldError(field, msg) {
        var errEl = document.getElementById(fieldErrorIds[field]);
        if (errEl) {
            errEl.textContent = msg;
            errEl.style.display = 'block';
        }
        var inputEl = fieldInputs[field];
        if (inputEl) {
            inputEl.style.borderColor = '#dc2626';
            inputEl.style.boxShadow = '0 0 0 3px rgba(220, 38, 38, 0.15)';
        }
    }

    function clearAllErrors() {
        for (var f in fieldErrorIds) {
            clearFieldError(f);
        }
    }

    function displayFieldErrors(errors) {
        for (var f in errors) {
            setFieldError(f, errors[f]);
        }
    }

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

    // Attach real-time input listeners to clear errors on typing
    if (descIn) {
        descIn.addEventListener('input', function () {
            updateDescCounter();
            if (descIn.value.trim().length >= 20) {
                clearFieldError('description');
            }
        });
    }

    ['make', 'model', 'year', 'type', 'passenger_capacity', 'price_per_day', 'location', 'address', 'custom_spec'].forEach(function (f) {
        var inputEl = fieldInputs[f];
        if (inputEl) {
            inputEl.addEventListener('input', function () {
                clearFieldError(f);
            });
            inputEl.addEventListener('change', function () {
                clearFieldError(f);
            });
        }
    });

    // Comprehensive client-side form validation
    function validateClientForm() {
        clearAllErrors();
        var errors = {};
        var firstInvalidInput = null;

        var makeVal = (makeIn.value || '').trim();
        if (makeVal === '') {
            errors.make = 'Vehicle make/brand is required (e.g. Honda, Toyota, Yamaha).';
            if (!firstInvalidInput) firstInvalidInput = makeIn;
        } else if (makeVal.length < 2) {
            errors.make = 'Vehicle make/brand must be at least 2 characters.';
            if (!firstInvalidInput) firstInvalidInput = makeIn;
        } else if (makeVal.length > 80) {
            errors.make = 'Vehicle make/brand must not exceed 80 characters.';
            if (!firstInvalidInput) firstInvalidInput = makeIn;
        }

        var modelVal = (modelIn.value || '').trim();
        if (modelVal === '') {
            errors.model = 'Vehicle model is required (e.g. Click 125i, Vios, Aerox 155).';
            if (!firstInvalidInput) firstInvalidInput = modelIn;
        } else if (modelVal.length > 80) {
            errors.model = 'Vehicle model must not exceed 80 characters.';
            if (!firstInvalidInput) firstInvalidInput = modelIn;
        }

        var yearVal = (yearIn.value || '').trim();
        var yearNum = parseInt(yearVal, 10);
        var maxYear = new Date().getFullYear() + 1;
        if (yearVal === '' || isNaN(yearNum)) {
            errors.year = 'Model year is required.';
            if (!firstInvalidInput) firstInvalidInput = yearIn;
        } else if (yearNum < 1980 || yearNum > maxYear) {
            errors.year = 'Please enter a valid model year between 1980 and ' + maxYear + '.';
            if (!firstInvalidInput) firstInvalidInput = yearIn;
        }

        var typeVal = (typeIn.value || '').trim();
        if (typeVal === '') {
            errors.type = 'Vehicle type is required (e.g. Scooter, Sedan, SUV, Van).';
            if (!firstInvalidInput) firstInvalidInput = typeIn;
        } else if (typeVal.length < 2) {
            errors.type = 'Vehicle type must be at least 2 characters.';
            if (!firstInvalidInput) firstInvalidInput = typeIn;
        } else if (typeVal.length > 50) {
            errors.type = 'Vehicle type must not exceed 50 characters.';
            if (!firstInvalidInput) firstInvalidInput = typeIn;
        }

        var capVal = (passengerCapacityIn.value || '').trim();
        var capNum = parseInt(capVal, 10);
        if (capVal === '' || isNaN(capNum)) {
            errors.passenger_capacity = 'Passenger capacity is required and must be a valid number.';
            if (!firstInvalidInput) firstInvalidInput = passengerCapacityIn;
        } else if (capNum <= 0) {
            errors.passenger_capacity = 'Passenger capacity must be a positive number (at least 1 passenger).';
            if (!firstInvalidInput) firstInvalidInput = passengerCapacityIn;
        }

        var priceVal = (priceIn.value || '').trim();
        var priceNum = parseFloat(priceVal);
        if (priceVal === '' || isNaN(priceNum)) {
            errors.price_per_day = 'Daily rental rate is required and must be a valid number.';
            if (!firstInvalidInput) firstInvalidInput = priceIn;
        } else if (priceNum <= 0) {
            errors.price_per_day = 'Daily rental rate must be greater than 0.';
            if (!firstInvalidInput) firstInvalidInput = priceIn;
        } else if (priceNum < 50) {
            errors.price_per_day = 'Daily rental rate must be at least ₱50.';
            if (!firstInvalidInput) firstInvalidInput = priceIn;
        } else if (priceNum > 500000) {
            errors.price_per_day = 'Daily rental rate cannot exceed ₱500,000.';
            if (!firstInvalidInput) firstInvalidInput = priceIn;
        }

        var locVal = (locationIn.value || '').trim();
        if (locVal === '') {
            errors.location = 'Pickup area or island hub is required (e.g. Cebu City, Mactan Island, Oslob).';
            if (!firstInvalidInput) firstInvalidInput = locationIn;
        } else if (locVal.length < 3) {
            errors.location = 'Pickup location must be at least 3 characters.';
            if (!firstInvalidInput) firstInvalidInput = locationIn;
        } else if (locVal.length > 160) {
            errors.location = 'Pickup location must not exceed 160 characters.';
            if (!firstInvalidInput) firstInvalidInput = locationIn;
        }

        // Image check: Slot 0 (Cover) is required
        var file0 = document.getElementById('vf_file_0');
        var url0 = document.getElementById('vf_url_0');
        var exist0 = document.getElementById('vf_existing_0');
        var hasCover = (file0 && file0.files && file0.files.length > 0)
            || (url0 && url0.value && url0.value.trim() !== '')
            || (exist0 && exist0.value && exist0.value.trim() !== '');

        if (!hasCover) {
            errors.image = 'A primary cover photo of the vehicle is required (JPG, PNG, or WebP up to 5 MB).';
            if (!firstInvalidInput) firstInvalidInput = (file0 || url0);
        }

        // Validate file extensions and sizes for all 4 slots
        for (var i = 0; i < 4; i++) {
            var fIn = document.getElementById('vf_file_' + i);
            if (fIn && fIn.files && fIn.files[0]) {
                var fObj = fIn.files[0];
                var ext = fObj.name.split('.').pop().toLowerCase();
                var allowed = ['jpg', 'jpeg', 'png', 'webp'];
                if (allowed.indexOf(ext) === -1) {
                    errors.image = 'Invalid image format for Photo ' + (i + 1) + ' (.' + ext + '). Only JPG, PNG, and WebP are allowed.';
                    if (!firstInvalidInput) firstInvalidInput = fIn;
                } else if (fObj.size > 5 * 1024 * 1024) {
                    errors.image = 'Photo ' + (i + 1) + ' must be 5 MB or smaller.';
                    if (!firstInvalidInput) firstInvalidInput = fIn;
                }
            }
        }

        var descVal = (descIn.value || '').trim();
        if (descVal === '') {
            errors.description = 'Vehicle description and rental terms are required.';
            if (!firstInvalidInput) firstInvalidInput = descIn;
        } else if (descVal.length < 20) {
            errors.description = 'Description must be at least 20 characters (currently ' + descVal.length + ' characters).';
            if (!firstInvalidInput) firstInvalidInput = descIn;
        } else if (descVal.length > 5000) {
            errors.description = 'Description cannot exceed 5,000 characters.';
            if (!firstInvalidInput) firstInvalidInput = descIn;
        }

        if (Object.keys(errors).length > 0) {
            displayFieldErrors(errors);
            if (firstInvalidInput) {
                firstInvalidInput.focus();
                firstInvalidInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            return false;
        }

        return true;
    }

    // Attach form submit validator
    form.addEventListener('submit', function (e) {
        if (!validateClientForm()) {
            e.preventDefault();
            e.stopPropagation();
            return false;
        }
    });

    window.openCreateVehicleModal = function () {
        clearAllErrors();
        form.reset();
        form.action = '<?= e(url("/dashboard/vehicles")) ?>';
        vehicleIdIn.value = '';
        modalTitle.textContent = 'List a Vehicle for Rent';
        modalSubtitle.textContent = 'Provide complete and accurate details about your rental unit.';
        submitBtn.textContent = 'Submit for Approval';
        submitBtn.onclick = null;
        if (imageReq) imageReq.style.display = '';
        rejectedBanner.style.display = 'none';
        approvedBanner.style.display = 'none';

        for (var i = 0; i < 4; i++) {
            removeVehiclePhoto(i);
            var remIn = document.getElementById('vf_removed_' + i);
            if (remIn) remIn.value = '0';
            var existingIn = document.getElementById('vf_existing_' + i);
            if (existingIn) existingIn.value = '';
        }

        var checkboxes = document.querySelectorAll('.vf_spec_checkbox');
        checkboxes.forEach(function (cb) { cb.checked = false; });
        if (passengerCapacityIn) passengerCapacityIn.value = '';
        customSpecIn.value = '';
        updateDescCounter();

        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        if (makeIn) makeIn.focus();
    };

    window.openEditVehicleModal = function (vehicle) {
        if (!vehicle) return;
        clearAllErrors();
        form.reset();
        form.action = '<?= e(url("/dashboard/vehicles/")) ?>' + vehicle.id;
        vehicleIdIn.value = vehicle.id;
        modalTitle.textContent = 'Edit Vehicle: ' + (vehicle.name || (vehicle.make + ' ' + vehicle.model));
        modalSubtitle.textContent = 'Update your vehicle specifications, daily rate, and rental requirements.';

        makeIn.value = vehicle.make || '';
        modelIn.value = vehicle.model || '';
        yearIn.value = vehicle.year || new Date().getFullYear();
        typeIn.value = vehicle.type || '';
        if (passengerCapacityIn) {
            passengerCapacityIn.value = (vehicle.passenger_capacity !== undefined && vehicle.passenger_capacity !== null) ? vehicle.passenger_capacity : (vehicle.capacity !== undefined && vehicle.capacity !== null ? vehicle.capacity : 0);
        }
        nameIn.value = vehicle.name || '';
        priceIn.value = vehicle.price_per_day || '';
        locationIn.value = vehicle.location || '';
        addressIn.value = vehicle.address || '';
        descIn.value = vehicle.description || '';
        updateDescCounter();

        // Photos: Slot 0 = Primary cover, Slots 1-3 = Gallery photos
        if (imageReq) imageReq.style.display = 'none';
        var photos = [];
        if (vehicle.image) {
            photos.push(vehicle.image);
        } else {
            photos.push('');
        }
        if (Array.isArray(vehicle.gallery)) {
            vehicle.gallery.forEach(function (g) {
                if (!g) return;
                var p = (typeof g === 'object' && g.image_path) ? g.image_path : String(g);
                if (p) photos.push(p);
            });
        }

        for (var i = 0; i < 4; i++) {
            var fileIn = document.getElementById('vf_file_' + i);
            if (fileIn) fileIn.value = '';
            var urlIn = document.getElementById('vf_url_' + i);
            var existingIn = document.getElementById('vf_existing_' + i);
            var remIn = document.getElementById('vf_removed_' + i);
            if (remIn) remIn.value = '0';
            var rawPhoto = photos[i] || '';
            if (existingIn) existingIn.value = rawPhoto;
            if (rawPhoto) {
                if (urlIn) {
                    urlIn.value = (rawPhoto.startsWith('http://') || rawPhoto.startsWith('https://')) ? rawPhoto : '';
                }
                setVehicleSlotPreview(i, rawPhoto);
                if (i === 0) {
                    var covIn = document.getElementById('vf_cover_image');
                    if (covIn) covIn.value = rawPhoto;
                }
            } else {
                if (urlIn) urlIn.value = '';
                clearVehicleSlotPreview(i);
            }
        }

        // Specs
        var specs = [];
        try {
            if (Array.isArray(vehicle.specs)) {
                specs = vehicle.specs;
            } else if (typeof vehicle.specs === 'string' && vehicle.specs.trim()) {
                specs = JSON.parse(vehicle.specs);
            }
        } catch (e) {
            specs = [];
        }
        var customSpecs = [];
        var checkboxes = document.querySelectorAll('.vf_spec_checkbox');
        checkboxes.forEach(function (cb) {
            cb.checked = specs.indexOf(cb.value) !== -1;
        });
        specs.forEach(function (s) {
            if (commonSpecs.indexOf(s) === -1) {
                customSpecs.push(s);
            }
        });
        customSpecIn.value = customSpecs.join(', ');

        // Status banners & buttons
        var status = vehicle.status || 'draft';
        if (status === 'rejected') {
            rejectedBanner.style.display = 'flex';
            rejectionText.textContent = vehicle.rejection_reason || 'Please review and update the listing details.';
            approvedBanner.style.display = 'none';
            submitBtn.textContent = 'Resubmit for Approval';
            submitBtn.onclick = null;
        } else if (status === 'approved' || status === 'unavailable') {
            approvedBanner.style.display = 'flex';
            rejectedBanner.style.display = 'none';
            submitBtn.textContent = 'Save & Submit Changes';
            submitBtn.onclick = null;
        } else {
            rejectedBanner.style.display = 'none';
            approvedBanner.style.display = 'none';
            submitBtn.textContent = 'Save & Submit';
            submitBtn.onclick = null;
        }

        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        if (makeIn) makeIn.focus();
    };

    window.closeVehicleModal = function () {
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        clearAllErrors();
    };

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.hidden) {
            closeVehicleModal();
        }
    });

    // Check URL parameters for ?create=1 or ?edit=ID
    try {
        var params = new URLSearchParams(window.location.search);
        var createParam = params.get('create');
        var editParam = params.get('edit');

        if (createParam === '1' && <?= json_encode(empty($errors)) ?>) {
            openCreateVehicleModal();
            params.delete('create');
            var cleanSearch = params.toString() ? ('?' + params.toString()) : '';
            window.history.replaceState({}, document.title, window.location.pathname + cleanSearch + window.location.hash);
        } else if (editParam && <?= json_encode(empty($errors)) ?>) {
            var targetVehicle = allVehicles.find(function (v) { return String(v.id) === String(editParam); });
            if (targetVehicle) {
                openEditVehicleModal(targetVehicle);
            }
            params.delete('edit');
            var cleanSearch = params.toString() ? ('?' + params.toString()) : '';
            window.history.replaceState({}, document.title, window.location.pathname + cleanSearch + window.location.hash);
        }
    } catch (e) {}

    // Auto reopen on validation error with preserved old input
    <?php if (!empty($errors)): ?>
        try {
            var oldVehicleId = <?= json_encode((string) old('vehicle_id', '')) ?>;
            if (oldVehicleId) {
                var targetVehicle = allVehicles.find(function (v) { return String(v.id) === String(oldVehicleId); }) || {};
                openEditVehicleModal(targetVehicle);
                form.action = '<?= e(url("/dashboard/vehicles/")) ?>' + oldVehicleId;
                vehicleIdIn.value = oldVehicleId;
            } else {
                openCreateVehicleModal();
            }
            makeIn.value = <?= json_encode((string) old('make', '')) ?>;
            modelIn.value = <?= json_encode((string) old('model', '')) ?>;
            yearIn.value = <?= json_encode((string) old('year', '')) ?>;
            typeIn.value = <?= json_encode((string) old('type', '')) ?>;
            nameIn.value = <?= json_encode((string) old('name', '')) ?>;
            priceIn.value = <?= json_encode((string) old('price_per_day', '')) ?>;
            locationIn.value = <?= json_encode((string) old('location', '')) ?>;
            addressIn.value = <?= json_encode((string) old('address', '')) ?>;
            descIn.value = <?= json_encode((string) old('description', '')) ?>;
            customSpecIn.value = <?= json_encode((string) old('custom_spec', '')) ?>;
            updateDescCounter();

            var oldSpecs = <?= json_encode((array) old('specs', [])) ?>;
            var checkboxes = document.querySelectorAll('.vf_spec_checkbox');
            checkboxes.forEach(function (cb) {
                cb.checked = oldSpecs.indexOf(cb.value) !== -1;
            });

            var serverErrors = <?= json_encode((array) $errors) ?>;
            displayFieldErrors(serverErrors);
            modal.hidden = false;
            modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        } catch (e) {}
    <?php endif; ?>
})();
</script>
