<?php
/**
 * Public Guide Profile View
 *
 * @var string $title
 * @var array<string, mixed> $guide
 * @var array<string, mixed> $stats
 * @var array<int, array<string, mixed>> $listings
 * @var array<int, array<string, mixed>> $reviews
 */
$currentUser = auth_user();
$guide = $guide ?? [];
$stats = $stats ?? [];
$listings = $listings ?? [];
$reviews = $reviews ?? [];

$isOwnProfile = $currentUser && (int) $currentUser['id'] === (int) ($guide['id'] ?? 0);
$isVerified = ($guide['guide_status'] ?? '') === 'approved' || !empty($guide['is_verified']);

$name = trim((string) ($guide['name'] ?? ''));
$displayName = $name !== '' ? $name : 'Not Provided';

$location = trim((string) ($guide['location'] ?? ''));
$displayLocation = $location !== '' ? $location : 'Not Provided';

$experience = trim((string) ($guide['experience'] ?? ''));
$displayExperience = $experience !== '' ? $experience : 'Not Provided';

$languages = trim((string) ($guide['languages'] ?? ''));
$displayLanguages = $languages !== '' ? $languages : 'Not Provided';

$specialtiesRaw = trim((string) ($guide['specialties'] ?? ''));
$specialties = array_values(array_filter(array_map('trim', explode(',', $specialtiesRaw))));

$hasCreatedAt = !empty($guide['created_at']) && strtotime((string) $guide['created_at']) !== false;
$displayMemberSince = $hasCreatedAt ? date('F Y', strtotime((string) $guide['created_at'])) : 'Not Provided';

$rating = (float) ($guide['rating'] ?? 0);
$reviewCount = (int) ($guide['review_count'] ?? 0);
$displayRating = $rating > 0 ? '⭐ ' . number_format($rating, 1) . ' / 5.0' . ($reviewCount > 0 ? ' (' . $reviewCount . ')' : '') : 'Not Provided';

$bio = trim((string) ($guide['bio'] ?? ''));
$displayBio = $bio !== '' ? nl2br(e($bio)) : 'Not Provided';

