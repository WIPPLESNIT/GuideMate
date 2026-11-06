<?php
/**
 * GuideMate - Modern Travel Package Builder Wizard (5-Step Workflow)
 *
 * @var array<string,mixed> $user
 * @var array<int,array<string,mixed>> $myActivities
 * @var array<int,array<string,mixed>> $hotelRooms
 * @var array<int,array<string,mixed>> $vehicles
 * @var array<string,mixed>|null $package
 * @var bool|null $isEdit
 * @var string|null $parsedDesc
 * @var string|null $parsedItinerary
 * @var string|null $parsedPolicies
 * @var string|null $parsedTags
 * @var int|null $durationDays
 * @var int|null $durationNights
 * @var array<int,string>|null $existingPhotos
 * @var array<int,array<string,mixed>>|null $selectedActivities
 * @var array<string,string> $errors
 */
$errors = $errors ?? [];
$myActivities = $myActivities ?? [];
$hotelRooms = $hotelRooms ?? [];
$vehicles = $vehicles ?? [];
$package = $package ?? null;
$isEdit = (bool) ($isEdit ?? false);
$existingPhotos = $existingPhotos ?? [];
$selectedActivities = $selectedActivities ?? [];

// In edit mode, prefill defaults
$defaultTitle = $isEdit ? ($package['title'] ?? '') : '';
$defaultSummary = $isEdit ? ($package['summary'] ?? '') : '';
$defaultArea = $isEdit ? ($package['area'] ?? '') : '';
$defaultDeparture = $isEdit ? ($package['address'] ?? '') : '';
$defaultDays = $isEdit ? (int) ($durationDays ?? 3) : '';
$defaultNights = $isEdit ? (int) ($durationNights ?? 2) : '';
$defaultMinPax = $isEdit ? 2 : '';
$defaultMaxPax = $isEdit ? (int) ($package['capacity'] ?? 8) : '';
$defaultPrice = $isEdit ? (float) ($package['price'] ?? 0) : 0;

$defaultTags = $isEdit && !empty($parsedTags) ? $parsedTags : '';
$defaultDesc = $isEdit && !empty($parsedDesc) ? $parsedDesc : '';
$defaultItinerary = $isEdit && !empty($parsedItinerary) ? $parsedItinerary : '';
$defaultPolicies = $isEdit && !empty($parsedPolicies) ? $parsedPolicies : '';

$autoBadge = $autoBadge ?? ($defaultBadge ?: 'New Expedition');
$defaultBadge = $autoBadge;

$formAction = $isEdit ? url('/dashboard/packages/' . (int) $package['id']) : url('/dashboard/packages');

// Host info auto-retrieved from logged-in guide profile
$hostStats = $guideStats ?? (class_exists('\App\Models\Booking') ? \App\Models\Booking::guideRatingStats((int) ($user['id'] ?? 0)) : []);
$hostName = (string) ($user['name'] ?? 'Guide Host');
$hostAvatar = !empty($user['avatar']) ? img_src($user['avatar'], 'avatar') : 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=300';
$isApprovedGuide = ($user['guide_status'] ?? '') === 'approved';
$hostRole = ($user['role'] ?? '') === 'guide'
    ? ($isApprovedGuide ? 'DOT Accredited Regional Tour Guide' : 'Licensed Regional Tour Guide')
    : 'Certified Tour Guide & Package Host';
