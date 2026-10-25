<?php

declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Auth;
use App\Core\Database;
use App\Models\User;
use App\Models\Listing;
use App\Models\ListingSchedule;
use App\Controllers\GuideController;

final class GuideListingActionPatternAndScheduleTest
{
    private array $createdUserIds = [];
    private array $createdListingIds = [];
    private int $passed = 0;
    private int $failed = 0;

    public function run(): bool
    {
        echo "========================================================\n";
        echo " GuideMate Guide Listing Action Pattern & Schedule Suite\n";
        echo "========================================================\n\n";

        $this->runTest('testMyListingTableHasOverflowMenuWithEditScheduleDelete', [$this, 'testMyListingTableHasOverflowMenuWithEditScheduleDelete']);
        $this->runTest('testEditListingModalStructureAndBehavior', [$this, 'testEditListingModalStructureAndBehavior']);
        $this->runTest('testScheduleModalHasEndTimeFieldAndUpcomingTable', [$this, 'testScheduleModalHasEndTimeFieldAndUpcomingTable']);
        $this->runTest('testScheduleEndTimeLaterThanStartTimeValidation', [$this, 'testScheduleEndTimeLaterThanStartTimeValidation']);
        $this->runTest('testScheduleEntryCanBeAddedUpdatedAndRemoved', [$this, 'testScheduleEntryCanBeAddedUpdatedAndRemoved']);
        $this->runTest('testScheduleModalUsesAppBasePathForFetchAndActionUrls', [$this, 'testScheduleModalUsesAppBasePathForFetchAndActionUrls']);
        $this->runTest('testGuideScheduleApiEndpointReturnsScheduleJson', [$this, 'testGuideScheduleApiEndpointReturnsScheduleJson']);
        $this->runTest('testAbortHelperReturnsJsonForAjaxRequests', [$this, 'testAbortHelperReturnsJsonForAjaxRequests']);

        $this->tearDown();

        echo "\n--------------------------------------------------------\n";
        echo sprintf("Results: %d passed, %d failed\n", $this->passed, $this->failed);
        echo "--------------------------------------------------------\n";

        return $this->failed === 0;
    }

    public function testMyListingTableHasOverflowMenuWithEditScheduleDelete(): void
    {
        $listingsTpl = file_get_contents(BASE_DIR . '/app/Views/guide/listings.php');

        // 1. Must use the three-dot overflow trigger button matching My Hotels
        $this->assert(str_contains($listingsTpl, 'class="btn-res-action-trigger"'), "listings.php must use btn-res-action-trigger class");
        $this->assert(str_contains($listingsTpl, 'admin_icon(\'more-vertical\''), "listings.php must display more-vertical icon for overflow trigger");
        $this->assert(str_contains($listingsTpl, 'class="res-action-wrap"'), "listings.php must wrap actions in res-action-wrap");
        $this->assert(str_contains($listingsTpl, 'class="res-action-dropdown"'), "listings.php must include res-action-dropdown");

        // 2. Edit action moved into overflow menu and triggers openEditListingModal
        $this->assert(str_contains($listingsTpl, 'openEditListingModal'), "listings.php must call openEditListingModal from menu item");
        $this->assert(str_contains($listingsTpl, 'admin_icon(\'edit\''), "listings.php must show edit icon in menu item");

        // 3. Schedule action moved into overflow menu and triggers openScheduleModal
        $this->assert(str_contains($listingsTpl, 'openScheduleModal'), "listings.php must call openScheduleModal from menu item");
        $this->assert(str_contains($listingsTpl, 'admin_icon(\'calendar\''), "listings.php must show calendar icon in menu item");

        // 4. View live listing and Delete in overflow menu
        $this->assert(str_contains($listingsTpl, 'admin_icon(\'eye\''), "listings.php must show eye icon for live listing");
        $this->assert(str_contains($listingsTpl, 'admin_icon(\'trash\''), "listings.php must show trash icon for delete");
        $this->assert(str_contains($listingsTpl, 'class="res-action-form"'), "listings.php must use res-action-form for delete action");
    }

