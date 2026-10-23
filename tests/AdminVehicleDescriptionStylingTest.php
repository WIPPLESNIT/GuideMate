<?php

declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/../bootstrap.php';

class AdminVehicleDescriptionStylingTest
{
    private int $passed = 0;
    private int $failed = 0;

    public function run(): bool
    {
        echo "========================================================\n";
        echo " GuideMate Admin Vehicle Description Styling & Layout Tests\n";
        echo "========================================================\n\n";

        $this->testTemplateMarkupAndClasses();
        $this->testCssStyleRulesExist();
        $this->testShortDescriptionRendering();
        $this->testLongDescriptionWithParagraphsAndWrapping();
        $this->testExtensiveUnbrokenInputWrapping();
        $this->testEmptyDescriptionGracefulFallback();
        $this->testNoHardcodedBlackTextColorRemains();
        $this->testNoLeadingWhitespaceOrNewlineInDescriptionOutput();

        echo "\n--------------------------------------------------------\n";
        echo "Results: {$this->passed} passed, {$this->failed} failed\n";
        echo "--------------------------------------------------------\n\n";

        return $this->failed === 0;
    }

    private function testTemplateMarkupAndClasses(): void
    {
        $viewContent = file_get_contents(__DIR__ . '/../app/Views/admin/vehicle-show.php');

        $this->assert(
            strpos($viewContent, 'class="admin-vehicle-desc"') !== false,
            'testTemplateMarkupAndClasses: contains admin-vehicle-desc container class'
        );

        $this->assert(
            strpos($viewContent, 'class="admin-vehicle-layout"') !== false,
            'testTemplateMarkupAndClasses: contains admin-vehicle-layout grid class'
        );

        $this->assert(
            strpos($viewContent, 'class="admin-vehicle-main"') !== false,
            'testTemplateMarkupAndClasses: contains admin-vehicle-main column class'
        );

        $this->assert(
            strpos($viewContent, 'class="admin-vehicle-section-title"') !== false,
            'testTemplateMarkupAndClasses: contains admin-vehicle-section-title class'
        );
    }

    private function testCssStyleRulesExist(): void
    {
        $cssContent = file_get_contents(__DIR__ . '/../public/assets/css/style.css');

        $this->assert(
            strpos($cssContent, '.admin-vehicle-desc') !== false,
            'testCssStyleRulesExist: .admin-vehicle-desc selector defined in stylesheet'
        );

        $this->assert(
            strpos($cssContent, '.admin-body .admin-vehicle-desc') !== false,
            'testCssStyleRulesExist: .admin-body .admin-vehicle-desc rule defined'
        );

        $this->assert(
            strpos($cssContent, 'word-break: break-word') !== false,
            'testCssStyleRulesExist: word-break: break-word rule configured'
        );

        $this->assert(
            strpos($cssContent, 'overflow-wrap: break-word') !== false,
            'testCssStyleRulesExist: overflow-wrap: break-word rule configured'
        );

        $this->assert(
            strpos($cssContent, '@media (max-width: 900px)') !== false,
            'testCssStyleRulesExist: responsive media query exists for tablet/mobile layout'
        );
    }

    private function testShortDescriptionRendering(): void
    {
        $vehicle = $this->createMockVehicle('The vehicle condition is all good, with no dents or damage.');
        $rendered = $this->renderVehicleShowView($vehicle);

        $this->assert(
            strpos($rendered, 'The vehicle condition is all good, with no dents or damage.') !== false,
            'testShortDescriptionRendering: short description rendered accurately'
        );

        $this->assert(
            strpos($rendered, 'class="admin-vehicle-desc"') !== false,
            'testShortDescriptionRendering: wrapped in admin-vehicle-desc'
        );

        $this->assert(
            strpos($rendered, 'color:#ffffff') !== false || strpos($rendered, 'color: #ffffff') !== false,
            'testShortDescriptionRendering: description text color set to white'
        );
    }

    private function testLongDescriptionWithParagraphsAndWrapping(): void
    {
        $longText = "First paragraph: Excellent condition scooter with low mileage.\n\n" .
                    "Second paragraph: Rental terms require a valid driver's license and security deposit. " .
                    "Fuel must be replenished before return. Helmet is included for the primary rider.\n\n" .
                    "Third paragraph: Delivery available within 10km radius upon advance request.";

        $vehicle = $this->createMockVehicle($longText);
        $rendered = $this->renderVehicleShowView($vehicle);

        $this->assert(
            strpos($rendered, 'First paragraph:') !== false &&
            strpos($rendered, 'Second paragraph:') !== false &&
            strpos($rendered, 'Third paragraph:') !== false,
            'testLongDescriptionWithParagraphsAndWrapping: all paragraphs rendered cleanly'
        );

        $this->assert(
            strpos($rendered, 'white-space:pre-line') !== false || strpos($rendered, 'white-space: pre-line') !== false,
            'testLongDescriptionWithParagraphsAndWrapping: line breaks preserved with white-space:pre-line'
        );
    }

