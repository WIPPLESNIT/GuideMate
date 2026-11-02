<?php
/**
 * Property Rooms Management View - Dedicated room types page for a specific hotel property.
 *
 * @var array<string,mixed> $hotel
 * @var array<int,array<string,mixed>> $rooms
 * @var bool $isVerified
 * @var array<string,mixed> $user
 * @var array<string,string> $errors
 */
$hotel = $hotel ?? [];
$rooms = $rooms ?? [];
$isVerified = $isVerified ?? false;
$user = $user ?? [];
$status = (string) ($user['guide_status'] ?? 'none');
$errors = $errors ?? [];
$hotelId = (int) ($hotel['id'] ?? 0);
$hotelStatus = (string) ($hotel['status'] ?? 'pending');
$hotelCover = !empty($hotel['cover_image']) ? $hotel['cover_image'] : null;
$thumb = $hotelCover ?: 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=400&q=80';
$currentUrl = url('/dashboard/hotels/' . $hotelId . '/rooms');
?>
<div class="container">
    <div class="dash-layout">
        <?= \App\Core\View::partial('partials/dash-nav') ?>
        <div class="dash-main">
            <style>
            .room-types-panel {
                overflow: visible !important;
            }
            .room-types-wrap {
                width: 100%;
                overflow-x: auto;
            }
            @media (min-width: 768px) {
                .room-types-wrap {
                    overflow-x: visible !important;
                }
            }
            .room-types-table {
                width: 100% !important;
                table-layout: auto;
                border-collapse: collapse;
            }
            .room-types-table th,
            .room-types-table td {
                padding: 0.8rem 0.65rem !important;
                vertical-align: middle;
            }
            .room-types-table th:first-child,
            .room-types-table td:first-child {
                padding-left: 1.25rem !important;
            }
            .room-types-table th:last-child,
            .room-types-table td:last-child {
                padding-right: 1.25rem !important;
            }
            .room-types-table .col-room-name { width: 30%; }
            .room-types-table .col-specs { width: 24%; }
            .room-types-table .col-price { width: 14%; }
            .room-types-table .col-deposit { width: 14%; }
            .room-types-table .col-status { width: 11%; }
            .room-types-table .col-actions { width: 7%; text-align: right; }

            .room-desc-text {
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden;
                text-overflow: ellipsis;
                line-height: 1.25;
                word-break: break-word;
                max-width: 100%;
            }
            @media (max-width: 640px) {
                .room-types-table {
                    min-width: 580px;
                }
            }
            </style>

            <!-- ── BREADCRUMBS ── -->
            <nav class="breadcrumb-nav" aria-label="Breadcrumb" style="margin-bottom:1.25rem;">
                <ol style="list-style:none;padding:0;margin:0;display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;font-size:0.875rem;">
                    <li>
                        <a href="<?= e(url('/dashboard/hotels')) ?>" style="color:var(--brand, #059669);text-decoration:none;font-weight:600;display:inline-flex;align-items:center;gap:4px;">
                            <?= admin_icon('listings', 14) ?> My Hotels
                        </a>
                    </li>
                    <li style="color:var(--ink-200, #94a3b8);user-select:none;">/</li>
                    <li>
                        <span style="color:var(--ink-400, #475569);font-weight:500;"><?= e($hotel['title'] ?? 'Hotel') ?></span>
                    </li>
                    <li style="color:var(--ink-200, #94a3b8);user-select:none;">/</li>
                    <li>
                        <strong style="color:var(--teal-700, #0f766e);font-weight:700;">Rooms</strong>
                    </li>
                </ol>
            </nav>

            <!-- ── PROPERTY CONTEXT CARD ── -->
            <div class="panel" style="margin-bottom:1.5rem;">
                <div class="panel-body" style="padding:1.25rem 1.5rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1.25rem;">
                    <div style="display:flex;align-items:center;gap:1rem;min-width:280px;flex:1 1 300px;">
                        <?php if (!empty($hotelCover)): ?>
                            <img src="<?= e(img_src($hotelCover, 'hotel-' . $hotelId)) ?>" alt="<?= e($hotel['title'] ?? 'Hotel') ?>"
                                 style="width:72px;height:72px;border-radius:10px;object-fit:cover;flex-shrink:0;border:1px solid var(--line);background:#f1f5f9;">
                        <?php else: ?>
                            <div style="width:72px;height:72px;border-radius:10px;background:#f1f5f9;border:1px solid var(--line);display:flex;flex-direction:column;align-items:center;justify-content:center;flex-shrink:0;color:#94a3b8;text-align:center;padding:4px;" title="No photo uploaded">
                                <span style="font-size:1.5rem;line-height:1;">🏨</span>
                                <span style="font-size:0.65rem;font-weight:600;margin-top:3px;color:#64748b;line-height:1.1;">No photo uploaded</span>
                            </div>
                        <?php endif; ?>
                        <div>
                            <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;">
                                <h2 class="mb-0" style="font-size:1.35rem;line-height:1.2;font-weight:800;color:var(--ink-500, #0f172a);">
                                    <?= e($hotel['title'] ?? 'Property Rooms') ?>
                                </h2>
                                <span class="pill <?= e(\App\Models\Listing::hotelStatusPillClass($hotelStatus)) ?>" style="font-size:0.75rem;">
                                    <?= e(\App\Models\Listing::hotelStatusLabel($hotelStatus)) ?>
                                </span>
                            </div>
                            <div class="hint" style="font-size:0.85rem;margin-top:0.35rem;display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;">
                                <span><?= e($hotel['area'] ?? '') ?><?php if (!empty($hotel['address'])): ?> · <?= e($hotel['address']) ?><?php endif; ?></span>
                                <span>•</span>
                                <span>Starting rate: <?php if ((float) ($hotel['price'] ?? 0) > 0): ?><strong style="color:var(--brand, #059669);"><?= money($hotel['price'] ?? 0) ?></strong>/night<?php else: ?><span style="color:var(--text-muted, #64748B);font-style:italic;">Price unavailable</span><?php endif; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($hotelStatus !== 'approved'): ?>
                <div class="status-banner <?= $hotelStatus === 'rejected' ? 'banner-red' : 'banner-amber' ?>" style="margin-bottom:1.5rem;">
                    <span class="status-icon"><?= $hotelStatus === 'rejected' ? admin_icon('disputes', 24) : admin_icon('clock', 24) ?></span>
                    <div>
                        <strong><?= $hotelStatus === 'rejected' ? 'Hotel Listing Needs Revision' : 'Hotel Listing Pending Administrator Review' ?></strong>
                        <p style="margin:0.25rem 0 0;font-size:0.92rem;">
                            <?= $hotelStatus === 'rejected'
                                ? 'This property was not approved during review' . (!empty($hotel['rejection_reason']) ? ': "' . e($hotel['rejection_reason']) . '".' : '.') . ' Room configuration is only available for approved properties. Please update your hotel details and resubmit for approval.'
                                : 'This hotel property is currently under administrative review. Room types and inventory configuration will be unlocked once an administrator reviews and approves the property listing.' ?>
                        </p>
                        <?php if ($hotelStatus === 'rejected'): ?>
                            <div style="margin-top:0.75rem;display:flex;gap:0.5rem;align-items:center;">
                                <a href="<?= e(url('/dashboard/hotels')) ?>" class="btn btn-sm btn-ghost" style="border:1px solid currentColor;">← Back to My Hotels to Edit &amp; Resubmit</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- ── ROOMS TABLE PANEL ── -->
            <div class="panel room-types-panel">
                <div class="panel-head" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.5rem;">
                    <div>
                        <h3 class="mb-0">Configured Room Types (<?= count($rooms) ?>)</h3>
                        <p class="hint mb-0" style="font-size:0.82rem;">Manage room pricing, bed layouts, deposit requirements, and real-time availability for this property.</p>
                    </div>
                    <?php if ($hotelStatus === 'approved'): ?>
                        <?php if ($isVerified): ?>
                            <button type="button" class="btn btn-primary btn-sm" onclick="openAddPropertyRoomModal()">+ Add Room Type</button>
                        <?php else: ?>
                            <button type="button" class="btn btn-primary btn-sm" data-action="add-room" data-open-room-modal onclick="openAddPropertyRoomModal()">+ Add Room Type</button>
                        <?php endif; ?>
                    <?php else: ?>
                        <button type="button" class="btn btn-ghost btn-sm" style="border:1px dashed var(--line);color:var(--text-light,#64748b);background:#f8fafc;cursor:not-allowed;" title="Property must be approved by an administrator before configuring rooms." disabled>
                            🔒 + Add Room Type (Approval Required)
                        </button>
                    <?php endif; ?>
                </div>
                <div class="panel-body room-types-wrap" style="padding:0;">
                    <?php if ($rooms === []): ?>
                        <div class="empty-state" style="padding:3.5rem 1rem;text-align:center;">
                            <div class="big" style="font-size:3rem;margin-bottom:0.75rem;">🛏️</div>
                            <h3 style="margin-bottom:0.35rem;">No room types configured yet</h3>
                            <p class="hint" style="max-width:440px;margin:0 auto 1.25rem;">
                                Add room types for <?= e($hotel['title'] ?? 'this property') ?> (e.g. Deluxe Suite, Standard Queen, Ocean Villa) so travelers can view specifications and make reservations.
                            </p>
                            <?php if ($hotelStatus === 'approved'): ?>
                                <?php if ($isVerified): ?>
                                    <button type="button" class="btn btn-primary" onclick="openAddPropertyRoomModal()">+ Add Room Type</button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-primary" data-action="add-room" data-open-room-modal onclick="openAddPropertyRoomModal()">+ Add Room Type</button>
                                <?php endif; ?>
                            <?php else: ?>
                                <div style="margin-top:1rem;">
                                    <p class="hint" style="color:var(--coral-600);font-weight:600;margin-bottom:0.75rem;">Room types cannot be created until this property is approved by an administrator.</p>
                                    <a href="<?= e(url('/dashboard/hotels')) ?>" class="btn btn-ghost btn-sm">← Back to My Hotels</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <table class="table hotel-table room-types-table" style="vertical-align:middle;width:100%;margin:0;">
                            <thead>
                                <tr>
                                    <th class="col-room-name" style="width:30%;">Room Name</th>
                                    <th class="col-specs" style="width:24%;">Specifications</th>
                                    <th class="col-price" style="width:14%;">Price</th>
                                    <th class="col-deposit" style="width:14%;">Deposit</th>
                                    <th class="col-status" style="width:11%;">Status</th>
                                    <th class="col-actions" style="width:7%;text-align:right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rooms as $rm):
                                    $st = (string) ($rm['status'] ?? 'available');
                                    $pillClass = match ($st) {
                                        'available' => 'pill-approved',
                                        'blocked' => 'pill-pending',
                                        'fully_booked' => 'pill-cancelled',
                                        default => 'pill-secondary',
                                    };
                                    $statusText = match ($st) {
                                        'available' => 'Available',
                                        'blocked' => 'Blocked',
                                        'fully_booked' => 'Fully Booked',
                                        default => 'Unavailable',
                                    };
                                    $depositPct = max(5, min(100, (int) ($rm['deposit_percentage'] ?? 20)));
                                    $nightlyPrice = (float) ($rm['price_per_night'] ?? 0);
                                    $depositAmt = round(($nightlyPrice * $depositPct) / 100, 2);
                                    $roomImg = !empty($rm['image']) ? $rm['image'] : (!empty($rm['photos'][0]) ? $rm['photos'][0] : null);
                                ?>
                                    <tr id="room-row-<?= (int) $rm['id'] ?>" data-room-id="<?= (int) $rm['id'] ?>" data-room-status="<?= e($st) ?>" data-room-name="<?= e($rm['name']) ?>">
                                        <!-- 1. ROOM NAME -->
                                        <td class="col-room-name">
                                            <div style="display:flex;align-items:center;gap:0.75rem;min-width:0;">
                                                <?php if (!empty($roomImg)): ?>
                                                    <img src="<?= e(img_src($roomImg, 'room-' . $rm['id'])) ?>" alt="<?= e($rm['name']) ?>"
                                                         onerror="this.style.display='none';if(this.nextElementSibling)this.nextElementSibling.style.display='flex';"
                                                         onload="if(this.naturalWidth<=1&&this.naturalHeight<=1){this.style.display='none';if(this.nextElementSibling)this.nextElementSibling.style.display='flex';}"
                                                         style="width:44px;height:44px;border-radius:8px;object-fit:cover;border:1px solid var(--line);flex-shrink:0;background:#f8fafc;">
                                                    <div style="width:44px;height:44px;border-radius:8px;background:#f1f5f9;border:1px solid var(--line);display:none;flex-direction:column;align-items:center;justify-content:center;flex-shrink:0;color:#94a3b8;text-align:center;padding:2px;" title="No photo uploaded">
                                                        <span style="font-size:0.95rem;line-height:1;">🛏️</span>
                                                        <span style="font-size:0.58rem;font-weight:600;margin-top:2px;color:#64748b;line-height:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:40px;">No photo</span>
                                                    </div>
                                                <?php else: ?>
                                                    <div style="width:44px;height:44px;border-radius:8px;background:#f1f5f9;border:1px solid var(--line);display:flex;flex-direction:column;align-items:center;justify-content:center;flex-shrink:0;color:#94a3b8;text-align:center;padding:2px;" title="No photo uploaded">
                                                        <span style="font-size:0.95rem;line-height:1;">🛏️</span>
                                                        <span style="font-size:0.58rem;font-weight:600;margin-top:2px;color:#64748b;line-height:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:40px;">No photo</span>
                                                    </div>
                                                <?php endif; ?>
                                                <div style="min-width:0;flex:1 1 auto;">
                                                    <strong style="color:var(--teal-700, #0f766e);font-size:0.90rem;display:block;line-height:1.25;word-break:break-word;">
                                                        <?= e($rm['name']) ?>
                                                    </strong>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- 2. SPECIFICATIONS -->
                                        <td class="col-specs">
                                            <div style="font-size:0.86rem;color:var(--ink-400, #334155);line-height:1.25;font-weight:600;word-break:break-word;">
                                                <?= e($rm['bed_type'] ?? '1 King Bed') ?>
                                            </div>
                                            <div class="hint" style="font-size:0.76rem;margin-top:2px;line-height:1.25;color:var(--ink-300, #64748b);display:flex;align-items:center;gap:4px;flex-wrap:wrap;">
                                                <span style="white-space:nowrap;">Max <?= (int) ($rm['max_guests'] ?? 2) ?> guests</span>
                                            </div>
                                        </td>

                                        <!-- 3. PRICE -->
                                        <td class="col-price" style="white-space:nowrap;">
                                            <strong style="color:var(--ink-500, #0f172a);font-size:0.90rem;display:block;line-height:1.2;"><?= money($nightlyPrice) ?></strong>
                                            <small class="hint" style="display:block;font-size:0.74rem;margin-top:2px;color:var(--ink-300, #64748b);">/ night</small>
                                        </td>

                                        <!-- 4. DEPOSIT -->
                                        <td class="col-deposit" style="white-space:nowrap;">
                                            <strong style="color:var(--ink-500, #0f172a);font-size:0.88rem;display:block;line-height:1.2;"><?= $depositPct ?>%</strong>
                                            <small class="hint" style="display:block;font-size:0.74rem;margin-top:2px;color:var(--ink-300, #64748b);"><?= money($depositAmt) ?> required</small>
                                        </td>

                                        <!-- 5. STATUS -->
                                        <td class="col-status" style="white-space:nowrap;">
                                            <span class="pill <?= $pillClass ?> room-status-pill" id="roomStatusPill_<?= (int) $rm['id'] ?>" style="padding:3px 8px;font-size:0.75rem;font-weight:600;display:inline-block;">
                                                <?= e($statusText) ?>
                                            </span>
                                        </td>

                                        <!-- 6. ACTIONS (3-DOT VERTICAL ACTION MENU) -->
                                        <td class="col-actions" style="text-align:right;white-space:nowrap;">
                                            <div class="res-action-wrap">
                                                <button type="button"
                                                        class="btn-res-action-trigger"
                                                        id="roomActionBtn_<?= (int) $rm['id'] ?>"
                                                        aria-haspopup="true"
                                                        aria-expanded="false"
                                                        aria-controls="roomActionMenu_<?= (int) $rm['id'] ?>"
                                                        title="Actions for <?= e($rm['name']) ?>"
                                                        aria-label="Actions for <?= e($rm['name']) ?>">
                                                    <?= admin_icon('more-vertical', 16) ?>
                                                </button>
                                                <div class="res-action-dropdown"
                                                     id="roomActionMenu_<?= (int) $rm['id'] ?>"
                                                     role="menu"
                                                     aria-labelledby="roomActionBtn_<?= (int) $rm['id'] ?>">
                                                    
                                                    <!-- Quick Status Changes -->
                                                    <div class="room-status-options" id="roomStatusOptions_<?= (int) $rm['id'] ?>">
                                                        <div style="padding:6px 10px 4px;font-size:0.7rem;font-weight:700;color:var(--ink-200,#94a3b8);text-transform:uppercase;letter-spacing:0.04em;">
                                                            Change Status
                                                        </div>
                                                        <?php foreach ([
                                                            'available' => ['label' => '🟢 Mark Available', 'class' => 'action-accept'],
                                                            'blocked' => ['label' => '🔒 Block (Maintenance)', 'class' => 'action-confirm'],
                                                            'fully_booked' => ['label' => '🔴 Mark Fully Booked', 'class' => 'action-checkin'],
                                                            'unavailable' => ['label' => '⚪ Mark Unavailable', 'class' => 'action-settle'],
                                                        ] as $statusCode => $statusMeta): ?>
                                                            <form method="post" action="<?= e(url('/dashboard/hotel/rooms/' . $rm['id'] . '/status')) ?>" class="res-action-form room-status-form" data-room-id="<?= (int) $rm['id'] ?>" data-target-status="<?= e($statusCode) ?>" style="<?= $st === $statusCode ? 'display:none;' : '' ?>">
                                                                <?= csrf_field() ?>
                                                                <input type="hidden" name="status" value="<?= e($statusCode) ?>">
                                                                <input type="hidden" name="redirect_to" value="<?= e($currentUrl) ?>">
                                                                <button type="submit" class="res-action-item <?= $statusMeta['class'] ?>" role="menuitem">
                                                                    <?= $statusMeta['label'] ?>
                                                                </button>
                                                            </form>
                                                        <?php endforeach; ?>
                                                    </div>

                                                    <div style="border-top:1px solid var(--line);margin:3px 0;"></div>

                                                    <!-- Edit Details -->
                                                    <button type="button" class="res-action-item" onclick='openEditPropertyRoomModal(<?= htmlspecialchars(json_encode($rm, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, "UTF-8") ?>)' role="menuitem">
                                                        <?= admin_icon('edit', 14) ?> Edit Room Details
                                                    </button>

                                                    <div style="border-top:1px solid var(--line);margin:3px 0;"></div>

                                                    <!-- Delete Room -->
                                                    <form method="post" action="<?= e(url('/dashboard/hotel/rooms/' . (int) $rm['id'] . '/delete')) ?>" class="res-action-form" onsubmit="return confirm('Are you sure you want to delete room &quot;<?= e(addslashes($rm['name'])) ?>&quot;? This action cannot be undone.');">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="redirect_to" value="<?= e($currentUrl) ?>">
                                                        <button type="submit" class="res-action-item action-cancel" role="menuitem">
                                                            <?= admin_icon('trash', 14) ?> Delete Room
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