$hostBio = !empty($user['bio']) ? (string) $user['bio'] : 'Certified tour guide and package host dedicated to authentic, safe local travel experiences.';
$hostRating = (float) ($hostStats['avg_rating'] ?? 0);
$hostReviewCount = (int) ($hostStats['review_count'] ?? 0);
$hostToursCount = (int) ($hostStats['completed'] ?? 0);
?>
<div class="container">
    <div class="dash-layout">
        <?= \App\Core\View::partial('partials/dash-nav') ?>
        <div style="flex:1;min-width:0;">
            <!-- Breadcrumb Navigation with High-Contrast Text Colors -->
            <div class="breadcrumb" style="margin-bottom:0.75rem;font-size:0.875rem;">
                <a href="<?= e(url('/dashboard/listings')) ?>" style="color:#64748b;text-decoration:none;font-weight:600;transition:color 0.2s;">My Listings</a>
                <span style="margin:0 0.4rem;color:#94a3b8;">›</span>
                <strong style="color:#0f172a;font-weight:700;"><?= $isEdit ? 'Edit Travel Package' : 'New Travel Package' ?></strong>
            </div>

            <!-- Page Title & Header with Crisp White Background Card -->
            <div class="panel" style="background:#ffffff;border:1px solid #e2e8f0;border-radius:16px;padding:1.5rem 1.75rem;margin-bottom:1.5rem;box-shadow:0 1px 4px rgba(0,0,0,0.04);">
                <div>
                    <h1 style="margin:0 0 0.35rem 0;font-size:1.75rem;font-weight:800;letter-spacing:-0.02em;color:#0f172a;display:flex;align-items:center;gap:0.6rem;">
                        <span style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:12px;background:#ecfdf5;color:#059669;font-size:1.2rem;">📦</span>
                        <?= $isEdit ? 'Edit Travel Package' : 'Travel Package Builder' ?>
                    </h1>
                    <p class="hint mb-0" style="font-size:0.95rem;color:#475569;">
                        Step-by-step travel package builder: configure overview, select bundled services, manage host & package details, plan itinerary, and calculate pricing.
                    </p>
                </div>
            </div>

            <!-- Stepper Progress Header (5-Step Workflow) -->
            <div class="panel pkg-wizard-card" style="margin-bottom:1.5rem;padding:0.65rem 0.85rem;background:#ffffff;border:1px solid #e2e8f0;border-radius:14px;box-shadow:0 1px 4px rgba(0,0,0,0.04);">
                <div style="display:grid;grid-template-columns:repeat(5, 1fr);gap:0.5rem;align-items:center;">
                    <div id="stepTab1" class="pkg-step-pill active" onclick="goToStep(1)">
                        <span class="pkg-step-num">1</span>
                        <div class="pkg-step-label"><strong>Overview</strong><small>Basics & Narrative</small></div>
                    </div>
                    <div id="stepTab2" class="pkg-step-pill" onclick="goToStep(2)">
                        <span class="pkg-step-num">2</span>
                        <div class="pkg-step-label"><strong>Package Selection</strong><small>4-Category Services</small></div>
                    </div>
                    <div id="stepTab3" class="pkg-step-pill" onclick="goToStep(3)">
                        <span class="pkg-step-num">3</span>
                        <div class="pkg-step-label"><strong>Selected Package Details</strong><small>Host, Inclusions & Perks</small></div>
                    </div>
                    <div id="stepTab4" class="pkg-step-pill" onclick="goToStep(4)">
                        <span class="pkg-step-num">4</span>
                        <div class="pkg-step-label"><strong>Itinerary</strong><small>Day-by-Day Organizer</small></div>
                    </div>
                    <div id="stepTab5" class="pkg-step-pill" onclick="goToStep(5)">
                        <span class="pkg-step-num">5</span>
                        <div class="pkg-step-label"><strong>Pricing</strong><small>Cost Breakdown & Total</small></div>
                    </div>
                </div>
            </div>

            <!-- Form Container -->
            <form id="packageBuilderForm" method="post" action="<?= e($formAction) ?>" enctype="multipart/form-data" onsubmit="return handleFormSubmit(event);" novalidate>
                <?= csrf_field() ?>

                <!-- ======================================================== -->
                <!-- STEP 1: PACKAGE OVERVIEW & BASIC INFORMATION (Image 1)   -->
                <!-- ======================================================== -->
                <div id="stepSection1" class="panel pkg-step-content" style="padding:1.75rem;margin-bottom:1.5rem;border-radius:16px;background:#ffffff;border:1px solid #e2e8f0;box-shadow:0 1px 4px rgba(0,0,0,0.04);">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:0.75rem;margin-bottom:1.5rem;border-bottom:1px solid #f1f5f9;padding-bottom:1rem;flex-wrap:wrap;">
                        <div style="display:flex;align-items:center;gap:0.75rem;">
                            <span style="font-size:1.75rem;display:inline-flex;align-items:center;justify-content:center;width:44px;height:44px;background:#ecfdf5;border-radius:12px;color:#059669;">🗺️</span>
                            <div>
                                <h3 style="margin:0;font-size:1.25rem;font-weight:800;color:#0f172a;">Step 1: Package Overview & Basic Information</h3>
                                <p class="hint mb-0" style="font-size:0.875rem;color:#64748b;">Design the package identity, destination, trip attributes, narrative overview, and showcase cover photo.</p>
                            </div>
                        </div>
                        <div style="display:inline-flex;align-items:center;gap:0.4rem;background:#fff7ed;color:#c2410c;border:1px solid #fed7aa;padding:5px 12px;border-radius:999px;font-size:0.8rem;font-weight:700;" title="Badge applied automatically based on package status">
                            <span>✨</span> <?= e($autoBadge) ?>
                        </div>
                    </div>

                    <!-- Step 1 Full-Width Form Controls -->
                    <div>
                        <!-- Hidden badge input automatically synchronized with package status -->
                        <input type="hidden" id="pkg_badge" name="badge" value="<?= e($autoBadge) ?>">

                        <!-- Title & Destination -->
                        <div class="field-row" style="margin-bottom:1rem;">
                            <label for="pkg_title" style="font-weight:700;color:#0f172a;">Package Title <span class="req" style="color:#f43f5e;">*</span></label>
                            <input class="input pkg-input" id="pkg_title" name="title" required
                                   placeholder="e.g. 3D2N South Cebu Expedition: Canyoneering, Moalboal Reef & NMAX Scooter"
                                   value="<?= e($defaultTitle) ?>"
                                   style="width:100%;box-sizing:border-box;">
                            <span class="hint" style="font-size:0.8rem;color:#64748b;display:block;margin-top:0.25rem;">Highlight duration and key destination or transport inclusions for maximum booking interest.</span>
                        </div>

                        <div class="form-grid-2" style="margin-bottom:1rem;">
                            <div class="field-row">
                                <label for="pkg_area" style="font-weight:700;color:#0f172a;">Destination & Sub-region <span class="req" style="color:#f43f5e;">*</span></label>
                                <input class="input pkg-input" id="pkg_area" name="area" required
                                       placeholder="e.g. Moalboal & Badian, South Cebu"
                                       value="<?= e($defaultArea) ?>"
                                       style="width:100%;box-sizing:border-box;">
                            </div>
                            <div class="field-row">
                                <label for="pkg_departure" style="font-weight:700;color:#0f172a;">Departure / Jump-off Meeting Point</label>
                                <input class="input pkg-input" id="pkg_departure" name="base_departure"
                                       placeholder="e.g. Panagsama Beach, Moalboal or Mactan Airport"
                                       value="<?= e($defaultDeparture) ?>"
                                       style="width:100%;box-sizing:border-box;">
                            </div>
                        </div>

                        <div class="field-row" style="margin-bottom:1rem;">
                            <label for="pkg_summary" style="font-weight:700;color:#0f172a;">Tagline Summary</label>
                            <input class="input pkg-input" id="pkg_summary" name="summary"
                                   placeholder="e.g. All-inclusive 3-day South Cebu escape with Kawasan canyoneering and resort stay"
                                   value="<?= e($defaultSummary) ?>"
                                   style="width:100%;box-sizing:border-box;">
                        </div>

                        <!-- Key Trip Attributes Section: 2-Column Matrix (Duration & Group Size only, Assurance removed per Requirement 3) -->
                        <div class="field-row" style="background:#f8fafc;padding:1.25rem;border-radius:14px;border:1px solid #e2e8f0;margin-bottom:1.25rem;">
                            <label style="font-weight:700;font-size:0.9rem;color:#0f172a;margin-bottom:0.75rem;display:block;">
                                ⏱️ Key Trip Attributes
                            </label>
                            <div class="form-grid-2" style="gap:1rem;">
                                <!-- Duration Attributes -->
                                <div style="background:#ffffff;padding:0.85rem 1rem;border-radius:10px;border:1px solid #e2e8f0;">
                                    <span style="font-size:0.75rem;font-weight:700;color:#059669;text-transform:uppercase;letter-spacing:0.04em;display:block;margin-bottom:0.4rem;">Duration Breakdown</span>
                                    <div style="display:flex;gap:0.75rem;">
                                        <div style="flex:1;">
                                            <label for="pkg_days" style="font-size:0.75rem;color:#64748b;display:block;margin-bottom:2px;">Days <span class="req" style="color:#f43f5e;">*</span></label>
                                            <input class="input pkg-input" type="number" id="pkg_days" name="duration_days" min="1" max="14"
                                                   placeholder="e.g. 3"
                                                   value="<?= e($defaultDays) ?>" required oninput="calculateFinancials(); updateItineraryDays();" onchange="calculateFinancials(); updateItineraryDays();" style="width:100%;box-sizing:border-box;">
                                        </div>
                                        <div style="flex:1;">
                                            <label for="pkg_nights" style="font-size:0.75rem;color:#64748b;display:block;margin-bottom:2px;">Nights <span class="req" style="color:#f43f5e;">*</span></label>
                                            <input class="input pkg-input" type="number" id="pkg_nights" name="duration_nights" min="0" max="14"
                                                   placeholder="e.g. 2"
                                                   value="<?= e($defaultNights) ?>" required oninput="calculateFinancials();" style="width:100%;box-sizing:border-box;">
                                        </div>
                                    </div>
                                </div>

                                <!-- Group Capacity Attributes -->
                                <div style="background:#ffffff;padding:0.85rem 1rem;border-radius:10px;border:1px solid #e2e8f0;">
                                    <span style="font-size:0.75rem;font-weight:700;color:#059669;text-transform:uppercase;letter-spacing:0.04em;display:block;margin-bottom:0.4rem;">Group Size Scope</span>
                                    <div style="display:flex;gap:0.75rem;">
                                        <div style="flex:1;">
                                            <label for="pkg_min_pax" style="font-size:0.75rem;color:#64748b;display:block;margin-bottom:2px;">Min Pax</label>
                                            <input class="input pkg-input" type="number" id="pkg_min_pax" name="min_pax" min="1" max="20"
                                                   placeholder="e.g. 2"
                                                   value="<?= e($defaultMinPax) ?>" style="width:100%;box-sizing:border-box;">
                                        </div>
                                        <div style="flex:1;">
                                            <label for="pkg_max_pax" style="font-size:0.75rem;color:#64748b;display:block;margin-bottom:2px;">Max Pax <span class="req" style="color:#f43f5e;">*</span></label>
                                            <input class="input pkg-input" type="number" id="pkg_max_pax" name="max_pax" min="1" max="30"
                                                   placeholder="e.g. 8"
                                                   value="<?= e($defaultMaxPax) ?>" required style="width:100%;box-sizing:border-box;">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Narrative & Tags -->
                        <div class="field-row" style="margin-bottom:1rem;">
                            <label for="pkg_description" style="font-weight:700;color:#0f172a;display:flex;justify-content:space-between;align-items:center;">
                                <span>About This Package (Narrative Overview) <span class="req" style="color:#f43f5e;">*</span></span>
                                <span id="pkg_desc_count" style="font-size:0.75rem;color:#64748b;font-weight:normal;">0 characters</span>
                            </label>
                            <textarea class="input pkg-input" id="pkg_description" name="description" rows="5" required
                                      placeholder="Describe the overall package experience, atmosphere, inclusions, and what makes it extraordinary..."
                                      oninput="updateDescCount()"
                                      style="resize:vertical;font-family:inherit;font-size:0.9rem;line-height:1.6;width:100%;box-sizing:border-box;"><?= e($defaultDesc) ?></textarea>
                            <span class="hint" style="font-size:0.8rem;color:#64748b;display:block;margin-top:0.25rem;">This compelling narrative appears directly under 'About This Package' in the customer package page.</span>
                        </div>

                        <div class="field-row" style="margin-bottom:1.5rem;">
                            <label for="pkg_tags" style="font-weight:700;color:#0f172a;">Experience Tags (One per line)</label>
                            <textarea class="input pkg-input" id="pkg_tags" name="tags" rows="3"
                                      placeholder="e.g. Adventure & Nature&#10;Water Activities&#10;Family-Friendly&#10;Island Hopping"
                                      style="resize:vertical;font-family:inherit;font-size:0.85rem;line-height:1.5;width:100%;box-sizing:border-box;"><?= e($defaultTags) ?></textarea>
                        </div>

                        <!-- Package Showcase Photos (Cover & Gallery) matching Reference Image 4 -->
                        <div class="field-row" style="background:#f8fafc;padding:1.25rem;border-radius:14px;border:1px dashed #cbd5e1;margin-bottom:1.5rem;box-sizing:border-box;">
                            <label style="font-weight:700;color:#0f172a;margin-bottom:0.25rem;display:flex;align-items:center;justify-content:space-between;">
                                <span>📸 Package Showcase Photos (Cover & Gallery)</span>
                                <span style="font-size:0.75rem;font-weight:600;background:#ecfdf5;color:#047857;padding:2px 8px;border-radius:12px;">3 Photo Slots</span>
                            </label>
                            <span class="hint" style="display:block;margin-bottom:0.85rem;font-size:0.8rem;color:#64748b;">
                                Upload high-quality JPG, PNG, or WEBP images or paste image URLs. Photo 1 immediately acts as your hero cover banner.
                            </span>

                            <div class="pkg-showcase-grid" style="display:grid;grid-template-columns:repeat(3, minmax(0, 1fr));gap:0.85rem;width:100%;box-sizing:border-box;">
                                <?php for ($i = 0; $i < 3; $i++):
                                    $existingPhoto = $existingPhotos[$i] ?? ($i === 0 && !empty($package['cover_image']) ? $package['cover_image'] : '');
                                    $hasExisting = !empty($existingPhoto);
                                ?>
                                    <div class="pkg-showcase-card" style="border:1px solid #e2e8f0;border-radius:10px;background:#ffffff;padding:0.75rem;display:flex;flex-direction:column;gap:0.4rem;min-width:0;box-sizing:border-box;overflow:hidden;">
                                        <div style="display:flex;align-items:center;justify-content:space-between;">
                                            <strong style="font-size:0.75rem;color:#0f172a;">Photo <?= $i + 1 ?><?= $i === 0 ? ' (Cover) *' : '' ?></strong>
                                            <?php if ($i === 0): ?>
                                                <div style="display:flex;align-items:center;gap:0.35rem;">
                                                    <span style="font-size:0.65rem;background:#dcfce7;color:#166534;font-weight:700;padding:1px 5px;border-radius:4px;">Hero</span>
                                                    <span style="font-size:0.65rem;background:#fff7ed;color:#c2410c;border:1px solid #fed7aa;font-weight:700;padding:1px 6px;border-radius:999px;">✨ <?= e($autoBadge) ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div id="pkg_preview_wrap_<?= $i ?>" style="position:relative;height:100px;background:#f1f5f9;border-radius:8px;overflow:hidden;display:flex;align-items:center;justify-content:center;border:1px solid #e2e8f0;width:100%;box-sizing:border-box;">
                                            <img id="pkg_preview_img_<?= $i ?>" src="<?= $hasExisting ? e(img_src($existingPhoto, 'pkg-' . $i)) : '' ?>" alt="Photo <?= $i + 1 ?>" style="width:100%;height:100%;object-fit:cover;display:<?= $hasExisting ? 'block' : 'none' ?>;">
                                            <span id="pkg_placeholder_<?= $i ?>" style="font-size:0.7rem;color:#94a3b8;text-align:center;padding:4px;display:<?= $hasExisting ? 'none' : 'block' ?>;">📷 No photo</span>
                                            <button type="button" id="pkg_remove_btn_<?= $i ?>" onclick="removePkgPhoto(<?= $i ?>)" style="display:<?= $hasExisting ? 'flex' : 'none' ?>;position:absolute;top:5px;right:5px;background:rgba(0,0,0,0.7);color:#fff;border:none;border-radius:50%;width:22px;height:22px;align-items:center;justify-content:center;font-size:13px;line-height:1;cursor:pointer;">&times;</button>
                                        </div>
                                        <input type="file" name="photo_file_<?= $i ?>" id="pkg_file_<?= $i ?>" accept=".jpg,.jpeg,.png,.webp" style="font-size:0.7rem;width:100%;max-width:100%;box-sizing:border-box;color:#475569;" onchange="handlePkgFileChange(<?= $i ?>, this)">
                                        <input class="input pkg-input" type="text" name="photo_url_<?= $i ?>" id="pkg_url_<?= $i ?>" placeholder="Or photo URL..." style="font-size:0.7rem;padding:3px 6px;height:28px;width:100%;box-sizing:border-box;" oninput="handlePkgUrlChange(<?= $i ?>, this.value)">
                                        <input type="hidden" name="photo_existing_<?= $i ?>" id="pkg_existing_<?= $i ?>" value="<?= e($existingPhoto) ?>">
                                        <input type="hidden" name="photo_removed_<?= $i ?>" id="pkg_removed_<?= $i ?>" value="0">
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <!-- Bottom Navigation: Step 1 -> Step 2 -->
                        <div class="pkg-step-actions-row">
                            <div>
                                <a href="<?= e(url('/dashboard/listings')) ?>" class="pkg-btn-cancel" onclick="return confirmDiscardPackage(event);">Cancel</a>
                            </div>
                            <div style="display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap;">
                                <button type="button" class="pkg-btn-back" onclick="handleStep1Back();">Back</button>
                                <button type="button" onclick="goToStep(2)" class="pkg-btn-next">Next</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ======================================================== -->
                <!-- STEP 2: PACKAGE SELECTION (Image 3 Reference)            -->
                <!-- ======================================================== -->
                <div id="stepSection2" class="panel pkg-step-content" style="padding:1.75rem;margin-bottom:1.5rem;border-radius:16px;background:#ffffff;border:1px solid #e2e8f0;box-shadow:0 1px 4px rgba(0,0,0,0.04);display:none;">
                    <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:1.25rem;border-bottom:1px solid #f1f5f9;padding-bottom:0.85rem;">
                        <span style="font-size:1.5rem;">🏄</span>
                        <div>
                            <h3 style="margin:0;font-size:1.2rem;font-weight:800;color:#0f172a;">Step 2: Package Selection</h3>
                            <p class="hint mb-0" style="font-size:0.85rem;color:#64748b;">Browse and select available listings from 4 core categories: Things to Do, Attractions, Hotels & Stays, and Vehicle Rentals.</p>
                        </div>
                    </div>

                    <!-- Category Filter Tabs -->
                    <div style="display:flex;flex-wrap:wrap;gap:0.5rem;margin-bottom:1.5rem;padding:0.75rem 1rem;background:#f8fafc;border-radius:12px;border:1px solid #e2e8f0;align-items:center;justify-content:space-between;">
                        <div style="display:flex;flex-wrap:wrap;gap:0.4rem;align-items:center;">
                            <button type="button" class="pkg-category-filter-btn active" onclick="filterSelectionCategory('all', this)">All Categories</button>
                            <button type="button" class="pkg-category-filter-btn" onclick="filterSelectionCategory('things-to-do', this)">🏄 Things to Do</button>
                            <button type="button" class="pkg-category-filter-btn" onclick="filterSelectionCategory('attractions', this)">🏞️ Attractions</button>
                            <button type="button" class="pkg-category-filter-btn" onclick="filterSelectionCategory('hotels', this)">🏨 Hotels & Stays</button>
                            <button type="button" class="pkg-category-filter-btn" onclick="filterSelectionCategory('vehicles', this)">🛵 Vehicle Rentals</button>
                        </div>
                        <span id="step2CounterBadge" style="background:#ecfdf5;color:#047857;padding:4px 12px;border-radius:999px;font-size:0.8rem;font-weight:700;">
                            0 Selected
                        </span>
                    </div>

                    <!-- CATEGORY 1: THINGS TO DO -->
                    <div class="pkg-cat-block" data-category="things-to-do" style="margin-bottom:2rem;">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.75rem;">
                            <label style="font-weight:800;font-size:1.05rem;color:#0f172a;display:flex;align-items:center;gap:0.4rem;">
                                <span>🏄</span> Things to Do (Guided Adventures & Activities)
                            </label>
                            <span class="hint" style="font-size:0.8rem;color:#059669;font-weight:600;">Click card to bundle into package</span>
                        </div>
                        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));gap:1rem;">
                            <?php foreach ($myActivities as $act):
                                if (str_contains(strtolower($act['category_slug'] ?? $act['category_name'] ?? ''), 'attraction')) continue;
                                $actId = (int) $act['id'];
                                $actTitle = (string) $act['title'];
                                $actPrice = (float) $act['price'];
                                $actArea = (string) ($act['area'] ?? 'Cebu, Philippines');
                                $actCover = (string) ($act['cover_image'] ?? 'uploads/places/kawasan.jpg');
                                $actCategory = 'Things to Do';
                                $actSummary = (string) ($act['summary'] ?? 'Curated outdoor guided experience');
                                $isSelected = in_array($actId, array_column($selectedActivities, 'id'), true);
                            ?>
                                <div class="pkg-destination-card <?= $isSelected ? 'is-selected' : '' ?>"
                                     id="card_act_<?= $actId ?>"
                                     onclick="toggleActivityCard(<?= $actId ?>, '<?= e(addslashes($actTitle)) ?>', <?= $actPrice ?>, '<?= e(addslashes($actArea)) ?>', '<?= e(addslashes($actCover)) ?>', '<?= e(addslashes($actCategory)) ?>', '<?= e(addslashes($actSummary)) ?>')">
                                    <div class="pkg-card-img-wrap">
                                        <img src="<?= e(img_src($actCover, 'listing-' . $actId)) ?>" alt="<?= e($actTitle) ?>" class="pkg-card-img" onerror="this.src='https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?w=800&q=80'">
                                        <span class="pkg-category-badge badge-purple"><?= e($actCategory) ?></span>
                                        <div class="pkg-card-select-pill">
                                            <span class="pkg-check-icon"><?= $isSelected ? '✓' : '+' ?></span>
                                            <span class="pkg-check-text"><?= $isSelected ? 'Included' : 'Select' ?></span>
                                        </div>
                                    </div>
                                    <div class="pkg-card-body">
                                        <div class="pkg-card-location"><span>📍</span> <?= e($actArea) ?></div>
                                        <h4 class="pkg-card-title"><?= e($actTitle) ?></h4>
                                        <p style="font-size:0.75rem;color:#64748b;margin:0 0 0.5rem 0;line-height:1.4;"><?= e(substr($actSummary, 0, 80)) ?>...</p>
                                        <div class="pkg-card-footer">
                                            <span class="pkg-card-price"><?= money($actPrice) ?> <small>/ person</small></span>
                                            <span class="pkg-card-duration">⏱ <?= e($act['duration'] ?? 'Half day') ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- CATEGORY 2: ATTRACTIONS -->
                    <div class="pkg-cat-block" data-category="attractions" style="margin-bottom:2rem;">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.75rem;">
                            <label style="font-weight:800;font-size:1.05rem;color:#0f172a;display:flex;align-items:center;gap:0.4rem;">
                                <span>🏞️</span> Attractions (Scenic Parks & Ecological Reserves)
                            </label>
                            <span class="hint" style="font-size:0.8rem;color:#db2777;font-weight:600;">Includes admission & conservation tickets</span>
                        </div>
                        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));gap:1rem;">
                            <?php foreach ($myActivities as $act):
                                if (!str_contains(strtolower($act['category_slug'] ?? $act['category_name'] ?? ''), 'attraction')) continue;
                                $actId = (int) $act['id'];
                                $actTitle = (string) $act['title'];
                                $actPrice = (float) $act['price'];
                                $actArea = (string) ($act['area'] ?? 'Cebu, Philippines');
                                $actCover = (string) ($act['cover_image'] ?? 'uploads/places/kawasan.jpg');
                                $actCategory = 'Attraction';
                                $actSummary = (string) ($act['summary'] ?? 'Natural scenic landmark and ecological site');
                                $isSelected = in_array($actId, array_column($selectedActivities, 'id'), true);
                            ?>
                                <div class="pkg-destination-card <?= $isSelected ? 'is-selected' : '' ?>"
                                     id="card_act_<?= $actId ?>"
                                     onclick="toggleActivityCard(<?= $actId ?>, '<?= e(addslashes($actTitle)) ?>', <?= $actPrice ?>, '<?= e(addslashes($actArea)) ?>', '<?= e(addslashes($actCover)) ?>', '<?= e(addslashes($actCategory)) ?>', '<?= e(addslashes($actSummary)) ?>')">
                                    <div class="pkg-card-img-wrap">
                                        <img src="<?= e(img_src($actCover, 'listing-' . $actId)) ?>" alt="<?= e($actTitle) ?>" class="pkg-card-img" onerror="this.src='https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?w=800&q=80'">
                                        <span class="pkg-category-badge badge-pink"><?= e($actCategory) ?></span>
                                        <div class="pkg-card-select-pill">
                                            <span class="pkg-check-icon"><?= $isSelected ? '✓' : '+' ?></span>
                                            <span class="pkg-check-text"><?= $isSelected ? 'Included' : 'Select' ?></span>
                                        </div>
                                    </div>
                                    <div class="pkg-card-body">
                                        <div class="pkg-card-location"><span>📍</span> <?= e($actArea) ?></div>
                                        <h4 class="pkg-card-title"><?= e($actTitle) ?></h4>
                                        <p style="font-size:0.75rem;color:#64748b;margin:0 0 0.5rem 0;line-height:1.4;"><?= e(substr($actSummary, 0, 80)) ?>...</p>
                                        <div class="pkg-card-footer">
                                            <span class="pkg-card-price"><?= money($actPrice) ?> <small>/ person</small></span>
                                            <span class="pkg-card-duration">⏱ <?= e($act['duration'] ?? 'Day Pass') ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- CATEGORY 3: HOTELS & STAYS -->
                    <div class="pkg-cat-block" data-category="hotels" style="margin-bottom:2rem;">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.75rem;">
                            <label style="font-weight:800;font-size:1.05rem;color:#0f172a;display:flex;align-items:center;gap:0.4rem;">
                                <span>🏨</span> Hotels & Stays (Partner Accommodations)
                            </label>
                            <span class="hint" style="font-size:0.8rem;color:#d97706;font-weight:600;">Nightly rate automatically split for 2 pax</span>
                        </div>
                        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));gap:1rem;">
                            <?php foreach ($hotelRooms as $idx => $r):
                                $roomId = (int) $r['id'];
                                $hName = (string) ($r['hotel_name'] ?? 'Sea Breeze Oceanfront Resort');
                                $rName = (string) ($r['room_name'] ?? $r['name'] ?? 'Deluxe Queen Room');
                                $hRate = (float) $r['price_per_night'];
                                $hMax = (int) ($r['max_guests'] ?? 2);
                                $hBed = (string) ($r['bed_type'] ?? '1 Bed');
                                $hArea = (string) ($r['hotel_area'] ?? 'Moalboal, Cebu');
                                $hCover = (string) ($r['hotel_cover'] ?? 'https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?w=800&q=80');
                                $hCheckIn = (string) ($r['check_in_time'] ?? '14:00:00');
                                $hCheckOut = (string) ($r['check_out_time'] ?? '12:00:00');
                                $hAmenities = $r['amenities'] ?? [];
                                $hAmenitiesJson = is_string($hAmenities) ? $hAmenities : json_encode($hAmenities);
                                $isSelectedHotel = false;
                            ?>
                                <label class="pkg-card-radio <?= $isSelectedHotel ? 'selected' : '' ?>" id="hotelCard_<?= $roomId ?>">
                                    <input type="radio" name="selected_hotel_room" value="<?= $roomId ?>"
                                           data-hotel-name="<?= e($hName) ?>"
                                           data-room-name="<?= e($rName) ?>"
                                           data-rate="<?= $hRate ?>"
                                           data-max-guests="<?= $hMax ?>"
                                           data-checkin="<?= e($hCheckIn) ?>"
                                           data-checkout="<?= e($hCheckOut) ?>"
                                           data-amenities="<?= e($hAmenitiesJson) ?>"
                                           data-hotel-area="<?= e($hArea) ?>"
                                           data-bed-type="<?= e($hBed) ?>"
                                           <?= $isSelectedHotel ? 'checked' : '' ?>
                                           onchange="selectHotelRoom(this)"
                                           style="position:absolute;top:0.75rem;right:0.75rem;z-index:2;width:18px;height:18px;accent-color:#10b981;">
                                    <div class="pkg-card-img-wrap">
                                        <img src="<?= e(img_src($hCover, 'hotel-' . $roomId)) ?>" alt="<?= e($hName) ?>" class="pkg-card-img" onerror="this.src='https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?w=800&q=80'">
                                        <span class="pkg-category-badge badge-amber">Hotel & Stays</span>
                                    </div>
                                    <div class="pkg-card-body">
                                        <div class="pkg-card-location"><span>📍</span> <?= e($hArea) ?></div>
                                        <h4 class="pkg-card-title"><?= e($hName) ?></h4>
                                        <div style="background:#f8fafc;padding:0.6rem 0.75rem;border-radius:8px;margin:0.5rem 0;border:1px solid #e2e8f0;">
                                            <div style="display:flex;justify-content:space-between;font-size:0.85rem;color:#0f172a;">
                                                <span><strong>Room:</strong> <?= e($rName) ?></span>
                                                <span style="color:#059669;font-weight:700;"><?= money($hRate) ?> / night</span>
                                            </div>
                                            <div style="font-size:0.75rem;color:#64748b;margin-top:3px;">
                                                Max: <?= $hMax ?> Guests • <?= e($hBed) ?> • Daily Breakfast
                                            </div>
                                        </div>
                                        <div style="display:flex;flex-wrap:wrap;gap:0.35rem;margin-top:0.35rem;">
                                            <span class="pkg-perk-pill">✓ Wi-Fi</span>
                                            <span class="pkg-perk-pill">✓ Pool Access</span>
                                            <span class="pkg-perk-pill">✓ Air Conditioning</span>
                                        </div>
                                    </div>
                                </label>
                            <?php endforeach; ?>

                            <!-- Option for no hotel -->
                            <label class="pkg-card-radio" id="hotelCard_none">
                                <input type="radio" name="selected_hotel_room" value="none"
                                       data-hotel-name="none"
                                       data-room-name=""
                                       data-rate="0"
                                       data-max-guests="20"
                                       data-checkin="N/A"
                                       data-checkout="N/A"
                                       data-amenities="[]"
                                       onchange="selectHotelRoom(this)"
                                       style="position:absolute;top:0.75rem;right:0.75rem;z-index:2;width:18px;height:18px;">
                                <div style="height:160px;background:#f8fafc;border-radius:12px 12px 0 0;display:flex;flex-direction:column;align-items:center;justify-content:center;color:#64748b;border-bottom:1px solid #e2e8f0;">
                                    <span style="font-size:2.5rem;margin-bottom:0.25rem;">🚶</span>
                                    <strong style="font-size:0.85rem;color:#0f172a;">No Hotel Stay (Day Tour)</strong>
                                </div>
                                <div class="pkg-card-body">
                                    <h4 class="pkg-card-title">Day-Tour Only / Guest Arranges Lodging</h4>
                                    <p style="font-size:0.8rem;color:#64748b;margin:0.25rem 0;">Excludes accommodation cost from the bundle (₱0.00 stay charge).</p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- CATEGORY 4: VEHICLE RENTALS -->
                    <div class="pkg-cat-block" data-category="vehicles" style="margin-bottom:2rem;">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.75rem;">
                            <label style="font-weight:800;font-size:1.05rem;color:#0f172a;display:flex;align-items:center;gap:0.4rem;">
                                <span>🛵</span> Vehicle Rentals (Transportation Partners)
                            </label>
                            <span class="hint" style="font-size:0.8rem;color:#2563eb;font-weight:600;">Daily rate covers dedicated vehicle duration</span>
                        </div>
                        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));gap:1rem;">
                            <?php foreach ($vehicles as $idx => $v):
                                $vId = (int) $v['id'];
                                $vName = (string) ($v['vehicle_name'] ?? ($v['make'] . ' ' . $v['model']));
                                $vType = ucfirst((string) ($v['vehicle_type'] ?? 'Scooter'));
                                $vRate = (float) $v['daily_rate'];
                                $vImage = (string) ($v['vehicle_image'] ?? 'uploads/vehicles/167/6d69ffd5b4ee70ab.jpg');
                                $vLoc = (string) ($v['location'] ?? 'Cebu, Philippines');
                                $vSpecs = $v['specs'] ?? [];
                                $vSpecsJson = is_string($vSpecs) ? $vSpecs : json_encode($vSpecs);
                                $vHandover = (string) ($v['handover_time'] ?? '13:00:00');
                                $vReturn = (string) ($v['return_time'] ?? '12:00:00');
                                $isSelectedVehicle = false;
                            ?>
                                <label class="pkg-card-radio <?= $isSelectedVehicle ? 'selected' : '' ?>" id="vehicleCard_<?= $vId ?>">
                                    <input type="radio" name="selected_vehicle_id" value="<?= $vId ?>"
                                           data-vehicle-model="<?= e($vName) ?>"
                                           data-vehicle-type="<?= e($vType) ?>"
                                           data-daily-rate="<?= $vRate ?>"
                                           data-location="<?= e($vLoc) ?>"
                                           data-specs="<?= e($vSpecsJson) ?>"
                                           data-handover-time="<?= e($vHandover) ?>"
                                           data-return-time="<?= e($vReturn) ?>"
                                           <?= $isSelectedVehicle ? 'checked' : '' ?>
                                           onchange="selectVehicle(this)"
                                           style="position:absolute;top:0.75rem;right:0.75rem;z-index:2;width:18px;height:18px;accent-color:#10b981;">
                                    <div class="pkg-card-img-wrap">
                                        <img src="<?= e(img_src($vImage, 'vehicle-' . $vId)) ?>" alt="<?= e($vName) ?>" class="pkg-card-img" onerror="this.src='https://images.unsplash.com/photo-1558981403-c5f9899a28bc?w=800&q=80'">
                                        <span class="pkg-category-badge badge-blue">Vehicle Rental</span>
                                    </div>
                                    <div class="pkg-card-body">
                                        <div class="pkg-card-location"><span>📍</span> <?= e($vLoc) ?></div>
                                        <h4 class="pkg-card-title"><?= e($vName) ?></h4>
                                        <div style="background:#f8fafc;padding:0.6rem 0.75rem;border-radius:8px;margin:0.5rem 0;border:1px solid #e2e8f0;">
                                            <div style="display:flex;justify-content:space-between;font-size:0.85rem;color:#0f172a;">
                                                <span><strong>Type:</strong> <?= e($vType) ?></span>
                                                <span style="color:#2563eb;font-weight:700;"><?= money($vRate) ?> / day</span>
                                            </div>
                                            <div style="font-size:0.75rem;color:#64748b;margin-top:3px;">
                                                Automatic • 2 DOT Helmets • Unlimited South Cebu Mileage
                                            </div>
                                        </div>
                                        <div style="display:flex;flex-wrap:wrap;gap:0.35rem;margin-top:0.35rem;">
                                            <span class="pkg-perk-pill">✓ Phone Mount</span>
                                            <span class="pkg-perk-pill">✓ Full Tank Included</span>
                                            <span class="pkg-perk-pill">✓ 24/7 Road Assistance</span>
                                        </div>
                                    </div>
                                </label>
                            <?php endforeach; ?>

                            <!-- Option for no vehicle -->
                            <label class="pkg-card-radio" id="vehicleCard_none">
                                <input type="radio" name="selected_vehicle_id" value="none"
                                       data-vehicle-model="none"
                                       data-vehicle-type="none"
                                       data-daily-rate="0"
                                       data-specs="[]"
                                       data-location=""
                                       data-handover-time="N/A"
                                       data-return-time="N/A"
                                       onchange="selectVehicle(this)"
                                       style="position:absolute;top:0.75rem;right:0.75rem;z-index:2;width:18px;height:18px;">
                                <div style="height:160px;background:#f8fafc;border-radius:12px 12px 0 0;display:flex;flex-direction:column;align-items:center;justify-content:center;color:#64748b;border-bottom:1px solid #e2e8f0;">
                                    <span style="font-size:2.5rem;margin-bottom:0.25rem;">🚶</span>
                                    <strong style="font-size:0.85rem;color:#0f172a;">No Rental Vehicle</strong>
                                </div>
                                <div class="pkg-card-body">
                                    <h4 class="pkg-card-title">Tourists Commute / Walking Itinerary</h4>
                                    <p style="font-size:0.8rem;color:#64748b;margin:0.25rem 0;">Omits rental transportation from the package price (₱0.00 vehicle cost).</p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <input type="hidden" id="selected_hotel_name" name="selected_hotel_name" value="<?= $isEdit && !empty($selectedHotelName) ? e($selectedHotelName) : 'None' ?>">
                    <input type="hidden" id="selected_vehicle_name" name="selected_vehicle_name" value="<?= $isEdit && !empty($selectedVehicleName) ? e($selectedVehicleName) : 'None' ?>">

                    <!-- Bottom Navigation: Step 2 -> Step 3 -->
                    <div class="pkg-step-actions-row">
                        <div>
                            <a href="<?= e(url('/dashboard/listings')) ?>" class="pkg-btn-cancel" onclick="return confirmDiscardPackage(event);">Cancel</a>
                        </div>
                        <div style="display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap;">
                            <button type="button" onclick="goToStep(1)" class="pkg-btn-back">Back</button>
                            <button type="button" onclick="goToStep(3)" class="pkg-btn-next">Next</button>
                        </div>
                    </div>
                </div>

                <!-- ======================================================== -->
                <!-- STEP 3: SELECTED PACKAGE DETAILS (Image 2 & 5 Reference) -->
                <!-- ======================================================== -->
                <div id="stepSection3" class="panel pkg-step-content" style="padding:1.75rem;margin-bottom:1.5rem;border-radius:16px;background:#ffffff;border:1px solid #e2e8f0;box-shadow:0 1px 4px rgba(0,0,0,0.04);display:none;">
                    <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:1.25rem;border-bottom:1px solid #f1f5f9;padding-bottom:0.85rem;">
                        <span style="font-size:1.5rem;">📋</span>
                        <div>
                            <h3 style="margin:0;font-size:1.2rem;font-weight:800;color:#0f172a;">Step 3: Selected Package Details</h3>
                            <p class="hint mb-0" style="font-size:0.85rem;color:#64748b;">Review and manage the details of selected listings, auto-retrieved host credentials, bundled inclusions, and traveler policies.</p>
                        </div>
                    </div>

                    <!-- 1. HOST INFORMATION CARD (Image 2 Reference) -->
                    <div style="margin-bottom:2rem;">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.75rem;">
                            <label style="font-weight:800;font-size:1.05rem;color:#0f172a;margin:0;display:flex;align-items:center;gap:0.4rem;">
                                <span>🧑‍💼</span> Package Host Information (Auto-Retrieved)
                            </label>
                            <span style="font-size:0.75rem;color:#059669;font-weight:700;background:#d1fae5;padding:3px 10px;border-radius:999px;">
                                ✓ Verified Host Sync
                            </span>
                        </div>

                        <!-- Host Card with White Background -->
                        <div style="background:#ffffff;border-radius:16px;padding:1.5rem 1.75rem;color:#0f172a;border:1px solid #e2e8f0;box-shadow:0 1px 4px rgba(0,0,0,0.04);">
                            <div style="display:flex;align-items:center;gap:1.15rem;margin-bottom:1rem;">
                                <div style="width:68px;height:68px;border-radius:50%;overflow:hidden;border:2px solid #10b981;flex-shrink:0;background:#f1f5f9;">
                                    <img src="<?= e($hostAvatar) ?>" alt="<?= e($hostName) ?>" style="width:100%;height:100%;object-fit:cover;">
                                </div>
                                <div style="flex:1;">
                                    <div style="display:flex;align-items:center;gap:0.5rem;">
                                        <h3 style="margin:0;font-size:1.3rem;font-weight:800;color:#0f172a;"><?= e($hostName) ?></h3>
                                        <span style="display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;border-radius:50%;background:#10b981;color:#ffffff;font-size:12px;font-weight:bold;">✓</span>
                                    </div>
                                    <div style="font-size:0.9rem;color:#475569;margin-top:2px;font-weight:500;">
                                        <?= e($hostRole) ?>
                                    </div>
                                    <div style="font-size:0.85rem;color:#059669;margin-top:4px;font-weight:600;">
                                        <?php if ($hostReviewCount > 0 && $hostRating > 0): ?>
                                            <?= $isApprovedGuide ? 'DOT Accredited • ' : '' ?><?= number_format($hostRating, 1) ?> ★ (<?= $hostReviewCount ?> <?= $hostReviewCount === 1 ? 'review' : 'reviews' ?>) • <?= $hostToursCount ?>+ tours hosted
                                        <?php elseif ($hostToursCount > 0): ?>
                                            <?= $isApprovedGuide ? 'DOT Accredited • ' : '' ?>Verified Host • <?= $hostToursCount ?>+ tours hosted
                                        <?php else: ?>
                                            <?= $isApprovedGuide ? 'DOT Accredited • ' : '' ?>Verified Tour Guide Host
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <p style="margin:0 0 1.25rem 0;font-size:0.9rem;line-height:1.6;color:#334155;font-style:italic;">
                                "<?= e($hostBio) ?>"
                            </p>
                            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.75rem;">
                                <div style="border:1px solid #10b981;color:#047857;padding:0.6rem 1.5rem;border-radius:999px;font-size:0.85rem;font-weight:700;display:inline-flex;align-items:center;gap:0.4rem;background:#ecfdf5;">
                                    <span>💬</span> Message Guide Host
                                </div>
                                <div style="font-size:0.8rem;color:#64748b;">
                                    Automatically linked from your logged-in profile. No repetitive data entry needed.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 2. SELECTED ACTIVITIES & ATTRACTIONS SUMMARY -->
                    <div style="margin-bottom:2rem;">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.75rem;">
                            <label style="font-weight:800;font-size:1.05rem;color:#0f172a;margin:0;display:flex;align-items:center;gap:0.4rem;">
                                <span>🎯</span> Bundled Activities & Attractions
                            </label>
                            <span id="selectedActivitiesDetailsCount" style="background:#10b981;color:#ffffff;padding:2px 10px;border-radius:999px;font-size:0.75rem;font-weight:700;">0 Bundled</span>
                        </div>
                        <div id="step3ActivitiesList" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(300px, 1fr));gap:1rem;">
                            <!-- Populated dynamically via renderStep3Details() -->
                        </div>
                        <div id="step3NoActivitiesAlert" style="text-align:center;padding:2rem 1rem;background:#f8fafc;border-radius:12px;border:1px solid #e2e8f0;color:#64748b;">
                            <p style="margin:0;font-size:0.9rem;">No activities selected yet in Step 2. Return to Step 2 to bundle tours and attractions.</p>
                        </div>
                    </div>

                    <!-- 3. INCLUDED HOTEL DETAILS CARD (Image 5 Reference) -->
                    <div style="margin-bottom:2rem;">
                        <label style="font-weight:800;font-size:1.05rem;color:#0f172a;margin-bottom:0.75rem;display:flex;align-items:center;gap:0.4rem;">
                            <span>🏨</span> Included Accommodation Details
                        </label>
                        <div style="background:#ffffff;border-radius:16px;padding:1.5rem;color:#0f172a;border:1px solid #e2e8f0;box-shadow:0 1px 4px rgba(0,0,0,0.04);">
                            <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.6rem;">
                                <span style="font-size:1.3rem;color:#f59e0b;">🛏️</span>
                                <h4 style="margin:0;font-size:1.15rem;font-weight:800;color:#0f172a;">Included Hotel Details</h4>
                            </div>
                            <h3 id="step3HotelName" style="margin:0 0 0.25rem 0;font-size:1.25rem;font-weight:800;color:#0f172a;">
                                No Hotel Selected
                            </h3>
                            <div id="step3HotelSpecs" style="font-size:0.875rem;color:#64748b;margin-bottom:1rem;">
                                Select a partner hotel or stay in Step 2 to bundle accommodation.
                            </div>
                            <div style="display:flex;flex-wrap:wrap;gap:0.5rem;margin-bottom:1rem;" id="step3HotelAmenities">
                                <span class="hint" style="color:#64748b;font-size:0.85rem;">Please select a hotel in Step 2 to view included amenities.</span>
                            </div>
                            <div style="border-top:1px solid #e2e8f0;padding-top:0.75rem;font-size:0.85rem;color:#475569;display:flex;gap:1.5rem;flex-wrap:wrap;">
                                <span id="step3HotelCheckIn"><strong>Check-in:</strong> Not selected (Select in Step 2)</span>
                                <span id="step3HotelCheckOut"><strong>Check-out:</strong> Not selected (Select in Step 2)</span>
                            </div>
                        </div>
                    </div>

                    <!-- 4. INCLUDED RENTAL VEHICLE CARD (Image 5 Reference) -->
                    <div style="margin-bottom:2rem;">
                        <label style="font-weight:800;font-size:1.05rem;color:#0f172a;margin-bottom:0.75rem;display:flex;align-items:center;gap:0.4rem;">
                            <span>🛵</span> Included Rental Vehicle
                        </label>
                        <div style="background:#ffffff;border-radius:16px;padding:1.5rem;color:#0f172a;border:1px solid #e2e8f0;box-shadow:0 1px 4px rgba(0,0,0,0.04);">
                            <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.6rem;">
                                <span style="font-size:1.3rem;color:#3b82f6;">🚗</span>
                                <h4 style="margin:0;font-size:1.15rem;font-weight:800;color:#0f172a;">Included Rental Vehicle</h4>
                            </div>
                            <h3 id="step3VehicleModel" style="margin:0 0 0.25rem 0;font-size:1.25rem;font-weight:800;color:#0f172a;">
                                No Rental Vehicle Selected
                            </h3>
                            <div id="step3VehicleSpecs" style="font-size:0.875rem;color:#64748b;margin-bottom:1rem;">
                                Select a vehicle rental in Step 2 to bundle transportation.
                            </div>
                            <div style="display:flex;flex-wrap:wrap;gap:0.5rem;margin-bottom:1rem;" id="step3VehicleFeatures">
                                <span class="hint" style="color:#64748b;font-size:0.85rem;">Please select a rental vehicle in Step 2 to view included features.</span>
                            </div>
                            <div style="border-top:1px solid #e2e8f0;padding-top:0.75rem;font-size:0.85rem;color:#475569;display:flex;gap:1.5rem;flex-wrap:wrap;">
                                <span id="step3VehicleHandover"><strong>Handover:</strong> Not selected (Select in Step 2)</span>
                                <span id="step3VehicleReturn"><strong>Return:</strong> Not selected (Select in Step 2)</span>
                            </div>
                        </div>
                    </div>

                    <!-- 5. ALL-IN-ONE INCLUSIONS & EXCLUSIONS (Image 3 Reference) -->
                    <div style="background:#f8fafc;padding:1.5rem;border-radius:14px;border:1px solid #e2e8f0;margin-bottom:1.5rem;">
                        <h4 style="margin:0 0 0.5rem 0;font-size:1.05rem;font-weight:800;color:#0f172a;display:flex;align-items:center;gap:0.4rem;">
                            <span>🎁</span> Included Perks & What's Not Included
                        </h4>
                        <p style="font-size:0.85rem;color:#64748b;margin:0 0 1rem 0;">Confirm package perks and transparent exclusions so tourists know what is covered.</p>
                        
                        <div class="form-grid-2">
                            <div class="field-row">
                                <label style="font-weight:700;color:#0f172a;">Included Perks & Permits</label>
                                <textarea class="input pkg-input" name="inclusions" rows="4" style="resize:vertical;font-size:0.85rem;" placeholder="All Environmental & Local Tourism Fees (Includes river rescue gear, wet shoes, and recovery hot lunch)&#10;Licensed Local Tour Guide Host & Trip Coordination"><?= e($package['included'] ?? '') ?></textarea>
                            </div>
                            <div class="field-row">
                                <label style="font-weight:700;color:#0f172a;">What's Not Included (One per line)</label>
                                <textarea class="input pkg-input" name="exclusions" rows="4" style="resize:vertical;font-size:0.85rem;" placeholder="Airfare to and from Cebu (Mactan)&#10;Personal souvenir shopping & alcoholic drinks&#10;Optional zip-line upgrade at Kawasan starting point (₱600)"><?= e($package['not_included'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- 6. POLICIES & REQUIREMENTS (Image 5 Reference) -->
                    <div class="field-row" style="background:#f8fafc;padding:1.5rem;border-radius:14px;border:1px solid #e2e8f0;margin-bottom:1.5rem;">
                        <label for="pkg_policies" style="font-weight:800;font-size:1rem;color:#0f172a;display:flex;align-items:center;gap:0.4rem;margin-bottom:0.5rem;">
                            <span>📄</span> Policies & Requirements
                        </label>
                        <textarea class="input pkg-input" id="pkg_policies" name="policies" rows="5"
                                  placeholder="e.g. Free cancellation up to 48 hours prior to trip departure (50% refund within 24-48 hours).&#10;Requirements:&#10;• Valid Government ID or Passport for all participants&#10;• Valid Motorcycle/Driver's license for vehicle rental&#10;• Moderate physical fitness for canyoneering"
                                  style="resize:vertical;font-family:inherit;font-size:0.85rem;line-height:1.5;width:100%;box-sizing:border-box;"><?= e($defaultPolicies) ?></textarea>
                        <span class="hint" style="font-size:0.8rem;color:#64748b;display:block;margin-top:0.25rem;">Provides clear conditions matching the customer package display in Image 5.</span>
                    </div>

                    <!-- Bottom Navigation: Step 3 -> Step 4 -->
                    <div class="pkg-step-actions-row">
                        <div>
                            <a href="<?= e(url('/dashboard/listings')) ?>" class="pkg-btn-cancel" onclick="return confirmDiscardPackage(event);">Cancel</a>
                        </div>
                        <div style="display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap;">
                            <button type="button" onclick="goToStep(2)" class="pkg-btn-back">Back</button>
                            <button type="button" onclick="goToStep(4)" class="pkg-btn-next">Next</button>
                        </div>
                    </div>
                </div>

                <!-- ======================================================== -->
                <!-- STEP 4: DAY-BY-DAY ITINERARY (Image 4 Reference)         -->
                <!-- ======================================================== -->
                <div id="stepSection4" class="panel pkg-step-content" style="padding:1.75rem;margin-bottom:1.5rem;border-radius:16px;background:#ffffff;border:1px solid #e2e8f0;box-shadow:0 1px 4px rgba(0,0,0,0.04);display:none;">
                    <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:1.25rem;border-bottom:1px solid #f1f5f9;padding-bottom:0.85rem;">
                        <span style="font-size:1.5rem;">📅</span>
                        <div>
                            <h3 style="margin:0;font-size:1.2rem;font-weight:800;color:#0f172a;">Step 4: Day-by-Day Itinerary</h3>
                            <p class="hint mb-0" style="font-size:0.85rem;color:#64748b;">Organize activities, scheduled milestones, and day highlights according to the package duration entered in Step 1.</p>
                        </div>
                    </div>

                    <!-- Dynamic Day Tabs based on package duration (Image 4) -->
                    <div style="margin-bottom:1.25rem;">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.75rem;">
                            <strong style="font-size:0.9rem;color:#0f172a;">Itinerary Schedule Days:</strong>
                            <span id="itineraryDaysCount" style="font-size:0.8rem;color:#059669;font-weight:700;">Duration: 3 Days</span>
                        </div>
                        <div id="itineraryDayTabs" style="display:flex;flex-wrap:wrap;gap:0.5rem;">
                            <!-- Dynamically generated Day 1, Day 2, etc. -->
                        </div>
                    </div>

                    <!-- Itinerary Day Editor Container (White Background Card) -->
                    <div style="background:#ffffff;border-radius:16px;padding:1.75rem;color:#0f172a;border:1px solid #e2e8f0;box-shadow:0 1px 4px rgba(0,0,0,0.04);margin-bottom:1.5rem;">
                        <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:1.25rem;border-bottom:1px solid #f1f5f9;padding-bottom:0.75rem;">
                            <span style="font-size:1.3rem;">📅</span>
                            <h4 style="margin:0;font-size:1.15rem;font-weight:800;color:#0f172a;">Day-by-Day Itinerary</h4>
                        </div>

                        <!-- Active Day Title & Narrative Description -->
                        <div style="margin-bottom:1.25rem;">
                            <label style="font-size:0.8rem;color:#64748b;display:block;margin-bottom:4px;font-weight:600;">Day Title</label>
                            <input class="input pkg-input" type="text" id="activeDayTitle"
                                   placeholder="e.g. Day 1: Coastal Arrival & Moalboal Sunset Marine Sanctuary"
                                   oninput="saveCurrentDayData()"
                                   style="width:100%;box-sizing:border-box;background:#ffffff !important;border:1px solid #cbd5e1 !important;color:#0f172a !important;font-weight:700;">
                        </div>

                        <div style="margin-bottom:1.5rem;">
                            <label style="font-size:0.8rem;color:#64748b;display:block;margin-bottom:4px;font-weight:600;">Day Summary Narrative</label>
                            <textarea class="input pkg-input" id="activeDayDesc" rows="2"
                                      placeholder="e.g. Meet your guide host Marc, pick up your scooter, check in to your resort, and snorkel with turtles."
                                      oninput="saveCurrentDayData()"
                                      style="width:100%;box-sizing:border-box;background:#ffffff !important;border:1px solid #cbd5e1 !important;color:#0f172a !important;resize:vertical;"></textarea>
                        </div>

                        <!-- Timed Schedule Milestones -->
                        <div style="margin-bottom:1.5rem;background:#f8fafc;border-radius:12px;padding:1.25rem;border:1px solid #e2e8f0;">
                            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
                                <strong style="font-size:0.85rem;color:#059669;text-transform:uppercase;letter-spacing:0.04em;">Timed Schedule Timeline</strong>
                                <button type="button" onclick="addTimelineSlot()" style="background:#10b981;color:#ffffff;border:none;border-radius:6px;padding:4px 12px;font-size:0.75rem;font-weight:700;cursor:pointer;">+ Add Time Slot</button>
                            </div>
                            <div id="activeDayTimeline" style="display:flex;flex-direction:column;gap:0.75rem;">
                                <!-- Populated dynamically -->
                            </div>
                        </div>

                        <!-- Day Highlights Checkmarks -->
                        <div style="background:#f8fafc;border-radius:12px;padding:1.25rem;border:1px solid #e2e8f0;">
                            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.75rem;">
                                <strong style="font-size:0.85rem;color:#059669;text-transform:uppercase;letter-spacing:0.04em;">Day Highlights (Checkmarks)</strong>
                                <button type="button" onclick="addDayHighlight()" style="background:#059669;color:#ffffff;border:none;border-radius:6px;padding:4px 12px;font-size:0.75rem;font-weight:700;cursor:pointer;">+ Add Highlight</button>
                            </div>
                            <div id="activeDayHighlights" style="display:flex;flex-direction:column;gap:0.5rem;">
                                <!-- Populated dynamically -->
                            </div>
                        </div>
                    </div>

                    <!-- Hidden JSON field for Itinerary form submission -->
                    <input type="hidden" id="pkg_itinerary" name="itinerary" value="<?= e($defaultItinerary) ?>" placeholder="e.g. Day 1: Coastal Arrival, Resort Check-in & Moalboal Turtle Snorkeling (03:00 PM)&#10;Day 2: Full Kawasan Canyoneering & Cliff Jumps (08:30 AM)&#10;Day 3: Coral Reef Cruise & Return at Midday">

                    <!-- Bottom Navigation: Step 4 -> Step 5 -->
                    <div class="pkg-step-actions-row">
                        <div>
                            <a href="<?= e(url('/dashboard/listings')) ?>" class="pkg-btn-cancel" onclick="return confirmDiscardPackage(event);">Cancel</a>
                        </div>
                        <div style="display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap;">
                            <button type="button" onclick="goToStep(3)" class="pkg-btn-back">Back</button>
                            <button type="button" onclick="goToStep(5)" class="pkg-btn-next">Next</button>
                        </div>
                    </div>
                </div>

                <!-- ======================================================== -->
                <!-- STEP 5: PRICING – PACKAGE COST BREAKDOWN                 -->
                <!-- ======================================================== -->
                <div id="stepSection5" class="panel pkg-step-content" style="padding:1.75rem;margin-bottom:1.5rem;border-radius:16px;background:#ffffff;border:1px solid #e2e8f0;box-shadow:0 1px 4px rgba(0,0,0,0.04);display:none;">
                    <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:1.25rem;border-bottom:1px solid #f1f5f9;padding-bottom:0.85rem;">
                        <span style="font-size:1.5rem;">💎</span>
                        <div>
                            <h3 style="margin:0;font-size:1.2rem;font-weight:800;color:#0f172a;">Step 5: Pricing – Package Cost Breakdown</h3>
                            <p class="hint mb-0" style="font-size:0.85rem;color:#64748b;">Comprehensive cost calculation of all selected services, hotel nights, vehicle days, and guide fee.</p>
                        </div>
                    </div>

                    <!-- LIVE FINANCIAL SIMULATOR CARD -->
                    <div style="background:#f8fafc;color:#0f172a;padding:1.5rem;border-radius:16px;margin-bottom:1.5rem;box-shadow:0 1px 4px rgba(0,0,0,0.04);border:1px solid #e2e8f0;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;border-bottom:1px solid #e2e8f0;padding-bottom:0.75rem;">
                            <span style="font-size:0.85rem;text-transform:uppercase;letter-spacing:0.08em;font-weight:800;color:#059669;">All-Inclusive Package Split Simulation</span>
                            <span style="background:#ecfdf5;color:#047857;padding:3px 12px;border-radius:999px;font-size:0.75rem;font-weight:700;">Based on 2 Pax Standard Occupancy</span>
                        </div>

                        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(160px, 1fr));gap:0.85rem;margin-bottom:1.25rem;">
                            <div style="background:#ffffff;padding:0.85rem;border-radius:12px;border:1px solid #e2e8f0;">
                                <span style="font-size:0.75rem;color:#64748b;display:block;">🏄 Things to Do</span>
                                <strong id="sim_activity_fee" style="font-size:1.25rem;display:block;margin-top:2px;color:#0f172a;">₱0.00</strong>
                            </div>

                            <div style="background:#ffffff;padding:0.85rem;border-radius:12px;border:1px solid #e2e8f0;">
                                <span style="font-size:0.75rem;color:#64748b;display:block;">🏞️ Attractions</span>
                                <strong id="sim_attraction_fee" style="font-size:1.25rem;display:block;margin-top:2px;color:#0f172a;">₱0.00</strong>
                            </div>

                            <div style="background:#ffffff;padding:0.85rem;border-radius:12px;border:1px solid #e2e8f0;">
                                <span style="font-size:0.75rem;color:#64748b;display:block;">🏨 Hotel Stay</span>
                                <strong id="sim_hotel_fee" style="font-size:1.25rem;display:block;margin-top:2px;color:#0f172a;">₱2,200.00</strong>
                            </div>

                            <div style="background:#ffffff;padding:0.85rem;border-radius:12px;border:1px solid #e2e8f0;">
                                <span style="font-size:0.75rem;color:#64748b;display:block;">🛵 Transport</span>
                                <strong id="sim_rental_fee" style="font-size:1.25rem;display:block;margin-top:2px;color:#0f172a;">₱975.00</strong>
                            </div>

                            <div style="background:#f0fdf4;padding:0.85rem;border-radius:12px;border:1px solid #a7f3d0;">
                                <span style="font-size:0.75rem;color:#047857;display:block;font-weight:600;">🧑‍💼 Guide Fee / Margin</span>
                                <strong id="sim_guide_margin" style="font-size:1.25rem;display:block;margin-top:2px;color:#047857;">₱<?= $isEdit ? '2,200.00' : '0.00' ?></strong>
                            </div>

                            <div style="background:#ffffff;padding:0.85rem;border-radius:12px;border:1px solid #e2e8f0;">
                                <span style="font-size:0.75rem;color:#64748b;display:block;">🛡️ GuideMate Fee (8%)</span>
                                <strong id="sim_platform_fee" style="font-size:1.25rem;display:block;margin-top:2px;color:#0f172a;">₱430.00</strong>
                            </div>
                        </div>

                        <div style="display:flex;align-items:center;justify-content:space-between;border-top:1px dashed #cbd5e1;padding-top:1.15rem;padding-bottom:0.35rem;flex-wrap:wrap;gap:1rem;">
                            <div>
                                <span style="font-size:1.05rem;color:#0f172a;text-transform:uppercase;font-weight:800;letter-spacing:0.04em;">
                                    💰 Calculated Total Package Price
                                </span>
                                <small style="display:block;color:#64748b;font-size:0.8rem;margin-top:2px;">Published price per traveler in customer package listing</small>
                            </div>
                            <div style="text-align:right;">
                                <span id="sim_final_total" style="font-size:2.4rem;font-weight:900;color:#059669;letter-spacing:-0.03em;">₱<?= number_format($defaultPrice, 2) ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Hidden Inputs for Form Submission -->
                    <input type="hidden" id="calc_activity_fee" name="calc_activity_fee" value="0">
                    <input type="hidden" id="calc_attraction_fee" name="calc_attraction_fee" value="0">
                    <input type="hidden" id="calc_hotel_fee" name="calc_hotel_fee" value="2200">
                    <input type="hidden" id="calc_rental_fee" name="calc_rental_fee" value="975">
                    <input type="hidden" id="calc_platform_fee" name="calc_platform_fee" value="430">
                    <input type="hidden" id="final_price_per_pax" name="final_price_per_pax" value="<?= $defaultPrice ?>">
                    <input type="hidden" id="total_package_price" name="total_package_price" value="<?= $defaultPrice ?>">

                    <!-- Interactive Guide Margin Controller -->
                    <div style="background:#f8fafc;padding:1.25rem;border-radius:14px;border:1px solid #e2e8f0;margin-bottom:1.5rem;">
                        <label for="guide_margin" style="font-weight:700;display:flex;justify-content:space-between;color:#0f172a;margin-bottom:0.5rem;">
                            <span>Guide Curation & Hosting Margin (₱)</span>
                            <span id="guide_margin_val" style="color:#047857;font-weight:800;">₱<?= $isEdit ? '2,200' : '0' ?></span>
                        </label>
                        <input class="input pkg-input" type="number" id="guide_margin" name="guide_margin" min="0" step="50" value="<?= $isEdit ? '2200' : '' ?>" placeholder="e.g. 2000" oninput="calculateFinancials()" style="font-size:1.15rem;font-weight:800;color:#047857;width:100%;box-sizing:border-box;">
                        <span class="hint" style="font-size:0.8rem;color:#64748b;display:block;margin-top:0.4rem;">
                            Adjust your earnings margin for curating this travel bundle. The Calculated Total Package Price automatically updates in real time.
                        </span>
                    </div>

                    <!-- Bottom Navigation: Step 5 Final Submission -->
                    <div class="pkg-step-actions-row">
                        <div>
                            <a href="<?= e(url('/dashboard/listings')) ?>" class="pkg-btn-cancel" onclick="return confirmDiscardPackage(event);">Cancel</a>
                        </div>
                        <div style="display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap;">
                            <button type="button" onclick="goToStep(4)" class="pkg-btn-back">Back</button>
                            <button type="submit" class="pkg-btn-next" style="padding:0.75rem 2.2rem;font-size:1rem;box-shadow:0 4px 14px rgba(16,185,129,0.3);">
                                <?= $isEdit ? 'Save Package Changes' : 'Submit Package for Approval' ?>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Modern Clean White-Theme Package Builder Styling */
.pkg-input {
    background: #ffffff !important;
    border: 1px solid #cbd5e1 !important;
    color: #0f172a !important;
    border-radius: 10px !important;
    padding: 0.65rem 0.85rem !important;
    font-size: 0.95rem !important;
    font-weight: 500 !important;
    transition: all 0.2s ease;
}
.pkg-input:focus {
    border-color: #10b981 !important;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15) !important;
    outline: none !important;
}

/* Choice Pills for Badges in Step 1 */
.pkg-choice-pill {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    color: #475569;
    padding: 0.45rem 1rem;
    border-radius: 999px;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}
.pkg-choice-pill:hover {
    border-color: #94a3b8;
    background: #f8fafc;
    transform: none !important;
}
.pkg-choice-pill.active {
    background: #f59e0b !important;
    border-color: #d97706 !important;
    color: #ffffff !important;
    box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3) !important;
}

