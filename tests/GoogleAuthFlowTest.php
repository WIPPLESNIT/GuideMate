<?php

declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Database;
use App\Models\User;
use App\Controllers\ApiController;

final class GoogleAuthFlowTest
{
    private array $createdUserIds = [];
    private int $passed = 0;
    private int $failed = 0;

    public function run(): bool
    {
        echo "========================================================\n";
        echo " GuideMate Google Authentication Flow Test Suite\n";
        echo "========================================================\n\n";

        $this->runTest('testCreateOAuthUserStoresRealDisplayName', [$this, 'testCreateOAuthUserStoresRealDisplayName']);
        $this->runTest('testCreateOAuthUserNeverStoresGoogleOrGoogleUser', [$this, 'testCreateOAuthUserNeverStoresGoogleOrGoogleUser']);
        $this->runTest('testDisplayNameFromClaimsFallbacks', [$this, 'testDisplayNameFromClaimsFallbacks']);
        $this->runTest('testReturningUserCustomNameIsPreserved', [$this, 'testReturningUserCustomNameIsPreserved']);
        $this->runTest('testPlaceholderNameIsUpdatedWithRealGoogleName', [$this, 'testPlaceholderNameIsUpdatedWithRealGoogleName']);
        $this->runTest('testGoogleStartGeneratesOfficialAccountPickerUrl', [$this, 'testGoogleStartGeneratesOfficialAccountPickerUrl']);
        $this->runTest('testSocialAuthEndpointCreatesUserWithActualName', [$this, 'testSocialAuthEndpointCreatesUserWithActualName']);
        $this->runTest('testSocialAuthEndpointPreservesExistingCustomName', [$this, 'testSocialAuthEndpointPreservesExistingCustomName']);
        $this->runTest('testExistingEmailPasswordAuthRemainsIntact', [$this, 'testExistingEmailPasswordAuthRemainsIntact']);

        $this->cleanup();

        echo "\n--------------------------------------------------------\n";
        echo "Results: {$this->passed} passed, {$this->failed} failed\n";
        echo "--------------------------------------------------------\n";

        return $this->failed === 0;
    }

    private function runTest(string $name, callable $fn): void
    {
        try {
            $fn();
            echo " [PASS] {$name}\n";
            $this->passed++;
        } catch (\Throwable $e) {
            echo " [FAIL] {$name}: {$e->getMessage()}\n";
            echo "        Line: {$e->getFile()}:{$e->getLine()}\n";
            $this->failed++;
        }
    }

    public function testCreateOAuthUserStoresRealDisplayName(): void
    {
        $oauthId = 'google_test_' . bin2hex(random_bytes(8));
        $realName = 'Marc Repuesto';

        $userId = User::createOAuthUser('google', $oauthId, $realName);
        $this->createdUserIds[] = $userId;

        $user = User::find($userId);
        $this->assert($user !== null, 'Created user should be found in database');
        $this->assert($user['name'] === 'Marc Repuesto', "User name should be 'Marc Repuesto', got '{$user['name']}'");
        $this->assert($user['oauth_provider'] === 'google', "Provider should be 'google'");
        $this->assert($user['oauth_id'] === $oauthId, "OAuth ID should match");
        $this->assert(strpos($user['email'], '@social.guidemate.local') !== false, "Internal confidential email generated");
    }

    public function testCreateOAuthUserNeverStoresGoogleOrGoogleUser(): void
    {
        $oauthId1 = 'google_test_' . bin2hex(random_bytes(8));
        $userId1 = User::createOAuthUser('google', $oauthId1, null);
        $this->createdUserIds[] = $userId1;
        $user1 = User::find($userId1);
        $this->assert($user1['name'] === 'User', "Default name without Google name should be 'User', not 'Google User' or 'Google'");

        $oauthId2 = 'google_test_' . bin2hex(random_bytes(8));
        $userId2 = User::createOAuthUser('google', $oauthId2, 'Google');
        $this->createdUserIds[] = $userId2;
        $user2 = User::find($userId2);
        $this->assert($user2['name'] === 'User', "Name 'Google' should be normalized to fallback 'User'");

        $oauthId3 = 'google_test_' . bin2hex(random_bytes(8));
        $userId3 = User::createOAuthUser('google', $oauthId3, 'Google User');
        $this->createdUserIds[] = $userId3;
        $user3 = User::find($userId3);
        $this->assert($user3['name'] === 'User', "Name 'Google User' should be normalized to fallback 'User'");
    }