<!-- ── MODAL: ADD ROOM TYPE ── -->
<div id="modal-add-property-room" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;padding:1rem;">
    <div class="card-soft" style="background:#FFF;max-width:540px;width:100%;max-height:90vh;overflow-y:auto;border-radius:12px;padding:1.5rem;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
            <h3 class="mb-0">Add Room Type for <?= e($hotel['title'] ?? 'Hotel') ?></h3>
            <button type="button" onclick="closeAddPropertyRoomModal()" class="btn btn-ghost btn-sm" style="font-size:1.2rem;line-height:1;">✕</button>
        </div>
        <form method="post" action="<?= e(url('/dashboard/hotel/rooms/create')) ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="listing_id" value="<?= $hotelId ?>">
            <input type="hidden" name="redirect_to" value="<?= e($currentUrl) ?>">

            <div class="field-row">
                <label>Room Name *</label>
                <input type="text" name="name" class="input" placeholder="e.g. Deluxe Ocean View Suite" required>
            </div>
            <div class="form-grid-2">
                <div class="field-row">
                    <label>Bed Configuration *</label>
                    <input type="text" name="bed_type" class="input" placeholder="1 King Bed" value="1 King Bed" required>
                </div>
                <div class="field-row">
                    <label>Max Guests *</label>
                    <input type="number" name="max_guests" class="input" min="1" max="10" value="2" required>
                </div>
            </div>
            <div class="form-grid-2">
                <div class="field-row">
                    <label>Nightly Rate (₱) *</label>
                    <input type="number" name="price_per_night" class="input" step="50" min="0" placeholder="2800" value="<?= (int) ($hotel['price'] ?? 2500) ?>" required>
                </div>
                <div class="field-row">
                    <label>Deposit Percentage (%) *</label>
                    <input type="number" name="deposit_percentage" class="input" min="5" max="100" value="20" required>
                    <small class="hint">Remaining % paid at front desk</small>
                </div>
            </div>

            <div class="field-row">
                <label>Availability Status</label>
                <select name="status" class="input">
                    <option value="available" selected>🟢 Available</option>
                    <option value="blocked">🔒 Blocked (Maintenance / Hold)</option>
                    <option value="fully_booked">🔴 Fully Booked</option>
                    <option value="unavailable">⚪ Unavailable</option>
                </select>
            </div>
            <div class="field-row">
                <label>Key Amenities (comma separated)</label>
                <input type="text" name="amenities" class="input" placeholder="Air Conditioning, Free Wi-Fi, Hot Shower, Private Balcony, Mini-Fridge">
            </div>
            <div class="field-row" style="background:#f8fafc;padding:0.85rem 1rem;border-radius:8px;border:1px dashed #cbd5e1;margin-bottom:1rem;">
                <label style="font-weight:700;font-size:0.90rem;display:flex;align-items:center;justify-content:space-between;margin-bottom:0.25rem;color:var(--text, #0f172a);">
                    <span>📸 Room Photo</span>
                    <span style="font-size:0.75rem;font-weight:600;background:#e0f2fe;color:#0284c7;padding:2px 8px;border-radius:12px;">1 Photo</span>
                </label>
                <span class="hint" style="display:block;margin-bottom:0.75rem;font-size:0.8rem;color:#64748b;">
                    Upload a high-quality JPG, PNG, or WEBP image (up to 5MB) or paste a direct photo URL.
                </span>

                <div style="max-width:280px;">
                    <!-- Slot 0: Primary Room Photo -->
                    <div style="border:1px solid #e2e8f0;border-radius:8px;background:#ffffff;padding:0.6rem;display:flex;flex-direction:column;gap:0.35rem;">
                        <div style="display:flex;justify-content:space-between;align-items:center;">
                            <strong style="font-size:0.78rem;color:#0f172a;">Room Photo *</strong>
                            <span style="font-size:0.65rem;background:#dcfce7;color:#166534;font-weight:700;padding:1px 5px;border-radius:4px;">Cover</span>
                        </div>
                        <div id="add_room_preview_wrap_0" style="position:relative;height:120px;background:#f1f5f9;border-radius:6px;overflow:hidden;display:flex;align-items:center;justify-content:center;border:1px solid #e2e8f0;">
                            <img id="add_room_preview_img_0" src="" alt="Room Photo" style="width:100%;height:100%;object-fit:cover;display:none;">
                            <span id="add_room_placeholder_0" style="font-size:0.75rem;color:#94a3b8;text-align:center;padding:4px;">📷 No photo uploaded</span>
                            <button type="button" id="add_room_remove_btn_0" onclick="removeAddRoomPhoto(0)" style="display:none;position:absolute;top:4px;right:4px;background:rgba(0,0,0,0.65);color:#fff;border:none;border-radius:50%;width:22px;height:22px;font-size:12px;line-height:1;cursor:pointer;align-items:center;justify-content:center;" title="Remove Photo">&times;</button>
                        </div>
                        <input type="file" name="room_photo_file_0" id="add_room_file_0" class="file-input" accept=".jpg,.jpeg,.png,.webp" style="font-size:0.72rem;width:100%;" onchange="handleAddRoomFileChange(0, this)">
                        <input type="url" name="room_photo_url_0" id="add_room_url_0" class="input" placeholder="…or photo URL" style="font-size:0.75rem;padding:0.3rem 0.45rem;" oninput="handleAddRoomUrlChange(0, this.value)">
                        <input type="hidden" name="room_photo_removed_0" id="add_room_removed_0" value="0">
                    </div>
                </div>
            </div>
            <div class="field-row">
                <label>Description</label>
                <textarea name="description" class="input" rows="3" placeholder="Brief description of the room, views, and unique features..."></textarea>
            </div>
            <div style="display:flex;gap:0.5rem;justify-content:flex-end;margin-top:1rem;">
                <button type="button" onclick="closeAddPropertyRoomModal()" class="btn btn-ghost">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Room Type</button>
            </div>
        </form>
    </div>
