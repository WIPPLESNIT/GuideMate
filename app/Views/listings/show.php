<?php
/**
 * @var array<string,mixed> $listing
 * @var array<int,array<string,mixed>> $gallery
 * @var array<int,array<string,mixed>> $reviews
 * @var array{avg:float,count:int,breakdown:array<int,int>} $summary
 * @var bool $canReview @var bool $isFavorited
 * @var array<string,string> $errors
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
                    <a class="btn btn-ghost btn-sm" href="https://www.google.com/maps/dir/?api=1&destination=<?= e((string) $lat) ?>,<?= e((string) $lng) ?>" target="_blank" rel="noopener">🚗 Check traffic to meeting point</a>
                </p>
            <?php endif; ?>

            <div class="chips">
                <span class="chip">Hosted by <?= e($listing['owner_name']) ?></span>
                <?php if ($canSeeMapLocation && !empty($listing['address'])): ?><span class="chip">🗺️ <?= e($listing['address']) ?></span><?php endif; ?>
            </div>

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
                <?php if ($isBookable): ?>
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
                            <label for="rcomment">Review</label>
                            <textarea class="input" id="rcomment" name="comment" placeholder="Tell other travelers about it..."></textarea>
                        </div>
                        <div class="field-row">
                            <label for="photo">Photo (optional)</label>
                            <input class="input" id="photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp">
                        </div>
                        <button class="btn btn-primary" type="submit">Post review</button>
                    </form>
                </div>
            <?php endif; ?>

            <div class="mt-3">
                <?php if ($reviews === []): ?>
                    <p class="hint">No reviews yet — be the first to share your experience after a visit.</p>
                <?php else: ?>
                    <?php foreach ($reviews as $r): ?>
                        <div class="review">
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

        <!-- Booking sidebar -->
        <aside>
            <div class="booking-box">
                <div class="price"><?= money($displayPrice ?? $listing['price']) ?>
                    <?php if (isset($displayPrice) && (float) $displayPrice < (float) $listing['price']): ?>
                        <s style="font-size:.85rem;color:var(--ink-300);"><?= money($listing['price']) ?></s>
                    <?php endif; ?>
                    <span style="font-size:.9rem;color:var(--ink-300);font-weight:600;">/ <?= e($listing['price_unit']) ?></span></div>

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
                <?php elseif ($isBookable): ?>
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
                    <p class="hint">Inquire with the <?= e($host['noun']) ?> for availability. Bookings on GuideMate are for tour guides.</p>
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
        // Always validate first.
        if (!validate()) {
            e.preventDefault();
            var firstError = form.querySelector('.js-field-error');
            if (firstError) { firstError.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
            return;
        }
        // Form is valid but the visitor is not signed in: send them to login,
        // then bring them right back to this listing to complete the booking.
        if (!loggedIn && loginUrl) {
            e.preventDefault();
            window.location.href = loginUrl;
        }
    });
})();
</script>