    public function testDisplayNameFromClaimsFallbacks(): void
    {
        $api = new ApiController();
        $ref = new \ReflectionClass($api);
        $method = $ref->getMethod('displayNameFromClaims');
        $method->setAccessible(true);

        // 1. Full name provided
        $res = $method->invoke($api, 'Jane Doe', 'Jane', 'Doe', 'jane.doe@gmail.com');
        $this->assert($res === 'Jane Doe', "Should prefer full name, got '{$res}'");

        // 2. Only given + family name
        $res = $method->invoke($api, '', 'John', 'Smith', 'jsmith@gmail.com');
        $this->assert($res === 'John Smith', "Should combine given + family, got '{$res}'");

        // 3. Only email address (with dot/hyphen separators)
        $res = $method->invoke($api, '', '', '', 'maria.clara@gmail.com');
        $this->assert($res === 'Maria Clara', "Should derive title-cased name from email local part, got '{$res}'");

        // 4. Only email address (single word)
        $res = $method->invoke($api, '', '', '', 'alex@gmail.com');
        $this->assert($res === 'Alex', "Should capitalize single word email, got '{$res}'");

        // 5. Provider returned 'Google' as the name claim
        $res = $method->invoke($api, 'Google', '', '', 'tourist.cebu@guidemate.local');
        $this->assert($res === 'Tourist Cebu', "Should not accept 'Google' as name and fallback to email, got '{$res}'");

        // 6. No name, no email
        $res = $method->invoke($api, '', '', '', '');
        $this->assert($res === 'User', "Should fallback to 'User', got '{$res}'");
    }

    public function testReturningUserCustomNameIsPreserved(): void
    {
        $oauthId = 'google_test_' . bin2hex(random_bytes(8));
        $userId = User::createOAuthUser('google', $oauthId, 'Initial Google Name');
        $this->createdUserIds[] = $userId;

        // User edits their profile in the app to a custom name
        User::updateProfile($userId, ['name' => 'Custom Tourist Name']);

        $updated = User::find($userId);
        $this->assert($updated['name'] === 'Custom Tourist Name', "User name updated to custom name");

        // User signs in again via Google OAuth
        User::renameIfPlaceholder($userId, $updated['name'], 'Initial Google Name');

        $reloaded = User::find($userId);
        $this->assert($reloaded['name'] === 'Custom Tourist Name', "Custom name must be preserved and not overwritten on re-login");
    }

    public function testPlaceholderNameIsUpdatedWithRealGoogleName(): void
    {
        // Suppose user was created previously with a placeholder name
        $oauthId = 'google_test_' . bin2hex(random_bytes(8));
        $userId = User::createOAuthUser('google', $oauthId, 'Google User');
        $this->createdUserIds[] = $userId;

        // Force old legacy placeholder 'Google User' in DB
        Database::run('UPDATE users SET name = ? WHERE id = ?', ['Google User', $userId]);

        $before = User::find($userId);
        $this->assert($before['name'] === 'Google User', "Precondition: user has legacy placeholder name");

        // On next login, actual Google display name is supplied
        User::renameIfPlaceholder($userId, $before['name'], 'Juan Dela Cruz');

        $after = User::find($userId);
        $this->assert($after['name'] === 'Juan Dela Cruz', "Placeholder must be updated to actual Google display name 'Juan Dela Cruz', got '{$after['name']}'");

        // Also test 'Google' placeholder
        Database::run('UPDATE users SET name = ? WHERE id = ?', ['Google', $userId]);
        User::renameIfPlaceholder($userId, 'Google', 'Juan Dela Cruz');
        $after2 = User::find($userId);
        $this->assert($after2['name'] === 'Juan Dela Cruz', "Placeholder 'Google' must be updated to actual name");
    }

