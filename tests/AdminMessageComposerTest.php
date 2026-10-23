<?php
/**
 * AdminMessageComposerTest.php
 *
 * Automated regression and verification tests for the Admin Messaging Feature.
 * Modeled on the Provider/Partner Messaging implementation for layout, behavior,
 * stability, scrolling, empty states, real-time polling, and responsive design.
 */

declare(strict_types=1);

final class AdminMessageComposerTest
{
    private int $passed = 0;
    private int $failed = 0;
    /** @var list<string> */
    private array $failures = [];

    public function run(): void
    {
        echo "========================================================\n";
        echo " GuideMate Admin Messaging Provider-Consistency Tests\n";
        echo "========================================================\n\n";

        $this->testViewMarkupStructure();
        $this->testInputAttributesAndPlaceholder();
        $this->testSendButtonAttributesAndStyling();
        $this->testStylesheetComposerClasses();
        $this->testStylesheetColorsAndPillRadius();
        $this->testLayoutStabilityAndContainerConstraints();
        $this->testBubbleRenderingAndWordWrapping();
        $this->testCustomThemedScrollbars();
        $this->testDateSeparatorsAndTimestamps();
        $this->testEmptyStatesConsistency();
        $this->testRealtimePollingEndpointAndRoute();
        $this->testJsonSendingAndControllerIntegrity();
        $this->testExclusionOfAutoTranslationAndOnlineStatus();
        $this->testPreservationOfAdminPermissionsAndActions();
        $this->testResponsiveStyles();
        $this->testKeyboardUsabilityAndScript();

        echo "\n--------------------------------------------------------\n";
        echo "Results: {$this->passed} passed, {$this->failed} failed\n";
        echo "--------------------------------------------------------\n";

        if ($this->failed > 0) {
            echo "\nFailures:\n";
            foreach ($this->failures as $f) {
                echo " - {$f}\n";
            }
            exit(1);
        }
    }

    private function assert(bool $condition, string $message): void
    {
        if ($condition) {
            $this->passed++;
            echo " [PASS] {$message}\n";
        } else {
            $this->failed++;
            $this->failures[] = $message;
            echo " [FAIL] {$message}\n";
        }
    }

    private function testViewMarkupStructure(): void
    {
        $viewPath = __DIR__ . '/../app/Views/admin/messages.php';
        $this->assert(file_exists($viewPath), 'messages.php view file exists');

        $html = file_get_contents($viewPath);

        $this->assert(str_contains($html, 'class="panel admin-msg-panel"'), 'Wrapper has panel admin-msg-panel class');
        $this->assert(str_contains($html, 'class="admin-msg"'), 'Main grid container has admin-msg class');
        $this->assert(str_contains($html, 'class="admin-msg-list"'), 'Conversation list pane exists');
        $this->assert(str_contains($html, 'class="admin-msg-list-head"'), 'Conversation list has header matching provider design');
        $this->assert(str_contains($html, 'class="admin-msg-thread"'), 'Conversation thread pane exists');
        $this->assert(str_contains($html, 'class="admin-msg-body"'), 'Scrollable message body exists');
        $this->assert(str_contains($html, 'class="admin-msg-form"'), 'Form has class "admin-msg-form"');
        $this->assert(str_contains($html, 'id="adminMsgForm"'), 'Form has id="adminMsgForm"');
        $this->assert(str_contains($html, 'action="<?= e(url(\'/admin/messages/send\')) ?>"'), 'Form posts to /admin/messages/send');
        $this->assert(str_contains($html, 'method="post"'), 'Form method is POST');
        $this->assert(str_contains($html, '<?= csrf_field() ?>'), 'Form includes CSRF token protection');
        $this->assert(str_contains($html, 'name="receiver_id"'), 'Form includes hidden receiver_id input');
        $this->assert(str_contains($html, 'class="admin-msg-input-wrap"'), 'Form wraps input in admin-msg-input-wrap');
    }

    private function testInputAttributesAndPlaceholder(): void
    {
        $html = file_get_contents(__DIR__ . '/../app/Views/admin/messages.php');

        $this->assert(str_contains($html, 'name="body"'), 'Textarea has name="body"');
        $this->assert(str_contains($html, 'id="adminMsgInput"'), 'Textarea has id="adminMsgInput"');
        $this->assert(str_contains($html, 'class="admin-msg-input"'), 'Textarea uses specialized class "admin-msg-input"');
        $this->assert(str_contains($html, 'rows="1"'), 'Textarea defaults to compact single row (rows="1")');
        $this->assert(str_contains($html, 'placeholder="Type a message..."'), 'Textarea placeholder matches reference "Type a message..."');
        $this->assert(str_contains($html, 'aria-label="Type a message"'), 'Textarea has accessible aria-label');
        $this->assert(str_contains($html, 'required'), 'Textarea has HTML5 required validation');
    }

