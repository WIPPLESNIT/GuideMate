<?php
/**
 * @var array<string,mixed> $user
 * @var array<int,array<string,mixed>> $listings
 * @var array<int,array<string,mixed>> $rooms
 * @var array<int,array<string,mixed>> $reservations
 * @var array<int,array<string,mixed>> $inquiries
 * @var int $unread
 * @var array<string,mixed> $reviewStats
 */
$approved = array_filter($listings, fn($l) => $l['status'] === 'approved');
$status = (string) ($user['guide_status'] ?? 'none');
$isVerified = $status === 'approved';
$unread = (int) ($unread ?? 0);
$inquiries = $inquiries ?? [];
$reviewStats = $reviewStats ?? ['avg_rating' => 0, 'review_count' => 0];
$rooms = $rooms ?? [];
$reservations = $reservations ?? [];

$totalRoomsCount = count($rooms);
$availableRoomsCount = count(array_filter($rooms, fn($r) => ($r['status'] ?? '') === 'available'));
$blockedRoomsCount = count(array_filter($rooms, fn($r) => in_array($r['status'] ?? '', ['blocked', 'unavailable', 'fully_booked'], true)));

$activeReservations = array_filter($reservations, fn($b) => !in_array($b['status'] ?? '', ['cancelled', 'refunded'], true) && ($b['hotel_status'] ?? '') !== 'cancelled');
$pendingCheckIns = array_filter($reservations, fn($b) => in_array($b['hotel_status'] ?? '', ['deposit_paid', 'confirmed'], true));
$balanceDueReservations = array_filter($reservations, fn($b) => ($b['payment_stage'] ?? '') === 'balance_due' || ($b['hotel_status'] ?? '') === 'checked_in');