    private function testExtensiveUnbrokenInputWrapping(): void
    {
        $unbrokenText = "VehicleInspectionReportLink:https://guidemate.example.com/rentals/vehicle/2022-honda-click-125i-automatic-v2-edition-minglanilla-cebu-philippines-inspection-details-and-specifications";

        $vehicle = $this->createMockVehicle($unbrokenText);
        $rendered = $this->renderVehicleShowView($vehicle);

        $this->assert(
            strpos($rendered, 'overflow-wrap:break-word') !== false || strpos($rendered, 'overflow-wrap: break-word') !== false,
            'testExtensiveUnbrokenInputWrapping: contains overflow-wrap:break-word to prevent horizontal overflow'
        );

        $this->assert(
            strpos($rendered, 'word-break:break-word') !== false || strpos($rendered, 'word-break: break-word') !== false,
            'testExtensiveUnbrokenInputWrapping: contains word-break:break-word for robust wrapping'
        );
    }

    private function testEmptyDescriptionGracefulFallback(): void
    {
        $vehicle = $this->createMockVehicle('');
        $rendered = $this->renderVehicleShowView($vehicle);

        $this->assert(
            strpos($rendered, 'admin-vehicle-desc-empty') !== false,
            'testEmptyDescriptionGracefulFallback: displays empty description placeholder'
        );

        $this->assert(
            strpos($rendered, 'No description provided') !== false,
            'testEmptyDescriptionGracefulFallback: shows friendly fallback message'
        );
    }

    private function testNoHardcodedBlackTextColorRemains(): void
    {
        $viewContent = file_get_contents(__DIR__ . '/../app/Views/admin/vehicle-show.php');

        // Confirm the old black text style color:#334155 in description is gone
        $this->assert(
            strpos($viewContent, 'color:#334155;white-space:pre-line;') === false,
            'testNoHardcodedBlackTextColorRemains: old dark text color (#334155) completely removed from description'
        );
    }

    private function testNoLeadingWhitespaceOrNewlineInDescriptionOutput(): void
    {
        $text = "The vehicle is in excellent overall condition, both inside and out. The exterior body is free of any dents, scratches, or visible damage. Everything is fully functional and ready to go.";
        $vehicle = $this->createMockVehicle($text);
        $rendered = $this->renderVehicleShowView($vehicle);

        if (preg_match('/<div class="admin-vehicle-desc"[^>]*>(.*?)<\/div>/s', $rendered, $matches)) {
            $inner = $matches[1];
            $this->assert(
                !str_starts_with($inner, "\n") && !str_starts_with($inner, "\r\n") && !str_starts_with($inner, " "),
                'testNoLeadingWhitespaceOrNewlineInDescriptionOutput: no leading newline or whitespace rendered inside pre-line container'
            );
            $this->assert(
                str_starts_with($inner, "The vehicle is in excellent overall condition"),
                'testNoLeadingWhitespaceOrNewlineInDescriptionOutput: description starts immediately at the beginning of the container'
            );
        } else {
            $this->assert(false, 'testNoLeadingWhitespaceOrNewlineInDescriptionOutput: admin-vehicle-desc container not found in rendered HTML');
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function createMockVehicle(string $description): array
    {
        return [
            'id' => 9991,
            'name' => '2022 Honda Click 125i V2 Automatic',
            'location' => 'Minglanilla, Cebu',
            'address' => 'Purok Guyabano, Kilid Hopian',
            'year' => 2022,
            'make' => 'Honda',
            'model' => 'Click 125i V2',
            'specs' => 'Automatic Transmission',
            'image' => null,
            'provider_name' => 'Miguel Yongzon',
            'provider_email' => 'migyong@sample.com',
            'provider_phone' => '09123456789',
            'user_id' => 370,
            'type' => 'Scooter',
            'price_per_day' => 1010.00,
            'status' => 'pending',
            'description' => $description,
            'created_at' => '2026-10-23 07:00:00',
            'submitted_at' => '2026-10-23 07:52:00',
            'reviewed_at' => null,
            'reviewer_name' => null,
            'rejection_reason' => null,
        ];
    }

    /**
     * @param array<string,mixed> $vehicle
     */
    private function renderVehicleShowView(array $vehicle): string
    {
        $gallery = [];
        $specs = ['Automatic Transmission'];
        ob_start();
        include __DIR__ . '/../app/Views/admin/vehicle-show.php';
        return (string) ob_get_clean();
    }

    private function assert(bool $condition, string $message): void
    {
        if ($condition) {
            $this->passed++;
            echo " [PASS] {$message}\n";
        } else {
            $this->failed++;
            echo " [FAIL] {$message}\n";
        }
    }
}

$test = new AdminVehicleDescriptionStylingTest();
$success = $test->run();
exit($success ? 0 : 1);