/* Category Filter Buttons in Step 2 */
.pkg-category-filter-btn {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    color: #475569;
    padding: 0.4rem 0.9rem;
    border-radius: 999px;
    font-size: 0.8rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
}
.pkg-category-filter-btn:hover {
    background: #f1f5f9;
    border-color: #94a3b8;
    transform: none !important;
}
.pkg-category-filter-btn.active {
    background: #10b981;
    border-color: #059669;
    color: #ffffff;
}

/* Stepper Pills */
.pkg-step-pill {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.6rem 0.75rem;
    border-radius: 10px;
    cursor: pointer;
    border: 1px solid transparent;
    transition: all 0.2s ease;
}
.pkg-step-pill:hover {
    background: #f8fafc;
    transform: none !important;
}
.pkg-step-pill.active {
    background: #ecfdf5;
    border-color: #a7f3d0;
}
.pkg-step-num {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    background: #f1f5f9;
    color: #64748b;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 0.8rem;
    flex-shrink: 0;
}
.pkg-step-pill.active .pkg-step-num {
    background: #10b981;
    color: #ffffff;
}
.pkg-step-label strong {
    display: block;
    font-size: 0.8rem;
    color: #0f172a;
    line-height: 1.1;
}
.pkg-step-label small {
    display: block;
    font-size: 0.7rem;
    color: #64748b;
}

