<?php

require __DIR__ . '/patch_helper.php';

$showFile = 'app/Views/listings/show.php';
$content = file_get_contents($showFile);

// 1. Add hotel detection variables at top
$topSearch = "\$host = host_labels(\$listing['owner_role'] ?? null);";
$topReplace = "\$host = host_labels(\$listing['owner_role'] ?? null);
\$isHotel = (\$listing['category_slug'] ?? '') === 'hotels';
\$hotelRooms = \$hotelRooms ?? (\$isHotel ? \\App\\Models\\HotelRoom::forListing((int) \$listing['id']) : []);";

patchFile($showFile, $topSearch, $topReplace);

// 2. Add Available Rooms & Suites section before "About this ..."
$aboutSearch = '<h2 class="mt-3">About this <?= e(strtolower($listing[\'category_name\'])) ?></h2>';
$roomsSection = '<?php if ($isHotel && $hotelRooms !== []): ?>
            <!-- Dedicated Available Rooms Section -->
            <section class="mt-4 mb-4" id="available-rooms">
                <div style="display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:1.25rem;flex-wrap:wrap;gap:0.5rem;border-bottom:2px solid var(--line);padding-bottom:0.75rem;">
                    <div>
                        <h2 class="mb-0" style="font-size:1.6rem;color:var(--teal-900);">Available Rooms & Suites</h2>
                        <p class="hint mb-0">Choose your room. Reserve with a small online deposit today — pay the remaining balance at hotel check-in.</p>
                    </div>
                    <span class="chip" style="background:#E0F2FE;color:#0369A1;font-weight:600;font-size:0.85rem;">🏨 <?= count($hotelRooms) ?> Room Types</span>
                </div>

                <div class="rooms-list" style="display:flex;flex-direction:column;gap:1.25rem;">
                    <?php foreach ($hotelRooms as $room): ?>
                        <?php
                        $rStatus = (string) ($room['status'] ?? 'available');
                        $isAvail = $rStatus === 'available';
                        $depositPct = (int) ($room['deposit_percentage'] ?? 20);
                        $depositNow = round(((float) $room['price_per_night'] * $depositPct) / 100, 2);
                        $balanceAtDesk = round((float) $room['price_per_night'] - $depositNow, 2);

                        $badgeHtml = match ($rStatus) {
                            \'available\' => \'<span class="badge" style="background:#DCFCE7;color:#166534;border:1px solid #86EFAC;font-weight:600;">🟢 Available</span>\',
                            \'blocked\' => \'<span class="badge" style="background:#FEF3C7;color:#92400E;border:1px solid #FCD34D;font-weight:600;">🔒 Blocked (Maintenance)</span>\',
                            \'fully_booked\' => \'<span class="badge" style="background:#FEE2E2;color:#991B1B;border:1px solid #FCA5A5;font-weight:600;">🔴 Fully Booked</span>\',
                            default => \'<span class="badge" style="background:#F1F5F9;color:#475569;border:1px solid #CBD5E1;font-weight:600;">⚪ Unavailable</span>\',
                        };
                        ?>
                        <div class="card-soft room-card" style="padding:0;overflow:hidden;border:1px solid var(--line);border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,0.05);<?= !$isAvail ? \'opacity:0.75;background:#F8FAFC;\' : \'background:#FFF;\' ?>">
                            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(260px, 1fr));">
                                <div style="position:relative;min-height:220px;background:#E2E8F0;">
                                    <img src="<?= e(img_src($room[\'image\'] ?? null, \'room\' . $room[\'id\'])) ?>" alt="<?= e($room[\'name\']) ?>" style="width:100%;height:100%;object-fit:cover;position:absolute;inset:0;" onerror="this.onerror=null;this.src=\'https://picsum.photos/seed/room<?= (int) $room[\'id\'] ?>/800/600\';">
                                </div>
                                <div style="padding:1.25rem;display:flex;flex-direction:column;justify-content:space-between;">
                                    <div>
                                        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:0.5rem;flex-wrap:wrap;">
                                            <div>
                                                <h3 style="margin:0 0 4px;font-size:1.25rem;font-weight:700;color:var(--ink-900);"><?= e($room[\'name\']) ?></h3>
                                                <div style="display:flex;gap:0.75rem;flex-wrap:wrap;font-size:0.85rem;color:var(--ink-500);margin-bottom:8px;">
                                                    <span>📐 <?= e($room[\'room_size\'] ?? \'32 m²\') ?></span>
                                                    <span>🛏️ <?= e($room[\'bed_type\'] ?? \'1 King Bed\') ?></span>
                                                    <span>👥 Up to <?= (int) ($room[\'max_guests\'] ?? 2) ?> guests</span>
                                                </div>
                                            </div>
                                            <div><?= $badgeHtml ?></div>
                                        </div>

                                        <p class="hint" style="font-size:0.88rem;line-height:1.45;margin-bottom:0.75rem;color:var(--ink-600);">
                                            <?= e($room[\'description\'] ?? \'Comfortable, well-appointed guest room with modern hotel amenities.\') ?>
                                        </p>

                                        <?php if (!empty($room[\'amenities\'])): ?>
                                            <div style="display:flex;flex-wrap:wrap;gap:5px;margin-bottom:1rem;">
                                                <?php foreach (explode(\',\', (string) $room[\'amenities\']) as $am): ?>
                                                    <?php if (trim($am) !== \'\'): ?>
                                                        <span class="chip" style="font-size:0.78rem;padding:3px 8px;background:var(--ink-50);border:1px solid var(--line);border-radius:4px;">✓ <?= e(trim($am)) ?></span>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div style="border-top:1px solid var(--line);padding-top:0.85rem;display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:0.75rem;">
                                        <div>
                                            <div style="display:flex;align-items:baseline;gap:4px;">
                                                <span style="font-size:1.45rem;font-weight:800;color:var(--teal-900);"><?= money($room[\'price_per_night\']) ?></span>
                                                <span class="hint" style="font-size:0.85rem;">/ night</span>
                                            </div>
                                            <div style="font-size:0.85rem;color:#059669;font-weight:600;margin-top:2px;">
                                                Pay only <?= $depositPct ?>% deposit (<?= money($depositNow) ?>) now · <?= money($balanceAtDesk) ?> at check-in
                                            </div>
                                            <div class="hint" style="font-size:0.78rem;color:#64748B;margin-top:2px;">
                                                🛡️ Free cancellation up to 48 hrs before check-in (100% refund)
                                            </div>
                                        </div>
                                        <div>
                                            <?php if ($isAvail): ?>
                                                <button type="button" class="btn btn-primary" onclick="selectRoomForBooking(<?= $room[\'id\'] ?>, \'<?= e(addslashes($room[\'name\'])) ?>\', <?= (float) $room[\'price_per_night\'] ?>, <?= $depositPct ?>, <?= (int) ($room[\'max_guests\'] ?? 2) ?>)">
                                                    ⚡ Select Room
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-ghost" disabled style="opacity:0.5;cursor:not-allowed;">
                                                    Unavailable
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <h2 class="mt-3">About this <?= e(strtolower($listing[\'category_name\'])) ?></h2>';

patchFile($showFile, $aboutSearch, $roomsSection);

echo "Show view patched successfully.\n";
