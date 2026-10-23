<?php
/**
 * @var array<string,mixed> $listing
 * @var array<int,array<string,mixed>> $gallery
 * @var array<int,array<string,mixed>> $reviews
 * @var array{avg:float,count:int,breakdown:array<int,int>} $summary
 * @var bool $canReview @var bool $isFavorited
 * @var array<string,string> $errors
 * @var ?array<int,array<string,mixed>> $hotelRooms
 */
$errors = $errors ?? [];
$user = auth_user();
$isOwner = $user && (int) $user['id'] === (int) $listing['user_id'];
$isBookable = $isBookable ?? \App\Models\Listing::isBookable($listing);
$canSeeMapLocation = $canSeeMapLocation ?? false;
$lat = $canSeeMapLocation ? ($listing['latitude'] ?? null) : null;
$lng = $canSeeMapLocation ? ($listing['longitude'] ?? null) : null;
$gallery = $gallery ?: [];
$host = host_labels($listing['owner_role'] ?? null);

$isHotel = ($listing['category_slug'] ?? '') === 'hotels';
$hotelRooms = $hotelRooms ?? ($isHotel ? \App\Models\HotelRoom::forListing((int) $listing['id']) : []);
?>
<div class="container">
    <div class="breadcrumb">
        <a href="<?= e(url('/')) ?>">Home</a> ›
        <a href="<?= e(url('/' . $listing['category_slug'])) ?>"><?= e($listing['category_name']) ?></a> ›
        <span><?= e($listing['title']) ?></span>
    </div>

    <?php if ($listing['status'] !== 'approved'): ?>
        <div class="card-soft" style="background:#fdf0d5;border-color:#f0d9a0;">
            <strong>Preview mode —</strong> this listing is <em><?= e($listing['status']) ?></em> and not yet visible to the public.
        </div>
    <?php endif; ?>

    <div class="detail-hero">
        <div class="main-img">
            <img src="<?= e(img_src($listing['cover_image'] ?? null, 'listing' . $listing['id'])) ?>" alt="<?= e($listing['title']) ?>" onerror="this.onerror=null;this.src='https://picsum.photos/seed/listing<?= (int) $listing['id'] ?>/1200/800';">
        </div>
        <div class="side">
            <?php for ($i = 0; $i < 2; $i++): ?>
                <img src="<?= e(img_src($gallery[$i]['image_path'] ?? null, 'gallery' . $listing['id'] . $i)) ?>" alt="">
            <?php endfor; ?>
        </div>
    </div>

    <div class="detail-layout">
        <div>
            <span class="chip"><?= e($listing['category_name']) ?></span>
            <h1 class="detail-title mt-2"><?= e($listing['title']) ?></h1>
            <div class="detail-meta">
                <span class="rating"><?= e(stars($summary['avg'])) ?> <strong><?= number_format($summary['avg'], 1) ?></strong>
                    <span class="count">(<?= $summary['count'] ?> reviews)</span></span>
                <span>📍 <?= e($listing['area']) ?></span>
                <?php if (!empty($listing['duration'])): ?><span>⏱️ <?= e($listing['duration']) ?></span><?php endif; ?>
                <?php if (!empty($guideBadge)): ?><span class="chip"><?= e($guideBadge) ?> <?= e($host['badge']) ?></span><?php endif; ?>
            </div>

            <?php if (!empty($weather)): ?>
                <div class="card-soft mt-2" style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
                    <strong>Weather near <?= e($listing['area']) ?></strong>
                    <span><?= e((string) $weather['temp']) ?>°C — <?= e(ucfirst((string) $weather['desc'])) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($lat && $lng): ?>
                <p class="mt-2">
                    <a class="btn btn-ghost btn-sm" href="https://www.google.com/maps/dir/?api=1&destination=<?= e((string) $lat) ?>,<?= e((string) $lng) ?>" target="_blank" rel="noopener">🚗 Check directions on Google Maps</a>
                </p>
            <?php endif; ?>

            <div class="chips">
                <?php $isHostOnline = \App\Models\User::isOnline((int) $listing['user_id']); ?>
                <span class="chip chip-host">
                    <span class="presence-dot <?= $isHostOnline ? 'online' : 'offline' ?>"></span>
                    Hosted by <?= e($listing['owner_name']) ?> · <span class="presence-text"><?= $isHostOnline ? 'Online' : 'Offline' ?></span>
                </span>
                <?php if ($canSeeMapLocation && !empty($listing['address'])): ?><span class="chip">🗺️ <?= e($listing['address']) ?></span><?php endif; ?>
            </div>

            <!-- ── DEDICATED AVAILABLE ROOMS SECTION (FOR HOTELS) ── -->
            <?php if ($isHotel && $hotelRooms !== []): ?>
                <section class="mt-4 mb-4" id="available-rooms">
                    <div style="display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:1.25rem;flex-wrap:wrap;gap:0.5rem;border-bottom:2px solid var(--line);padding-bottom:0.75rem;">
                        <div>
                            <h2 class="mb-0" style="font-size:1.6rem;color:var(--teal-900);">Available Rooms & Suites</h2>
                            <p class="hint mb-0">Choose your room type. Pay a small deposit now to confirm, and pay the rest at hotel check-in.</p>
                        </div>
                        <span class="chip" style="background:#E0F2FE;color:#0369A1;font-weight:600;font-size:0.85rem;">🏨 <?= count($hotelRooms) ?> Room Types</span>
                    </div>

                    <div class="rooms-list" style="display:flex;flex-direction:column;gap:1.25rem;">
                        <?php foreach ($hotelRooms as $room): ?>
                            <?php
                            $rStatus = (string) ($room['status'] ?? 'available');
                            $isAvail = $rStatus === 'available';
                            $depositPct = (int) ($room['deposit_percentage'] ?? 20);
                            $depositNow = round(((float) $room['price_per_night'] * $depositPct) / 100, 2);
                            $balanceAtDesk = round((float) $room['price_per_night'] - $depositNow, 2);

                            $badgeHtml = match ($rStatus) {
                                'available' => '<span class="badge" style="background:#DCFCE7;color:#166534;border:1px solid #86EFAC;font-weight:600;">🟢 Available</span>',
                                'blocked' => '<span class="badge" style="background:#FEF3C7;color:#92400E;border:1px solid #FCD34D;font-weight:600;">🔒 Blocked (Maintenance)</span>',
                                'fully_booked' => '<span class="badge" style="background:#FEE2E2;color:#991B1B;border:1px solid #FCA5A5;font-weight:600;">🔴 Fully Booked</span>',
                                default => '<span class="badge" style="background:#F1F5F9;color:#475569;border:1px solid #CBD5E1;font-weight:600;">⚪ Unavailable</span>',
                            };
                            ?>
                            <div class="card-soft room-card" style="padding:0;overflow:hidden;border:1px solid var(--line);border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,0.05);<?= !$isAvail ? 'opacity:0.75;background:#F8FAFC;' : 'background:#FFF;' ?>">
                                <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(260px, 1fr));">
                                    <div style="position:relative;min-height:220px;background:#E2E8F0;">
                                        <img src="<?= e(img_src($room['image'] ?? null, 'room' . $room['id'])) ?>" alt="<?= e($room['name']) ?>" style="width:100%;height:100%;object-fit:cover;position:absolute;inset:0;" onerror="this.onerror=null;this.src='https://picsum.photos/seed/room<?= (int) $room['id'] ?>/800/600';">
                                    </div>
                                    <div style="padding:1.25rem;display:flex;flex-direction:column;justify-content:space-between;">
                                        <div>
                                            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:0.5rem;flex-wrap:wrap;">
                                                <div>
                                                    <h3 style="margin:0 0 4px;font-size:1.25rem;font-weight:700;color:var(--ink-900);"><?= e($room['name']) ?></h3>
                                                    <div style="display:flex;gap:0.75rem;flex-wrap:wrap;font-size:0.85rem;color:var(--ink-500);margin-bottom:8px;">
                                                        <span>📐 <?= e($room['room_size'] ?? '32 m²') ?></span>
                                                        <span>🛏️ <?= e($room['bed_type'] ?? '1 King Bed') ?></span>
                                                        <span>👥 Up to <?= (int) ($room['max_guests'] ?? 2) ?> guests</span>
                                                    </div>
                                                </div>
                                                <div><?= $badgeHtml ?></div>
                                            </div>

                                            <p class="hint" style="font-size:0.88rem;line-height:1.45;margin-bottom:0.75rem;color:var(--ink-600);">
                                                <?= e($room['description'] ?? 'Comfortable, well-appointed guest room with modern hotel amenities.') ?>
                                            </p>

                                            <?php if (!empty($room['amenities'])): ?>
                                                <div style="display:flex;flex-wrap:wrap;gap:5px;margin-bottom:1rem;">
                                                    <?php foreach (explode(',', (string) $room['amenities']) as $am): ?>
                                                        <?php if (trim($am) !== ''): ?>
                                                            <span class="chip" style="font-size:0.78rem;padding:3px 8px;background:var(--ink-50);border:1px solid var(--line);border-radius:4px;">✓ <?= e(trim($am)) ?></span>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <div style="border-top:1px solid var(--line);padding-top:0.85rem;display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:0.75rem;">
                                            <div>
                                                <div style="display:flex;align-items:baseline;gap:4px;">
                                                    <span style="font-size:1.45rem;font-weight:800;color:var(--teal-900);"><?= money($room['price_per_night']) ?></span>
                                                    <span class="hint" style="font-size:0.85rem;">/ night</span>
                                                </div>
                                                <div style="font-size:0.85rem;color:#059669;font-weight:600;margin-top:2px;">
                                                    Pay only <?= $depositPct ?>% deposit (<?= money($depositNow) ?>) now · <?= money($balanceAtDesk) ?> at check-in
                                                </div>
                                                <div class="hint" style="font-size:0.78rem;color:#64748B;margin-top:2px;">
                                                    🛡️ Free cancellation up to 48 hrs before check-in (100% refund)
                                                </div>
                                            </div>
                                            <div>
                                                <?php if ($isAvail): ?>
                                                    <button type="button" class="btn btn-primary" onclick="selectRoomForBooking(<?= $room['id'] ?>, '<?= e(addslashes($room['name'])) ?>', <?= (float) $room['price_per_night'] ?>, <?= $depositPct ?>, <?= (int) ($room['max_guests'] ?? 2) ?>)">
                                                        ⚡ Select Room
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-ghost" disabled style="opacity:0.5;cursor:not-allowed;">
                                                        Unavailable
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <h2 class="mt-3">About this <?= e(strtolower($listing['category_name'])) ?></h2>
            <p class="prose"><?= nl2br(e($listing['description'])) ?></p>

            <?php if (!empty($listing['included']) || !empty($listing['not_included'])): ?>
                <div class="inclusion-box mt-3">
                    <?php if (!empty($listing['included'])): ?>
                        <div class="inclusion-col">
                            <h3>What's included</h3>
                            <ul class="inclusion-list">
                                <?php foreach (preg_split('/\r\n|\r|\n/', (string) $listing['included']) ?: [] as $line): ?>
                                    <?php if (trim($line) !== ''): ?><li><?= e(trim($line)) ?></li><?php endif; ?>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($listing['not_included'])): ?>
                        <div class="inclusion-col">
                            <h3>Not included</h3>
                            <ul class="inclusion-list inclusion-list-muted">
                                <?php foreach (preg_split('/\r\n|\r|\n/', (string) $listing['not_included']) ?: [] as $line): ?>
                                    <?php if (trim($line) !== ''): ?><li><?= e(trim($line)) ?></li><?php endif; ?>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if ($isBookable && !$isHotel): ?>
                <p class="hint mt-2">All fees listed under “Included” are covered when you pay on GuideMate. Guides must not ask for extra payment unless it is listed under “Not included”. <a href="<?= e(url('/policy')) ?>">Read our policy</a></p>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($lat && $lng): ?>
                <h2 class="mt-3">Location</h2>
                <iframe
                    title="Map"
                    width="100%" height="320" style="border:0;border-radius:var(--radius);"
                    loading="lazy"
                    src="https://www.openstreetmap.org/export/embed.html?bbox=<?= ($lng-0.02) ?>,<?= ($lat-0.02) ?>,<?= ($lng+0.02) ?>,<?= ($lat+0.02) ?>&layer=mapnik&marker=<?= e((string)$lat) ?>,<?= e((string)$lng) ?>">
                </iframe>
            <?php endif; ?>

            <!-- Reviews -->
            <h2 class="mt-3" id="reviews">Reviews</h2>
            <?php if ($summary['count'] > 0): ?>
                <div class="review-summary">
                    <div class="text-center">
                        <div class="big-score"><?= number_format($summary['avg'], 1) ?></div>
                        <div class="rating"><?= e(stars($summary['avg'])) ?></div>
                        <div class="hint"><?= $summary['count'] ?> reviews</div>
                    </div>
                    <div>
                        <?php foreach ([5,4,3,2,1] as $star):
                            $count = $summary['breakdown'][$star];
                            $pct = $summary['count'] > 0 ? round($count / $summary['count'] * 100) : 0; ?>
                            <div class="bar-row">
                                <span><?= $star ?> star</span>
                                <span class="bar"><i style="width:<?= $pct ?>%"></i></span>
                                <span><?= $count ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($canReview): ?>
                <div class="card-soft mt-2">
                    <h3>Write a review</h3>
                    <?php if (isset($errors['rating']) || isset($errors['comment'])): ?>
                        <div class="field-error"><?= e($errors['rating'] ?? $errors['comment']) ?></div>
                    <?php endif; ?>
                    <form method="post" action="<?= e(url('/listing/' . $listing['id'] . '/review')) ?>" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <div class="field-row">
                            <label>Your rating</label>
                            <div class="star-picker">
                                <input type="hidden" name="rating" value="0">
                                <?php for ($i = 0; $i < 5; $i++): ?><span class="star">★</span><?php endfor; ?>
                            </div>
                        </div>
                        <div class="field-row">
                            <label for="rtitle">Title</label>
                            <input class="input" id="rtitle" type="text" name="title" placeholder="Sum up your experience">
                        </div>
                        <div class="field-row">
                            <label for="rcomment">Comment</label>
                            <textarea class="input" id="rcomment" name="comment" rows="3" placeholder="What was great? What could be better?"></textarea>
                        </div>
                        <div class="field-row">
                            <label for="review_images">Attach photos (optional, up to 5)</label>
                            <input class="input" id="review_images" type="file" name="images[]" multiple accept="image/*">
                        </div>
                        <button class="btn btn-primary" type="submit">Submit review</button>
                    </form>
                </div>
            <?php endif; ?>

            <div class="review-list mt-3">
                <?php if ($reviews === []): ?>
                    <p class="hint">No reviews yet. Be the first to review after your stay!</p>
                <?php else: ?>
                    <?php foreach ($reviews as $r): ?>
                        <div class="review-card">
                            <div class="review-head">
                                <img src="<?= e(img_src($r['user_avatar'] ?? null, 'avatar' . $r['user_id'])) ?>" alt="">
                                <div class="meta">
                                    <strong><?= e($r['user_name']) ?></strong><br>
                                    <small><?= e(time_ago($r['created_at'])) ?></small>
                                </div>
                                <span class="rating" style="margin-left:auto;"><?= e(stars((float) $r['rating'])) ?></span>
                            </div>
                            <?php if (!empty($r['title'])): ?><strong><?= e($r['title']) ?></strong><?php endif; ?>
                            <p class="mb-0"><?= nl2br(e($r['comment'])) ?></p>
                            <?php if (!empty($r['images'])): ?>
                                <div class="review-photos" style="display:flex;gap:.5rem;margin-top:.5rem;flex-wrap:wrap;">
                                    <?php foreach ($r['images'] as $img): ?>
                                        <a href="<?= e(url('/' . ltrim((string) $img['file_path'], '/'))) ?>" target="_blank" rel="noopener">
                                            <img src="<?= e(url('/' . ltrim((string) $img['file_path'], '/'))) ?>" alt="" style="width:72px;height:72px;object-fit:cover;border-radius:8px;">
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── BOOKING SIDEBAR ── -->
        <aside>
            <div class="booking-box" id="bookingBox">
                <div class="price"><?= money($displayPrice ?? $listing['price']) ?>
                    <?php if (isset($displayPrice) && (float) $displayPrice < (float) $listing['price']): ?>
                        <s style="font-size:.85rem;color:var(--ink-300);"><?= money($listing['price']) ?></s>
                    <?php endif; ?>
                    <span style="font-size:.9rem;color:var(--ink-300);font-weight:600;">/ <?= e($listing['price_unit']) ?></span>
                </div>

                <div class="guide-mini">
                    <img src="<?= e(img_src($listing['owner_avatar'] ?? null, 'avatar' . $listing['user_id'])) ?>" alt="">
                    <div>
                        <small class="hint"><?= e($host['sidebar']) ?></small><br>
                        <strong><?= e($listing['owner_name']) ?></strong>
                    </div>
                </div>

                <?php if ($isOwner): ?>
                    <p class="hint">This is your listing.</p>
                    <a href="<?= e(url('/dashboard/listings/' . $listing['id'] . '/edit')) ?>" class="btn btn-ghost btn-block">Edit listing</a>

                <?php elseif ($isHotel): ?>
                    <!-- HOTEL BOOKING WIDGET WITH PARTIAL DEPOSIT -->
                    <div class="policy-note" style="background:#F0FDF4;border-color:#BBF7D0;">
                        <strong style="color:#166534;">🏨 Verified Hotel Reservation</strong>
                        <p class="mb-0" style="color:#15803D;">Pay a partial deposit online to secure your room. The remaining balance is paid upon check-in at the hotel.</p>
                    </div>

                    <form method="post" action="<?= e(url('/listing/' . $listing['id'] . '/book')) ?>" id="hotelBookingForm">
                        <?= csrf_field() ?>
                        
                        <div class="field-row">
                            <label for="hotel_room_id">Select Room Type</label>
                            <select class="input" id="hotel_room_id" name="room_id" required onchange="onHotelRoomChange()">
                                <?php foreach ($hotelRooms as $r): ?>
                                    <option value="<?= $r['id'] ?>" 
                                            data-price="<?= (float) $r['price_per_night'] ?>" 
                                            data-deposit="<?= (int) ($r['deposit_percentage'] ?? 20) ?>" 
                                            data-max="<?= (int) ($r['max_guests'] ?? 2) ?>" 
                                            <?= ($r['status'] ?? '') !== 'available' ? 'disabled' : '' ?>>
                                        <?= e($r['name']) ?> — <?= money($r['price_per_night']) ?>/nt <?= ($r['status'] ?? '') !== 'available' ? ' (' . e(str_replace('_', ' ', $r['status'])) . ')' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-grid-2">
                            <div class="field-row">
                                <label for="check_in_date">Check-in</label>
                                <input class="input" type="date" id="check_in_date" name="check_in_date" min="<?= date('Y-m-d') ?>" value="<?= e(old('check_in_date', date('Y-m-d', strtotime('+1 day')))) ?>" required onchange="recalculateHotelTotal()">
                                <?php if (isset($errors['check_in_date'])): ?><div class="field-error"><?= e($errors['check_in_date']) ?></div><?php endif; ?>
                            </div>
                            <div class="field-row">
                                <label for="check_out_date">Check-out</label>
                                <input class="input" type="date" id="check_out_date" name="check_out_date" min="<?= date('Y-m-d', strtotime('+2 days')) ?>" value="<?= e(old('check_out_date', date('Y-m-d', strtotime('+3 days')))) ?>" required onchange="recalculateHotelTotal()">
                                <?php if (isset($errors['check_out_date'])): ?><div class="field-error"><?= e($errors['check_out_date']) ?></div><?php endif; ?>
                            </div>
                        </div>

                        <div class="field-row">
                            <label for="hotel_guests">Guests</label>
                            <input class="input" type="number" id="hotel_guests" name="guests" min="1" max="10" value="2">
                        </div>

                        <div class="field-row">
                            <label for="hotel_notes">Special Requests (optional)</label>
                            <textarea class="input" id="hotel_notes" name="notes" rows="2" placeholder="Early arrival, high floor, quiet room..."></textarea>
                        </div>

                        <!-- Real-time calculation summary -->
                        <div class="card-soft mb-3" style="background:#F8FAFC;border:1px solid #E2E8F0;padding:0.75rem 1rem;">
                            <div class="result-bar" style="margin:0;"><span id="hotelNightsLabel">Total Stay (2 nights)</span><strong id="hotelTotalStay">₱0.00</strong></div>
                            <div class="result-bar" style="margin:4px 0 0;color:#059669;"><span>Deposit Due Today (<span id="hotelDepositPctLabel">20</span>%)</span><strong id="hotelDepositNow" style="font-size:1.15rem;color:#059669;">₱0.00</strong></div>
                            <div class="result-bar" style="margin:4px 0 0;color:#D97706;"><span>Balance at Check-in</span><strong id="hotelBalanceAtDesk" style="color:#D97706;">₱0.00</strong></div>
                        </div>

                        <button class="btn btn-primary btn-block btn-lg" type="submit" id="hotelSubmitBtn">
                            <?= $user ? 'Reserve Room (Pay Deposit)' : 'Log in to Reserve' ?>
                        </button>
                        <p class="hint mt-2 mb-0" style="font-size:0.8rem;text-align:center;">
                            🔒 Free cancellation up to 48 hours before check-in.
                        </p>
                    </form>

                    <form method="post" action="<?= e(url('/listing/' . $listing['id'] . '/contact')) ?>" class="mt-2">
                        <?= csrf_field() ?>
                        <button class="btn btn-ghost btn-block" type="submit">💬 Message Hotel Partner</button>
                    </form>

                <?php elseif ($isBookable): ?>
                    <!-- TOUR GUIDE BOOKING FORM -->
                    <div class="policy-note">
                        <strong>Protected booking</strong>
                        <p class="mb-0">Pay on GuideMate — your <?= e($host['noun']) ?> must not ask for extra money unless it was listed as “Not included” before you booked.</p>
                    </div>
                    <form method="post" action="<?= e(url('/listing/' . $listing['id'] . '/book')) ?>" id="bookingForm" data-price="<?= e((string) ($displayPrice ?? $listing['price'])) ?>" data-logged-in="<?= $user ? '1' : '0' ?>" data-login-url="<?= e(url('/login?redirect=' . rawurlencode('/listing/' . ($listing['slug'] ?? $listing['id'])))) ?>">
                        <?= csrf_field() ?>
                        <div class="field-row">
                            <label for="booking_date">Date</label>
                            <input class="input" type="date" id="booking_date" name="booking_date" min="<?= date('Y-m-d') ?>" value="<?= e(old('booking_date')) ?>" required data-booked="<?= e(implode(',', $bookedDates ?? [])) ?>">
                            <?php if (isset($errors['booking_date'])): ?><div class="field-error"><?= e($errors['booking_date']) ?></div><?php endif; ?>
                            <?php if (!empty($bookedDates)): ?><span class="hint">Some dates are already booked and can't be selected.</span><?php endif; ?>
                        </div>
                        <div class="field-row">
                            <label for="guests">Guests</label>
                            <input class="input" type="number" id="guests" name="guests" min="1" max="50" value="1">
                        </div>
                        <div class="field-row">
                            <label for="notes">Notes (optional)</label>
                            <textarea class="input" id="notes" name="notes" rows="2" placeholder="Any special requests?"></textarea>
                        </div>
                        <div class="result-bar" style="margin:0 0 1rem;">
                            <span>Total</span>
                            <strong id="bookingTotal" style="font-size:1.2rem;color:var(--teal-900);"><?= money($displayPrice ?? $listing['price']) ?></strong>
                        </div>
                        <button class="btn btn-primary btn-block btn-lg" type="submit"><?= $user ? 'Book now' : 'Log in to book' ?></button>
                        <p class="hint mt-2 mb-0">Payment holds the date. The <?= e($host['noun']) ?> must still confirm the booking.</p>
                    </form>

                    <form method="post" action="<?= e(url('/listing/' . $listing['id'] . '/contact')) ?>" class="mt-2">
                        <?= csrf_field() ?>
                        <button class="btn btn-ghost btn-block" type="submit">💬 <?= e($host['message']) ?></button>
                    </form>
                <?php else: ?>
                    <p class="hint">Inquire with the <?= e($host['noun']) ?> for availability.</p>
                    <?php if ($user): ?>
                        <form method="post" action="<?= e(url('/listing/' . $listing['id'] . '/contact')) ?>">
                            <?= csrf_field() ?>
                            <button class="btn btn-primary btn-block btn-lg" type="submit">💬 <?= e($host['message']) ?></button>
                        </form>
                    <?php else: ?>
                        <a class="btn btn-primary btn-block btn-lg" href="<?= e(url('/login?redirect=' . rawurlencode('/listing/' . ($listing['slug'] ?? $listing['id'])))) ?>">💬 <?= e($host['message']) ?></a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </aside>
    </div>
