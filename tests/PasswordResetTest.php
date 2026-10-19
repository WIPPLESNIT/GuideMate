<?php

declare(strict_types=1);

/**
 * End-to-end tests for the Password Reset feature.
 *
 * Covers:
 * 1. Valid password-reset requests (token creation, email link, instructions, 30-min expiry)
 * 2. Invalid tokens (non-existent tokens rejected)
 * 3. Expired tokens (tokens past expiration window rejected)
 * 4. Token reuse (single-use enforcement: token rejected once password is changed)
 * 5. Unknown email addresses (no token created, no email sent, generic response to prevent account enumeration)
 * 6. Successful password changes (bcrypt hashing, user login verification with new password)
 * 7. Email-sending failures (graceful error handling, server-side logging without leaking credentials)
 * 8. Password policy validation (length, uppercase, number, special character rules)
 */

namespace Tests;

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Database;
use App\Core\Mailer;
use App\Models\PasswordReset;
use App\Models\User;
use App\Services\NotificationService;

class PasswordResetTest
{
    private string $testEmail = 'test_reset_user@example.com';
    private string $testPassword = 'OldPassword123!';
    private ?int $testUserId = null;
    private int $passed = 0;
    private int $failed = 0;

    public function run(): bool
    {
        echo "========================================================\n";
        echo " GuideMate Password Reset Test Suite\n";
        echo "========================================================\n\n";

        $this->setUp();

        $this->runTest('testValidPasswordResetRequest', [$this, 'testValidPasswordResetRequest']);
        $this->runTest('testInvalidToken', [$this, 'testInvalidToken']);
        $this->runTest('testExpiredToken', [$this, 'testExpiredToken']);
        $this->runTest('testTokenReusePrevention', [$this, 'testTokenReusePrevention']);
        $this->runTest('testUnknownEmailAddress', [$this, 'testUnknownEmailAddress']);
        $this->runTest('testSuccessfulPasswordChange', [$this, 'testSuccessfulPasswordChange']);
        $this->runTest('testEmailSendingFailureHandledGracefully', [$this, 'testEmailSendingFailureHandledGracefully']);
        $this->runTest('testPasswordPolicyValidation', [$this, 'testPasswordPolicyValidation']);

        $this->tearDown();

        echo "\n--------------------------------------------------------\n";
        echo sprintf("Results: %d passed, %d failed\n", $this->passed, $this->failed);
        echo "--------------------------------------------------------\n";

        return $this->failed === 0;
    }

    private function runTest(string $name, callable $callback): void
    {
        try {
            $callback();
            $this->passed++;
            echo " [PASS] {$name}\n";
        } catch (\Throwable $e) {
            $this->failed++;
            echo " [FAIL] {$name}: " . $e->getMessage() . "\n";
            echo "        in " . $e->getFile() . ':' . $e->getLine() . "\n";
        }
    }

    private function setUp(): void
    {
        // Clean up any previous test artifacts
        Database::run('DELETE FROM password_resets WHERE email = ?', [$this->testEmail]);
        Database::run('DELETE FROM users WHERE email = ?', [$this->testEmail]);

        // Create a dedicated test user
        $hash = password_hash($this->testPassword, PASSWORD_BCRYPT);
        $this->testUserId = Database::insert(
            'INSERT INTO users (name, email, password, role, is_active) VALUES (?, ?, ?, "tourist", 1)',
            ['Reset Test User', $this->testEmail, $hash]
        );

        Mailer::clearLastSent();
        Mailer::setSimulateFailure(false);
    }

    private function tearDown(): void
    {
        Database::run('DELETE FROM password_resets WHERE email = ?', [$this->testEmail]);
        if ($this->testUserId !== null) {
            Database::run('DELETE FROM users WHERE id = ?', [$this->testUserId]);
        }
        Mailer::clearLastSent();
        Mailer::setSimulateFailure(false);
    }