/* Destination & Radio Cards */
.pkg-destination-card, .pkg-card-radio {
    position: relative;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: #ffffff;
    overflow: hidden;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    transition: all 0.2s ease;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
}
.pkg-destination-card:hover, .pkg-card-radio:hover {
    border-color: #cbd5e1;
    transform: none;
    box-shadow: 0 6px 16px rgba(0,0,0,0.08);
}
.pkg-destination-card.is-selected, .pkg-card-radio.selected {
    border: 2px solid #10b981 !important;
    background: #f0fdf4 !important;
    box-shadow: 0 0 0 1px #10b981, 0 8px 20px rgba(16, 185, 129, 0.12) !important;
}
.pkg-card-img-wrap {
    position: relative;
    width: 100%;
    height: 150px;
    background: #f1f5f9;
}
.pkg-card-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.pkg-category-badge {
    position: absolute;
    top: 0.65rem;
    left: 0.65rem;
    font-size: 0.7rem;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 6px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.badge-purple { background: rgba(16, 185, 129, 0.95); color: #ffffff; }
.badge-pink { background: rgba(236, 72, 153, 0.95); color: #ffffff; }
.badge-amber { background: rgba(245, 158, 11, 0.95); color: #ffffff; }
.badge-blue { background: rgba(37, 99, 235, 0.95); color: #ffffff; }

.pkg-card-select-pill {
    position: absolute;
    top: 0.65rem;
    right: 0.65rem;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(4px);
    border: 1px solid #e2e8f0;
    color: #0f172a;
    border-radius: 999px;
    padding: 3px 10px;
    font-size: 0.75rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 4px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.06);
}
.pkg-destination-card.is-selected .pkg-card-select-pill {
    background: #10b981;
    border-color: #059669;
    color: #ffffff;
}

.pkg-card-body {
    padding: 0.85rem 1rem 1rem 1rem;
    flex: 1;
    display: flex;
    flex-direction: column;
}
.pkg-card-location {
    font-size: 0.75rem;
    color: #64748b;
    margin-bottom: 0.25rem;
    display: flex;
    align-items: center;
    gap: 3px;
    font-weight: 500;
}
.pkg-card-title {
    margin: 0 0 0.5rem 0;
    font-size: 0.95rem;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.3;
}
.pkg-card-footer {
    margin-top: auto;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-top: 0.5rem;
    border-top: 1px solid #f1f5f9;
}
.pkg-card-price {
    font-size: 0.95rem;
    font-weight: 800;
    color: #059669;
}
.pkg-card-price small {
    font-size: 0.7rem;
    color: #64748b;
    font-weight: 500;
}
.pkg-card-duration {
    font-size: 0.75rem;
    color: #64748b;
}
.pkg-perk-pill {
    display: inline-block;
    padding: 2px 7px;
    border-radius: 5px;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    font-size: 0.7rem;
    color: #334155;
    font-weight: 600;
}
.pkg-amenity-badge {
    background: #fef3c7;
    border: 1px solid #fde68a;
    color: #92400e;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.pkg-feature-badge {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    color: #1e40af;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.pkg-day-tab-btn {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    color: #475569;
    padding: 0.5rem 1.25rem;
    border-radius: 999px;
    font-size: 0.85rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
}
.pkg-day-tab-btn.active {
    background: #10b981;
    border-color: #059669;
    color: #ffffff;
}

/* Action Button Row & Typography Styling Matching System Design Tokens */
.pkg-step-actions-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 1.5rem;
    border-top: 1px solid #f1f5f9;
    padding-top: 1.25rem;
    flex-wrap: wrap;
    gap: 0.75rem;
}
.pkg-btn-cancel {
    font-family: 'Plus Jakarta Sans', system-ui, -apple-system, Segoe UI, Roboto, sans-serif !important;
    font-size: 0.95rem !important;
    font-weight: 600 !important;
    letter-spacing: -0.01em !important;
    line-height: 1.2 !important;
    border: 1px solid #cbd5e1 !important;
    background: #ffffff !important;
    color: #64748b !important;
    border-radius: 999px !important;
    padding: 0.7rem 1.6rem !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 0.4rem !important;
    text-decoration: none !important;
    cursor: pointer !important;
    transition: all 0.15s ease !important;
    white-space: nowrap !important;
}
.pkg-btn-cancel:hover {
    color: #ef4444 !important;
    border-color: #fca5a5 !important;
    background: #fef2f2 !important;
    transform: none !important;
}
.pkg-btn-back {
    font-family: 'Plus Jakarta Sans', system-ui, -apple-system, Segoe UI, Roboto, sans-serif !important;
    font-size: 0.95rem !important;
    font-weight: 600 !important;
    letter-spacing: -0.01em !important;
    line-height: 1.2 !important;
    border: 1px solid #cbd5e1 !important;
    background: #ffffff !important;
    color: #334155 !important;
    border-radius: 999px !important;
    padding: 0.7rem 1.6rem !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 0.4rem !important;
    cursor: pointer !important;
    transition: all 0.15s ease !important;
    white-space: nowrap !important;
}
.pkg-btn-back:hover {
    background: #f8fafc !important;
    color: #0f172a !important;
    border-color: #94a3b8 !important;
    transform: none !important;
}
.pkg-btn-next {
    font-family: 'Plus Jakarta Sans', system-ui, -apple-system, Segoe UI, Roboto, sans-serif !important;
    font-size: 0.95rem !important;
    font-weight: 700 !important;
    letter-spacing: -0.01em !important;
    line-height: 1.2 !important;
    background: #10b981 !important;
    color: #ffffff !important;
    border: 1.5px solid transparent !important;
    border-radius: 999px !important;
    padding: 0.7rem 1.75rem !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 0.45rem !important;
    cursor: pointer !important;
    box-shadow: 0 2px 6px rgba(16, 185, 129, 0.25) !important;
    transition: box-shadow 0.15s ease, background 0.15s ease !important;
    text-decoration: none !important;
    white-space: nowrap !important;
}
.pkg-btn-next:hover {
    background: #059669 !important;
    color: #ffffff !important;
    transform: none !important;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.35) !important;
}
.pkg-btn-next:active {
    transform: none !important;
}

/* Form Validation Error Styles */
.pkg-input.is-invalid, .input.is-invalid, textarea.is-invalid {
    border-color: #ef4444 !important;
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.15) !important;
}
.pkg-field-error {
    color: #dc2626;
    font-size: 0.8rem;
    font-weight: 600;
    margin-top: 4px;
    display: flex;
    align-items: center;
    gap: 4px;
    line-height: 1.4;
}

@media (max-width: 640px) {
    .pkg-step-actions-row {
        flex-direction: column-reverse;
        align-items: stretch;
    }
    .pkg-step-actions-row > div {
        display: flex;
        flex-direction: column;
        width: 100%;
        gap: 0.5rem;
    }
    .pkg-btn-cancel, .pkg-btn-back, .pkg-btn-next {
        width: 100%;
        justify-content: center;
        text-align: center;
    }
}
</style>

<script>
// Selected activities state: empty in create mode, pre-populated in edit mode
let selectedActivities = <?= json_encode($selectedActivities ?? []) ?>;

let selectedHotelRate = <?= $isEdit && !empty($selectedHotelRate) ? (float) $selectedHotelRate : 0 ?>;
let selectedHotelName = "<?= $isEdit && !empty($selectedHotelName) ? e(addslashes($selectedHotelName)) : 'None' ?>";
let selectedHotelRoom = "<?= $isEdit && !empty($selectedHotelRoom) ? e(addslashes($selectedHotelRoom)) : '' ?>";
// Partner hotel amenities: Free High-Speed Wi-Fi, Swimming Pool Access, Complimentary Daily Breakfast
let selectedHotelAmenities = <?= $isEdit && !empty($selectedHotelAmenities) ? json_encode($selectedHotelAmenities) : '[]' ?>;
let selectedHotelCheckin = "<?= $isEdit && !empty($selectedHotelCheckin) ? e($selectedHotelCheckin) : '' ?>";
let selectedHotelCheckout = "<?= $isEdit && !empty($selectedHotelCheckout) ? e($selectedHotelCheckout) : '' ?>";

let selectedVehicleDailyRate = <?= $isEdit && !empty($selectedVehicleDailyRate) ? (float) $selectedVehicleDailyRate : 0 ?>;
let selectedVehicleModel = "<?= $isEdit && !empty($selectedVehicleModel) ? e(addslashes($selectedVehicleModel)) : 'None' ?>";
// Partner vehicle features: 2 DOT-Certified Safety Helmets, Comprehensive 24/7 Roadside Assistance
let selectedVehicleSpecs = <?= $isEdit && !empty($selectedVehicleSpecs) ? json_encode($selectedVehicleSpecs) : '[]' ?>;
let selectedVehicleType = "<?= $isEdit && !empty($selectedVehicleType) ? e($selectedVehicleType) : '' ?>";
let selectedVehicleLocation = "<?= $isEdit && !empty($selectedVehicleLocation) ? e($selectedVehicleLocation) : '' ?>";
let selectedVehicleHandover = "<?= $isEdit && !empty($selectedVehicleHandover) ? e($selectedVehicleHandover) : '' ?>";
let selectedVehicleReturn = "<?= $isEdit && !empty($selectedVehicleReturn) ? e($selectedVehicleReturn) : '' ?>";

// Multi-day itinerary data store & cache
let itineraryDaysCache = {};
let itineraryDaysData = [
    {
        day: 1,
        title: "Day 1: Coastal Arrival & Moalboal Sunset Marine Sanctuary",
        desc: "Meet your guide host Marc, pick up your scooter, check in to your resort, and snorkel with turtles.",
        schedule: [
            { time: "01:00 PM", activity: "Resort check-in & scooter handover briefing" },
            { time: "03:00 PM", activity: "Guided snorkeling session at Panagsama marine sanctuary" },
            { time: "05:30 PM", activity: "Golden hour sunset viewing and leisure dinner" }
        ],
        highlights: [
            "Pick up your Yamaha NMAX scooter in Moalboal (or complimentary town greeting)",
            "Check-in at Sea Breeze Oceanfront Resort and freshen up",
            "Afternoon guided snorkeling: Swim alongside millions of sardines and resident sea turtles",
            "Sunset seafood dinner recommendation along Panagsama beachfront strip"
        ]
    },
    {
        day: 2,
        title: "Day 2: Full Kawasan Canyoneering Expedition",
        desc: "Guided canyon trek, cliff jumps, natural slides, and waterfall boodle fight lunch.",
        schedule: [
            { time: "07:30 AM", activity: "Complimentary resort breakfast" },
            { time: "08:30 AM", activity: "Scenic convoy ride to Badian Canyoneering outpost" },
            { time: "09:15 AM", activity: "Canyon descent begins with certified master guide" },
            { time: "01:30 PM", activity: "Finish at Main Kawasan cascade & hot boodle lunch" },
            { time: "03:30 PM", activity: "Return to resort for swimming and evening rest" }
        ],
        highlights: [
            "Full canyoneering route with certified canyon master",
            "Cliff jumps (3m to 12m) with certified helmets & vests",
            "Hot Filipino waterfall boodle fight lunch included"
        ]
    },
    {
        day: 3,
        title: "Day 3: Coastal Cruise & Departure",
        desc: "Morning coastal ride, souvenir shopping, scooter return, and farewell.",
        schedule: [
            { time: "08:00 AM", activity: "Morning leisure and checkout" },
            { time: "11:00 AM", activity: "Trip conclusion, scooter return, and transfers" }
        ],
        highlights: [
            "Morning coastal ride along South Cebu highway",
            "Souvenir shopping assistance in Moalboal town center",
            "Scooter handover inspection and travel farewell"
        ]
    }
];
// Populate initial cache
itineraryDaysData.forEach(d => { itineraryDaysCache[d.day] = JSON.parse(JSON.stringify(d)); });
let currentActiveDayIndex = 0;

// =============================================================
// Validation System for All Steps (Trim whitespace & friendly errors)
// =============================================================
function clearStepErrors(step) {
    const sec = document.getElementById('stepSection' + step);
    if (!sec) return;
    const errorEls = sec.querySelectorAll('.pkg-field-error');
    errorEls.forEach(el => el.remove());
    const invalidInputs = sec.querySelectorAll('.is-invalid');
    invalidInputs.forEach(el => {
        el.classList.remove('is-invalid');
        el.style.borderColor = '';
        el.style.boxShadow = '';
    });
}

function showFieldError(fieldEl, message) {
    if (!fieldEl) return;
    fieldEl.classList.add('is-invalid');
    fieldEl.style.borderColor = '#ef4444';
    fieldEl.style.boxShadow = '0 0 0 3px rgba(239, 68, 68, 0.15)';

    const parent = fieldEl.closest('.field-row') || fieldEl.parentElement;
    const existing = parent ? parent.querySelector('.pkg-field-error') : null;
    if (existing) existing.remove();

    const err = document.createElement('div');
    err.className = 'pkg-field-error';
    err.innerHTML = '<span>⚠️</span> <span>' + escapeHtml(message) + '</span>';

    if (fieldEl.nextSibling) {
        fieldEl.parentNode.insertBefore(err, fieldEl.nextSibling);
    } else {
        fieldEl.parentNode.appendChild(err);
    }

    const clearHandler = function() {
        fieldEl.classList.remove('is-invalid');
        fieldEl.style.borderColor = '';
        fieldEl.style.boxShadow = '';
        err.remove();
        fieldEl.removeEventListener('input', clearHandler);
        fieldEl.removeEventListener('change', clearHandler);
    };
    fieldEl.addEventListener('input', clearHandler);
    fieldEl.addEventListener('change', clearHandler);
}

function showSectionError(containerEl, message) {
    if (!containerEl) return;
    const existing = containerEl.querySelector('.pkg-field-error');
    if (existing) existing.remove();

    const err = document.createElement('div');
    err.className = 'pkg-field-error';
    err.style.color = '#dc2626';
    err.style.fontSize = '0.82rem';
    err.style.fontWeight = '700';
    err.style.marginTop = '8px';
    err.style.padding = '8px 12px';
    err.style.background = '#fef2f2';
    err.style.border = '1px solid #fecaca';
    err.style.borderRadius = '8px';
    err.style.display = 'flex';
    err.style.alignItems = 'center';
    err.style.gap = '6px';
    err.innerHTML = '<span>⚠️</span> <span>' + escapeHtml(message) + '</span>';

    containerEl.appendChild(err);
}

function validateStep(step) {
    clearStepErrors(step);
    let isValid = true;

    if (step === 1) {
        // Step 1: Package Overview & Basic Information
        const titleEl = document.getElementById('pkg_title');
        const titleVal = (titleEl?.value || '').trim();
        if (titleVal === '') {
            showFieldError(titleEl, 'Package title is required and cannot be empty or spaces only.');
            isValid = false;
        }

        const areaEl = document.getElementById('pkg_area');
        const areaVal = (areaEl?.value || '').trim();
        if (areaVal === '') {
            showFieldError(areaEl, 'Destination & sub-region is required and cannot be empty.');
            isValid = false;
        }

        const daysEl = document.getElementById('pkg_days');
        const daysVal = (daysEl?.value || '').trim();
        const daysNum = parseInt(daysVal, 10);
        if (daysVal === '' || isNaN(daysNum) || daysNum < 1 || daysNum > 14) {
            showFieldError(daysEl, 'Duration days is required (between 1 and 14 days).');
            isValid = false;
        }

        const nightsEl = document.getElementById('pkg_nights');
        const nightsVal = (nightsEl?.value || '').trim();
        const nightsNum = parseInt(nightsVal, 10);
        if (nightsVal === '' || isNaN(nightsNum) || nightsNum < 0 || nightsNum > 14) {
            showFieldError(nightsEl, 'Duration nights is required (0 to 14 nights).');
            isValid = false;
        }

        const maxPaxEl = document.getElementById('pkg_max_pax');
        const maxPaxVal = (maxPaxEl?.value || '').trim();
        const maxPaxNum = parseInt(maxPaxVal, 10);
        if (maxPaxVal === '' || isNaN(maxPaxNum) || maxPaxNum < 1) {
            showFieldError(maxPaxEl, 'Maximum group capacity is required (at least 1 guest).');
            isValid = false;
        } else {
            const minPaxVal = (document.getElementById('pkg_min_pax')?.value || '').trim();
            const minPaxNum = parseInt(minPaxVal, 10);
            if (!isNaN(minPaxNum) && minPaxNum > maxPaxNum) {
                showFieldError(maxPaxEl, 'Max capacity (' + maxPaxNum + ') cannot be less than min capacity (' + minPaxNum + ').');
                isValid = false;
            }
        }

        const descEl = document.getElementById('pkg_description');
        const descVal = (descEl?.value || '').trim();
        if (descVal === '') {
            showFieldError(descEl, 'Package narrative description is required and cannot be empty.');
            isValid = false;
        }

        // Cover Photo (Photo 1)
        const photoFile = document.getElementById('pkg_file_0')?.files?.length > 0;
        const photoUrl = (document.getElementById('pkg_url_0')?.value || '').trim() !== '';
        const photoExisting = (document.getElementById('pkg_existing_0')?.value || '').trim() !== '';
        const photoRemoved = (document.getElementById('pkg_removed_0')?.value || '0') === '1';
        if (!photoFile && !photoUrl && (!photoExisting || photoRemoved)) {
            showFieldError(document.getElementById('pkg_preview_wrap_0'), 'Cover photo (Photo 1) is required. Upload an image file or provide an image URL.');
            isValid = false;
        }
    } else if (step === 2) {
        // Step 2: Package Selection
        if (!selectedActivities || selectedActivities.length === 0) {
            const thingsBlock = document.querySelector('.pkg-cat-block[data-category="things-to-do"]');
            showSectionError(thingsBlock, 'Please select at least one activity or attraction to bundle into this package.');
            isValid = false;
        }

        const hotelChecked = document.querySelector('input[name="selected_hotel_room"]:checked');
        if (!hotelChecked) {
            const hotelBlock = document.querySelector('.pkg-cat-block[data-category="hotels"]');
            showSectionError(hotelBlock, 'Please select an accommodation option or choose "No Hotel Stay (Day Tour)".');
            isValid = false;
        }

        const vehicleChecked = document.querySelector('input[name="selected_vehicle_id"]:checked');
        if (!vehicleChecked) {
            const vehicleBlock = document.querySelector('.pkg-cat-block[data-category="vehicles"]');
            showSectionError(vehicleBlock, 'Please select a vehicle rental option or choose "No Rental Vehicle".');
            isValid = false;
        }
    } else if (step === 3) {
        // Step 3: Selected Package Details
        const incEl = document.querySelector('textarea[name="inclusions"]');
        const incVal = (incEl?.value || '').trim();
        if (incVal === '') {
            showFieldError(incEl, 'Please specify included perks & permits for this package.');
            isValid = false;
        }

        const polEl = document.getElementById('pkg_policies');
        const polVal = (polEl?.value || '').trim();
        if (polVal === '') {
            showFieldError(polEl, 'Package policies and cancellation requirements are required.');
            isValid = false;
        }
    } else if (step === 4) {
        // Step 4: Day-by-Day Itinerary
        saveCurrentDayData();
        for (let i = 0; i < itineraryDaysData.length; i++) {
            const d = itineraryDaysData[i];
            const title = (d.title || '').trim();
            const desc = (d.desc || '').trim();
            if (title === '') {
                switchItineraryDay(i);
                showFieldError(document.getElementById('activeDayTitle'), 'Day ' + d.day + ' Title is required and cannot be empty.');
                isValid = false;
                break;
            }
            if (desc === '') {
                switchItineraryDay(i);
                showFieldError(document.getElementById('activeDayDesc'), 'Day ' + d.day + ' Summary Narrative is required and cannot be empty.');
                isValid = false;
                break;
            }
            if (!d.schedule || d.schedule.length === 0) {
                switchItineraryDay(i);
                showSectionError(document.getElementById('activeDayTimeline'), 'Day ' + d.day + ' requires at least one scheduled time slot.');
                isValid = false;
                break;
            }
            let hasEmptySlot = false;
            for (let s = 0; s < d.schedule.length; s++) {
                const slot = d.schedule[s];
                if ((slot.time || '').trim() === '' || (slot.activity || '').trim() === '') {
                    switchItineraryDay(i);
                    showSectionError(document.getElementById('activeDayTimeline'), 'All time slots in Day ' + d.day + ' must have a non-empty time and activity description.');
                    hasEmptySlot = true;
                    isValid = false;
                    break;
                }
            }
            if (hasEmptySlot) break;
        }
    } else if (step === 5) {
        // Step 5: Pricing & Cost Breakdown
        const marginEl = document.getElementById('guide_margin');
        const marginVal = (marginEl?.value || '').trim();
        const marginNum = parseFloat(marginVal);
        if (marginVal === '' || isNaN(marginNum) || marginNum < 0) {
            showFieldError(marginEl, 'Please enter a valid guide fee / margin (₱0 or greater).');
            isValid = false;
        }

        const priceEl = document.getElementById('final_price_per_pax');
        const priceNum = parseFloat(priceEl?.value || '0');
        if (priceNum <= 0) {
            const totalWrap = document.getElementById('sim_final_total')?.parentElement;
            showSectionError(totalWrap, 'Calculated total package price must be greater than ₱0.00.');
            isValid = false;
        }
    }

    return isValid;
}

function getCurrentStep() {
    for (let i = 1; i <= 5; i++) {
        const sec = document.getElementById('stepSection' + i);
        if (sec && sec.style.display !== 'none') return i;
    }
    return 1;
}

function goToStep(step) {
    saveCurrentDayData();
    const currentStep = getCurrentStep();

    // Prevent proceeding forward if current step has validation errors
    if (step > currentStep) {
        for (let s = currentStep; s < step; s++) {
            if (!validateStep(s)) {
                const firstError = document.querySelector('#stepSection' + s + ' .is-invalid, #stepSection' + s + ' .pkg-field-error');
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    if (typeof firstError.focus === 'function') firstError.focus();
                }
                return false;
            }
        }
    }

    for (let i = 1; i <= 5; i++) {
        const sec = document.getElementById('stepSection' + i);
        const tab = document.getElementById('stepTab' + i);
        if (sec) sec.style.display = (i === step) ? 'block' : 'none';
        if (tab) {
            if (i === step) {
                tab.classList.add('active');
            } else {
                tab.classList.remove('active');
            }
        }
    }
    if (step === 3) {
        renderStep3Details();
    } else if (step === 4) {
        updateItineraryDays();
    } else if (step === 5) {
        calculateFinancials();
    }
    window.scrollTo({ top: 120, behavior: 'smooth' });
    return true;
}

