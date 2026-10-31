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
$thumb = !empty($hotel['cover_image']) ? $hotel['cover_image'] : 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=400&q=80';
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
                        <img src="<?= e(img_src($thumb, 'hotel-' . $hotelId)) ?>" alt=""
                             style="width:72px;height:72px;border-radius:10px;object-fit:cover;flex-shrink:0;border:1px solid var(--line);background:#f1f5f9;">
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
                                <span>Starting rate: <strong style="color:var(--brand, #059669);"><?= money($hotel['price'] ?? 0) ?></strong>/night</span>
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
                                    $roomImg = !empty($rm['image']) ? $rm['image'] : $thumb;
                                ?>
                                    <tr id="room-row-<?= (int) $rm['id'] ?>" data-room-id="<?= (int) $rm['id'] ?>" data-room-status="<?= e($st) ?>" data-room-name="<?= e($rm['name']) ?>">
                                        <!-- 1. ROOM NAME -->
                                        <td class="col-room-name">
                                            <div style="display:flex;align-items:center;gap:0.75rem;min-width:0;">
                                                <img src="<?= e(img_src($roomImg, 'room-' . $rm['id'])) ?>" alt=""
                                                     style="width:44px;height:44px;border-radius:8px;object-fit:cover;border:1px solid var(--line);flex-shrink:0;background:#f8fafc;">
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
                                                <span><?= e($rm['room_size'] ?? '32 m²') ?></span>
                                                <?php if (!empty($rm['room_size'])): ?><span style="user-select:none;">•</span><?php endif; ?>
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
        <form method="post" action="<?= e(url('/dashboard/hotel/rooms/create')) ?>">
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
                <label>Room Size</label>
                <input type="text" name="room_size" class="input" placeholder="e.g. 38 m² (409 sq ft)" value="38 m²">
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
            <div class="field-row">
                <label>Photo URL (Optional)</label>
                <input type="text" name="image" class="input" placeholder="https://images.unsplash.com/..." value="<?= e($thumb) ?>">
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
        <form id="edit-property-room-form" method="post" action="">
            <?= csrf_field() ?>
            <input type="hidden" name="redirect_to" value="<?= e($currentUrl) ?>">

            <div class="field-row">
                <label>Room Name *</label>
                <input type="text" id="edit-prop-room-name" name="name" class="input" required>
            </div>
            <div class="form-grid-2">
                <div class="field-row">
                    <label>Bed Configuration *</label>
                    <input type="text" id="edit-prop-room-bed" name="bed_type" class="input" required>
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
                </div>
            </div>
            <div class="field-row">
                <label>Room Size</label>
                <input type="text" id="edit-prop-room-size" name="room_size" class="input">
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
                <label>Key Amenities</label>
                <input type="text" id="edit-prop-room-amenities" name="amenities" class="input">
            </div>
            <div class="field-row">
                <label>Photo URL (Optional)</label>
                <input type="text" id="edit-prop-room-image" name="image" class="input">
            </div>
            <div class="field-row">
                <label>Description</label>
                <textarea id="edit-prop-room-desc" name="description" class="input" rows="3"></textarea>
            </div>
            <div style="display:flex;gap:0.5rem;justify-content:flex-end;margin-top:1rem;">
                <button type="button" onclick="closeEditPropertyRoomModal()" class="btn btn-ghost">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Room</button>
            </div>
        </form>
    </div>
</div>

<script>
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
    document.getElementById('edit-property-room-form').action = '<?= url('/dashboard/hotel/rooms/') ?>' + room.id + '/edit';
    document.getElementById('edit-prop-room-name').value = room.name || '';
    document.getElementById('edit-prop-room-bed').value = room.bed_type || '1 King Bed';
    document.getElementById('edit-prop-room-guests').value = room.max_guests || 2;
    document.getElementById('edit-prop-room-price').value = room.price_per_night || 0;
    document.getElementById('edit-prop-room-deposit').value = room.deposit_percentage || 20;
    document.getElementById('edit-prop-room-size').value = room.room_size || '';
    document.getElementById('edit-prop-room-status').value = room.status || 'available';
    document.getElementById('edit-prop-room-amenities').value = room.amenities || '';
    document.getElementById('edit-prop-room-image').value = room.image || '';
    document.getElementById('edit-prop-room-desc').value = room.description || '';
    document.getElementById('modal-edit-property-room').style.display = 'flex';
}

function closeEditPropertyRoomModal() {
    var modal = document.getElementById('modal-edit-property-room');
    if (modal) {
        modal.style.display = 'none';
    }
}

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