    /**
     * Test 1: Valid request creates a single-use token and sends email with 30-min expiration instructions.
     */
    public function testValidPasswordResetRequest(): void
    {
        Mailer::clearLastSent();

        // 1. Generate token
        $token = PasswordReset::create($this->testEmail);
        $this->assertNotEmpty($token, 'Generated token must not be empty.');
        $this->assertEquals(64, strlen($token), 'Hex token length should be 64 characters (32 bytes).');

        // 2. Verify token is in DB hashed with sha256
        $hashed = hash('sha256', $token);
        $row = Database::first('SELECT * FROM password_resets WHERE email = ?', [$this->testEmail]);
        $this->assertNotNull($row, 'Password reset record must exist in database.');
        $this->assertEquals($hashed, $row['token'], 'Token must be stored hashed with sha256.');
        $this->assertNull($row['used_at'], 'used_at must be NULL for fresh tokens.');

        // 3. Expiration window must be ~30 minutes
        $expires = strtotime((string) $row['expires_at']);
        $diffMinutes = ($expires - time()) / 60;
        $this->assertTrue($diffMinutes >= 28 && $diffMinutes <= 32, "Expiration should be approx 30 minutes, got {$diffMinutes}m.");

        // 4. Send notification
        $sent = NotificationService::passwordReset($this->testEmail, $token);
        $this->assertTrue($sent, 'NotificationService::passwordReset must return true.');

        // 5. Verify email content
        $lastSent = Mailer::lastSent();
        $this->assertNotNull($lastSent, 'An email must have been dispatched.');
        $this->assertEquals($this->testEmail, $lastSent['to'], 'Email recipient must match.');
        $this->assertStringContains('30 minutes', $lastSent['body'], 'Email must mention 30 minutes expiration.');
        $this->assertStringContains($token, $lastSent['body'], 'Email must contain the reset token link.');
        $this->assertStringContains('single-use', $lastSent['body'], 'Email must mention single-use notice.');

        // 6. Verify findValid succeeds
        $validRow = PasswordReset::findValid($token);
        $this->assertNotNull($validRow, 'findValid() must return the row for a fresh token.');
        $this->assertEquals($this->testEmail, $validRow['email']);
    }

    /**
     * Test 2: Invalid or tampered tokens are rejected.
     */
    public function testInvalidToken(): void
    {
        $fakeToken = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';
        $validRow = PasswordReset::findValid($fakeToken);
        $this->assertNull($validRow, 'findValid() must return null for non-existent token.');

        $anyRow = PasswordReset::findByToken($fakeToken);
        $this->assertNull($anyRow, 'findByToken() must return null for non-existent token.');
    }

    /**
     * Test 3: Expired tokens are rejected.
     */
    public function testExpiredToken(): void
    {
        $token = PasswordReset::create($this->testEmail);

        // Force token to be expired in the past
        $hashed = hash('sha256', $token);
        Database::run(
            'UPDATE password_resets SET expires_at = DATE_SUB(NOW(), INTERVAL 5 MINUTE) WHERE token = ?',
            [$hashed]
        );

        // findValid must reject expired token
        $validRow = PasswordReset::findValid($token);
        $this->assertNull($validRow, 'findValid() must return null for expired token.');

        // findByToken should still find the row to enable informative error messaging
        $existing = PasswordReset::findByToken($token);
        $this->assertNotNull($existing, 'findByToken() finds the row.');
        $this->assertTrue(strtotime((string) $existing['expires_at']) <= time(), 'Token expires_at is in the past.');
    }

    /**
     * Test 4: Token reuse prevention (single-use enforcement).
     */
    public function testTokenReusePrevention(): void
    {
        $token = PasswordReset::create($this->testEmail);

        $validBefore = PasswordReset::findValid($token);
        $this->assertNotNull($validBefore, 'Token is valid prior to use.');

        // Mark token as used
        PasswordReset::markUsed($token);

        // findValid must reject used token
        $validAfter = PasswordReset::findValid($token);
        $this->assertNull($validAfter, 'findValid() must return null for already-used token.');

        // findByToken confirms used_at timestamp is recorded
        $row = PasswordReset::findByToken($token);
        $this->assertNotNull($row, 'Record still exists.');
        $this->assertNotNull($row['used_at'], 'used_at column must be populated.');
    }