</div>

<!-- ── MODAL: EDIT ROOM TYPE ── -->
<div id="modal-edit-property-room" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;padding:1rem;">
    <div class="card-soft" style="background:#FFF;max-width:540px;width:100%;max-height:90vh;overflow-y:auto;border-radius:12px;padding:1.5rem;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
            <h3 class="mb-0">Edit Room Type</h3>
            <button type="button" onclick="closeEditPropertyRoomModal()" class="btn btn-ghost btn-sm" style="font-size:1.2rem;line-height:1;">✕</button>
        </div>
        <form id="edit-property-room-form" method="post" action="" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="redirect_to" value="<?= e($currentUrl) ?>">

            <div class="field-row">
                <label>Room Name *</label>
                <input type="text" id="edit-prop-room-name" name="name" class="input" placeholder="e.g. Deluxe Ocean View Suite" required>
            </div>
            <div class="form-grid-2">
                <div class="field-row">
                    <label>Bed Configuration *</label>
                    <input type="text" id="edit-prop-room-bed" name="bed_type" class="input" placeholder="1 King Bed" required>
                </div>
                <div class="field-row">
                    <label>Max Guests *</label>
                    <input type="number" id="edit-prop-room-guests" name="max_guests" class="input" min="1" max="10" required>
                </div>
            </div>
            <div class="form-grid-2">
                <div class="field-row">
                    <label>Nightly Rate (₱) *</label>
                    <input type="number" id="edit-prop-room-price" name="price_per_night" class="input" step="50" min="0" required>
                </div>
                <div class="field-row">
                    <label>Deposit Percentage (%) *</label>
                    <input type="number" id="edit-prop-room-deposit" name="deposit_percentage" class="input" min="5" max="100" required>
                    <small class="hint">Remaining % paid at front desk</small>
                </div>
            </div>

            <div class="field-row">
                <label>Availability Status</label>
                <select id="edit-prop-room-status" name="status" class="input">
                    <option value="available">🟢 Available</option>
                    <option value="blocked">🔒 Blocked (Maintenance / Hold)</option>
                    <option value="fully_booked">🔴 Fully Booked</option>
                    <option value="unavailable">⚪ Unavailable</option>
                </select>
            </div>
            <div class="field-row">
                <label>Key Amenities (comma separated)</label>
                <input type="text" id="edit-prop-room-amenities" name="amenities" class="input" placeholder="Air Conditioning, Free Wi-Fi, Hot Shower, Private Balcony, Mini-Fridge">
            </div>
            <div class="field-row" style="background:#f8fafc;padding:0.85rem 1rem;border-radius:8px;border:1px dashed #cbd5e1;margin-bottom:1rem;">
                <label style="font-weight:700;font-size:0.90rem;display:flex;align-items:center;justify-content:space-between;margin-bottom:0.25rem;color:var(--text, #0f172a);">
                    <span>📸 Room Photo</span>
                    <span style="font-size:0.75rem;font-weight:600;background:#e0f2fe;color:#0284c7;padding:2px 8px;border-radius:12px;">1 Photo</span>
                </label>
                <span class="hint" style="display:block;margin-bottom:0.75rem;font-size:0.8rem;color:#64748b;">
                    Upload a high-quality JPG, PNG, or WEBP image (up to 5MB) or paste a direct photo URL.
                </span>

                <div style="max-width:280px;">
                    <!-- Slot 0: Primary Room Photo -->
                    <div style="border:1px solid #e2e8f0;border-radius:8px;background:#ffffff;padding:0.6rem;display:flex;flex-direction:column;gap:0.35rem;">
                        <div style="display:flex;justify-content:space-between;align-items:center;">
                            <strong style="font-size:0.78rem;color:#0f172a;">Room Photo *</strong>
                            <span style="font-size:0.65rem;background:#dcfce7;color:#166534;font-weight:700;padding:1px 5px;border-radius:4px;">Cover</span>
                        </div>
                        <div id="edit_room_preview_wrap_0" style="position:relative;height:120px;background:#f1f5f9;border-radius:6px;overflow:hidden;display:flex;align-items:center;justify-content:center;border:1px solid #e2e8f0;">
                            <img id="edit_room_preview_img_0" src="" alt="Room Photo" style="width:100%;height:100%;object-fit:cover;display:none;">
                            <span id="edit_room_placeholder_0" style="font-size:0.75rem;color:#94a3b8;text-align:center;padding:4px;">📷 No photo uploaded</span>
                            <button type="button" id="edit_room_remove_btn_0" onclick="removeEditRoomPhoto(0)" style="display:none;position:absolute;top:4px;right:4px;background:rgba(0,0,0,0.65);color:#fff;border:none;border-radius:50%;width:22px;height:22px;font-size:12px;line-height:1;cursor:pointer;align-items:center;justify-content:center;" title="Remove Photo">&times;</button>
                        </div>
                        <input type="file" name="room_photo_file_0" id="edit_room_file_0" class="file-input" accept=".jpg,.jpeg,.png,.webp" style="font-size:0.72rem;width:100%;" onchange="handleEditRoomFileChange(0, this)">
                        <input type="text" name="room_photo_url_0" id="edit_room_url_0" class="input" placeholder="…or photo URL" style="font-size:0.75rem;padding:0.3rem 0.45rem;" oninput="handleEditRoomUrlChange(0, this.value)">
                        <input type="hidden" name="existing_room_photo_0" id="edit_room_existing_0" value="">
                        <input type="hidden" name="room_photo_removed_0" id="edit_room_removed_0" value="0">
                    </div>
                </div>
            </div>
            <div class="field-row">
                <label>Description</label>
                <textarea id="edit-prop-room-desc" name="description" class="input" rows="3" placeholder="Brief description of the room, views, and unique features..."></textarea>
            </div>
            <div style="display:flex;gap:0.5rem;justify-content:flex-end;margin-top:1rem;">
                <button type="button" onclick="closeEditPropertyRoomModal()" class="btn btn-ghost">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Room</button>
            </div>
        </form>
    </div>
