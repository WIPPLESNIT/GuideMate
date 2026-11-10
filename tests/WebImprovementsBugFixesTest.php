<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Database;
use App\Models\RentalRequest;
use App\Models\Dispute;
use App\Models\User;

$failures = 0;
function assertTrue(bool $cond, string $msg): void {
    global $failures;
    if (!$cond) {
        echo "FAILED: $msg\n";
        $failures++;
    } else {
        echo "PASSED: $msg\n";
    }
}

echo "=== 1. Testing Rental Requests Action Dropdown Menu ===\n";

// Helper function to capture rendered action dropdown HTML for a given request
function renderRentalActions(array $r, bool $isWarned = false): string {
    $paymentStatus = strtolower(trim((string) ($r['payment_status'] ?? 'unpaid')));
    $reportStatus = strtolower(trim((string) ($r['report_status'] ?? 'none')));
    $reqStatus = strtolower(trim((string) ($r['status'] ?? 'pending')));
    $isPending = $reqStatus === 'pending';
    $isRefunded = in_array('refunded', [$reqStatus, $paymentStatus, $reportStatus], true);
    $idDoc = $r['id_doc_path'] ?? null;
    $idLabel = 'Driver License';
    $guideName = 'Test Partner';

    ob_start();
    ?>
    <div class="res-action-dropdown" id="rentalReqMenu_<?= (int) $r['id'] ?>" role="menu">
        <?php if ($isRefunded): ?>
            <button type="button" class="res-action-item action-delete" data-delete-rental data-delete-report data-id="<?= (int) $r['id'] ?>" data-url="<?= e(url('/dashboard/rentals/' . $r['id'] . '/delete')) ?>" role="menuitem" style="color:#DC2626;display:flex;align-items:center;gap:6px;">
                <?= admin_icon('trash', 14) ?> Delete Report
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

                <?php if ($reqStatus === 'completed'): ?>
                    <button type="button" class="res-action-item action-delete" data-delete-rental data-id="<?= (int) $r['id'] ?>" data-url="<?= e(url('/dashboard/rentals/' . $r['id'] . '/delete')) ?>" role="menuitem" style="color:#DC2626;display:flex;align-items:center;gap:6px;">
                        <?= admin_icon('trash', 14) ?> Delete Record
                    </button>
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
    <?php
    return ob_get_clean();
}

// 1.1 Refunded request by status = 'refunded'
$refundedByStatus = [
    'id' => 9991,
    'status' => 'refunded',
    'payment_status' => 'refunded',
    'report_status' => 'none',
    'customer_name' => 'John Doe',
    'vehicle_name' => 'Scooter',
    'pickup_date' => '2026-11-12',
    'total_amount' => 1500,
];
$html1 = renderRentalActions($refundedByStatus);
assertTrue(str_contains($html1, 'Delete Report'), 'Refunded request includes Delete Report');
assertTrue(!str_contains($html1, 'Confirm Booking'), 'Refunded request does NOT include Confirm Booking');
assertTrue(!str_contains($html1, 'Decline Booking'), 'Refunded request does NOT include Decline Booking');
assertTrue(!str_contains($html1, 'Mark Contacted'), 'Refunded request does NOT include Mark Contacted');
assertTrue(!str_contains($html1, 'Complete Rental'), 'Refunded request does NOT include Complete Rental');
assertTrue(!str_contains($html1, 'Cancel Rental'), 'Refunded request does NOT include Cancel Rental');
assertTrue(!str_contains($html1, 'Refund Customer'), 'Refunded request does NOT include Refund Customer');
assertTrue(!str_contains($html1, 'Refund Payment'), 'Refunded request does NOT include Refund Payment');

// 1.2 Refunded request by payment_status = 'refunded' (status cancelled)
$refundedByPayment = [
    'id' => 9992,
    'status' => 'cancelled',
    'payment_status' => 'refunded',
    'report_status' => 'none',
    'customer_name' => 'Jane Smith',
    'vehicle_name' => 'SUV',
    'pickup_date' => '2026-11-12',
    'total_amount' => 3500,
];
$html2 = renderRentalActions($refundedByPayment);
assertTrue(str_contains($html2, 'Delete Report'), 'Cancelled+Refunded request displays Delete Report');
assertTrue(!str_contains($html2, 'Complete Rental'), 'Cancelled+Refunded request does NOT display Complete Rental');
assertTrue(!str_contains($html2, 'Cancel Rental'), 'Cancelled+Refunded request does NOT display Cancel Rental');

// 1.3 Non-refunded Pending request
$pendingReq = [
    'id' => 9993,
    'status' => 'pending',
    'payment_status' => 'paid',
    'report_status' => 'none',
    'customer_name' => 'Alice Brown',
    'vehicle_name' => 'Motorcycle',
    'pickup_date' => '2026-11-15',
    'total_amount' => 1200,
];
$html3 = renderRentalActions($pendingReq);
assertTrue(!str_contains($html3, 'Delete Report'), 'Pending request does NOT show Delete Report');
assertTrue(str_contains($html3, 'Confirm Booking'), 'Pending request shows Confirm Booking');
assertTrue(str_contains($html3, 'Decline Booking'), 'Pending request shows Decline Booking');

