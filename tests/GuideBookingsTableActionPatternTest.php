<?php

declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Auth;
use App\Core\Database;
use App\Models\User;
use App\Models\Listing;
use App\Models\Booking;
use App\Controllers\GuideController;

final class GuideBookingsTableActionPatternTest
{
    private array $createdUserIds = [];
    private array $createdListingIds = [];
    private array $createdBookingIds = [];
    private int $passed = 0;
    private int $failed = 0;

    public function run(): bool
    {
        echo "========================================================\n";
        echo " GuideMate Guide Bookings Table Action Pattern Test Suite\n";
        echo "========================================================\n\n";

        $this->runTest('testGuideBookingsTableHasThreeDotOverflowMenuAndCleanLayout', [$this, 'testGuideBookingsTableHasThreeDotOverflowMenuAndCleanLayout']);
        $this->runTest('testGuideCanConfirmPendingBookingViaActionForm', [$this, 'testGuideCanConfirmPendingBookingViaActionForm']);
        $this->runTest('testGuideCanCompleteConfirmedBookingViaActionForm', [$this, 'testGuideCanCompleteConfirmedBookingViaActionForm']);
        $this->runTest('testGuideCanCancelBookingViaActionForm', [$this, 'testGuideCanCancelBookingViaActionForm']);
        $this->runTest('testWarnedGuideIsBlockedFromBookingActions', [$this, 'testWarnedGuideIsBlockedFromBookingActions']);

        $this->tearDown();

        echo "\n--------------------------------------------------------\n";
        echo sprintf("Results: %d passed, %d failed\n", $this->passed, $this->failed);
        echo "--------------------------------------------------------\n";

        return $this->failed === 0;
    }

    public function testGuideBookingsTableHasThreeDotOverflowMenuAndCleanLayout(): void
    {
        $tpl = file_get_contents(BASE_DIR . '/app/Views/guide/bookings.php');

        // Table layout and styling
        $this->assert(str_contains($tpl, 'guide-bookings-table'), "bookings.php must use guide-bookings-table class");
        $this->assert(str_contains($tpl, 'guide-bookings-panel'), "bookings.php must use guide-bookings-panel class");
        $this->assert(str_contains($tpl, 'guide-bookings-wrap'), "bookings.php must use guide-bookings-wrap class");
        $this->assert(str_contains($tpl, 'class="col-actions" style="width:8%;text-align:right;padding-right:1.5rem;"'), "Actions th must have 8% width and padding-right:1.5rem to prevent border crowding");
        $this->assert(str_contains($tpl, 'aria-label="<?= e(__(\'th_actions\', \'Actions\')) ?>"></th'), "Action title column text must be removed from header and replaced with accessible aria-label");

        // Date nowrap fix
        $this->assert(str_contains($tpl, 'class="col-date" style="width:11%;white-space:nowrap;'), "Date column must prevent line break with white-space:nowrap");

        // Responsive percentage columns avoiding collisions
        $this->assert(str_contains($tpl, 'class="col-customer" style="width:16%;"'), "Customer column must use proportional width");
        $this->assert(str_contains($tpl, 'class="col-listing" style="width:20%;"'), "Listing column must use proportional width");
        $this->assert(str_contains($tpl, 'class="col-guests" style="width:7%;text-align:center;white-space:nowrap;"'), "Guests column must have adequate width and nowrap");
        $this->assert(str_contains($tpl, 'class="col-status" style="width:18%;white-space:nowrap;"'), "Status column must have distinct space preventing overlap with Actions");
        $this->assert(!str_contains($tpl, 'min-width:140px'), "Table header must not have rigid min-width:140px");
        $this->assert(!str_contains($tpl, 'min-width:150px'), "Table header must not have rigid min-width:150px");

        // CSS rules check for horizontal scrollbar removal, distinct status area, and listing clamp
        $css = file_get_contents(BASE_DIR . '/public/assets/css/style.css');
        $this->assert(str_contains($css, 'table-layout: fixed;'), "guide-bookings-table must use table-layout: fixed to prevent overflow");
        $this->assert(str_contains($css, 'overflow-x: hidden !important;'), "guide-bookings-wrap must use overflow-x: hidden to eliminate horizontal scrollbar");
        $this->assert(str_contains($css, '.col-status   { width: 18%; white-space: nowrap; text-align: left; }'), "col-status must have width 18% to prevent badge spillover");
        $this->assert(str_contains($css, '.col-actions  { width: 8%; text-align: right; white-space: nowrap; padding-right: 1.5rem !important; }'), "col-actions must be 8% with padding-right 1.5rem");
        $this->assert(str_contains($css, '-webkit-line-clamp: 2;'), "booking-listing-link must use 2-line clamp for very long names");

        // 3-dot overflow menu trigger matching My Listing
        $this->assert(str_contains($tpl, 'class="btn-res-action-trigger"'), "bookings.php must use btn-res-action-trigger");
        $this->assert(str_contains($tpl, "admin_icon('more-vertical', 16)"), "bookings.php must use more-vertical icon for overflow button");
        $this->assert(str_contains($tpl, 'class="res-action-wrap"'), "bookings.php must use res-action-wrap");
        $this->assert(str_contains($tpl, 'class="res-action-dropdown"'), "bookings.php must use res-action-dropdown");

        // Dropdown actions: Confirm, Complete, Cancel, and View live listing
        $this->assert(str_contains($tpl, "admin_icon('check-circle', 14)"), "Dropdown must include check-circle icon for confirm/complete");
        $this->assert(str_contains($tpl, "admin_icon('x-circle', 14)"), "Dropdown must include x-circle icon for cancel");
        $this->assert(str_contains($tpl, "admin_icon('eye', 14)"), "Dropdown must include eye icon for view live listing");
        $this->assert(str_contains($tpl, "value=\"confirmed\""), "Dropdown must include confirmed status action form");
        $this->assert(str_contains($tpl, "value=\"completed\""), "Dropdown must include completed status action form");
        $this->assert(str_contains($tpl, "value=\"cancelled\""), "Dropdown must include cancelled status action form");

        // Obsolete select input should not be in the guide table
        $this->assert(!str_contains($tpl, '<select name="status" class="input guide-status-select"'), "bookings.php must not have the old select dropdown");
    }