$guideId = (int) ($guide['id'] ?? 0);
$loginUrl = url('/login?redirect=' . rawurlencode('/guides/' . $guideId));
?>
<section class="guide-profile-breadcrumb-bar" style="background:transparent;padding:1.25rem 0 0.85rem;">
    <div class="container">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.75rem;">
            <nav class="breadcrumb" aria-label="Breadcrumb" style="margin:0;font-size:0.92rem;color:var(--ink-400);">
                <a href="<?= e(url('/')) ?>" style="color:var(--ink-500);text-decoration:none;">Home</a>
                <span style="margin:0 0.4rem;color:var(--ink-300);" aria-hidden="true">&gt;</span>
                <a href="<?= e(url('/tour-guides')) ?>" style="color:var(--ink-500);text-decoration:none;">Tour Guides</a>
                <span style="margin:0 0.4rem;color:var(--ink-300);" aria-hidden="true">&gt;</span>
                <span style="color:var(--ink-800);font-weight:600;" aria-current="page"><?= e($displayName) ?></span>
            </nav>
            <?php if ($isOwnProfile): ?>
                <a href="<?= e(url('/profile/edit')) ?>" class="btn btn-ghost" style="font-size:0.9rem;">
                    ✏️ Edit Profile
                </a>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="section" style="padding-top:0.5rem;padding-bottom:3.5rem;">
    <div class="container" style="max-width:960px;">
        <!-- Guide Header Card -->
        <article class="card" style="padding:1.75rem 2rem;margin-bottom:1.5rem;background:#ffffff;border:1px solid var(--line);border-radius:var(--radius);box-shadow:var(--shadow-sm);">
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1.5rem;">
                <div style="display:flex;align-items:center;gap:1.25rem;flex-wrap:wrap;">
                    <div style="position:relative;width:88px;height:88px;flex-shrink:0;">
                        <img src="<?= e(img_src($guide['avatar'] ?? null, 'avatar' . $guideId)) ?>"
                             alt="<?= e($displayName) ?>"
                             style="width:88px;height:88px;border-radius:50%;object-fit:cover;border:3px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,0.08);">
                        <?php if (!empty($guide['online'])): ?>
                            <span class="guide-dir-online" title="Online" style="width:16px;height:16px;right:2px;bottom:2px;"></span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h1 style="margin:0 0 0.4rem;font-size:1.6rem;font-weight:800;color:var(--ink-900);">
                            <?= e($displayName) ?>
                        </h1>
                        <div style="display:flex;align-items:center;gap:0.6rem;flex-wrap:wrap;margin-bottom:0.35rem;">
                            <?php if ($isVerified): ?>
                                <span class="pill pill-confirmed" style="background:#dcfce7;color:#15803d;font-weight:700;">
                                    ✔ Verified Guide
                                </span>
                            <?php endif; ?>
                            <span class="pill" style="background:#e0f2fe;color:#0369a1;font-weight:700;">
                                <?= e(!empty($guide['role']) ? (\App\Models\User::PROVIDER_LABELS[$guide['role']] ?? 'Tour Guide') : 'Not Provided') ?>
                            </span>
                            <span class="hint" style="font-weight:600;display:inline-flex;align-items:center;gap:0.25rem;">
                                📍 <?= e($displayLocation) ?>
                            </span>
                        </div>
                    </div>
                </div>

                <div style="display:flex;align-items:center;gap:0.75rem;">
                    <?php if ($isOwnProfile): ?>
                        <span class="pill pill-confirmed" style="padding:0.6rem 1rem;font-size:0.9rem;">This is your public guide profile</span>
                    <?php elseif ($currentUser): ?>
                        <form method="post" action="<?= e(url('/guides/' . $guideId . '/contact')) ?>" style="margin:0;">
                            <?= csrf_field() ?>
                            <button class="btn btn-primary" type="submit" style="padding:0.65rem 1.4rem;font-size:0.95rem;">
                                💬 Message Guide
                            </button>
                        </form>
                    <?php else: ?>
                        <a href="<?= e($loginUrl) ?>" class="btn btn-primary" style="padding:0.65rem 1.4rem;font-size:0.95rem;">
                            💬 Message Guide
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </article>

        <!-- About the Guide Card -->
        <article class="card" style="padding:1.75rem 2rem;margin-bottom:1.5rem;background:#ffffff;border:1px solid var(--line);border-radius:var(--radius);box-shadow:var(--shadow-sm);">
            <h2 style="font-size:1.25rem;font-weight:800;color:var(--ink-900);margin:0 0 1rem;">About the Guide</h2>
            <p style="font-size:0.98rem;line-height:1.65;color:var(--ink-700);margin:0 0 1.5rem;">
                <?= $displayBio ?>
            </p>

            <!-- 4-Stat Grid -->
            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));gap:1rem;background:var(--sand-50, #f8fafc);border:1px solid var(--line, #e2e8f0);border-radius:12px;padding:1.25rem;">
                <div>
                    <span class="hint" style="display:block;margin-bottom:0.25rem;font-size:0.78rem;text-transform:uppercase;letter-spacing:0.05em;font-weight:700;">Experience</span>
                    <div style="font-size:1.05rem;font-weight:700;color:var(--ink-900);">
                        <?= e($displayExperience) ?>
                    </div>
                </div>
                <div>
                    <span class="hint" style="display:block;margin-bottom:0.25rem;font-size:0.78rem;text-transform:uppercase;letter-spacing:0.05em;font-weight:700;">Languages</span>
                    <div style="font-size:1.05rem;font-weight:700;color:var(--ink-900);">
                        <?= e($displayLanguages) ?>
                    </div>
                </div>
                <div>
                    <span class="hint" style="display:block;margin-bottom:0.25rem;font-size:0.78rem;text-transform:uppercase;letter-spacing:0.05em;font-weight:700;">Rating</span>
                    <div style="font-size:1.05rem;font-weight:700;color:var(--ink-900);">
                        <?= e($displayRating) ?>
                    </div>
                </div>
                <div>
                    <span class="hint" style="display:block;margin-bottom:0.25rem;font-size:0.78rem;text-transform:uppercase;letter-spacing:0.05em;font-weight:700;">Member Since</span>
                    <div style="font-size:1.05rem;font-weight:700;color:var(--ink-900);">
                        <?= e($displayMemberSince) ?>
                    </div>
                </div>
            </div>

            <!-- Specialties Section -->
            <div style="margin-top:1.5rem;">
                <h3 style="font-size:1rem;font-weight:800;color:var(--ink-900);margin:0 0 0.65rem;">Specialties</h3>
                <?php if ($specialties !== []): ?>
                    <div style="display:flex;flex-wrap:wrap;gap:0.5rem;">
                        <?php foreach ($specialties as $spec): ?>
                            <span style="display:inline-flex;align-items:center;padding:0.35rem 0.9rem;border-radius:999px;background:var(--teal-50, #f0fdfa);border:1px solid var(--teal-200, #99f6e4);color:var(--teal-800, #115e59);font-weight:700;font-size:0.85rem;">
                                <?= e($spec) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="hint" style="font-size:0.95rem;color:var(--ink-700);">Not Provided</div>
                <?php endif; ?>
            </div>
        </article>

        <!-- Available Listings Card -->
        <article class="card" style="padding:1.75rem 2rem;margin-bottom:1.5rem;background:#ffffff;border:1px solid var(--line);border-radius:var(--radius);box-shadow:var(--shadow-sm);">
            <h2 style="font-size:1.25rem;font-weight:800;color:var(--ink-900);margin:0 0 1rem;">Available Listings</h2>
            <?php if ($listings === []): ?>
                <div class="empty-state" style="padding:2rem 1rem;">
                    <div class="big">🧭</div>
                    <h3>No listings available right now</h3>
                    <p>This guide currently does not have active tours or listings published.</p>
                </div>
            <?php else: ?>
                <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(280px, 1fr));gap:1.25rem;">
                    <?php foreach ($listings as $listing):
                        $imgUrl = !empty($listing['cover_image']) ? img_src($listing['cover_image'], 'listing' . $listing['id']) : asset('images/placeholder.jpg');
                        $catName = (string) ($listing['category_name'] ?? 'Tour');
                        $price = (float) ($listing['price'] ?? 0);
                        $priceUnit = (string) ($listing['price_unit'] ?? 'person');
                        ?>
                        <a href="<?= e(url('/listing/' . (string) $listing['slug'])) ?>"
                           style="display:flex;gap:0.9rem;align-items:center;padding:0.75rem;border-radius:10px;border:1px solid var(--line);background:#fff;text-decoration:none;color:inherit;transition:transform 0.15s ease, box-shadow 0.15s ease;"
                           onmouseover="this.style.boxShadow='var(--shadow-md)';this.style.borderColor='var(--teal-400)';"
                           onmouseout="this.style.boxShadow='none';this.style.borderColor='var(--line)';">
                            <img src="<?= e($imgUrl) ?>" alt="<?= e((string) $listing['title']) ?>"
                                 style="width:72px;height:72px;border-radius:8px;object-fit:cover;flex-shrink:0;">
                            <div style="flex:1;min-width:0;">
                                <div style="font-weight:700;font-size:0.96rem;color:var(--ink-900);margin-bottom:0.25rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                    <?= e((string) $listing['title']) ?>
                                </div>
                                <div style="font-size:0.8rem;color:var(--ink-400);margin-bottom:0.35rem;">
                                    <?= e($catName) ?>
                                </div>
                                <?php if ($price > 0): ?>
                                    <div style="font-size:0.88rem;font-weight:700;color:var(--teal-700);">
                                        ₱<?= number_format($price, 2) ?> <span style="font-weight:400;font-size:0.78rem;color:var(--ink-400);">/ <?= e($priceUnit) ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </article>

        <!-- Traveler Reviews Section -->
        <article class="card" style="padding:1.75rem 2rem;background:#ffffff;border:1px solid var(--line);border-radius:var(--radius);box-shadow:var(--shadow-sm);">
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.5rem;margin-bottom:1.25rem;">
                <div>
                    <h2 style="font-size:1.25rem;font-weight:800;color:var(--ink-900);margin:0 0 0.25rem;">Traveler Reviews</h2>
                    <p class="hint" style="margin:0;font-size:0.88rem;">Reviews from travelers who booked the guide's services.</p>
                </div>
                <?php if ($reviews !== []): ?>
                    <div style="display:flex;align-items:center;gap:0.4rem;font-weight:800;font-size:1.1rem;color:var(--ink-900);">
                        ⭐ <?= number_format($rating, 1) ?>
                        <span class="hint" style="font-weight:600;font-size:0.9rem;">(<?= count($reviews) ?>)</span>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($reviews === []): ?>
                <div class="empty-state" style="padding:2rem 1rem;">
                    <div class="big">💬</div>
                    <h3>No traveler reviews yet</h3>
                    <p>Reviews will appear here once travelers complete and review their guided tours.</p>
                </div>
            <?php else: ?>
                <div style="display:flex;flex-direction:column;gap:1rem;">
                    <?php foreach ($reviews as $rev):
                        $revUserAvatar = !empty($rev['user_avatar']) ? img_src($rev['user_avatar'], 'avatar' . $rev['user_id']) : asset('images/avatar-placeholder.png');
                        $revRating = (int) ($rev['rating'] ?? 5);
                        $revDate = !empty($rev['created_at']) ? date('M d, Y', strtotime((string) $rev['created_at'])) : '';
                        ?>
                        <div style="padding:1rem 1.25rem;border-radius:10px;border:1px solid var(--line);background:var(--sand-50, #f8fafc);">
                            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.5rem;flex-wrap:wrap;gap:0.5rem;">
                                <div style="display:flex;align-items:center;gap:0.75rem;">
                                    <img src="<?= e($revUserAvatar) ?>" alt="<?= e((string) ($rev['user_name'] ?? 'Traveler')) ?>"
                                         style="width:36px;height:36px;border-radius:50%;object-fit:cover;">
                                    <div>
                                        <div style="font-weight:700;font-size:0.92rem;color:var(--ink-900);">
                                            <?= e((string) ($rev['user_name'] ?? 'Traveler')) ?>
                                        </div>
                                        <?php if (!empty($rev['listing_title'])): ?>
                                            <div style="font-size:0.78rem;color:var(--ink-400);">
                                                <?= e((string) $rev['listing_title']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div style="text-align:right;">
                                    <div style="color:var(--gold, #eab308);font-size:0.9rem;">
                                        <?= str_repeat('★', max(1, min(5, $revRating))) ?>
                                    </div>
                                    <div style="font-size:0.75rem;color:var(--ink-400);"><?= e($revDate) ?></div>
                                </div>
                            </div>
                            <p style="margin:0;font-size:0.92rem;line-height:1.55;color:var(--ink-800);">
                                <?= nl2br(e((string) ($rev['comment'] ?? ''))) ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </article>
    </div>
</section>