    private function testSendButtonAttributesAndStyling(): void
    {
        $html = file_get_contents(__DIR__ . '/../app/Views/admin/messages.php');

        $this->assert(str_contains($html, 'class="admin-msg-send-btn"'), 'Send button uses class "admin-msg-send-btn"');
        $this->assert(str_contains($html, 'id="adminMsgSendBtn"'), 'Send button has id="adminMsgSendBtn"');
        $this->assert(str_contains($html, 'type="submit"'), 'Send button type is submit');
        $this->assert(preg_match('/<button[^>]*class="admin-msg-send-btn"[^>]*>Send<\/button>/', $html) === 1, 'Send button displays clean "Send" text');
    }

    private function testStylesheetComposerClasses(): void
    {
        $cssPath = __DIR__ . '/../public/assets/css/style.css';
        $this->assert(file_exists($cssPath), 'style.css file exists');

        $css = file_get_contents($cssPath);

        $this->assert(str_contains($css, '.admin-msg-form {'), '.admin-msg-form selector defined in CSS');
        $this->assert(str_contains($css, '.admin-body .admin-msg-form {'), '.admin-body .admin-msg-form selector defined for dark body contrast');
        $this->assert(str_contains($css, '.admin-msg-input-wrap {'), '.admin-msg-input-wrap selector defined');
        $this->assert(str_contains($css, '.admin-msg-input {'), '.admin-msg-input selector defined');
        $this->assert(str_contains($css, '.admin-body .admin-msg-input {'), '.admin-body .admin-msg-input selector defined');
        $this->assert(str_contains($css, '.admin-msg-input::placeholder'), '.admin-msg-input::placeholder selector defined');
        $this->assert(str_contains($css, '.admin-msg-send-btn {'), '.admin-msg-send-btn selector defined');
        $this->assert(str_contains($css, '.admin-msg-send-btn:hover'), '.admin-msg-send-btn:hover selector defined');
        $this->assert(str_contains($css, '.admin-msg-send-btn:focus-visible'), '.admin-msg-send-btn:focus-visible selector defined');
    }

    private function testStylesheetColorsAndPillRadius(): void
    {
        $css = file_get_contents(__DIR__ . '/../public/assets/css/style.css');

        // Form container styling
        $this->assert(preg_match('/\.admin-msg-form\s*\{[^}]*background:\s*#ffffff;/s', $css) === 1, 'Composer container has clean white background (#ffffff)');
        $this->assert(preg_match('/\.admin-msg-form\s*\{[^}]*border-top:\s*1px\s+solid\s+#e5e7eb;/s', $css) === 1, 'Composer has subtle top divider border');

        // Textarea styling
        $this->assert(preg_match('/\.admin-msg-input\s*\{[^}]*height:\s*42px;/s', $css) === 1, 'Textarea base height is 42px');
        $this->assert(preg_match('/\.admin-msg-input\s*\{[^}]*border-radius:\s*8px;/s', $css) === 1, 'Textarea border radius is 8px');
        $this->assert(preg_match('/\.admin-msg-input\s*\{[^}]*resize:\s*none;/s', $css) === 1, 'Textarea manual resizing disabled');

        // Button capsule pill styling
        $this->assert(preg_match('/\.admin-msg-send-btn\s*\{[^}]*border-radius:\s*9999px;/s', $css) === 1, 'Send button has full capsule pill border-radius (9999px)');
        $this->assert(preg_match('/\.admin-msg-send-btn\s*\{[^}]*height:\s*42px;/s', $css) === 1, 'Send button matches input height (42px)');
        $this->assert(preg_match('/\.admin-msg-send-btn\s*\{[^}]*background:\s*var\(--coral-500,\s*#1faa4e\);/s', $css) === 1, 'Send button uses GuideMate brand green background');
        $this->assert(preg_match('/\.admin-msg-send-btn\s*\{[^}]*color:\s*#ffffff;/s', $css) === 1, 'Send button text color is white (#ffffff)');
        $this->assert(preg_match('/\.admin-msg-send-btn\s*\{[^}]*font-weight:\s*700;/s', $css) === 1, 'Send button text is bold (700)');
    }

