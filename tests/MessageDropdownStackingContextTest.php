<?php

declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/../bootstrap.php';

class MessageDropdownStackingContextTest
{
    private int $passed = 0;
    private int $failed = 0;

    public function run(): bool
    {
        echo "========================================================\n";
        echo " GuideMate Message Dropdown Stacking & Stacking Context Tests\n";
        echo "========================================================\n\n";

        $this->testThreadHeadStackingContext();
        $this->testThreadDropdownZIndex();
        $this->testThreadBodyStackingLevel();
        $this->testGlobalDropdownZIndex();
        $this->testMobileResponsiveDropdownScoped();
        $this->testThreadMenuMarkupAndAccessibility();
        $this->testThreadMenuJavaScriptInteractions();

        echo "\n--------------------------------------------------------\n";
        echo sprintf("Results: %d passed, %d failed\n", $this->passed, $this->failed);
        echo "--------------------------------------------------------\n";

        return $this->failed === 0;
    }

    private function testThreadHeadStackingContext(): void
    {
        $css = file_get_contents(__DIR__ . '/../public/assets/css/style.css');

        // .thread-head must have position: relative and a z-index higher than thread-body
        $hasRelative = preg_match('/\.thread-head\s*\{[^}]*position:\s*relative;/s', $css);
        $hasZIndex = preg_match('/\.thread-head\s*\{[^}]*z-index:\s*(3[0-9]|[4-9][0-9]);/s', $css);

        if ($hasRelative && $hasZIndex) {
            $this->pass('testThreadHeadStackingContext: .thread-head creates stacking context with high z-index');
        } else {
            $this->fail('testThreadHeadStackingContext: .thread-head missing position: relative or high z-index');
        }
    }

    private function testThreadDropdownZIndex(): void
    {
        $css = file_get_contents(__DIR__ . '/../public/assets/css/style.css');

        $hasZIndex = preg_match('/\.thread-dropdown\s*\{[^}]*z-index:\s*[0-9]+;/s', $css);

        if ($hasZIndex) {
            $this->pass('testThreadDropdownZIndex: .thread-dropdown has explicit positive z-index');
        } else {
            $this->fail('testThreadDropdownZIndex: .thread-dropdown missing explicit z-index');
        }
    }

    private function testThreadBodyStackingLevel(): void
    {
        $css = file_get_contents(__DIR__ . '/../public/assets/css/style.css');

        // Extract z-index of .thread-head and .thread-body
        preg_match('/\.thread-head\s*\{[^}]*z-index:\s*([0-9]+);/s', $css, $mHead);
        preg_match('/\.thread-body\s*\{[^}]*z-index:\s*([0-9]+);/s', $css, $mBody);

        $headZ = isset($mHead[1]) ? (int) $mHead[1] : 0;
        $bodyZ = isset($mBody[1]) ? (int) $mBody[1] : 0;

        if ($headZ > $bodyZ && $bodyZ >= 0) {
            $this->pass("testThreadBodyStackingLevel: .thread-head (z-index $headZ) stacks strictly in front of .thread-body (z-index $bodyZ)");
        } else {
            $this->fail("testThreadBodyStackingLevel: .thread-head ($headZ) does not stack above .thread-body ($bodyZ)");
        }
    }

    private function testGlobalDropdownZIndex(): void
    {
        $css = file_get_contents(__DIR__ . '/../public/assets/css/style.css');

        $hasDropdownZIndex = preg_match('/\.dropdown\s*\{[^}]*z-index:\s*[0-9]+;/s', $css);

        if ($hasDropdownZIndex) {
            $this->pass('testGlobalDropdownZIndex: base .dropdown includes z-index for floating menus');
        } else {
            $this->fail('testGlobalDropdownZIndex: base .dropdown missing z-index');
        }
    }

    private function testMobileResponsiveDropdownScoped(): void
    {
        $css = file_get_contents(__DIR__ . '/../public/assets/css/style.css');

        // Verify that the static dropdown rule in @media is scoped to .nav-menu .dropdown,
        // not global .dropdown, so .thread-dropdown maintains absolute positioning on mobile
        $hasUnscopedStatic = preg_match('/@media[^{]+\{[^}]*?(?<!\.nav-menu\s)\.dropdown\s*\{\s*position:\s*static;/s', $css);
        $hasScopedStatic = strpos($css, '.nav-menu .dropdown { position: static;') !== false;

        if ($hasScopedStatic && !$hasUnscopedStatic) {
            $this->pass('testMobileResponsiveDropdownScoped: mobile static rule scoped to .nav-menu, preserving .thread-dropdown behavior on mobile');
        } else {
            $this->fail('testMobileResponsiveDropdownScoped: unscoped .dropdown found in mobile media queries');
        }
    }

    private function testThreadMenuMarkupAndAccessibility(): void
    {
        $view = file_get_contents(__DIR__ . '/../app/Views/messages/index.php');

        $hasBtn = strpos($view, 'id="threadMenuBtn"') !== false;
        $hasDropdown = strpos($view, 'id="threadMenu"') !== false;
        $hasAriaExpanded = strpos($view, 'aria-expanded') !== false;
        $hasThreadMenuClass = strpos($view, 'class="thread-menu"') !== false;

        if ($hasBtn && $hasDropdown && $hasAriaExpanded && $hasThreadMenuClass) {
            $this->pass('testThreadMenuMarkupAndAccessibility: thread header contains accessible menu button and dropdown container');
        } else {
            $this->fail('testThreadMenuMarkupAndAccessibility: thread header missing expected menu elements or accessibility attributes');
        }
    }

    private function testThreadMenuJavaScriptInteractions(): void
    {
        $js = file_get_contents(__DIR__ . '/../public/assets/js/main.js');

        $hasToggle = strpos($js, 'threadMenu.classList.toggle(\'open\')') !== false;
        $hasAriaSync = strpos($js, 'threadMenuBtn.setAttribute(\'aria-expanded\'') !== false;
        $hasOutsideClick = strpos($js, '!threadMenu.contains(e.target)') !== false;
        $hasEscapeDismiss = strpos($js, "e.key === 'Escape'") !== false;

        if ($hasToggle && $hasAriaSync && $hasOutsideClick && $hasEscapeDismiss) {
            $this->pass('testThreadMenuJavaScriptInteractions: click toggle, outside dismiss, and Escape key dismissal verified in JS');
        } else {
            $this->fail('testThreadMenuJavaScriptInteractions: missing click toggle, outside dismiss, or Escape key handler in JS');
        }
    }

    private function pass(string $name): void
    {
        echo " [PASS] $name\n";
        $this->passed++;
    }

    private function fail(string $name): void
    {
        echo " [FAIL] $name\n";
        $this->failed++;
    }
}

$test = new MessageDropdownStackingContextTest();
exit($test->run() ? 0 : 1);