</div>

<script>

// Room photo handlers - Add Modal
window.handleAddRoomFileChange = function(slot, input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            setAddRoomSlotPreview(slot, e.target.result);
        };
        reader.readAsDataURL(input.files[0]);
        var rem = document.getElementById('add_room_removed_' + slot);
        if (rem) rem.value = '0';
    }
};

window.handleAddRoomUrlChange = function(slot, val) {
    val = (val || '').trim();
    if (val) {
        setAddRoomSlotPreview(slot, val);
        var rem = document.getElementById('add_room_removed_' + slot);
        if (rem) rem.value = '0';
    } else {
        var fileIn = document.getElementById('add_room_file_' + slot);
        if (!fileIn || !fileIn.files || !fileIn.files[0]) {
            clearAddRoomSlotPreview(slot);
        }
    }
};

window.removeAddRoomPhoto = function(slot) {
    var fileIn = document.getElementById('add_room_file_' + slot);
    if (fileIn) fileIn.value = '';
    var urlIn = document.getElementById('add_room_url_' + slot);
    if (urlIn) urlIn.value = '';
    var rem = document.getElementById('add_room_removed_' + slot);
    if (rem) rem.value = '1';
    clearAddRoomSlotPreview(slot);
};

function resolveImageUrl(path) {
    if (!path) return '';
    path = String(path).trim();
    if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('data:image/')) {
        return path;
    }
    var base = '<?= e(rtrim(url("/"), "/")) ?>';
    if (base && (path === base || path.startsWith(base + '/'))) {
        return path.startsWith('/') ? path : '/' + path;
    }
    var clean = path.replace(/^\/+/, '');
    return (base ? base + '/' : '/') + clean;
}

