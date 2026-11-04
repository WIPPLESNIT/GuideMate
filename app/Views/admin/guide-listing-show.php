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
$includedRaw = (string) ($listing['included'] ?? '');
$amenities = !empty($includedRaw) ? array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $includedRaw)))) : [];
$notIncludedRaw = (string) ($listing['not_included'] ?? '');
$notIncluded = !empty($notIncludedRaw) ? array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $notIncludedRaw)))) : [];
$thumb = !empty($listing['cover_image']) ? $listing['cover_image'] : 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=400&q=80';
$isAttraction = ($listing['category_slug'] ?? '') === 'attractions' || ($listing['category_slug'] ?? '') === 'attraction-and-tours';
$isPackage = ($listing['category_slug'] ?? '') === 'packages' || ($listing['category_slug'] ?? '') === 'travel-packages';
$cap = (int) ($listing['total_capacity'] ?? $listing['capacity'] ?? 10);
$packageInclusions = $packageInclusions ?? [];
$incThings = $packageInclusions['things_to_do'] ?? [];
$incAttrs = $packageInclusions['attractions'] ?? [];
$incHotel = $packageInclusions['hotel'] ?? null;
$incRental = $packageInclusions['rental'] ?? null;
?>
<div style="margin-bottom:1.5rem;">
    <a href="<?= e(url('/admin/guide-listings')) ?>" class="btn btn-ghost btn-sm" style="margin-bottom:0.75rem;">← Back to Guide Listings Review</a>
    <div class="section-head" style="margin-bottom:0.25rem;">
        <div>
            <div style="display:flex;align-items:center;gap:0.6rem;margin-bottom:0.35rem;">
                <h1 class="mb-0"><?= e($listing['title']) ?></h1>
                <?php if ($isPackage): ?>
                    <span class="pill" style="font-size:0.78rem;padding:3px 10px;background:rgba(139,92,246,0.18);color:#a78bfa;border:1px solid rgba(139,92,246,0.35);font-weight:700;border-radius:20px;">
                        Travel Package
                    </span>
                <?php elseif ($isAttraction): ?>
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
                            <?php if ($isPackage): ?>
                                <small class="hint">(Total Package Price)</small>
                            <?php else: ?>
                                <?php if ($isPackage): ?>
                                <small class="hint">(Total Package Price)</small>
                            <?php else: ?>
                                <small class="hint">/ <?= e($listing['price_unit'] ?? 'person') ?></small>
                            <?php endif; ?>
                            <?php endif; ?>
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

                
                <?php if ($isPackage && (!empty($incThings) || !empty($incAttrs) || !empty($incHotel) || !empty($incRental))): ?>
                    <!-- ======================================================== -->
                    <!-- DEDICATED PACKAGE INCLUSIONS / SELECTED SERVICES SECTION  -->
                    <!-- ======================================================== -->
                    <div style="margin: 2rem 0; padding: 1.5rem; background: rgba(139, 92, 246, 0.04); border: 1px solid rgba(139, 92, 246, 0.35); border-radius: 14px;">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;border-bottom:1px solid rgba(255,255,255,0.08);padding-bottom:0.85rem;flex-wrap:wrap;gap:0.75rem;">
                            <div style="display:flex;align-items:center;gap:0.75rem;">
                                <span style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:10px;background:rgba(139,92,246,0.2);color:#c4b5fd;font-size:1.3rem;">📦</span>
                                <div>
                                    <h3 style="margin:0;font-size:1.25rem;font-weight:800;color:#ffffff;letter-spacing:-0.02em;">Package Inclusions (Selected Services)</h3>
                                    <p class="hint mb-0" style="font-size:0.85rem;color:#94a3b8;">
                                        Inspect the actual tours, partner accommodation, and rental units bundled into this travel package.
                                    </p>
                                </div>
                            </div>
                            <span class="pill" style="background:rgba(139,92,246,0.25);color:#ede9fe;border:1px solid rgba(139,92,246,0.5);font-weight:700;padding:4px 12px;font-size:0.8rem;border-radius:20px;">
                                Verified Bundled Services
                            </span>
                        </div>

                        <!-- A. THINGS TO DO SECTION -->
                        <?php if (!empty($incThings)): ?>
                            <div style="margin-bottom: 1.75rem;">
                                <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.85rem;">
                                    <span style="font-size:1.1rem;">🏄</span>
                                    <h4 style="margin:0;font-size:1.05rem;font-weight:700;color:#38bdf8;">Things to Do (<?= count($incThings) ?>)</h4>
                                </div>
                                <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(260px, 1fr));gap:1rem;">
                                    <?php foreach ($incThings as $act): ?>
                                        <div style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:12px;overflow:hidden;display:flex;flex-direction:column;">
                                            <div style="position:relative;height:140px;background:#1e293b;">
                                                <img src="<?= e(img_src($act['cover_image'] ?? 'uploads/places/kawasan.jpg')) ?>" alt="<?= e($act['title']) ?>" style="width:100%;height:100%;object-fit:cover;" onerror="this.src='https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?w=600'">
                                                <span style="position:absolute;top:0.5rem;left:0.5rem;background:rgba(14,165,233,0.9);color:#ffffff;font-size:0.7rem;font-weight:700;padding:2px 8px;border-radius:6px;text-transform:uppercase;">Things to Do</span>
                                            </div>
                                            <div style="padding:0.85rem;flex:1;display:flex;flex-direction:column;">
                                                <div style="font-size:0.75rem;color:#94a3b8;margin-bottom:0.25rem;">📍 <?= e($act['area'] ?? 'Cebu') ?></div>
                                                <strong style="font-size:0.95rem;color:#f8fafc;line-height:1.3;margin-bottom:0.5rem;"><?= e($act['title']) ?></strong>
                                                <div style="margin-top:auto;display:flex;justify-content:space-between;align-items:center;border-top:1px solid rgba(255,255,255,0.06);padding-top:0.5rem;">
                                                    <span style="color:#34d399;font-weight:800;font-size:0.95rem;"><?= money((float) ($act['price'] ?? 0)) ?> <small style="color:#94a3b8;font-size:0.7rem;font-weight:normal;">/ <?= e($act['price_unit'] ?? 'person') ?></small></span>
                                                    <span class="hint" style="font-size:0.75rem;">⏱ <?= e($act['duration'] ?? 'Half day') ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- B. ATTRACTIONS SECTION -->
                        <?php if (!empty($incAttrs)): ?>
                            <div style="margin-bottom: 1.75rem;">
                                <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.85rem;">
                                    <span style="font-size:1.1rem;">🎟️</span>
                                    <h4 style="margin:0;font-size:1.05rem;font-weight:700;color:#f472b6;">Attractions (<?= count($incAttrs) ?>)</h4>
                                </div>
                                <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(260px, 1fr));gap:1rem;">
                                    <?php foreach ($incAttrs as $att): ?>
                                        <div style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:12px;overflow:hidden;display:flex;flex-direction:column;">
                                            <div style="position:relative;height:140px;background:#1e293b;">
                                                <img src="<?= e(img_src($att['cover_image'] ?? 'uploads/places/sardine.jpg')) ?>" alt="<?= e($att['title']) ?>" style="width:100%;height:100%;object-fit:cover;" onerror="this.src='https://images.unsplash.com/photo-1544644181-1484b3fdfc62?w=600'">
                                                <span style="position:absolute;top:0.5rem;left:0.5rem;background:rgba(236,72,153,0.9);color:#ffffff;font-size:0.7rem;font-weight:700;padding:2px 8px;border-radius:6px;text-transform:uppercase;">Attraction</span>
                                            </div>
                                            <div style="padding:0.85rem;flex:1;display:flex;flex-direction:column;">
                                                <div style="font-size:0.75rem;color:#94a3b8;margin-bottom:0.25rem;">📍 <?= e($att['area'] ?? 'Cebu') ?></div>
                                                <strong style="font-size:0.95rem;color:#f8fafc;line-height:1.3;margin-bottom:0.5rem;"><?= e($att['title']) ?></strong>
                                                <div style="margin-top:auto;display:flex;justify-content:space-between;align-items:center;border-top:1px solid rgba(255,255,255,0.06);padding-top:0.5rem;">
                                                    <span style="color:#34d399;font-weight:800;font-size:0.95rem;"><?= money((float) ($att['price'] ?? 0)) ?> <small style="color:#94a3b8;font-size:0.7rem;font-weight:normal;">/ <?= e($att['price_unit'] ?? 'person') ?></small></span>
                                                    <span class="hint" style="font-size:0.75rem;">⏱ <?= e($att['duration'] ?? 'Flexible') ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- C. HOTEL ACCOMMODATION SECTION -->
                        <?php if (!empty($incHotel)): ?>
                            <div style="margin-bottom: 1.75rem;">
                                <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.85rem;">
                                    <span style="font-size:1.1rem;">🏨</span>
                                    <h4 style="margin:0;font-size:1.05rem;font-weight:700;color:#fbbf24;">Hotel Accommodation</h4>
                                </div>
                                <div style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:12px;overflow:hidden;display:grid;grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));gap:1.25rem;padding:1.25rem;">
                                    <div style="position:relative;min-height:180px;border-radius:10px;overflow:hidden;background:#1e293b;">
                                        <img src="<?= e(img_src($incHotel['hotel_cover'] ?? $incHotel['room_image'] ?? 'uploads/places/hotel.jpg')) ?>" alt="<?= e($incHotel['hotel_name'] ?? 'Partner Hotel') ?>" style="width:100%;height:100%;object-fit:cover;" onerror="this.src='https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?w=600'">
                                        <span style="position:absolute;top:0.6rem;left:0.6rem;background:rgba(245,158,11,0.9);color:#ffffff;font-size:0.75rem;font-weight:700;padding:2px 8px;border-radius:6px;text-transform:uppercase;">Hotel &amp; Stays</span>
                                    </div>
                                    <div style="display:flex;flex-direction:column;justify-content:space-between;">
                                        <div>
                                            <div style="font-size:0.8rem;color:#94a3b8;margin-bottom:0.25rem;">📍 <?= e($incHotel['hotel_area'] ?? 'Moalboal, Cebu') ?></div>
                                            <h4 style="margin:0 0 0.5rem 0;font-size:1.15rem;color:#f8fafc;font-weight:800;"><?= e($incHotel['hotel_name'] ?? 'Sea Breeze Oceanfront Resort') ?></h4>
                                            <div style="background:rgba(255,255,255,0.04);padding:0.65rem 0.85rem;border-radius:8px;border:1px solid rgba(255,255,255,0.08);margin-bottom:0.75rem;">
                                                <div style="display:flex;justify-content:space-between;font-size:0.9rem;">
                                                    <span style="color:#f8fafc;font-weight:700;">Room: <?= e($incHotel['room_name'] ?? 'Deluxe Queen Room') ?></span>
                                                    <span style="color:#34d399;font-weight:800;"><?= money((float) ($incHotel['price_per_night'] ?? 2200)) ?> <small style="color:#94a3b8;font-size:0.75rem;">/ night</small></span>
                                                </div>
                                                <div style="font-size:0.78rem;color:#94a3b8;margin-top:4px;">
                                                    Max: <?= (int) ($incHotel['max_guests'] ?? 2) ?> Guests • <?= e($incHotel['bed_type'] ?? '1 Queen Bed') ?> • Verified Partner: <?= e($incHotel['partner_name'] ?? 'Hospitality Provider') ?>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- Check-in and Check-out Cards -->
                                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;margin-top:0.5rem;">
                                            <div style="padding:0.65rem 0.85rem;background:rgba(139,92,246,0.08);border:1px solid rgba(139,92,246,0.3);border-radius:8px;">
                                                <span style="font-size:0.7rem;color:#c4b5fd;font-weight:700;display:block;text-transform:uppercase;">Check-in Schedule</span>
                                                <strong style="font-size:0.85rem;color:#f8fafc;display:block;margin-top:2px;">
                                                    Day 1 · From <?= date('g:i A', strtotime((string) ($incHotel['check_in_time'] ?? '14:00:00'))) ?>
                                                </strong>
                                                <small class="hint" style="font-size:0.7rem;">Front desk key handover</small>
                                            </div>
                                            <div style="padding:0.65rem 0.85rem;background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.3);border-radius:8px;">
                                                <span style="font-size:0.7rem;color:#6ee7b7;font-weight:700;display:block;text-transform:uppercase;">Check-out Schedule</span>
                                                <strong style="font-size:0.85rem;color:#f8fafc;display:block;margin-top:2px;">
                                                    Day 3 · Until <?= date('g:i A', strtotime((string) ($incHotel['check_out_time'] ?? '12:00:00'))) ?>
                                                </strong>
                                                <small class="hint" style="font-size:0.7rem;">Room clearance &amp; departure</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- D. RENTAL SERVICES SECTION -->
                        <?php if (!empty($incRental)): ?>
                            <div>
                                <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.85rem;">
                                    <span style="font-size:1.1rem;">🛵</span>
                                    <h4 style="margin:0;font-size:1.05rem;font-weight:700;color:#60a5fa;">Rental Services</h4>
                                </div>
                                <div style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:12px;overflow:hidden;display:grid;grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));gap:1.25rem;padding:1.25rem;">
                                    <div style="position:relative;min-height:180px;border-radius:10px;overflow:hidden;background:#1e293b;">
                                        <img src="<?= e(img_src($incRental['vehicle_image'] ?? 'uploads/places/vehicle.jpg')) ?>" alt="<?= e($incRental['vehicle_name'] ?? 'Rental Vehicle') ?>" style="width:100%;height:100%;object-fit:cover;" onerror="this.src='https://images.unsplash.com/photo-1558981403-c5f9899a28bc?w=600'">
                                        <span style="position:absolute;top:0.6rem;left:0.6rem;background:rgba(37,99,235,0.9);color:#ffffff;font-size:0.75rem;font-weight:700;padding:2px 8px;border-radius:6px;text-transform:uppercase;">Vehicle Rental</span>
                                    </div>
                                    <div style="display:flex;flex-direction:column;justify-content:space-between;">
                                        <div>
                                            <div style="font-size:0.8rem;color:#94a3b8;margin-bottom:0.25rem;">📍 <?= e($incRental['location'] ?? 'Cebu, Philippines') ?></div>
                                            <h4 style="margin:0 0 0.5rem 0;font-size:1.15rem;color:#f8fafc;font-weight:800;">
                                                <?= e($incRental['vehicle_name'] ?? ($incRental['make'] . ' ' . $incRental['model'])) ?>
                                            </h4>
                                            <div style="background:rgba(255,255,255,0.04);padding:0.65rem 0.85rem;border-radius:8px;border:1px solid rgba(255,255,255,0.08);margin-bottom:0.75rem;">
                                                <div style="display:flex;justify-content:space-between;font-size:0.9rem;">
                                                    <span style="color:#f8fafc;font-weight:700;">Type: <?= ucfirst(e($incRental['vehicle_type'] ?? 'Scooter')) ?></span>
                                                    <span style="color:#60a5fa;font-weight:800;"><?= money((float) ($incRental['daily_rate'] ?? 650)) ?> <small style="color:#94a3b8;font-size:0.75rem;">/ day</small></span>
                                                </div>
                                                <div style="font-size:0.78rem;color:#94a3b8;margin-top:4px;">
                                                    Automatic • 2 DOT Helmets • Unlimited Mileage • Partner: <?= e($incRental['partner_name'] ?? 'Rental Partner') ?>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- Handover and Return Schedule Cards -->
                                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;margin-top:0.5rem;">
                                            <div style="padding:0.65rem 0.85rem;background:rgba(59,130,246,0.08);border:1px solid rgba(59,130,246,0.3);border-radius:8px;">
                                                <span style="font-size:0.7rem;color:#93c5fd;font-weight:700;display:block;text-transform:uppercase;">Handover / Pickup</span>
                                                <strong style="font-size:0.85rem;color:#f8fafc;display:block;margin-top:2px;">Day 1 · 01:00 PM</strong>
                                                <small class="hint" style="font-size:0.7rem;">Safety briefing &amp; helmet fitting</small>
                                            </div>
                                            <div style="padding:0.65rem 0.85rem;background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.3);border-radius:8px;">
                                                <span style="font-size:0.7rem;color:#6ee7b7;font-weight:700;display:block;text-transform:uppercase;">Drop-off / Return</span>
                                                <strong style="font-size:0.85rem;color:#f8fafc;display:block;margin-top:2px;">Day 3 · 12:00 PM</strong>
                                                <small class="hint" style="font-size:0.7rem;">Unit inspection &amp; clearance</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

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
