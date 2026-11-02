<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Controllers\ApiController;
use App\Core\Database;
use App\Models\Listing;
use App\Models\ListingSchedule;

final class AttractionTourScheduleTest
{
    private int $passed = 0;
    private int $failed = 0;

    public function run(): bool
    {
        echo "========================================================\n";
        echo " Attraction & Tour Interactive Schedule Feature Tests\n";
        echo "========================================================\n\n";

        $this->runTest('API Endpoint returns schedules array and address for attraction', function () {
            $controller = new ApiController();
            ob_start();
            $controller->listing('bojo-river');
            $output = ob_get_clean();
            $data = json_decode($output, true);

            $this->assert(is_array($data), 'Response must be valid JSON');
            $this->assert(isset($data['listing']), 'Response must have listing object');
            $this->assert(isset($data['schedules']), 'Response must have schedules array');
            $this->assert(is_array($data['schedules']), 'schedules must be an array');
            $this->assert(!empty($data['listing']['address']), 'Listing must include address for meeting point');
            $this->assert($data['listing']['address'] === 'Aloguinsan, Cebu', 'Address must match Bojo River address');
            $this->assert(!empty($data['schedules']), 'Bojo River must have scheduled sessions returned');
            $this->assert($data['schedules'][0]['start_time'] === '10:53', 'Start time must match');
            $this->assert($data['schedules'][0]['end_time'] === '13:01', 'End time must match');
            $this->assert($data['schedules'][0]['capacity'] === 2, 'Capacity must match');
            $this->assert($data['schedules'][0]['note'] === 'test note', 'Note must match');
        });

        $this->runTest('Mobile app [slug].tsx replaces Duration with interactive Schedule field', function () {
            $slugCode = file_get_contents(BASE_DIR . '/app/listing/[slug].tsx');

            $this->assert(
                strpos($slugCode, '>Schedule</Text>') !== false,
                '[slug].tsx must render Schedule label'
            );
            $this->assert(
                strpos($slugCode, 'setScheduleModalOpen(true)') !== false,
                '[slug].tsx must open schedule modal on pressing Schedule'
            );
            $this->assert(
                strpos($slugCode, 'See schedule</Text>') !== false,
                '[slug].tsx must render "See schedule" action link'
            );
            $this->assert(
                strpos($slugCode, 'calendar-outline') !== false,
                '[slug].tsx must render calendar icon for Schedule'
            );
        });

        $this->runTest('Mobile app [slug].tsx contains comprehensive Schedule Modal', function () {
            $slugCode = file_get_contents(BASE_DIR . '/app/listing/[slug].tsx');

            $this->assert(
                strpos($slugCode, 'visible={scheduleModalOpen}') !== false,
                'Schedule modal must be controlled by scheduleModalOpen state'
            );
            $this->assert(
                strpos($slugCode, 'Tour Schedule</Text>') !== false,
                'Schedule modal must display Tour Schedule title'
            );
            $this->assert(
                strpos($slugCode, 'Meeting Point & Departure') !== false,
                'Schedule modal must display Meeting Point & Departure section'
            );
            $this->assert(
                strpos($slugCode, 'Open in Google Maps') !== false,
                'Schedule modal must provide Google Maps directions link'
            );
            $this->assert(
                strpos($slugCode, 'Available Dates & Sessions') !== false,
                'Schedule modal must display Available Dates & Sessions'
            );
            $this->assert(
                strpos($slugCode, 'Book this Session') !== false,
                'Schedule modal must provide action to book a specific session'
            );
            $this->assert(
                strpos($slugCode, 'Select Date') !== false,
                'Schedule modal must provide primary action to select date'
            );
            $this->assert(
                strpos($slugCode, 'onRequestClose={() => setScheduleModalOpen(false)}') !== false,
                'Schedule modal must support closing on back press or cancel'
            );
        });

        echo "\n--------------------------------------------------------\n";
        echo "Results: {$this->passed} passed, {$this->failed} failed\n";
        echo "--------------------------------------------------------\n";

        return $this->failed === 0;
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

$suite = new AttractionTourScheduleTest();
exit($suite->run() ? 0 : 1);