    public function testGuideCanConfirmPendingBookingViaActionForm(): void
    {
        $guideId = User::create('Guide Tester', 'guide_bk_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass123!', 'guide');
        $this->createdUserIds[] = $guideId;
        Database::run("UPDATE users SET guide_status = 'approved' WHERE id = ?", [$guideId]);

        $touristId = User::create('Tourist One', 'tourist_bk_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass123!', 'tourist');
        $this->createdUserIds[] = $touristId;

        $slug = 'guide-tour-' . bin2hex(random_bytes(4));
        $listingId = Database::insert(
            "INSERT INTO listings (user_id, category_id, title, slug, summary, description, area, price, price_unit, status)
             VALUES (?, 1, 'Bojo River Tour', ?, 'Summary', 'Desc', 'Aloguinsan', 1000, 'per person', 'approved')",
            [$guideId, $slug]
        );
        $this->createdListingIds[] = $listingId;

        $bookingId = Database::insert(
            "INSERT INTO bookings (user_id, listing_id, booking_date, guests, total_amount, status, created_at)
             VALUES (?, ?, '2026-11-10', 2, 2000.00, 'pending', NOW())",
            [$touristId, $listingId]
        );
        $this->createdBookingIds[] = $bookingId;

        $this->invokeUpdateBookingStatus($guideId, $bookingId, 'confirmed');

        $booking = Booking::find($bookingId);
        $this->assert($booking !== null, "Booking must exist");
        $this->assert($booking['status'] === 'confirmed', "Booking status must be updated to confirmed, got {$booking['status']}");
    }

    public function testGuideCanCompleteConfirmedBookingViaActionForm(): void
    {
        $guideId = $this->createdUserIds[0];
        $bookingId = $this->createdBookingIds[0];

        $this->invokeUpdateBookingStatus($guideId, $bookingId, 'completed');

        $booking = Booking::find($bookingId);
        $this->assert($booking['status'] === 'completed', "Booking status must be updated to completed, got {$booking['status']}");
    }

