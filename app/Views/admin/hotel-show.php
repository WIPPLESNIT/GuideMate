<?php
/**
 * Admin Hotel Listing Inspection
 *
 * @var array<string,mixed> $hotel
 * @var array<string,mixed>|null $provider
 * @var array<string,mixed>|null $reviewer
 * @var array<int,array<string,mixed>> $rooms
 * @var array<int,array<string,mixed>> $gallery
 */
$hStatus = (string) ($hotel['status'] ?? 'pending');
$amenities = !empty($hotel['included']) ? array_map('trim', explode(',', (string) $hotel['included'])) : [];
$notIncluded = !empty($hotel['not_included']) ? array_map('trim', explode(',', (string) $hotel['not_included'])) : [];
$thumb = !empty($hotel['cover_image']) ? $hotel['cover_image'] : 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=400&q=80';
?>
<div style="margin-bottom:1.5rem;">
    <a href="<?= e(url('/admin/hotels')) ?>" class="btn btn-ghost btn-sm" style="margin-bottom:0.75rem;">← Back to Hotel Reviews</a>
    <div class="section-head" style="margin-bottom:0.25rem;">
        <div>
            <h1 class="mb-0"><?= e($hotel['title']) ?></h1>
            <p class="hint mb-0">Submitted by <?= e($provider['name'] ?? 'Hotel Partner') ?> (<?= e($provider['email'] ?? '') ?>)</p>
        </div>
        <div>
            <span class="pill <?= e(\App\Models\Listing::hotelStatusPillClass($hStatus)) ?>" style="font-size:0.95rem;padding:0.4rem 0.8rem;">
                <?= e(\App\Models\Listing::hotelStatusLabel($hStatus)) ?>
            </span>
        </div>
    </div>
</div>

<?php if ($hStatus === 'rejected' && !empty($hotel['rejection_reason'])): ?>
    <div class="status-banner banner-red" style="margin-bottom:1.5rem;">
        <span class="status-icon"><?= admin_icon('disputes', 24) ?></span>
        <div>
            <strong>Rejection Reason Recorded:</strong>
            <p style="margin:0.25rem 0 0;font-size:0.95rem;">
                <?= nl2br(e($hotel['rejection_reason'])) ?>
            </p>
        </div>
    </div>
<?php endif; ?>

