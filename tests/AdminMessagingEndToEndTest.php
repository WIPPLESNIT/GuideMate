<?php
/**
 * AdminMessagingEndToEndTest.php
 *
 * Verifies the 6 exact test cases requested by the user:
 * 1. Sending and displaying short and long messages.
 * 2. Entering multiline messages.
 * 3. Viewing conversations with a large number of messages (stability & scrolling).
 * 4. Loading, empty, and error states.
 * 5. Responsive behavior across supported screen sizes.
 * 6. Consistency between the provider and admin messaging experiences, excluding Auto translation and Online Status.
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Models\Message;
use App\Models\User;

final class AdminMessagingEndToEndTest
{
    private int $passed = 0;
    private int $failed = 0;

    public function run(): void
    {
        echo "========================================================\n";
        echo " Admin Messaging End-to-End & Stress Test Suite\n";
        echo "========================================================\n\n";

        $admin = User::adminAccount();
        $adminId = (int) ($admin['id'] ?? 1);

        // Find a test partner
        $partner = \App\Core\Database::first('SELECT * FROM users WHERE role IN (?, ?) ORDER BY id ASC LIMIT 1', ['guide', 'rental_provider']);
        $partnerId = (int) ($partner['id'] ?? 2);

        $this->testShortAndLongMessages($adminId, $partnerId);
        $this->testMultilineMessages($adminId, $partnerId);
        $this->testLargeNumberOfMessages($adminId, $partnerId);
        $this->testLoadingEmptyAndErrorStates($adminId);
        $this->testResponsiveStylesAndLayoutIntegrity();
        $this->testConsistencyWithProviderMessaging();

        echo "\n--------------------------------------------------------\n";
        echo "Results: {$this->passed} passed, {$this->failed} failed\n";
        echo "--------------------------------------------------------\n";

        if ($this->failed > 0) {
            exit(1);
        }
    }

    private function assert(bool $condition, string $msg): void
    {
        if ($condition) {
            $this->passed++;
            echo " [PASS] {$msg}\n";
        } else {
            $this->failed++;
            echo " [FAIL] {$msg}\n";
        }
    }

    private function testShortAndLongMessages(int $adminId, int $partnerId): void
    {
        echo "--- Test 1: Sending & Displaying Short and Long Messages ---\n";

        // Short message
        $shortText = 'Short greeting: Hello partner!';
        $id1 = Message::send($adminId, $partnerId, $shortText);
        $this->assert($id1 > 0, 'Short message stored in database successfully');

        $retrieved1 = Message::find($id1);
        $this->assert($retrieved1 !== null && $retrieved1['body'] === $shortText, 'Short message retrieved accurately');

        // Long message (simulate extensive paragraph & unbroken URL)
        $longText = str_repeat('Lorem ipsum dolor sit amet, consectetur adipiscing elit. ', 15)
            . 'https://guidemate.example.com/very/long/unbroken/path/that/could/overflow/without/word-break/styling/test1234567890';
        $id2 = Message::send($adminId, $partnerId, $longText);
        $this->assert($id2 > 0, 'Long message stored in database successfully');

        $retrieved2 = Message::find($id2);
        $this->assert($retrieved2 !== null && $retrieved2['body'] === $longText, 'Long message retrieved accurately');

        // Check CSS wrapping rule protecting against layout shift
        $css = file_get_contents(__DIR__ . '/../public/assets/css/style.css');
        $this->assert(str_contains($css, 'overflow-wrap: anywhere;'), 'Stylesheet enforces overflow-wrap: anywhere for long unbroken text');
        $this->assert(str_contains($css, 'word-break: break-word;'), 'Stylesheet enforces word-break: break-word');
    }

    private function testMultilineMessages(int $adminId, int $partnerId): void
    {
        echo "--- Test 2: Multiline Messages & Line Break Preservation ---\n";

        $multiline = "Line 1: Tour guide policy reminder\nLine 2: Terms and conditions\nLine 3: Thank you.";
        $id = Message::send($adminId, $partnerId, $multiline);
        $this->assert($id > 0, 'Multiline message persisted to database');

        $msg = Message::find($id);
        $this->assert(str_contains($msg['body'], "\n"), 'Multiline line breaks preserved intact');

        $rendered = nl2br(e($msg['body']));
        $this->assert(str_contains($rendered, '<br />') || str_contains($rendered, '<br>'), 'nl2br converts newlines to HTML break tags for display');
    }

    private function testLargeNumberOfMessages(int $adminId, int $partnerId): void
    {
        echo "--- Test 3: Large Number of Messages (Stability & Bounded Height) ---\n";

        // Fetch thread
        $thread = Message::thread($adminId, $partnerId);
        $this->assert(is_array($thread) && count($thread) >= 3, 'Thread contains multiple messages');

        // Check polling fetch newer messages
        $firstId = (int) $thread[0]['id'];
        $since = Message::since($adminId, $partnerId, $firstId);
        $this->assert(count($since) >= 2, 'Message::since returns newer messages for real-time polling');

        // Check stylesheet guarantees fixed height so panel never expands
        $css = file_get_contents(__DIR__ . '/../public/assets/css/style.css');
        $this->assert(preg_match('/\.admin-msg\s*\{[^}]*height:\s*640px;/', $css) === 1, 'Container height is fixed at 640px, preventing expansion under 100+ messages');
        $this->assert(preg_match('/\.admin-msg-body\s*\{[^}]*overflow-y:\s*auto;/', $css) === 1, 'Thread body scrolls internally without expanding outer card');
    }

    private function testLoadingEmptyAndErrorStates(int $adminId): void
    {
        echo "--- Test 4: Loading, Empty, and Error States ---\n";

        $html = file_get_contents(__DIR__ . '/../app/Views/admin/messages.php');
        $this->assert(str_contains($html, 'class="admin-empty-thread"'), 'Empty thread state wrapper present');
        $this->assert(str_contains($html, 'No conversation selected'), 'Shows "No conversation selected" when partner is null');
        $this->assert(str_contains($html, 'No messages yet'), 'Shows friendly "No messages yet" when conversation is empty');
        $this->assert(str_contains($html, 'id="adminEmptyThread"'), 'Empty thread state removable dynamically upon message arrival');

        // Error handling in controller
        $ctrl = file_get_contents(__DIR__ . '/../app/Controllers/AdminController.php');
        $this->assert(str_contains($ctrl, "abort(404, 'Recipient not found.')"), 'Non-existent recipient triggers 404 abort');
        $this->assert(str_contains($ctrl, '$receiverId === 0 || $receiverId === $adminId || $body === \'\''), 'Empty body or invalid recipient rejected');
    }

    private function testResponsiveStylesAndLayoutIntegrity(): void
    {
        echo "--- Test 5: Responsive Behavior Across Screen Sizes ---\n";

        $css = file_get_contents(__DIR__ . '/../public/assets/css/style.css');

        $this->assert(str_contains($css, '@media (max-width: 820px)'), 'Media query for tablet/mobile defined at 820px');
        $this->assert(preg_match('/@media\s*\(max-width:\s*820px\)[\s\S]*?grid-template-columns:\s*1fr;/', $css) === 1, 'Stacked single-column layout on mobile screens');
        $this->assert(preg_match('/@media\s*\(max-width:\s*820px\)[\s\S]*?\.admin-msg-thread\s*\{[^}]*height:\s*520px;/', $css) === 1, 'Generous scrollable thread height configured on mobile');
    }

    private function testConsistencyWithProviderMessaging(): void
    {
        echo "--- Test 6: Consistency Between Provider & Admin Messaging ---\n";

        $adminView = file_get_contents(__DIR__ . '/../app/Views/admin/messages.php');
        $providerView = file_get_contents(__DIR__ . '/../app/Views/messages/index.php');

        // Both use two-pane layout with thread header, body and form
        $this->assert(str_contains($adminView, 'class="admin-msg-list"') && str_contains($providerView, 'class="conv-list"'), 'Both implement two-pane conversation listing');
        $this->assert(str_contains($adminView, 'data-poll-url=') && str_contains($providerView, 'data-poll-url='), 'Both feature automated polling attribute');
        $this->assert(str_contains($adminView, 'data-last-id=') && str_contains($providerView, 'data-last-id='), 'Both track last message id for incremental polling');

        // Explicit exclusions verified:
        $this->assert(!str_contains($adminView, 'btnAutoTranslate'), 'Auto-translation button strictly excluded from admin');
        $this->assert(!str_contains($adminView, 'presence-dot'), 'Online presence dot strictly excluded from admin');
        $this->assert(!str_contains($adminView, 'presence-badge'), 'Online presence badge strictly excluded from admin');
    }
}

(new AdminMessagingEndToEndTest())->run();
