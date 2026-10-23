<?php

declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Auth;
use App\Core\Database;
use App\Models\User;
use App\Controllers\ProfileController;

final class ProfileNavigationAndEditIconTest
{
    private array $createdUserIds = [];
    private int $passed = 0;
    private int $failed = 0;

    public function run(): bool
    {
        echo "========================================================\n";
        echo " GuideMate Profile UI & Corner Icon Test Suite\n";
        echo "========================================================\n\n";

        $this->runTest('testProfileRemovedFromSidebar', [$this, 'testProfileRemovedFromSidebar']);
        $this->runTest('testProfileIconDropdownHasNoEditProfile', [$this, 'testProfileIconDropdownHasNoEditProfile']);
        $this->runTest('testProfileRoutesRemainAccessible', [$this, 'testProfileRoutesRemainAccessible']);
        $this->runTest('testProfileDetailsViewContainsCornerIconControl', [$this, 'testProfileDetailsViewContainsCornerIconControl']);
        $this->runTest('testOldEditProfileTextButtonRemoved', [$this, 'testOldEditProfileTextButtonRemoved']);
        $this->runTest('testEditProfilePageContainsCornerIconControl', [$this, 'testEditProfilePageContainsCornerIconControl']);
        $this->runTest('testStylesheetContainsCornerControlAndTooltipRules', [$this, 'testStylesheetContainsCornerControlAndTooltipRules']);

        $this->tearDown();

        echo "\n--------------------------------------------------------\n";
        echo sprintf("Results: %d passed, %d failed\n", $this->passed, $this->failed);
        echo "--------------------------------------------------------\n";

        return $this->failed === 0;
    }

    public function testProfileRemovedFromSidebar(): void
    {
        $dashNav = file_get_contents(BASE_DIR . '/app/Views/partials/dash-nav.php');
        $this->assert(
            !str_contains($dashNav, "url('/profile')") && !str_contains($dashNav, 'd_profile'),
            "dash-nav.php must not contain a link to /profile or d_profile translation"
        );
    }

    public function testProfileIconDropdownHasNoEditProfile(): void
    {
        $layout = file_get_contents(BASE_DIR . '/app/Views/layouts/main.php');

        $this->assert(
            str_contains($layout, 'id="userMenu"'),
            "main.php must contain the userMenu dropdown"
        );
        $this->assert(
            str_contains($layout, "url('/profile')") && str_contains($layout, '>Profile</a>'),
            "main.php dropdown must contain Profile link"
        );
        $this->assert(
            !str_contains($layout, '>Edit Profile</a>'),
            "main.php dropdown must NOT contain Edit Profile link"
        );
    }

    public function testProfileRoutesRemainAccessible(): void
    {
        $userId = User::create('Profile Tester', 'prof_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass123!', 'tourist');
        $this->createdUserIds[] = $userId;

        $_SESSION['user_id'] = $userId;
        Auth::refreshUser();

        $controller = new ProfileController();

        // 1. View profile
        ob_start();
        $controller->show();
        $htmlShow = ob_get_clean();
        $this->assert(str_contains($htmlShow, 'My profile'), "show() must render profile view");

        // 2. Edit profile
        ob_start();
        $controller->edit();
        $htmlEdit = ob_get_clean();
        $this->assert(str_contains($htmlEdit, 'Edit profile'), "edit() must render edit profile view");
    }

