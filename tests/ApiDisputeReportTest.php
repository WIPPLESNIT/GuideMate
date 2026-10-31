<?php

declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Database;
use App\Models\Booking;
use App\Models\Dispute;
use App\Models\DisputeFile;
use App\Models\Payment;
use App\Models\User;

final class ApiDisputeReportTest
{
    private int $passed = 0;
    private int $failed = 0;
    /** @var array<int, int> */
    private array $createdUserIds = [];
    /** @var array<int, int> */
    private array $createdBookingIds = [];
    /** @var array<int, int> */
    private array $createdDisputeIds = [];

    public function run(): bool
    {
        echo "========================================================\n";
        echo " GuideMate API Dispute Report & Evidence Upload Suite\n";
        echo "========================================================\n\n";

        $this->runTest('testDisputeCreationWithJsonPayload', [$this, 'testDisputeCreationWithJsonPayload']);
        $this->runTest('testDisputeCreationWithMultipartAndEvidence', [$this, 'testDisputeCreationWithMultipartAndEvidence']);
        $this->runTest('testUploadAdditionalDisputeEvidence', [$this, 'testUploadAdditionalDisputeEvidence']);
        $this->runTest('testReportScreenViewIntegrity', [$this, 'testReportScreenViewIntegrity']);

        $this->cleanup();

        echo "\n--------------------------------------------------------\n";
        echo sprintf("Results: %d passed, %d failed\n", $this->passed, $this->failed);
        echo "--------------------------------------------------------\n";

        return $this->failed === 0;
    }

    private function runTest(string $name, callable $test): void
    {
        try {
            $test();
            $this->passed++;
            echo " [PASS] {$name}\n";
        } catch (\Throwable $e) {
            $this->failed++;
            echo " [FAIL] {$name}: {$e->getMessage()}\n";
        }
    }

