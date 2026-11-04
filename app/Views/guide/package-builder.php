<?php
/**
 * GuideMate - Modern Travel Package Builder Wizard (White Background Theme)
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
$defaultTitle = $package['title'] ?? '3D2N South Cebu Expedition: Canyoneering, Moalboal Reef & NMAX Scooter';
$defaultSummary = $package['summary'] ?? 'All-inclusive 3-day South Cebu escape with Kawasan canyoneering, sea turtles, resort stay & NMAX scooter.';
$defaultArea = $package['area'] ?? 'Moalboal & Badian, South Cebu';
$defaultDeparture = $package['address'] ?? 'Panagsama Beach, Moalboal, Cebu';
$defaultDays = (int) ($durationDays ?? 3);
$defaultNights = (int) ($durationNights ?? 2);
$defaultMinPax = 2;
$defaultMaxPax = (int) ($package['capacity'] ?? 8);
$defaultPrice = (float) ($package['price'] ?? 8499.00);

$defaultTags = !empty($parsedTags) ? $parsedTags : "Adventure & Nature\nWater Activities\nFamily-Friendly\nIsland Hopping";
$defaultDesc = !empty($parsedDesc) ? $parsedDesc : "Immerse yourself in the thrill of South Cebu. This comprehensive 3-day travel bundle combines the world-famous Kawasan Falls Canyoneering, the hypnotic Moalboal Sardine Run and sea turtle sanctuary, 2 nights at a beachfront resort in Moalboal, and a dedicated 155cc Yamaha NMAX scooter for ultimate freedom across coastal vistas. Every permit, entrance, safety gear, and licensed guide assistance is completely covered.";
$defaultItinerary = !empty($parsedItinerary) ? $parsedItinerary : "Day 1: Coastal Arrival, Resort Check-in & Moalboal Turtle Snorkeling (03:00 PM)\nDay 2: Full Kawasan Canyoneering & 7 Cliff Jumps (08:30 AM) followed by recovery lunch\nDay 3: Pescador Island Coral Reef Cruise & Scooter Return at Midday";
$defaultPolicies = !empty($parsedPolicies) ? $parsedPolicies : "Free cancellation up to 48 hours prior to trip departure (50% refund within 24-48 hours).\nRequirements:\n• Valid Government ID or Passport for all participants\n• Valid Motorcycle/Driver's license for scooter rider\n• Moderate physical fitness for canyoneering (life vests provided)";

$formAction = $isEdit ? url('/dashboard/packages/' . (int) $package['id']) : url('/dashboard/packages');
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
                        <span style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:12px;background:#ede9fe;color:#7c3aed;font-size:1.2rem;">📦</span>
                        <?= $isEdit ? 'Edit Travel Package' : 'Travel Package Builder' ?>
                    </h1>
                    <p class="hint mb-0" style="font-size:0.95rem;color:#475569;">
                        Design an all-inclusive travel bundle: combine your activities with partner hotel rooms and vehicle rentals under one single price.
                    </p>
                </div>
            </div>

            <!-- Stepper Progress Header (White Background) -->
            <div class="panel pkg-wizard-card" style="margin-bottom:1.5rem;padding:0.65rem 0.85rem;background:#ffffff;border:1px solid #e2e8f0;border-radius:14px;box-shadow:0 1px 4px rgba(0,0,0,0.04);">
                <div style="display:grid;grid-template-columns:repeat(5, 1fr);gap:0.5rem;align-items:center;">
                    <div id="stepTab1" class="pkg-step-pill active" onclick="goToStep(1)">
                        <span class="pkg-step-num">1</span>
                        <div class="pkg-step-label"><strong>Framework</strong><small>Basics & Dates</small></div>
                    </div>
                    <div id="stepTab2" class="pkg-step-pill" onclick="goToStep(2)">
                        <span class="pkg-step-num">2</span>
                        <div class="pkg-step-label"><strong>Activities</strong><small>Tours & Sights</small></div>
                    </div>
                    <div id="stepTab3" class="pkg-step-pill" onclick="goToStep(3)">
                        <span class="pkg-step-num">3</span>
                        <div class="pkg-step-label"><strong>Hotel Stay</strong><small>Partner Rooms</small></div>
                    </div>
                    <div id="stepTab4" class="pkg-step-pill" onclick="goToStep(4)">
                        <span class="pkg-step-num">4</span>
                        <div class="pkg-step-label"><strong>Transport</strong><small>Scooters / Vans</small></div>
                    </div>
                    <div id="stepTab5" class="pkg-step-pill" onclick="goToStep(5)">
                        <span class="pkg-step-num">5</span>
                        <div class="pkg-step-label"><strong>Pricing</strong><small>Photos & Launch</small></div>
                    </div>
                </div>
            </div>

            <!-- Form Container -->
            <form id="packageBuilderForm" method="post" action="<?= e($formAction) ?>" enctype="multipart/form-data">
                <?= csrf_field() ?>

                <!-- ======================================================== -->
                <!-- STEP 1: ITINERARY FRAMEWORK & BASIC DETAILS (White Bg)   -->
                <!-- ======================================================== -->
                <div id="stepSection1" class="panel pkg-step-content" style="padding:1.75rem;margin-bottom:1.5rem;border-radius:16px;background:#ffffff;border:1px solid #e2e8f0;box-shadow:0 1px 4px rgba(0,0,0,0.04);">
                    <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:1.25rem;border-bottom:1px solid #f1f5f9;padding-bottom:0.85rem;">
                        <span style="font-size:1.5rem;">🗺️</span>
                        <div>
                            <h3 style="margin:0;font-size:1.2rem;font-weight:800;color:#0f172a;">Step 1: Itinerary Framework & Basic Details</h3>
                            <p class="hint mb-0" style="font-size:0.85rem;color:#64748b;">Name your package, define duration, participant capacity, and departure area.</p>
                        </div>
                    </div>

                    <div class="field-row">
                        <label for="pkg_title" style="font-weight:700;color:#0f172a;">Package Title <span class="req" style="color:#f43f5e;">*</span></label>
                        <input class="input pkg-input" id="pkg_title" name="title" required
                               placeholder="e.g. 3D2N South Cebu Expedition: Canyoneering, Moalboal Reef & NMAX Scooter"
                               value="<?= e($defaultTitle) ?>"
                               oninput="updateSimulatorTitle()">
                        <span class="hint" style="font-size:0.8rem;color:#64748b;display:block;margin-top:0.25rem;">Include duration (e.g. 3D2N) and key destinations or vehicle inclusions for maximum booking interest.</span>
                    </div>

                    <div class="field-row" style="margin-top:1rem;">
                        <label for="pkg_summary" style="font-weight:700;color:#0f172a;">Package Tagline / Hook</label>
                        <input class="input pkg-input" id="pkg_summary" name="summary"
                               placeholder="e.g. Immerse yourself in the thrill of South Cebu with Kawasan Falls, sardine run & resort stay."
                               value="<?= e($defaultSummary) ?>">
                        <span class="hint" style="font-size:0.8rem;color:#64748b;display:block;margin-top:0.25rem;">A single catchy sentence displayed prominently on search result cards and package headers.</span>
                    </div>

                    <div class="form-grid-2" style="margin-top:1rem;">
                        <div class="field-row">
                            <label for="pkg_tags" style="font-weight:700;color:#0f172a;">Experience Tags (One per line)</label>
                            <textarea class="input pkg-input" id="pkg_tags" name="tags" rows="5"
                                      placeholder="Adventure&#10;Nature&#10;Cultural Experience&#10;Family-Friendly&#10;Relaxation"
                                      style="resize:vertical;font-family:inherit;font-size:0.875rem;line-height:1.5;"><?= e($defaultTags) ?></textarea>
                            <span class="hint" style="font-size:0.8rem;color:#64748b;display:block;margin-top:0.25rem;">
                                Enter one experience tag per line. These tags highlight key features across search and booking category filters.
                            </span>
                        </div>

                        <div class="field-row">
                            <label for="pkg_area" style="font-weight:700;color:#0f172a;">Base Area / Primary Destination <span class="req" style="color:#f43f5e;">*</span></label>
                            <input class="input pkg-input" id="pkg_area" name="area" required
                                   placeholder="e.g. Moalboal & Badian, South Cebu"
                                   value="<?= e($defaultArea) ?>"
                                   oninput="updateSimulatorArea()">
                            <div style="margin-top:0.75rem;">
                                <label for="pkg_departure" style="font-weight:700;color:#0f172a;">Departure & Jump-off Point</label>
                                <input class="input pkg-input" id="pkg_departure" name="base_departure"
                                       placeholder="e.g. Panagsama Beach, Moalboal or Mactan Airport"
                                       value="<?= e($defaultDeparture) ?>">
                            </div>
                        </div>
                    </div>

                    <div class="form-grid-2" style="margin-top:1.25rem;background:#f8fafc;padding:1.25rem;border-radius:14px;border:1px solid #e2e8f0;">
                        <div>
                            <label style="font-weight:700;display:block;margin-bottom:0.4rem;color:#0f172a;">Trip Duration (Days & Nights)</label>
                            <div style="display:flex;align-items:center;gap:0.75rem;">
                                <div style="flex:1;">
                                    <small class="hint" style="display:block;font-weight:600;color:#64748b;margin-bottom:3px;">Days</small>
                                    <input class="input pkg-input" type="number" id="pkg_days" name="duration_days" min="1" max="14" value="<?= $defaultDays ?>" required onchange="calculateFinancials()">
                                </div>
                                <div style="flex:1;">
                                    <small class="hint" style="display:block;font-weight:600;color:#64748b;margin-bottom:3px;">Nights</small>
                                    <input class="input pkg-input" type="number" id="pkg_nights" name="duration_nights" min="0" max="14" value="<?= $defaultNights ?>" required onchange="calculateFinancials()">
                                </div>
                            </div>
                        </div>

                        <div>
                            <label style="font-weight:700;display:block;margin-bottom:0.4rem;color:#0f172a;">Participant Capacity (Pax)</label>
                            <div style="display:flex;align-items:center;gap:0.75rem;">
                                <div style="flex:1;">
                                    <small class="hint" style="display:block;font-weight:600;color:#64748b;margin-bottom:3px;">Min Pax</small>
                                    <input class="input pkg-input" type="number" id="pkg_min_pax" name="min_pax" min="1" max="20" value="<?= $defaultMinPax ?>" required onchange="calculateFinancials()">
                                </div>
                                <div style="flex:1;">
                                    <small class="hint" style="display:block;font-weight:600;color:#64748b;margin-bottom:3px;">Max Pax</small>
                                    <input class="input pkg-input" type="number" id="pkg_max_pax" name="max_pax" min="1" max="20" value="<?= $defaultMaxPax ?>" required onchange="calculateFinancials()">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bottom Navigation: Cancel beside Next (labeled 'Next') -->
                    <div style="display:flex;justify-content:flex-end;align-items:center;gap:0.75rem;margin-top:1.5rem;border-top:1px solid #f1f5f9;padding-top:1.25rem;">
                        <a href="<?= e(url('/dashboard/listings')) ?>" class="btn" style="border:1px solid #cbd5e1;background:#ffffff;color:#475569;border-radius:999px;padding:0.65rem 1.6rem;font-weight:600;text-decoration:none;">Cancel</a>
                        <button type="button" onclick="goToStep(2)" style="background:#10b981;border:none;border-radius:999px;color:#ffffff;padding:0.65rem 1.75rem;font-weight:700;display:inline-flex;align-items:center;gap:0.4rem;cursor:pointer;box-shadow:0 2px 6px rgba(16,185,129,0.25);">
                            Next <span>→</span>
                        </button>
                    </div>
                </div>

                <!-- ======================================================== -->
                <!-- STEP 2: SELECTING GUIDE'S OWN ACTIVITIES & ATTRACTIONS   -->
                <!-- ======================================================== -->
                <div id="stepSection2" class="panel pkg-step-content" style="padding:1.75rem;margin-bottom:1.5rem;border-radius:16px;background:#ffffff;border:1px solid #e2e8f0;box-shadow:0 1px 4px rgba(0,0,0,0.04);display:none;">
                    <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:1.25rem;border-bottom:1px solid #f1f5f9;padding-bottom:0.85rem;">
                        <span style="font-size:1.5rem;">🏄</span>
                        <div>
                            <h3 style="margin:0;font-size:1.2rem;font-weight:800;color:#0f172a;">Step 2: Selecting Activities & Attractions</h3>
                            <p class="hint mb-0" style="font-size:0.85rem;color:#64748b;">Pick from your registered tours and approved Cebu destinations. No activities are pre-selected by default.</p>
                        </div>
                    </div>

                    <!-- SECTION A: YOUR APPROVED TOURS & ATTRACTIONS (Card-based grid) -->
                    <div style="margin-bottom:2rem;">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.75rem;">
                            <label style="font-weight:800;font-size:1.05rem;color:#0f172a;margin:0;">Your Approved Tours & Attractions</label>
                            <span class="hint" style="font-size:0.8rem;color:#7c3aed;font-weight:600;">Click any card to add or remove it from this package</span>
                        </div>

                        <div id="availableActivitiesGrid" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));gap:1rem;">
                            <?php foreach ($myActivities as $act):
                                $actId = (int) $act['id'];
                                $actTitle = (string) $act['title'];
                                $actPrice = (float) $act['price'];
                                $actArea = (string) ($act['area'] ?? 'Cebu, Philippines');
                                $actCover = (string) ($act['cover_image'] ?? 'uploads/places/kawasan.jpg');
                                $actCategory = (string) ($act['category_name'] ?? 'Things to Do');
                                $isCatAttraction = str_contains(strtolower($actCategory), 'attraction');
                                // In create mode, NO card is selected by default; in edit mode, match selectedActivities
                                $isSelected = in_array($actId, array_column($selectedActivities, 'id'), true);
                            ?>
                                <div class="pkg-destination-card <?= $isSelected ? 'is-selected' : '' ?>"
                                     id="card_act_<?= $actId ?>"
                                     onclick="toggleActivityCard(<?= $actId ?>, '<?= e(addslashes($actTitle)) ?>', <?= $actPrice ?>, '<?= e(addslashes($actArea)) ?>', '<?= e(addslashes($actCover)) ?>', '<?= e(addslashes($actCategory)) ?>')">
                                    <div class="pkg-card-img-wrap">
                                        <img src="<?= e(img_src($actCover, 'listing-' . $actId)) ?>" alt="<?= e($actTitle) ?>" class="pkg-card-img" onerror="this.src='https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?w=800&q=80'">
                                        <span class="pkg-category-badge <?= $isCatAttraction ? 'badge-pink' : 'badge-purple' ?>">
                                            <?= e($actCategory) ?>
                                        </span>
                                        <div class="pkg-card-select-pill">
                                            <span class="pkg-check-icon"><?= $isSelected ? '✓' : '+' ?></span>
                                            <span class="pkg-check-text"><?= $isSelected ? 'Included' : 'Select' ?></span>
                                        </div>
                                    </div>
                                    <div class="pkg-card-body">
                                        <div class="pkg-card-location">
                                            <span>📍</span> <?= e($actArea) ?>
                                        </div>
                                        <h4 class="pkg-card-title"><?= e($actTitle) ?></h4>
                                        <div class="pkg-card-footer">
                                            <span class="pkg-card-price"><?= money($actPrice) ?> <small>/ person</small></span>
                                            <span class="pkg-card-duration">⏱ <?= e($act['duration'] ?? 'Half day') ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- SECTION B: INCLUDED ACTIVITIES FOR THIS PACKAGE -->
                    <div style="background:#f8fafc;padding:1.5rem;border-radius:14px;border:1px solid #e2e8f0;margin-bottom:1.5rem;">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;border-bottom:1px solid #e2e8f0;padding-bottom:0.75rem;">
                            <div>
                                <h4 style="margin:0;font-size:1rem;font-weight:800;color:#0f172a;display:flex;align-items:center;gap:0.5rem;">
                                    <span>🎯</span> Included Activities for This Package
                                </h4>
                                <small style="color:#64748b;font-size:0.8rem;">Review bundled activities, assign each to an itinerary day, and specify departure slots.</small>
                            </div>
                            <span id="selectedActivitiesCount" style="background:#7c3aed;color:#ffffff;padding:2px 10px;border-radius:999px;font-size:0.75rem;font-weight:700;">0 Selected</span>
                        </div>

                        <!-- Dynamic container filled by JavaScript matching card layout -->
                        <div id="includedActivitiesList" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(300px, 1fr));gap:1rem;">
                            <!-- Populated dynamically via renderIncludedActivities() -->
                        </div>

                        <div id="noActivitiesAlert" style="text-align:center;padding:2.5rem 1rem;color:#64748b;">
                            <span style="font-size:2.5rem;display:block;margin-bottom:0.5rem;">🏖️</span>
                            <strong style="color:#0f172a;font-size:1rem;">No activities included yet</strong>
                            <p style="font-size:0.85rem;margin:0.35rem 0 0 0;color:#64748b;">Click on any approved tour card above to bundle it into this travel package.</p>
                        </div>
                    </div>

                    <div style="background:#f5f3ff;padding:0.85rem 1rem;border-radius:10px;margin-bottom:1.25rem;display:flex;align-items:center;gap:0.6rem;border:1px solid #ddd6fe;">
                        <input type="checkbox" id="sync_schedules" name="sync_schedules" value="1" checked style="width:16px;height:16px;cursor:pointer;accent-color:#7c3aed;">
                        <label for="sync_schedules" style="font-size:0.85rem;color:#4338ca;font-weight:600;margin:0;cursor:pointer;">
                            Sync package start dates automatically with my active Tour Guide calendar (listing_schedules).
                        </label>
                    </div>

                    <!-- Bottom Navigation: Cancel beside Next (label simply 'Next') -->
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:1.5rem;border-top:1px solid #f1f5f9;padding-top:1.25rem;">
                        <button type="button" onclick="goToStep(1)" style="border:1px solid #cbd5e1;background:#ffffff;color:#64748b;border-radius:999px;padding:0.65rem 1.4rem;font-weight:600;cursor:pointer;">← Back</button>
                        <div style="display:flex;align-items:center;gap:0.75rem;">
                            <a href="<?= e(url('/dashboard/listings')) ?>" class="btn" style="border:1px solid #cbd5e1;background:#ffffff;color:#475569;border-radius:999px;padding:0.65rem 1.6rem;font-weight:600;text-decoration:none;">Cancel</a>
                            <button type="button" onclick="goToStep(3)" style="background:#10b981;border:none;border-radius:999px;color:#ffffff;padding:0.65rem 1.75rem;font-weight:700;display:inline-flex;align-items:center;gap:0.4rem;cursor:pointer;box-shadow:0 2px 6px rgba(16,185,129,0.25);">
                                Next <span>→</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ======================================================== -->
                <!-- STEP 3: ATTACHING HOTEL ACCOMMODATION (White Bg)         -->
                <!-- ======================================================== -->
                <div id="stepSection3" class="panel pkg-step-content" style="padding:1.75rem;margin-bottom:1.5rem;border-radius:16px;background:#ffffff;border:1px solid #e2e8f0;box-shadow:0 1px 4px rgba(0,0,0,0.04);display:none;">
                    <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:1.25rem;border-bottom:1px solid #f1f5f9;padding-bottom:0.85rem;">
                        <span style="font-size:1.5rem;">🏨</span>
                        <div>
                            <h3 style="margin:0;font-size:1.2rem;font-weight:800;color:#0f172a;">Step 3: Attaching Hotel Accommodation</h3>
                            <p class="hint mb-0" style="font-size:0.85rem;color:#64748b;">Select an approved partner hotel room. System displays actual property photos, nightly rates, and inclusions.</p>
                        </div>
                    </div>

                    <!-- Hotel Selection Grid with Actual Cover Photos -->
                    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));gap:1rem;margin-bottom:1.5rem;">
                        <?php foreach ($hotelRooms as $idx => $r):
                            $roomId = (int) $r['id'];
                            $hName = (string) ($r['hotel_name'] ?? 'Sea Breeze Oceanfront Resort');
                            $rName = (string) ($r['room_name'] ?? $r['name'] ?? 'Deluxe Queen Room');
                            $hRate = (float) $r['price_per_night'];
                            $hMax = (int) ($r['max_guests'] ?? 2);
                            $hBed = (string) ($r['bed_type'] ?? '1 Bed');
                            $hArea = (string) ($r['hotel_area'] ?? 'Moalboal, Cebu');
                            $hCover = (string) ($r['hotel_cover'] ?? 'https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?w=800&q=80');
                            $hPartner = (string) ($r['partner_name'] ?? 'Verified Partner');
                            $isSelectedHotel = ($idx === 0);
                        ?>
                            <label class="pkg-card-radio <?= $isSelectedHotel ? 'selected' : '' ?>" id="hotelCard_<?= $roomId ?>">
                                <input type="radio" name="selected_hotel_room" value="<?= $roomId ?>"
                                       data-hotel-name="<?= e($hName) ?>"
                                       data-room-name="<?= e($rName) ?>"
                                       data-rate="<?= $hRate ?>"
                                       data-max-guests="<?= $hMax ?>"
                                       data-checkin="<?= e($r['check_in_time'] ?? '14:00:00') ?>"
                                       data-checkout="<?= e($r['check_out_time'] ?? '12:00:00') ?>"
                                       <?= $isSelectedHotel ? 'checked' : '' ?>
                                       onchange="selectHotelRoom(this)"
                                       style="position:absolute;top:0.75rem;right:0.75rem;z-index:2;width:18px;height:18px;accent-color:#7c3aed;">
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
                                    <span class="hint" style="font-size:0.75rem;color:#64748b;display:block;margin-top:0.5rem;">Partner: <?= e($hPartner) ?></span>
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

                    <input type="hidden" id="selected_hotel_name" name="selected_hotel_name" value="<?= e($hotelRooms[0]['hotel_name'] ?? 'Sea Breeze Oceanfront Resort') ?> (<?= e($hotelRooms[0]['room_name'] ?? 'Deluxe Queen Room') ?>)">

                    <!-- Check-in and Check-out Schedule (Informational Text / Non-editable Cards) -->
                    <div style="background:#f8fafc;padding:1.25rem;border-radius:14px;border:1px solid #e2e8f0;margin-bottom:1rem;">
                        <h4 style="margin:0 0 0.5rem 0;font-size:0.95rem;font-weight:700;color:#0f172a;display:flex;align-items:center;gap:0.4rem;">
                            <span>🕒</span> Check-in & Check-out Schedule Information
                        </h4>
                        <p style="font-size:0.8rem;color:#64748b;margin:0 0 0.85rem 0;">
                            Standard hospitality check-in and checkout policies retrieved directly from the accommodation partner's availability settings.
                        </p>
                        <div class="form-grid-2">
                            <div style="background:#ffffff;padding:0.85rem 1rem;border-radius:10px;border:1px solid #e2e8f0;border-left:3px solid #7c3aed;">
                                <strong style="display:block;font-size:0.75rem;color:#7c3aed;text-transform:uppercase;letter-spacing:0.05em;">Check-in Schedule</strong>
                                <span style="font-size:0.95rem;font-weight:700;color:#0f172a;display:block;margin-top:2px;" id="info_hotel_checkin">Day 1 · 02:00 PM Onwards</span>
                                <small style="color:#64748b;font-size:0.75rem;">Resort front desk registration & room key handover</small>
                            </div>
                            <div style="background:#ffffff;padding:0.85rem 1rem;border-radius:10px;border:1px solid #e2e8f0;border-left:3px solid #10b981;">
                                <strong style="display:block;font-size:0.75rem;color:#059669;text-transform:uppercase;letter-spacing:0.05em;">Check-out Schedule</strong>
                                <span style="font-size:0.95rem;font-weight:700;color:#0f172a;display:block;margin-top:2px;" id="info_hotel_checkout">Day 3 · 12:00 PM Midday</span>
                                <small style="color:#64748b;font-size:0.75rem;">Room inspection, luggage assistance & departure checkout</small>
                            </div>
                        </div>
                    </div>

                    <!-- Bottom Navigation: Cancel beside Next (labeled 'Next') -->
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:1.5rem;border-top:1px solid #f1f5f9;padding-top:1.25rem;">
                        <button type="button" onclick="goToStep(2)" style="border:1px solid #cbd5e1;background:#ffffff;color:#64748b;border-radius:999px;padding:0.65rem 1.4rem;font-weight:600;cursor:pointer;">← Back</button>
                        <div style="display:flex;align-items:center;gap:0.75rem;">
                            <a href="<?= e(url('/dashboard/listings')) ?>" class="btn" style="border:1px solid #cbd5e1;background:#ffffff;color:#475569;border-radius:999px;padding:0.65rem 1.6rem;font-weight:600;text-decoration:none;">Cancel</a>
                            <button type="button" onclick="goToStep(4)" style="background:#10b981;border:none;border-radius:999px;color:#ffffff;padding:0.65rem 1.75rem;font-weight:700;display:inline-flex;align-items:center;gap:0.4rem;cursor:pointer;box-shadow:0 2px 6px rgba(16,185,129,0.25);">
                                Next <span>→</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ======================================================== -->
                <!-- STEP 4: ATTACHING RENTAL TRANSPORTATION (White Bg)       -->
                <!-- ======================================================== -->
                <div id="stepSection4" class="panel pkg-step-content" style="padding:1.75rem;margin-bottom:1.5rem;border-radius:16px;background:#ffffff;border:1px solid #e2e8f0;box-shadow:0 1px 4px rgba(0,0,0,0.04);display:none;">
                    <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:1.25rem;border-bottom:1px solid #f1f5f9;padding-bottom:0.85rem;">
                        <span style="font-size:1.5rem;">🛵</span>
                        <div>
                            <h3 style="margin:0;font-size:1.2rem;font-weight:800;color:#0f172a;">Step 4: Attaching Rental Transportation</h3>
                            <p class="hint mb-0" style="font-size:0.85rem;color:#64748b;">Choose dedicated transportation: scooters for couples or a passenger van with driver for group travelers.</p>
                        </div>
                    </div>

                    <!-- Vehicle Selection Grid with Actual Vehicle Images -->
                    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));gap:1rem;margin-bottom:1.5rem;">
                        <?php foreach ($vehicles as $idx => $v):
                            $vId = (int) $v['id'];
                            $vName = (string) ($v['vehicle_name'] ?? ($v['make'] . ' ' . $v['model']));
                            $vType = ucfirst((string) ($v['vehicle_type'] ?? 'Scooter'));
                            $vRate = (float) $v['daily_rate'];
                            $vImage = (string) ($v['vehicle_image'] ?? 'uploads/vehicles/167/6d69ffd5b4ee70ab.jpg');
                            $vLoc = (string) ($v['location'] ?? 'Cebu, Philippines');
                            $vPartner = (string) ($v['partner_name'] ?? 'Rental Partner');
                            $isSelectedVehicle = ($idx === 0);
                        ?>
                            <label class="pkg-card-radio <?= $isSelectedVehicle ? 'selected' : '' ?>" id="vehicleCard_<?= $vId ?>">
                                <input type="radio" name="selected_vehicle_id" value="<?= $vId ?>"
                                       data-vehicle-model="<?= e($vName) ?>"
                                       data-vehicle-type="<?= e($vType) ?>"
                                       data-daily-rate="<?= $vRate ?>"
                                       <?= $isSelectedVehicle ? 'checked' : '' ?>
                                       onchange="selectVehicle(this)"
                                       style="position:absolute;top:0.75rem;right:0.75rem;z-index:2;width:18px;height:18px;accent-color:#7c3aed;">
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
                                    <span class="hint" style="font-size:0.75rem;color:#64748b;display:block;margin-top:0.5rem;">Provider: <?= e($vPartner) ?></span>
                                </div>
                            </label>
                        <?php endforeach; ?>

                        <!-- Option for no vehicle -->
                        <label class="pkg-card-radio" id="vehicleCard_none">
                            <input type="radio" name="selected_vehicle_id" value="none"
                                   data-vehicle-model="none"
                                   data-vehicle-type="none"
                                   data-daily-rate="0"
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

                    <input type="hidden" id="selected_vehicle_name" name="selected_vehicle_name" value="<?= e($vehicles[0]['vehicle_name'] ?? 'Yamaha NMAX 155cc ABS') ?>">

                    <!-- Rental Schedule (Informational Text / Non-editable Cards) -->
                    <div style="background:#f8fafc;padding:1.25rem;border-radius:14px;border:1px solid #e2e8f0;margin-bottom:1rem;">
                        <h4 style="margin:0 0 0.5rem 0;font-size:0.95rem;font-weight:700;color:#0f172a;display:flex;align-items:center;gap:0.4rem;">
                            <span>🕒</span> Vehicle Handover & Return Schedule
                        </h4>
                        <p style="font-size:0.8rem;color:#64748b;margin:0 0 0.85rem 0;">
                            Standard pickup and drop-off schedule based on verified partner rental terms.
                        </p>
                        <div class="form-grid-2">
                            <div style="background:#ffffff;padding:0.85rem 1rem;border-radius:10px;border:1px solid #e2e8f0;border-left:3px solid #3b82f6;">
                                <strong style="display:block;font-size:0.75rem;color:#2563eb;text-transform:uppercase;letter-spacing:0.05em;">Handover Schedule</strong>
                                <span style="font-size:0.95rem;font-weight:700;color:#0f172a;display:block;margin-top:2px;">Day 1 · 01:00 PM Handover</span>
                                <small style="color:#64748b;font-size:0.75rem;">Vehicle briefing, safety checklist, and helmet fitting at jump-off point</small>
                            </div>
                            <div style="background:#ffffff;padding:0.85rem 1rem;border-radius:10px;border:1px solid #e2e8f0;border-left:3px solid #10b981;">
                                <strong style="display:block;font-size:0.75rem;color:#059669;text-transform:uppercase;letter-spacing:0.05em;">Return Schedule</strong>
                                <span style="font-size:0.95rem;font-weight:700;color:#0f172a;display:block;margin-top:2px;">Day 3 · 12:00 PM Midday</span>
                                <small style="color:#64748b;font-size:0.75rem;">Drop-off unit inspection and security clearance</small>
                            </div>
                        </div>
                    </div>

                    <!-- Bottom Navigation: Cancel beside Next (labeled 'Next') -->
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:1.5rem;border-top:1px solid #f1f5f9;padding-top:1.25rem;">
                        <button type="button" onclick="goToStep(3)" style="border:1px solid #cbd5e1;background:#ffffff;color:#64748b;border-radius:999px;padding:0.65rem 1.4rem;font-weight:600;cursor:pointer;">← Back</button>
                        <div style="display:flex;align-items:center;gap:0.75rem;">
                            <a href="<?= e(url('/dashboard/listings')) ?>" class="btn" style="border:1px solid #cbd5e1;background:#ffffff;color:#475569;border-radius:999px;padding:0.65rem 1.6rem;font-weight:600;text-decoration:none;">Cancel</a>
                            <button type="button" onclick="goToStep(5)" style="background:#10b981;border:none;border-radius:999px;color:#ffffff;padding:0.65rem 1.75rem;font-weight:700;display:inline-flex;align-items:center;gap:0.4rem;cursor:pointer;box-shadow:0 2px 6px rgba(16,185,129,0.25);">
                                Next <span>→</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ======================================================== -->
                <!-- STEP 5: PRICING, PHOTOS & PACKAGE SUBMISSION (White Bg)  -->
                <!-- ======================================================== -->
                <div id="stepSection5" class="panel pkg-step-content" style="padding:1.75rem;margin-bottom:1.5rem;border-radius:16px;background:#ffffff;border:1px solid #e2e8f0;box-shadow:0 1px 4px rgba(0,0,0,0.04);display:none;">
                    <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:1.25rem;border-bottom:1px solid #f1f5f9;padding-bottom:0.85rem;">
                        <span style="font-size:1.5rem;">💎</span>
                        <div>
                            <h3 style="margin:0;font-size:1.2rem;font-weight:800;color:#0f172a;">Step 5: Pricing, Photos & Package Submission</h3>
                            <p class="hint mb-0" style="font-size:0.85rem;color:#64748b;">Review costs in the financial breakdown, upload package photos, and submit for approval.</p>
                        </div>
                    </div>

                    <!-- LIVE FINANCIAL SIMULATOR CARD -->
                    <div style="background:#f8fafc;color:#0f172a;padding:1.5rem;border-radius:16px;margin-bottom:1.5rem;box-shadow:0 1px 4px rgba(0,0,0,0.04);border:1px solid #e2e8f0;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;border-bottom:1px solid #e2e8f0;padding-bottom:0.75rem;">
                            <span style="font-size:0.85rem;text-transform:uppercase;letter-spacing:0.08em;font-weight:800;color:#7c3aed;">All-Inclusive Package Split Simulation</span>
                            <span style="background:#ede9fe;color:#7c3aed;padding:3px 12px;border-radius:999px;font-size:0.75rem;font-weight:700;">Based on 2 Pax Default Group</span>
                        </div>

                        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(160px, 1fr));gap:0.85rem;margin-bottom:1.25rem;">
                            <div style="background:#ffffff;padding:0.85rem;border-radius:12px;border:1px solid #e2e8f0;">
                                <span style="font-size:0.75rem;color:#64748b;display:block;">🏄 Activity Rates</span>
                                <strong id="sim_activity_fee" style="font-size:1.25rem;display:block;margin-top:2px;color:#0f172a;">₱0.00</strong>
                            </div>

                            <div style="background:#ffffff;padding:0.85rem;border-radius:12px;border:1px solid #e2e8f0;">
                                <span style="font-size:0.75rem;color:#64748b;display:block;">🏨 Hotel Stay (<?= $defaultNights ?> nights)</span>
                                <strong id="sim_hotel_fee" style="font-size:1.25rem;display:block;margin-top:2px;color:#0f172a;">₱2,200.00</strong>
                            </div>

                            <div style="background:#ffffff;padding:0.85rem;border-radius:12px;border:1px solid #e2e8f0;">
                                <span style="font-size:0.75rem;color:#64748b;display:block;">🛵 Transport (<?= $defaultDays ?> days)</span>
                                <strong id="sim_rental_fee" style="font-size:1.25rem;display:block;margin-top:2px;color:#0f172a;">₱975.00</strong>
                            </div>

                            <div style="background:#faf5ff;padding:0.85rem;border-radius:12px;border:1px solid #d8b4fe;">
                                <span style="font-size:0.75rem;color:#7c3aed;display:block;font-weight:600;">🧑‍💼 Guide Curation Margin</span>
                                <strong id="sim_guide_margin" style="font-size:1.25rem;display:block;margin-top:2px;color:#6d28d9;">₱2,200.00</strong>
                            </div>

                            <div style="background:#ffffff;padding:0.85rem;border-radius:12px;border:1px solid #e2e8f0;">
                                <span style="font-size:0.75rem;color:#64748b;display:block;">🛡️ GuideMate Fee (8%)</span>
                                <strong id="sim_platform_fee" style="font-size:1.25rem;display:block;margin-top:2px;color:#0f172a;">₱430.00</strong>
                            </div>
                        </div>

                        <div style="display:flex;align-items:center;justify-content:space-between;border-top:1px dashed #cbd5e1;padding-top:1.15rem;padding-bottom:0.35rem;flex-wrap:wrap;gap:1rem;">
                            <div>
                                <span style="font-size:1.05rem;color:#0f172a;text-transform:uppercase;font-weight:800;letter-spacing:0.04em;">
                                    💰 Calculated Package Estimate
                                </span>
                            </div>
                            <div style="text-align:right;">
                                <span id="sim_final_total" style="font-size:2.4rem;font-weight:900;color:#059669;letter-spacing:-0.03em;">₱<?= number_format($defaultPrice, 2) ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Hidden Inputs for Calculated Values (Package pricing is automatically based on the Calculated Package Estimate) -->
                    <input type="hidden" id="calc_activity_fee" name="calc_activity_fee" value="0">
                    <input type="hidden" id="calc_hotel_fee" name="calc_hotel_fee" value="2200">
                    <input type="hidden" id="calc_rental_fee" name="calc_rental_fee" value="975">
                    <input type="hidden" id="calc_platform_fee" name="calc_platform_fee" value="430">
                    <input type="hidden" id="final_price_per_pax" name="final_price_per_pax" value="<?= $defaultPrice ?>">
                    <input type="hidden" id="total_package_price" name="total_package_price" value="<?= $defaultPrice ?>">

                    <!-- Interactive Guide Margin Controller (Calculated package estimate automatically adapts) -->
                    <div style="background:#f8fafc;padding:1.25rem;border-radius:14px;border:1px solid #e2e8f0;margin-bottom:1.5rem;">
                        <label for="guide_margin" style="font-weight:700;display:flex;justify-content:space-between;color:#0f172a;margin-bottom:0.5rem;">
                            <span>Curation & Coordination Margin (₱)</span>
                            <span id="guide_margin_val" style="color:#7c3aed;font-weight:800;">₱2,200</span>
                        </label>
                        <input class="input pkg-input" type="number" id="guide_margin" name="guide_margin" min="0" step="50" value="2200" oninput="calculateFinancials()" style="font-size:1.15rem;font-weight:800;color:#7c3aed;width:100%;">
                        <span class="hint" style="font-size:0.8rem;color:#64748b;display:block;margin-top:0.4rem;">
                            Adjust your earnings margin for curating this travel bundle. The Calculated Package Estimate automatically updates as your package's published pricing basis.
                        </span>
                    </div>

                    <!-- STRUCTURED 3-PHOTO UPLOAD SECTION (Reusing Guide Account Upload Pattern) -->
                    <div class="field-row" style="background:#f8fafc;padding:1.25rem;border-radius:14px;border:1px dashed #cbd5e1;margin-bottom:1.5rem;">
                        <label style="font-weight:700;color:#0f172a;margin-bottom:0.25rem;display:flex;align-items:center;justify-content:space-between;">
                            <span>📸 Package Photos (Upload up to 3 photos)</span>
                            <span style="font-size:0.75rem;font-weight:600;background:#ede9fe;color:#7c3aed;padding:2px 8px;border-radius:12px;">Max 3 Photos</span>
                        </label>
                        <span class="hint" style="display:block;margin-bottom:0.85rem;font-size:0.8rem;color:#64748b;">
                            Upload high-quality JPG, PNG, or WEBP images (up to 5MB each) or paste direct photo URLs. Photo 1 will be used as the primary cover banner.
                        </span>

                        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:0.85rem;">
                            <?php for ($i = 0; $i < 3; $i++):
                                $existingPhoto = $existingPhotos[$i] ?? '';
                                $hasExisting = !empty($existingPhoto);
                            ?>
                                <div style="border:1px solid #e2e8f0;border-radius:10px;background:#ffffff;padding:0.75rem;display:flex;flex-direction:column;gap:0.4rem;">
                                    <div style="display:flex;align-items:center;justify-content:space-between;">
                                        <strong style="font-size:0.8rem;color:#0f172a;">Photo <?= $i + 1 ?><?= $i === 0 ? ' (Cover) *' : '' ?></strong>
                                        <?php if ($i === 0): ?>
                                            <span style="font-size:0.68rem;background:#dcfce7;color:#166534;font-weight:700;padding:1px 6px;border-radius:4px;">Cover</span>
                                        <?php endif; ?>
                                    </div>
                                    <div id="pkg_preview_wrap_<?= $i ?>" style="position:relative;height:110px;background:#f1f5f9;border-radius:8px;overflow:hidden;display:flex;align-items:center;justify-content:center;border:1px solid #e2e8f0;">
                                        <img id="pkg_preview_img_<?= $i ?>" src="<?= $hasExisting ? e(img_src($existingPhoto, 'pkg-' . $i)) : '' ?>" alt="Photo <?= $i + 1 ?>" style="width:100%;height:100%;object-fit:cover;display:<?= $hasExisting ? 'block' : 'none' ?>;">
                                        <span id="pkg_placeholder_<?= $i ?>" style="font-size:0.75rem;color:#94a3b8;text-align:center;padding:4px;display:<?= $hasExisting ? 'none' : 'block' ?>;">📷 No photo uploaded</span>
                                        <button type="button" id="pkg_remove_btn_<?= $i ?>" onclick="removePkgPhoto(<?= $i ?>)" style="display:<?= $hasExisting ? 'flex' : 'none' ?>;position:absolute;top:4px;right:4px;background:rgba(0,0,0,0.65);color:#fff;border:none;border-radius:50%;width:22px;height:22px;font-size:12px;line-height:1;cursor:pointer;align-items:center;justify-content:center;" title="Remove Photo">&times;</button>
                                    </div>
                                    <input type="file" name="photo_file_<?= $i ?>" id="pkg_file_<?= $i ?>" accept=".jpg,.jpeg,.png,.webp" style="font-size:0.75rem;width:100%;color:#475569;" onchange="handlePkgFileChange(<?= $i ?>, this)">
                                    <input type="text" name="photo_url_<?= $i ?>" id="pkg_url_<?= $i ?>" class="input pkg-input input-sm" placeholder="or paste photo URL..." style="font-size:0.75rem;" oninput="handlePkgUrlChange(<?= $i ?>, this.value)">
                                    <input type="hidden" name="photo_existing_<?= $i ?>" id="pkg_existing_<?= $i ?>" value="<?= e($existingPhoto) ?>">
                                    <input type="hidden" name="photo_removed_<?= $i ?>" id="pkg_removed_<?= $i ?>" value="0">
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <!-- SECTION 7: ITINERARY, INCLUSIONS & BOOKING REQUIREMENTS -->
                    <div class="field-row" style="margin-bottom:1.5rem;">
                        <label for="pkg_description" style="font-weight:700;color:#0f172a;">About This Package (Detailed Description)</label>
                        <textarea class="input pkg-input" id="pkg_description" name="description" rows="4"
                                  placeholder="Describe the overall package experience, atmosphere, and what makes it extraordinary."><?= e($defaultDesc) ?></textarea>
                    </div>

                    <div class="form-grid-2" style="margin-bottom:1.5rem;">
                        <div class="field-row">
                            <label for="pkg_itinerary" style="font-weight:700;color:#0f172a;">Day-by-Day Itinerary Highlights</label>
                            <textarea class="input pkg-input" id="pkg_itinerary" name="itinerary" rows="6"
                                      placeholder="Day 1: Arrival & Moalboal Sunset Marine Sanctuary&#10;Day 2: Full Kawasan Canyoneering Expedition&#10;Day 3: Scenic Coastal Ride & Departure"><?= e($defaultItinerary) ?></textarea>
                            <span class="hint" style="font-size:0.8rem;color:#64748b;">Outlines the daily schedule so travelers know exactly what to expect.</span>
                        </div>

                        <div class="field-row">
                            <label for="pkg_policies" style="font-weight:700;color:#0f172a;">Policies & Traveler Requirements</label>
                            <textarea class="input pkg-input" id="pkg_policies" name="policies" rows="6"
                                      placeholder="e.g. Free cancellation up to 48 hours prior to trip departure (50% refund within 24-48 hours).&#10;Requirements:&#10;• Valid Government ID or Passport for all participants&#10;• Valid Motorcycle/Driver's license for scooter rider&#10;• Moderate physical fitness for canyoneering"><?= e($defaultPolicies) ?></textarea>
                            <span class="hint" style="font-size:0.8rem;color:#64748b;">Provides transparent booking conditions for tourist reassurance.</span>
                        </div>
                    </div>

                    <!-- Bottom Navigation: Cancel beside Submit -->
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:1.5rem;border-top:1px solid #f1f5f9;padding-top:1.25rem;">
                        <button type="button" onclick="goToStep(4)" style="border:1px solid #cbd5e1;background:#ffffff;color:#64748b;border-radius:999px;padding:0.65rem 1.4rem;font-weight:600;cursor:pointer;">← Back</button>
                        <div style="display:flex;align-items:center;gap:0.75rem;">
                            <a href="<?= e(url('/dashboard/listings')) ?>" class="btn" style="border:1px solid #cbd5e1;background:#ffffff;color:#475569;border-radius:999px;padding:0.65rem 1.6rem;font-weight:600;text-decoration:none;">Cancel</a>
                            <button type="submit" style="background:#10b981;border:none;border-radius:999px;color:#ffffff;padding:0.75rem 2rem;font-size:1rem;font-weight:800;cursor:pointer;box-shadow:0 4px 14px rgba(16,185,129,0.3);">
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
    border-color: #7c3aed !important;
    box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.15) !important;
    outline: none !important;
}

/* Stepper Pill on White Background */
.pkg-step-pill {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.75rem;
    border-radius: 10px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    cursor: pointer;
    transition: all 0.2s ease;
}
.pkg-step-pill:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
}
.pkg-step-pill.active {
    background: #7c3aed;
    border-color: #6d28d9;
    box-shadow: 0 2px 8px rgba(124, 58, 237, 0.25);
}
.pkg-step-num {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: #e2e8f0;
    color: #475569;
    font-size: 0.75rem;
    font-weight: 700;
}
.pkg-step-pill.active .pkg-step-num {
    background: #ffffff;
    color: #7c3aed;
}
.pkg-step-label strong {
    display: block;
    font-size: 0.8rem;
    color: #0f172a;
    line-height: 1.2;
}
.pkg-step-label small {
    font-size: 0.7rem;
    color: #64748b;
}
.pkg-step-pill.active .pkg-step-label strong {
    color: #ffffff;
}
.pkg-step-pill.active .pkg-step-label small {
    color: #ede9fe;
}