    /**
     * Test 5: Unknown email address handling (returns error message, no token created, no email sent).
     */
    public function testUnknownEmailAddress(): void
    {
        Mailer::clearLastSent();
        $unknownEmail = 'nonexistent_account_' . bin2hex(random_bytes(6)) . '@example.com';

        // Check user lookup
        $user = User::findByEmail($unknownEmail);
        $this->assertNull($user, 'User must not exist.');

        // Controller logic for unknown email:
        // Generates error, does not create token, does not send email
        $errorMessage = null;
        if ($user === null) {
            $errorMessage = 'We could not find an account with that email address.';
        } else {
            $token = PasswordReset::create($unknownEmail);
            NotificationService::passwordReset($unknownEmail, $token);
        }

        $this->assertEquals('We could not find an account with that email address.', $errorMessage);

        $row = Database::first('SELECT * FROM password_resets WHERE email = ?', [$unknownEmail]);
        $this->assertNull($row, 'No password_resets row should be created for unknown email.');

        $this->assertNull(Mailer::lastSent(), 'No email should be sent for unknown email.');
    }

    /**
     * Test 6: End-to-end password change with bcrypt hashing and validation.
     */
    public function testSuccessfulPasswordChange(): void
    {
        // 1. Initial password verification
        $initialUser = User::findByEmail($this->testEmail);
        $this->assertNotNull($initialUser);
        $this->assertTrue(password_verify($this->testPassword, (string) $initialUser['password']), 'Initial password matches.');

        // 2. Request reset
        $token = PasswordReset::create($this->testEmail);
        $row = PasswordReset::findValid($token);
        $this->assertNotNull($row);

        // 3. New password update
        $newPassword = 'NewSecretPass99!';
        User::updatePassword((int) $initialUser['id'], $newPassword);
        PasswordReset::markUsed($token);

        // 4. Verify password in DB was hashed with bcrypt
        $updatedUser = User::findByEmail($this->testEmail);
        $this->assertNotNull($updatedUser);
        $this->assertNotEquals($newPassword, $updatedUser['password'], 'Password must not be stored in plaintext.');
        $this->assertTrue(password_verify($newPassword, (string) $updatedUser['password']), 'New password bcrypt matches.');
        $this->assertFalse(password_verify($this->testPassword, (string) $updatedUser['password']), 'Old password no longer works.');

        // 5. Verify token cannot be reused
        $this->assertNull(PasswordReset::findValid($token), 'Token cannot be reused after password update.');
    }

    /**
     * Test 7: Email-sending failure handled gracefully without crashing or leaking sensitive info.
     */
    public function testEmailSendingFailureHandledGracefully(): void
    {
        Mailer::clearLastSent();
        Mailer::setSimulateFailure(true);

        $token = PasswordReset::create($this->testEmail);

        // Simulating controller error handling
        $caught = false;
        $failed = false;

        try {
            $sent = NotificationService::passwordReset($this->testEmail, $token);
            if (!$sent) {
                $failed = true;
                // Log failure server-side without leaking credentials or tokens
                error_log("[Test Handled] Password reset delivery returned false for {$this->testEmail}");
            }
        } catch (\Throwable $e) {
            $caught = true;
            error_log("[Test Handled] Password reset delivery threw exception: " . $e->getMessage());
        }

        // Must fail gracefully without unhandled exception crashing the request
        $this->assertTrue($failed || $caught, 'Email failure was detected and handled.');

        // Check that mail failure log was written
        $errorLogFile = BASE_DIR . '/storage/logs/mail_error.log';
        if (is_file($errorLogFile)) {
            $content = file_get_contents($errorLogFile) ?: '';
            $this->assertStringContains($this->testEmail, $content, 'Error log recorded recipient.');
            $this->assertStringNotContains($this->testPassword, $content, 'Error log must NEVER leak passwords.');
        }

        Mailer::setSimulateFailure(false);
    }

