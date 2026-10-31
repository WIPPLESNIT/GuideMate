<?php

declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Database;
use App\Models\RentalReportFile;
use App\Models\RentalRequest;
use App\Models\User;

final class RentalReportImageAttachmentTest
{
    private int $passed = 0;
    private int $failed = 0;
    /** @var array<int, int> */
    private array $createdUserIds = [];
    /** @var array<int, int> */
    private array $createdRentalIds = [];

    public function run(): bool
    {
        echo "========================================================\n";
        echo " GuideMate Rental Report Image Attachment Suite\n";
        echo "========================================================\n\n";

        $this->runTest('testRentalReportFilesTableAndModel', [$this, 'testRentalReportFilesTableAndModel']);
        $this->runTest('testReportRentalWithJsonPayloadNoPhotos', [$this, 'testReportRentalWithJsonPayloadNoPhotos']);
        $this->runTest('testReportRentalWithMultipartEvidence', [$this, 'testReportRentalWithMultipartEvidence']);
        $this->runTest('testRentalReportMobileAppViewIntegrity', [$this, 'testRentalReportMobileAppViewIntegrity']);
        $this->runTest('testWebRentalRequestsModalPhotoEvidence', [$this, 'testWebRentalRequestsModalPhotoEvidence']);

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

    private function createPaidRental(int $userId): int
    {
        $rentalId = (int) Database::insert(
            "INSERT INTO rental_requests (
                user_id, vehicle_id, vehicle_name, vehicle_type, shop_name, location,
                pickup_date, rental_days, total_amount, customer_name, customer_email,
                status, payment_status, created_at
            ) VALUES (
                ?, 'veh-test-99', 'Yamaha NMAX 155', 'Scooter', 'Cebu Moto Rentals', 'Cebu City',
                CURDATE(), 2, 1600.00, 'Test Tourist', 'tourist_rental@example.com',
                'approved', 'paid', NOW()
            )",
            [$userId]
        );
        $this->createdRentalIds[] = $rentalId;
        return $rentalId;
    }

    public function testRentalReportFilesTableAndModel(): void
    {
        $userId = User::create('Tourist One', 'rent_user1_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass1234!', 'tourist');
        $this->createdUserIds[] = $userId;
        $rentalId = $this->createPaidRental($userId);

        // Add 2 dummy evidence files
        RentalReportFile::add($rentalId, 'uploads/rentals/reports/test_evidence_1.jpg', 'defect_1.jpg');
        RentalReportFile::add($rentalId, 'uploads/rentals/reports/test_evidence_2.jpg', 'odometer.jpg');

        $files = RentalReportFile::forRental($rentalId);
        $this->assert(count($files) === 2, 'Should retrieve 2 rental report files');
        $this->assert($files[0]['label'] === 'defect_1.jpg', 'First label should match');
        $this->assert($files[1]['label'] === 'odometer.jpg', 'Second label should match');

        RentalReportFile::deleteForRental($rentalId);
        $remaining = RentalReportFile::forRental($rentalId);
        $this->assert(count($remaining) === 0, 'Should have deleted all files for rental');
    }

    public function testReportRentalWithJsonPayloadNoPhotos(): void
    {
        $userId = User::create('Tourist Two', 'rent_user2_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass1234!', 'tourist');
        $this->createdUserIds[] = $userId;
        $rentalId = $this->createPaidRental($userId);

        RentalRequest::report($rentalId, 'vehicle_defect', 'The front brake was squeaking and unit was not safe to ride.');

        $fresh = RentalRequest::find($rentalId);
        $this->assert($fresh !== null, 'Rental request must exist');
        $this->assert($fresh['report_status'] === 'open', 'Report status must be open');
        $this->assert($fresh['report_type'] === 'vehicle_defect', 'Report type must match');

        $files = RentalReportFile::forRental($rentalId);
        $this->assert(count($files) === 0, 'Should have 0 files for text-only submission');
    }