/* Card-Based Layout for Activities, Hotels, and Vehicles */
.pkg-destination-card, .pkg-card-radio {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    overflow: hidden;
    cursor: pointer;
    transition: all 0.25s ease;
    position: relative;
    display: flex;
    flex-direction: column;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
}
.pkg-destination-card:hover, .pkg-card-radio:hover {
    border-color: #cbd5e1;
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0,0,0,0.08);
}
.pkg-destination-card.is-selected, .pkg-card-radio.selected {
    border: 2px solid #7c3aed !important;
    background: #faf5ff !important;
    box-shadow: 0 0 0 1px #7c3aed, 0 8px 20px rgba(124, 58, 237, 0.12) !important;
}
.pkg-card-img-wrap {
    position: relative;
    width: 100%;
    height: 160px;
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
.badge-purple { background: rgba(124, 58, 237, 0.9); color: #ffffff; }
.badge-pink { background: rgba(236, 72, 153, 0.9); color: #ffffff; }
.badge-amber { background: rgba(245, 158, 11, 0.95); color: #ffffff; }
.badge-blue { background: rgba(37, 99, 235, 0.9); color: #ffffff; }

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
    background: #7c3aed;
    border-color: #6d28d9;
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
</style>

<script>
// Selected activities state: empty in create mode, pre-populated in edit mode
let selectedActivities = <?= json_encode($selectedActivities ?? []) ?>;

let selectedHotelRate = <?= (float) ($hotelRooms[0]['price_per_night'] ?? 2200) ?>;
let selectedHotelName = "<?= e(addslashes($hotelRooms[0]['hotel_name'] ?? 'Sea Breeze Resort')) ?>";
let selectedVehicleDailyRate = <?= (float) ($vehicles[0]['daily_rate'] ?? 650) ?>;

function goToStep(step) {
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
    window.scrollTo({ top: 120, behavior: 'smooth' });
}

function updateSimulatorTitle() {}
function updateSimulatorArea() {}

function toggleActivityCard(id, title, rate, area, cover, category) {
    const existingIndex = selectedActivities.findIndex(a => a.id === id);
    const cardEl = document.getElementById('card_act_' + id);

    if (existingIndex !== -1) {
        // Remove
        selectedActivities.splice(existingIndex, 1);
        if (cardEl) {
            cardEl.classList.remove('is-selected');
            const icon = cardEl.querySelector('.pkg-check-icon');
            const text = cardEl.querySelector('.pkg-check-text');
            if (icon) icon.innerText = '+';
            if (text) text.innerText = 'Select';
        }
    } else {
        // Add
        selectedActivities.push({
            id: id,
            title: title,
            rate: rate,
            area: area,
            cover: cover,
            category: category,
            day: 'Day ' + Math.min(3, selectedActivities.length + 1),
            time: '09:00 AM'
        });
        if (cardEl) {
            cardEl.classList.add('is-selected');
            const icon = cardEl.querySelector('.pkg-check-icon');
            const text = cardEl.querySelector('.pkg-check-text');
            if (icon) icon.innerText = '✓';
            if (text) text.innerText = 'Included';
        }
    }

    renderIncludedActivities();
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
    renderIncludedActivities();
    calculateFinancials();
}

function updateActivitySchedule(id, field, value) {
    const act = selectedActivities.find(a => a.id === id);
    if (act) {
        act[field] = value;
    }
}

function renderIncludedActivities() {
    const container = document.getElementById('includedActivitiesList');
    const alertEl = document.getElementById('noActivitiesAlert');
    const countBadge = document.getElementById('selectedActivitiesCount');

    if (!container) return;

    if (countBadge) {
        countBadge.innerText = selectedActivities.length + ' Selected';
    }

    if (selectedActivities.length === 0) {
        container.innerHTML = '';
        if (alertEl) alertEl.style.display = 'block';
        return;
    }

    if (alertEl) alertEl.style.display = 'none';

    container.innerHTML = selectedActivities.map(act => {
        const isPink = act.category.toLowerCase().includes('attraction');
        return `
            <div class="pkg-destination-card is-selected" style="margin:0;">
                <input type="hidden" name="activities[]" value="${escapeHtml(act.title)}">
                <input type="hidden" name="selected_activity_ids[]" value="${act.id}">
                <div class="pkg-card-img-wrap" style="height:140px;">
                    <img src="${resolveCardImg(act.cover)}" alt="${escapeHtml(act.title)}" class="pkg-card-img" onerror="this.src='https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?w=800&q=80'">
                    <span class="pkg-category-badge ${isPink ? 'badge-pink' : 'badge-purple'}">${escapeHtml(act.category)}</span>
                    <button type="button" onclick="removeActivityById(${act.id})" style="position:absolute;top:0.6rem;right:0.6rem;background:rgba(225,29,72,0.9);color:#ffffff;border:none;border-radius:6px;padding:3px 8px;font-size:0.75rem;font-weight:700;cursor:pointer;">
                        ✕ Remove
                    </button>
                </div>
                <div class="pkg-card-body" style="padding:0.75rem;">
                    <div class="pkg-card-location"><span>📍</span> ${escapeHtml(act.area)}</div>
                    <h4 class="pkg-card-title" style="font-size:0.9rem;margin-bottom:0.4rem;">${escapeHtml(act.title)}</h4>
                    <div style="font-size:0.85rem;color:#059669;font-weight:700;margin-bottom:0.5rem;">
                        ₱${Number(act.rate).toLocaleString('en-US', {minimumFractionDigits:2})} <small style="color:#64748b;font-weight:normal;">/ person</small>
                    </div>

                    <div style="background:#f8fafc;padding:0.5rem;border-radius:8px;border:1px solid #e2e8f0;display:flex;gap:0.5rem;">
                        <div style="flex:1;">
                            <label style="font-size:0.7rem;color:#64748b;display:block;margin-bottom:2px;font-weight:600;">Itinerary Day</label>
                            <select class="input pkg-input input-sm" style="font-size:0.75rem;padding:2px 4px;height:30px;" onchange="updateActivitySchedule(${act.id}, 'day', this.value)">
                                <option value="Day 1" ${act.day === 'Day 1' ? 'selected' : ''}>Day 1</option>
                                <option value="Day 2" ${act.day === 'Day 2' ? 'selected' : ''}>Day 2</option>
                                <option value="Day 3" ${act.day === 'Day 3' ? 'selected' : ''}>Day 3</option>
                            </select>
                        </div>
                        <div style="flex:1;">
                            <label style="font-size:0.7rem;color:#64748b;display:block;margin-bottom:2px;font-weight:600;">Time Slot</label>
                            <select class="input pkg-input input-sm" style="font-size:0.75rem;padding:2px 4px;height:30px;" onchange="updateActivitySchedule(${act.id}, 'time', this.value)">
                                <option value="08:30 AM" ${act.time === '08:30 AM' ? 'selected' : ''}>08:30 AM</option>
                                <option value="10:00 AM" ${act.time === '10:00 AM' ? 'selected' : ''}>10:00 AM</option>
                                <option value="01:00 PM" ${act.time === '01:00 PM' ? 'selected' : ''}>01:00 PM</option>
                                <option value="03:00 PM" ${act.time === '03:00 PM' ? 'selected' : ''}>03:00 PM</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }).join('');
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

function selectHotelRoom(radio) {
    document.querySelectorAll('[id^="hotelCard_"]').forEach(el => el.classList.remove('selected'));
    const parentCard = radio.closest('.pkg-card-radio');
    if (parentCard) parentCard.classList.add('selected');

    selectedHotelRate = parseFloat(radio.dataset.rate) || 0;
    const hName = radio.dataset.hotelName || '';
    const rName = radio.dataset.roomName || '';
    const hiddenNameInput = document.getElementById('selected_hotel_name');
    if (hiddenNameInput) {
        hiddenNameInput.value = hName === 'none' ? 'None' : (hName + ' (' + rName + ')');
    }

    const checkinText = document.getElementById('info_hotel_checkin');
    const checkoutText = document.getElementById('info_hotel_checkout');
    if (checkinText) {
        checkinText.innerText = hName === 'none' ? 'N/A (No Hotel Stay)' : 'Day 1 · 02:00 PM Onwards';
    }
    if (checkoutText) {
        checkoutText.innerText = hName === 'none' ? 'N/A (No Hotel Stay)' : 'Day 3 · 12:00 PM Midday';
    }

    calculateFinancials();
}

function selectVehicle(radio) {
    document.querySelectorAll('[id^="vehicleCard_"]').forEach(el => el.classList.remove('selected'));
    const parentCard = radio.closest('.pkg-card-radio');
    if (parentCard) parentCard.classList.add('selected');

    selectedVehicleDailyRate = parseFloat(radio.dataset.dailyRate) || 0;
    const vModel = radio.dataset.vehicleModel || '';
    const hiddenNameInput = document.getElementById('selected_vehicle_name');
    if (hiddenNameInput) {
        hiddenNameInput.value = vModel === 'none' ? 'None' : vModel;
    }

    calculateFinancials();
}

// -------------------------------------------------------------
// Package Photo Upload Handlers (Matching Guide Listings Pattern)
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

function calculateFinancials() {
    const days = parseInt(document.getElementById('pkg_days').value) || 3;
    const nights = parseInt(document.getElementById('pkg_nights').value) || 2;
    const guideMargin = parseFloat(document.getElementById('guide_margin').value) || 0;

    // 1. Total Activity Fee per pax
    let totalActivityRate = 0;
    selectedActivities.forEach(act => {
        totalActivityRate += (parseFloat(act.rate) || 0);
    });

    // 2. Hotel Net Rate per pax (Split across 2 pax standard occupancy)
    const hotelTotal = selectedHotelRate * nights;
    const hotelPerPax = hotelTotal / 2;

    // 3. Rental Net Rate per pax (1 Scooter per 2 pax)
    const vehicleTotal = selectedVehicleDailyRate * days;
    const vehiclePerPax = vehicleTotal / 2;

    // 4. Subtotal & Platform Fee
    const subtotal = totalActivityRate + hotelPerPax + vehiclePerPax + guideMargin;
    const platformFee = Math.round(subtotal * 0.08);
    const calculatedTotal = Math.round(subtotal + platformFee);

    // Update Simulator Labels
    const actEl = document.getElementById('sim_activity_fee');
    const hotEl = document.getElementById('sim_hotel_fee');
    const renEl = document.getElementById('sim_rental_fee');
    const marEl = document.getElementById('sim_guide_margin');
    const plaEl = document.getElementById('sim_platform_fee');
    const totEl = document.getElementById('sim_final_total');
    const marVal = document.getElementById('guide_margin_val');
    const totalPkgInput = document.getElementById('total_package_price');

    if (actEl) actEl.innerText = '₱' + totalActivityRate.toLocaleString('en-US', { minimumFractionDigits: 2 });
    if (hotEl) hotEl.innerText = '₱' + hotelPerPax.toLocaleString('en-US', { minimumFractionDigits: 2 });
    if (renEl) renEl.innerText = '₱' + vehiclePerPax.toLocaleString('en-US', { minimumFractionDigits: 2 });
    if (marEl) marEl.innerText = '₱' + guideMargin.toLocaleString('en-US', { minimumFractionDigits: 2 });
    if (plaEl) plaEl.innerText = '₱' + platformFee.toLocaleString('en-US', { minimumFractionDigits: 2 });
    if (marVal) marVal.innerText = '₱' + guideMargin.toLocaleString('en-US');

    // Update hidden inputs for server POST
    const cAct = document.getElementById('calc_activity_fee');
    const cHot = document.getElementById('calc_hotel_fee');
    const cRen = document.getElementById('calc_rental_fee');
    const cPla = document.getElementById('calc_platform_fee');
    const cFin = document.getElementById('final_price_per_pax');

    if (cAct) cAct.value = totalActivityRate;
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
    renderIncludedActivities();
    calculateFinancials();
});
</script>
