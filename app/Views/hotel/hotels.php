<?php
/**
 * My Hotels - Hotel Partner Property Management Page
 *
 * @var array<int,array<string,mixed>> $hotels
 * @var array<string,int> $stats
 * @var bool $isVerified
 * @var array<string,mixed> $user
 * @var array<string,string> $errors
 */
$hotels = $hotels ?? [];
$stats = $stats ?? ['total' => 0, 'approved' => 0, 'rooms' => 0];
$isVerified = $isVerified ?? false;
$status = (string) ($user['guide_status'] ?? 'none');
$errors = $errors ?? [];
?>
<div class="container">
    <div class="dash-layout">
        <?= \App\Core\View::partial('partials/dash-nav') ?>
        <div class="dash-main">
            <div class="section-head" style="margin-bottom:1.25rem;">
                <div>
                    <h1 class="mb-0">My Hotels</h1>
                    <p class="hint mb-0">Manage your hotel properties, locations, starting rates, and room availability.</p>
                </div>
                <?php if (!$isVerified): ?>
                    <div style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;">
                        <a href="<?= e(url('/documents')) ?>" class="btn btn-ghost"><?= admin_icon('guides', 16) ?> Complete Verification</a>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!$isVerified): ?>
                <div class="status-banner <?= $status === 'rejected' ? 'banner-red' : 'banner-amber' ?>" style="margin-bottom:1.2rem;">
                    <span class="status-icon"><?= $status === 'rejected' ? admin_icon('disputes', 22) : admin_icon('clock', 22) ?></span>
                    <div>
                        <strong><?= $status === 'rejected' ? 'Hotel Partner Verification Needs Update' : 'Hotel Partner Verification Pending' ?></strong>
                        <p class="mb-0">
                            <?= $status === 'rejected'
                                ? 'Your verification documents were not approved. Please review administrator notes and re-submit your credentials.'
                                : 'Your hotel partner documents are currently under administrative review. Once verified, you can publish properties and accept guest bookings.' ?>
                            <a href="<?= e(url('/documents')) ?>">View verification status →</a>
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="status-banner banner-red" style="margin-bottom:1.2rem;">
                    <span class="status-icon">⚠️</span>
                    <div>
                        <strong>Unable to save hotel listing</strong>
                        <p class="mb-0"><?= e(implode(' ', (array) $errors)) ?></p>
                    </div>
                </div>
            <?php endif; ?>



            <div class="panel">
                <div class="panel-head" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.5rem;">
                    <h3 class="mb-0">Hotel Properties (<?= count($hotels) ?>)</h3>
                    <?php if ($isVerified): ?>
                        <button type="button" class="btn btn-primary btn-sm" onclick="openCreateHotelModal()">+ Add Hotel</button>
                    <?php else: ?>
                        <button type="button" class="btn btn-primary btn-sm" data-action="new-hotel" data-open-new-hotel>+ Add Hotel</button>
                    <?php endif; ?>
                </div>
                <div class="panel-body" style="padding:0;overflow-x:auto;">
                    <?php if ($hotels === []): ?>
                        <div class="empty-state" style="padding:3.5rem 1rem;text-align:center;">
                            <div class="big" style="font-size:3rem;margin-bottom:0.75rem;">🏨</div>
                            <h3 style="margin-bottom:0.35rem;">No hotel properties yet</h3>
                            <p class="hint" style="max-width:440px;margin:0 auto 1.25rem;">
                                List your hotel, resort, or homestay so travelers across Cebu can discover your stays, view room options, and make reservations.
                            </p>
                            <?php if ($isVerified): ?>
                                <button type="button" class="btn btn-primary" onclick="openCreateHotelModal()">+ Add your first hotel</button>
                            <?php else: ?>
                                <button type="button" class="btn btn-primary" data-action="new-hotel" data-open-new-hotel>+ Add your first hotel</button>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <table class="table my-hotels-table" style="vertical-align:middle;width:100%;">
                            <thead>
                                <tr>
                                    <th style="min-width:240px;">Property</th>
                                    <th>Starting Rate</th>
                                    <th>Rooms Configured</th>
                                    <th>Status</th>
                                    <th class="col-actions" style="text-align:right;width:140px;min-width:140px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($hotels as $h):
                                    $hStatus = (string) ($h['status'] ?? 'pending');
                                    $roomCount = (int) ($h['room_count'] ?? 0);
                                    $thumb = !empty($h['cover_image']) ? $h['cover_image'] : 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=400&q=80';
                                ?>
                                    <tr>
                                        <td>
                                            <div style="display:flex;align-items:center;gap:0.75rem;">
                                                <img src="<?= e(img_src($thumb, 'hotel-' . $h['id'])) ?>" alt=""
                                                     style="width:52px;height:52px;border-radius:8px;object-fit:cover;flex-shrink:0;background:#f1f5f9;">
                                                <div>
                                                    <a href="<?= e(url('/listing/' . ($h['slug'] ?? $h['id']))) ?>" target="_blank" style="font-weight:700;color:var(--text, #0f172a);text-decoration:none;">
                                                        <?= e($h['title']) ?>
                                                    </a>
                                                    <div class="hint" style="font-size:0.8rem;margin-top:0.15rem;">
                                                        <?= e($h['area']) ?><?php if (!empty($h['address'])): ?> · <?= e($h['address']) ?><?php endif; ?>
                                                    </div>
                                                    <?php if ($hStatus === 'rejected' && !empty($h['rejection_reason'])):
                                                        $reasonText = (string) $h['rejection_reason'];
                                                        $lineCount = substr_count(trim($reasonText), "\n") + 1;
                                                        $charCount = mb_strlen(trim($reasonText));
                                                        $isLong = $lineCount >= 3 || $charCount > 90;
                                                    ?>
                                                        <div class="admin-note-card <?= $isLong ? 'is-long' : '' ?>" style="margin-top:0.4rem;">
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
                                        <td>
                                            <?php if ((float) ($h['price'] ?? 0) > 0): ?>
                                                <strong style="color:var(--brand, #059669);"><?= money($h['price']) ?></strong>
                                                <small class="hint">/ night</small>
                                            <?php else: ?>
                                                <span class="hint" style="color:var(--text-muted, #64748B);font-style:italic;">Price unavailable</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="pill <?= $roomCount > 0 ? 'pill-approved' : 'pill-pending' ?>" style="font-size:0.75rem;padding:3px 8px;">
                                                <?= $roomCount ?> <?= $roomCount === 1 ? 'Room Type' : 'Room Types' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="pill <?= e(\App\Models\Listing::hotelStatusPillClass($hStatus)) ?>">
                                                <?= e(\App\Models\Listing::hotelStatusLabel($hStatus)) ?>
                                            </span>
                                        </td>
                                        <td class="col-actions" style="text-align:right;white-space:nowrap;width:140px;min-width:140px;">
                                            <div class="hotel-action-buttons">
                                                <?php if ($hStatus === 'approved'): ?>
                                                    <a href="<?= e(url('/dashboard/hotels/' . (int) $h['id'] . '/rooms')) ?>" class="btn btn-ghost btn-sm btn-hotel-room" title="View and manage rooms">
                                                        <?= admin_icon('listings', 14) ?> Room
                                                    </a>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-ghost btn-sm btn-hotel-room-disabled" disabled aria-disabled="true" title="Room management is unavailable until this hotel listing is approved by an administrator.">
                                                        <span aria-hidden="true" style="font-size:0.8rem;line-height:1;">🔒</span> Room
                                                    </button>
                                                <?php endif; ?>
                                                <div class="res-action-wrap">
                                                    <button type="button"
                                                            class="btn-res-action-trigger"
                                                            id="hotelActionBtn_<?= (int) $h['id'] ?>"
                                                            aria-haspopup="true"
                                                            aria-expanded="false"
                                                            aria-controls="hotelActionMenu_<?= (int) $h['id'] ?>"
                                                            title="Actions for <?= e($h['title']) ?>"
                                                            aria-label="Actions for <?= e($h['title']) ?>">
                                                        <?= admin_icon('more-vertical', 16) ?>
                                                    </button>
                                                    <div class="res-action-dropdown"
                                                         id="hotelActionMenu_<?= (int) $h['id'] ?>"
                                                         role="menu"
                                                         aria-labelledby="hotelActionBtn_<?= (int) $h['id'] ?>">
                                                        <?php if ($hStatus === 'approved'): ?>
                                                            <a href="<?= e(url('/dashboard/hotels/' . (int) $h['id'] . '/rooms')) ?>" class="res-action-item" role="menuitem">
                                                                <?= admin_icon('listings', 14) ?> Manage Rooms
                                                            </a>
                                                        <?php else: ?>
                                                            <span class="res-action-item" style="color:var(--text-light, #94a3b8);cursor:not-allowed;" title="Room configuration is locked until approved.">
                                                                🔒 Rooms (Locked until Approved)
                                                            </span>
                                                        <?php endif; ?>
                                                        <button type="button" class="res-action-item" onclick='openEditHotelModal(<?= htmlspecialchars(json_encode($h, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, "UTF-8") ?>)' role="menuitem">
                                                            <?= admin_icon('edit', 14) ?> Edit Property
                                                        </button>
                                                        <?php if ($hStatus === 'rejected'): ?>
                                                            <form method="post" action="<?= e(url('/dashboard/hotels/' . (int) $h['id'] . '/resubmit')) ?>" class="res-action-form" onsubmit="return confirm('Resubmit this hotel listing for administrator review?');">
                                                                <?= csrf_field() ?>
                                                                <button type="submit" class="res-action-item action-accept" role="menuitem">
                                                                    <?= admin_icon('check-circle', 14) ?> Resubmit for Review
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                        <a href="<?= e(url('/listing/' . ($h['slug'] ?? $h['id']))) ?>" target="_blank" class="res-action-item" role="menuitem">
                                                            <?= admin_icon('eye', 14) ?> View Live Listing
                                                        </a>
                                                        <div style="border-top:1px solid var(--line);margin:2px 0;"></div>
                                                        <form method="post" action="<?= e(url('/dashboard/hotels/' . (int) $h['id'] . '/delete')) ?>" class="res-action-form" onsubmit="return confirm('Are you sure you want to delete this hotel property? All associated room types will also be deleted.');">
                                                            <?= csrf_field() ?>
                                                            <button type="submit" class="res-action-item action-cancel" role="menuitem">
                                                                <?= admin_icon('trash', 14) ?> Delete Property
                                                            </button>
                                                        </form>
                                                    </div>
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

