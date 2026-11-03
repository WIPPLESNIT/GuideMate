<?php
/**
 * Admin Guide Listing Inspection
 *
 * @var array<string,mixed> $listing
 * @var array<string,mixed>|null $guide
 * @var array<string,mixed>|null $reviewer
 * @var array<int,array<string,mixed>> $schedules
 * @var array<int,array<string,mixed>> $gallery
 */
$lStatus = (string) ($listing['status'] ?? 'pending');
$amenities = !empty($listing['included']) ? array_map('trim', explode(',', (string) $listing['included'])) : [];
$notIncluded = !empty($listing['not_included']) ? array_map('trim', explode(',', (string) $listing['not_included'])) : [];
$thumb = !empty($listing['cover_image']) ? $listing['cover_image'] : 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=400&q=80';
$isAttraction = ($listing['category_slug'] ?? '') === 'attractions' || ($listing['category_slug'] ?? '') === 'attraction-and-tours';
$cap = (int) ($listing['total_capacity'] ?? $listing['capacity'] ?? 10);
?>
<div style="margin-bottom:1.5rem;">
    <a href="<?= e(url('/admin/guide-listings')) ?>" class="btn btn-ghost btn-sm" style="margin-bottom:0.75rem;">← Back to Guide Listings Review</a>
    <div class="section-head" style="margin-bottom:0.25rem;">
        <div>
            <div style="display:flex;align-items:center;gap:0.6rem;margin-bottom:0.35rem;">
                <h1 class="mb-0"><?= e($listing['title']) ?></h1>
                <?php if ($isAttraction): ?>
                    <span class="pill" style="font-size:0.78rem;padding:3px 10px;background:rgba(236,72,153,0.18);color:#f472b6;border:1px solid rgba(236,72,153,0.35);font-weight:700;border-radius:20px;">
                        Attraction
                    </span>
                <?php else: ?>
                    <span class="pill" style="font-size:0.78rem;padding:3px 10px;background:rgba(16,185,129,0.18);color:#34d399;border:1px solid rgba(16,185,129,0.35);font-weight:700;border-radius:20px;">
                        Things to Do
                    </span>
                <?php endif; ?>
            </div>
            <p class="hint mb-0">Submitted by <?= e($guide['name'] ?? 'Tour Guide') ?> (<?= e($guide['email'] ?? '') ?>)</p>
        </div>
        <div>
            <span class="pill <?= e(\App\Models\Listing::hotelStatusPillClass($lStatus)) ?>" style="font-size:0.95rem;padding:0.4rem 0.8rem;">
                <?= e(\App\Models\Listing::hotelStatusLabel($lStatus)) ?>
            </span>
        </div>
    </div>
</div>