</div>
<div style="height:3rem;"></div>

<script>
// Select a room from the available rooms cards and sync with sidebar widget
function selectRoomForBooking(roomId, roomName, price, depositPct, maxGuests) {
    var select = document.getElementById('hotel_room_id');
    if (select) {
        select.value = roomId;
    }
    var guestsInput = document.getElementById('hotel_guests');
    if (guestsInput && maxGuests) {
        guestsInput.max = maxGuests;
    }
    recalculateHotelTotal();
    var box = document.getElementById('bookingBox');
    if (box) {
        box.scrollIntoView({ behavior: 'smooth', block: 'center' });
        box.style.transition = 'outline 0.3s';
        box.style.outline = '3px solid #10B981';
        setTimeout(function() { box.style.outline = ''; }, 1500);
    }
}

function onHotelRoomChange() {
    recalculateHotelTotal();
}

function recalculateHotelTotal() {
    var roomSelect = document.getElementById('hotel_room_id');
    var inDate = document.getElementById('check_in_date');
    var outDate = document.getElementById('check_out_date');
    if (!roomSelect || !inDate || !outDate) return;

    var selectedOpt = roomSelect.options[roomSelect.selectedIndex];
    if (!selectedOpt) return;

    var pricePerNight = parseFloat(selectedOpt.getAttribute('data-price') || 0);
    var depositPct = parseInt(selectedOpt.getAttribute('data-deposit') || 20, 10);

    var dIn = new Date(inDate.value + 'T00:00:00');
    var dOut = new Date(outDate.value + 'T00:00:00');

    var nights = 1;
    if (dOut > dIn) {
        nights = Math.max(1, Math.round((dOut - dIn) / (1000 * 60 * 60 * 24)));
    }

    var totalStay = pricePerNight * nights;
    var depositNow = Math.round((totalStay * depositPct) / 100);
    var balanceAtDesk = totalStay - depositNow;

    var lblNights = document.getElementById('hotelNightsLabel');
    if (lblNights) lblNights.textContent = 'Total Stay (' + nights + ' night' + (nights > 1 ? 's' : '') + ')';

    var lblPct = document.getElementById('hotelDepositPctLabel');
    if (lblPct) lblPct.textContent = depositPct;

    var totalEl = document.getElementById('hotelTotalStay');
    if (totalEl) totalEl.textContent = '₱' + totalStay.toLocaleString('en-US', { minimumFractionDigits: 2 });

    var depEl = document.getElementById('hotelDepositNow');
    if (depEl) depEl.textContent = '₱' + depositNow.toLocaleString('en-US', { minimumFractionDigits: 2 });

    var balEl = document.getElementById('hotelBalanceAtDesk');
    if (balEl) balEl.textContent = '₱' + balanceAtDesk.toLocaleString('en-US', { minimumFractionDigits: 2 });

    var submitBtn = document.getElementById('hotelSubmitBtn');
    if (submitBtn && <?= $user ? 'true' : 'false' ?>) {
        submitBtn.textContent = 'Reserve Room (Pay ₱' + depositNow.toLocaleString('en-US') + ' Deposit)';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    recalculateHotelTotal();
});

