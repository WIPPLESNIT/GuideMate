<?php
/** @var array<int,array<string,mixed>> $requests @var bool $isWarned @var string $warningNote */
$isWarned = $isWarned ?? \App\Models\User::isGuideWarned((int) (auth_user()['id'] ?? 0));
$warningNote = $warningNote ?? (string) (auth_user()['guide_warning_note'] ?? '');
?>
<div class="container">
    <div class="dash-layout">
        <?= \App\Core\View::partial('partials/dash-nav') ?>
        <div>
            <div class="section-head" style="margin-bottom:1rem;">
                <div>
                    <h1 class="mb-0">Rental requests</h1>
                    <p class="hint mb-0">Paid reservations stay awaiting confirmation until you confirm or decline them.</p>
                </div>
            </div>

            <?php if ($isWarned): ?>
                <div class="status-banner banner-red" style="margin-bottom:1.2rem;">
                    <span class="status-icon">⚠️</span>
                    <div>
                        <strong>Administrator warning — booking actions restricted</strong>
                        <p class="mb-0">
                            <?= $warningNote !== '' ? e($warningNote) . ' — ' : '' ?>
                            Your rental actions (Confirm, Complete, Cancel, and Refund) are restricted until an administrator clears this warning.
                            <a href="<?= e(url('/messages')) ?>">View administrator message →</a>
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($requests === []): ?>
                <div class="panel">
                    <div class="panel-body">
                        <div class="empty-state" style="padding:2.5rem 1rem;">
                            <p>No rental requests yet. Requests from the mobile app will appear here.</p>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="panel">
                    <div class="panel-body rental-requests-wrap" style="padding:0;">
                        <table class="table rental-requests-table">
                            <thead>
                                <tr>
                                    <th class="col-vehicle">Vehicle</th>
                                    <th class="col-customer">Customer</th>
                                    <th class="col-pickup">Pickup</th>
                                    <th class="col-payment">Payment</th>
                                    <th class="col-id-doc">Verification ID</th>
                                    <th class="col-status">Status</th>
                                    <th class="col-actions">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($requests as $r):
                                $paymentStatus = strtolower(trim((string) ($r['payment_status'] ?? 'unpaid')));
                                $reportStatus = strtolower(trim((string) ($r['report_status'] ?? 'none')));
                                $reqStatus = strtolower(trim((string) ($r['status'] ?? 'pending')));
                                $isPending = $reqStatus === 'pending';
                                $isRefunded = in_array('refunded', [$reqStatus, $paymentStatus, $reportStatus], true);
                            ?>
                                <tr class="<?= $isPending ? 'row-pending' : '' ?>"<?= $isPending ? ' style="background:#FFFBEB;"' : '' ?>>
                                    <td class="col-vehicle">
                                        <?php
                                        $isPkgReq = !empty($r['package_booking_ref']);
                                        $vName = !empty($r['vehicle_name']) && $r['vehicle_name'] !== 'Travel Package'
                                            ? (string) $r['vehicle_name']
                                            : (!empty($r['vehicle_db_name'])
                                                ? (string) $r['vehicle_db_name']
                                                : trim(($r['vehicle_make'] ?? '') . ' ' . ($r['vehicle_model'] ?? '')));
                                        if ($vName === '') {
                                            $vName = 'Partner Vehicle';
                                        }
                                        $vType = !empty($r['vehicle_type']) ? (string) $r['vehicle_type'] : (!empty($r['vehicle_type_db']) ? (string) $r['vehicle_type_db'] : 'Scooter');
                                        ?>
                                        <?php if ($isPkgReq): ?>
                                            <strong class="vehicle-title"><?= e($vName) ?></strong>
                                            <div style="font-size:0.75rem;font-weight:700;color:var(--brand, #059669);margin-top:2px;display:flex;align-items:center;gap:0.35rem;">
                                                <span style="background:#ecfdf5;color:#047857;border:1px solid #a7f3d0;font-size:0.7rem;padding:1px 6px;border-radius:10px;font-weight:700;">Travel Package</span>
                                                <span class="hint" style="color:#64748b;font-weight:500;font-size:0.75rem;">· <?= e($vType) ?></span>
                                            </div>
                                        <?php else: ?>
                                            <strong class="vehicle-title"><?= e($vName) ?></strong>
                                            <div class="hint" style="font-size:0.8rem;margin-top:2px;">
                                                <?= e($vType) ?> · <?= e($r['shop_name']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-customer">
                                        <strong class="customer-name"><?= e($r['customer_name']) ?></strong>
                                        <div class="customer-email"><?= e($r['customer_email']) ?></div>
                                        <?php if (!empty($r['customer_phone'])): ?>
                                            <div class="hint" style="font-size:0.78rem;"><?= e((string) $r['customer_phone']) ?></div>
                                        <?php endif; ?>
                                        <?php if (empty($r['package_booking_ref']) && !empty($r['notes'])): ?>
                                            <button type="button" 
                                                    class="rental-note-btn" 
                                                    data-open-note-modal
                                                    data-req-id="<?= (int) $r['id'] ?>"
                                                    data-customer="<?= e($r['customer_name']) ?>"
                                                    data-vehicle="<?= e($r['vehicle_name']) ?>"
                                                    data-pickup="<?= e(date('M j, Y', strtotime((string) $r['pickup_date']))) ?>"
                                                    data-note="<?= e((string) $r['notes']) ?>"
                                                    title="Tourist note: <?= e((string) $r['notes']) ?>">
                                                <span class="note-btn-icon">💬</span>
                                                <span class="note-btn-label">View note</span>
                                                <span class="note-btn-snippet">"<?= e(mb_strimwidth((string)$r['notes'], 0, 18, '...')) ?>"</span>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-pickup">
                                        <strong style="display:block;font-size:0.88rem;"><?= e(date('M j, Y', strtotime((string) $r['pickup_date']))) ?></strong>
                                        <small class="hint"><?= (int) $r['rental_days'] ?> day(s)</small>
                                    </td>
                                    <td class="col-payment">
                                        <?php
                                        $rentalDays = max(1, (int) ($r['rental_days'] ?? 1));
                                        $dailyRate = (float) ($r['vehicle_daily_rate'] ?? 0);
                                        $payAmount = (float) ($r['total_amount'] ?? 0);
                                        if ($isPkgReq && $dailyRate > 0) {
                                            $payAmount = round($dailyRate * $rentalDays, 2);
                                        }
                                        ?>
                                        <strong style="color:var(--brand, #059669);display:block;font-size:0.95rem;"><?= money($payAmount) ?></strong>
                                        <?php if ($dailyRate > 0): ?>
                                            <small class="hint d-block" style="font-size:0.72rem;color:#64748b;margin-top:1px;">
                                                <?= money($dailyRate) ?> × <?= $rentalDays ?> day<?= $rentalDays > 1 ? 's' : '' ?>
                                            </small>
                                        <?php endif; ?>
                                        <div style="margin-top:2px;">
                                            <?php if ($paymentStatus === 'paid'): ?>
                                                <span class="pill pill-approved" style="font-size:0.72rem;padding:2px 8px;">Paid</span>
                                                <small class="hint d-block" style="font-size:0.72rem;margin-top:2px;"><?= e(ucfirst((string) ($r['payment_method'] ?? ''))) ?></small>
                                            <?php elseif ($paymentStatus === 'refunded'): ?>
                                                <span class="pill pill-cancelled" style="font-size:0.72rem;padding:2px 8px;">Refunded</span>
                                            <?php else: ?>
                                                <span class="pill pill-pending" style="font-size:0.72rem;padding:2px 8px;">Unpaid</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="col-id-doc">
                                        <?php
                                        $idDoc = !empty($r['guide_id_doc'])
                                            ? $r['guide_id_doc']
                                            : (!empty($r['fallback_guide_id_doc'])
                                                ? $r['fallback_guide_id_doc']
                                                : (!empty($r['id_document']) ? $r['id_document'] : ''));
                                        $isGuideId = !empty($r['guide_id_doc']) || !empty($r['fallback_guide_id_doc']);
                                        $idLabel = $isGuideId ? 'Tour Guide ID' : (string) ($r['id_type'] ?? 'ID');
                                        $guideName = (string) ($r['customer_name'] ?? 'Tour Guide Host');
                                        ?>
                                        <?php if (!empty($idDoc)): ?>
                                            <div class="rental-id-doc-wrap" style="display:flex;flex-direction:column;align-items:flex-start;gap:3px;">
                                                <a href="<?= e(url($idDoc)) ?>"
                                                   target="_blank"
                                                   rel="noopener"
                                                   class="rental-id-doc-link doc-link"
                                                   data-doc-preview="1"
                                                   data-doc-url="<?= e(url($idDoc)) ?>"
                                                   data-doc-label="<?= e($idLabel) ?>"
                                                   data-doc-type="Document"
                                                   data-doc-status="pending"
                                                   data-doc-owner="<?= e($guideName) ?>">
                                                    <span>📎</span>
                                                    <span><?= e($idLabel) ?></span>
                                                    <span style="font-size:0.75rem;opacity:0.75;">↗</span>
                                                </a>
                                                <?php if (!empty($r['id_number'])): ?>
                                                    <div class="hint" style="font-size:0.75rem;margin-top:2px;">No. <?= e((string) $r['id_number']) ?></div>
                                                <?php endif; ?>
                                                <div class="hint" style="font-size:0.72rem;color:<?= $isGuideId ? '#0284c7' : 'var(--ink-400)' ?>;">
                                                    <?= $isGuideId ? 'Guide verification document' : 'Tourist valid ID' ?>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div style="display:flex;flex-direction:column;align-items:flex-start;gap:2px;">
                                                <span style="color:#94a3b8;font-size:0.85rem;font-weight:600;">—</span>
                                                <small class="hint" style="font-size:0.72rem;color:#94a3b8;">No document provided</small>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-status">
                                        <div class="status-pill-wrap">
                                            <span class="pill pill-<?= e($reqStatus) ?>">
                                                <?= e(\App\Models\RentalRequest::statusLabel($reqStatus)) ?>
                                            </span>
                                            <?php if ($reportStatus === 'open'): ?>
                                                <span class="pill pill-cancelled" style="font-size:0.72rem;padding:3px 9px;" title="<?= e((string) ($r['report_message'] ?? '')) ?>">
                                                    ⚠ Problem Reported
                                                </span>
                                            <?php elseif ($reportStatus === 'refunded'): ?>
                                                <span class="pill pill-cancelled" style="font-size:0.7rem;padding:2px 7px;">Refunded after report</span>
                                            <?php elseif ($reportStatus === 'rejected'): ?>
                                                <span class="pill pill-pending" style="font-size:0.7rem;padding:2px 7px;">Report dismissed</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="col-actions">
                                        <div class="res-action-wrap">
                                            <button type="button"
                                                        class="btn-res-action-trigger"
                                                        id="rentalReqBtn_<?= (int) $r['id'] ?>"
                                                        aria-haspopup="true"
                                                        aria-expanded="false"
                                                        aria-controls="rentalReqMenu_<?= (int) $r['id'] ?>"
                                                        title="Actions for request #<?= (int) $r['id'] ?>"
                                                        aria-label="Actions for request #<?= (int) $r['id'] ?>">
                                                    <?= admin_icon('more-vertical', 16) ?>
                                                </button>
                                                <div class="res-action-dropdown"
                                                     id="rentalReqMenu_<?= (int) $r['id'] ?>"
                                                     role="menu"
                                                     aria-labelledby="rentalReqBtn_<?= (int) $r['id'] ?>">
                                                    
                                                    <?php if ($isRefunded): ?>
                                                        <button type="button" class="res-action-item action-delete" data-delete-rental data-delete-report data-id="<?= (int) $r['id'] ?>" data-url="<?= e(url('/dashboard/rentals/' . $r['id'] . '/delete')) ?>" role="menuitem" style="color:#DC2626;display:flex;align-items:center;gap:6px;">
                                                            <?= admin_icon('trash', 14) ?> Delete Report
                                                        </button>
                                                    <?php elseif (in_array($reqStatus, ['completed', 'cancelled'], true)): ?>
                                                        <button type="button" class="res-action-item action-delete" data-delete-rental data-id="<?= (int) $r['id'] ?>" data-url="<?= e(url('/dashboard/rentals/' . $r['id'] . '/delete')) ?>" role="menuitem" style="color:#DC2626;display:flex;align-items:center;gap:6px;">
                                                            <?= admin_icon('trash', 14) ?> Delete Record
                                                        </button>
                                                    <?php else: ?>
                                                        <?php if ($isWarned): ?>
                                                            <div class="res-action-restricted-notice" title="Action unavailable: Active administrator warning on your account.">
                                                                <span class="res-action-restricted-badge"><?= admin_icon('alert-triangle', 13) ?> Actions Restricted</span>
                                                                <span>Unavailable due to an active administrator warning.</span>
                                                            </div>
                                                            <?php if ($reportStatus === 'open'):
                                                                $rFiles = \App\Models\RentalReportFile::forRental((int) $r['id']);
                                                                $rPhotos = array_map(fn($f) => img_src($f['file_path'], 'rental-evidence'), $rFiles);
                                                            ?>
                                                                <button type="button"
                                                                        class="res-action-item action-problem"
                                                                        role="menuitem"
                                                                        data-open-problem-modal
                                                                        data-req-id="<?= (int) $r['id'] ?>"
                                                                        data-customer="<?= e($r['customer_name']) ?>"
                                                                        data-phone="<?= e((string)($r['customer_phone'] ?? '')) ?>"
                                                                        data-vehicle="<?= e($r['vehicle_name']) ?>"
                                                                        data-pickup="<?= e(date('M j, Y', strtotime((string)$r['pickup_date']))) ?>"
                                                                        data-amount="<?= money((float) $r['total_amount']) ?>"
                                                                        data-message="<?= e((string)($r['report_message'] ?? '')) ?>"
                                                                        data-photos="<?= e(json_encode($rPhotos)) ?>"
                                                                        data-refund-url="<?= e(url('/dashboard/rentals/' . $r['id'] . '/refund')) ?>"
                                                                        data-reject-url="<?= e(url('/dashboard/rentals/' . $r['id'] . '/reject-report')) ?>">
                                                                    <?= admin_icon('disputes', 14) ?> View Reported Problem
                                                                </button>
                                                                <button type="button" class="res-action-item is-disabled action-cancel" disabled title="Unavailable: Active administrator warning on your account." role="menuitem">
                                                                    <?= admin_icon('revenue', 14) ?> Refund Customer
                                                                </button>
                                                                <div style="border-top:1px solid var(--line, #e2e8f0);margin:2px 0;"></div>
                                                            <?php endif; ?>

                                                            <?php if ($isPending): ?>
                                                                <button type="button" class="res-action-item is-disabled action-accept" disabled title="Unavailable: Active administrator warning on your account." role="menuitem">
                                                                    <?= admin_icon('check-circle', 14) ?> Confirm Booking
                                                                </button>
                                                                <button type="button" class="res-action-item is-disabled action-cancel" disabled title="Unavailable: Active administrator warning on your account." role="menuitem">
                                                                    <?= admin_icon('x-circle', 14) ?> Decline Booking
                                                                </button>
                                                            <?php else: ?>
                                                                <?php if ($reqStatus !== 'completed' && $reqStatus !== 'cancelled'): ?>
                                                                    <button type="button" class="res-action-item is-disabled action-accept" disabled title="Unavailable: Active administrator warning on your account." role="menuitem">
                                                                        <?= admin_icon('check-circle', 14) ?> Complete Rental
                                                                    </button>
                                                                    <button type="button" class="res-action-item is-disabled action-cancel" disabled title="Unavailable: Active administrator warning on your account." role="menuitem">
                                                                        <?= admin_icon('x-circle', 14) ?> Cancel Rental
                                                                    </button>
                                                                <?php endif; ?>
                                                            <?php endif; ?>

                                                            <?php if ($paymentStatus === 'paid' && !in_array($reqStatus, ['cancelled', 'refunded'], true) && $reportStatus !== 'open'): ?>
                                                                <div style="border-top:1px solid var(--line, #e2e8f0);margin:2px 0;"></div>
                                                                <button type="button" class="res-action-item is-disabled action-cancel" disabled title="Unavailable: Active administrator warning on your account." role="menuitem">
                                                                    <?= admin_icon('revenue', 14) ?> Refund Payment
                                                                </button>
                                                            <?php endif; ?>

                                                        <?php else: ?>
                                                            <?php if ($reportStatus === 'open'):
                                                                $rFiles = \App\Models\RentalReportFile::forRental((int) $r['id']);
                                                                $rPhotos = array_map(fn($f) => img_src($f['file_path'], 'rental-evidence'), $rFiles);
                                                            ?>
                                                                <button type="button"
                                                                        class="res-action-item action-problem"
                                                                        role="menuitem"
                                                                        data-open-problem-modal
                                                                        data-req-id="<?= (int) $r['id'] ?>"
                                                                        data-customer="<?= e($r['customer_name']) ?>"
                                                                        data-phone="<?= e((string)($r['customer_phone'] ?? '')) ?>"
                                                                        data-vehicle="<?= e($r['vehicle_name']) ?>"
                                                                        data-pickup="<?= e(date('M j, Y', strtotime((string)$r['pickup_date']))) ?>"
                                                                        data-amount="<?= money((float) $r['total_amount']) ?>"
                                                                        data-message="<?= e((string)($r['report_message'] ?? '')) ?>"
                                                                        data-photos="<?= e(json_encode($rPhotos)) ?>"
                                                                        data-refund-url="<?= e(url('/dashboard/rentals/' . $r['id'] . '/refund')) ?>"
                                                                        data-reject-url="<?= e(url('/dashboard/rentals/' . $r['id'] . '/reject-report')) ?>">
                                                                    <?= admin_icon('disputes', 14) ?> View Reported Problem
                                                                </button>
                                                                <form method="post" action="<?= e(url('/dashboard/rentals/' . $r['id'] . '/refund')) ?>" class="res-action-form" onsubmit="return confirm('Refund full payment of <?= money((float) $r['total_amount']) ?> to the customer?');">
                                                                    <?= csrf_field() ?>
                                                                    <button type="submit" class="res-action-item action-cancel" role="menuitem">
                                                                        <?= admin_icon('revenue', 14) ?> Refund Customer
                                                                    </button>
                                                                </form>
                                                                <form method="post" action="<?= e(url('/dashboard/rentals/' . $r['id'] . '/reject-report')) ?>" class="res-action-form" onsubmit="return confirm('Dismiss this problem report?');">
                                                                    <?= csrf_field() ?>
                                                                    <button type="submit" class="res-action-item action-accept" role="menuitem">
                                                                        <?= admin_icon('check-circle', 14) ?> Dismiss Report
                                                                    </button>
                                                                </form>
                                                                <div style="border-top:1px solid var(--line, #e2e8f0);margin:2px 0;"></div>
                                                            <?php endif; ?>

                                                            <?php if ($isPending): ?>
                                                                <form method="post" action="<?= e(url('/dashboard/rentals/' . $r['id'] . '/status')) ?>" class="res-action-form" onsubmit="return confirm('Confirm and approve this rental booking?');">
                                                                    <?= csrf_field() ?>
                                                                    <input type="hidden" name="status" value="approved">
                                                                    <button type="submit" class="res-action-item action-accept" role="menuitem">
                                                                        <?= admin_icon('check-circle', 14) ?> Confirm Booking
                                                                    </button>
                                                                </form>
                                                                <form method="post" action="<?= e(url('/dashboard/rentals/' . $r['id'] . '/status')) ?>" class="res-action-form" onsubmit="return confirm('Decline this booking? Any payment made will be refunded.');">
                                                                    <?= csrf_field() ?>
                                                                    <input type="hidden" name="status" value="cancelled">
                                                                    <button type="submit" class="res-action-item action-cancel" role="menuitem">
                                                                        <?= admin_icon('x-circle', 14) ?> Decline Booking
                                                                    </button>
                                                                </form>
                                                            <?php else: ?>
                                                                <?php if ($reqStatus !== 'completed' && $reqStatus !== 'cancelled'): ?>
                                                                    <?php if ($reqStatus !== 'contacted'): ?>
                                                                        <form method="post" action="<?= e(url('/dashboard/rentals/' . $r['id'] . '/status')) ?>" class="res-action-form">
                                                                            <?= csrf_field() ?>
                                                                            <input type="hidden" name="status" value="contacted">
                                                                            <button type="submit" class="res-action-item action-confirm" role="menuitem">
                                                                                <?= admin_icon('messages', 14) ?> Mark Contacted
                                                                            </button>
                                                                        </form>
                                                                    <?php endif; ?>
                                                                    <form method="post" action="<?= e(url('/dashboard/rentals/' . $r['id'] . '/status')) ?>" class="res-action-form" onsubmit="return confirm('Mark this rental as completed?');">
                                                                        <?= csrf_field() ?>
                                                                        <input type="hidden" name="status" value="completed">
                                                                        <button type="submit" class="res-action-item action-accept" role="menuitem">
                                                                        <?= admin_icon('check-circle', 14) ?> Complete Rental
                                                                        </button>
                                                                    </form>
                                                                    <form method="post" action="<?= e(url('/dashboard/rentals/' . $r['id'] . '/status')) ?>" class="res-action-form" onsubmit="return confirm('Cancel this reservation?');">
                                                                        <?= csrf_field() ?>
                                                                        <input type="hidden" name="status" value="cancelled">
                                                                        <button type="submit" class="res-action-item action-cancel" role="menuitem">
                                                                            <?= admin_icon('x-circle', 14) ?> Cancel Rental
                                                                        </button>
                                                                    </form>
                                                                <?php endif; ?>
                                                            <?php endif; ?>


                                                            <?php if ($paymentStatus === 'paid' && !in_array($reqStatus, ['cancelled', 'refunded'], true) && $reportStatus !== 'open'): ?>
                                                                <div style="border-top:1px solid var(--line, #e2e8f0);margin:2px 0;"></div>
                                                                <form method="post" action="<?= e(url('/dashboard/rentals/' . $r['id'] . '/refund')) ?>" class="res-action-form" onsubmit="return confirm('Refund the full payment of <?= money((float) $r['total_amount']) ?> for this reservation?');">
                                                                    <?= csrf_field() ?>
                                                                    <button type="submit" class="res-action-item action-cancel" role="menuitem">
                                                                        <?= admin_icon('revenue', 14) ?> Refund Payment
                                                                    </button>
                                                                </form>
                                                            <?php endif; ?>

                                                            <?php if (!empty($idDoc)): ?>
                                                                <div style="border-top:1px solid var(--line, #e2e8f0);margin:2px 0;"></div>
                                                                <a href="<?= e(url($idDoc)) ?>"
                                                                   target="_blank"
                                                                   rel="noopener"
                                                                   class="res-action-item doc-link"
                                                                   role="menuitem"
                                                                   data-doc-preview="1"
                                                                   data-doc-url="<?= e(url($idDoc)) ?>"
                                                                   data-doc-label="<?= e($idLabel) ?>"
                                                                   data-doc-type="Document"
                                                                   data-doc-status="pending"
                                                                   data-doc-owner="<?= e($guideName) ?>">
                                                                    <?= admin_icon('documents', 14) ?> View Verification ID
                                                                </a>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Tourist Note Modal -->
<div class="gm-modal" id="touristNoteModal" hidden aria-hidden="true">
    <div class="gm-modal-backdrop" data-close-note-modal></div>
    <div class="gm-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="touristNoteTitle" style="max-width:500px;border-radius:14px;overflow:hidden;box-shadow:0 20px 50px rgba(0,0,0,0.25);">
        <div class="gm-modal-head" style="padding:1rem 1.25rem;border-bottom:1px solid var(--line);background:#ffffff;display:flex;align-items:center;justify-content:space-between;">
            <div style="display:flex;align-items:center;gap:0.65rem;">
                <div style="width:36px;height:36px;border-radius:8px;background:#e0f2fe;color:#0284c7;display:flex;align-items:center;justify-content:center;font-size:1.15rem;flex-shrink:0;">
                    💬
                </div>
                <div>
                    <h3 id="touristNoteTitle" style="margin:0;font-size:1.05rem;font-weight:700;color:var(--ink-900);">Tourist Note</h3>
                    <small class="hint" style="font-size:0.75rem;">Special requests & instructions from customer</small>
                </div>
            </div>
            <button type="button" class="gm-modal-close" data-close-note-modal aria-label="Close note dialog" style="background:none;border:none;font-size:1.5rem;color:#94a3b8;cursor:pointer;line-height:1;padding:0 4px;">&times;</button>
        </div>
        <div class="gm-modal-body" style="padding:1.25rem;background:#ffffff;">
            <!-- Context card -->
            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:0.75rem 0.95rem;margin-bottom:1rem;display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
                <div>
                    <span style="font-size:0.72rem;color:var(--ink-300);text-transform:uppercase;font-weight:700;letter-spacing:0.04em;display:block;margin-bottom:2px;">Customer</span>
                    <strong id="noteModalCustomer" style="font-size:0.88rem;color:var(--ink-900);display:block;">—</strong>
                </div>
                <div>
                    <span style="font-size:0.72rem;color:var(--ink-300);text-transform:uppercase;font-weight:700;letter-spacing:0.04em;display:block;margin-bottom:2px;">Pickup Date</span>
                    <strong id="noteModalPickup" style="font-size:0.88rem;color:var(--teal-700);display:block;">—</strong>
                </div>
                <div style="grid-column:1 / -1;border-top:1px dashed #e2e8f0;padding-top:0.5rem;margin-top:0.15rem;">
                    <span style="font-size:0.72rem;color:var(--ink-300);text-transform:uppercase;font-weight:700;letter-spacing:0.04em;display:block;margin-bottom:2px;">Vehicle</span>
                    <span id="noteModalVehicle" style="font-size:0.86rem;font-weight:600;color:var(--ink-700);display:block;">—</span>
                </div>
            </div>

            <!-- Message Card -->
            <div>
                <span style="font-size:0.72rem;color:var(--ink-500);text-transform:uppercase;font-weight:700;letter-spacing:0.04em;display:block;margin-bottom:0.4rem;">Message from Tourist</span>
                <div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:0.95rem 1rem;">
                    <p id="noteModalText" style="margin:0;font-size:0.92rem;line-height:1.55;color:#0f172a;white-space:pre-wrap;word-break:break-word;"></p>
                </div>
            </div>
        </div>
        <div class="gm-modal-foot" style="padding:0.75rem 1.25rem;border-top:1px solid var(--line);background:#f8fafc;margin:0;display:flex;justify-content:flex-end;">
            <button type="button" class="btn btn-primary btn-sm" data-close-note-modal style="padding:0.4rem 1.25rem;font-size:0.84rem;border-radius:6px;">Close</button>
        </div>
    </div>
</div>

<!-- Problem Report Resolution Modal -->
<div class="gm-modal" id="problemReportModal" hidden aria-hidden="true">
    <div class="gm-modal-backdrop" data-close-problem-modal></div>
    <div class="gm-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="problemModalTitle" style="max-width:520px;border-radius:14px;overflow:hidden;box-shadow:0 24px 60px rgba(0,0,0,0.3);border:1px solid #e2e8f0;">
        <div class="gm-modal-head" style="padding:1rem 1.25rem;border-bottom:1px solid #fee2e2;background:#fff5f5;display:flex;align-items:center;justify-content:space-between;">
            <div style="display:flex;align-items:center;gap:0.65rem;">
                <div style="width:36px;height:36px;border-radius:8px;background:#fee2e2;color:#dc2626;display:flex;align-items:center;justify-content:center;font-size:1.15rem;flex-shrink:0;">
                    ⚠
                </div>
                <div>
                    <h3 id="problemModalTitle" style="margin:0;font-size:1.05rem;font-weight:700;color:#991b1b;">Tourist Problem Report</h3>
                    <small class="hint" style="font-size:0.75rem;color:#b91c1c;">Customer reported an issue with this rental</small>
                </div>
            </div>
            <button type="button" class="gm-modal-close" data-close-problem-modal aria-label="Close dialog" style="background:none;border:none;font-size:1.5rem;color:#94a3b8;cursor:pointer;line-height:1;padding:0 4px;">&times;</button>
        </div>
        <div class="gm-modal-body" style="padding:1.25rem;background:#ffffff;">
            <!-- Reservation Context Card -->
            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:0.75rem 0.95rem;margin-bottom:1rem;display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
                <div>
                    <span style="font-size:0.72rem;color:var(--ink-300);text-transform:uppercase;font-weight:700;letter-spacing:0.04em;display:block;margin-bottom:2px;">Customer</span>
                    <strong id="problemModalCustomer" style="font-size:0.88rem;color:var(--ink-900);display:block;">—</strong>
                    <small id="problemModalPhone" class="hint" style="font-size:0.76rem;display:block;">—</small>
                </div>
                <div>
                    <span style="font-size:0.72rem;color:var(--ink-300);text-transform:uppercase;font-weight:700;letter-spacing:0.04em;display:block;margin-bottom:2px;">Total Paid</span>
                    <strong id="problemModalAmount" style="font-size:0.95rem;color:#059669;display:block;">—</strong>
                    <small id="problemModalPickup" class="hint" style="font-size:0.76rem;display:block;">—</small>
                </div>
                <div style="grid-column:1 / -1;border-top:1px dashed #e2e8f0;padding-top:0.5rem;margin-top:0.15rem;">
                    <span style="font-size:0.72rem;color:var(--ink-300);text-transform:uppercase;font-weight:700;letter-spacing:0.04em;display:block;margin-bottom:2px;">Vehicle</span>
                    <span id="problemModalVehicle" style="font-size:0.86rem;font-weight:600;color:var(--ink-700);display:block;">—</span>
                </div>
            </div>

            <!-- Complaint Message -->
            <div>
                <span style="font-size:0.72rem;color:#991b1b;text-transform:uppercase;font-weight:700;letter-spacing:0.04em;display:block;margin-bottom:0.4rem;">Customer's Statement</span>
                <div style="background:#fef2f2;border:1.5px solid #fecaca;border-radius:8px;padding:0.95rem 1rem;">
                    <p id="problemModalMessage" style="margin:0;font-size:0.92rem;line-height:1.55;color:#7f1d1d;white-space:pre-wrap;word-break:break-word;"></p>
                </div>
            </div>

            <!-- Attached Photo Evidence -->
            <div id="problemModalPhotosWrap" style="margin-top:1rem;display:none;">
                <span style="font-size:0.72rem;color:#991b1b;text-transform:uppercase;font-weight:700;letter-spacing:0.04em;display:block;margin-bottom:0.4rem;">Photo Evidence</span>
                <div id="problemModalPhotos" style="display:flex;gap:0.5rem;flex-wrap:wrap;"></div>
            </div>
        </div>
        <div class="gm-modal-foot" style="padding:0.85rem 1.25rem;border-top:1px solid var(--line);background:#f8fafc;margin:0;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.6rem;">
            <?php if ($isWarned): ?>
                <button type="button" class="btn btn-ghost btn-sm" disabled title="Unavailable: Active administrator warning on your account." style="opacity:0.6;cursor:not-allowed;">
                    Dismiss Report (Restricted)
                </button>
            <?php else: ?>
                <form method="post" id="problemDismissForm" action="" class="res-action-form" onsubmit="return confirm('Dismiss this problem report? The reservation will remain in its current status.');">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-ghost btn-sm" style="color:#64748b;border:1px solid #cbd5e1;background:#fff;padding:0.4rem 0.9rem;border-radius:6px;font-weight:600;">
                        Dismiss Report
                    </button>
                </form>
            <?php endif; ?>
            <div style="display:flex;gap:0.5rem;align-items:center;">
                <button type="button" class="btn btn-ghost btn-sm" data-close-problem-modal style="padding:0.4rem 0.9rem;border-radius:6px;">Close</button>
                <?php if ($isWarned): ?>
                    <button type="button" class="btn btn-sm" disabled title="Unavailable: Active administrator warning on your account." style="background:#cbd5e1;color:#64748b;font-weight:700;border:none;padding:0.4rem 1rem;border-radius:6px;cursor:not-allowed;">
                        Refund Restricted
                    </button>
                <?php else: ?>
                    <form method="post" id="problemRefundForm" action="" class="res-action-form" onsubmit="return confirm('Issue a full refund to the customer for this rental booking?');">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm" style="background:#dc2626;color:#ffffff;font-weight:700;border:none;padding:0.4rem 1rem;border-radius:6px;cursor:pointer;">
                            Refund Customer
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var noteModal = document.getElementById('touristNoteModal');
    var probModal = document.getElementById('problemReportModal');

    // --- Tourist Note Modal Logic ---
    if (noteModal) {
        var noteCustomerEl = document.getElementById('noteModalCustomer');
        var noteVehicleEl = document.getElementById('noteModalVehicle');
        var notePickupEl = document.getElementById('noteModalPickup');
        var noteTextEl = document.getElementById('noteModalText');

        function openNoteModal(btn) {
            var customer = btn.getAttribute('data-customer') || 'Customer';
            var vehicle = btn.getAttribute('data-vehicle') || 'Vehicle';
            var pickup = btn.getAttribute('data-pickup') || '—';
            var note = btn.getAttribute('data-note') || '';

            if (noteCustomerEl) noteCustomerEl.textContent = customer;
            if (noteVehicleEl) noteVehicleEl.textContent = vehicle;
            if (notePickupEl) notePickupEl.textContent = pickup;
            if (noteTextEl) noteTextEl.textContent = note;

            noteModal.hidden = false;
            noteModal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        }

        function closeNoteModal() {
            noteModal.hidden = true;
            noteModal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        }

        document.addEventListener('click', function (e) {
            var trigger = e.target.closest ? e.target.closest('[data-open-note-modal]') : null;
            if (trigger) {
                e.preventDefault();
                e.stopPropagation();
                var openMenu = document.querySelector('.res-action-dropdown.open');
                if (openMenu) {
                    openMenu.classList.remove('open');
                }
                openNoteModal(trigger);
                return;
            }

            if (e.target.closest && e.target.closest('[data-close-note-modal]')) {
                e.preventDefault();
                closeNoteModal();
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !noteModal.hidden) {
                closeNoteModal();
            }
        });
    }

    // --- Problem Report Modal Logic ---
    if (probModal) {
        var probCustomerEl = document.getElementById('problemModalCustomer');
        var probPhoneEl = document.getElementById('problemModalPhone');
        var probAmountEl = document.getElementById('problemModalAmount');
        var probPickupEl = document.getElementById('problemModalPickup');
        var probVehicleEl = document.getElementById('problemModalVehicle');
        var probMessageEl = document.getElementById('problemModalMessage');
        var probRefundForm = document.getElementById('problemRefundForm');
        var probDismissForm = document.getElementById('problemDismissForm');

        function openProblemModal(btn) {
            var customer = btn.getAttribute('data-customer') || 'Customer';
            var phone = btn.getAttribute('data-phone') || '';
            var vehicle = btn.getAttribute('data-vehicle') || 'Vehicle';
            var pickup = btn.getAttribute('data-pickup') || '—';
            var amount = btn.getAttribute('data-amount') || '—';
            var message = btn.getAttribute('data-message') || '';
            var refundUrl = btn.getAttribute('data-refund-url') || '';
            var rejectUrl = btn.getAttribute('data-reject-url') || '';

            if (probCustomerEl) probCustomerEl.textContent = customer;
            if (probPhoneEl) probPhoneEl.textContent = phone;
            if (probVehicleEl) probVehicleEl.textContent = vehicle;
            if (probPickupEl) probPickupEl.textContent = pickup;
            if (probAmountEl) probAmountEl.textContent = amount;
            if (probMessageEl) probMessageEl.textContent = message || 'No details provided.';

            var photosWrap = document.getElementById('problemModalPhotosWrap');
            var photosContainer = document.getElementById('problemModalPhotos');
            var photos = [];
            try {
                photos = JSON.parse(btn.getAttribute('data-photos') || '[]');
            } catch (e) { photos = []; }

            if (photosWrap && photosContainer) {
                photosContainer.innerHTML = '';
                if (Array.isArray(photos) && photos.length > 0) {
                    photosWrap.style.display = 'block';
                    photos.forEach(function(url) {
                        var a = document.createElement('a');
                        a.href = url;
                        a.target = '_blank';
                        a.rel = 'noopener noreferrer';
                        a.title = 'Click to view full photo';
                        var img = document.createElement('img');
                        img.src = url;
                        img.style.width = '74px';
                        img.style.height = '74px';
                        img.style.objectFit = 'cover';
                        img.style.borderRadius = '8px';
                        img.style.border = '1px solid #fca5a5';
                        a.appendChild(img);
                        photosContainer.appendChild(a);
                    });
                } else {
                    photosWrap.style.display = 'none';
                }
            }

            if (probRefundForm && refundUrl) probRefundForm.action = refundUrl;
            if (probDismissForm && rejectUrl) probDismissForm.action = rejectUrl;

            probModal.hidden = false;
            probModal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        }

        function closeProblemModal() {
            probModal.hidden = true;
            probModal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        }

        document.addEventListener('click', function (e) {
            var trigger = e.target.closest ? e.target.closest('[data-open-problem-modal]') : null;
            if (trigger) {
                e.preventDefault();
                e.stopPropagation();
                var openMenu = document.querySelector('.res-action-dropdown.open');
                if (openMenu) {
                    openMenu.classList.remove('open');
                }
                openProblemModal(trigger);
                return;
            }

            if (e.target.closest && e.target.closest('[data-close-problem-modal]')) {
                e.preventDefault();
                closeProblemModal();
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !probModal.hidden) {
                closeProblemModal();
            }
        });
    }
})();
</script>
