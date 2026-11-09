<?php
/**
 * Admin Guide Listing Inspection
 *
 * Displays full inspection details for Travel Packages (matching the 5 customer app reference screens)
 * and single tour/attraction listings submitted by tour guides.
 *
 * @var array<string,mixed> $listing
 * @var array<string,mixed>|null $guide
 * @var array<string,mixed>|null $reviewer
 * @var array<int,array<string,mixed>> $schedules
 * @var array<int,array<string,mixed>> $gallery
 * @var bool $isPackage
 * @var array<string,mixed>|null $packageData
 * @var array<string,mixed> $packageInclusions
 */

$lStatus = (string) ($listing['status'] ?? 'pending');
$includedRaw = (string) ($listing['included'] ?? '');

// Filter out internal metadata/comment lines from amenities for standard listings
$amenityLines = !empty($includedRaw) ? preg_split('/[\r\n,]+/', $includedRaw) : [];
$amenities = [];
foreach ($amenityLines as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '<!--') || str_starts_with($line, 'Activities:') || str_starts_with($line, 'Accommodation:') || str_starts_with($line, 'Transportation:')) {
        continue;
    }
    $amenities[] = $line;
}

$notIncludedRaw = (string) ($listing['not_included'] ?? '');
$notIncluded = !empty($notIncludedRaw) ? array_values(array_filter(array_map('trim', preg_split('/[\r\n]+/', $notIncludedRaw)))) : [];
$thumb = !empty($listing['cover_image']) ? $listing['cover_image'] : 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=800&q=80';
$isAttraction = ($listing['category_slug'] ?? '') === 'attractions' || ($listing['category_slug'] ?? '') === 'attraction-and-tours';
$isPackage = $isPackage ?? (($listing['category_slug'] ?? '') === 'packages' || ($listing['category_slug'] ?? '') === 'travel-packages' || ($listing['price_unit'] ?? '') === 'package');
$cap = (int) ($listing['total_capacity'] ?? $listing['capacity'] ?? 8);
$packageInclusions = $packageInclusions ?? ($packageData['inclusions'] ?? []);
$incThings = $packageInclusions['things_to_do'] ?? [];
$incAttrs = $packageInclusions['attractions'] ?? [];
$incHotel = $packageData['hotel'] ?? ($packageInclusions['hotel'] ?? null);
$incRental = $packageData['rental'] ?? ($packageInclusions['rental'] ?? null);
?>

<div style="margin-bottom:1.5rem;">
    <a href="<?= e(url('/admin/guide-listings')) ?>" class="btn btn-ghost btn-sm" style="margin-bottom:0.75rem;">← Back to Guide Listings Review</a>
    <div class="section-head" style="margin-bottom:0.25rem;">
        <div>
            <div style="display:flex;align-items:center;gap:0.6rem;margin-bottom:0.35rem;flex-wrap:wrap;">
                <h1 class="mb-0" style="font-size:1.6rem;font-weight:800;color:#ffffff;"><?= e($listing['title']) ?></h1>
                <?php if ($isPackage): ?>
                    <span class="pill" style="font-size:0.78rem;padding:4px 12px;background:rgba(139,92,246,0.22);color:#c4b5fd;border:1px solid rgba(139,92,246,0.45);font-weight:800;border-radius:20px;letter-spacing:0.5px;text-transform:uppercase;">
                        📦 Travel Package
                    </span>
                <?php elseif ($isAttraction): ?>
                    <span class="pill" style="font-size:0.78rem;padding:4px 12px;background:rgba(236,72,153,0.22);color:#f472b6;border:1px solid rgba(236,72,153,0.45);font-weight:800;border-radius:20px;">
                        Attraction
                    </span>
                <?php else: ?>
                    <span class="pill" style="font-size:0.78rem;padding:4px 12px;background:rgba(16,185,129,0.22);color:#34d399;border:1px solid rgba(16,185,129,0.45);font-weight:800;border-radius:20px;">
                        Things to Do
                    </span>
                <?php endif; ?>
            </div>
            <p class="hint mb-0" style="color:#94a3b8;">
                Submitted by <strong style="color:#f8fafc;"><?= e($guide['name'] ?? 'Tour Guide') ?></strong> (<?= e($guide['email'] ?? 'guide@guidemate.ph') ?>)
                <?php if (!empty($listing['created_at'])): ?>
                    · <?= e(date('M j, Y g:ia', strtotime((string) $listing['created_at']))) ?>
                <?php endif; ?>
            </p>
        </div>
        <div>
            <span class="pill <?= e(\App\Models\Listing::hotelStatusPillClass($lStatus)) ?>" style="font-size:0.95rem;padding:0.4rem 0.9rem;font-weight:700;">
                <?= e(\App\Models\Listing::hotelStatusLabel($lStatus)) ?>
            </span>
        </div>
    </div>
</div>

<?php if ($lStatus === 'rejected' && !empty($listing['rejection_reason'])): ?>
    <div class="status-banner banner-red" style="margin-bottom:1.5rem;background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.35);border-radius:12px;padding:1rem 1.25rem;">
        <span class="status-icon" style="color:#f87171;font-size:1.5rem;"><?= admin_icon('disputes', 24) ?></span>
        <div>
            <strong style="color:#fca5a5;">Rejection Reason Recorded:</strong>
            <p style="margin:0.25rem 0 0;font-size:0.95rem;color:#fecaca;">
                <?= nl2br(e($listing['rejection_reason'])) ?>
            </p>
        </div>
    </div>
<?php endif; ?>