    public function testEditListingModalStructureAndBehavior(): void
    {
        $listingsTpl = file_get_contents(BASE_DIR . '/app/Views/guide/listings.php');

        // Modal and form exist with required inputs
        $this->assert(str_contains($listingsTpl, 'id="newListingModal"'), "listings.php must have newListingModal");
        $this->assert(str_contains($listingsTpl, 'id="newListingForm"'), "listings.php must have newListingForm");
        $this->assert(str_contains($listingsTpl, 'id="nl_listing_id"'), "listings.php must have nl_listing_id hidden input");
        $this->assert(str_contains($listingsTpl, 'id="nl_preview_wrap"'), "listings.php must have nl_preview_wrap for image preview");
        $this->assert(str_contains($listingsTpl, 'window.openEditListingModal'), "listings.php must define window.openEditListingModal");

        // Test guide editing their listing via controller
        $guideId = User::create('Edit Guide', 'guide_edit_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass123!', 'guide');
        $this->createdUserIds[] = $guideId;
        Database::run("UPDATE users SET guide_status = 'approved' WHERE id = ?", [$guideId]);

        $_SESSION['user_id'] = $guideId;
        Auth::refreshUser();

        $slug = 'test-tour-' . bin2hex(random_bytes(4));
        $listingId = Database::insert(
            "INSERT INTO listings (user_id, category_id, title, slug, summary, description, area, price, price_unit, status)
             VALUES (?, 1, 'Initial Tour Title', ?, 'Initial summary', 'Initial desc', 'Cebu City', 1500, 'per person', 'approved')",
            [$guideId, $slug]
        );
        $this->createdListingIds[] = $listingId;

        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $_POST['_token'] = csrf_token();
        $_POST['title'] = 'Updated Tour Title';
        $_POST['category_id'] = '1';
        $_POST['area'] = 'Moalboal, Cebu';
        $_POST['description'] = 'Updated description with more details';
        $_POST['price'] = '1800';
        $_POST['price_unit'] = 'per person';

        $controller = new GuideController();
        ob_start();
        $controller->update((string) $listingId);
        $output = ob_get_clean();

        $data = json_decode($output ?: '', true);
        $this->assert(is_array($data) && ($data['success'] ?? false) === true, "Controller must return success JSON on update");

        $updated = Listing::find($listingId);
        $this->assert($updated['title'] === 'Updated Tour Title', "Listing title must be updated in database");
        $this->assert($updated['area'] === 'Moalboal, Cebu', "Listing area must be updated in database");

        unset($_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_X_REQUESTED_WITH'], $_POST['_token'], $_POST['title'], $_POST['category_id'], $_POST['area'], $_POST['description'], $_POST['price'], $_POST['price_unit']);
    }

    public function testScheduleModalHasEndTimeFieldAndUpcomingTable(): void
    {
        $listingsTpl = file_get_contents(BASE_DIR . '/app/Views/guide/listings.php');

        // Schedule modal dialog exists
        $this->assert(str_contains($listingsTpl, 'id="scheduleModal"'), "listings.php must contain scheduleModal");
        $this->assert(str_contains($listingsTpl, 'id="scheduleModalForm"'), "listings.php must contain scheduleModalForm");
        $this->assert(str_contains($listingsTpl, 'id="sch_date"'), "scheduleModal must contain sch_date input");
        $this->assert(str_contains($listingsTpl, 'id="sch_start_time"'), "scheduleModal must contain sch_start_time input");
        $this->assert(str_contains($listingsTpl, 'id="sch_end_time"'), "scheduleModal must contain sch_end_time input");
        $this->assert(str_contains($listingsTpl, 'type="time" id="sch_end_time"'), "sch_end_time must use type=time matching start_time");
        $this->assert(str_contains($listingsTpl, 'id="sch_table"'), "scheduleModal must have sch_table for upcoming sessions");
        $this->assert(str_contains($listingsTpl, 'window.openScheduleModal'), "listings.php must define window.openScheduleModal");

        // Standalone schedule.php also has end_time
        $schedTpl = file_get_contents(BASE_DIR . '/app/Views/guide/schedule.php');
        $this->assert(str_contains($schedTpl, 'id="end_time"'), "schedule.php must contain end_time input");
        $this->assert(str_contains($schedTpl, 'type="time" id="end_time"'), "schedule.php end_time must use type=time");
    }

    public function testScheduleEndTimeLaterThanStartTimeValidation(): void
    {
        $guideId = User::create('Schedule Guide', 'guide_sched_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass123!', 'guide');
        $this->createdUserIds[] = $guideId;
        Database::run("UPDATE users SET guide_status = 'approved' WHERE id = ?", [$guideId]);

        $_SESSION['user_id'] = $guideId;
        Auth::refreshUser();

        $slug = 'test-sched-tour-' . bin2hex(random_bytes(4));
        $listingId = Database::insert(
            "INSERT INTO listings (user_id, category_id, title, slug, summary, description, area, price, price_unit, status)
             VALUES (?, 1, 'Schedule Tour', ?, 'Summary', 'Desc', 'Cebu City', 1500, 'per person', 'approved')",
            [$guideId, $slug]
        );
        $this->createdListingIds[] = $listingId;

        $controller = new GuideController();

        // 1. Validation failure: end_time earlier than start_time
        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $_POST['_token'] = csrf_token();
        $_POST['schedule_date'] = date('Y-m-d', strtotime('+3 days'));
        $_POST['start_time'] = '14:00';
        $_POST['end_time'] = '10:00'; // Invalid: earlier than start_time

        ob_start();
        $controller->addSchedule((string) $listingId);
        $output = ob_get_clean();
        $statusCode = http_response_code();

        $data = json_decode($output ?: '', true);
        $this->assert($statusCode === 422, "Controller must return HTTP 422 when end_time is earlier than start_time, got {$statusCode}");
        $this->assert(isset($data['error']) && str_contains($data['error'], 'End time must be later than the start time'), "Error message must state 'End time must be later than the start time.'");

        // 2. Validation failure: end_time equal to start_time
        $_POST['end_time'] = '14:00'; // Invalid: equal to start_time
        ob_start();
        $controller->addSchedule((string) $listingId);
        $output = ob_get_clean();
        $statusCode = http_response_code();

        $data = json_decode($output ?: '', true);
        $this->assert($statusCode === 422, "Controller must return HTTP 422 when end_time is equal to start_time, got {$statusCode}");
        $this->assert(isset($data['error']) && str_contains($data['error'], 'End time must be later than the start time'), "Error message must state 'End time must be later than the start time.'");

        unset($_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_X_REQUESTED_WITH'], $_POST['_token'], $_POST['schedule_date'], $_POST['start_time'], $_POST['end_time']);
    }

    public function testScheduleEntryCanBeAddedUpdatedAndRemoved(): void
    {
        $guideId = User::create('Schedule Crud Guide', 'guide_crud_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass123!', 'guide');
        $this->createdUserIds[] = $guideId;
        Database::run("UPDATE users SET guide_status = 'approved' WHERE id = ?", [$guideId]);

        $_SESSION['user_id'] = $guideId;
        Auth::refreshUser();

        $slug = 'test-crud-tour-' . bin2hex(random_bytes(4));
        $listingId = Database::insert(
            "INSERT INTO listings (user_id, category_id, title, slug, summary, description, area, price, price_unit, status)
             VALUES (?, 1, 'Schedule CRUD Tour', ?, 'Summary', 'Desc', 'Cebu City', 1500, 'per person', 'approved')",
            [$guideId, $slug]
        );
        $this->createdListingIds[] = $listingId;

        $controller = new GuideController();

        // 1. Add valid schedule entry with end_time later than start_time
        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $_POST['_token'] = csrf_token();
        $futureDate = date('Y-m-d', strtotime('+5 days'));
        $_POST['schedule_date'] = $futureDate;
        $_POST['start_time'] = '08:30';
        $_POST['end_time'] = '12:30';
        $_POST['capacity'] = '12';
        $_POST['note'] = 'Morning Canyoneering';

        ob_start();
        $controller->addSchedule((string) $listingId);
        $output = ob_get_clean();
        $statusCode = http_response_code();

        $data = json_decode($output ?: '', true);
        $this->assert($statusCode === 200, "Adding valid schedule must return HTTP 200, got {$statusCode}");
        $this->assert(($data['success'] ?? false) === true, "Response must indicate success");

        $entries = ListingSchedule::forListing($listingId);
        $this->assert(count($entries) === 1, "There should be 1 schedule entry in database");
        $this->assert($entries[0]['start_time'] === '08:30', "start_time must match '08:30'");
        $this->assert($entries[0]['end_time'] === '12:30', "end_time must match '12:30'");
        $this->assert((int) $entries[0]['capacity'] === 12, "capacity must match 12");

        $scheduleId = (int) $entries[0]['id'];

        // 2. Update the schedule entry
        $_POST['schedule_id'] = (string) $scheduleId;
        $_POST['schedule_date'] = $futureDate;
        $_POST['start_time'] = '09:00';
        $_POST['end_time'] = '14:00';
        $_POST['capacity'] = '15';
        $_POST['note'] = 'Extended batch';

        ob_start();
        $controller->updateSchedule((string) $listingId, (string) $scheduleId);
        $output = ob_get_clean();

        $data = json_decode($output ?: '', true);
        $this->assert(($data['success'] ?? false) === true, "Response must indicate update success");

        $updatedEntry = ListingSchedule::find($listingId, $scheduleId);
        $this->assert($updatedEntry['start_time'] === '09:00', "start_time must be updated to '09:00'");
        $this->assert($updatedEntry['end_time'] === '14:00', "end_time must be updated to '14:00'");
        $this->assert((int) $updatedEntry['capacity'] === 15, "capacity must be updated to 15");

        // 3. Remove the schedule entry
        $_POST = ['_token' => csrf_token(), 'schedule_id' => (string) $scheduleId];
        ob_start();
        $controller->removeSchedule((string) $listingId);
        $output = ob_get_clean();

        $data = json_decode($output ?: '', true);
        $this->assert(($data['success'] ?? false) === true, "Response must indicate remove success");

        $remaining = ListingSchedule::forListing($listingId);
        $this->assert(count($remaining) === 0, "Schedule entry must be deleted from database");

        unset($_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_X_REQUESTED_WITH'], $_POST);
    }

    public function testScheduleModalUsesAppBasePathForFetchAndActionUrls(): void
    {
        $listingsTpl = file_get_contents(BASE_DIR . '/app/Views/guide/listings.php');

        // Verify baseListingsUrl is defined using url('/dashboard/listings')
        $this->assert(str_contains($listingsTpl, 'var baseListingsUrl = <?= json_encode(url(\'/dashboard/listings\')) ?>;'), "listings.php must define baseListingsUrl via url('/dashboard/listings')");

        // Verify openScheduleModal uses baseListingsUrl
        $this->assert(str_contains($listingsTpl, "fetch(baseListingsUrl + '/' + listingId + '/schedule'"), "listings.php must fetch schedule using baseListingsUrl");
        $this->assert(str_contains($listingsTpl, "schedForm.action = baseListingsUrl + '/' + listingId + '/schedule/add'"), "schedForm.action must use baseListingsUrl for add");

        // Verify removeBtn uses baseListingsUrl
        $this->assert(str_contains($listingsTpl, "fetch(baseListingsUrl + '/' + currentListingId + '/schedule/remove'"), "removeBtn must fetch remove endpoint using baseListingsUrl");

        // Verify editBtn sets schedForm.action with baseListingsUrl
        $this->assert(str_contains($listingsTpl, "schedForm.action = baseListingsUrl + '/' + currentListingId + '/schedule/' + s.id + '/update'"), "editBtn must set schedForm.action using baseListingsUrl");

        // Verify resetScheduleForm uses baseListingsUrl
        $this->assert(str_contains($listingsTpl, "schedForm.action = baseListingsUrl + '/' + currentListingId + '/schedule/add'"), "resetScheduleForm must set action using baseListingsUrl");

        // Verify no raw hardcoded /dashboard/listings paths in JS fetch/action
        $this->assert(!str_contains($listingsTpl, "fetch('/dashboard/listings/"), "listings.php must not contain hardcoded fetch('/dashboard/listings/...)");
        $this->assert(!str_contains($listingsTpl, "schedForm.action = '/dashboard/listings/"), "listings.php must not contain hardcoded schedForm.action = '/dashboard/listings/...)");
    }

    public function testGuideScheduleApiEndpointReturnsScheduleJson(): void
    {
        $guideId = User::create('Schedule Owner Guide', 'guide_owner_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass123!', 'guide');
        $this->createdUserIds[] = $guideId;
        Database::run("UPDATE users SET guide_status = 'approved' WHERE id = ?", [$guideId]);

        $_SESSION['user_id'] = $guideId;
        Auth::refreshUser();

        $slug = 'test-owner-tour-' . bin2hex(random_bytes(4));
        $listingId = Database::insert(
            "INSERT INTO listings (user_id, category_id, title, slug, summary, description, area, price, price_unit, status)
             VALUES (?, 1, 'Owner Tour', ?, 'Summary', 'Desc', 'Cebu City', 1200, 'per person', 'approved')",
            [$guideId, $slug]
        );
        $this->createdListingIds[] = $listingId;

        ListingSchedule::add($listingId, '2026-11-01', '09:00', '13:00', 8, 'Session 1');

        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';

        $controller = new GuideController();
        ob_start();
        $controller->schedule((string) $listingId);
        $output = ob_get_clean();
        $statusCode = http_response_code();

        $data = json_decode($output ?: '', true);
        $this->assert($statusCode === 200, "Schedule endpoint must return HTTP 200 for listing owner, got {$statusCode}");
        $this->assert(($data['success'] ?? false) === true, "Response must have success = true");
        $this->assert(($data['listing']['id'] ?? 0) === $listingId, "Response must include listing ID");
        $this->assert(($data['listing']['title'] ?? '') === 'Owner Tour', "Response must include listing title");
        $this->assert(is_array($data['schedule']) && count($data['schedule']) === 1, "Response must include schedule array");
        $this->assert($data['schedule'][0]['start_time'] === '09:00', "Schedule start_time must match");
        $this->assert($data['schedule'][0]['end_time'] === '13:00', "Schedule end_time must match");

        unset($_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_X_REQUESTED_WITH']);
    }

    public function testAbortHelperReturnsJsonForAjaxRequests(): void
    {
        $phpScript = <<<'PHP'
<?php
require_once __DIR__ . '/bootstrap.php';
$_SERVER['HTTP_ACCEPT'] = 'application/json';
$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
abort(404, 'Custom not found message');
PHP;

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open('C:\\xampp\\php\\php.exe', $descriptors, $pipes, BASE_DIR);
        $this->assert(is_resource($process), "Failed to launch subprocess for abort test");
        fwrite($pipes[0], $phpScript);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        proc_close($process);

        $json = json_decode(trim($stdout), true);
        $this->assert(is_array($json), "abort() on AJAX request must output valid JSON. stdout: [{$stdout}] stderr: [{$stderr}]");
        $this->assert(($json['success'] ?? true) === false, "abort() JSON output must have success=false");
        $this->assert(($json['error'] ?? '') === 'Custom not found message', "abort() JSON output must contain the custom error message");
        $this->assert(($json['status'] ?? 0) === 404, "abort() JSON output must contain status 404");
    }

    private function tearDown(): void
    {
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

$suite = new GuideListingActionPatternAndScheduleTest();
exit($suite->run() ? 0 : 1);