function handleFormSubmit(e) {
    for (let s = 1; s <= 5; s++) {
        if (!validateStep(s)) {
            if (e && e.preventDefault) e.preventDefault();
            for (let i = 1; i <= 5; i++) {
                const sec = document.getElementById('stepSection' + i);
                const tab = document.getElementById('stepTab' + i);
                if (sec) sec.style.display = (i === s) ? 'block' : 'none';
                if (tab) {
                    if (i === s) tab.classList.add('active');
                    else tab.classList.remove('active');
                }
            }
            const firstError = document.querySelector('#stepSection' + s + ' .is-invalid, #stepSection' + s + ' .pkg-field-error');
            if (firstError) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                if (typeof firstError.focus === 'function') firstError.focus();
            }
            return false;
        }
    }
    return true;
}

function confirmDiscardPackage(e) {
    if (!confirm('Are you sure you want to cancel? Any unsaved package information will be discarded.')) {
        if (e && e.preventDefault) e.preventDefault();
        return false;
    }
    return true;
}

function handleStep1Back() {
    if (history.length > 1) {
        history.back();
    } else {
        window.location.href = '<?= e(url('/dashboard/listings')) ?>';
    }
}

function filterSelectionCategory(category, btn) {
    const filterBtns = document.querySelectorAll('.pkg-category-filter-btn');
    filterBtns.forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');

    const blocks = document.querySelectorAll('.pkg-cat-block');
    blocks.forEach(block => {
        if (category === 'all' || block.dataset.category === category) {
            block.style.display = 'block';
        } else {
            block.style.display = 'none';
        }
    });
}

