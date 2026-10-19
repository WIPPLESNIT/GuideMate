<?php
/**
 * @var array<string,mixed> $vehicle
 * @var array<int,array<string,mixed>> $gallery
 * @var array<int,string> $specs
 */
$vStatus = (string) ($vehicle['status'] ?? 'pending');
?>
<div style="margin-bottom:1.5rem;">
    <a href="<?= e(url('/admin/vehicles')) ?>" class="btn btn-ghost btn-sm" style="margin-bottom:0.75rem;">← Back to Vehicle Reviews</a>
    <div class="section-head" style="margin-bottom:0.25rem;">
        <div>
            <h1 class="mb-0"><?= e($vehicle['name']) ?></h1>
            <p class="hint mb-0">Submitted by <?= e($vehicle['provider_name']) ?> (<?= e($vehicle['provider_email']) ?>)</p>
        </div>
        <div>
            <span class="pill <?= e(\App\Models\Vehicle::statusPillClass($vStatus)) ?>" style="font-size:0.95rem;padding:0.4rem 0.8rem;">
                <?= e(\App\Models\Vehicle::statusLabel($vStatus)) ?>
            </span>
        </div>
    </div>
</div>

<?php if ($vStatus === 'rejected' && !empty($vehicle['rejection_reason'])): ?>
    <div class="status-banner banner-red" style="margin-bottom:1.5rem;">
        <span class="status-icon"><?= admin_icon('disputes', 24) ?></span>
        <div>
            <strong>Rejection Reason Recorded:</strong>
            <p style="margin:0.25rem 0 0;font-size:0.95rem;">
                <?= nl2br(e($vehicle['rejection_reason'])) ?>
            </p>
        </div>
    </div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 340px;gap:1.5rem;align-items:start;">
    <!-- Main Details Column -->
    <div>
        <div class="panel" style="margin-bottom:1.5rem;">
            <div class="panel-body">
                <div style="margin-bottom:1.5rem;">
                    <img src="<?= e(img_src($vehicle['image'] ?? null, 'vehicle-' . $vehicle['id'])) ?>" alt="" style="width:100%;max-height:420px;object-fit:cover;border-radius:8px;border:1px solid var(--border);">
                </div>

                <?php if ($gallery !== []): ?>
                    <h4 style="margin:0 0 0.75rem 0;">Additional Gallery Photos</h4>
                    <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(130px, 1fr));gap:0.75rem;margin-bottom:1.5rem;">
                        <?php foreach ($gallery as $img): ?>
                            <a href="<?= e(url($img['image_path'])) ?>" target="_blank" rel="noopener">
                                <img src="<?= e(url($img['image_path'])) ?>" alt="" style="width:100%;height:95px;object-fit:cover;border-radius:6px;border:1px solid var(--border);">
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <h3 style="margin:1rem 0 0.5rem;border-bottom:1px solid var(--border);padding-bottom:0.5rem;">Description &amp; Terms</h3>
                <div style="line-height:1.7;color:#334155;white-space:pre-line;">
                    <?= e($vehicle['description']) ?>
                </div>

                <?php if ($specs !== []): ?>
                    <h3 style="margin:1.5rem 0 0.75rem;border-bottom:1px solid var(--border);padding-bottom:0.5rem;">Features &amp; Inclusions</h3>
                    <div style="display:flex;flex-wrap:wrap;gap:0.5rem;">
                        <?php foreach ($specs as $s): ?>
                            <span style="background:#f1f5f9;color:#334155;padding:0.35rem 0.75rem;border-radius:20px;font-size:0.875rem;border:1px solid var(--border);">
                                ✓ <?= e($s) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Sidebar / Audit & Decision Column -->
    <div>
        <!-- Decision Box -->
        <div class="panel" style="margin-bottom:1.5rem;border-top:3px solid var(--primary, #059669);">
            <div class="panel-head">
                <h3 style="margin:0;">Review Decision</h3>
            </div>
            <div class="panel-body">
                <?php if ($vStatus !== 'approved'): ?>
                    <form method="post" action="<?= e(url('/admin/vehicles/' . (int) $vehicle['id'] . '/approve')) ?>" style="margin-bottom:1rem;" onsubmit="return confirm('Approve this listing? It will immediately become visible to tourists.');">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">
                            ✓ Approve Listing
                        </button>
                    </form>
                <?php else: ?>
                    <div style="background:#ecfdf5;color:#065f46;padding:0.75rem 1rem;border-radius:6px;font-size:0.875rem;margin-bottom:1rem;border:1px solid #a7f3d0;">
                        ✓ This listing is currently <strong>Approved</strong> and published for tourists.
                    </div>
                <?php endif; ?>

                <form method="post" action="<?= e(url('/admin/vehicles/' . (int) $vehicle['id'] . '/reject')) ?>" onsubmit="return confirm('Confirm rejection of this listing?');">
                    <?= csrf_field() ?>
                    <label for="side_reason" class="hint" style="display:block;margin-bottom:0.4rem;font-weight:600;">
                        <?= $vStatus === 'approved' ? 'Unpublish / Reject Reason' : 'Rejection Reason (optional)' ?>
                    </label>
                    <textarea class="input" id="side_reason" name="reason" rows="3" placeholder="Provide notes or feedback for the rental partner..."></textarea>
                    <button type="submit" class="btn btn-ghost" style="width:100%;margin-top:0.5rem;color:var(--coral-600);justify-content:center;">
                        <?= $vStatus === 'approved' ? 'Unpublish Listing' : 'Reject Listing' ?>
                    </button>
                </form>
            </div>
        </div>

        <!-- Vehicle Quick Specs -->
        <div class="panel" style="margin-bottom:1.5rem;">
            <div class="panel-head"><h3 style="margin:0;">Vehicle Summary</h3></div>
            <div class="panel-body" style="font-size:0.9rem;">
                <div style="display:flex;justify-content:space-between;padding:0.4rem 0;border-bottom:1px solid var(--border);">
                    <span class="hint">Make</span>
                    <strong><?= e($vehicle['make']) ?></strong>
                </div>
                <div style="display:flex;justify-content:space-between;padding:0.4rem 0;border-bottom:1px solid var(--border);">
                    <span class="hint">Model</span>
                    <strong><?= e($vehicle['model']) ?></strong>
                </div>
                <div style="display:flex;justify-content:space-between;padding:0.4rem 0;border-bottom:1px solid var(--border);">
                    <span class="hint">Year</span>
                    <strong><?= (int) $vehicle['year'] ?></strong>
                </div>
                <div style="display:flex;justify-content:space-between;padding:0.4rem 0;border-bottom:1px solid var(--border);">
                    <span class="hint">Type</span>
                    <strong><?= e($vehicle['type']) ?></strong>
                </div>
                <div style="display:flex;justify-content:space-between;padding:0.4rem 0;border-bottom:1px solid var(--border);">
                    <span class="hint">Daily Rate</span>
                    <strong style="color:var(--primary);font-size:1.05rem;"><?= money((float) $vehicle['price_per_day']) ?></strong>
                </div>
                <div style="padding:0.4rem 0;border-bottom:1px solid var(--border);">
                    <span class="hint" style="display:block;">Location</span>
                    <strong><?= e($vehicle['location']) ?></strong>
                    <?php if (!empty($vehicle['address'])): ?>
                        <div class="hint" style="font-size:0.8rem;"><?= e($vehicle['address']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Rental Partner Info -->
        <div class="panel" style="margin-bottom:1.5rem;">
            <div class="panel-head"><h3 style="margin:0;">Rental Partner</h3></div>
            <div class="panel-body" style="font-size:0.9rem;">
                <div style="font-weight:700;font-size:1rem;margin-bottom:0.25rem;"><?= e($vehicle['provider_name']) ?></div>
                <div class="hint" style="margin-bottom:0.25rem;">✉️ <?= e($vehicle['provider_email']) ?></div>
                <?php if (!empty($vehicle['provider_phone'])): ?>
                    <div class="hint" style="margin-bottom:0.5rem;">📞 <?= e($vehicle['provider_phone']) ?></div>
                <?php endif; ?>
                <div style="margin-top:0.75rem;">
                    <a href="<?= e(url('/admin/users/' . (int) $vehicle['user_id'] . '/edit')) ?>" class="btn btn-ghost btn-sm" style="width:100%;justify-content:center;">
                        View Partner Profile
                    </a>
                </div>
            </div>
        </div>

        <!-- Audit Trail -->
        <div class="panel">
            <div class="panel-head"><h3 style="margin:0;">Audit Trail</h3></div>
            <div class="panel-body" style="font-size:0.85rem;">
                <div style="margin-bottom:0.6rem;">
                    <span class="hint" style="display:block;">Created</span>
                    <?= e(date('M j, Y g:ia', strtotime((string) $vehicle['created_at']))) ?>
                </div>
                <?php if (!empty($vehicle['submitted_at'])): ?>
                    <div style="margin-bottom:0.6rem;">
                        <span class="hint" style="display:block;">Submitted for Review</span>
                        <?= e(date('M j, Y g:ia', strtotime((string) $vehicle['submitted_at']))) ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($vehicle['reviewed_at'])): ?>
                    <div style="margin-bottom:0.6rem;">
                        <span class="hint" style="display:block;">Last Reviewed</span>
                        <?= e(date('M j, Y g:ia', strtotime((string) $vehicle['reviewed_at']))) ?>
                        <?php if (!empty($vehicle['reviewer_name'])): ?>
                            <div class="hint">by <?= e($vehicle['reviewer_name']) ?></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