function setAddRoomSlotPreview(slot, src) {
    var img = document.getElementById('add_room_preview_img_' + slot);
    var ph = document.getElementById('add_room_placeholder_' + slot);
    var btn = document.getElementById('add_room_remove_btn_' + slot);
    if (img && ph && btn) {
        var resolved = resolveImageUrl(src);
        img.onload = function() {
            if (this.naturalWidth <= 1 && this.naturalHeight <= 1) {
                this.style.display = 'none';
                ph.textContent = '⚠️ Invalid image';
                ph.style.display = 'block';
                btn.style.display = 'flex';
                return;
            }
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
    }
}

function clearAddRoomSlotPreview(slot) {
    var img = document.getElementById('add_room_preview_img_' + slot);
    var ph = document.getElementById('add_room_placeholder_' + slot);
    var btn = document.getElementById('add_room_remove_btn_' + slot);
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

// Room photo handlers - Edit Modal
window.handleEditRoomFileChange = function(slot, input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            setEditRoomSlotPreview(slot, e.target.result);
        };
        reader.readAsDataURL(input.files[0]);
        var rem = document.getElementById('edit_room_removed_' + slot);
        if (rem) rem.value = '0';
        var urlIn = document.getElementById('edit_room_url_' + slot);
        if (urlIn) urlIn.value = '';
    }
};

