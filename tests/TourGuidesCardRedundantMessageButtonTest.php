<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

/**
 * Test Tour Guides Card Redundant Message Button Removal & Menu Preservation
 */
final class TourGuidesCardRedundantMessageButtonTest
{
    private array $results = [];

    public function run(): void
    {
        echo "\n======================================================\n";
        echo "   TOUR GUIDES CARD REDUNDANT MESSAGE BUTTON TEST\n";
        echo "======================================================\n\n";

        $this->testRedundantMessageButtonRemoved();
        $this->testUnusedStylesRemoved();
        $this->testThreeDotMenuPreservedAndAccessible();
        $this->testThreeDotModalActionsPreserved();
        $this->testCardLayoutAndVisualBalance();
        $this->printSummary();
    }

    private function assert(bool $condition, string $description): void
    {
        if ($condition) {
            $this->results[] = ['status' => 'PASS', 'desc' => $description];
            echo "  [PASS] {$description}\n";
        } else {
            $this->results[] = ['status' => 'FAIL', 'desc' => $description];
            echo "  [FAIL] {$description}\n";
        }
    }

    private function testRedundantMessageButtonRemoved(): void
    {
        echo "--- 1. Testing Standalone Message Button Removal ---\n";
        $guidesTsx = file_get_contents(__DIR__ . '/../app/guides.tsx');

        // Verify standalone Message button has been removed from card
        $this->assert(!str_contains($guidesTsx, 'styles.msgBtn'), "styles.msgBtn is not used in card");
        $this->assert(!str_contains($guidesTsx, 'styles.msgBtnText'), "styles.msgBtnText is not used in card");
        $this->assert(!preg_match('/<TouchableOpacity[^>]*styles\.msgBtn/', $guidesTsx), "No TouchableOpacity with msgBtn exists");
        $this->assert(!str_contains($guidesTsx, 'Tap Message to plan your tour'), "Header subtitle does not refer to 'Tap Message'");
    }

    private function testUnusedStylesRemoved(): void
    {
        echo "\n--- 2. Testing Unused Styles & Dead Code Removal ---\n";
        $guidesTsx = file_get_contents(__DIR__ . '/../app/guides.tsx');

        $this->assert(!str_contains($guidesTsx, 'msgBtn:'), "msgBtn style definition removed from StyleSheet");
        $this->assert(!str_contains($guidesTsx, 'msgBtnText:'), "msgBtnText style definition removed from StyleSheet");
        $this->assert(!str_contains($guidesTsx, 'actionCol:'), "actionCol style definition removed from StyleSheet");
        $this->assert(!str_contains($guidesTsx, 'styles.actionCol'), "styles.actionCol wrapper removed from card");
    }

    private function testThreeDotMenuPreservedAndAccessible(): void
    {
        echo "\n--- 3. Testing Three-Dot Menu Trigger Preservation & Accessibility ---\n";
        $guidesTsx = file_get_contents(__DIR__ . '/../app/guides.tsx');

        $this->assert(str_contains($guidesTsx, 'styles.moreBtn'), "moreBtn style is used for three-dot menu trigger");
        $this->assert(str_contains($guidesTsx, 'name="ellipsis-vertical"'), "moreBtn renders ellipsis-vertical icon");
        $this->assert(str_contains($guidesTsx, 'accessibilityRole="button"'), "moreBtn has accessibilityRole='button'");
        $this->assert(str_contains($guidesTsx, 'accessibilityLabel={`Options for ${item.name}`}'), "moreBtn has dynamic accessibilityLabel for screen readers");
        $this->assert(str_contains($guidesTsx, 'onPress={() => setActiveMenuGuide(item)}'), "moreBtn activates the activeMenuGuide modal state");
    }

    private function testThreeDotModalActionsPreserved(): void
    {
        echo "\n--- 4. Testing Modal Actions & Messaging Functionality Preserved ---\n";
        $guidesTsx = file_get_contents(__DIR__ . '/../app/guides.tsx');

        // Check modal presence
        $this->assert(str_contains($guidesTsx, 'activeMenuGuide !== null'), "Menu modal opens when activeMenuGuide is set");

        // Check View Guide Profile option
        $this->assert(str_contains($guidesTsx, 'View Guide Profile'), "Menu modal contains 'View Guide Profile'");
        $this->assert(str_contains($guidesTsx, 'router.push(`/guide/${id}`'), "View Guide Profile navigates to /guide/\${id}");

        // Check Message Guide option (messaging option fully functional)
        $this->assert(str_contains($guidesTsx, 'Message Guide'), "Menu modal contains 'Message Guide'");
        $this->assert(str_contains($guidesTsx, 'router.push(`/chat/${id}`'), "Message Guide navigates to /chat/\${id}");
        $this->assert(str_contains($guidesTsx, 'name="chatbubble-outline"'), "Message Guide option renders chatbubble icon");

        // Check Cancel button
        $this->assert(str_contains($guidesTsx, 'Cancel'), "Menu modal has Cancel action");
    }

    private function testCardLayoutAndVisualBalance(): void
    {
        echo "\n--- 5. Testing Card Layout & Visual Balance ---\n";
        $guidesTsx = file_get_contents(__DIR__ . '/../app/guides.tsx');

        // Check guide details are preserved
        $this->assert(str_contains($guidesTsx, 'styles.avatarWrap'), "Guide avatar wrapper is preserved");
        $this->assert(str_contains($guidesTsx, 'styles.onlineDot'), "Guide online status dot is preserved");
        $this->assert(str_contains($guidesTsx, 'styles.info'), "Guide info section is preserved with flex: 1");
        $this->assert(str_contains($guidesTsx, 'styles.name'), "Guide name is preserved");
        $this->assert(str_contains($guidesTsx, 'styles.availPill'), "Guide availability status pill is preserved");
        $this->assert(str_contains($guidesTsx, 'styles.metaRow'), "Guide rating and completed tours meta row is preserved");
        $this->assert(str_contains($guidesTsx, 'styles.bio'), "Guide bio is preserved");

        // Check moreBtn sizing and hitSlop
        $this->assert(str_contains($guidesTsx, 'hitSlop='), "moreBtn includes hitSlop for accessible touch target");
    }

    private function printSummary(): void
    {
        $total = count($this->results);
        $passed = count(array_filter($this->results, fn($r) => $r['status'] === 'PASS'));
        $failed = $total - $passed;

        echo "\n============================================\n";
        echo "TOTAL TESTS: {$total} | PASSED: {$passed} | FAILED: {$failed}\n";
        echo "============================================\n";

        if ($failed > 0) {
            exit(1);
        }
    }
}

$test = new TourGuidesCardRedundantMessageButtonTest();
$test->run();