    public function testGoogleStartGeneratesOfficialAccountPickerUrl(): void
    {
        $api = new ApiController();
        $_GET['format'] = 'json';
        $_GET['return'] = 'guidemate://auth/google';

        ob_start();
        $api->googleStart();
        $out = ob_get_clean();

        $data = json_decode($out, true);
        $this->assert(is_array($data), "googleStart should output JSON array");
        $this->assert(!empty($data['auth_url']), "googleStart should return auth_url");
        $this->assert(strpos($data['auth_url'], 'https://accounts.google.com/o/oauth2/v2/auth') === 0, "auth_url must start with official Google OAuth URL");
        $this->assert(strpos($data['auth_url'], 'prompt=select_account') !== false, "auth_url must include prompt=select_account to trigger native Google account picker");
        $this->assert(strpos($data['auth_url'], 'scope=openid+profile+email') !== false || strpos($data['auth_url'], 'scope=openid%20profile%20email') !== false, "auth_url must request profile and email scopes");
        $this->assert(!empty($data['session_id']), "googleStart should generate a session_id");
    }

    public function testSocialAuthEndpointCreatesUserWithActualName(): void
    {
        $api = new ApiController();
        $uniqueSub = 'sub_' . bin2hex(random_bytes(6));
        $_POST = [
            'provider' => 'google',
            'subject' => $uniqueSub,
            'name' => 'Samantha Taylor',
            'email' => 'samantha.taylor@gmail.com',
        ];

        ob_start();
        $api->socialAuth();
        $out = ob_get_clean();

        $res = json_decode($out, true);
        $this->assert(is_array($res) && isset($res['user']), "socialAuth should return user payload");
        $this->assert($res['user']['name'] === 'Samantha Taylor', "User display name should be 'Samantha Taylor', got '{$res['user']['name']}'");
        $this->assert(!empty($res['token']), "Should issue valid session token");

        $this->createdUserIds[] = (int) $res['user']['id'];
    }

    public function testSocialAuthEndpointPreservesExistingCustomName(): void
    {
        $api = new ApiController();
        $uniqueSub = 'sub_' . bin2hex(random_bytes(6));
        $userId = User::createOAuthUser('google', 'demo_' . $uniqueSub, 'Original Name');
        $this->createdUserIds[] = $userId;

        // User customizes their name
        User::updateProfile($userId, ['name' => 'Sam T.']);

        $_POST = [
            'provider' => 'google',
            'subject' => $uniqueSub,
            'name' => 'Original Name',
            'email' => 'samantha@gmail.com',
        ];

        ob_start();
        $api->socialAuth();
        $out = ob_get_clean();

        $res = json_decode($out, true);
        $this->assert(is_array($res) && isset($res['user']), "socialAuth should return user payload");
        $this->assert($res['user']['name'] === 'Sam T.', "Returning user custom name 'Sam T.' must be preserved, got '{$res['user']['name']}'");
    }

    public function testExistingEmailPasswordAuthRemainsIntact(): void
    {
        // Ensure email/password registration and login still work seamlessly
        $email = 'standard_test_' . bin2hex(random_bytes(6)) . '@example.com';
        $password = 'TestPassword123!';
        $name = 'Standard User';

        $userId = User::create($name, $email, $password, 'tourist');
        $this->createdUserIds[] = $userId;

        $user = User::findByEmail($email);
        $this->assert($user !== null, 'Registered user should be found');
        $this->assert(password_verify($password, $user['password']), 'Password hash must verify');
        $this->assert($user['name'] === 'Standard User', 'User name must match');
    }

    private function cleanup(): void
    {
        foreach ($this->createdUserIds as $id) {
            try {
                Database::run('DELETE FROM users WHERE id = ?', [$id]);
            } catch (\Throwable $e) {}
        }
    }

    private function assert(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new \RuntimeException($message);
        }
    }
}

$suite = new GoogleAuthFlowTest();
exit($suite->run() ? 0 : 1);