(function () {
    var form = document.getElementById('bookingForm');
    if (!form) { return; }

    var dateInput = document.getElementById('booking_date');
    var guestsInput = document.getElementById('guests');
    var loggedIn = form.getAttribute('data-logged-in') === '1';
    var loginUrl = form.getAttribute('data-login-url') || '';

    var booked = (dateInput && dateInput.getAttribute('data-booked') || '')
        .split(',').map(function (s) { return s.trim(); }).filter(Boolean);

    function showError(el, msg) {
        if (!el) { return; }
        el.style.outline = '2px solid #DC2626';
        var next = el.parentNode.querySelector('.js-field-error');
        if (!next) {
            next = document.createElement('div');
            next.className = 'field-error js-field-error';
            el.parentNode.appendChild(next);
        }
        next.textContent = msg;
    }

    function clearError(el) {
        if (!el) { return; }
        el.style.outline = '';
        var next = el.parentNode.querySelector('.js-field-error');
        if (next) { next.remove(); }
    }

    function validate() {
        var ok = true;
        clearError(dateInput);
        clearError(guestsInput);

        var val = dateInput ? dateInput.value : '';
        if (!val) {
            showError(dateInput, 'Please choose a date.');
            ok = false;
        } else {
            var today = new Date(); today.setHours(0, 0, 0, 0);
            var chosen = new Date(val + 'T00:00:00');
            if (chosen < today) {
                showError(dateInput, 'Please choose a date in the future.');
                ok = false;
            } else if (booked.indexOf(val) !== -1) {
                showError(dateInput, 'That date is already booked. Please pick another.');
                ok = false;
            }
        }

        var guests = guestsInput ? parseInt(guestsInput.value, 10) : 1;
        if (!guests || guests < 1) {
            showError(guestsInput, 'Please enter at least 1 guest.');
            ok = false;
        }
        return ok;
    }

    form.addEventListener('submit', function (e) {
        if (!validate()) {
            e.preventDefault();
            var firstError = form.querySelector('.js-field-error');
            if (firstError) { firstError.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
            return;
        }
        if (!loggedIn && loginUrl) {
            e.preventDefault();
            window.location.href = loginUrl;
        }
    });
})();
</script>