window.handleEditRoomUrlChange = function(slot, val) {
    val = (val || '').trim();
    var existingIn = document.getElementById('edit_room_existing_' + slot);
    if (val) {
        setEditRoomSlotPreview(slot, val);
        var rem = document.getElementById('edit_room_removed_' + slot);
        if (rem) rem.value = '0';
    } else {
        var fileIn = document.getElementById('edit_room_file_' + slot);
        if (fileIn && fileIn.files && fileIn.files[0]) {
            // Keep file preview
        } else if (existingIn && existingIn.value) {
            setEditRoomSlotPreview(slot, existingIn.value);
            var rem2 = document.getElementById('edit_room_removed_' + slot);
            if (rem2) rem2.value = '0';
        } else {
            clearEditRoomSlotPreview(slot);
        }
    }
};

window.removeEditRoomPhoto = function(slot) {
    var fileIn = document.getElementById('edit_room_file_' + slot);
    if (fileIn) fileIn.value = '';
    var urlIn = document.getElementById('edit_room_url_' + slot);
    if (urlIn) urlIn.value = '';
    var existingIn = document.getElementById('edit_room_existing_' + slot);
    if (existingIn) existingIn.value = '';
    var rem = document.getElementById('edit_room_removed_' + slot);
    if (rem) rem.value = '1';
    clearEditRoomSlotPreview(slot);
};