$totalOutstandingBalance = 0.0;
foreach ($reservations as $res) {
    if (in_array($res['hotel_status'] ?? '', ['deposit_paid', 'confirmed', 'checked_in'], true)) {
        $totalOutstandingBalance += (float) ($res['balance_amount'] ?? 0);
    }
}
?>
<div class="container">
    <div class="dash-layout">
        <?= \App\Core\View::partial('partials/dash-nav') ?>
        <div class="dash-main">
            <div class="section-head section-head-vertical" style="margin-bottom:1.5rem;display:flex;flex-direction:column;align-items:flex-start;gap:0.9rem;">
                <div>
                    <h1 class="mb-0"><?= !empty($isFirstLogin) ? 'Welcome to GuideMate' : 'Welcome back' ?>, <?= e(explode(' ', (string) $user['name'])[0]) ?></h1>
                    <p class="hint mb-0">Hotel Partner Portal — manage room inventory, live availability, guest deposits, and front desk check-ins.</p>
                </div>
                <div class="dash-header-actions" style="display:flex;gap:0.75rem;align-items:center;flex-wrap:wrap;">
                    <a href="<?= e(url('/dashboard/hotels?create=1')) ?>" class="btn btn-primary" data-action="new-hotel" data-open-new-hotel data-open-new-listing>+ New Hotel</a>
                    <?php if (!$isVerified): ?>
                        <a href="<?= e(url('/dashboard/verification')) ?>" class="btn btn-ghost"><?= admin_icon('guides', 16) ?>Verification</a>
                    <?php else: ?>
                        <a href="<?= e(url('/documents')) ?>" class="btn btn-ghost"><?= admin_icon('guides', 16) ?>Documents</a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!$isVerified): ?>
                <div class="status-banner <?= $status === 'rejected' ? 'banner-red' : 'banner-amber' ?>" style="margin-bottom:1.2rem;">
                    <span class="status-icon"><?= $status === 'rejected' ? admin_icon('disputes', 22) : admin_icon('clock', 22) ?></span>
                    <div>
                        <strong><?= $status === 'rejected' ? 'Application not approved' : 'Verification pending' ?></strong>
                        <p class="mb-0">
                            <?= $status === 'rejected'
                                ? 'Your hotel partner application was not approved. '
                                : 'An admin is reviewing your documents. ' ?>
                            You can publish hotel listings once verified.
                            <a href="<?= e(url('/dashboard/verification')) ?>">View status →</a>
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <div class="stat-grid" style="grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));">
                <a href="<?= e(url('/dashboard/hotels')) ?>" class="stat stat-ico-card stat-clickable">
                    <span class="stat-ico"><?= admin_icon('home', 18) ?></span>
                    <div class="label">Total Properties</div>
                    <div class="value"><?= count($listings) ?></div>
                    <div class="stat-action" style="color:var(--brand, #059669);">View properties →</div>
                </a>
                <div class="stat stat-ico-card">
                    <span class="stat-ico"><?= admin_icon('check-circle', 18) ?></span>
                    <div class="label">Approved &amp; Live</div>
                    <div class="value"><?= count($approved) ?></div>
                    <div class="stat-action" style="color:#059669;"><?= count($approved) > 0 ? count($approved) . ' active on platform' : 'Pending review' ?></div>
                </div>
                <div class="stat stat-ico-card">
                    <span class="stat-ico">🛏️</span>
                    <div class="label">Total Rooms</div>
                    <div class="value"><?= $totalRoomsCount ?></div>
                    <div class="stat-action" style="color:#059669;"><?= $availableRoomsCount ?> bookable live</div>
                </div>
                <div class="stat stat-ico-card">
                    <span class="stat-ico"><?= admin_icon('bookings', 18) ?></span>
                    <div class="label">Active Stays</div>
                    <div class="value"><?= count($activeReservations) ?></div>
                    <div class="stat-action" style="color:#2563EB;"><?= count($pendingCheckIns) ?> arriving soon</div>
                </div>
                <div class="stat stat-ico-card">
                    <span class="stat-ico">💳</span>
                    <div class="label">Front Desk Balance</div>
                    <div class="value" style="font-size:1.4rem;"><?= money($totalOutstandingBalance) ?></div>
                    <div class="stat-action" style="color:#D97706;"><?= count($balanceDueReservations) ?> balance due</div>
                </div>
                <a href="<?= e(url('/dashboard/reviews')) ?>" class="stat stat-ico-card stat-clickable">
                    <span class="stat-ico"><?= admin_icon('feedback', 18) ?></span>
                    <div class="label">Total Reviews</div>
                    <div class="value"><?= (int) ($reviewStats['review_count'] ?? 0) ?></div>
                    <div class="stat-action" style="color:var(--brand, #059669);"><?= number_format((float) ($reviewStats['avg_rating'] ?? 5.0), 1) ?> ★ avg rating</div>
                </a>
                <a href="<?= e(url('/messages')) ?>" class="stat stat-ico-card stat-clickable">
                    <span class="stat-ico"><?= admin_icon('messages', 18) ?></span>
                    <div class="label">Inquiries</div>
                    <div class="value"><?= $unread ?></div>
                    <div class="stat-action">Open inbox →</div>
                </a>
            </div>

            <!-- ── SECTION 1: ROOM INVENTORY & AVAILABILITY CONTROLS ── -->
            <div class="panel room-inventory-panel mt-4" id="inventory">
                <div class="panel-head" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.5rem;">
                    <div>
                        <h3 class="mb-0">Room Inventory &amp; Live Availability</h3>
                        <p class="hint mb-0" style="font-size:0.82rem;">Manage room pricing, bed layouts, deposit requirements, and real-time availability for this property.</p>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" id="btnAddRoomType" data-action="add-room" data-open-room-modal onclick="openAddRoomModal()">+ Add Room Type</button>
                </div>
                <div class="panel-body room-inventory-wrap" style="padding:0;">
                    <?php if ($rooms === []): ?>
                        <div class="empty-state" style="padding:2.5rem 1rem;">
                            <p class="mb-2">No room types configured yet. Add your first room type to start receiving guest bookings.</p>
                            <button type="button" class="btn btn-primary btn-sm" data-action="add-room" data-open-room-modal onclick="openAddRoomModal()">+ Add Room Type</button>
                        </div>
                    <?php else: ?>
                        <table class="table hotel-table room-inventory-table" style="vertical-align:middle;width:100%;margin:0;">
                            <thead>
                                <tr>
                                    <th class="col-room-name" style="width:28%;text-transform:uppercase;font-size:0.75rem;font-weight:700;letter-spacing:0.04em;color:var(--ink-300,#64748b);">Room Name</th>
                                    <th class="col-specs" style="width:24%;text-transform:uppercase;font-size:0.75rem;font-weight:700;letter-spacing:0.04em;color:var(--ink-300,#64748b);">Specifications</th>
                                    <th class="col-price" style="width:14%;text-transform:uppercase;font-size:0.75rem;font-weight:700;letter-spacing:0.04em;color:var(--ink-300,#64748b);">Price</th>
                                    <th class="col-deposit" style="width:14%;text-transform:uppercase;font-size:0.75rem;font-weight:700;letter-spacing:0.04em;color:var(--ink-300,#64748b);">Deposit</th>
                                    <th class="col-status" style="width:12%;text-transform:uppercase;font-size:0.75rem;font-weight:700;letter-spacing:0.04em;color:var(--ink-300,#64748b);">Status</th>
                                    <th class="col-actions" style="width:8%;text-align:right;text-transform:uppercase;font-size:0.75rem;font-weight:700;letter-spacing:0.04em;color:var(--ink-300,#64748b);">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rooms as $rm): ?>
                                    <?php
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
                                    $balanceAmt = max(0.0, $nightlyPrice - $depositAmt);
                                    ?>
                                    <tr id="room-row-<?= (int) $rm['id'] ?>" data-room-id="<?= (int) $rm['id'] ?>" data-room-status="<?= e($st) ?>" data-room-name="<?= e($rm['name']) ?>">
                                        <!-- 1. ROOM NAME -->
                                        <td class="col-room-name">
                                            <div style="display:flex;align-items:center;gap:0.75rem;min-width:0;">
                                                <img src="<?= e(img_src($rm['image'] ?? null, 'room-' . $rm['id'])) ?>" alt=""
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
                                            <div style="font-size:0.88rem;color:var(--ink-400, #334155);line-height:1.25;font-weight:600;word-break:break-word;">
                                                <?= e($rm['bed_type'] ?? '1 King Bed') ?>
                                            </div>
                                            <div class="hint" style="font-size:0.78rem;margin-top:2px;line-height:1.35;color:var(--ink-300, #64748b);">
                                                <?php if (!empty($rm['room_size'])): ?>
                                                    <div><?= e($rm['room_size']) ?> •</div>
                                                <?php endif; ?>
                                                <div style="white-space:nowrap;">Max <?= (int) ($rm['max_guests'] ?? 2) ?> <?= (int) ($rm['max_guests'] ?? 2) > 1 ? 'guests' : 'guest' ?></div>
                                            </div>
                                        </td>

                                        <!-- 3. PRICE -->
                                        <td class="col-price" style="white-space:nowrap;">
                                            <strong style="color:var(--ink-500, #0f172a);font-size:0.92rem;display:block;line-height:1.2;"><?= money($nightlyPrice) ?></strong>
                                            <small class="hint" style="display:block;font-size:0.75rem;margin-top:2px;color:var(--ink-300, #64748b);">/ night</small>
                                        </td>

                                        <!-- 4. DEPOSIT -->
                                        <td class="col-deposit" style="white-space:nowrap;">
                                            <strong style="color:var(--ink-500, #0f172a);font-size:0.90rem;display:block;line-height:1.2;"><?= $depositPct ?>%</strong>
                                            <small class="hint" style="display:block;font-size:0.75rem;margin-top:2px;color:var(--ink-300, #64748b);white-space:nowrap;"><?= money($depositAmt) ?> required</small>
                                        </td>

                                        <!-- 5. STATUS -->
                                        <td class="col-status" style="white-space:nowrap;">
                                            <span class="pill <?= $pillClass ?> room-status-pill" id="overviewRoomStatusPill_<?= (int) $rm['id'] ?>" style="padding:4px 12px;font-size:0.76rem;font-weight:600;display:inline-block;">
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
                                                            'fully_booked' => ['label' => '🔴 Mark Fully Booked', 'class' => 'action-cancel'],
                                                            'unavailable' => ['label' => '⚪ Mark Unavailable', 'class' => 'action-settle'],
                                                        ] as $statusCode => $statusMeta): ?>
                                                            <form method="post" action="<?= e(url('/dashboard/hotel/rooms/' . $rm['id'] . '/status')) ?>" class="res-action-form room-status-form" data-room-id="<?= (int) $rm['id'] ?>" data-target-status="<?= e($statusCode) ?>" style="<?= $st === $statusCode ? 'display:none;' : '' ?>">
                                                                <?= csrf_field() ?>
                                                                <input type="hidden" name="status" value="<?= e($statusCode) ?>">
                                                                <input type="hidden" name="redirect_to" value="<?= e(url('/dashboard#inventory')) ?>">
                                                                <button type="submit" class="res-action-item <?= $statusMeta['class'] ?>" role="menuitem">
                                                                    <?= $statusMeta['label'] ?>
                                                                </button>
                                                            </form>
                                                        <?php endforeach; ?>
                                                    </div>

                                                    <div style="border-top:1px solid var(--line);margin:3px 0;"></div>

                                                    <!-- Edit Details -->
                                                    <button type="button" class="res-action-item" onclick='openEditRoomModal(<?= htmlspecialchars(json_encode($rm, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, "UTF-8") ?>)' role="menuitem">
                                                        <?= admin_icon('edit', 14) ?> Edit Room Details
                                                    </button>
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

            <!-- ── SECTION 2: HOTEL RESERVATIONS & FRONT DESK STAYS ── -->
            <div class="panel hotel-reservations-panel mt-4" id="reservations">
                <div class="panel-head" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.75rem;">
                    <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;">
                        <h3 class="mb-0">Hotel Reservations &amp; Guest Balances</h3>
                        <span class="badge" style="background:#DCFCE7;color:#15803D;font-weight:700;font-size:0.75rem;padding:2px 8px;border-radius:999px;">
                            <?= count($activeReservations) ?> Active
                        </span>
                    </div>
                    <a href="<?= e(url('/dashboard/bookings')) ?>" class="btn-view-all">View all</a>
                </div>
                <div class="panel-body hotel-reservations-wrap" style="padding:0;overflow-x:auto;">
                    <?php if ($reservations === []): ?>
                        <div class="empty-state" style="padding:2.5rem 1rem;">
                            <p>No room bookings yet. Once guests reserve with an online deposit, reservations appear here.</p>
                        </div>
                    <?php else: ?>
                        <table class="table hotel-table hotel-reservations-table" style="vertical-align:middle;width:100%;">
                            <thead>
                                <tr>
                                    <th style="width:16%;">Guest</th>
                                    <th style="width:20%;">Property</th>
                                    <th style="width:21%;">Room &amp; Stay</th>
                                    <th style="width:16%;">Total &amp; Deposit</th>
                                    <th style="width:13%;">Balance Due</th>
                                    <th style="width:9%;">Status</th>
                                    <th class="col-actions" style="width:5%;text-align:right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reservations as $b): ?>
                                    <?php
                                    $hotelStatus = (string) ($b['hotel_status'] ?? $b['status'] ?? 'pending');
                                    $checkIn = !empty($b['check_in_date']) ? $b['check_in_date'] : $b['booking_date'];
                                    $checkOut = !empty($b['check_out_date']) ? $b['check_out_date'] : $b['booking_date'];
                                    $nights = max(1, (int) ($b['nights'] ?? 1));
                                    $deposit = (float) ($b['deposit_amount'] ?? 0);
                                    $balance = (float) ($b['balance_amount'] ?? 0);
                                    $totalStay = (float) ($b['total_amount'] ?? 0);
                                    $customerName = (string) ($b['customer_name'] ?? 'Guest');
                                    $propertyName = (string) ($b['listing_title'] ?? 'Hotel Property');
                                    $roomName = (string) (!empty($b['room_name']) ? $b['room_name'] : 'Standard Room');
                                    
                                    $resPillClass = match ($hotelStatus) {
                                        'confirmed' => 'pill-approved',
                                        'deposit_paid' => 'pill-paid',
                                        'accepted' => 'pill-completed',
                                        'checked_in' => 'pill-completed',
                                        'pending' => 'pill-pending',
                                        'cancelled' => 'pill-cancelled',
                                        'no_show' => 'pill-refunded',
                                        default => 'pill-secondary',
                                    };
                                    $resPillLabel = match ($hotelStatus) {
                                        'pending' => 'Pending',
                                        'accepted' => 'Accepted',
                                        'deposit_paid' => 'Deposit Paid',
                                        'confirmed' => 'Confirmed',
                                        'checked_in' => 'Checked In',
                                        'completed' => 'Completed',
                                        'cancelled' => 'Cancelled',
                                        'no_show' => 'No-Show',
                                        default => ucfirst($hotelStatus),
                                    };
                                    ?>
                                    <tr>
                                        <!-- 1. GUEST -->
                                        <td>
                                            <strong style="color:var(--ink-400);font-size:0.88rem;display:block;line-height:1.2;"><?= e($customerName) ?></strong>
                                        </td>

                                        <!-- 2. PROPERTY -->
                                        <td>
                                            <strong style="color:var(--ink-400);font-size:0.88rem;display:block;line-height:1.25;"><?= e($propertyName) ?></strong>
                                        </td>

                                        <!-- 3. ROOM & STAY -->
                                        <td>
                                            <strong style="color:var(--teal-700);font-size:0.88rem;display:block;line-height:1.25;"><?= e($roomName) ?></strong>
                                            <small class="hint" style="font-size:0.75rem;display:block;margin-top:2px;">
                                                <?= e(date('M j', strtotime($checkIn))) ?> – <?= e(date('M j, Y', strtotime($checkOut))) ?> · <?= $nights ?> <?= $nights > 1 ? 'nights' : 'night' ?> · <?= (int) ($b['guests'] ?? 1) ?> <?= (int) ($b['guests'] ?? 1) > 1 ? 'guests' : 'guest' ?>
                                            </small>
                                        </td>

                                        <!-- 3. TOTAL & DEPOSIT PAID -->
                                        <td>
                                            <strong style="font-size:0.9rem;color:var(--ink-400);display:block;line-height:1.2;"><?= money($totalStay) ?></strong>
                                            <span class="badge" style="background:#ECFDF5;color:#047857;border:1px solid #A7F3D0;font-size:0.72rem;font-weight:600;padding:1px 6px;border-radius:5px;white-space:nowrap;margin-top:2px;display:inline-block;"><?= money($deposit > 0 ? $deposit : $totalStay) ?> deposit</span>
                                        </td>

                                        <!-- 4. BALANCE DUE AT HOTEL -->
                                        <td>
                                            <?php if ($balance > 0 && !in_array($hotelStatus, ['completed', 'cancelled', 'no_show'], true) && ($b['payment_stage'] ?? '') !== 'settled'): ?>
                                                <strong style="font-size:0.92rem;color:#D97706;display:block;line-height:1.2;"><?= money($balance) ?></strong>
                                                <small class="hint" style="font-size:0.72rem;color:#B45309;font-weight:600;white-space:nowrap;display:block;margin-top:2px;">At front desk</small>
                                            <?php elseif ($hotelStatus === 'completed' || ($b['payment_stage'] ?? '') === 'settled'): ?>
                                                <span class="pill pill-approved" style="font-size:0.74rem;padding:2px 7px;">Settled</span>
                                            <?php else: ?>
                                                <span class="hint">—</span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- 5. RESERVATION STATUS BADGE -->
                                        <td>
                                            <span class="pill <?= $resPillClass ?>" style="display:inline-flex;align-items:center;justify-content:center;padding:0.25rem 0.65rem;font-size:0.75rem;font-weight:600;line-height:1.2;white-space:nowrap;"><?= e($resPillLabel) ?></span>
                                        </td>

                                        <!-- 6. ACTIONS (3-DOT DROPDOWN MENU) -->
                                        <td class="col-actions" style="text-align:right;white-space:nowrap;">
                                            <div class="res-action-wrap">
                                                <button type="button"
                                                        class="btn-res-action-trigger"
                                                        id="resActionBtn_<?= (int) $b['id'] ?>"
                                                        aria-haspopup="true"
                                                        aria-expanded="false"
                                                        aria-controls="resActionMenu_<?= (int) $b['id'] ?>"
                                                        title="Actions for <?= e($customerName) ?>"
                                                        aria-label="Actions for <?= e($customerName) ?>">
                                                    <?= admin_icon('more-vertical', 16) ?>
                                                </button>
                                                <div class="res-action-dropdown"
                                                     id="resActionMenu_<?= (int) $b['id'] ?>"
                                                     role="menu"
                                                     aria-labelledby="resActionBtn_<?= (int) $b['id'] ?>">
                                                    <?php if ($hotelStatus === 'pending'): ?>
                                                        <!-- Accept Reservation -->
                                                        <form method="post" action="<?= e(url('/dashboard/hotel/bookings/' . $b['id'] . '/status')) ?>" class="res-action-form">
                                                            <?= csrf_field() ?>
                                                            <button type="submit" name="action" value="accept" class="res-action-item action-accept" role="menuitem">
                                                                Accept Reservation
                                                            </button>
                                                        </form>
                                                        <!-- Confirm Deposit -->
                                                        <form method="post" action="<?= e(url('/dashboard/hotel/bookings/' . $b['id'] . '/status')) ?>" class="res-action-form">
                                                            <?= csrf_field() ?>
                                                            <button type="submit" name="action" value="confirm_deposit" class="res-action-item action-confirm" role="menuitem">
                                                                Confirm Deposit
                                                            </button>
                                                        </form>
                                                        <!-- Cancel Reservation with Confirmation Dialog -->
                                                        <form method="post" action="<?= e(url('/dashboard/hotel/bookings/' . $b['id'] . '/status')) ?>" class="res-action-form" onsubmit="return confirm('Cancel this pending reservation for <?= e(addslashes($customerName)) ?>?');">
                                                            <?= csrf_field() ?>
                                                            <button type="submit" name="action" value="cancel" class="res-action-item action-cancel" role="menuitem">
                                                                Cancel Reservation
                                                            </button>
                                                        </form>

                                                    <?php elseif ($hotelStatus === 'accepted' || $hotelStatus === 'deposit_paid'): ?>
                                                        <!-- Confirm Deposit -->
                                                        <form method="post" action="<?= e(url('/dashboard/hotel/bookings/' . $b['id'] . '/status')) ?>" class="res-action-form">
                                                            <?= csrf_field() ?>
                                                            <button type="submit" name="action" value="confirm_deposit" class="res-action-item action-confirm" role="menuitem">
                                                                Confirm Deposit
                                                            </button>
                                                        </form>
                                                        <!-- Cancel Reservation with Confirmation Dialog -->
                                                        <form method="post" action="<?= e(url('/dashboard/hotel/bookings/' . $b['id'] . '/status')) ?>" class="res-action-form" onsubmit="return confirm('Cancel this reservation for <?= e(addslashes($customerName)) ?>?');">
                                                            <?= csrf_field() ?>
                                                            <button type="submit" name="action" value="cancel" class="res-action-item action-cancel" role="menuitem">
                                                                Cancel Reservation
                                                            </button>
                                                        </form>

                                                    <?php elseif ($hotelStatus === 'confirmed'): ?>
                                                        <!-- Check In -->
                                                        <form method="post" action="<?= e(url('/dashboard/hotel/bookings/' . $b['id'] . '/status')) ?>" class="res-action-form">
                                                            <?= csrf_field() ?>
                                                            <button type="submit" name="action" value="check_in" class="res-action-item action-checkin" role="menuitem">
                                                                Check In
                                                            </button>
                                                        </form>
                                                        <!-- Cancel Reservation with Confirmation Dialog -->
                                                        <form method="post" action="<?= e(url('/dashboard/hotel/bookings/' . $b['id'] . '/status')) ?>" class="res-action-form" onsubmit="return confirm('Cancel this confirmed reservation for <?= e(addslashes($customerName)) ?>?');">
                                                            <?= csrf_field() ?>
                                                            <button type="submit" name="action" value="cancel" class="res-action-item action-cancel" role="menuitem">
                                                                Cancel Reservation
                                                            </button>
                                                        </form>

                                                    <?php elseif ($hotelStatus === 'checked_in'): ?>
                                                        <!-- Settle Balance & Check Out -->
                                                        <?php $isSettled = ($b['payment_stage'] ?? '') === 'settled' || $balance <= 0; ?>
                                                        <form method="post" action="<?= e(url('/dashboard/hotel/bookings/' . $b['id'] . '/status')) ?>" class="res-action-form" onsubmit="return confirm('<?= $isSettled ? 'Complete check-out for this guest?' : 'Collect outstanding balance of ' . money($balance) . ' and complete check-out?' ?>');">
                                                            <?= csrf_field() ?>
                                                            <button type="submit" name="action" value="settle_balance" class="res-action-item action-settle" role="menuitem">
                                                                <?= $isSettled ? 'Complete Check Out' : 'Settle &amp; Check Out' ?>
                                                            </button>
                                                        </form>

                                                    <?php else: ?>
                                                        <div style="padding: 8px 12px; font-size: 0.8rem; color: #94a3b8; font-weight: 500;">No actions available</div>
                                                    <?php endif; ?>
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

            <!-- ── SECTION 3: HOTEL PROPERTIES ── -->
            <div class="panel mt-4">
                <div class="panel-head">
                    <h3>Your Hotel Properties</h3>
                    <a href="<?= e(url('/dashboard/listings')) ?>" class="btn btn-ghost btn-sm">View all</a>
                </div>
                <div class="panel-body" style="padding:0;overflow-x:auto;">
                    <?php if ($listings === []): ?>
                        <div class="empty-state" style="padding:2.5rem 1rem;"><p>You haven't created any listings yet. <a href="<?= e(url('/dashboard/listings/create')) ?>">Create your first →</a></p></div>
                    <?php else: ?>
                        <table class="table hotel-table hotel-properties-table" style="vertical-align:middle;width:100%;margin:0;">
                            <thead>
                                <tr>
                                    <th style="width:28%;text-transform:uppercase;font-size:0.75rem;font-weight:700;letter-spacing:0.04em;color:var(--ink-300,#64748b);">Hotel Name</th>
                                    <th style="width:22%;text-transform:uppercase;font-size:0.75rem;font-weight:700;letter-spacing:0.04em;color:var(--ink-300,#64748b);">Location</th>
                                    <th style="width:18%;text-transform:uppercase;font-size:0.75rem;font-weight:700;letter-spacing:0.04em;color:var(--ink-300,#64748b);">Base Nightly Rate</th>
                                    <th style="width:10%;text-transform:uppercase;font-size:0.75rem;font-weight:700;letter-spacing:0.04em;color:var(--ink-300,#64748b);">Rating</th>
                                    <th style="width:10%;text-transform:uppercase;font-size:0.75rem;font-weight:700;letter-spacing:0.04em;color:var(--ink-300,#64748b);">Status</th>
                                    <th class="col-actions" style="width:12%;text-align:right;text-transform:uppercase;font-size:0.75rem;font-weight:700;letter-spacing:0.04em;color:var(--ink-300,#64748b);">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($listings as $l): ?>
                                <tr>
                                    <td>
                                        <a href="<?= e(url('/listing/' . $l['slug'])) ?>" style="font-weight:600;"><?= e($l['title']) ?></a>
                                    </td>
                                    <td class="hint" style="white-space:nowrap;">📍 <?= e($l['area']) ?></td>
                                    <td style="white-space:nowrap;"><strong><?= money($l['price']) ?></strong></td>
                                    <td style="white-space:nowrap;"><?= (int) $l['review_count'] > 0 ? number_format((float) $l['avg_rating'], 1) . ' ★' : '—' ?></td>
                                    <td style="white-space:nowrap;"><span class="pill pill-<?= e($l['status']) ?>"><?= e(status_label((string) $l['status'])) ?></span></td>
                                    <td class="col-actions" style="text-align:right;white-space:nowrap;">
                                        <div class="hotel-prop-actions">
                                            <a href="<?= e(url('/listing/' . $l['slug'])) ?>" class="btn btn-ghost btn-sm hotel-prop-btn" target="_blank" rel="noopener">View Listing ↗</a>
                                            <a href="<?= e(url('/dashboard/listings/' . $l['id'] . '/edit')) ?>" class="btn btn-ghost btn-sm hotel-prop-btn">Edit</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ── SECTION 4: GUEST INQUIRIES ── -->
            <div class="panel mt-4">
                <div class="panel-head"><h3>Recent Guest Messages</h3><a href="<?= e(url('/messages')) ?>" class="btn btn-ghost btn-sm">Inbox</a></div>
                <div class="panel-body" style="padding:0;overflow-x:auto;">
                    <?php if ($inquiries === []): ?>
                        <div class="empty-state" style="padding:2.5rem 1rem;"><p>No guest messages yet. Travelers reach you from your hotel listing.</p></div>
                    <?php else: ?>
                        <table class="table">
                            <thead><tr><th>Guest</th><th>Last message</th><th>When</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($inquiries as $c): ?>
                                <?php
                                $snippet = trim(preg_replace('/\s+/', ' ', (string) ($c['last_body'] ?? '')) ?? '');
                                if (function_exists('mb_strlen') && mb_strlen($snippet) > 72) {
                                    $snippet = mb_substr($snippet, 0, 69) . '…';
                                } elseif (strlen($snippet) > 72) {
                                    $snippet = substr($snippet, 0, 69) . '…';
                                }
                                $unreadHere = (int) ($c['unread'] ?? 0);
                                ?>
                                <tr>
                                    <td>
                                        <?= e((string) $c['partner_name']) ?>
                                        <?php if ($unreadHere > 0): ?>
                                            <span class="badge"><?= $unreadHere ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="hint"><?= e($snippet !== '' ? $snippet : '—') ?></td>
                                    <td><?= !empty($c['last_at']) ? e(loc_date((string) $c['last_at'])) : '—' ?></td>
                                    <td><a class="btn btn-ghost btn-sm" href="<?= e(url('/messages/' . (int) $c['partner_id'])) ?>">Reply</a></td>
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
<div id="modal-add-room" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;padding:1rem;">
    <div class="card-soft" style="background:#FFF;max-width:540px;width:100%;max-height:90vh;overflow-y:auto;border-radius:12px;padding:1.5rem;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
            <h3 class="mb-0">Add New Room Type</h3>
            <button type="button" onclick="closeAddRoomModal()" class="btn btn-ghost btn-sm" style="font-size:1.2rem;line-height:1;">✕</button>
        </div>
        <form method="post" action="<?= e(url('/dashboard/hotel/rooms/create')) ?>">
            <?= csrf_field() ?>
            <div class="field-row">
                <label>Select Hotel Property</label>
                <select name="listing_id" class="input" required>
                    <?php foreach ($listings as $l): ?>
                        <option value="<?= $l['id'] ?>"><?= e($l['title']) ?> (<?= e($l['area']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field-row">
                <label>Room Name</label>
                <input type="text" name="name" class="input" placeholder="e.g. Deluxe Ocean View Villa" required>
            </div>
            <div class="form-grid-2">
                <div class="field-row">
                    <label>Bed Configuration</label>
                    <input type="text" name="bed_type" class="input" placeholder="e.g. 1 King Bed or 2 Twin Beds" value="1 King Bed" required>
                </div>
                <div class="field-row">
                    <label>Max Guests</label>
                    <input type="number" name="max_guests" class="input" min="1" max="10" value="2" required>
                </div>
            </div>
            <div class="form-grid-2">
                <div class="field-row">
                    <label>Nightly Rate (₱)</label>
                    <input type="number" name="price_per_night" class="input" step="50" min="0" placeholder="2800" required>
                </div>
                <div class="field-row">
                    <label>Deposit Percentage (%)</label>
                    <input type="number" name="deposit_percentage" class="input" min="5" max="100" value="20" required>
                    <small class="hint">Remaining % paid at hotel check-in</small>
                </div>
            </div>
            <div class="field-row">
                <label>Room Size</label>
                <input type="text" name="room_size" class="input" placeholder="e.g. 38 m² (409 sq ft)" value="38 m²">
            </div>
            <div class="field-row">
                <label>Key Amenities (comma separated)</label>
                <input type="text" name="amenities" class="input" placeholder="Air Conditioning, Free Wi-Fi, Hot Rain Shower, Private Balcony, Mini-Fridge">
            </div>
            <div class="field-row">
                <label>Photo URL (Optional)</label>
                <input type="text" name="image" class="input" placeholder="https://images.unsplash.com/...">
            </div>
            <div class="field-row">
                <label>Description</label>
                <textarea name="description" class="input" rows="3" placeholder="Brief description of the room, views, and unique features..."></textarea>
            </div>
            <div style="display:flex;gap:0.5rem;justify-content:flex-end;margin-top:1rem;">
                <button type="button" onclick="closeAddRoomModal()" class="btn btn-ghost">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Room Type</button>
            </div>
        </form>
    </div>
</div>

<!-- ── MODAL: EDIT ROOM TYPE ── -->
<div id="modal-edit-room" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;padding:1rem;">
    <div class="card-soft" style="background:#FFF;max-width:540px;width:100%;max-height:90vh;overflow-y:auto;border-radius:12px;padding:1.5rem;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
            <h3 class="mb-0">Edit Room Type</h3>
            <button onclick="document.getElementById('modal-edit-room').style.display='none';" class="btn btn-ghost btn-sm" style="font-size:1.2rem;line-height:1;">✕</button>
        </div>
        <form id="edit-room-form" method="post" action="">
            <?= csrf_field() ?>
            <div class="field-row">
                <label>Room Name</label>
                <input type="text" id="edit-room-name" name="name" class="input" required>
            </div>
            <div class="form-grid-2">
                <div class="field-row">
                    <label>Bed Configuration</label>
                    <input type="text" id="edit-room-bed" name="bed_type" class="input" required>
                </div>
                <div class="field-row">
                    <label>Max Guests</label>
                    <input type="number" id="edit-room-guests" name="max_guests" class="input" min="1" max="10" required>
                </div>
            </div>
            <div class="form-grid-2">
                <div class="field-row">
                    <label>Nightly Rate (₱)</label>
                    <input type="number" id="edit-room-price" name="price_per_night" class="input" step="50" min="0" required>
                </div>
                <div class="field-row">
                    <label>Deposit Percentage (%)</label>
                    <input type="number" id="edit-room-deposit" name="deposit_percentage" class="input" min="5" max="100" required>
                </div>
            </div>
            <div class="field-row">
                <label>Room Size</label>
                <input type="text" id="edit-room-size" name="room_size" class="input">
            </div>
            <div class="field-row">
                <label>Availability Status</label>
                <select id="edit-room-status" name="status" class="input">
                    <option value="available">🟢 Available</option>
                    <option value="blocked">🔒 Blocked (Maintenance / Hold)</option>
                    <option value="fully_booked">🔴 Fully Booked</option>
                    <option value="unavailable">⚪ Unavailable</option>
                </select>
            </div>
            <div class="field-row">
                <label>Key Amenities</label>
                <input type="text" id="edit-room-amenities" name="amenities" class="input">
            </div>
            <div class="field-row">
                <label>Description</label>
                <textarea id="edit-room-desc" name="description" class="input" rows="3"></textarea>
            </div>
            <div style="display:flex;gap:0.5rem;justify-content:flex-end;margin-top:1rem;">
                <button type="button" onclick="document.getElementById('modal-edit-room').style.display='none';" class="btn btn-ghost">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Room</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddRoomModal() {
    var gatingModal = document.getElementById('verificationGatingModal');
    var metaVerified = document.querySelector('meta[name="auth-user-verified"]');
    var isVerified = (metaVerified && metaVerified.content === '1') || (gatingModal && gatingModal.dataset.userVerified === '1');
    if (!isVerified) {
        return;
    }
    var modal = document.getElementById('modal-add-room');
    if (modal) {
        modal.style.display = 'flex';
        var firstInput = modal.querySelector('input:not([type="hidden"]), select, textarea');
        if (firstInput) {
            try { firstInput.focus(); } catch (e) {}
        }
    }
}
function closeAddRoomModal() {
    var modal = document.getElementById('modal-add-room');
    if (modal) {
        modal.style.display = 'none';
    }
}
function openEditRoomModal(room) {
    document.getElementById('edit-room-form').action = '<?= url('/dashboard/hotel/rooms/') ?>' + room.id + '/edit';
    document.getElementById('edit-room-name').value = room.name || '';
    document.getElementById('edit-room-bed').value = room.bed_type || '1 King Bed';
    document.getElementById('edit-room-guests').value = room.max_guests || 2;
    document.getElementById('edit-room-price').value = room.price_per_night || 0;
    document.getElementById('edit-room-deposit').value = room.deposit_percentage || 20;
    document.getElementById('edit-room-size').value = room.room_size || '';
    document.getElementById('edit-room-status').value = room.status || 'available';
    document.getElementById('edit-room-amenities').value = room.amenities || '';
    document.getElementById('edit-room-desc').value = room.description || '';
    document.getElementById('modal-edit-room').style.display = 'flex';
}
function closeEditRoomModal() {
    var modal = document.getElementById('modal-edit-room');
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
    var pill = document.getElementById('overviewRoomStatusPill_' + roomId) || document.getElementById('roomStatusPill_' + roomId);
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