    public function testProfileDetailsViewContainsCornerIconControl(): void
    {
        $userId = User::create('Show Corner Tester', 'show_corner_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass123!', 'guide');
        $this->createdUserIds[] = $userId;

        $_SESSION['user_id'] = $userId;
        Auth::refreshUser();

        $controller = new ProfileController();
        ob_start();
        $controller->show();
        $html = ob_get_clean();

        // 1. Profile details section
        $this->assert(
            str_contains($html, 'profile-details-panel') || str_contains($html, 'profileDetailsSection'),
            "Profile Details view must include the profile details section container"
        );

        // 2. Upper-right corner action & button
        $this->assert(
            str_contains($html, 'profile-details-corner-action'),
            "Profile Details view must contain the profile-details-corner-action container"
        );
        $this->assert(
            str_contains($html, 'profile-corner-control'),
            "Corner action must contain profile-corner-control link"
        );
        $this->assert(
            str_contains($html, 'id="editProfileBtn"'),
            "Corner action link must have id editProfileBtn"
        );

        // 3. Accessibility & Tooltip
        $this->assert(
            str_contains($html, 'aria-label="Edit profile"'),
            "Control must include accessible aria-label='Edit profile'"
        );
        $this->assert(
            str_contains($html, 'title="Edit profile"'),
            "Control must include accessible title='Edit profile'"
        );
        $this->assert(
            str_contains($html, 'data-tooltip="Edit profile"') && str_contains($html, 'has-tooltip'),
            "Control must include data-tooltip='Edit profile' and has-tooltip class"
        );

        // 4. SVG icon matching pencil / edit
        $this->assert(
            str_contains($html, 'ico-edit-pencil'),
            "Control must render the edit-pencil SVG icon"
        );
    }

    public function testOldEditProfileTextButtonRemoved(): void
    {
        $userId = User::create('Old Button Check', 'btn_chk_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass123!', 'guide');
        $this->createdUserIds[] = $userId;

        $_SESSION['user_id'] = $userId;
        Auth::refreshUser();

        $controller = new ProfileController();
        ob_start();
        $controller->show();
        $html = ob_get_clean();

        // Verify old text button does not exist
        $this->assert(
            !str_contains($html, '✏️ Edit profile') && !str_contains($html, 'class="btn btn-primary">Edit profile') && !str_contains($html, 'btn-primary">✏️'),
            "Old Edit Profile text button must be completely removed"
        );
    }

    public function testEditProfilePageContainsCornerIconControl(): void
    {
        $userId = User::create('Edit Corner Tester', 'corner_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass123!', 'guide');
        $this->createdUserIds[] = $userId;

        $_SESSION['user_id'] = $userId;
        Auth::refreshUser();

        $controller = new ProfileController();
        ob_start();
        $controller->edit();
        $html = ob_get_clean();

        // 1. Profile details container
        $this->assert(
            str_contains($html, 'profile-details-panel') || str_contains($html, 'profileDetailsContainer'),
            "Edit profile must include the profile details container identifier"
        );

        // 2. Corner action & button
        $this->assert(
            str_contains($html, 'profile-details-corner-action'),
            "Edit profile must contain the profile-details-corner-action container"
        );
        $this->assert(
            str_contains($html, 'profile-corner-control'),
            "Corner action must contain profile-corner-control button"
        );

        // 3. Accessibility attributes
        $this->assert(
            str_contains($html, 'aria-label="Edit profile details"'),
            "Control must include accessible aria-label"
        );
        $this->assert(
            str_contains($html, 'data-tooltip="Edit profile details"') && str_contains($html, 'has-tooltip'),
            "Control must include tooltip attribute and has-tooltip class"
        );

        // 4. SVG icon
        $this->assert(
            str_contains($html, 'ico-edit-pencil'),
            "Control must render the edit-pencil SVG icon"
        );
    }

    public function testStylesheetContainsCornerControlAndTooltipRules(): void
    {
        $css = file_get_contents(BASE_DIR . '/public/assets/css/style.css');

        $this->assert(str_contains($css, '.profile-details-panel'), "style.css must define .profile-details-panel");
        $this->assert(str_contains($css, '.profile-details-corner-action'), "style.css must define .profile-details-corner-action");
        $this->assert(str_contains($css, '.profile-corner-control'), "style.css must define .profile-corner-control");
        $this->assert(str_contains($css, '.has-tooltip'), "style.css must define .has-tooltip");
        $this->assert(str_contains($css, '.profile-corner-control:hover'), "style.css must define hover state for corner control");
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

    private function tearDown(): void
    {
        if ($this->createdUserIds !== []) {
            $placeholders = implode(',', array_fill(0, count($this->createdUserIds), '?'));
            Database::run("DELETE FROM users WHERE id IN ({$placeholders})", $this->createdUserIds);
        }
    }
}

$test = new ProfileNavigationAndEditIconTest();
if (!$test->run()) {
    exit(1);
}