function setEditRoomSlotPreview(slot, src) {
    var img = document.getElementById('edit_room_preview_img_' + slot);
    var ph = document.getElementById('edit_room_placeholder_' + slot);
    var btn = document.getElementById('edit_room_remove_btn_' + slot);
    if (img && ph && btn) {
        var resolved = resolveImageUrl(src);
        img.onload = function() {
            if (this.naturalWidth <= 1 && this.naturalHeight <= 1) {
                this.style.display = 'none';
                ph.textContent = '⚠️ Invalid image';
                ph.style.display = 'block';
                btn.style.display = 'flex';
                return;
            }
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
    }
}

function clearEditRoomSlotPreview(slot) {
    var img = document.getElementById('edit_room_preview_img_' + slot);
    var ph = document.getElementById('edit_room_placeholder_' + slot);
    var btn = document.getElementById('edit_room_remove_btn_' + slot);
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

function openAddPropertyRoomModal() {
    var gatingModal = document.getElementById('verificationGatingModal');
    var metaVerified = document.querySelector('meta[name="auth-user-verified"]');
    var isVerified = (metaVerified && metaVerified.content === '1') || (gatingModal && gatingModal.dataset.userVerified === '1') || <?= $isVerified ? 'true' : 'false' ?>;
    if (!isVerified) {
        if (typeof openVerificationGatingModal === 'function') {
            openVerificationGatingModal();
        } else if (gatingModal) {
            gatingModal.classList.add('is-open');
        } else {
            window.location.href = '<?= url('/documents') ?>';
        }
        return;
    }
    var modal = document.getElementById('modal-add-property-room');
    if (modal) {
        if (typeof removeAddRoomPhoto === 'function') {
            removeAddRoomPhoto(0);
            var rem = document.getElementById('add_room_removed_0');
            if (rem) rem.value = '0';
        }
        modal.style.display = 'flex';
        var firstInput = modal.querySelector('input:not([type="hidden"]), select, textarea');
        if (firstInput) {
            try { firstInput.focus(); } catch (e) {}
        }
    }
}

function closeAddPropertyRoomModal() {
    var modal = document.getElementById('modal-add-property-room');
    if (modal) {
        modal.style.display = 'none';
    }
}

function openEditPropertyRoomModal(room) {
    if (!room) return;

    // Close any open action menus
    if (typeof closeResMenu === 'function') {
        closeResMenu();
    } else {
        document.querySelectorAll('.res-action-dropdown.open').forEach(function(m) {
            m.classList.remove('open');
        });
    }

    var form = document.getElementById('edit-property-room-form');
    if (form) {
        form.action = '<?= url('/dashboard/hotel/rooms/') ?>' + (room.id || '') + '/edit';
    }

    var nameIn = document.getElementById('edit-prop-room-name');
    if (nameIn) nameIn.value = room.name || '';

    var bedIn = document.getElementById('edit-prop-room-bed');
    if (bedIn) bedIn.value = room.bed_type || '1 King Bed';

    var guestsIn = document.getElementById('edit-prop-room-guests');
    if (guestsIn) guestsIn.value = room.max_guests || 2;

    var priceIn = document.getElementById('edit-prop-room-price');
    if (priceIn) priceIn.value = room.price_per_night || 0;

    var depositIn = document.getElementById('edit-prop-room-deposit');
    if (depositIn) depositIn.value = room.deposit_percentage || 20;

    var statusIn = document.getElementById('edit-prop-room-status');
    if (statusIn) statusIn.value = room.status || 'available';

    var amenIn = document.getElementById('edit-prop-room-amenities');
    if (amenIn) amenIn.value = room.amenities || '';

    var photo = (room.photos && room.photos.length > 0) ? room.photos[0] : (room.image || '');
    var photoUrl = (room.photo_urls && room.photo_urls.length > 0) ? room.photo_urls[0] : (room.image_url || resolveImageUrl(photo));
    var fileIn = document.getElementById('edit_room_file_0');
    if (fileIn) fileIn.value = '';
    var urlIn = document.getElementById('edit_room_url_0');
    var existingIn = document.getElementById('edit_room_existing_0');
    var remIn = document.getElementById('edit_room_removed_0');
    if (remIn) remIn.value = '0';
    if (existingIn) existingIn.value = photo;
    if (photoUrl || photo) {
        if (urlIn) {
            urlIn.value = (photo.startsWith('http://') || photo.startsWith('https://')) ? photo : '';
        }
        setEditRoomSlotPreview(0, photoUrl || photo);
    } else {
        if (urlIn) urlIn.value = '';
        clearEditRoomSlotPreview(0);
    }

    var descIn = document.getElementById('edit-prop-room-desc');
    if (descIn) descIn.value = room.description || '';

    var modal = document.getElementById('modal-edit-property-room');
    if (modal) {
        modal.style.display = 'flex';
        var firstInput = modal.querySelector('input:not([type="hidden"]), select, textarea');
        if (firstInput) {
            try { firstInput.focus(); } catch (e) {}
        }
    }
}

function closeEditPropertyRoomModal() {
    var modal = document.getElementById('modal-edit-property-room');
    if (modal) {
        modal.style.display = 'none';
    }
}

// Close modals when Escape key is pressed
window.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeAddPropertyRoomModal();
        closeEditPropertyRoomModal();
    }
});