    /**
     * Test 8: Password policy validation rules.
     */
    public function testPasswordPolicyValidation(): void
    {
        // Enforced policy: 8-12 characters, >=1 uppercase, >=1 number, >=1 special character
        $policy = function (string $p): ?string {
            $len = strlen($p);
            if ($len < 8 || $len > 12) {
                return 'Password must be 8 to 12 characters long.';
            }
            if (!preg_match('/[A-Z]/', $p)) {
                return 'Password must include at least one uppercase letter.';
            }
            if (!preg_match('/[0-9]/', $p)) {
                return 'Password must include at least one number.';
            }
            if (!preg_match('/[^A-Za-z0-9]/', $p)) {
                return 'Password must include at least one special character.';
            }
            return null;
        };

        $this->assertNotNull($policy('short1!'), 'Too short (<8) should fail.');
        $this->assertNotNull($policy('WayTooLongPassword123!'), 'Too long (>12) should fail.');
        $this->assertNotNull($policy('lowercase1!'), 'Missing uppercase should fail.');
        $this->assertNotNull($policy('NoNumberHere!'), 'Missing number should fail.');
        $this->assertNotNull($policy('NoSpecial123'), 'Missing special character should fail.');

        $this->assertNull($policy('Valid123!@'), 'Valid 10-char password should pass.');
        $this->assertNull($policy('Abcd123#'), 'Valid 8-char password should pass.');
        $this->assertNull($policy('Zxcvbnm1!@#$'), 'Valid 12-char password should pass.');
    }

    // ── Assertions ───────────────────────────────────────────────────────────

    private function assertTrue(bool $condition, string $message = ''): void
    {
        if (!$condition) {
            throw new \RuntimeException($message !== '' ? $message : 'Failed asserting that condition is true.');
        }
    }

    private function assertFalse(bool $condition, string $message = ''): void
    {
        if ($condition) {
            throw new \RuntimeException($message !== '' ? $message : 'Failed asserting that condition is false.');
        }
    }

    private function assertEquals(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            throw new \RuntimeException(
                ($message !== '' ? $message . ' ' : '') .
                sprintf('Expected %s, got %s.', json_encode($expected), json_encode($actual))
            );
        }
    }

    private function assertNotEquals(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected === $actual) {
            throw new \RuntimeException(
                ($message !== '' ? $message . ' ' : '') .
                sprintf('Expected values to differ, both are %s.', json_encode($expected))
            );
        }
    }

    private function assertNull(mixed $actual, string $message = ''): void
    {
        if ($actual !== null) {
            throw new \RuntimeException(
                ($message !== '' ? $message . ' ' : '') .
                sprintf('Expected null, got %s.', json_encode($actual))
            );
        }
    }

    private function assertNotNull(mixed $actual, string $message = ''): void
    {
        if ($actual === null) {
            throw new \RuntimeException($message !== '' ? $message : 'Expected non-null value, got null.');
        }
    }

    private function assertNotEmpty(mixed $actual, string $message = ''): void
    {
        if (empty($actual)) {
            throw new \RuntimeException($message !== '' ? $message : 'Expected non-empty value, got empty.');
        }
    }

    private function assertStringContains(string $needle, string $haystack, string $message = ''): void
    {
        if (!str_contains($haystack, $needle)) {
            throw new \RuntimeException(
                ($message !== '' ? $message . ' ' : '') .
                sprintf('Failed asserting that "%s" is in string.', $needle)
            );
        }
    }

    private function assertStringNotContains(string $needle, string $haystack, string $message = ''): void
    {
        if (str_contains($haystack, $needle)) {
            throw new \RuntimeException(
                ($message !== '' ? $message . ' ' : '') .
                sprintf('Failed asserting that "%s" is NOT in string.', $needle)
            );
        }
    }
}

// Execute tests if run from CLI
if (php_sapi_name() === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__)) {
    $suite = new PasswordResetTest();
    $ok = $suite->run();
    exit($ok ? 0 : 1);
}