<!-- ── SHARED MODAL: CREATE & EDIT HOTEL LISTING ── -->
<div class="gm-modal" id="hotelModal" hidden aria-hidden="true">
    <div class="gm-modal-backdrop" onclick="closeHotelModal()"></div>
    <div class="gm-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="hotelModalTitle" style="max-width:620px;max-height:92vh;overflow-y:auto;border-radius:14px;padding:1.5rem;">
        <div class="gm-modal-head" style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1rem;border-bottom:1px solid var(--line, #e2e8f0);padding-bottom:0.75rem;">
            <div style="display:flex;align-items:center;gap:0.75rem;">
                <div style="width:42px;height:42px;border-radius:10px;background:rgba(5,150,105,0.1);color:#059669;display:flex;align-items:center;justify-content:center;font-size:1.35rem;flex-shrink:0;">
                    🏨
                </div>
                <div>
                    <h2 id="hotelModalTitle" style="margin:0;font-size:1.25rem;font-weight:800;color:var(--text, #0f172a);">Add New Hotel</h2>
                    <p id="hotelModalSubtitle" style="margin:0.15rem 0 0;font-size:0.85rem;color:var(--text-muted, #64748b);">Fill in property details for travelers across Cebu.</p>
                </div>
            </div>
            <button type="button" class="gm-modal-close" onclick="closeHotelModal()" aria-label="Close dialog" style="font-size:1.5rem;line-height:1;border:none;background:none;cursor:pointer;color:var(--text-muted, #64748b);">&times;</button>
        </div>

        <form id="hotelForm" method="post" action="<?= e(url('/dashboard/hotels')) ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="hotel_id" id="hf_hotel_id" value="">

            <div id="hotelFormError" class="status-banner banner-red" style="display:none;margin-bottom:1rem;">
                <span class="status-icon">⚠️</span>
                <div id="hotelFormErrorMsg" style="font-size:0.88rem;"></div>
            </div>

            <div class="field-row">
                <label for="hf_title" style="font-weight:600;font-size:0.88rem;display:block;margin-bottom:0.35rem;">
                    Hotel Property Name <span class="req" style="color:#ef4444;">*</span>
                </label>
                <input type="text" id="hf_title" name="title" class="input" placeholder="e.g. Bluewater Maribago Beach Resort" required style="width:100%;">
            </div>

            <div class="form-grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
                <div class="field-row">
                    <label for="hf_area" style="font-weight:600;font-size:0.88rem;display:block;margin-bottom:0.35rem;">
                        Area in Cebu <span class="req" style="color:#ef4444;">*</span>
                    </label>
                    <input type="text" id="hf_area" name="area" class="input" placeholder="e.g. Lapu-Lapu City, Cebu" required style="width:100%;">
                </div>
                <div class="field-row">
                    <label for="hf_address" style="font-weight:600;font-size:0.88rem;display:block;margin-bottom:0.35rem;">
                        Street / Landmark Address
                    </label>
                    <input type="text" id="hf_address" name="address" class="input" placeholder="e.g. Buyong, Maribago" style="width:100%;">
                </div>
            </div>

            <div class="field-row">
                <label for="hf_duration" style="font-weight:600;font-size:0.88rem;display:block;margin-bottom:0.35rem;">
                    Check-in &amp; Check-out Times
                </label>
                <input type="text" id="hf_duration" name="duration" class="input" placeholder="e.g. Check-in 2:00 PM / Check-out 12:00 PM" style="width:100%;">
                <span class="hint" style="display:block;margin-top:0.25rem;font-size:0.8rem;color:var(--text-muted,#64748B);">Starting nightly rate is automatically calculated from your lowest-priced room.</span>
            </div>

            <div class="field-row">
                <label for="hf_summary" style="font-weight:600;font-size:0.88rem;display:block;margin-bottom:0.35rem;">
                    Short Summary (Single sentence selling your hotel)
                </label>
                <input type="text" id="hf_summary" name="summary" class="input" placeholder="e.g. Luxury seaside escape with private beach and authentic Cebuano hospitality." maxlength="255" style="width:100%;">
            </div>

            <div class="field-row">
                <label for="hf_description" style="font-weight:600;font-size:0.88rem;display:block;margin-bottom:0.35rem;">
                    Full Hotel Description <span class="req" style="color:#ef4444;">*</span>
                </label>
                <textarea id="hf_description" name="description" class="input" rows="4" placeholder="Describe your property, views, dining options, proximity to tourist spots, and overall experience..." required style="width:100%;resize:vertical;"></textarea>
            </div>

            <div class="field-row">
                <label for="hf_included" style="font-weight:600;font-size:0.88rem;display:block;margin-bottom:0.35rem;">
                    Property Amenities &amp; Highlights (one per line)
                </label>
                <textarea id="hf_included" name="included" class="input" rows="3" placeholder="Free High-Speed Wi-Fi&#10;Outdoor Swimming Pool&#10;Complimentary Buffet Breakfast&#10;Full-Service Spa &amp; Wellness Center&#10;24/7 Front Desk" style="width:100%;resize:vertical;"></textarea>
            </div>

            <div class="field-row">
                <label for="hf_not_included" style="font-weight:600;font-size:0.88rem;display:block;margin-bottom:0.35rem;">
                    House Rules &amp; Optional Add-ons (one per line)
                </label>
                <textarea id="hf_not_included" name="not_included" class="input" rows="2" placeholder="Airport Transfer (₱800)&#10;Extra Bed (₱1,200/night)&#10;No Smoking Indoors" style="width:100%;resize:vertical;"></textarea>
            </div>

            <div class="field-row" style="background:#f8fafc;padding:0.85rem 1rem;border-radius:8px;border:1px dashed #cbd5e1;margin-bottom:1rem;">
                <label style="font-weight:700;font-size:0.92rem;display:flex;align-items:center;justify-content:space-between;margin-bottom:0.25rem;color:var(--text, #0f172a);">
                    <span>📸 Property Photos (Upload up to 3 photos)</span>
                    <span style="font-size:0.75rem;font-weight:600;background:#e0f2fe;color:#0284c7;padding:2px 8px;border-radius:12px;">Max 3 Photos</span>
                </label>
                <span class="hint" style="display:block;margin-bottom:0.75rem;font-size:0.8rem;color:#64748b;">
                    Upload high-quality JPG, PNG, or WEBP images (up to 5MB each) or paste direct photo URLs. Photo 1 will be used as the primary cover photo.
                </span>

                <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(160px, 1fr));gap:0.75rem;">
                    <!-- Slot 0: Primary Cover -->
                    <div style="border:1px solid #e2e8f0;border-radius:8px;background:#ffffff;padding:0.7rem;display:flex;flex-direction:column;gap:0.4rem;">
                        <div style="display:flex;justify-content:space-between;align-items:center;">
                            <strong style="font-size:0.8rem;color:#0f172a;">Photo 1 (Cover) *</strong>
                            <span style="font-size:0.68rem;background:#dcfce7;color:#166534;font-weight:700;padding:1px 6px;border-radius:4px;">Cover</span>
                        </div>
                        <div id="hf_preview_wrap_0" style="position:relative;height:100px;background:#f1f5f9;border-radius:6px;overflow:hidden;display:flex;align-items:center;justify-content:center;border:1px solid #e2e8f0;">
                            <img id="hf_preview_img_0" src="" alt="Photo 1" style="width:100%;height:100%;object-fit:cover;display:none;">
                            <span id="hf_placeholder_0" style="font-size:0.75rem;color:#94a3b8;text-align:center;padding:4px;">📷 No photo</span>
                            <button type="button" id="hf_remove_btn_0" onclick="removeHotelPhoto(0)" style="display:none;position:absolute;top:4px;right:4px;background:rgba(0,0,0,0.65);color:#fff;border:none;border-radius:50%;width:22px;height:22px;font-size:12px;line-height:1;cursor:pointer;align-items:center;justify-content:center;" title="Remove Photo">&times;</button>
                        </div>
                        <input type="file" name="photo_file_0" id="hf_file_0" class="file-input" accept=".jpg,.jpeg,.png,.webp" style="font-size:0.75rem;width:100%;" onchange="handleHotelFileChange(0, this)">
                        <input type="url" name="photo_url_0" id="hf_url_0" class="input" placeholder="…or photo URL" style="font-size:0.78rem;padding:0.35rem 0.5rem;" oninput="handleHotelUrlChange(0, this.value)">
                        <input type="hidden" name="photo_removed_0" id="hf_removed_0" value="0">
                        <input type="hidden" name="cover_image" id="hf_cover_image" value="">
                    </div>

                    <!-- Slot 1: Gallery 2 -->
                    <div style="border:1px solid #e2e8f0;border-radius:8px;background:#ffffff;padding:0.7rem;display:flex;flex-direction:column;gap:0.4rem;">
                        <div style="display:flex;justify-content:space-between;align-items:center;">
                            <strong style="font-size:0.8rem;color:#0f172a;">Photo 2 (Gallery)</strong>
                            <span style="font-size:0.68rem;background:#f1f5f9;color:#64748b;font-weight:600;padding:1px 6px;border-radius:4px;">Optional</span>
                        </div>
                        <div id="hf_preview_wrap_1" style="position:relative;height:100px;background:#f1f5f9;border-radius:6px;overflow:hidden;display:flex;align-items:center;justify-content:center;border:1px solid #e2e8f0;">
                            <img id="hf_preview_img_1" src="" alt="Photo 2" style="width:100%;height:100%;object-fit:cover;display:none;">
                            <span id="hf_placeholder_1" style="font-size:0.75rem;color:#94a3b8;text-align:center;padding:4px;">📷 No photo</span>
                            <button type="button" id="hf_remove_btn_1" onclick="removeHotelPhoto(1)" style="display:none;position:absolute;top:4px;right:4px;background:rgba(0,0,0,0.65);color:#fff;border:none;border-radius:50%;width:22px;height:22px;font-size:12px;line-height:1;cursor:pointer;align-items:center;justify-content:center;" title="Remove Photo">&times;</button>
                        </div>
                        <input type="file" name="photo_file_1" id="hf_file_1" class="file-input" accept=".jpg,.jpeg,.png,.webp" style="font-size:0.75rem;width:100%;" onchange="handleHotelFileChange(1, this)">
                        <input type="url" name="photo_url_1" id="hf_url_1" class="input" placeholder="…or photo URL" style="font-size:0.78rem;padding:0.35rem 0.5rem;" oninput="handleHotelUrlChange(1, this.value)">
                        <input type="hidden" name="photo_removed_1" id="hf_removed_1" value="0">
                    </div>

                    <!-- Slot 2: Gallery 3 -->
                    <div style="border:1px solid #e2e8f0;border-radius:8px;background:#ffffff;padding:0.7rem;display:flex;flex-direction:column;gap:0.4rem;">
                        <div style="display:flex;justify-content:space-between;align-items:center;">
                            <strong style="font-size:0.8rem;color:#0f172a;">Photo 3 (Gallery)</strong>
                            <span style="font-size:0.68rem;background:#f1f5f9;color:#64748b;font-weight:600;padding:1px 6px;border-radius:4px;">Optional</span>
                        </div>
                        <div id="hf_preview_wrap_2" style="position:relative;height:100px;background:#f1f5f9;border-radius:6px;overflow:hidden;display:flex;align-items:center;justify-content:center;border:1px solid #e2e8f0;">
                            <img id="hf_preview_img_2" src="" alt="Photo 3" style="width:100%;height:100%;object-fit:cover;display:none;">
                            <span id="hf_placeholder_2" style="font-size:0.75rem;color:#94a3b8;text-align:center;padding:4px;">📷 No photo</span>
                            <button type="button" id="hf_remove_btn_2" onclick="removeHotelPhoto(2)" style="display:none;position:absolute;top:4px;right:4px;background:rgba(0,0,0,0.65);color:#fff;border:none;border-radius:50%;width:22px;height:22px;font-size:12px;line-height:1;cursor:pointer;align-items:center;justify-content:center;" title="Remove Photo">&times;</button>
                        </div>
                        <input type="file" name="photo_file_2" id="hf_file_2" class="file-input" accept=".jpg,.jpeg,.png,.webp" style="font-size:0.75rem;width:100%;" onchange="handleHotelFileChange(2, this)">
                        <input type="url" name="photo_url_2" id="hf_url_2" class="input" placeholder="…or photo URL" style="font-size:0.78rem;padding:0.35rem 0.5rem;" oninput="handleHotelUrlChange(2, this.value)">
                        <input type="hidden" name="photo_removed_2" id="hf_removed_2" value="0">
                    </div>
                </div>
            </div>

            <div class="gm-modal-foot" style="display:flex;justify-content:flex-end;gap:0.75rem;padding-top:1rem;border-top:1px solid var(--line, #e2e8f0);">
                <button type="button" class="btn btn-ghost" onclick="closeHotelModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" id="hotelModalSubmitBtn">Save Hotel</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var modal = document.getElementById('hotelModal');
    var form = document.getElementById('hotelForm');
    var modalTitle = document.getElementById('hotelModalTitle');
    var modalSubtitle = document.getElementById('hotelModalSubtitle');
    var submitBtn = document.getElementById('hotelModalSubmitBtn');
    var errorBox = document.getElementById('hotelFormError');
    var errorMsg = document.getElementById('hotelFormErrorMsg');
    var previewWrap = document.getElementById('hf_image_preview_wrap');
    var previewImg = document.getElementById('hf_image_preview');

    function showError(msg) {
        if (errorBox && errorMsg) {
            errorMsg.textContent = msg;
            errorBox.style.display = 'flex';
        }
    }

    function clearError() {
        if (errorBox && errorMsg) {
            errorMsg.textContent = '';
            errorBox.style.display = 'none';
        }
    }

        window.handleHotelFileChange = function(slot, input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                setHotelSlotPreview(slot, e.target.result);
                if (slot === 0) {
                    var cov = document.getElementById('hf_cover_image');
                    if (cov) cov.value = ''; // File upload takes precedence; do not send base64 data URI
                }
            };
            reader.readAsDataURL(input.files[0]);
            var remIn = document.getElementById('hf_removed_' + slot);
            if (remIn) remIn.value = '0';
        }
    };

    window.handleHotelUrlChange = function(slot, val) {
        val = (val || '').trim();
        if (val) {
            setHotelSlotPreview(slot, val);
            var remIn = document.getElementById('hf_removed_' + slot);
            if (remIn) remIn.value = '0';
            if (slot === 0) {
                var cov = document.getElementById('hf_cover_image');
                if (cov) cov.value = val;
            }
        } else {
            var fileIn = document.getElementById('hf_file_' + slot);
            if (!fileIn || !fileIn.files || !fileIn.files[0]) {
                clearHotelSlotPreview(slot);
            }
        }
    };

    window.removeHotelPhoto = function(slot) {
        var fileIn = document.getElementById('hf_file_' + slot);
        if (fileIn) fileIn.value = '';
        var urlIn = document.getElementById('hf_url_' + slot);
        if (urlIn) urlIn.value = '';
        var remIn = document.getElementById('hf_removed_' + slot);
        if (remIn) remIn.value = '1';
        if (slot === 0) {
            var cov = document.getElementById('hf_cover_image');
            if (cov) cov.value = '';
        }
        clearHotelSlotPreview(slot);
    };

    function setHotelSlotPreview(slot, src) {
        var img = document.getElementById('hf_preview_img_' + slot);
        var ph = document.getElementById('hf_placeholder_' + slot);
        var btn = document.getElementById('hf_remove_btn_' + slot);
        if (img && ph && btn) {
            img.src = src;
            img.style.display = 'block';
            ph.style.display = 'none';
            btn.style.display = 'flex';
        }
    }

    function clearHotelSlotPreview(slot) {
        var img = document.getElementById('hf_preview_img_' + slot);
        var ph = document.getElementById('hf_placeholder_' + slot);
        var btn = document.getElementById('hf_remove_btn_' + slot);
        if (img && ph && btn) {
            img.src = '';
            img.style.display = 'none';
            ph.style.display = 'block';
            btn.style.display = 'none';
        }
    }

    window.openCreateHotelModal = function () {
        clearError();
        modalTitle.textContent = 'Add New Hotel';
        modalSubtitle.textContent = 'Fill in your property details for travelers across Cebu.';
        submitBtn.textContent = 'Save Hotel';
        submitBtn.disabled = false;
        form.action = '<?= e(url("/dashboard/hotels")) ?>';
        document.getElementById('hf_hotel_id').value = '';
        document.getElementById('hf_title').value = '';
        document.getElementById('hf_area').value = '';
        document.getElementById('hf_address').value = '';
        document.getElementById('hf_summary').value = '';
        document.getElementById('hf_description').value = '';
        document.getElementById('hf_duration').value = '';
        document.getElementById('hf_included').value = '';
        document.getElementById('hf_not_included').value = '';
        for (var i = 0; i < 3; i++) {
            removeHotelPhoto(i);
            var remIn = document.getElementById('hf_removed_' + i);
            if (remIn) remIn.value = '0';
        }

        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        var first = document.getElementById('hf_title');
        if (first) first.focus();
    };

    window.openEditHotelModal = function (hotel) {
        clearError();
        modalTitle.textContent = 'Edit Hotel Listing';
        modalSubtitle.textContent = 'Update your property details, description, and amenities.';
        submitBtn.textContent = 'Save Changes';
        submitBtn.disabled = false;
        form.action = '<?= e(url("/dashboard/hotels/")) ?>' + hotel.id;
        document.getElementById('hf_hotel_id').value = hotel.id;
        document.getElementById('hf_title').value = hotel.title || '';
        document.getElementById('hf_area').value = hotel.area || '';
        document.getElementById('hf_address').value = hotel.address || '';
        document.getElementById('hf_summary').value = hotel.summary || '';
        document.getElementById('hf_description').value = hotel.description || '';
        document.getElementById('hf_duration').value = hotel.duration || '';
        document.getElementById('hf_included').value = hotel.included || '';
        document.getElementById('hf_not_included').value = hotel.not_included || '';
        var photos = hotel.photos || (hotel.cover_image ? [hotel.cover_image] : []);
        for (var i = 0; i < 3; i++) {
            var fileIn = document.getElementById('hf_file_' + i);
            if (fileIn) fileIn.value = '';
            var urlIn = document.getElementById('hf_url_' + i);
            var remIn = document.getElementById('hf_removed_' + i);
            if (remIn) remIn.value = '0';
            if (photos[i]) {
                if (urlIn) urlIn.value = photos[i];
                setHotelSlotPreview(i, photos[i]);
                if (i === 0) {
                    var covIn = document.getElementById('hf_cover_image');
                    if (covIn) covIn.value = photos[0];
                }
            } else {
                if (urlIn) urlIn.value = '';
                clearHotelSlotPreview(i);
            }
        }

        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        var first = document.getElementById('hf_title');
        if (first) first.focus();
    };

    window.closeHotelModal = function () {
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        clearError();
    };

    // Escape key closes modal
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.hidden) {
            closeHotelModal();
        }
    });

    // Client-side validation & loading feedback
    if (form) {
        form.addEventListener('submit', function (e) {
            var title = (document.getElementById('hf_title').value || '').trim();
            var area = (document.getElementById('hf_area').value || '').trim();
            var desc = (document.getElementById('hf_description').value || '').trim();

            if (!title) {
                e.preventDefault();
                showError('Hotel Property Name is required.');
                document.getElementById('hf_title').focus();
                return;
            }
            if (!area) {
                e.preventDefault();
                showError('Area in Cebu is required.');
                document.getElementById('hf_area').focus();
                return;
            }
            if (!desc) {
                e.preventDefault();
                showError('Hotel Description is required.');
                document.getElementById('hf_description').focus();
                return;
            }

            var durEl = document.getElementById('hf_duration');
            if (durEl && !durEl.value.trim()) {
                durEl.value = 'Check-in 2:00 PM / Check-out 12:00 PM';
            }

            clearError();
            submitBtn.textContent = 'Saving Hotel...';
            setTimeout(function () {
                submitBtn.disabled = true;
            }, 100);
        });
    }

    // Auto-reopen modal if validation errors occurred on submit
    <?php if (!empty($errors)): ?>
        try {
            var hasOldId = <?= json_encode(!empty(old('hotel_id'))) ?>;
            var oldId = <?= json_encode((string) old('hotel_id', '')) ?>;
            if (hasOldId && oldId) {
                modalTitle.textContent = 'Edit Hotel Listing';
                modalSubtitle.textContent = 'Update your property details, description, and amenities.';
                submitBtn.textContent = 'Save Changes';
                form.action = '<?= e(url("/dashboard/hotels/")) ?>' + oldId;
                document.getElementById('hf_hotel_id').value = oldId;
            } else {
                modalTitle.textContent = 'Add New Hotel';
                modalSubtitle.textContent = 'Fill in your property details for travelers across Cebu.';
                submitBtn.textContent = 'Save Hotel';
                form.action = '<?= e(url("/dashboard/hotels")) ?>';
                document.getElementById('hf_hotel_id').value = '';
            }
            document.getElementById('hf_title').value = <?= json_encode((string) old('title', '')) ?>;
            document.getElementById('hf_area').value = <?= json_encode((string) old('area', '')) ?>;
            document.getElementById('hf_address').value = <?= json_encode((string) old('address', '')) ?>;
            document.getElementById('hf_duration').value = <?= json_encode((string) old('duration', '')) ?>;
            document.getElementById('hf_summary').value = <?= json_encode((string) old('summary', '')) ?>;
            document.getElementById('hf_description').value = <?= json_encode((string) old('description', '')) ?>;
            document.getElementById('hf_included').value = <?= json_encode((string) old('included', '')) ?>;
            document.getElementById('hf_not_included').value = <?= json_encode((string) old('not_included', '')) ?>;
            showError(<?= json_encode(implode(' ', (array) $errors)) ?>);
            modal.hidden = false;
            modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        } catch (e) {}
    <?php endif; ?>

    // Auto-open modal if URL has ?create=1
    try {
        var params = new URLSearchParams(window.location.search);
        if (params.get('create') === '1' && <?= json_encode(empty($errors)) ?>) {
            openCreateHotelModal();
            params.delete('create');
            var cleanSearch = params.toString() ? ('?' + params.toString()) : '';
            window.history.replaceState({}, document.title, window.location.pathname + cleanSearch + window.location.hash);
        }
    } catch (err) {}
    // Toggle admin note expand/collapse
    document.querySelectorAll('.admin-note-toggle').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var card = this.closest('.admin-note-card');
            var body = card ? card.querySelector('.admin-note-body') : null;
            var text = this.querySelector('.toggle-text');
            if (!card || !body) return;
            var expanded = this.getAttribute('aria-expanded') === 'true';
            this.setAttribute('aria-expanded', !expanded);
            if (expanded) {
                body.classList.add('is-clamped');
                card.classList.remove('is-expanded');
                if (text) text.textContent = 'Read more';
            } else {
                body.classList.remove('is-clamped');
                card.classList.add('is-expanded');
                if (text) text.textContent = 'Show less';
            }
        });
    });
})();
</script>