// Close modals when backdrop is clicked
document.addEventListener('DOMContentLoaded', function() {
    var addModal = document.getElementById('modal-add-property-room');
    if (addModal) {
        addModal.addEventListener('click', function(e) {
            if (e.target === addModal) {
                closeAddPropertyRoomModal();
            }
        });
    }
    var editModal = document.getElementById('modal-edit-property-room');
    if (editModal) {
        editModal.addEventListener('click', function(e) {
            if (e.target === editModal) {
                closeEditPropertyRoomModal();
            }
        });
    }
});

function showToast(message, type) {
    type = type || 'success';
    var existing = document.getElementById('gm-dynamic-toast');
    if (existing) {
        existing.remove();
    }
    document.querySelectorAll('.flash').forEach(function(el) {
        if (el.id !== 'gm-dynamic-toast') {
            el.remove();
        }
    });
    var toast = document.createElement('div');
    toast.id = 'gm-dynamic-toast';
    toast.className = 'flash ' + (type === 'error' ? 'flash-error' : 'flash-success');
    toast.style.position = 'fixed';
    toast.style.top = '1.25rem';
    toast.style.right = '1.25rem';
    toast.style.zIndex = '999999';
    toast.style.borderRadius = '8px';
    toast.style.boxShadow = '0 10px 25px -5px rgba(0, 0, 0, 0.2), 0 8px 10px -6px rgba(0, 0, 0, 0.1)';
    toast.style.padding = '0.75rem 1.25rem';
    toast.style.maxWidth = '420px';
    toast.style.display = 'flex';
    toast.style.alignItems = 'center';
    toast.style.gap = '0.75rem';
    toast.setAttribute('role', 'alert');

    var text = document.createElement('span');
    text.style.flex = '1 1 auto';
    text.textContent = message;

    var closeBtn = document.createElement('button');
    closeBtn.className = 'flash-close';
    closeBtn.innerHTML = '&times;';
    closeBtn.setAttribute('aria-label', 'Dismiss');
    closeBtn.onclick = function() { toast.remove(); };

    toast.appendChild(text);
    toast.appendChild(closeBtn);
    document.body.appendChild(toast);

    setTimeout(function() {
        if (toast.parentElement) {
            toast.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-8px)';
            setTimeout(function() { toast.remove(); }, 400);
        }
    }, 4000);
}

document.addEventListener('submit', function(e) {
    var form = e.target.closest ? e.target.closest('.room-status-form') : null;
    if (!form) return;

    e.preventDefault();

    var roomId = form.getAttribute('data-room-id');
    var targetStatus = form.getAttribute('data-target-status');
    var row = document.getElementById('room-row-' + roomId);
    var pill = document.getElementById('roomStatusPill_' + roomId);
    var submitBtn = form.querySelector('button[type="submit"]');

    if (typeof closeResMenu === 'function') {
        closeResMenu();
    } else {
        var menu = form.closest ? form.closest('.res-action-dropdown') : null;
        if (menu) menu.classList.remove('open');
    }

    if (submitBtn) {
        submitBtn.disabled = true;
    }
    if (pill) {
        pill.style.opacity = '0.5';
    }

    var formData = new FormData(form);

    fetch(form.action, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        },
        body: formData
    })
    .then(function(res) {
        if (!res.ok) {
            return res.json().then(function(errData) {
                throw new Error(errData.error || errData.message || 'Failed to update room status.');
            }).catch(function() {
                throw new Error('Server returned error ' + res.status);
            });
        }
        return res.json();
    })
    .then(function(data) {
        if (submitBtn) submitBtn.disabled = false;
        if (pill) pill.style.opacity = '1';

        if (data.success) {
            var updatedStatus = data.status || targetStatus;

            var statusMeta = {
                'available': { text: 'Available', pillClass: 'pill-approved' },
                'blocked': { text: 'Blocked', pillClass: 'pill-pending' },
                'fully_booked': { text: 'Fully Booked', pillClass: 'pill-cancelled' },
                'unavailable': { text: 'Unavailable', pillClass: 'pill-secondary' }
            };

            var meta = statusMeta[updatedStatus] || { text: updatedStatus, pillClass: 'pill-secondary' };

            if (pill) {
                pill.className = 'pill ' + meta.pillClass + ' room-status-pill';
                pill.textContent = meta.text;
            }

            if (row) {
                row.setAttribute('data-room-status', updatedStatus);
            }

            var optionsContainer = document.getElementById('roomStatusOptions_' + roomId);
            if (optionsContainer) {
                optionsContainer.querySelectorAll('.room-status-form').forEach(function(f) {
                    var st = f.getAttribute('data-target-status');
                    f.style.display = (st === updatedStatus) ? 'none' : 'block';
                });
            }

            showToast(data.message || ('Room status updated to ' + meta.text + '.'), 'success');
        } else {
            showToast(data.error || 'Could not update status.', 'error');
        }
    })
    .catch(function(err) {
        if (submitBtn) submitBtn.disabled = false;
        if (pill) pill.style.opacity = '1';
        showToast(err.message || 'An error occurred while updating room status.', 'error');
    });
});
</script>