function updateDescCount() {
    const desc = document.getElementById('pkg_description')?.value || '';
    const descCountEl = document.getElementById('pkg_desc_count');
    if (descCountEl) descCountEl.innerText = desc.length + ' characters';
}

function toggleActivityCard(id, title, rate, area, cover, category, summary) {
    const existingIndex = selectedActivities.findIndex(a => a.id === id);
    const cardEl = document.getElementById('card_act_' + id);

    if (existingIndex !== -1) {
        selectedActivities.splice(existingIndex, 1);
        if (cardEl) {
            cardEl.classList.remove('is-selected');
            const icon = cardEl.querySelector('.pkg-check-icon');
            const text = cardEl.querySelector('.pkg-check-text');
            if (icon) icon.innerText = '+';
            if (text) text.innerText = 'Select';
        }
    } else {
        selectedActivities.push({
            id: id,
            title: title,
            rate: rate,
            area: area,
            cover: cover,
            category: category,
            summary: summary || ''
        });
        if (cardEl) {
            cardEl.classList.add('is-selected');
            const icon = cardEl.querySelector('.pkg-check-icon');
            const text = cardEl.querySelector('.pkg-check-text');
            if (icon) icon.innerText = '✓';
            if (text) text.innerText = 'Included';
        }
    }

    if (selectedActivities.length > 0) {
        const thingsBlock = document.querySelector('.pkg-cat-block[data-category="things-to-do"]');
        if (thingsBlock) {
            const err = thingsBlock.querySelector('.pkg-field-error');
            if (err) err.remove();
        }
    }

    updateStep2Counters();
    calculateFinancials();
}

