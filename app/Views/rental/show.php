<?php
/**
 * @var array<string,mixed> $vehicle
 * @var array<int,array<string,mixed>> $gallery
 * @var array<int,string> $specs
 * @var bool $isPreview
 * @var bool $isOwner
 */
?>
<div class="container" style="padding-top:1.5rem;padding-bottom:3rem;">
    <!-- Breadcrumb -->
    <nav style="font-size:0.875rem;margin-bottom:1rem;color:var(--text-muted);">
        <a href="<?= e(url('/')) ?>" style="color:var(--text-muted);">Home</a> &gt;
        <a href="<?= e(url('/rentals')) ?>" style="color:var(--text-muted);">Vehicle Rentals</a> &gt;
        <span><?= e($vehicle['name']) ?></span>
    </nav>

    <?php if ($isPreview): ?>
        <div class="status-banner banner-amber" style="margin-bottom:1.5rem;">
            <span class="status-icon"><?= admin_icon('clock', 24) ?></span>
            <div>
                <strong>Preview Mode</strong>
                <p class="mb-0">
                    This vehicle listing is currently in <strong><?= e(\App\Models\Vehicle::statusLabel((string) $vehicle['status'])) ?></strong> status and is not visible to public tourists.
                    <?php if ($isOwner): ?>
                        <a href="<?= e(url('/dashboard/vehicles/' . (int) $vehicle['id'] . '/edit')) ?>">Edit listing details →</a>
                    <?php endif; ?>
                </p>
            </div>
        </div>
    <?php endif; ?>

    <div class="section-head" style="margin-bottom:1.25rem;">
        <div>
            <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.25rem;">
                <span class="pill pill-confirmed"><?= e($vehicle['type']) ?></span>
                <span class="hint">📍 <?= e($vehicle['location']) ?></span>
            </div>
            <h1 style="margin:0;"><?= e($vehicle['name']) ?></h1>
        </div>
        <div style="text-align:right;">
            <div style="font-size:1.75rem;font-weight:800;color:var(--primary);"><?= money((float) $vehicle['price_per_day']) ?></div>
            <div class="hint">per day</div>
        </div>
    </div>

    <!-- Media Gallery -->
    <div style="margin-bottom:2rem;">
        <img src="<?= e(img_src($vehicle['image'] ?? null, 'vehicle-' . $vehicle['id'])) ?>" alt="<?= e($vehicle['name']) ?>" style="width:100%;max-height:480px;object-fit:cover;border-radius:12px;border:1px solid var(--border);" onerror="this.onerror=null;this.src='https://picsum.photos/seed/vehicle<?= (int) $vehicle['id'] ?>/1200/800';">

        <?php if ($gallery !== []): ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(140px, 1fr));gap:0.75rem;margin-top:1rem;">
                <?php foreach ($gallery as $img): ?>
                    <a href="<?= e(url($img['image_path'])) ?>" target="_blank" rel="noopener">
                        <img src="<?= e(url($img['image_path'])) ?>" alt="" style="width:100%;height:100px;object-fit:cover;border-radius:8px;border:1px solid var(--border);">
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Two-column Layout -->
    <div style="display:grid;grid-template-columns:1fr 360px;gap:2rem;align-items:start;">
        <!-- Left: Details, Specs & Description -->
        <div>
            <!-- Specifications Grid -->
            <div class="panel" style="margin-bottom:1.5rem;">
                <div class="panel-head"><h3>Vehicle Specifications</h3></div>
                <div class="panel-body">
                    <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(180px, 1fr));gap:1rem;font-size:0.95rem;">
                        <div>
                            <span class="hint" style="display:block;font-size:0.8rem;">Make / Manufacturer</span>
                            <strong><?= e($vehicle['make']) ?></strong>
                        </div>
                        <div>
                            <span class="hint" style="display:block;font-size:0.8rem;">Model</span>
                            <strong><?= e($vehicle['model']) ?></strong>
                        </div>
                        <div>
                            <span class="hint" style="display:block;font-size:0.8rem;">Model Year</span>
                            <strong><?= (int) $vehicle['year'] ?></strong>
                        </div>
                        <div>
                            <span class="hint" style="display:block;font-size:0.8rem;">Category</span>
                            <strong><?= e($vehicle['type']) ?></strong>
                        </div>
                    </div>

                    <?php if ($specs !== []): ?>
                        <h4 style="margin:1.5rem 0 0.75rem 0;border-top:1px solid var(--border);padding-top:1rem;">Features &amp; Inclusions</h4>
                        <div style="display:flex;flex-wrap:wrap;gap:0.5rem;">
                            <?php foreach ($specs as $s): ?>
                                <span style="background:#f1f5f9;color:#334155;padding:0.4rem 0.8rem;border-radius:20px;font-size:0.875rem;border:1px solid var(--border);">
                                    ✓ <?= e($s) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Description -->
            <div class="panel" style="margin-bottom:1.5rem;">
                <div class="panel-head"><h3>Vehicle Overview &amp; Terms</h3></div>
                <div class="panel-body" style="line-height:1.7;color:#334155;white-space:pre-line;">
                    <?= e($vehicle['description']) ?>
                </div>
            </div>

            <!-- Rental Guidelines -->
            <div class="panel">
                <div class="panel-head"><h3>Rental Requirements</h3></div>
                <div class="panel-body" style="font-size:0.9rem;line-height:1.6;color:#475569;">
                    <ul style="padding-left:1.25rem;margin:0;">
                        <li><strong>Valid Driver's License:</strong> A valid Philippine or International Driver's License corresponding to the vehicle type.</li>
                        <li><strong>Valid Identification:</strong> Government-issued ID or passport required upon pickup.</li>
                        <li><strong>Fuel Policy:</strong> Vehicles are typically delivered full and must be returned with the same fuel level unless arranged otherwise.</li>
                        <li><strong>Safe Driving:</strong> Always wear a certified helmet on two-wheelers and observe local traffic regulations throughout Cebu.</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Right: Reservation / Partner Box -->
        <div>
            <div class="panel" style="position:sticky;top:80px;border-top:3px solid var(--primary);">
                <div class="panel-body">
                    <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:1rem;border-bottom:1px solid var(--border);padding-bottom:1rem;">
                        <div>
                            <span style="font-size:1.8rem;font-weight:800;color:var(--primary);"><?= money((float) $vehicle['price_per_day']) ?></span>
                            <span class="hint">/ day</span>
                        </div>
                        <span class="pill pill-approved">Available for Rent</span>
                    </div>

                    <div style="margin-bottom:1.25rem;font-size:0.9rem;">
                        <div style="margin-bottom:0.5rem;">
                            <span class="hint">Pickup Location:</span>
                            <div style="font-weight:600;">📍 <?= e($vehicle['location']) ?></div>
                            <?php if (!empty($vehicle['address'])): ?>
                                <div class="hint" style="font-size:0.8rem;"><?= e($vehicle['address']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div style="margin-top:0.75rem;padding-top:0.75rem;border-top:1px solid var(--border);">
                            <span class="hint">Rental Partner:</span>
                            <div style="font-weight:600;font-size:1rem;"><?= e($vehicle['provider_name']) ?></div>
                            <div class="hint" style="font-size:0.8rem;">Verified GuideMate Partner</div>
                        </div>
                    </div>

                    <?php if ($isOwner): ?>
                        <a href="<?= e(url('/dashboard/vehicles/' . (int) $vehicle['id'] . '/edit')) ?>" class="btn btn-primary" style="width:100%;justify-content:center;">
                            Manage Your Listing
                        </a>
                    <?php else: ?>
                        <?php if (\App\Core\Auth::check()): ?>
                            <form method="post" action="<?= e(url('/messages')) ?>" style="margin-bottom:0.75rem;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="receiver_id" value="<?= (int) $vehicle['user_id'] ?>">
                                <input type="hidden" name="body" value="Hi! I am interested in renting your <?= e($vehicle['name']) ?> (<?= money((float) $vehicle['price_per_day']) ?>/day). Is this available?">
                                <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">
                                    Inquire with Partner
                                </button>
                            </form>
                        <?php else: ?>
                            <a href="<?= e(url('/login?return=' . urlencode('/rentals/' . $vehicle['slug']))) ?>" class="btn btn-primary" style="width:100%;justify-content:center;margin-bottom:0.75rem;">
                                Log in to Reserve Unit
                            </a>
                        <?php endif; ?>
                        <div class="hint" style="text-align:center;font-size:0.8rem;">
                            🔒 Direct booking with verified Cebu rental operators.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