<div class="admin-vehicle-layout" style="display:grid;grid-template-columns:1fr 340px;gap:1.5rem;align-items:start;">
    <!-- Main Details Column -->
    <div class="admin-vehicle-main" style="min-width:0;">

        <?php if ($isPackage && !empty($packageData)): ?>
            <!-- ================================================================= -->
            <!-- 1. LIVE TOURIST VIEW / PACKAGE HERO & OVERVIEW (IMAGE 1 REPLICA)  -->
            <!-- ================================================================= -->
            <div class="panel" style="margin-bottom:1.5rem;background:#0f172a;border:1px solid rgba(255,255,255,0.1);border-radius:16px;overflow:hidden;box-shadow:0 10px 25px rgba(0,0,0,0.4);">
                <!-- Package Tourist View Header Bar -->
                <div style="display:flex;align-items:center;justify-content:space-between;padding:0.75rem 1.25rem;background:rgba(255,255,255,0.03);border-bottom:1px solid rgba(255,255,255,0.08);">
                    <div style="display:flex;align-items:center;gap:0.5rem;font-size:0.8rem;font-weight:700;color:#c4b5fd;">
                        <!-- Live Tourist View (Image 1) -->
                        <span>PACKAGE SHOWCASE</span>
                    </div>
                    <span style="font-size:0.75rem;color:#c4b5fd;font-weight:600;">Updates in real time</span>
                </div>

                <!-- Showcase Cover Photo with Badges -->
                <div style="position:relative;width:100%;height:340px;background:#1e293b;overflow:hidden;">
                    <img src="<?= e(img_src($thumb, 'listing-' . $listing['id'])) ?>" alt="<?= e($packageData['title']) ?>" style="width:100%;height:100%;object-fit:cover;" onerror="this.src='https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=800'">
                    <div style="position:absolute;inset:0;background:linear-gradient(to top, rgba(15,23,42,0.92) 0%, rgba(15,23,42,0.2) 60%, rgba(0,0,0,0.4) 100%);"></div>

                    <!-- Top Floating Action Pill Controls (Preview Controls) -->
                    <div style="position:absolute;top:1rem;left:1rem;right:1rem;display:flex;justify-content:space-between;align-items:center;">
                        <span style="display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:50%;background:rgba(15,23,42,0.7);color:#ffffff;backdrop-filter:blur(6px);font-size:0.9rem;">
                            ←
                        </span>
                        <div style="display:flex;gap:0.5rem;">
                            <span style="display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:50%;background:rgba(15,23,42,0.7);color:#ffffff;backdrop-filter:blur(6px);font-size:0.9rem;">
                                ♡
                            </span>
                            <span style="display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:50%;background:rgba(15,23,42,0.7);color:#ffffff;backdrop-filter:blur(6px);font-size:0.9rem;">
                                ⚙
                            </span>
                        </div>
                    </div>

                    <!-- Bottom Floating Badges (Only New Expedition badge) -->
                    <div style="position:absolute;bottom:1.25rem;left:1.25rem;right:1.25rem;display:flex;gap:0.6rem;flex-wrap:wrap;align-items:center;">
                        <span style="display:inline-flex;align-items:center;gap:6px;background:linear-gradient(135deg, #f59e0b, #d97706);color:#ffffff;font-size:0.8rem;font-weight:800;padding:6px 14px;border-radius:9999px;letter-spacing:0.5px;text-transform:uppercase;box-shadow:0 4px 14px rgba(245,158,11,0.5);">
                            ✨ <?= strtoupper(e(!empty($packageData['badge']) ? $packageData['badge'] : 'NEW EXPEDITION')) ?>
                        </span>
                    </div>
                </div>

                <!-- Gallery Photos Preview -->
                <?php if ($gallery !== []): ?>
                    <div style="padding:0.75rem 1.25rem;background:rgba(255,255,255,0.02);border-bottom:1px solid rgba(255,255,255,0.06);display:flex;gap:0.6rem;overflow-x:auto;">
                        <?php foreach ($gallery as $gIdx => $gImg): ?>
                            <a href="<?= e(url($gImg['image_path'])) ?>" target="_blank" rel="noopener" style="flex-shrink:0;">
                                <img src="<?= e(url($gImg['image_path'])) ?>" alt="Showcase <?= $gIdx + 1 ?>" style="width:72px;height:52px;object-fit:cover;border-radius:8px;border:1px solid rgba(255,255,255,0.15);">
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Content Body -->
                <div style="padding:1.5rem;">
                    <!-- Title -->
                    <h2 style="font-size:1.55rem;font-weight:800;color:#ffffff;line-height:1.3;margin:0 0 0.65rem 0;">
                        <?= e($packageData['title']) ?>
                    </h2>

                    <!-- Location & Reviews Bar -->
                    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.75rem;margin-bottom:1.25rem;">
                        <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
                            <span style="color:#c084fc;font-size:0.92rem;font-weight:600;display:inline-flex;align-items:center;gap:4px;">
                                <span style="color:#ec4899;">📍</span> <?= e($packageData['destination']) ?>
                            </span>
                            <span style="color:#fbbf24;font-size:0.92rem;font-weight:700;display:inline-flex;align-items:center;gap:4px;">
                                ★ <?= $packageData['rating'] > 0 ? number_format($packageData['rating'], 1) : 'New' ?> <span style="color:#94a3b8;font-weight:normal;font-size:0.85rem;">(<?= $packageData['review_count'] > 0 ? (int)$packageData['review_count'] . ' reviews' : 'No reviews yet' ?>)</span>
                            </span>
                        </div>
                        <div>
                            <span style="font-size:1.35rem;font-weight:800;color:#34d399;">
                                <?= money($packageData['price']) ?>
                            </span>
                            <span class="hint" style="font-size:0.75rem;color:#94a3b8;display:block;text-align:right;">Single bundle total</span>
                        </div>
                    </div>

                    <!-- 3-Column Key Trip Attributes (Duration, Group Size, Assurance) -->
                    <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);border-radius:12px;padding:1rem 0.5rem;margin-bottom:1.5rem;display:grid;grid-template-columns:1fr 1fr 1fr;text-align:center;">
                        <!-- Column 1: Duration -->
                        <div style="padding:0 0.5rem;">
                            <div style="font-size:1.15rem;color:#c084fc;">⏱</div>
                            <div style="font-size:0.72rem;color:#94a3b8;text-transform:uppercase;letter-spacing:0.5px;margin-top:2px;">Duration</div>
                            <div style="font-size:1.1rem;font-weight:800;color:#ffffff;margin-top:2px;"><?= e($packageData['duration_code']) ?></div>
                        </div>

                        <!-- Column 2: Group Size -->
                        <div style="padding:0 0.5rem;border-left:1px solid rgba(255,255,255,0.08);border-right:1px solid rgba(255,255,255,0.08);">
                            <div style="font-size:1.15rem;color:#818cf8;">👥</div>
                            <div style="font-size:0.72rem;color:#94a3b8;text-transform:uppercase;letter-spacing:0.5px;margin-top:2px;">Group Size</div>
                            <div style="font-size:1.1rem;font-weight:800;color:#ffffff;margin-top:2px;"><?= e($packageData['group_size']) ?></div>
                        </div>

                        <!-- Column 3: Assurance -->
                        <div style="padding:0 0.5rem;">
                            <div style="font-size:1.15rem;color:#34d399;">🛡️</div>
                            <div style="font-size:0.72rem;color:#94a3b8;text-transform:uppercase;letter-spacing:0.5px;margin-top:2px;">Assurance</div>
                            <div style="font-size:1.1rem;font-weight:800;color:#34d399;margin-top:2px;"><?= e($packageData['assurance']) ?></div>
                        </div>
                    </div>

                    <!-- About This Package Narrative -->
                    <h3 style="font-size:1.15rem;font-weight:800;color:#ffffff;margin:0 0 0.6rem 0;">About This Package</h3>
                    <div style="font-size:0.95rem;line-height:1.7;color:#cbd5e1;white-space:pre-line;">
                        <?= e($packageData['description']) ?>
                    </div>

                    <!-- Experience Tags -->
                    <?php if (!empty($packageData['tags'])): ?>
                        <div style="margin-top:1.25rem;display:flex;flex-wrap:wrap;gap:0.5rem;align-items:center;">
                            <span style="font-size:0.75rem;color:#94a3b8;text-transform:uppercase;font-weight:700;letter-spacing:0.5px;margin-right:0.25rem;">Experience Tags:</span>
                            <?php foreach ($packageData['tags'] as $tg): ?>
                                <span style="background:rgba(139,92,246,0.12);color:#c4b5fd;border:1px solid rgba(139,92,246,0.3);border-radius:20px;padding:3px 12px;font-size:0.8rem;font-weight:600;">
                                    #<?= e($tg) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Footer Tagline -->
                <div style="padding:0.6rem 1.25rem;background:rgba(255,255,255,0.02);border-top:1px solid rgba(255,255,255,0.06);display:flex;justify-content:flex-end;align-items:center;font-size:0.78rem;">
                    <span style="color:#34d399;font-weight:700;">● Active</span>
                </div>
            </div>

            <!-- ================================================================= -->
            <!-- 2. PACKAGE HOST CARD (IMAGE 2 REPLICA)                            -->
            <!-- ================================================================= -->
            <div class="panel" style="margin-bottom:1.5rem;background:#0f172a;border:1px solid rgba(255,255,255,0.1);border-radius:16px;padding:1.5rem;box-shadow:0 10px 25px rgba(0,0,0,0.35);">
                <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1rem;">
                    <?php if (!empty($packageData['host']['avatar'])): ?>
                        <img src="<?= e(url($packageData['host']['avatar'])) ?>" alt="<?= e($packageData['host']['name']) ?>" style="width:68px;height:68px;border-radius:50%;object-fit:cover;border:2px solid #8b5cf6;">
                    <?php else: ?>
                        <div style="width:68px;height:68px;border-radius:50%;background:linear-gradient(135deg, #8b5cf6, #ec4899);color:#ffffff;display:flex;align-items:center;justify-content:center;font-size:1.5rem;font-weight:800;border:2px solid rgba(255,255,255,0.2);">
                            <?= strtoupper(substr((string) $packageData['host']['name'], 0, 1)) ?>
                        </div>
                    <?php endif; ?>

                    <div style="flex:1;">
                        <div style="display:flex;align-items:center;gap:0.4rem;">
                            <h3 style="font-size:1.25rem;font-weight:800;color:#ffffff;margin:0;">
                                <?= e($packageData['host']['name']) ?>
                            </h3>
                            <span style="display:inline-flex;align-items:center;justify-content:center;width:18px;height:18px;border-radius:50%;background:#10b981;color:#ffffff;font-size:11px;font-weight:800;" title="Verified Licensed Guide">
                                ✓
                            </span>
                        </div>
                        <div style="color:#94a3b8;font-size:0.92rem;margin-top:2px;">
                            <?= e($packageData['host']['role']) ?>
                        </div>
                        <div style="color:#c4b5fd;font-size:0.85rem;font-weight:700;margin-top:4px;">
                            <?= e($packageData['host']['badge']) ?> • <?= number_format($packageData['host']['rating'], 1) ?> ★ (<?= (int)$packageData['host']['reviews_count'] ?> reviews) • <?= (int)$packageData['host']['tours_count'] ?>+ tours hosted
                        </div>
                    </div>
                </div>

                <!-- Host Bio Quote -->
                <div style="margin:1rem 0 1.25rem 0;padding:0.75rem 1rem;border-left:3px solid #8b5cf6;background:rgba(139,92,246,0.06);border-radius:0 8px 8px 0;">
                    <p style="font-style:italic;color:#e2e8f0;font-size:0.93rem;line-height:1.6;margin:0;">
                        "<?= e($packageData['host']['bio']) ?>"
                    </p>
                </div>

                <!-- Host Messaging Action Button -->
                <a href="<?= e(url('/admin/messages/' . (int) $packageData['host']['id'])) ?>" style="display:flex;align-items:center;justify-content:center;gap:0.5rem;width:100%;padding:0.75rem 1rem;background:rgba(139,92,246,0.1);border:1.5px solid #8b5cf6;border-radius:12px;color:#c4b5fd;font-weight:700;font-size:0.95rem;text-decoration:none;transition:all 0.2s ease;">
                    <span style="font-size:1rem;">💬</span> Message Guide Host
                </a>
            </div>

            <!-- ================================================================= -->
            <!-- 3. ALL-IN-ONE INCLUSIONS & EXCLUSIONS (IMAGE 3 REPLICA)           -->
            <!-- ================================================================= -->
            <div class="panel" style="margin-bottom:1.5rem;background:#0f172a;border:1px solid rgba(255,255,255,0.1);border-radius:16px;padding:1.5rem;box-shadow:0 10px 25px rgba(0,0,0,0.35);">
                <div style="margin-bottom:1.25rem;">
                    <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.25rem;">
                        <span style="font-size:1.3rem;">🎁</span>
                        <h3 style="font-size:1.25rem;font-weight:800;color:#ffffff;margin:0;">All-In-One Inclusions</h3>
                    </div>
                    <p style="color:#94a3b8;font-size:0.88rem;margin:0;">
                        Everything included in your single bundle reservation:
                    </p>
                </div>

                <!-- Bundled Items Stack -->
                <div style="display:flex;flex-direction:column;gap:0.75rem;">
                    <!-- A. Attractions -->
                    <?php foreach ($packageData['inclusions']['attractions'] as $att): ?>
                        <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);border-radius:14px;padding:0.9rem 1.1rem;display:flex;align-items:center;gap:1rem;">
                            <div style="width:46px;height:46px;border-radius:12px;background:#fdf2f8;color:#ec4899;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0;">
                                💧
                            </div>
                            <div style="flex:1;min-width:0;">
                                <div style="display:flex;align-items:center;justify-content:space-between;gap:0.5rem;flex-wrap:wrap;">
                                    <strong style="color:#f8fafc;font-size:0.98rem;"><?= e($att['title']) ?></strong>
                                    <span style="background:#fdf2f8;color:#db2777;font-size:0.72rem;font-weight:800;padding:2px 10px;border-radius:12px;text-transform:uppercase;">
                                        Attraction
                                    </span>
                                </div>
                                <div style="color:#94a3b8;font-size:0.82rem;margin-top:3px;">
                                    <?= e($att['summary'] ?? ($att['area'] ?? 'Badian Ecological Park entrance & conservation ticket')) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <!-- B. Things to Do -->
                    <?php foreach ($packageData['inclusions']['things_to_do'] as $actIdx => $act): ?>
                        <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);border-radius:14px;padding:0.9rem 1.1rem;display:flex;align-items:center;gap:1rem;">
                            <div style="width:46px;height:46px;border-radius:12px;background:#f5f3ff;color:#8b5cf6;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0;">
                                <?= $actIdx % 2 === 0 ? '🧭' : '🐟' ?>
                            </div>
                            <div style="flex:1;min-width:0;">
                                <div style="display:flex;align-items:center;justify-content:space-between;gap:0.5rem;flex-wrap:wrap;">
                                    <strong style="color:#f8fafc;font-size:0.98rem;"><?= e($act['title']) ?></strong>
                                    <span style="background:#f5f3ff;color:#7c3aed;font-size:0.72rem;font-weight:800;padding:2px 10px;border-radius:12px;text-transform:uppercase;">
                                        Things to Do
                                    </span>
                                </div>
                                <div style="color:#94a3b8;font-size:0.82rem;margin-top:3px;">
                                    <?= e($act['summary'] ?? ($act['area'] ?? 'Guided outdoor session with licensed guides & equipment')) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <!-- C. Hotel & Stays (if included) -->
                    <?php if ($packageData['hotel_included'] && !empty($packageData['hotel'])): ?>
                        <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);border-radius:14px;padding:0.9rem 1.1rem;display:flex;align-items:center;gap:1rem;">
                            <div style="width:46px;height:46px;border-radius:12px;background:#fffbeb;color:#f59e0b;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0;">
                                🛏️
                            </div>
                            <div style="flex:1;min-width:0;">
                                <div style="display:flex;align-items:center;justify-content:space-between;gap:0.5rem;flex-wrap:wrap;">
                                    <strong style="color:#f8fafc;font-size:0.98rem;"><?= e($packageData['hotel']['hotel_name'] ?? 'Sea Breeze Oceanfront Resort') ?></strong>
                                    <span style="background:#fef3c7;color:#d97706;font-size:0.72rem;font-weight:800;padding:2px 10px;border-radius:12px;text-transform:uppercase;">
                                        Hotel &amp; Stays
                                    </span>
                                </div>
                                <div style="color:#94a3b8;font-size:0.82rem;margin-top:3px;">
                                    <?= $packageData['duration_nights'] ?> Nights • <?= e($packageData['hotel']['room_name'] ?? 'Deluxe Queen Room') ?> with balcony &amp; pool access
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- D. Vehicle Rental (if included) -->
                    <?php if ($packageData['rental_included'] && !empty($packageData['rental'])): ?>
                        <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);border-radius:14px;padding:0.9rem 1.1rem;display:flex;align-items:center;gap:1rem;">
                            <div style="width:46px;height:46px;border-radius:12px;background:#eff6ff;color:#3b82f6;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0;">
                                🛵
                            </div>
                            <div style="flex:1;min-width:0;">
                                <div style="display:flex;align-items:center;justify-content:space-between;gap:0.5rem;flex-wrap:wrap;">
                                    <strong style="color:#f8fafc;font-size:0.98rem;">
                                        <?= e($packageData['rental']['vehicle_name'] ?? ($packageData['rental']['make'] . ' ' . $packageData['rental']['model'])) ?>
                                    </strong>
                                    <span style="background:#dbeafe;color:#2563eb;font-size:0.72rem;font-weight:800;padding:2px 10px;border-radius:12px;text-transform:uppercase;">
                                        Vehicle Rental
                                    </span>
                                </div>
                                <div style="color:#94a3b8;font-size:0.82rem;margin-top:3px;">
                                    <?= $packageData['duration_days'] ?>-Day rental with 2 helmets, phone mount, and insurance
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- E. Included Perks -->
                    <?php foreach ($packageData['custom_perks'] as $cp): ?>
                        <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);border-radius:14px;padding:0.9rem 1.1rem;display:flex;align-items:center;gap:1rem;">
                            <div style="width:46px;height:46px;border-radius:12px;background:#ecfdf5;color:#10b981;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0;">
                                🛡️
                            </div>
                            <div style="flex:1;min-width:0;">
                                <div style="display:flex;align-items:center;justify-content:space-between;gap:0.5rem;flex-wrap:wrap;">
                                    <strong style="color:#f8fafc;font-size:0.98rem;"><?= e(explode('(', $cp)[0]) ?></strong>
                                    <span style="background:#d1fae5;color:#059669;font-size:0.72rem;font-weight:800;padding:2px 10px;border-radius:12px;text-transform:uppercase;">
                                        Included Perk
                                    </span>
                                </div>
                                <div style="color:#94a3b8;font-size:0.82rem;margin-top:3px;">
                                    <?= str_contains($cp, '(') ? e(rtrim(explode('(', $cp, 2)[1] ?? '', ')')) : 'Full tourism coordination, safety gear, and licensed assistance' ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- What's Not Included Section -->
                <div style="margin-top:1.75rem;padding-top:1.25rem;border-top:1px solid rgba(255,255,255,0.08);">
                    <h4 style="font-size:0.98rem;font-weight:700;color:#cbd5e1;margin:0 0 0.85rem 0;">What's Not Included:</h4>
                    <div style="display:flex;flex-direction:column;gap:0.5rem;">
                        <?php foreach ($packageData['exclusions'] as $ex): ?>
                            <div style="display:flex;align-items:flex-start;gap:0.5rem;font-size:0.88rem;color:#94a3b8;">
                                <span style="color:#ef4444;font-size:1rem;line-height:1;font-weight:bold;flex-shrink:0;">⊗</span>
                                <span><?= e($ex) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- ================================================================= -->
            <!-- 4. DAY-BY-DAY ITINERARY (IMAGE 4 REPLICA)                         -->
            <!-- ================================================================= -->
            <div class="panel" style="margin-bottom:1.5rem;background:#0f172a;border:1px solid rgba(255,255,255,0.1);border-radius:16px;padding:1.5rem;box-shadow:0 10px 25px rgba(0,0,0,0.35);">
                <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:1.25rem;">
                    <span style="font-size:1.3rem;">📅</span>
                    <h3 style="font-size:1.25rem;font-weight:800;color:#ffffff;margin:0;">Day-by-Day Itinerary</h3>
                </div>

                <!-- Day Tabs Navigation -->
                <div style="display:flex;gap:0.5rem;margin-bottom:1.5rem;flex-wrap:wrap;">
                    <?php foreach ($packageData['itinerary'] as $idx => $dayItem): ?>
                        <button type="button" class="pkg-itinerary-tab" data-day="<?= (int) ($dayItem['day'] ?? ($idx + 1)) ?>" onclick="switchPkgDay(<?= (int) ($dayItem['day'] ?? ($idx + 1)) ?>)" style="padding:7px 18px;border-radius:20px;font-size:0.85rem;font-weight:700;cursor:pointer;border:1px solid <?= $idx === 0 ? '#8b5cf6' : 'rgba(255,255,255,0.1)' ?>;background:<?= $idx === 0 ? '#8b5cf6' : 'rgba(255,255,255,0.06)' ?>;color:<?= $idx === 0 ? '#ffffff' : '#94a3b8' ?>;transition:all 0.2s ease;">
                            Day <?= (int) ($dayItem['day'] ?? ($idx + 1)) ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <!-- Day Panes Container -->
                <?php foreach ($packageData['itinerary'] as $idx => $dayItem):
                    $curDayNum = (int) ($dayItem['day'] ?? ($idx + 1));
                ?>
                    <div class="pkg-day-pane" data-day="<?= $curDayNum ?>" style="display:<?= $idx === 0 ? 'block' : 'none' ?>;">
                        <!-- Day Title -->
                        <h4 style="font-size:1.15rem;font-weight:800;color:#ffffff;margin:0 0 0.4rem 0;">
                            <?= e($dayItem['title']) ?>
                        </h4>

                        <!-- Day Narrative Summary -->
                        <p style="color:#94a3b8;font-size:0.92rem;line-height:1.6;margin:0 0 1.5rem 0;">
                            <?= e($dayItem['description']) ?>
                        </p>

                        <!-- Timed Schedule Timeline -->
                        <?php if (!empty($dayItem['schedule'])): ?>
                            <div style="position:relative;padding-left:1.5rem;margin-bottom:1.5rem;">
                                <!-- Connected vertical purple line -->
                                <div style="position:absolute;left:5px;top:8px;bottom:8px;width:2px;background:rgba(139,92,246,0.35);"></div>

                                <div style="display:flex;flex-direction:column;gap:1.25rem;">
                                    <?php foreach ($dayItem['schedule'] as $slot): ?>
                                        <div style="position:relative;">
                                            <!-- Purple Bullet Dot -->
                                            <div style="position:absolute;left:-1.5rem;top:3px;width:12px;height:12px;border-radius:50%;background:#8b5cf6;box-shadow:0 0 8px rgba(139,92,246,0.8);"></div>
                                            <!-- Time Badge -->
                                            <div style="color:#c4b5fd;font-weight:800;font-size:0.82rem;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:2px;">
                                                <?= e($slot['time'] ?? 'Flexible') ?>
                                            </div>
                                            <!-- Activity Description -->
                                            <div style="color:#f8fafc;font-size:0.95rem;font-weight:600;line-height:1.4;">
                                                <?= e($slot['activity'] ?? '') ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Day Highlights -->
                        <?php if (!empty($dayItem['highlights'])): ?>
                            <div style="background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.06);border-radius:12px;padding:1.1rem;">
                                <h5 style="font-size:0.95rem;font-weight:700;color:#f8fafc;margin:0 0 0.75rem 0;">Day Highlights:</h5>
                                <div style="display:flex;flex-direction:column;gap:0.5rem;">
                                    <?php foreach ($dayItem['highlights'] as $hl): ?>
                                        <div style="display:flex;align-items:flex-start;gap:0.5rem;font-size:0.88rem;color:#cbd5e1;line-height:1.5;">
                                            <span style="color:#10b981;font-weight:bold;flex-shrink:0;">✓</span>
                                            <span><?= e($hl) ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- ================================================================= -->
            <!-- 5. HOTEL DETAILS, VEHICLE & POLICIES (IMAGE 5 REPLICA)            -->
            <!-- ================================================================= -->
            <div class="panel" style="margin-bottom:1.5rem;background:#0f172a;border:1px solid rgba(255,255,255,0.1);border-radius:16px;padding:1.5rem;box-shadow:0 10px 25px rgba(0,0,0,0.35);">
                <!-- A. Included Accommodation Details -->
                <div style="margin-bottom:2rem;">
                    <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:1rem;">
                        <span style="font-size:1.3rem;">🏨</span>
                        <h3 style="font-size:1.25rem;font-weight:800;color:#ffffff;margin:0;">Included Accommodation Details</h3>
                    </div>

                    <?php if ($packageData['hotel_included'] && !empty($packageData['hotel'])): ?>
                        <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);border-radius:14px;padding:1.25rem;">
                            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:0.75rem;margin-bottom:0.75rem;">
                                <div>
                                    <h4 style="font-size:1.15rem;font-weight:800;color:#ffffff;margin:0 0 0.25rem 0;">
                                        <?= e($packageData['hotel']['hotel_name'] ?? 'Sea Breeze Oceanfront Resort') ?>
                                    </h4>
                                    <div style="color:#c4b5fd;font-size:0.9rem;font-weight:600;">
                                        <?= e($packageData['hotel']['room_name'] ?? 'Deluxe Queen Room') ?> • <?= $packageData['duration_nights'] ?> Nights
                                    </div>
                                    <div style="color:#94a3b8;font-size:0.8rem;margin-top:2px;">
                                        📍 <?= e($packageData['hotel']['hotel_area'] ?? 'Moalboal, Cebu') ?> • Verified Partner: <?= e($packageData['hotel']['partner_name'] ?? 'Hospitality Provider') ?>
                                    </div>
                                </div>
                                <span style="background:rgba(245,158,11,0.15);color:#fbbf24;border:1px solid rgba(245,158,11,0.3);font-size:0.75rem;font-weight:700;padding:3px 10px;border-radius:12px;">
                                    Max <?= (int) ($packageData['hotel']['max_guests'] ?? 2) ?> Guests
                                </span>
                            </div>

                            <!-- Amenity Badges -->
                            <div style="display:flex;flex-wrap:wrap;gap:0.45rem;margin:1rem 0;">
                                <?php foreach ($packageData['hotel_amenities'] as $ham): ?>
                                    <span style="background:rgba(255,255,255,0.05);color:#cbd5e1;border:1px solid rgba(255,255,255,0.1);border-radius:20px;padding:3px 10px;font-size:0.78rem;">
                                        ✓ <?= e($ham) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>

                            <!-- Schedule Cards (Check-in & Check-out) -->
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;margin-top:1rem;">
                                <div style="background:rgba(139,92,246,0.08);border:1px solid rgba(139,92,246,0.25);border-radius:10px;padding:0.75rem 1rem;">
                                    <span style="color:#c4b5fd;font-size:0.7rem;font-weight:800;letter-spacing:0.5px;text-transform:uppercase;display:block;">Check-in Schedule</span>
                                    <strong style="color:#ffffff;font-size:0.88rem;display:block;margin-top:3px;">
                                        Day 1 · From <?= date('g:i A', strtotime((string) ($packageData['hotel']['check_in_time'] ?? '14:00:00'))) ?>
                                    </strong>
                                    <span style="color:#94a3b8;font-size:0.75rem;display:block;margin-top:2px;">Front desk key handover</span>
                                </div>
                                <div style="background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.25);border-radius:10px;padding:0.75rem 1rem;">
                                    <span style="color:#6ee7b7;font-size:0.7rem;font-weight:800;letter-spacing:0.5px;text-transform:uppercase;display:block;">Check-out Schedule</span>
                                    <strong style="color:#ffffff;font-size:0.88rem;display:block;margin-top:3px;">
                                        Day <?= $packageData['duration_days'] ?> · Until <?= date('g:i A', strtotime((string) ($packageData['hotel']['check_out_time'] ?? '12:00:00'))) ?>
                                    </strong>
                                    <span style="color:#94a3b8;font-size:0.75rem;display:block;margin-top:2px;">Room clearance &amp; departure</span>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div style="background:rgba(255,255,255,0.02);border:1px dashed rgba(255,255,255,0.15);border-radius:12px;padding:1rem 1.25rem;color:#94a3b8;font-size:0.9rem;">
                            ℹ️ <strong>No Hotel Stay Bundled</strong> — This travel package is designed as a day expedition or travelers arrange their own accommodations.
                        </div>
                    <?php endif; ?>
                </div>

                <!-- B. Included Rental Vehicle -->
                <div style="margin-bottom:2rem;padding-top:1.5rem;border-top:1px solid rgba(255,255,255,0.08);">
                    <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:1rem;">
                        <span style="font-size:1.3rem;">🛵</span>
                        <h3 style="font-size:1.25rem;font-weight:800;color:#ffffff;margin:0;">Included Rental Vehicle</h3>
                    </div>

                    <?php if ($packageData['rental_included'] && !empty($packageData['rental'])): ?>
                        <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);border-radius:14px;padding:1.25rem;">
                            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:0.75rem;margin-bottom:0.75rem;">
                                <div>
                                    <h4 style="font-size:1.15rem;font-weight:800;color:#ffffff;margin:0 0 0.25rem 0;">
                                        <?= e($packageData['rental']['vehicle_name'] ?? ($packageData['rental']['make'] . ' ' . $packageData['rental']['model'])) ?>
                                    </h4>
                                    <div style="color:#60a5fa;font-size:0.9rem;font-weight:600;">
                                        <?= ucfirst(e($packageData['rental']['vehicle_type'] ?? 'Scooter')) ?> • <?= $packageData['duration_days'] ?> Days Rental
                                    </div>
                                    <div style="color:#94a3b8;font-size:0.8rem;margin-top:2px;">
                                        📍 <?= e($packageData['rental']['location'] ?? 'Cebu, Philippines') ?> • Partner: <?= e($packageData['rental']['partner_name'] ?? 'Rental Partner') ?>
                                    </div>
                                </div>
                                <span style="background:rgba(59,130,246,0.15);color:#93c5fd;border:1px solid rgba(59,130,246,0.3);font-size:0.75rem;font-weight:700;padding:3px 10px;border-radius:12px;">
                                    Automatic Transmission
                                </span>
                            </div>

                            <!-- Vehicle Specs Pills -->
                            <div style="display:flex;flex-wrap:wrap;gap:0.45rem;margin:1rem 0;">
                                <?php foreach ($packageData['rental_specs'] as $rsp): ?>
                                    <span style="background:rgba(255,255,255,0.05);color:#cbd5e1;border:1px solid rgba(255,255,255,0.1);border-radius:20px;padding:3px 10px;font-size:0.78rem;">
                                        ✓ <?= e($rsp) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>

                            <!-- Schedule Cards (Handover & Return) -->
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;margin-top:1rem;">
                                <div style="background:rgba(59,130,246,0.08);border:1px solid rgba(59,130,246,0.25);border-radius:10px;padding:0.75rem 1rem;">
                                    <span style="color:#93c5fd;font-size:0.7rem;font-weight:800;letter-spacing:0.5px;text-transform:uppercase;display:block;">Handover / Pickup</span>
                                    <strong style="color:#ffffff;font-size:0.88rem;display:block;margin-top:3px;">Day 1 · 01:00 PM</strong>
                                    <span style="color:#94a3b8;font-size:0.75rem;display:block;margin-top:2px;">Safety briefing &amp; helmet fitting</span>
                                </div>
                                <div style="background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.25);border-radius:10px;padding:0.75rem 1rem;">
                                    <span style="color:#6ee7b7;font-size:0.7rem;font-weight:800;letter-spacing:0.5px;text-transform:uppercase;display:block;">Drop-off / Return</span>
                                    <strong style="color:#ffffff;font-size:0.88rem;display:block;margin-top:3px;">Day <?= $packageData['duration_days'] ?> · 12:00 PM</strong>
                                    <span style="color:#94a3b8;font-size:0.75rem;display:block;margin-top:2px;">Unit inspection &amp; clearance</span>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div style="background:rgba(255,255,255,0.02);border:1px dashed rgba(255,255,255,0.15);border-radius:12px;padding:1rem 1.25rem;color:#94a3b8;font-size:0.9rem;">
                            ℹ️ <strong>No Rental Vehicle Bundled</strong> — Travelers commute via walking, arranged tour van transfers, or local public transport.
                        </div>
                    <?php endif; ?>
                </div>

                <!-- C. Policies & Requirements -->
                <div style="padding-top:1.5rem;border-top:1px solid rgba(255,255,255,0.08);">
                    <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:1rem;">
                        <span style="font-size:1.3rem;">📋</span>
                        <h3 style="font-size:1.25rem;font-weight:800;color:#ffffff;margin:0;">Policies &amp; Requirements</h3>
                    </div>

                    <!-- Cancellation Policy Card -->
                    <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);border-radius:12px;padding:1rem;margin-bottom:1rem;">
                        <span style="color:#c4b5fd;font-size:0.75rem;font-weight:800;letter-spacing:0.5px;text-transform:uppercase;display:block;margin-bottom:0.35rem;">
                            Cancellation Policy
                        </span>
                        <p style="color:#cbd5e1;font-size:0.9rem;line-height:1.6;margin:0;">
                            <?= e($packageData['cancellation_policy']) ?>
                        </p>
                    </div>

                    <!-- Requirements Checklist -->
                    <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);border-radius:12px;padding:1rem;">
                        <span style="color:#6ee7b7;font-size:0.75rem;font-weight:800;letter-spacing:0.5px;text-transform:uppercase;display:block;margin-bottom:0.5rem;">
                            Trip Requirements &amp; Reminders
                        </span>
                        <div style="display:flex;flex-direction:column;gap:0.5rem;">
                            <?php foreach ($packageData['requirements'] as $req): ?>
                                <div style="display:flex;align-items:flex-start;gap:0.5rem;font-size:0.88rem;color:#cbd5e1;line-height:1.5;">
                                    <span style="color:#10b981;font-weight:bold;flex-shrink:0;">✓</span>
                                    <span><?= e($req) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- JavaScript for switching Itinerary Day tabs -->
            <script>
            function switchPkgDay(dayNumber) {
                document.querySelectorAll('.pkg-itinerary-tab').forEach(function(btn) {
                    if (btn.getAttribute('data-day') === String(dayNumber)) {
                        btn.style.background = '#8b5cf6';
                        btn.style.color = '#ffffff';
                        btn.style.borderColor = '#8b5cf6';
                    } else {
                        btn.style.background = 'rgba(255,255,255,0.06)';
                        btn.style.color = '#94a3b8';
                        btn.style.borderColor = 'rgba(255,255,255,0.1)';
                    }
                });
                document.querySelectorAll('.pkg-day-pane').forEach(function(pane) {
                    if (pane.getAttribute('data-day') === String(dayNumber)) {
                        pane.style.display = 'block';
                    } else {
                        pane.style.display = 'none';
                    }
                });
            }
            </script>

        <?php else: ?>
            <!-- ================================================================= -->
            <!-- STANDARD SINGLE TOUR / ATTRACTION LISTING VIEW                    -->
            <!-- ================================================================= -->
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
        <?php endif; ?>
    </div>

    <!-- Sidebar / Decision & Metadata Column -->
    <div class="admin-vehicle-sidebar" style="min-width:0;">
        <?php if ($isPackage):
            $pkgApproval = \App\Models\PackageService::getProviderApprovalSummary((int) $listing['id']);
            $pStatus = $pkgApproval['overall_status'];
        ?>
            <div class="panel" style="background:#0f172a;border:1px solid rgba(255,255,255,0.1);border-radius:14px;margin-bottom:1.5rem;overflow:hidden;">
                <div class="panel-head" style="padding:1rem 1.25rem;border-bottom:1px solid rgba(255,255,255,0.08);display:flex;align-items:center;justify-content:space-between;">
                    <h3 style="margin:0;font-size:1.05rem;font-weight:700;color:#ffffff;">Provider Approval Status</h3>
                    <?php if ($pStatus === 'agreed'): ?>
                        <span style="background:#059669;color:#ffffff;font-size:0.75rem;font-weight:700;padding:2px 8px;border-radius:12px;">✓ Agreed</span>
                    <?php elseif ($pStatus === 'rejected'): ?>
                        <span style="background:#dc2626;color:#ffffff;font-size:0.75rem;font-weight:700;padding:2px 8px;border-radius:12px;">✕ Rejected</span>
                    <?php else: ?>
                        <span style="background:#d97706;color:#ffffff;font-size:0.75rem;font-weight:700;padding:2px 8px;border-radius:12px;">⏳ Pending</span>
                    <?php endif; ?>
                </div>
                <div class="panel-body" style="padding:1.25rem;">
                    <?php if (empty($pkgApproval['services'])): ?>
                        <div style="font-size:0.85rem;color:#94a3b8;">
                            Guide-only package (no external accommodation or rental vehicle required).
                        </div>
                    <?php else: ?>
                        <div style="display:flex;flex-direction:column;gap:0.75rem;">
                            <?php foreach ($pkgApproval['services'] as $s):
                                $sType = ucfirst((string) ($s['service_type'] ?? 'Service'));
                                $sStatus = strtolower((string) ($s['status'] ?? 'pending'));
                                $pName = (string) ($s['provider_name'] ?? 'Provider Partner');
                                $itemLabel = ($s['service_type'] === 'hotel')
                                    ? (($s['hotel_name'] ?? 'Hotel') . ' (' . ($s['room_name'] ?? 'Room') . ')')
                                    : trim(($s['vehicle_make'] ?? '') . ' ' . ($s['vehicle_model'] ?? ''));
                            ?>
                                <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);border-radius:8px;padding:0.75rem;">
                                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:3px;">
                                        <strong style="color:#ffffff;font-size:0.85rem;"><?= e($sType) ?>: <?= e($pName) ?></strong>
                                        <?php if ($sStatus === 'agreed' || $sStatus === 'approved'): ?>
                                            <span style="color:#34d399;font-size:0.75rem;font-weight:700;">✓ Agreed</span>
                                        <?php elseif ($sStatus === 'rejected'): ?>
                                            <span style="color:#f87171;font-size:0.75rem;font-weight:700;">✕ Declined</span>
                                        <?php else: ?>
                                            <span style="color:#fbbf24;font-size:0.75rem;font-weight:700;">⏳ Pending</span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="font-size:0.78rem;color:#94a3b8;"><?= e($itemLabel) ?></div>
                                    <?php if ($sStatus === 'rejected' && !empty($s['rejection_reason'])): ?>
                                        <div style="font-size:0.75rem;color:#fca5a5;margin-top:4px;">
                                            Reason: <?= e($s['rejection_reason']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Decision Box -->
        <div class="panel" style="margin-bottom:1.5rem;background:#0f172a;border:1px solid rgba(255,255,255,0.1);border-radius:14px;">
            <div class="panel-head" style="border-bottom:1px solid rgba(255,255,255,0.08);padding:1rem 1.25rem;">
                <h3 style="margin:0;font-size:1.1rem;font-weight:700;color:#ffffff;">Review Decision</h3>
            </div>
            <div class="panel-body" style="padding:1.25rem;">
                <?php if ($lStatus !== 'approved'): ?>
                    <form method="post" action="<?= e(url('/admin/guide-listings/' . (int) $listing['id'] . '/approve')) ?>" style="margin-bottom:1rem;" onsubmit="return confirm('Approve this listing? It will immediately become visible to travelers.');">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;font-weight:700;padding:0.75rem;">
                            ✓ Approve &amp; Publish Listing
                        </button>
                    </form>
                <?php else: ?>
                    <div style="background:rgba(16,185,129,0.12);color:#34d399;padding:0.75rem 1rem;border-radius:8px;font-size:0.875rem;margin-bottom:1rem;border:1px solid rgba(16,185,129,0.3);">
                        ✓ This listing is currently <strong>Approved</strong> and published for travelers.
                    </div>
                <?php endif; ?>

                <form method="post" action="<?= e(url('/admin/guide-listings/' . (int) $listing['id'] . '/reject')) ?>" onsubmit="return confirm('Confirm rejection of this guide listing?');">
                    <?= csrf_field() ?>
                    <label for="side_reason" class="hint" style="display:block;margin-bottom:0.4rem;font-weight:600;color:#cbd5e1;">
                        <?= $lStatus === 'approved' ? 'Unpublish / Reject Reason' : 'Rejection Reason (optional)' ?>
                    </label>
                    <textarea class="input" id="side_reason" name="reason" rows="3" placeholder="Provide notes or feedback for the guide..." style="background:#1e293b;border-color:rgba(255,255,255,0.15);color:#ffffff;"></textarea>
                    <button type="submit" class="btn btn-ghost" style="width:100%;margin-top:0.65rem;color:#f87171;border:1px solid rgba(239,68,68,0.3);justify-content:center;font-weight:600;">
                        <?= $lStatus === 'approved' ? 'Unpublish Listing' : 'Reject Listing' ?>
                    </button>
                </form>
            </div>
        </div>

        <?php if ($isPackage && !empty($packageData)): ?>
            <!-- Package Quick Summary Box -->
            <div class="panel" style="margin-bottom:1.5rem;background:#0f172a;border:1px solid rgba(255,255,255,0.1);border-radius:14px;">
                <div class="panel-head" style="border-bottom:1px solid rgba(255,255,255,0.08);padding:1rem 1.25rem;">
                    <h3 style="margin:0;font-size:1.1rem;font-weight:700;color:#ffffff;">Package Financials</h3>
                </div>
                <div class="panel-body" style="padding:1.25rem;font-size:0.88rem;display:flex;flex-direction:column;gap:0.75rem;">
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <span class="hint" style="color:#94a3b8;">Package Total:</span>
                        <strong style="color:#34d399;font-size:1.1rem;"><?= money($packageData['price']) ?></strong>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <span class="hint" style="color:#94a3b8;">Duration:</span>
                        <strong style="color:#f8fafc;"><?= e($packageData['duration_code']) ?> (<?= e($packageData['duration_text']) ?>)</strong>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <span class="hint" style="color:#94a3b8;">Max Group Size:</span>
                        <strong style="color:#f8fafc;"><?= e($packageData['group_size']) ?> travelers</strong>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <span class="hint" style="color:#94a3b8;">Special Highlight:</span>
                        <span style="color:#fbbf24;font-weight:700;"><?= e($packageData['badge']) ?></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <span class="hint" style="color:#94a3b8;">Assurance:</span>
                        <span style="color:#10b981;font-weight:700;"><?= e($packageData['assurance']) ?></span>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Guide Information Box -->
        <div class="panel" style="margin-bottom:1.5rem;background:#0f172a;border:1px solid rgba(255,255,255,0.1);border-radius:14px;">
            <div class="panel-head" style="border-bottom:1px solid rgba(255,255,255,0.08);padding:1rem 1.25rem;">
                <h3 style="margin:0;font-size:1.1rem;font-weight:700;color:#ffffff;">Guide Information</h3>
            </div>
            <div class="panel-body" style="padding:1.25rem;font-size:0.875rem;">
                <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:1rem;">
                    <?php if (!empty($guide['avatar'])): ?>
                        <img src="<?= e(url($guide['avatar'])) ?>" alt="" style="width:48px;height:48px;border-radius:50%;object-fit:cover;border:1px solid var(--border);">
                    <?php else: ?>
                        <div style="width:48px;height:48px;border-radius:50%;background:linear-gradient(135deg,#8b5cf6,#3b82f6);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1.1rem;">
                            <?= strtoupper(substr((string) ($guide['name'] ?? 'G'), 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                    <div>
                        <strong style="font-size:1rem;color:#f8fafc;"><?= e($guide['name'] ?? 'Tour Guide') ?></strong>
                        <div class="hint" style="color:#94a3b8;"><?= e($guide['email'] ?? '') ?></div>
                    </div>
                </div>

                <div style="display:flex;flex-direction:column;gap:0.5rem;border-top:1px solid rgba(255,255,255,0.08);padding-top:0.75rem;">
                    <div>
                        <span class="hint" style="color:#94a3b8;">Role:</span>
                        <strong style="color:#f8fafc;"><?= e(ucfirst((string) ($guide['role'] ?? 'guide'))) ?></strong>
                    </div>
                    <?php if (!empty($guide['phone'])): ?>
                        <div>
                            <span class="hint" style="color:#94a3b8;">Phone:</span>
                            <span style="color:#f8fafc;"><?= e($guide['phone']) ?></span>
                        </div>
                    <?php endif; ?>
                    <div>
                        <span class="hint" style="color:#94a3b8;">Member since:</span>
                        <span style="color:#f8fafc;"><?= !empty($guide['created_at']) ? e(date('M j, Y', strtotime((string) $guide['created_at']))) : '—' ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Audit & Tracking Box -->
        <div class="panel" style="background:#0f172a;border:1px solid rgba(255,255,255,0.1);border-radius:14px;">
            <div class="panel-head" style="border-bottom:1px solid rgba(255,255,255,0.08);padding:1rem 1.25rem;">
                <h3 style="margin:0;font-size:1.1rem;font-weight:700;color:#ffffff;">Audit Information</h3>
            </div>
            <div class="panel-body" style="padding:1.25rem;font-size:0.85rem;display:flex;flex-direction:column;gap:0.6rem;">
                <div>
                    <span class="hint" style="color:#94a3b8;">Listing ID:</span>
                    <strong style="color:#f8fafc;">#<?= (int) $listing['id'] ?></strong>
                </div>
                <div>
                    <span class="hint" style="color:#94a3b8;">Created:</span>
                    <span style="color:#f8fafc;"><?= !empty($listing['created_at']) ? e(date('M j, Y g:ia', strtotime((string) $listing['created_at']))) : '—' ?></span>
                </div>
                <?php if (!empty($listing['submitted_at'])): ?>
                    <div>
                        <span class="hint" style="color:#94a3b8;">Submitted for review:</span>
                        <span style="color:#f8fafc;"><?= e(date('M j, Y g:ia', strtotime((string) $listing['submitted_at']))) ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($listing['reviewed_at'])): ?>
                    <div>
                        <span class="hint" style="color:#94a3b8;">Reviewed:</span>
                        <span style="color:#f8fafc;"><?= e(date('M j, Y g:ia', strtotime((string) $listing['reviewed_at']))) ?></span>
                    </div>
                    <?php if ($reviewer !== null): ?>
                        <div>
                            <span class="hint" style="color:#94a3b8;">Reviewed by:</span>
                            <strong style="color:#f8fafc;"><?= e($reviewer['name']) ?></strong> (<span style="color:#94a3b8;"><?= e($reviewer['email']) ?></span>)
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