function removeActivityById(id) {
    const cardEl = document.getElementById('card_act_' + id);
    selectedActivities = selectedActivities.filter(a => a.id !== id);
    if (cardEl) {
        cardEl.classList.remove('is-selected');
        const icon = cardEl.querySelector('.pkg-check-icon');
        const text = cardEl.querySelector('.pkg-check-text');
        if (icon) icon.innerText = '+';
        if (text) text.innerText = 'Select';
    }
    updateStep2Counters();
    renderStep3Details();
    calculateFinancials();
}

function updateStep2Counters() {
    const badge = document.getElementById('step2CounterBadge');
    if (badge) {
        const hasHotel = (selectedHotelName && selectedHotelName !== 'None');
        const hasVehicle = (selectedVehicleModel && selectedVehicleModel !== 'None');
        const totalItems = selectedActivities.length + (hasHotel ? 1 : 0) + (hasVehicle ? 1 : 0);
        badge.innerText = `${totalItems} Selected (${selectedActivities.length} Activities)`;
    }
}

function formatTime12h(timeStr, defaultTime) {
    if (!timeStr || timeStr === 'N/A') return defaultTime || '';
    if (/am|pm/i.test(timeStr)) return timeStr;
    const parts = timeStr.split(':');
    let hours = parseInt(parts[0], 10);
    const minutes = parts[1] || '00';
    if (isNaN(hours)) return timeStr;
    const ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12;
    hours = hours ? hours : 12;
    const strHours = hours < 10 ? '0' + hours : '' + hours;
    return `${strHours}:${minutes.substring(0, 2)} ${ampm}`;
}

function selectHotelRoom(radio) {
    document.querySelectorAll('[id^="hotelCard_"]').forEach(el => el.classList.remove('selected'));
    const parentCard = radio.closest('.pkg-card-radio');
    if (parentCard) parentCard.classList.add('selected');

    const hotelBlock = document.querySelector('.pkg-cat-block[data-category="hotels"]');
    if (hotelBlock) {
        const err = hotelBlock.querySelector('.pkg-field-error');
        if (err) err.remove();
    }

    selectedHotelRate = parseFloat(radio.dataset.rate) || 0;
    const hName = radio.dataset.hotelName || '';
    const rName = radio.dataset.roomName || '';
    selectedHotelName = (hName === 'none' || !hName) ? 'None' : hName;
    selectedHotelRoom = rName;
    selectedHotelCheckin = radio.dataset.checkin || '14:00:00';
    selectedHotelCheckout = radio.dataset.checkout || '12:00:00';

    try {
        selectedHotelAmenities = JSON.parse(radio.dataset.amenities || '[]');
    } catch (e) {
        selectedHotelAmenities = [];
    }

    const hiddenNameInput = document.getElementById('selected_hotel_name');
    if (hiddenNameInput) {
        hiddenNameInput.value = (hName === 'none' || !hName) ? 'None' : (hName + (rName ? ' (' + rName + ')' : ''));
    }

    updateStep2Counters();
    renderStep3Details();
    calculateFinancials();
}

function selectVehicle(radio) {
    document.querySelectorAll('[id^="vehicleCard_"]').forEach(el => el.classList.remove('selected'));
    const parentCard = radio.closest('.pkg-card-radio');
    if (parentCard) parentCard.classList.add('selected');

    const vehicleBlock = document.querySelector('.pkg-cat-block[data-category="vehicles"]');
    if (vehicleBlock) {
        const err = vehicleBlock.querySelector('.pkg-field-error');
        if (err) err.remove();
    }

    selectedVehicleDailyRate = parseFloat(radio.dataset.dailyRate) || 0;
    const vModel = radio.dataset.vehicleModel || '';
    selectedVehicleModel = (vModel === 'none' || !vModel) ? 'None' : vModel;
    selectedVehicleType = radio.dataset.vehicleType || '';
    selectedVehicleLocation = radio.dataset.location || '';
    selectedVehicleHandover = radio.dataset.handoverTime || '13:00:00';
    selectedVehicleReturn = radio.dataset.returnTime || '12:00:00';

    try {
        selectedVehicleSpecs = JSON.parse(radio.dataset.specs || '[]');
    } catch (e) {
        selectedVehicleSpecs = [];
    }

    const hiddenNameInput = document.getElementById('selected_vehicle_name');
    if (hiddenNameInput) {
        hiddenNameInput.value = (vModel === 'none' || !vModel) ? 'None' : vModel;
    }

    updateStep2Counters();
    renderStep3Details();
    calculateFinancials();
}