    private function testLayoutStabilityAndContainerConstraints(): void
    {
        $css = file_get_contents(__DIR__ . '/../public/assets/css/style.css');

        // Verify bounded height preventing layout shifts when many messages exist
        $this->assert(preg_match('/\.admin-msg\s*\{[^}]*grid-template-columns:\s*320px\s+1fr;/s', $css) === 1, 'Admin message container has 320px 1fr columns');
        $this->assert(preg_match('/\.admin-msg\s*\{[^}]*height:\s*640px;/s', $css) === 1, 'Admin message container has stable 640px height');
        $this->assert(preg_match('/\.admin-msg\s*\{[^}]*overflow:\s*hidden;/s', $css) === 1, 'Admin message container has overflow: hidden');

        // Verify thread pane constraints
        $this->assert(preg_match('/\.admin-msg-thread\s*\{[^}]*min-height:\s*0;/s', $css) === 1, 'Thread pane has min-height: 0');
        $this->assert(preg_match('/\.admin-msg-thread\s*\{[^}]*overflow:\s*hidden;/s', $css) === 1, 'Thread pane has overflow: hidden');
        $this->assert(preg_match('/\.admin-msg-body\s*\{[^}]*flex:\s*1\s+1\s+auto;/s', $css) === 1, 'Thread body has flex: 1 1 auto to fill space');
        $this->assert(preg_match('/\.admin-msg-body\s*\{[^}]*min-height:\s*0;/s', $css) === 1, 'Thread body has min-height: 0');
        $this->assert(preg_match('/\.admin-msg-body\s*\{[^}]*overflow-y:\s*auto;/s', $css) === 1, 'Thread body has overflow-y: auto');
    }

    private function testBubbleRenderingAndWordWrapping(): void
    {
        $css = file_get_contents(__DIR__ . '/../public/assets/css/style.css');

        $this->assert(preg_match('/\.admin-bubble\s*\{[^}]*word-break:\s*break-word;/s', $css) === 1, 'Bubble has word-break: break-word to prevent blowout');
        $this->assert(preg_match('/\.admin-bubble\s*\{[^}]*overflow-wrap:\s*anywhere;/s', $css) === 1, 'Bubble has overflow-wrap: anywhere for long strings/URLs');
        $this->assert(preg_match('/\.admin-bubble-text\s*\{[^}]*overflow-wrap:\s*anywhere;/s', $css) === 1, 'Bubble text wrapper has overflow-wrap: anywhere');
        $this->assert(preg_match('/\.admin-bubble\.theirs\s*\{[^}]*border-bottom-left-radius:\s*4px;/s', $css) === 1, 'Incoming bubble has chat-bubble tail');
        $this->assert(preg_match('/\.admin-bubble\.mine\s*\{[^}]*border-bottom-right-radius:\s*4px;/s', $css) === 1, 'Outgoing bubble has chat-bubble tail');
    }

    private function testCustomThemedScrollbars(): void
    {
        $css = file_get_contents(__DIR__ . '/../public/assets/css/style.css');

        $this->assert(str_contains($css, 'scrollbar-width: thin;'), 'Custom thin scrollbar configured');
        $this->assert(str_contains($css, '.admin-msg-body::-webkit-scrollbar'), 'Webkit scrollbar configured for thread body');
        $this->assert(str_contains($css, '.admin-msg-list::-webkit-scrollbar'), 'Webkit scrollbar configured for conversation list');
    }

    private function testDateSeparatorsAndTimestamps(): void
    {
        $html = file_get_contents(__DIR__ . '/../app/Views/admin/messages.php');

        $this->assert(str_contains($html, 'class="admin-msg-date-pill"'), 'View outputs date separation pills for conversation history');
        $this->assert(str_contains($html, 'class="admin-bubble-meta"'), 'View structures timestamps inside admin-bubble-meta');
        $this->assert(str_contains($html, "date('g:i a'"), 'Timestamps formatted as g:i a (e.g. 1:45 pm)');
    }

    private function testEmptyStatesConsistency(): void
    {
        $html = file_get_contents(__DIR__ . '/../app/Views/admin/messages.php');

        $this->assert(str_contains($html, 'class="admin-empty-thread"'), 'Empty thread state wrapper exists');
        $this->assert(str_contains($html, 'class="admin-empty-thread-icon"'), 'Empty thread state displays friendly icon');
        $this->assert(str_contains($html, 'id="adminEmptyThread"'), 'Empty thread state has id="adminEmptyThread" for dynamic removal');
    }

    private function testRealtimePollingEndpointAndRoute(): void
    {
        $routes = file_get_contents(__DIR__ . '/../routes/web.php');
        $this->assert(str_contains($routes, "'/admin/messages/{partner}/poll'"), 'Poll route registered in routes/web.php');

        $controller = file_get_contents(__DIR__ . '/../app/Controllers/AdminController.php');
        $this->assert(str_contains($controller, 'public function poll(string $partner): void'), 'AdminController defines poll method');
        $this->assert(str_contains($controller, 'Message::since('), 'Poll fetches messages using Message::since');
        $this->assert(str_contains($controller, 'Message::markRead('), 'Poll marks new messages as read');
    }

