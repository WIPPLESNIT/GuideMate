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
        <div>
            <div class="section-head" style="margin-bottom:1rem;">
                <div>
                    <h1 class="mb-0"><?= !empty($isFirstLogin) ? 'Welcome to GuideMate' : 'Welcome back' ?>, <?= e(explode(' ', (string) $user['name'])[0]) ?></h1>
                    <p class="hint mb-0">Hotel Partner Portal — manage room inventory, live availability, guest deposits, and front desk check-ins.</p>
                </div>
                <div style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;">
                    <a href="#new-room-modal" class="btn btn-primary" onclick="document.getElementById('modal-add-room').style.display='flex';return false;">+ Add Room Type</a>
                    <a href="<?= e(url('/dashboard/listings/create')) ?>" class="btn btn-ghost" data-action="new-listing">+ New Hotel</a>
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
                <div class="stat stat-ico-card">
                    <span class="stat-ico">🛏️</span>
                    <div class="label">Total Rooms</div>
                    <div class="value"><?= $totalRoomsCount ?></div>
                    <div class="stat-action" style="color:#059669;"><?= $availableRoomsCount ?> bookable live</div>
                </div>
                <div class="stat stat-ico-card">
                    <span class="stat-ico">📅</span>
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
                <a href="<?= e(url('/messages')) ?>" class="stat stat-ico-card stat-clickable">
                    <span class="stat-ico"><?= admin_icon('messages', 18) ?></span>
                    <div class="label">Inquiries</div>
                    <div class="value"><?= $unread ?></div>
                    <div class="stat-action">Open inbox →</div>
                </a>
            </div>

            <!-- ── SECTION 1: ROOM INVENTORY & AVAILABILITY CONTROLS ── -->
            <div class="panel mt-4" id="inventory">
                <div class="panel-head" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.5rem;">
                    <div>
                        <h3 class="mb-0">Room Inventory & Live Availability</h3>
                        <p class="hint mb-0" style="font-size:0.85rem;">Instantly toggle rooms as Available, Blocked for maintenance, or Fully Booked. Updates sync live to the guest app.</p>
                    </div>
                    <button class="btn btn-primary btn-sm" onclick="document.getElementById('modal-add-room').style.display='flex';">+ Add Room Type</button>
                </div>
                <div class="panel-body" style="padding:0;overflow-x:auto;">
                    <?php if ($rooms === []): ?>
                        <div class="empty-state" style="padding:2.5rem 1rem;">
                            <p>No room types configured yet. Add your first room type to start receiving guest bookings.</p>
                        </div>
                    <?php else: ?>
                        <table class="table" style="vertical-align:middle;">
                            <thead>
                                <tr>
                                    <th>Room & Property</th>
                                    <th>Specs</th>
                                    <th>Nightly Rate</th>
                                    <th>Online Deposit</th>
                                    <th>Availability Status</th>
                                    <th>Quick Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rooms as $rm): ?>
                                    <?php
                                    $st = (string) ($rm['status'] ?? 'available');
                                    $badgeStyle = match ($st) {
                                        'available' => 'background:#DCFCE7;color:#15803D;border:1px solid #86EFAC;',
                                        'blocked' => 'background:#FEF3C7;color:#B45309;border:1px solid #FCD34D;',
                                        'fully_booked' => 'background:#FEE2E2;color:#B91C1C;border:1px solid #FCA5A5;',
                                        default => 'background:#F1F5F9;color:#475569;border:1px solid #CBD5E1;',
                                    };
                                    $statusLabel = match ($st) {
                                        'available' => '🟢 Available',
                                        'blocked' => '🔒 Blocked / Maint.',
                                        'fully_booked' => '🔴 Fully Booked',
                                        default => '⚪ Unavailable',
                                    };
                                    ?>
                                    <tr>
                                        <td>
                                            <div style="display:flex;align-items:center;gap:0.75rem;">
                                                <img src="<?= e(img_src($rm['image'] ?? null, 'room' . $rm['id'])) ?>" alt="" style="width:48px;height:48px;border-radius:8px;object-fit:cover;border:1px solid var(--line);">
                                                <div>
                                                    <strong><?= e($rm['name']) ?></strong><br>
                                                    <span class="hint" style="font-size:0.8rem;"><?= e($rm['hotel_title'] ?? 'Hotel') ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span style="font-size:0.85rem;display:inline-block;"><?= e($rm['bed_type'] ?? '1 King') ?></span><br>
                                            <span class="hint" style="font-size:0.78rem;">👥 Max <?= (int) ($rm['max_guests'] ?? 2) ?> · 📐 <?= e($rm['room_size'] ?? '32 m²') ?></span>
                                        </td>
                                        <td>
                                            <strong><?= money($rm['price_per_night']) ?></strong><br>
                                            <span class="hint" style="font-size:0.75rem;">per night</span>
                                        </td>
                                        <td>
                                            <span class="badge" style="background:#E0F2FE;color:#0369A1;font-weight:600;">
                                                <?= (int) ($rm['deposit_percentage'] ?? 20) ?>% (<?= money(((float) $rm['price_per_night'] * (int) ($rm['deposit_percentage'] ?? 20)) / 100) ?>)
                                            </span><br>
                                            <span class="hint" style="font-size:0.75rem;">Balance at desk</span>
                                        </td>
                                        <td>
                                            <span class="badge" style="<?= $badgeStyle ?>font-weight:600;padding:4px 8px;border-radius:6px;">
                                                <?= $statusLabel ?>
                                            </span>
                                        </td>
                                        <td>
                                            <!-- Quick Status Changer Form -->
                                            <form method="post" action="<?= e(url('/dashboard/hotel/rooms/' . $rm['id'] . '/status')) ?>" style="display:inline-flex;gap:4px;align-items:center;">
                                                <?= csrf_field() ?>
                                                <select name="status" class="input input-sm" onchange="this.form.submit()" style="padding:3px 6px;font-size:0.8rem;height:auto;border-radius:6px;">
                                                    <option value="available" <?= $st === 'available' ? 'selected' : '' ?>>🟢 Set Available</option>
                                                    <option value="blocked" <?= $st === 'blocked' ? 'selected' : '' ?>>🔒 Block Room</option>
                                                    <option value="fully_booked" <?= $st === 'fully_booked' ? 'selected' : '' ?>>🔴 Fully Booked</option>
                                                    <option value="unavailable" <?= $st === 'unavailable' ? 'selected' : '' ?>>⚪ Set Unavailable</option>
                                                </select>
                                            </form>
                                            <button class="btn btn-ghost btn-sm" onclick='openEditRoomModal(<?= json_encode($rm) ?>)' style="padding:3px 8px;font-size:0.8rem;margin-left:4px;">Edit</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ── SECTION 2: HOTEL RESERVATIONS & FRONT DESK STAYS ── -->
            <div class="panel mt-4" id="reservations">
                <div class="panel-head">
                    <div>
                        <h3 class="mb-0">Hotel Reservations & Guest Balances</h3>
                        <p class="hint mb-0" style="font-size:0.85rem;">View verified deposits, track upcoming arrivals, collect outstanding front-desk balances, and handle check-ins.</p>
                    </div>
                    <a href="<?= e(url('/dashboard/bookings')) ?>" class="btn btn-ghost btn-sm">Full View</a>
                </div>
                <div class="panel-body" style="padding:0;overflow-x:auto;">
                    <?php if ($reservations === []): ?>
                        <div class="empty-state" style="padding:2.5rem 1rem;">
                            <p>No room bookings yet. Once guests reserve with an online deposit, reservations appear here.</p>
                        </div>
                    <?php else: ?>
                        <table class="table" style="vertical-align:middle;">
                            <thead>
                                <tr>
                                    <th>Guest</th>
                                    <th>Room & Stay</th>
                                    <th>Deposit Paid</th>
                                    <th>Balance Due at Hotel</th>
                                    <th>Reservation Status</th>
                                    <th>Front Desk Action</th>
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
                                    
                                    $statusPill = match ($hotelStatus) {
                                        'deposit_paid' => '<span class="badge" style="background:#DCFCE7;color:#166534;border:1px solid #86EFAC;">Deposit Paid (Pending Confirm)</span>',
                                        'confirmed' => '<span class="badge" style="background:#E0F2FE;color:#0369A1;border:1px solid #7DD3FC;">Confirmed (Room Held)</span>',
                                        'checked_in' => '<span class="badge" style="background:#FEF3C7;color:#92400E;border:1px solid #FCD34D;">Checked In (Balance Due)</span>',
                                        'completed' => '<span class="badge" style="background:#F1F5F9;color:#334155;border:1px solid #CBD5E1;">Settled & Checked Out</span>',
                                        'no_show' => '<span class="badge" style="background:#FEE2E2;color:#991B1B;border:1px solid #FCA5A5;">No-Show</span>',
                                        'cancelled' => '<span class="badge" style="background:#FEE2E2;color:#991B1B;border:1px solid #FCA5A5;">Cancelled</span>',
                                        default => '<span class="badge">' . e(ucfirst($hotelStatus)) . '</span>',
                                    };
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?= e($b['customer_name'] ?? 'Guest') ?></strong><br>
                                            <span class="hint" style="font-size:0.8rem;"><?= e($b['customer_email'] ?? '') ?></span>
                                        </td>
                                        <td>
                                            <strong><?= e($b['room_name'] ?? $b['listing_title']) ?></strong><br>
                                            <span class="hint" style="font-size:0.8rem;">
                                                📅 <?= e(date('M j', strtotime($checkIn))) ?> – <?= e(date('M j, Y', strtotime($checkOut))) ?> (<?= $nights ?> night<?= $nights > 1 ? 's' : '' ?>)
                                            </span>
                                        </td>
                                        <td>
                                            <span style="color:#166534;font-weight:700;font-size:0.95rem;">
                                                <?= money($deposit > 0 ? $deposit : $b['total_amount']) ?>
                                            </span><br>
                                            <span class="hint" style="font-size:0.75rem;">Online via <?= e(ucfirst($b['payment_method'] ?? 'Card/QR')) ?></span>
                                        </td>
                                        <td>
                                            <?php if ($balance > 0 && !in_array($hotelStatus, ['completed', 'cancelled', 'no_show'], true)): ?>
                                                <strong style="color:#D97706;font-size:0.95rem;"><?= money($balance) ?></strong><br>
                                                <span class="hint" style="font-size:0.75rem;">Collect at Check-in</span>
                                            <?php elseif ($hotelStatus === 'completed'): ?>
                                                <span style="color:#166534;font-size:0.85rem;font-weight:600;">✓ Balance Settled</span>
                                            <?php else: ?>
                                                <span class="hint">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= $statusPill ?>
                                        </td>
                                        <td>
                                            <div style="display:flex;gap:4px;flex-wrap:wrap;align-items:center;">
                                                <?php if ($hotelStatus === 'deposit_paid' || $hotelStatus === 'pending'): ?>
                                                    <form method="post" action="<?= e(url('/dashboard/hotel/bookings/' . $b['id'] . '/status')) ?>" style="display:inline;">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="action" value="confirm">
                                                        <button class="btn btn-primary btn-sm" type="submit">Confirm</button>
                                                    </form>
                                                <?php elseif ($hotelStatus === 'confirmed'): ?>
                                                    <form method="post" action="<?= e(url('/dashboard/hotel/bookings/' . $b['id'] . '/status')) ?>" style="display:inline;">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="action" value="check_in">
                                                        <button class="btn btn-primary btn-sm" type="submit" style="background:#059669;border-color:#059669;">Check In</button>
                                                    </form>
                                                    <form method="post" action="<?= e(url('/dashboard/hotel/bookings/' . $b['id'] . '/status')) ?>" style="display:inline;">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="action" value="no_show">
                                                        <button class="btn btn-ghost btn-sm" type="submit" onclick="return confirm('Mark this guest as No-Show?')">No-Show</button>
                                                    </form>
                                                <?php elseif ($hotelStatus === 'checked_in'): ?>
                                                    <form method="post" action="<?= e(url('/dashboard/hotel/bookings/' . $b['id'] . '/status')) ?>" style="display:inline;">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="action" value="settle_balance">
                                                        <button class="btn btn-primary btn-sm" type="submit" style="background:#0284C7;border-color:#0284C7;">Settle <?= money($balance) ?></button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="hint" style="font-size:0.8rem;">No actions</span>
                                                <?php endif; ?>
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
                        <table class="table">
                            <thead><tr><th>Hotel Name</th><th>Location</th><th>Base Nightly Rate</th><th>Rating</th><th>Status</th><th>Actions</th></tr></thead>
                            <tbody>
                            <?php foreach ($listings as $l): ?>
                                <tr>
                                    <td>
                                        <a href="<?= e(url('/listing/' . $l['slug'])) ?>" style="font-weight:600;"><?= e($l['title']) ?></a>
                                    </td>
                                    <td class="hint">📍 <?= e($l['area']) ?></td>
                                    <td><strong><?= money($l['price']) ?></strong></td>
                                    <td><?= (int) $l['review_count'] > 0 ? number_format((float) $l['avg_rating'], 1) . ' ★' : '—' ?></td>
                                    <td><span class="pill pill-<?= e($l['status']) ?>"><?= e(status_label((string) $l['status'])) ?></span></td>
                                    <td>
                                        <a href="<?= e(url('/listing/' . $l['slug'])) ?>" class="btn btn-ghost btn-sm" target="_blank">View Listing ↗</a>
                                        <a href="<?= e(url('/dashboard/listings/' . $l['id'] . '/edit')) ?>" class="btn btn-ghost btn-sm">Edit</a>
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
            <button onclick="document.getElementById('modal-add-room').style.display='none';" class="btn btn-ghost btn-sm" style="font-size:1.2rem;line-height:1;">✕</button>
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
                <button type="button" onclick="document.getElementById('modal-add-room').style.display='none';" class="btn btn-ghost">Cancel</button>
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
</script>