    public function testGuideCanCancelBookingViaActionForm(): void
    {
        $guideId = $this->createdUserIds[0];
        $touristId = $this->createdUserIds[1];
        $listingId = $this->createdListingIds[0];

        $bookingId = Database::insert(
            "INSERT INTO bookings (user_id, listing_id, booking_date, guests, total_amount, status, created_at)
             VALUES (?, ?, '2026-11-12', 1, 1000.00, 'pending', NOW())",
            [$touristId, $listingId]
        );
        $this->createdBookingIds[] = $bookingId;

        $this->invokeUpdateBookingStatus($guideId, $bookingId, 'cancelled');

        $booking = Booking::find($bookingId);
        $this->assert($booking['status'] === 'cancelled', "Booking status must be updated to cancelled, got {$booking['status']}");
    }

    public function testWarnedGuideIsBlockedFromBookingActions(): void
    {
        $guideId = $this->createdUserIds[0];
        $touristId = $this->createdUserIds[1];
        $listingId = $this->createdListingIds[0];

        $bookingId = Database::insert(
            "INSERT INTO bookings (user_id, listing_id, booking_date, guests, total_amount, status, created_at)
             VALUES (?, ?, '2026-11-15', 1, 1000.00, 'pending', NOW())",
            [$touristId, $listingId]
        );
        $this->createdBookingIds[] = $bookingId;

        // Issue warning to guide
        Database::run("UPDATE users SET guide_warned = 1, guide_warning_note = 'Violation note' WHERE id = ?", [$guideId]);

        $this->invokeUpdateBookingStatus($guideId, $bookingId, 'confirmed');

        $booking = Booking::find($bookingId);
        $this->assert($booking['status'] === 'pending', "Warned guide booking status must remain pending, got {$booking['status']}");

        // Clear warning for tearDown
        Database::run("UPDATE users SET guide_warned = 0, guide_warning_note = NULL WHERE id = ?", [$guideId]);
    }

    private function invokeUpdateBookingStatus(int $guideId, int $bookingId, string $status): void
    {
        $script = <<<PHP
<?php
require_once __DIR__ . '/bootstrap.php';
\$_SESSION['user_id'] = {$guideId};
\App\Core\Auth::refreshUser();
\$_POST['_token'] = csrf_token();
\$_POST['status'] = '{$status}';
\$c = new \App\Controllers\GuideController();
\$c->updateBookingStatus('{$bookingId}');
PHP;

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open('C:\\xampp\\php\\php.exe', $descriptors, $pipes, BASE_DIR);
        if (is_resource($process)) {
            fwrite($pipes[0], $script);
            fclose($pipes[0]);
            stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            stream_get_contents($pipes[2]);
            fclose($pipes[2]);
            proc_close($process);
        }
    }

    private function tearDown(): void
    {
        foreach ($this->createdBookingIds as $bid) {
            Database::run('DELETE FROM payments WHERE booking_id = ?', [$bid]);
            Database::run('DELETE FROM bookings WHERE id = ?', [$bid]);
        }
        foreach ($this->createdListingIds as $lid) {
            Database::run('DELETE FROM listing_schedules WHERE listing_id = ?', [$lid]);
            Database::run('DELETE FROM listings WHERE id = ?', [$lid]);
        }
        foreach ($this->createdUserIds as $uid) {
            Database::run('DELETE FROM users WHERE id = ?', [$uid]);
        }
    }

    private function runTest(string $name, callable $callable): void
    {
        try {
            $callable();
            $this->passed++;
            echo sprintf(" [\033[32mPASS\033[0m] %s\n", $name);
        } catch (\Throwable $e) {
            $this->failed++;
            echo sprintf(" [\033[31mFAIL\033[0m] %s: %s (in %s:%d)\n", $name, $e->getMessage(), $e->getFile(), $e->getLine());
        }
    }

    private function assert(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new \RuntimeException($message);
        }
    }
}

$suite = new GuideBookingsTableActionPatternTest();
exit($suite->run() ? 0 : 1);