// Render Step 3 Selected Package Details
function renderStep3Details() {
    const list = document.getElementById('step3ActivitiesList');
    const alertEl = document.getElementById('step3NoActivitiesAlert');
    const countBadge = document.getElementById('selectedActivitiesDetailsCount');

    if (countBadge) {
        countBadge.innerText = selectedActivities.length + ' Bundled';
    }

    if (list) {
        if (selectedActivities.length === 0) {
            list.innerHTML = '';
            if (alertEl) alertEl.style.display = 'block';
        } else {
            if (alertEl) alertEl.style.display = 'none';
            list.innerHTML = selectedActivities.map(act => {
                const isPink = act.category.toLowerCase().includes('attraction');
                return `
                    <div class="pkg-destination-card is-selected" style="margin:0;">
                        <input type="hidden" name="activities[]" value="${escapeHtml(act.title)}">
                        <input type="hidden" name="selected_activity_ids[]" value="${act.id}">
                        <div class="pkg-card-img-wrap" style="height:140px;">
                            <img src="${resolveCardImg(act.cover)}" alt="${escapeHtml(act.title)}" class="pkg-card-img" onerror="this.src='https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?w=800&q=80'">
                            <span class="pkg-category-badge ${isPink ? 'badge-pink' : 'badge-purple'}">${escapeHtml(act.category)}</span>
                            <button type="button" onclick="removeActivityById(${act.id})" style="position:absolute;top:0.6rem;right:0.6rem;background:rgba(225,29,72,0.95);color:#ffffff;border:none;border-radius:6px;padding:3px 8px;font-size:0.75rem;font-weight:700;cursor:pointer;">
                                ✕ Remove
                            </button>
                        </div>
                        <div class="pkg-card-body" style="padding:0.75rem;">
                            <div class="pkg-card-location"><span>📍</span> ${escapeHtml(act.area)}</div>
                            <h4 class="pkg-card-title" style="font-size:0.9rem;margin-bottom:0.4rem;">${escapeHtml(act.title)}</h4>
                            <div style="font-size:0.85rem;color:#059669;font-weight:700;">
                                ₱${Number(act.rate).toLocaleString('en-US', {minimumFractionDigits:2})} <small style="color:#64748b;font-weight:normal;">/ person</small>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }
    }

    // Update Hotel Card in Step 3 dynamically from selected hotel
    const hNameEl = document.getElementById('step3HotelName');
    const hSpecsEl = document.getElementById('step3HotelSpecs');
    const hAmenitiesEl = document.getElementById('step3HotelAmenities');
    const hCheckInEl = document.getElementById('step3HotelCheckIn');
    const hCheckOutEl = document.getElementById('step3HotelCheckOut');
    const nights = parseInt(document.getElementById('pkg_nights')?.value) || 2;
    const checkoutDay = nights > 0 ? (nights + 1) : 1;

    if (selectedHotelName === 'None' || !selectedHotelName) {
        if (hNameEl) hNameEl.innerText = (selectedHotelName === 'None' || !selectedHotelName) ? 'No Hotel Selected' : selectedHotelName;
        if (hSpecsEl) hSpecsEl.innerText = (selectedHotelName === 'None' || !selectedHotelName) ? 'Optional · Guest arranges own lodging or day tour' : `${selectedHotelRoom} • ${nights} Nights`;
        if (hAmenitiesEl) {
            hAmenitiesEl.innerHTML = '<span class="hint" style="color:#64748b;font-size:0.85rem;">No hotel partner bundled for this package.</span>';
        }
        if (hCheckInEl) hCheckInEl.innerHTML = '<strong>Check-in:</strong> N/A (No Hotel Stay)';
        if (hCheckOutEl) hCheckOutEl.innerHTML = '<strong>Check-out:</strong> N/A (No Hotel Stay)';
    } else {
        if (hNameEl) hNameEl.innerText = (selectedHotelName === 'None' || !selectedHotelName) ? 'No Hotel Selected' : selectedHotelName;
        if (hSpecsEl) hSpecsEl.innerText = `${selectedHotelRoom || 'Standard Room'} • ${nights} Night${nights !== 1 ? 's' : ''}`;
        if (hAmenitiesEl) {
            if (Array.isArray(selectedHotelAmenities) && selectedHotelAmenities.length > 0) {
                hAmenitiesEl.innerHTML = selectedHotelAmenities.map(am =>
                    `<span class="pkg-amenity-badge"><span style="color:#d97706;font-weight:bold;">✓</span> ${escapeHtml(am)}</span>`
                ).join('');
            } else {
                hAmenitiesEl.innerHTML = '<span class="hint" style="color:#64748b;font-size:0.85rem;">Standard amenities included with selected accommodation.</span>';
            }
        }
        const checkinFormatted = formatTime12h(selectedHotelCheckin, '02:00 PM');
        const checkoutFormatted = formatTime12h(selectedHotelCheckout, '12:00 PM');
        if (hCheckInEl) hCheckInEl.innerHTML = `<strong>Check-in:</strong> Day 1 · ${escapeHtml(checkinFormatted)} Onwards`;
        if (hCheckOutEl) hCheckOutEl.innerHTML = `<strong>Check-out:</strong> Day ${checkoutDay} · ${escapeHtml(checkoutFormatted)} Midday`;
    }

    // Update Rental Vehicle Card in Step 3 dynamically from selected vehicle
    const vModelEl = document.getElementById('step3VehicleModel');
    const vSpecsEl = document.getElementById('step3VehicleSpecs');
    const vFeaturesEl = document.getElementById('step3VehicleFeatures');
    const vHandoverEl = document.getElementById('step3VehicleHandover');
    const vReturnEl = document.getElementById('step3VehicleReturn');
    const days = parseInt(document.getElementById('pkg_days')?.value) || 3;

    if (selectedVehicleModel === 'None' || !selectedVehicleModel) {
        if (vModelEl) vModelEl.innerText = (selectedVehicleModel === 'None' || !selectedVehicleModel) ? 'No Rental Vehicle Selected' : selectedVehicleModel;
        if (vSpecsEl) vSpecsEl.innerText = (selectedVehicleModel === 'None' || !selectedVehicleModel) ? 'Optional · Tourists commute or walking tour' : `Automatic Transport • ${days} Full Days`;
        if (vFeaturesEl) {
            vFeaturesEl.innerHTML = '<span class="hint" style="color:#64748b;font-size:0.85rem;">No rental vehicle bundled for this package.</span>';
        }
        if (vHandoverEl) vHandoverEl.innerHTML = '<strong>Handover:</strong> N/A (Commute / Walking)';
        if (vReturnEl) vReturnEl.innerHTML = '<strong>Return:</strong> N/A (Commute / Walking)';
    } else {
        if (vModelEl) vModelEl.innerText = selectedVehicleModel;
        const typeLabel = selectedVehicleType ? selectedVehicleType : 'Dedicated Transport';
        const locLabel = selectedVehicleLocation ? ` (${selectedVehicleLocation})` : ' (Unlimited South Cebu mileage)';
        if (vSpecsEl) vSpecsEl.innerText = `${typeLabel} • ${days} Full Day${days !== 1 ? 's' : ''}${locLabel}`;
        if (vFeaturesEl) {
            if (Array.isArray(selectedVehicleSpecs) && selectedVehicleSpecs.length > 0) {
                vFeaturesEl.innerHTML = selectedVehicleSpecs.map(sp =>
                    `<span class="pkg-feature-badge"><span style="color:#2563eb;font-weight:bold;">✓</span> ${escapeHtml(sp)}</span>`
                ).join('');
            } else {
                vFeaturesEl.innerHTML = '<span class="hint" style="color:#64748b;font-size:0.85rem;">Standard rental features and safety equipment included.</span>';
            }
        }
        const handoverFormatted = formatTime12h(selectedVehicleHandover, '01:00 PM');
        const returnFormatted = formatTime12h(selectedVehicleReturn, '12:00 PM');
        if (vHandoverEl) vHandoverEl.innerHTML = `<strong>Handover:</strong> Day 1 · ${escapeHtml(handoverFormatted)}`;
        if (vReturnEl) vReturnEl.innerHTML = `<strong>Return:</strong> Day ${days} · ${escapeHtml(returnFormatted)}`;
    }
}

// Itinerary Day-by-Day Generation (Step 4)
function updateItineraryDays() {
    saveCurrentDayData();
    const rawVal = document.getElementById('pkg_days')?.value;
    const parsedDays = parseInt(rawVal, 10);
    const days = (!isNaN(parsedDays) && parsedDays > 0) ? Math.min(Math.max(1, parsedDays), 14) : (itineraryDaysData.length || 1);
    const tabsContainer = document.getElementById('itineraryDayTabs');
    const countEl = document.getElementById('itineraryDaysCount');

    if (countEl) countEl.innerText = `Duration: ${days} Day${days !== 1 ? 's' : ''}`;

    // Adjust itineraryDaysData array length to match days and preserve/restore cached data
    while (itineraryDaysData.length < days) {
        const nextDayNum = itineraryDaysData.length + 1;
        if (itineraryDaysCache[nextDayNum]) {
            itineraryDaysData.push(JSON.parse(JSON.stringify(itineraryDaysCache[nextDayNum])));
        } else {
            const newDay = {
                day: nextDayNum,
                title: `Day ${nextDayNum}: Exploring South Cebu Destinations`,
                desc: `Curated excursions and guided activities for Day ${nextDayNum}.`,
                schedule: [
                    { time: "09:00 AM", activity: `Morning excursion departure` },
                    { time: "01:30 PM", activity: `Afternoon activity session and lunch` }
                ],
                highlights: [
                    `Guided activity experience`,
                    `Scenic photo opportunities`,
                    `Local Cebuano culinary stops`
                ]
            };
            itineraryDaysCache[nextDayNum] = JSON.parse(JSON.stringify(newDay));
            itineraryDaysData.push(newDay);
        }
    }

    if (itineraryDaysData.length > days) {
        for (let i = days; i < itineraryDaysData.length; i++) {
            itineraryDaysCache[itineraryDaysData[i].day] = JSON.parse(JSON.stringify(itineraryDaysData[i]));
        }
        itineraryDaysData = itineraryDaysData.slice(0, days);
    }

    if (currentActiveDayIndex >= days) {
        currentActiveDayIndex = days - 1;
    }
    if (currentActiveDayIndex < 0) {
        currentActiveDayIndex = 0;
    }

    if (tabsContainer) {
        tabsContainer.innerHTML = itineraryDaysData.map((d, idx) => `
            <button type="button" class="pkg-day-tab-btn ${idx === currentActiveDayIndex ? 'active' : ''}" onclick="switchItineraryDay(${idx})">
                Day ${d.day}
            </button>
        `).join('');
    }

    renderActiveDayContent();
    syncItineraryToHidden();
}

function switchItineraryDay(index) {
    saveCurrentDayData();
    currentActiveDayIndex = index;
    const buttons = document.querySelectorAll('.pkg-day-tab-btn');
    buttons.forEach((btn, idx) => {
        if (idx === index) btn.classList.add('active');
        else btn.classList.remove('active');
    });
    renderActiveDayContent();
}

function renderActiveDayContent() {
    const dayData = itineraryDaysData[currentActiveDayIndex];
    if (!dayData) return;

    const titleIn = document.getElementById('activeDayTitle');
    const descIn = document.getElementById('activeDayDesc');
    const timelineEl = document.getElementById('activeDayTimeline');
    const highlightsEl = document.getElementById('activeDayHighlights');

    if (titleIn) titleIn.value = dayData.title;
    if (descIn) descIn.value = dayData.desc;

    // Render Timeline Items (clean white card on light background)
    if (timelineEl) {
        timelineEl.innerHTML = dayData.schedule.map((item, idx) => `
            <div style="display:flex;align-items:center;gap:0.75rem;background:#ffffff;padding:0.6rem 0.85rem;border-radius:8px;border:1px solid #e2e8f0;">
                <span style="width:10px;height:10px;border-radius:50%;background:#10b981;flex-shrink:0;"></span>
                <input class="input pkg-input" type="text" value="${escapeHtml(item.time)}" oninput="updateTimelineItem(${idx}, 'time', this.value)" style="width:95px;height:34px;font-size:0.8rem;padding:3px 8px;background:#ffffff !important;color:#047857 !important;border:1px solid #cbd5e1 !important;font-weight:700;border-radius:6px;">
                <input class="input pkg-input" type="text" value="${escapeHtml(item.activity)}" oninput="updateTimelineItem(${idx}, 'activity', this.value)" style="flex:1;height:34px;font-size:0.85rem;padding:3px 10px;background:#ffffff !important;color:#0f172a !important;border:1px solid #cbd5e1 !important;border-radius:6px;">
                <button type="button" onclick="removeTimelineSlot(${idx})" style="background:transparent;border:none;color:#ef4444;font-size:16px;cursor:pointer;padding:2px 6px;" title="Remove time slot">✕</button>
            </div>
        `).join('');
    }

    // Render Highlights (clean white card on light background)
    if (highlightsEl) {
        highlightsEl.innerHTML = dayData.highlights.map((hl, idx) => `
            <div style="display:flex;align-items:center;gap:0.5rem;background:#ffffff;padding:0.5rem 0.75rem;border-radius:8px;border:1px solid #e2e8f0;">
                <span style="color:#059669;font-weight:bold;font-size:0.95rem;">✓</span>
                <input class="input pkg-input" type="text" value="${escapeHtml(hl)}" oninput="updateDayHighlightItem(${idx}, this.value)" style="flex:1;height:34px;font-size:0.85rem;padding:3px 10px;background:#ffffff !important;color:#0f172a !important;border:1px solid #cbd5e1 !important;border-radius:6px;">
                <button type="button" onclick="removeDayHighlight(${idx})" style="background:transparent;border:none;color:#ef4444;font-size:16px;cursor:pointer;padding:2px 6px;" title="Remove highlight">✕</button>
            </div>
        `).join('');
    }
}

function saveCurrentDayData() {
    const dayData = itineraryDaysData[currentActiveDayIndex];
    if (!dayData) return;
    const titleIn = document.getElementById('activeDayTitle');
    const descIn = document.getElementById('activeDayDesc');
    if (titleIn) dayData.title = titleIn.value;
    if (descIn) dayData.desc = descIn.value;
    itineraryDaysCache[dayData.day] = JSON.parse(JSON.stringify(dayData));
    syncItineraryToHidden();
}

function updateTimelineItem(idx, key, val) {
    const dayData = itineraryDaysData[currentActiveDayIndex];
    if (dayData && dayData.schedule[idx]) {
        dayData.schedule[idx][key] = val;
        itineraryDaysCache[dayData.day] = JSON.parse(JSON.stringify(dayData));
        syncItineraryToHidden();
    }
}

function addTimelineSlot() {
    const dayData = itineraryDaysData[currentActiveDayIndex];
    if (dayData) {
        dayData.schedule.push({ time: "02:00 PM", activity: "New activity slot" });
        itineraryDaysCache[dayData.day] = JSON.parse(JSON.stringify(dayData));
        const tlErr = document.querySelector('#activeDayTimeline .pkg-field-error');
        if (tlErr) tlErr.remove();
        renderActiveDayContent();
        syncItineraryToHidden();
    }
}

function removeTimelineSlot(idx) {
    const dayData = itineraryDaysData[currentActiveDayIndex];
    if (dayData) {
        dayData.schedule.splice(idx, 1);
        itineraryDaysCache[dayData.day] = JSON.parse(JSON.stringify(dayData));
        renderActiveDayContent();
        syncItineraryToHidden();
    }
}

function updateDayHighlightItem(idx, val) {
    const dayData = itineraryDaysData[currentActiveDayIndex];
    if (dayData && dayData.highlights) {
        dayData.highlights[idx] = val;
        itineraryDaysCache[dayData.day] = JSON.parse(JSON.stringify(dayData));
        syncItineraryToHidden();
    }
}

function addDayHighlight() {
    const dayData = itineraryDaysData[currentActiveDayIndex];
    if (dayData) {
        dayData.highlights.push("Key experience highlight milestone");
        itineraryDaysCache[dayData.day] = JSON.parse(JSON.stringify(dayData));
        renderActiveDayContent();
        syncItineraryToHidden();
    }
}

function removeDayHighlight(idx) {
    const dayData = itineraryDaysData[currentActiveDayIndex];
    if (dayData) {
        dayData.highlights.splice(idx, 1);
        itineraryDaysCache[dayData.day] = JSON.parse(JSON.stringify(dayData));
        renderActiveDayContent();
        syncItineraryToHidden();
    }
}

function syncItineraryToHidden() {
    const hidden = document.getElementById('pkg_itinerary');
    if (hidden) {
        hidden.value = JSON.stringify(itineraryDaysData);
    }
}

function resolveCardImg(path) {
    if (!path) return 'https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?w=800&q=80';
    if (path.startsWith('http://') || path.startsWith('https://')) return path;
    const base = window.APP_BASE_URL || '';
    return base + '/' + path.replace(/^\/+/, '');
}

function escapeHtml(str) {
    return String(str || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// -------------------------------------------------------------
// Package Photo Upload Handlers
// -------------------------------------------------------------
function handlePkgFileChange(index, input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        if (file.size > 5 * 1024 * 1024) {
            alert('Image must be 5MB or smaller.');
            input.value = '';
            return;
        }
        const reader = new FileReader();
        reader.onload = function(e) {
            setPkgPreview(index, e.target.result);
            const remEl = document.getElementById('pkg_removed_' + index);
            if (remEl) remEl.value = '0';
        };
        reader.readAsDataURL(file);
    }
}

function handlePkgUrlChange(index, url) {
    url = url.trim();
    if (url) {
        setPkgPreview(index, url);
        const remEl = document.getElementById('pkg_removed_' + index);
        if (remEl) remEl.value = '0';
    }
}

function setPkgPreview(index, src) {
    const img = document.getElementById('pkg_preview_img_' + index);
    const ph = document.getElementById('pkg_placeholder_' + index);
    const btn = document.getElementById('pkg_remove_btn_' + index);

    if (img && ph && btn) {
        img.src = src;
        img.style.display = 'block';
        ph.style.display = 'none';
        btn.style.display = 'flex';
    }

    if (index === 0) {
        const wrap = document.getElementById('pkg_preview_wrap_0');
        if (wrap) {
            wrap.classList.remove('is-invalid');
            wrap.style.borderColor = '';
            wrap.style.boxShadow = '';
            const parent = wrap.closest('.field-row') || wrap.parentElement;
            const err = parent ? parent.querySelector('.pkg-field-error') : null;
            if (err) err.remove();
        }
    }
}

function removePkgPhoto(index) {
    const img = document.getElementById('pkg_preview_img_' + index);
    const ph = document.getElementById('pkg_placeholder_' + index);
    const btn = document.getElementById('pkg_remove_btn_' + index);
    const fileIn = document.getElementById('pkg_file_' + index);
    const urlIn = document.getElementById('pkg_url_' + index);
    const remIn = document.getElementById('pkg_removed_' + index);
    const existIn = document.getElementById('pkg_existing_' + index);

    if (img) { img.src = ''; img.style.display = 'none'; }
    if (ph) { ph.style.display = 'block'; }
    if (btn) { btn.style.display = 'none'; }
    if (fileIn) { fileIn.value = ''; }
    if (urlIn) { urlIn.value = ''; }
    if (existIn) { existIn.value = ''; }
    if (remIn) { remIn.value = '1'; }
}

// -------------------------------------------------------------
// Pricing Financial Simulator Calculation (Step 5)
// -------------------------------------------------------------
function calculateFinancials() {
    const days = parseInt(document.getElementById('pkg_days')?.value) || 0;
    const nights = parseInt(document.getElementById('pkg_nights')?.value) || 0;
    const guideMargin = parseFloat(document.getElementById('guide_margin')?.value) || 0;

    // 1. Separate Things to Do & Attractions rates
    let totalThingsToDoRate = 0;
    let totalAttractionsRate = 0;

    selectedActivities.forEach(act => {
        const rate = parseFloat(act.rate) || 0;
        if (act.category.toLowerCase().includes('attraction')) {
            totalAttractionsRate += rate;
        } else {
            totalThingsToDoRate += rate;
        }
    });

    // 2. Hotel Net Rate per pax (Split across 2 pax standard occupancy)
    const hotelTotal = (selectedHotelName !== 'None') ? (selectedHotelRate * nights) : 0;
    const hotelPerPax = hotelTotal / 2;

    // 3. Rental Net Rate per pax (1 Scooter per 2 pax)
    const vehicleTotal = (selectedVehicleModel !== 'None') ? (selectedVehicleDailyRate * days) : 0;
    const vehiclePerPax = vehicleTotal / 2;

    // 4. Subtotal & Platform Fee
    const subtotal = totalThingsToDoRate + totalAttractionsRate + hotelPerPax + vehiclePerPax + guideMargin;
    const platformFee = Math.round(subtotal * 0.08);
    const calculatedTotal = Math.round(subtotal + platformFee);

    // Update Simulator Labels
    const actEl = document.getElementById('sim_activity_fee');
    const attEl = document.getElementById('sim_attraction_fee');
    const hotEl = document.getElementById('sim_hotel_fee');
    const renEl = document.getElementById('sim_rental_fee');
    const marEl = document.getElementById('sim_guide_margin');
    const plaEl = document.getElementById('sim_platform_fee');
    const totEl = document.getElementById('sim_final_total');
    const marVal = document.getElementById('guide_margin_val');
    const totalPkgInput = document.getElementById('total_package_price');

    if (actEl) actEl.innerText = '₱' + totalThingsToDoRate.toLocaleString('en-US', { minimumFractionDigits: 2 });
    if (attEl) attEl.innerText = '₱' + totalAttractionsRate.toLocaleString('en-US', { minimumFractionDigits: 2 });
    if (hotEl) hotEl.innerText = '₱' + hotelPerPax.toLocaleString('en-US', { minimumFractionDigits: 2 });
    if (renEl) renEl.innerText = '₱' + vehiclePerPax.toLocaleString('en-US', { minimumFractionDigits: 2 });
    if (marEl) marEl.innerText = '₱' + guideMargin.toLocaleString('en-US', { minimumFractionDigits: 2 });
    if (plaEl) plaEl.innerText = '₱' + platformFee.toLocaleString('en-US', { minimumFractionDigits: 2 });
    if (marVal) marVal.innerText = '₱' + guideMargin.toLocaleString('en-US');

    // Update hidden inputs for server POST
    const cAct = document.getElementById('calc_activity_fee');
    const cAtt = document.getElementById('calc_attraction_fee');
    const cHot = document.getElementById('calc_hotel_fee');
    const cRen = document.getElementById('calc_rental_fee');
    const cPla = document.getElementById('calc_platform_fee');
    const cFin = document.getElementById('final_price_per_pax');

    if (cAct) cAct.value = totalThingsToDoRate;
    if (cAtt) cAtt.value = totalAttractionsRate;
    if (cHot) cHot.value = hotelPerPax;
    if (cRen) cRen.value = vehiclePerPax;
    if (cPla) cPla.value = platformFee;

    // Only update total input if not in edit mode or if totalPkgInput is 0/default
    if (!<?= $isEdit ? 'true' : 'false' ?> || !totalPkgInput || parseFloat(totalPkgInput.value) <= 0) {
        if (totalPkgInput) totalPkgInput.value = calculatedTotal;
        if (totEl) totEl.innerText = '₱' + calculatedTotal.toLocaleString('en-US', { minimumFractionDigits: 2 });
        if (cFin) cFin.value = calculatedTotal;
    } else {
        if (totEl && totalPkgInput) totEl.innerText = '₱' + Number(totalPkgInput.value).toLocaleString('en-US', { minimumFractionDigits: 2 });
        if (cFin && totalPkgInput) cFin.value = totalPkgInput.value;
    }
}

// Initial render on page load
document.addEventListener('DOMContentLoaded', () => {
    updateDescCount();
    updateStep2Counters();
    renderStep3Details();
    updateItineraryDays();
    calculateFinancials();
});
</script>