    public function testReportRentalWithMultipartEvidence(): void
    {
        $userId = User::create('Tourist Three', 'rent_user3_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass1234!', 'tourist');
        $this->createdUserIds[] = $userId;
        $rentalId = $this->createPaidRental($userId);

        RentalRequest::report($rentalId, 'not_delivered', 'The vehicle was never delivered to the airport meeting point.');
        RentalReportFile::add($rentalId, 'uploads/rentals/reports/test_airport_chat.jpg', 'chat_screenshot.jpg');

        $fresh = RentalRequest::find($rentalId);
        $this->assert($fresh['report_status'] === 'open', 'Report status must be open');

        $files = RentalReportFile::forRental($rentalId);
        $this->assert(count($files) === 1, 'Should have 1 evidence file attached');
        $this->assert(str_contains($files[0]['file_path'], 'test_airport_chat.jpg'), 'File path should match');
    }

    public function testRentalReportMobileAppViewIntegrity(): void
    {
        $viewContent = file_get_contents(BASE_DIR . '/app/rental-report/[id].tsx');
        $this->assert($viewContent !== false, 'Unable to read app/rental-report/[id].tsx');

        // 1. ImagePicker import and MAX_PHOTOS
        $this->assert(str_contains($viewContent, "import * as ImagePicker from 'expo-image-picker'"), 'Must import expo-image-picker');
        $this->assert(str_contains($viewContent, 'MAX_PHOTOS = 4'), 'Must define MAX_PHOTOS limit of 4');

        // 2. Camera and Library permission handlers
        $this->assert(str_contains($viewContent, 'requestCameraPermissionsAsync'), 'Must request camera permissions');
        $this->assert(str_contains($viewContent, 'requestMediaLibraryPermissionsAsync'), 'Must request media library permissions');
        $this->assert(str_contains($viewContent, 'launchCameraAsync'), 'Must support launching camera');
        $this->assert(str_contains($viewContent, 'launchImageLibraryAsync'), 'Must support picking from library');

        // 3. Previews, replace and remove
        $this->assert(str_contains($viewContent, 'removePhoto'), 'Must support removing photos');
        $this->assert(str_contains($viewContent, 'handlePhotoPress') || str_contains($viewContent, 'promptReplaceImage'), 'Must support replacing or managing photos');
        $this->assert(str_contains($viewContent, 'Attach picture'), 'Must have Attach picture button label');

        // 4. Loading state and accessibility
        $this->assert(str_contains($viewContent, 'ActivityIndicator'), 'Must show loading indicator during submission');
        $this->assert(str_contains($viewContent, 'accessibilityLabel'), 'Must have accessibility attributes');
    }

    public function testWebRentalRequestsModalPhotoEvidence(): void
    {
        $viewContent = file_get_contents(BASE_DIR . '/app/Views/rental/requests.php');
        $this->assert($viewContent !== false, 'Unable to read app/Views/rental/requests.php');

        // 1. Data attribute for photos
        $this->assert(str_contains($viewContent, 'data-photos'), 'Button must pass data-photos attribute');

        // 2. Modal photo evidence container
        $this->assert(str_contains($viewContent, 'problemModalPhotosWrap'), 'Modal must have problemModalPhotosWrap');
        $this->assert(str_contains($viewContent, 'problemModalPhotos'), 'Modal must have problemModalPhotos container');

        // 3. JS parser for modal photos
        $this->assert(str_contains($viewContent, "getAttribute('data-photos')"), 'JS must read data-photos attribute');
    }

    private function cleanup(): void
    {
        foreach ($this->createdRentalIds as $id) {
            Database::run('DELETE FROM rental_report_files WHERE rental_id = ?', [$id]);
            Database::run('DELETE FROM rental_requests WHERE id = ?', [$id]);
        }
        foreach ($this->createdUserIds as $id) {
            Database::run('DELETE FROM users WHERE id = ?', [$id]);
        }
    }
}

if (php_sapi_name() === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__)) {
    $suite = new RentalReportImageAttachmentTest();
    exit($suite->run() ? 0 : 1);
}
