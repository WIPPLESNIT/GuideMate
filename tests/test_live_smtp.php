<?php

declare(strict_types=1);

/**
 * Diagnostic tool to verify live Gmail SMTP delivery.
 *
 * Usage:
 *   php tests/test_live_smtp.php [recipient@gmail.com]
 */

require dirname(__DIR__) . '/bootstrap.php';

use App\Core\App;
use App\Core\Mailer;

$recipient = $argv[1] ?? null;

echo "========================================================\n";
echo " GuideMate Gmail SMTP Live Connection Tester\n";
echo "========================================================\n\n";

$config = App::config('mail') ?? [];
$host = trim((string) ($config['host'] ?? ''));
$port = (int) ($config['port'] ?? 587);
$user = trim((string) ($config['username'] ?? ''));
$pass = (string) ($config['password'] ?? '');
$from = trim((string) ($config['from'] ?? ''));

echo "SMTP Settings in .env:\n";
echo "  MAIL_HOST:       " . ($host !== '' ? $host : '(empty)') . "\n";
echo "  MAIL_PORT:       " . $port . "\n";
echo "  MAIL_ENCRYPTION: " . ($config['encryption'] ?? 'tls') . "\n";
echo "  MAIL_USERNAME:   " . ($user !== '' ? $user : '(not configured yet)') . "\n";
echo "  MAIL_PASSWORD:   " . ($pass !== '' ? str_repeat('*', strlen($pass)) : '(not configured yet)') . "\n";
echo "  MAIL_FROM:       " . ($from !== '' ? $from : ($user !== '' ? $user : 'noreply@guidemate.local')) . "\n\n";

if ($user === '' || $pass === '') {
    echo "❌ Gmail SMTP is not yet fully configured in your .env file.\n\n";
    echo "To send real password reset emails directly to Gmail inboxes:\n";
    echo "1. Open .env in your editor.\n";
    echo "2. Set MAIL_USERNAME to your Gmail address (e.g. yourname@gmail.com).\n";
    echo "3. Generate a 16-character Google App Password:\n";
    echo "   - Go to: https://myaccount.google.com/apppasswords\n";
    echo "   - (Make sure 2-Step Verification is turned ON on your Google Account)\n";
    echo "   - Create an App Password with name 'GuideMate'\n";
    echo "   - Copy the 16 letters (e.g. abcd efgh ijkl mnop)\n";
    echo "4. Set MAIL_PASSWORD=abcdefghijklmnop in .env\n";
    echo "5. Re-run this test script: php tests/test_live_smtp.php <your_email@gmail.com>\n\n";
    exit(1);
}

if (!$recipient) {
    $recipient = $user;
    echo "No recipient provided as argument. Defaulting test recipient to sender: {$recipient}\n";
}

echo "Attempting to send live test email to: {$recipient} ...\n";

$subject = 'GuideMate Live SMTP Test (' . date('Y-m-d H:i:s') . ')';
$textBody = "Hello!\n\nThis is a test email sent from GuideMate using Gmail SMTP.\nIf you received this, real inbox delivery is working perfectly!\n";
$htmlBody = <<<HTML
<!DOCTYPE html>
<html>
<body style="font-family:sans-serif;padding:20px;background:#f8fafc;color:#1e293b;">
<div style="max-width:480px;margin:0 auto;background:#fff;padding:24px;border-radius:10px;border:1px solid #e2e8f0;">
  <h2 style="color:#059669;margin-top:0;">GuideMate SMTP Test</h2>
  <p>Success! This test email confirms that your <strong>Gmail SMTP</strong> configuration is active.</p>
  <p>Password reset links will now arrive directly in real Gmail inboxes.</p>
</div>
</body>
</html>
HTML;

$sent = Mailer::send($recipient, $subject, $textBody, $htmlBody);

if ($sent) {
    echo "\n🎉 SUCCESS! Test email was successfully accepted by Gmail SMTP.\n";
    echo "Please check the inbox (or Spam/Promotions folder) of: {$recipient}\n\n";
    exit(0);
} else {
    echo "\n❌ FAILED: The email could not be sent.\n";
    $errorLog = BASE_DIR . '/storage/logs/mail_error.log';
    if (is_file($errorLog)) {
        echo "Check storage/logs/mail_error.log for details:\n";
        $lines = file($errorLog);
        $recent = array_slice($lines, -15);
        echo implode('', $recent);
    }
    echo "\n";
    exit(1);
}