<div class="admin-vehicle-layout" style="display:grid;grid-template-columns:1fr 340px;gap:1.5rem;align-items:start;">
    <!-- Main Details Column -->
    <div class="admin-vehicle-main" style="min-width:0;">
        <div class="panel" style="margin-bottom:1.5rem;">
            <div class="panel-body">
                <div style="margin-bottom:1.5rem;">
                    <img src="<?= e(img_src($thumb, 'hotel-' . $hotel['id'])) ?>" alt="" style="width:100%;max-height:420px;object-fit:cover;border-radius:8px;border:1px solid var(--border);">
                </div>

                <?php if ($gallery !== []): ?>
                    <h4 style="margin:0 0 0.75rem 0;">Property Photos &amp; Gallery</h4>
                    <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(130px, 1fr));gap:0.75rem;margin-bottom:1.5rem;">
                        <?php foreach ($gallery as $img): ?>
                            <a href="<?= e(url($img['image_path'])) ?>" target="_blank" rel="noopener">
                                <img src="<?= e(url($img['image_path'])) ?>" alt="" style="width:100%;height:95px;object-fit:cover;border-radius:6px;border:1px solid var(--border);">
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($hotel['summary'])): ?>
                    <div style="margin-bottom:1.25rem;font-size:1.05rem;line-height:1.6;color:var(--text);">
                        <?= e($hotel['summary']) ?>
                    </div>
                <?php endif; ?>

                <h3 class="admin-vehicle-section-title" style="margin:1.25rem 0 0;border-bottom:1px solid rgba(255,255,255,.08);padding-bottom:0.45rem;">Property Description</h3>
                <div class="admin-vehicle-desc" style="line-height:1.7;color:#ffffff;white-space:pre-line;word-break:break-word;overflow-wrap:break-word;max-width:100%;margin:0.5rem 0 1.5rem 0;text-align:left;">
                    <?php if (!empty(trim((string) ($hotel['description'] ?? '')))): ?>
                        <?= e(trim((string) $hotel['description'])) ?>
                    <?php else: ?>
                        <em class="admin-vehicle-desc-empty" style="color:rgba(255,255,255,0.45);font-style:italic;">No description provided.</em>
                    <?php endif; ?>
                </div>

                <?php if ($amenities !== []): ?>
                    <h3 class="admin-vehicle-section-title" style="margin:1.5rem 0 0.75rem;border-bottom:1px solid rgba(255,255,255,.08);padding-bottom:0.5rem;">Included Amenities</h3>
                    <div style="display:flex;flex-wrap:wrap;gap:0.5rem;margin-bottom:1.5rem;">
                        <?php foreach ($amenities as $a): ?>
                            <span style="background:rgba(255,255,255,0.06);color:#e2e8f0;padding:0.35rem 0.75rem;border-radius:20px;font-size:0.875rem;border:1px solid var(--border);">
                                ✓ <?= e($a) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($notIncluded !== []): ?>
                    <h3 class="admin-vehicle-section-title" style="margin:1.5rem 0 0.75rem;border-bottom:1px solid rgba(255,255,255,.08);padding-bottom:0.5rem;">House Rules / Not Included</h3>
                    <div style="display:flex;flex-wrap:wrap;gap:0.5rem;margin-bottom:1.5rem;">
                        <?php foreach ($notIncluded as $ni): ?>
                            <span style="background:rgba(239,68,68,0.1);color:#fca5a5;padding:0.35rem 0.75rem;border-radius:20px;font-size:0.875rem;border:1px solid rgba(239,68,68,0.25);">
                                ✕ <?= e($ni) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <h3 class="admin-vehicle-section-title" style="margin:1.5rem 0 0.75rem;border-bottom:1px solid rgba(255,255,255,.08);padding-bottom:0.5rem;">
                    Configured Room Types (<?= count($rooms) ?>)
                </h3>
                <?php if ($rooms === []): ?>
                    <p class="hint" style="font-size:0.875rem;margin:0.5rem 0;">No room types configured yet for this property.</p>
                <?php else: ?>
                    <div style="display:flex;flex-direction:column;gap:0.75rem;margin-top:0.75rem;">
                        <?php foreach ($rooms as $r): ?>
                            <div style="padding:0.75rem 1rem;background:rgba(255,255,255,0.03);border:1px solid var(--border);border-radius:8px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.5rem;">
                                <div>
                                    <strong><?= e($r['name']) ?></strong>
                                    <div class="hint" style="font-size:0.8rem;margin-top:2px;">
                                        <?= e($r['bed_type']) ?> · Up to <?= (int) $r['capacity'] ?> guests<?php if (!empty($r['room_size'])): ?> · <?= e($r['room_size']) ?><?php endif; ?>
                                    </div>
                                </div>
                                <div style="text-align:right;">
                                    <strong style="color:var(--brand, #059669);"><?= money((float) $r['price_per_night']) ?></strong>
                                    <small class="hint">/ night</small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Sidebar / Audit & Decision Column -->
    <div class="admin-vehicle-sidebar" style="min-width:0;">
        <!-- Decision Box -->
        <div class="panel" style="margin-bottom:1.5rem;">
            <div class="panel-head">
                <h3 style="margin:0;">Review Decision</h3>
            </div>
            <div class="panel-body">
                <?php if ($hStatus !== 'approved'): ?>
                    <form method="post" action="<?= e(url('/admin/hotels/' . (int) $hotel['id'] . '/approve')) ?>" style="margin-bottom:1rem;" onsubmit="return confirm('Approve this hotel listing? It will immediately become visible to travelers.');">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">
                            ✓ Approve Listing
                        </button>
                    </form>
                <?php else: ?>
                    <div style="background:#ecfdf5;color:#065f46;padding:0.75rem 1rem;border-radius:6px;font-size:0.875rem;margin-bottom:1rem;border:1px solid #a7f3d0;">
                        ✓ This listing is currently <strong>Approved</strong> and published for travelers.
                    </div>
                <?php endif; ?>

                <form method="post" action="<?= e(url('/admin/hotels/' . (int) $hotel['id'] . '/reject')) ?>" onsubmit="return confirm('Confirm rejection of this hotel listing?');">
                    <?= csrf_field() ?>
                    <label for="side_reason" class="hint" style="display:block;margin-bottom:0.4rem;font-weight:600;">
                        <?= $hStatus === 'approved' ? 'Unpublish / Reject Reason' : 'Rejection Reason (optional)' ?>
                    </label>
                    <textarea class="input" id="side_reason" name="reason" rows="3" placeholder="Provide notes or feedback for the hotel partner..."></textarea>
                    <button type="submit" class="btn btn-ghost" style="width:100%;margin-top:0.5rem;color:var(--coral-600);justify-content:center;">
                        <?= $hStatus === 'approved' ? 'Unpublish Listing' : 'Reject Listing' ?>
                    </button>
                </form>
            </div>
        </div>

        <!-- Hotel Quick Summary -->
        <div class="panel" style="margin-bottom:1.5rem;">
            <div class="panel-head"><h3 style="margin:0;">Hotel Summary</h3></div>
            <div class="panel-body" style="font-size:0.9rem;">
                <div style="display:flex;justify-content:space-between;padding:0.4rem 0;border-bottom:1px solid var(--border);">
                    <span class="hint">Location</span>
                    <strong><?= e($hotel['area']) ?></strong>
                </div>
                <div style="display:flex;justify-content:space-between;padding:0.4rem 0;border-bottom:1px solid var(--border);">
                    <span class="hint">Starting Rate</span>
                    <strong style="color:var(--primary);font-size:1.05rem;"><?= money((float) $hotel['price']) ?> / night</strong>
                </div>
                <div style="display:flex;justify-content:space-between;padding:0.4rem 0;border-bottom:1px solid var(--border);">
                    <span class="hint">Room Types</span>
                    <strong><?= count($rooms) ?> configured</strong>
                </div>
                <?php if (!empty($hotel['address'])): ?>
                    <div style="padding:0.4rem 0;border-bottom:1px solid var(--border);">
                        <span class="hint" style="display:block;">Full Address</span>
                        <strong><?= e($hotel['address']) ?></strong>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Accommodation Partner Info -->
        <div class="panel" style="margin-bottom:1.5rem;">
            <div class="panel-head"><h3 style="margin:0;">Accommodation Partner</h3></div>
            <div class="panel-body" style="font-size:0.9rem;">
                <div style="font-weight:700;font-size:1rem;margin-bottom:0.25rem;"><?= e($provider['name'] ?? 'Hotel Partner') ?></div>
                <div class="hint" style="margin-bottom:0.25rem;">✉️ <?= e($provider['email'] ?? '') ?></div>
                <?php if (!empty($provider['phone'])): ?>
                    <div class="hint" style="margin-bottom:0.5rem;">📞 <?= e($provider['phone']) ?></div>
                <?php endif; ?>
                <?php if ($provider !== null): ?>
                    <div style="margin-top:0.75rem;">
                        <a href="<?= e(url('/admin/users/' . (int) $provider['id'] . '/edit')) ?>" class="btn btn-ghost btn-sm" style="width:100%;justify-content:center;">
                            View Partner Profile
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Audit Trail -->
        <div class="panel">
            <div class="panel-head"><h3 style="margin:0;">Audit Trail</h3></div>
            <div class="panel-body" style="font-size:0.875rem;">
                <div style="margin-bottom:0.5rem;">
                    <span class="hint">Submitted:</span>
                    <div><?= !empty($hotel['submitted_at']) ? e(date('M j, Y g:ia', strtotime((string) $hotel['submitted_at']))) : e(date('M j, Y g:ia', strtotime((string) $hotel['created_at']))) ?></div>
                </div>
                <?php if (!empty($hotel['reviewed_at'])): ?>
                    <div>
                        <span class="hint">Reviewed:</span>
                        <div><?= e(date('M j, Y g:ia', strtotime((string) $hotel['reviewed_at']))) ?></div>
                        <?php if ($reviewer !== null): ?>
                            <div class="hint">by <?= e($reviewer['name']) ?></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
