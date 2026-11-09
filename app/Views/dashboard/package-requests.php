<?php
/**
 * Provider Package Inclusion Requests View
 *
 * @var array<string,mixed> $user
 * @var array<int,array<string,mixed>> $requests
 * @var string $status
 * @var array<string,int> $counts
 */
$user = $user ?? auth_user();
$status = (string) ($status ?? 'all');
$requests = $requests ?? [];
$counts = $counts ?? [
    'all' => count($requests),
    'pending' => 0,
    'agreed' => 0,
    'rejected' => 0,
];
?>
<div class="container">
    <div class="dash-layout">
        <?= \App\Core\View::partial('partials/dash-nav') ?>
        <div class="dash-main">
            <!-- Header -->
            <div class="section-head" style="margin-bottom:1.5rem;">
                <div>
                    <h1 class="mb-0">Package Inclusion Requests</h1>
                    <p class="hint mb-0">Review requests from licensed tour guides wanting to bundle your services into their all-in-one travel packages. Review itinerary details, confirm your agreement, or decline requests.</p>
                </div>
            </div>

            <!-- Filter Tabs -->
            <div style="background:#f1f5f9;padding:4px;border-radius:12px;display:inline-flex;gap:4px;flex-wrap:wrap;margin-bottom:1.5rem;border:1px solid #e2e8f0;">
                <a href="<?= e(url('/dashboard/package-requests?status=all')) ?>"
                   style="<?= $status === 'all'
                       ? 'background:#059669;color:#ffffff;box-shadow:0 1px 3px rgba(5,150,105,0.25);'
                       : 'background:transparent;color:#64748b;' ?>padding:0.5rem 1.1rem;border-radius:9px;font-size:0.85rem;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:0.5rem;transition:all 0.15s ease;">
                    <span>All Requests</span>
                    <span style="<?= $status === 'all' ? 'background:rgba(255,255,255,0.25);color:#ffffff;' : 'background:#e2e8f0;color:#475569;' ?>padding:1px 7px;border-radius:9999px;font-size:0.75rem;font-weight:700;">
                        <?= (int) ($counts['all'] ?? count($requests)) ?>
                    </span>
                </a>
                <a href="<?= e(url('/dashboard/package-requests?status=pending')) ?>"
                   style="<?= $status === 'pending'
                       ? 'background:#059669;color:#ffffff;box-shadow:0 1px 3px rgba(5,150,105,0.25);'
                       : 'background:transparent;color:#64748b;' ?>padding:0.5rem 1.1rem;border-radius:9px;font-size:0.85rem;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:0.5rem;transition:all 0.15s ease;">
                    <span>Pending Action</span>
                    <span style="<?= $status === 'pending' ? 'background:rgba(255,255,255,0.25);color:#ffffff;' : 'background:#e2e8f0;color:#475569;' ?>padding:1px 7px;border-radius:9999px;font-size:0.75rem;font-weight:700;">
                        <?= (int) ($counts['pending'] ?? 0) ?>
                    </span>
                </a>
                <a href="<?= e(url('/dashboard/package-requests?status=agreed')) ?>"
                   style="<?= $status === 'agreed'
                       ? 'background:#059669;color:#ffffff;box-shadow:0 1px 3px rgba(5,150,105,0.25);'
                       : 'background:transparent;color:#64748b;' ?>padding:0.5rem 1.1rem;border-radius:9px;font-size:0.85rem;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:0.5rem;transition:all 0.15s ease;">
                    <span>Agreed</span>
                    <span style="<?= $status === 'agreed' ? 'background:rgba(255,255,255,0.25);color:#ffffff;' : 'background:#e2e8f0;color:#475569;' ?>padding:1px 7px;border-radius:9999px;font-size:0.75rem;font-weight:700;">
                        <?= (int) ($counts['agreed'] ?? 0) ?>
                    </span>
                </a>
                <a href="<?= e(url('/dashboard/package-requests?status=rejected')) ?>"
                   style="<?= $status === 'rejected'
                       ? 'background:#059669;color:#ffffff;box-shadow:0 1px 3px rgba(5,150,105,0.25);'
                       : 'background:transparent;color:#64748b;' ?>padding:0.5rem 1.1rem;border-radius:9px;font-size:0.85rem;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:0.5rem;transition:all 0.15s ease;">
                    <span>Declined</span>
                    <span style="<?= $status === 'rejected' ? 'background:rgba(255,255,255,0.25);color:#ffffff;' : 'background:#e2e8f0;color:#475569;' ?>padding:1px 7px;border-radius:9999px;font-size:0.75rem;font-weight:700;">
                        <?= (int) ($counts['rejected'] ?? 0) ?>
                    </span>
                </a>
            </div>

            <!-- Requests List -->
            <?php if (empty($requests)): ?>
                <div class="empty-state" style="background:#ffffff;border:1px dashed #cbd5e1;border-radius:16px;padding:3.5rem 1.5rem;text-align:center;">
                    <div style="font-size:3rem;margin-bottom:0.75rem;">📦</div>
                    <h3 style="font-size:1.15rem;font-weight:700;color:#0f172a;margin-bottom:0.4rem;">No Package Requests Found</h3>
                    <p style="color:#64748b;font-size:0.9rem;max-width:500px;margin:0 auto;">
                        <?= $status === 'pending' ? 'You have no pending package inclusion requests at this time. When tour guides select your listings for their travel packages, requests will appear here.' : 'No requests match your selected filter.' ?>
                    </p>
                </div>
            <?php else: ?>
                <div style="display:flex;flex-direction:column;gap:1.25rem;">
                    <?php foreach ($requests as $req):
                        $reqId = (int) $req['id'];
                        $reqStatus = strtolower((string) ($req['status'] ?? 'pending'));
                        $isAgreed = ($reqStatus === 'agreed' || $reqStatus === 'approved');
                        $isRejected = ($reqStatus === 'rejected');
                        $isPending = (!$isAgreed && !$isRejected);

                        $serviceType = (string) ($req['service_type'] ?? 'service');
                        $pkgTitle = (string) ($req['package_title'] ?? 'Travel Package');
                        $pkgCover = (string) ($req['package_cover'] ?? 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=800&q=80');
                        $pkgArea = (string) ($req['package_area'] ?? 'Cebu');
                        $pkgDuration = (string) ($req['package_duration'] ?? 'Multi-day');
                        $guideName = (string) ($req['guide_name'] ?? 'Licensed Tour Guide');
                        $guideEmail = (string) ($req['guide_email'] ?? '');
                        $guidePhone = (string) ($req['guide_phone'] ?? '');

                        // Service specific item label
                        if ($serviceType === 'hotel') {
                            $serviceName = !empty($req['room_name'])
                                ? "{$req['room_name']} at {$req['hotel_name']}"
                                : "Hotel Accommodation";
                            $serviceRateLabel = money((float) ($req['agreed_rate'] ?? $req['price_per_night'] ?? 0)) . ' / night';
                            $serviceIcon = '🏨';
                        } else {
                            $vName = trim(($req['vehicle_make'] ?? '') . ' ' . ($req['vehicle_model'] ?? ''));
                            $serviceName = $vName !== '' ? $vName : "Partner Vehicle";
                            $serviceRateLabel = money((float) ($req['agreed_rate'] ?? $req['vehicle_daily_rate'] ?? 0)) . ' / day';
                            $serviceIcon = '🛵';
                        }

                        // 1. Clean and parse description/itinerary for preview modal
                        $rawDesc = (string) ($req['package_description'] ?? '');
                        $cleanDesc = str_replace(["\r\n", "\r"], "\n", $rawDesc);
                        $cleanDesc = preg_replace('/<!--[\s\S]*?-->/', '', $cleanDesc);

                        $itineraryDays = [];
                        if (preg_match('/--- Day-by-Day Itinerary ---\s*([\s\S]*?)(?=\n+--- Policies|\n+Experience Tags:|$)/s', $cleanDesc, $m)) {
                            $itineraryRaw = trim($m[1]);
                            $cleanDesc = trim(str_replace($m[0], '', $cleanDesc));
                            $decoded = json_decode($itineraryRaw, true);
                            if (is_array($decoded)) {
                                foreach ($decoded as $d) {
                                    $itineraryDays[] = [
                                        'day' => (int) ($d['day'] ?? (count($itineraryDays) + 1)),
                                        'title' => (string) ($d['title'] ?? ('Day ' . (count($itineraryDays) + 1))),
                                        'desc' => (string) ($d['desc'] ?? ($d['description'] ?? '')),
                                        'schedule' => is_array($d['schedule'] ?? null) ? $d['schedule'] : [],
                                        'highlights' => is_array($d['highlights'] ?? null) ? $d['highlights'] : [],
                                    ];
                                }
                            }
                        }

                        $cleanDesc = preg_replace('/\n*--- Policies & Requirements ---[\s\S]*$/', '', $cleanDesc);
                        $cleanDesc = preg_replace('/Experience Tags:\s*.*$/mi', '', $cleanDesc);
                        $cleanDesc = trim($cleanDesc);
                        if ($cleanDesc === '') {
                            $cleanDesc = 'Curated all-in-one travel package bundled by licensed tour guide host.';
                        }

                        // 2. Clean and deduplicate inclusions
                        $rawIncluded = (string) ($req['package_included'] ?? '');
                        $rawIncluded = str_replace(["\r\n", "\r"], "\n", $rawIncluded);
                        $rawIncluded = preg_replace('/<!--[\s\S]*?-->/', '', $rawIncluded);
                        $incLines = array_filter(array_map('trim', explode("\n", $rawIncluded)));
                        $parsedActivities = [];
                        $parsedAccommodation = '';
                        $parsedTransportation = '';
                        $parsedPerks = [];

                        foreach ($incLines as $iLine) {
                            if (preg_match('/^Activities:\s*(.*)$/i', $iLine, $am)) {
                                $acts = array_filter(array_map('trim', explode(',', $am[1])));
                                foreach ($acts as $a) {
                                    if (!in_array($a, $parsedActivities, true)) {
                                        $parsedActivities[] = $a;
                                    }
                                }
                            } elseif (preg_match('/^Accommodation:\s*(.*)$/i', $iLine, $hm)) {
                                $acc = trim($hm[1]);
                                if ($parsedAccommodation === '' || stripos($parsedAccommodation, 'none') !== false) {
                                    $parsedAccommodation = $acc;
                                }
                            } elseif (preg_match('/^Transportation:\s*(.*)$/i', $iLine, $tm)) {
                                $trans = trim($tm[1]);
                                if ($parsedTransportation === '' || stripos($parsedTransportation, 'none') !== false) {
                                    $parsedTransportation = $trans;
                                }
                            } else {
                                if (str_starts_with($iLine, '<!--') || str_starts_with($iLine, '-->') || str_contains($iLine, 'PACKAGE_SERVICES')) {
                                    continue;
                                }
                                if (!in_array($iLine, $parsedPerks, true)) {
                                    $parsedPerks[] = $iLine;
                                }
                            }
                        }
                    ?>
                        <div class="pkg-request-card" style="background:#ffffff;border:1px solid #e2e8f0;border-radius:14px;padding:1.5rem;box-shadow:0 1px 3px rgba(0,0,0,0.04);transition:border-color 0.2s;">
                            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap;margin-bottom:1rem;border-bottom:1px solid #f1f5f9;padding-bottom:1rem;">
                                <div style="display:flex;gap:1rem;align-items:center;">
                                    <img src="<?= e(img_src($pkgCover, 'pkg-cover-' . $req['pkg_id'])) ?>" alt="" style="width:72px;height:72px;border-radius:10px;object-fit:cover;flex-shrink:0;" onerror="this.src='https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=400&q=80'">
                                    <div>
                                        <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.25rem;">
                                            <span style="background:#ecfdf5;color:#047857;border:1px solid #a7f3d0;font-size:0.75rem;padding:2px 8px;border-radius:12px;font-weight:700;">
                                                Travel Package
                                            </span>
                                            <span style="font-size:0.8rem;color:#64748b;">📍 <?= e($pkgArea) ?> • ⏱️ <?= e($pkgDuration) ?></span>
                                        </div>
                                        <h3 style="margin:0 0 0.25rem 0;font-size:1.05rem;font-weight:800;color:#0f172a;line-height:1.3;"><?= e($pkgTitle) ?></h3>
                                        <div style="font-size:0.825rem;color:#475569;">
                                            <strong>Guide Host:</strong> <?= e($guideName) ?>
                                            <?php if ($guideEmail): ?> • <span class="hint"><?= e($guideEmail) ?></span><?php endif; ?>
                                            <?php if ($guidePhone): ?> • <span class="hint"><?= e($guidePhone) ?></span><?php endif; ?>
                                            <?php if (!empty($req['guide_id_doc'])): ?>
                                                • <a href="<?= e(url($req['guide_id_doc'])) ?>" target="_blank" rel="noopener" style="color:#2563eb;font-weight:600;">📎 Tour Guide ID ↗</a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Status Pill -->
                                <div>
                                    <?php if ($isAgreed): ?>
                                        <span style="display:inline-flex;align-items:center;gap:0.35rem;background:#f0fdf4;color:#15803d;border:1px solid #86efac;font-size:0.825rem;padding:0.4rem 0.9rem;border-radius:20px;font-weight:700;">
                                            ✓ You Agreed to Inclusion
                                        </span>
                                    <?php elseif ($isRejected): ?>
                                        <span style="display:inline-flex;align-items:center;gap:0.35rem;background:#fef2f2;color:#b91c1c;border:1px solid #fca5a5;font-size:0.825rem;padding:0.4rem 0.9rem;border-radius:20px;font-weight:700;">
                                            ✕ Declined by You
                                        </span>
                                    <?php else: ?>
                                        <span style="display:inline-flex;align-items:center;gap:0.35rem;background:#fefce8;color:#a16207;border:1px solid #fde047;font-size:0.825rem;padding:0.4rem 0.9rem;border-radius:20px;font-weight:700;">
                                            ⏳ Pending Your Response
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Service Details Box -->
                            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:1rem 1.25rem;margin-bottom:1rem;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.75rem;">
                                <div>
                                    <div style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.05em;color:#64748b;font-weight:700;margin-bottom:0.25rem;">
                                        Requested Service:
                                    </div>
                                    <div style="font-size:0.95rem;font-weight:800;color:#0f172a;display:flex;align-items:center;gap:0.4rem;">
                                        <span><?= $serviceIcon ?></span> <?= e($serviceName) ?>
                                    </div>
                                    <div style="font-size:0.8rem;color:#64748b;margin-top:2px;">
                                        Agreed Rate: <strong style="color:#059669;"><?= $serviceRateLabel ?></strong> (per guest/day reservation)
                                    </div>
                                </div>
                                <div>
                                    <button type="button" class="btn btn-ghost btn-sm" onclick="openPackageInspectionModal(<?= $reqId ?>)">
                                        🔍 Inspect Full Package & Itinerary
                                    </button>
                                </div>
                            </div>

                            <?php if ($isRejected && !empty($req['rejection_reason'])): ?>
                                <div style="background:#fff1f2;border-left:4px solid #f43f5e;padding:0.6rem 0.9rem;border-radius:6px;font-size:0.825rem;color:#9f1239;margin-bottom:1rem;">
                                    <strong>Decline Reason Provided:</strong> <?= e($req['rejection_reason']) ?>
                                </div>
                            <?php endif; ?>

                            <!-- Actions Row -->
                            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.75rem;">
                                <div style="font-size:0.8rem;color:#64748b;">
                                    Request received: <?= e(date('M j, Y g:ia', strtotime((string) $req['created_at']))) ?>
                                </div>

                                <div style="display:flex;gap:0.6rem;align-items:center;">
                                    <?php if ($isPending || $isRejected): ?>
                                        <form method="post" action="<?= e(url('/dashboard/package-requests/' . $reqId . '/agree')) ?>" style="margin:0;">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-primary btn-sm" style="background:#10b981;border-color:#10b981;font-weight:700;">
                                                ✓ Agree to Inclusion
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($isPending): ?>
                                        <button type="button" class="btn btn-ghost btn-sm" style="color:#ef4444;border-color:#fca5a5;font-weight:700;" onclick="openDeclineModal(<?= $reqId ?>, '<?= e(addslashes($pkgTitle)) ?>')">
                                            ✕ Decline Request
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Modal for Inspecting Package Details -->
                        <div id="pkgModal_<?= $reqId ?>" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.6);z-index:9999;align-items:center;justify-content:center;padding:1rem;">
                            <div style="background:#ffffff;border-radius:16px;max-width:680px;width:100%;max-height:85vh;overflow-y:auto;padding:2rem;box-shadow:0 20px 25px -5px rgba(0,0,0,0.1);position:relative;">
                                <button type="button" onclick="closePackageInspectionModal(<?= $reqId ?>)" style="position:absolute;top:1.25rem;right:1.25rem;background:transparent;border:none;font-size:1.25rem;cursor:pointer;color:#64748b;">✕</button>

                                <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.5rem;">
                                    <span style="background:#ecfdf5;color:#047857;border:1px solid #a7f3d0;font-size:0.75rem;padding:2px 8px;border-radius:12px;font-weight:700;">Travel Package</span>
                                    <span style="font-size:0.825rem;color:#64748b;">⏱️ <?= e($pkgDuration) ?></span>
                                </div>
                                <h2 style="font-size:1.25rem;font-weight:800;color:#0f172a;margin:0 0 1rem 0;"><?= e($pkgTitle) ?></h2>

                                <div style="background:#f8fafc;padding:1rem;border-radius:10px;margin-bottom:1.25rem;border:1px solid #e2e8f0;">
                                    <h4 style="margin:0 0 0.35rem 0;font-size:0.9rem;font-weight:700;color:#0f172a;">Guide Information:</h4>
                                    <div style="font-size:0.85rem;color:#475569;">
                                        <strong>Name:</strong> <?= e($guideName) ?><br>
                                        <strong>Email:</strong> <?= e($guideEmail) ?><br>
                                        <strong>Phone:</strong> <?= e($guidePhone ?: 'Not provided') ?><br>
                                        <?php if (!empty($req['guide_id_doc'])): ?>
                                            <strong>Supporting ID:</strong> <a href="<?= e(url($req['guide_id_doc'])) ?>" target="_blank" rel="noopener" style="color:#2563eb;font-weight:600;">📎 View Tour Guide Valid ID ↗</a>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div style="margin-bottom:1.25rem;">
                                    <h4 style="margin:0 0 0.5rem 0;font-size:0.9rem;font-weight:700;color:#0f172a;">Package Overview & Description:</h4>
                                    <div style="font-size:0.85rem;color:#334155;line-height:1.6;background:#ffffff;border:1px solid #e2e8f0;padding:0.85rem;border-radius:10px;"><?= nl2br(e($cleanDesc)) ?></div>
                                </div>

                                <?php if (!empty($itineraryDays)): ?>
                                    <div style="margin-bottom:1.25rem;">
                                        <h4 style="margin:0 0 0.5rem 0;font-size:0.9rem;font-weight:700;color:#0f172a;">Day-by-Day Itinerary:</h4>
                                        <div style="display:flex;flex-direction:column;gap:0.75rem;max-height:240px;overflow-y:auto;padding-right:4px;">
                                            <?php foreach ($itineraryDays as $day): ?>
                                                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:0.85rem;">
                                                    <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.35rem;">
                                                        <span style="background:#059669;color:#ffffff;font-size:0.7rem;font-weight:700;padding:2px 7px;border-radius:8px;">Day <?= (int) $day['day'] ?></span>
                                                        <strong style="font-size:0.85rem;color:#0f172a;"><?= e($day['title']) ?></strong>
                                                    </div>
                                                    <?php if (!empty($day['desc'])): ?>
                                                        <p style="font-size:0.8rem;color:#475569;margin:0 0 0.4rem 0;line-height:1.4;"><?= e($day['desc']) ?></p>
                                                    <?php endif; ?>
                                                    <?php if (!empty($day['schedule'])): ?>
                                                        <div style="display:flex;flex-direction:column;gap:0.25rem;margin-bottom:0.4rem;">
                                                            <?php foreach ($day['schedule'] as $slot): ?>
                                                                <div style="font-size:0.78rem;color:#334155;display:flex;align-items:baseline;gap:0.4rem;">
                                                                    <span style="background:#e2e8f0;color:#1e293b;padding:1px 6px;border-radius:4px;font-weight:600;font-size:0.72rem;"><?= e((string)($slot['time'] ?? '')) ?></span>
                                                                    <span><?= e((string)($slot['activity'] ?? '')) ?></span>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($day['highlights'])): ?>
                                                        <div style="display:flex;flex-wrap:wrap;gap:0.35rem;">
                                                            <?php foreach ($day['highlights'] as $hl): ?>
                                                                <span style="background:#ecfdf5;color:#047857;border:1px solid #a7f3d0;padding:2px 7px;border-radius:6px;font-size:0.72rem;font-weight:600;">✓ <?= e((string)$hl) ?></span>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div style="margin-bottom:1.5rem;">
                                    <h4 style="margin:0 0 0.5rem 0;font-size:0.9rem;font-weight:700;color:#0f172a;">Inclusions & Included Perks:</h4>
                                    <div style="background:#f8fafc;padding:1rem;border-radius:10px;border:1px solid #e2e8f0;display:flex;flex-direction:column;gap:0.6rem;font-size:0.85rem;">
                                        <?php if (!empty($parsedActivities)): ?>
                                            <div>
                                                <strong style="color:#0f172a;display:block;margin-bottom:0.25rem;">Activities & Attractions:</strong>
                                                <div style="display:flex;flex-wrap:wrap;gap:0.35rem;">
                                                    <?php foreach ($parsedActivities as $act): ?>
                                                        <span style="background:#e0f2fe;color:#0369a1;border:1px solid #bae6fd;padding:2px 8px;border-radius:6px;font-size:0.75rem;font-weight:600;">📍 <?= e($act) ?></span>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($parsedAccommodation !== ''): ?>
                                            <div>
                                                <strong style="color:#0f172a;">Accommodation:</strong> <span style="color:#475569;"><?= e($parsedAccommodation) ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($parsedTransportation !== ''): ?>
                                            <div>
                                                <strong style="color:#0f172a;">Transportation:</strong> <span style="color:#475569;"><?= e($parsedTransportation) ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($parsedPerks)): ?>
                                            <div>
                                                <strong style="color:#0f172a;display:block;margin-bottom:0.25rem;">Included Perks:</strong>
                                                <ul style="margin:0;padding-left:1.2rem;color:#475569;">
                                                    <?php foreach ($parsedPerks as $perk): ?>
                                                        <li style="margin-bottom:0.2rem;"><?= e($perk) ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div style="display:flex;justify-content:flex-end;gap:0.75rem;border-top:1px solid #e2e8f0;padding-top:1rem;">
                                    <button type="button" class="btn btn-ghost" onclick="closePackageInspectionModal(<?= $reqId ?>)">Close</button>
                                    <?php if ($isPending): ?>
                                        <form method="post" action="<?= e(url('/dashboard/package-requests/' . $reqId . '/agree')) ?>" style="margin:0;">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-primary" style="background:#10b981;border-color:#10b981;font-weight:700;">
                                                ✓ Agree to Inclusion
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Decline Modal -->
<div id="declineModal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.6);z-index:99999;align-items:center;justify-content:center;padding:1rem;">
    <div style="background:#ffffff;border-radius:16px;max-width:480px;width:100%;padding:1.75rem;box-shadow:0 20px 25px -5px rgba(0,0,0,0.1);position:relative;">
        <h3 style="margin:0 0 0.5rem 0;font-size:1.15rem;font-weight:800;color:#0f172a;">Decline Package Inclusion</h3>
        <p style="font-size:0.85rem;color:#64748b;margin:0 0 1rem 0;" id="declineModalPkgTitle">Are you sure you want to decline this request?</p>

        <form method="post" id="declineForm" action="">
            <?= csrf_field() ?>
            <div style="margin-bottom:1rem;">
                <label style="display:block;font-size:0.85rem;font-weight:700;color:#334155;margin-bottom:0.35rem;">
                    Reason for declining (optional):
                </label>
                <textarea name="reason" class="input" rows="3" placeholder="e.g., Dates not available, fully booked, rate renegotiation needed..." style="width:100%;font-size:0.85rem;padding:0.6rem;"></textarea>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:0.75rem;">
                <button type="button" class="btn btn-ghost" onclick="closeDeclineModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" style="background:#ef4444;border-color:#ef4444;font-weight:700;">Confirm Decline</button>
            </div>
        </form>
    </div>
</div>

<script>
function openPackageInspectionModal(id) {
    const el = document.getElementById('pkgModal_' + id);
    if (el) el.style.display = 'flex';
}
function closePackageInspectionModal(id) {
    const el = document.getElementById('pkgModal_' + id);
    if (el) el.style.display = 'none';
}
function openDeclineModal(reqId, title) {
    const form = document.getElementById('declineForm');
    const titleEl = document.getElementById('declineModalPkgTitle');
    if (form) form.action = '<?= url('/dashboard/package-requests/') ?>' + reqId + '/reject';
    if (titleEl) titleEl.innerText = 'Declining inclusion in "' + title + '". The tour guide will be notified.';
    const modal = document.getElementById('declineModal');
    if (modal) modal.style.display = 'flex';
}
function closeDeclineModal() {
    const modal = document.getElementById('declineModal');
    if (modal) modal.style.display = 'none';
}
</script>