<?php if ($lStatus === 'rejected' && !empty($listing['rejection_reason'])): ?>
    <div class="status-banner banner-red" style="margin-bottom:1.5rem;">
        <span class="status-icon"><?= admin_icon('disputes', 24) ?></span>
        <div>
            <strong>Rejection Reason Recorded:</strong>
            <p style="margin:0.25rem 0 0;font-size:0.95rem;">
                <?= nl2br(e($listing['rejection_reason'])) ?>
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
                    <img src="<?= e(img_src($thumb, 'listing-' . $listing['id'])) ?>" alt="" style="width:100%;max-height:420px;object-fit:cover;border-radius:8px;border:1px solid var(--border);">
                </div>

                <?php if ($gallery !== []): ?>
                    <h4 style="margin:0 0 0.75rem 0;">Photo Gallery</h4>
                    <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(130px, 1fr));gap:0.75rem;margin-bottom:1.5rem;">
                        <?php foreach ($gallery as $img): ?>
                            <a href="<?= e(url($img['image_path'])) ?>" target="_blank" rel="noopener">
                                <img src="<?= e(url($img['image_path'])) ?>" alt="" style="width:100%;height:95px;object-fit:cover;border-radius:6px;border:1px solid var(--border);">
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Key Highlights Grid -->
                <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(160px, 1fr));gap:0.85rem;margin-bottom:1.5rem;padding:1rem;background:rgba(255,255,255,0.02);border:1px solid var(--border);border-radius:8px;">
                    <div>
                        <div class="hint" style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.5px;">Location / Area</div>
                        <strong style="font-size:0.95rem;color:#f8fafc;"><?= e($listing['area'] ?? 'Cebu') ?></strong>
                    </div>
                    <div>
                        <div class="hint" style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.5px;">Rate &amp; Unit</div>
                        <strong style="font-size:0.95rem;color:var(--brand, #10B981);">
                            <?= (float) ($listing['price'] ?? 0) > 0 ? money((float) $listing['price']) : 'Free' ?>
                            <small class="hint">/ <?= e($listing['price_unit'] ?? 'person') ?></small>
                        </strong>
                    </div>
                    <div>
                        <div class="hint" style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.5px;">Capacity / Slots</div>
                        <strong style="font-size:0.95rem;color:#f8fafc;">Up to <?= $cap ?> slots</strong>
                    </div>
                    <div>
                        <div class="hint" style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.5px;">Duration</div>
                        <strong style="font-size:0.95rem;color:#f8fafc;"><?= e($listing['duration'] ?? 'Flexible') ?></strong>
                    </div>
                </div>

                <?php if (!empty($listing['summary'])): ?>
                    <div style="margin-bottom:1.25rem;font-size:1.05rem;line-height:1.6;color:var(--text);">
                        <?= e($listing['summary']) ?>
                    </div>
                <?php endif; ?>

                <h3 class="admin-vehicle-section-title" style="margin:1.25rem 0 0;border-bottom:1px solid rgba(255,255,255,.08);padding-bottom:0.45rem;">Experience Description</h3>
                <div class="admin-vehicle-desc" style="line-height:1.7;color:#ffffff;white-space:pre-line;word-break:break-word;overflow-wrap:break-word;max-width:100%;margin:0.5rem 0 1.5rem 0;text-align:left;">
                    <?php if (!empty(trim((string) ($listing['description'] ?? '')))): ?>
                        <?= e(trim((string) $listing['description'])) ?>
                    <?php else: ?>
                        <em class="admin-vehicle-desc-empty" style="color:rgba(255,255,255,0.45);font-style:italic;">No description provided.</em>
                    <?php endif; ?>
                </div>

                <?php if (!empty($listing['meeting_point']) || !empty($listing['address'])): ?>
                    <h3 class="admin-vehicle-section-title" style="margin:1.5rem 0 0.75rem;border-bottom:1px solid rgba(255,255,255,.08);padding-bottom:0.5rem;">Meeting Point &amp; Departure</h3>
                    <div style="padding:0.85rem 1rem;background:rgba(255,255,255,0.03);border:1px solid var(--border);border-radius:8px;margin-bottom:1.5rem;">
                        <?php if (!empty($listing['meeting_point'])): ?>
                            <div style="font-weight:600;color:#f8fafc;">📍 <?= e($listing['meeting_point']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($listing['address'])): ?>
                            <div class="hint" style="font-size:0.85rem;margin-top:4px;"><?= e($listing['address']) ?></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($amenities !== []): ?>
                    <h3 class="admin-vehicle-section-title" style="margin:1.5rem 0 0.75rem;border-bottom:1px solid rgba(255,255,255,.08);padding-bottom:0.5rem;">What's Included</h3>
                    <div style="display:flex;flex-wrap:wrap;gap:0.5rem;margin-bottom:1.5rem;">
                        <?php foreach ($amenities as $a): ?>
                            <span style="background:rgba(255,255,255,0.06);color:#e2e8f0;padding:0.35rem 0.75rem;border-radius:20px;font-size:0.875rem;border:1px solid var(--border);">
                                ✓ <?= e($a) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($notIncluded !== []): ?>
                    <h3 class="admin-vehicle-section-title" style="margin:1.5rem 0 0.75rem;border-bottom:1px solid rgba(255,255,255,.08);padding-bottom:0.5rem;">Not Included / House Rules</h3>
                    <div style="display:flex;flex-wrap:wrap;gap:0.5rem;margin-bottom:1.5rem;">
                        <?php foreach ($notIncluded as $ni): ?>
                            <span style="background:rgba(239,68,68,0.1);color:#fca5a5;padding:0.35rem 0.75rem;border-radius:20px;font-size:0.875rem;border:1px solid rgba(239,68,68,0.25);">
                                ✕ <?= e($ni) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Configured Tour Schedules & Sessions -->
                <h3 class="admin-vehicle-section-title" style="margin:1.5rem 0 0.75rem;border-bottom:1px solid rgba(255,255,255,.08);padding-bottom:0.5rem;">
                    Configured Tour Sessions (<?= count($schedules) ?>)
                </h3>
                <?php if ($schedules === []): ?>
                    <p class="hint" style="font-size:0.875rem;margin:0.5rem 0;">No predefined session dates configured yet. This experience operates on flexible scheduling.</p>
                <?php else: ?>
                    <div style="display:flex;flex-direction:column;gap:0.75rem;margin-top:0.75rem;">
                        <?php foreach ($schedules as $s):
                            $sCap = (int) (!empty($s['capacity']) ? $s['capacity'] : $cap);
                            $timeStr = !empty($s['start_time']) ? date('g:i A', strtotime((string) $s['start_time'])) . (!empty($s['end_time']) ? ' – ' . date('g:i A', strtotime((string) $s['end_time'])) : '') : 'Flexible timing';
                        ?>
                            <div style="padding:0.75rem 1rem;background:rgba(255,255,255,0.03);border:1px solid var(--border);border-radius:8px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.5rem;">
                                <div>
                                    <div style="display:flex;align-items:center;gap:0.5rem;">
                                        <strong>🗓 <?= e(date('D, M j, Y', strtotime((string) $s['schedule_date']))) ?></strong>
                                        <span class="pill" style="font-size:0.7rem;padding:2px 6px;background:rgba(16,185,129,0.15);color:#34d399;">
                                            <?= $timeStr ?>
                                        </span>
                                    </div>
                                    <?php if (!empty($s['note'])): ?>
                                        <div class="hint" style="font-size:0.8rem;margin-top:3px;"><?= e($s['note']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div style="text-align:right;">
                                    <strong style="color:#f8fafc;font-size:0.9rem;"><?= $sCap ?> max slots</strong>
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
                <?php if ($lStatus !== 'approved'): ?>
                    <form method="post" action="<?= e(url('/admin/guide-listings/' . (int) $listing['id'] . '/approve')) ?>" style="margin-bottom:1rem;" onsubmit="return confirm('Approve this guide listing? It will immediately become visible to travelers.');">
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

                <form method="post" action="<?= e(url('/admin/guide-listings/' . (int) $listing['id'] . '/reject')) ?>" onsubmit="return confirm('Confirm rejection of this guide listing?');">
                    <?= csrf_field() ?>
                    <label for="side_reason" class="hint" style="display:block;margin-bottom:0.4rem;font-weight:600;">
                        <?= $lStatus === 'approved' ? 'Unpublish / Reject Reason' : 'Rejection Reason (optional)' ?>
                    </label>
                    <textarea class="input" id="side_reason" name="reason" rows="3" placeholder="Provide notes or feedback for the guide..."></textarea>
                    <button type="submit" class="btn btn-ghost" style="width:100%;margin-top:0.5rem;color:var(--coral-600);justify-content:center;">
                        <?= $lStatus === 'approved' ? 'Unpublish Listing' : 'Reject Listing' ?>
                    </button>
                </form>
            </div>
        </div>

        <!-- Guide Information Box -->
        <div class="panel" style="margin-bottom:1.5rem;">
            <div class="panel-head">
                <h3 style="margin:0;">Guide Information</h3>
            </div>
            <div class="panel-body" style="font-size:0.875rem;">
                <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:1rem;">
                    <?php if (!empty($guide['avatar'])): ?>
                        <img src="<?= e(url($guide['avatar'])) ?>" alt="" style="width:48px;height:48px;border-radius:50%;object-fit:cover;border:1px solid var(--border);">
                    <?php else: ?>
                        <div style="width:48px;height:48px;border-radius:50%;background:var(--brand);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1.1rem;">
                            <?= strtoupper(substr((string) ($guide['name'] ?? 'G'), 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                    <div>
                        <strong style="font-size:1rem;color:#f8fafc;"><?= e($guide['name'] ?? 'Tour Guide') ?></strong>
                        <div class="hint"><?= e($guide['email'] ?? '') ?></div>
                    </div>
                </div>

                <div style="display:flex;flex-direction:column;gap:0.5rem;border-top:1px solid rgba(255,255,255,0.08);padding-top:0.75rem;">
                    <div>
                        <span class="hint">Role:</span>
                        <strong style="color:#f8fafc;"><?= e(ucfirst((string) ($guide['role'] ?? 'guide'))) ?></strong>
                    </div>
                    <?php if (!empty($guide['phone'])): ?>
                        <div>
                            <span class="hint">Phone:</span>
                            <span style="color:#f8fafc;"><?= e($guide['phone']) ?></span>
                        </div>
                    <?php endif; ?>
                    <div>
                        <span class="hint">Member since:</span>
                        <span><?= !empty($guide['created_at']) ? e(date('M j, Y', strtotime((string) $guide['created_at']))) : '—' ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Audit & Tracking Box -->
        <div class="panel">
            <div class="panel-head">
                <h3 style="margin:0;">Audit Information</h3>
            </div>
            <div class="panel-body" style="font-size:0.85rem;display:flex;flex-direction:column;gap:0.6rem;">
                <div>
                    <span class="hint">Listing ID:</span>
                    <strong>#<?= (int) $listing['id'] ?></strong>
                </div>
                <div>
                    <span class="hint">Created:</span>
                    <?= e(date('M j, Y g:ia', strtotime((string) $listing['created_at']))) ?>
                </div>
                <?php if (!empty($listing['submitted_at'])): ?>
                    <div>
                        <span class="hint">Submitted for review:</span>
                        <?= e(date('M j, Y g:ia', strtotime((string) $listing['submitted_at']))) ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($listing['reviewed_at'])): ?>
                    <div>
                        <span class="hint">Reviewed:</span>
                        <?= e(date('M j, Y g:ia', strtotime((string) $listing['reviewed_at']))) ?>
                    </div>
                    <?php if ($reviewer !== null): ?>
                        <div>
                            <span class="hint">Reviewed by:</span>
                            <strong><?= e($reviewer['name']) ?></strong> (<?= e($reviewer['email']) ?>)
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