// 1.4 Non-refunded Approved request
$approvedReq = [
    'id' => 9994,
    'status' => 'approved',
    'payment_status' => 'paid',
    'report_status' => 'none',
    'customer_name' => 'Bob White',
    'vehicle_name' => 'Sedan',
    'pickup_date' => '2026-11-16',
    'total_amount' => 2500,
];
$html4 = renderRentalActions($approvedReq);
assertTrue(str_contains($html4, 'Mark Contacted'), 'Approved request shows Mark Contacted');
assertTrue(str_contains($html4, 'Complete Rental'), 'Approved request shows Complete Rental');
assertTrue(str_contains($html4, 'Cancel Rental'), 'Approved request shows Cancel Rental');
assertTrue(str_contains($html4, 'Refund Payment'), 'Paid Approved request shows Refund Payment');

// 1.5 Non-refunded Completed request
$completedReq = [
    'id' => 9995,
    'status' => 'completed',
    'payment_status' => 'paid',
    'report_status' => 'none',
    'customer_name' => 'Charlie Green',
    'vehicle_name' => 'Van',
    'pickup_date' => '2026-11-10',
    'total_amount' => 4500,
];
$html5 = renderRentalActions($completedReq);
assertTrue(str_contains($html5, 'Delete Record'), 'Completed request shows Delete Record');
assertTrue(!str_contains($html5, 'Delete Report'), 'Completed request does not show Delete Report');
assertTrue(!str_contains($html5, 'Complete Rental'), 'Completed request does not show Complete Rental');

echo "\n=== 2. Testing Admin Overview Complaints Table Layout ===\n";

$adminViewFile = __DIR__ . '/../app/Views/dashboard/admin.php';
$adminViewContent = file_get_contents($adminViewFile);

assertTrue(str_contains($adminViewContent, 'admin-complaints-wrap'), 'admin.php contains admin-complaints-wrap class');
assertTrue(str_contains($adminViewContent, 'admin-complaints-table'), 'admin.php contains admin-complaints-table class');
assertTrue(str_contains($adminViewContent, '<colgroup>'), 'admin.php complaints table uses <colgroup> with defined column proportions');
assertTrue(!str_contains($adminViewContent, 'min-width:220px;'), 'admin.php does not contain overflowing min-width:220px style');
assertTrue(!str_contains($adminViewContent, 'min-width:200px;'), 'admin.php does not contain overflowing min-width:200px style');
assertTrue(!preg_match('/admin-complaints-wrap[^>]*overflow-x:\s*auto/', $adminViewContent), 'admin.php does not hardcode overflow-x:auto on complaints panel');
$cssFile = __DIR__ . '/../public/assets/css/style.css';
$cssContent = file_get_contents($cssFile);

assertTrue(str_contains($cssContent, '.admin-complaints-wrap'), 'style.css defines .admin-complaints-wrap');
assertTrue(str_contains($cssContent, 'table.admin-complaints-table'), 'style.css defines table.admin-complaints-table');
assertTrue(str_contains($cssContent, 'table-layout: fixed;'), 'style.css enforces table-layout: fixed for complaints table');
assertTrue(str_contains($cssContent, 'overflow-wrap: anywhere;'), 'style.css sets overflow-wrap for cell contents');

echo "\n=== 3. Testing Real Database Dispute Rendering in Complaints Table ===\n";

$disputes = Dispute::all();
assertTrue(count($disputes) > 0, 'Database contains disputes to test rendering');

ob_start();
$complaints = $disputes;
$stats = [
    'tourists' => 10,
    'guides' => 5,
    'listings' => 15,
    'revenue' => 12500.0,
    'openDisputes' => count($disputes),
    'pendingGuides' => 0,
    'pending' => 0,
];
$pendingGuides = [];
$pending = [];
include $adminViewFile;
$renderedAdmin = ob_get_clean();

assertTrue(str_contains($renderedAdmin, 'admin-complaints-table'), 'Rendered admin page contains admin-complaints-table');
assertTrue(str_contains($renderedAdmin, 'col-complaint-tourist'), 'Rendered table contains tourist column');
assertTrue(str_contains($renderedAdmin, 'col-complaint-reported'), 'Rendered table contains reported account column');
assertTrue(str_contains($renderedAdmin, 'col-complaint-details'), 'Rendered table contains problem details column');
assertTrue(str_contains($renderedAdmin, 'Review'), 'Rendered table contains Review action button');

if ($failures === 0) {
    echo "\nALL TESTS PASSED SUCCESSFULLY! (0 failures)\n";
    exit(0);
} else {
    echo "\nTESTS FAILED with $failures failure(s).\n";
    exit(1);
}
