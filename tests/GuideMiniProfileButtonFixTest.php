<?php
require_once __DIR__ . '/../bootstrap.php';

class GuideMiniProfileButtonFixTest
{
    private array $results = [];

    private function assert(bool $condition, string $message): void
    {
        $status = $condition ? 'PASS' : 'FAIL';
        $this->results[] = ['status' => $status, 'message' => $message];
        echo "[$status] $message\n";
        if (!$condition) {
            throw new Exception("Assertion failed: $message");
        }
    }

    public function run(): void
    {
        echo "Starting GuideMiniProfileButtonFixTest...\n\n";

        $this->testShowTemplateStructure();
        $this->testStylesheetRules();
        $this->testLiveHttpRender();

        $this->printSummary();
    }

    private function testShowTemplateStructure(): void
    {
        echo "--- 1. Testing app/Views/listings/show.php Structure ---\n";
        $template = file_get_contents(__DIR__ . '/../app/Views/listings/show.php');

        $this->assert(
            str_contains($template, 'class="guide-mini-link"'),
            "Template uses semantic .guide-mini-link class for guide link"
        );

        $this->assert(
            str_contains($template, 'class="btn btn-ghost btn-sm guide-mini-btn"'),
            "Template assigns .guide-mini-btn class to the View Profile button"
        );

        $this->assert(
            str_contains($template, 'onerror="this.onerror=null;this.src=\'https://ui-avatars.com'),
            "Template includes onerror avatar fallback for circular avatar resilience"
        );

        $this->assert(
            !str_contains($template, 'style="display:flex;align-items:center;justify-content:space-between;gap:0.75rem;"'),
            "Inline styles removed from .guide-mini container in favor of CSS stylesheet"
        );

        $this->assert(
            !str_contains($template, 'style="font-size:0.8rem;padding:0.35rem 0.65rem;flex-shrink:0;"'),
            "Inline styles removed from View Profile button in favor of CSS stylesheet"
        );
    }

    private function testStylesheetRules(): void
    {
        echo "\n--- 2. Testing public/assets/css/style.css Rules ---\n";
        $css = file_get_contents(__DIR__ . '/../public/assets/css/style.css');

        $this->assert(
            str_contains($css, '.guide-mini-link {'),
            "style.css defines .guide-mini-link"
        );

        $this->assert(
            str_contains($css, '.guide-mini-btn,'),
            "style.css defines .guide-mini-btn"
        );

        $this->assert(
            str_contains($css, 'width: auto !important;'),
            ".guide-mini-btn enforces width: auto !important"
        );

        $this->assert(
            str_contains($css, '.booking-box .btn-ghost:not(.guide-mini-btn):not(.btn-sm)'),
            ".booking-box .btn-ghost scopes 100% width to exclude guide-mini-btn and btn-sm"
        );

        $this->assert(
            str_contains($css, '.guide-mini-label {') && str_contains($css, 'white-space: nowrap;'),
            ".guide-mini-label enforces white-space: nowrap to prevent multi-line squashing"
        );

        $this->assert(
            str_contains($css, '.guide-mini-name {') && str_contains($css, 'text-overflow: ellipsis;'),
            ".guide-mini-name enforces text-overflow: ellipsis for overflow handling"
        );

        $this->assert(
            str_contains($css, '.guide-mini-avatar,') && str_contains($css, 'flex-shrink: 0;'),
            ".guide-mini-avatar enforces flex-shrink: 0 to maintain 48px circle"
        );
    }

    private function testLiveHttpRender(): void
    {
        echo "\n--- 3. Testing Live HTTP Rendering ---\n";
        $html = @file_get_contents('http://localhost/GuideMate/public/listing/kawasan-falls-canyoneering-adventure');
        $this->assert($html !== false && strlen($html) > 0, "Page fetched successfully over HTTP");

        $this->assert(
            str_contains($html, 'guide-mini-btn'),
            "Live HTML renders .guide-mini-btn"
        );

        $this->assert(
            str_contains($html, 'View Profile →'),
            "Live HTML renders 'View Profile →' link"
        );

        $this->assert(
            str_contains($html, 'John Lloyd Noya'),
            "Live HTML renders guide name"
        );

        $this->assert(
            str_contains($html, 'Your guide'),
            "Live HTML renders host label"
        );

        $this->assert(
            str_contains($html, 'guide-mini-avatar'),
            "Live HTML renders .guide-mini-avatar"
        );

        $css = @file_get_contents('http://localhost/GuideMate/public/assets/css/style.css');
        $this->assert(
            $css !== false && str_contains($css, 'guide-mini-btn'),
            "Live CSS served from Apache contains .guide-mini-btn"
        );
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

$test = new GuideMiniProfileButtonFixTest();
$test->run();