    private function testJsonSendingAndControllerIntegrity(): void
    {
        $controller = file_get_contents(__DIR__ . '/../app/Controllers/AdminController.php');

        $this->assert(str_contains($controller, 'public function sendMessage(): void'), 'AdminController defines sendMessage method');
        $this->assert(str_contains($controller, '$this->verifyCsrf();'), 'sendMessage verifies CSRF token');
        $this->assert(str_contains($controller, "Message::send("), 'sendMessage persists message via Message model');
        $this->assert(str_contains($controller, "AuditLog::recordAction('admin.message'"), 'sendMessage logs action to AuditLog');
        $this->assert(str_contains($controller, '$this->isJson()'), 'sendMessage supports JSON response for instant AJAX submission');
        $this->assert(str_contains($controller, "redirect('/admin/messages/'"), 'sendMessage provides fallback redirect for standard form submissions');
        $this->assert(!str_contains($controller, "flash('success', 'Reply sent.');"), 'sendMessage does not set "Reply sent." flash notification popup');
    }

    private function testExclusionOfAutoTranslationAndOnlineStatus(): void
    {
        $html = file_get_contents(__DIR__ . '/../app/Views/admin/messages.php');
        $css = file_get_contents(__DIR__ . '/../public/assets/css/style.css');

        $this->assert(!str_contains($html, 'btnAutoTranslate'), 'Auto-translation button is strictly omitted');
        $this->assert(!str_contains($html, 'auto_translate'), 'Auto-translation logic is strictly omitted');
        $this->assert(!str_contains($html, 'presence-dot'), 'Online presence dot is strictly omitted in admin');
        $this->assert(!str_contains($html, 'presence-badge'), 'Online presence badge is strictly omitted in admin');
    }

    private function testPreservationOfAdminPermissionsAndActions(): void
    {
        $html = file_get_contents(__DIR__ . '/../app/Views/admin/messages.php');

        $this->assert(str_contains($html, '$partnerWarned'), 'Checks partner restriction status');
        $this->assert(str_contains($html, 'class="pill pill-disputed"'), 'Shows Restricted badge on warned partners');
        $this->assert(str_contains($html, 'class="admin-msg-warn"'), 'Displays warning notice banner');
        $this->assert(str_contains($html, 'partials/admin-unrestrict'), 'Includes unrestrict partial for admin actions');
    }

    private function testResponsiveStyles(): void
    {
        $css = file_get_contents(__DIR__ . '/../public/assets/css/style.css');

        $this->assert(preg_match('/@media\s*\(max-width:\s*820px\)[\s\S]*?\.admin-msg\s*\{/', $css) === 1, 'Media query contains responsive rules for .admin-msg');
        $this->assert(preg_match('/@media\s*\(max-width:\s*820px\)[\s\S]*?\.admin-msg-list\s*\{/', $css) === 1, 'Media query contains responsive rules for .admin-msg-list');
        $this->assert(preg_match('/@media\s*\(max-width:\s*820px\)[\s\S]*?\.admin-msg-thread\s*\{/', $css) === 1, 'Media query contains responsive rules for .admin-msg-thread');
        $this->assert(preg_match('/@media\s*\(max-width:\s*820px\)[\s\S]*?\.admin-msg-form\s*\{/', $css) === 1, 'Media query contains responsive rules for .admin-msg-form');
    }

    private function testKeyboardUsabilityAndScript(): void
    {
        $html = file_get_contents(__DIR__ . '/../app/Views/admin/messages.php');

        $this->assert(str_contains($html, 'function autoResize()'), 'Script defines autoResize function');
        $this->assert(str_contains($html, 'input.scrollHeight'), 'Auto-resizing measures input.scrollHeight');
        $this->assert(str_contains($html, "e.key === 'Enter' && !e.shiftKey"), 'Script handles Enter key submission while ignoring Shift+Enter');
        $this->assert(str_contains($html, 'function submitMessage()'), 'Script defines submitMessage handler');
        $this->assert(str_contains($html, 'function appendMessageBubble('), 'Script defines appendMessageBubble for instant dynamic display');
        $this->assert(str_contains($html, 'setInterval(function ()'), 'Script sets up polling timer');
        $this->assert(!str_contains($html, 'Sending...'), 'Send button does not display "Sending..." text on submit');
        $this->assert(!str_contains($html, 'sendBtn.disabled = true;'), 'Send button does not artificially disable on submit');
    }
}

(new AdminMessageComposerTest())->run();