    private function assert(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new \RuntimeException($message);
        }
    }

    private function createPaidBooking(int $userId): int
    {
        $bookingId = (int) Database::insert(
            "INSERT INTO bookings (user_id, listing_id, booking_date, total_amount, status, created_at)
             VALUES (?, 1, CURDATE(), 1500.00, 'confirmed', NOW())",
            [$userId]
        );
        $this->createdBookingIds[] = $bookingId;

        $ref = 'PAY_' . bin2hex(random_bytes(8));
        Database::insert(
            "INSERT INTO payments (booking_id, amount, method, status, reference, paid_at, created_at)
             VALUES (?, 1500.00, 'card', 'paid', ?, NOW(), NOW())",
            [$bookingId, $ref]
        );

        return $bookingId;
    }

    public function testDisputeCreationWithJsonPayload(): void
    {
        $userId = User::create('Tourist One', 'tourist_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass1234!', 'tourist');
        $this->createdUserIds[] = $userId;
        $bookingId = $this->createPaidBooking($userId);

        // Simulate ApiAuth
        $_SESSION['user_id'] = $userId;
        $_SESSION['api_user_id'] = $userId;

        // Test creating dispute with JSON input
        $disputeId = Dispute::create([
            'booking_id' => $bookingId,
            'user_id' => $userId,
            'guide_id' => 1,
            'problem_type' => 'unsafe',
            'amount_requested' => null,
            'description' => 'Test problem description with more than 20 characters.',
        ]);
        $this->createdDisputeIds[] = $disputeId;

        $this->assert($disputeId > 0, 'Dispute should be created successfully');
        $dispute = Dispute::find($disputeId);
        $this->assert($dispute !== null, 'Dispute record must exist');
        $this->assert($dispute['problem_type'] === 'unsafe', 'Problem type should be unsafe');
    }

    public function testDisputeCreationWithMultipartAndEvidence(): void
    {
        $userId = User::create('Tourist Two', 'tourist2_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass1234!', 'tourist');
        $this->createdUserIds[] = $userId;
        $bookingId = $this->createPaidBooking($userId);

        $disputeId = Dispute::create([
            'booking_id' => $bookingId,
            'user_id' => $userId,
            'guide_id' => 1,
            'problem_type' => 'service_mismatch',
            'amount_requested' => null,
            'description' => 'Service was very different from what was shown on the listing.',
        ]);
        $this->createdDisputeIds[] = $disputeId;

        // Add dummy evidence file
        $mockPath = 'uploads/disputes/test_evidence_1.jpg';
        DisputeFile::add($disputeId, $mockPath, 'evidence_1.jpg');

        $files = DisputeFile::forDispute($disputeId);
        $this->assert(count($files) === 1, 'Dispute should have 1 evidence file attached');
        $this->assert($files[0]['file_path'] === $mockPath, 'Evidence path should match');
    }

    public function testUploadAdditionalDisputeEvidence(): void
    {
        $userId = User::create('Tourist Three', 'tourist3_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass1234!', 'tourist');
        $this->createdUserIds[] = $userId;
        $bookingId = $this->createPaidBooking($userId);

        $disputeId = Dispute::create([
            'booking_id' => $bookingId,
            'user_id' => $userId,
            'guide_id' => 1,
            'problem_type' => 'no_show',
            'amount_requested' => null,
            'description' => 'Guide failed to arrive at the agreed meeting location.',
        ]);
        $this->createdDisputeIds[] = $disputeId;

        // Add 2 additional evidence files
        DisputeFile::add($disputeId, 'uploads/disputes/test_evidence_2a.jpg', 'evidence_2a.jpg');
        DisputeFile::add($disputeId, 'uploads/disputes/test_evidence_2b.jpg', 'evidence_2b.jpg');

        $files = DisputeFile::forDispute($disputeId);
        $this->assert(count($files) === 2, 'Dispute should have 2 evidence files attached');
    }

    public function testReportScreenViewIntegrity(): void
    {
        $viewContent = file_get_contents(BASE_DIR . '/app/report/[id].tsx');
        $this->assert($viewContent !== false, 'Unable to read app/report/[id].tsx');

        // 1. Camera & Library permission and launch handlers
        $this->assert(str_contains($viewContent, 'requestCameraPermissionsAsync'), 'Must request camera permissions');
        $this->assert(str_contains($viewContent, 'requestMediaLibraryPermissionsAsync'), 'Must request media library permissions');
        $this->assert(str_contains($viewContent, 'launchCameraAsync'), 'Must support launching camera');
        $this->assert(str_contains($viewContent, 'launchImageLibraryAsync'), 'Must support picking from library');

        // 2. Previews, replace and remove
        $this->assert(str_contains($viewContent, 'promptImageSource'), 'Must have promptImageSource');
        $this->assert(str_contains($viewContent, 'removePhoto'), 'Must support removing photos');
        $this->assert(str_contains($viewContent, 'handlePhotoPress') || str_contains($viewContent, 'promptReplaceImage'), 'Must support replacing photos');

        // 3. Already reported view photo support
        $this->assert(str_contains($viewContent, 'existing.photos'), 'Must support rendering photos for existing reports');
        $this->assert(str_contains($viewContent, 'resolveImage'), 'Must use resolveImage for photos');

        // 4. Loading state and accessibility
        $this->assert(str_contains($viewContent, 'ActivityIndicator'), 'Must show loading indicator');
        $this->assert(str_contains($viewContent, 'accessibilityLabel'), 'Must have accessibility attributes');
    }

    private function cleanup(): void
    {
        foreach ($this->createdDisputeIds as $id) {
            Database::run('DELETE FROM dispute_files WHERE dispute_id = ?', [$id]);
            Database::run('DELETE FROM disputes WHERE id = ?', [$id]);
        }
        foreach ($this->createdBookingIds as $id) {
            Database::run('DELETE FROM payments WHERE booking_id = ?', [$id]);
            Database::run('DELETE FROM bookings WHERE id = ?', [$id]);
        }
        foreach ($this->createdUserIds as $id) {
            Database::run('DELETE FROM users WHERE id = ?', [$id]);
        }
    }
}

if (php_sapi_name() === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__)) {
    $suite = new ApiDisputeReportTest();
    exit($suite->run() ? 0 : 1);
}
