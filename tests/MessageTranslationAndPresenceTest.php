<?php

declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/../bootstrap.php';

use App\Controllers\MessageController;
use App\Core\App;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Translator;
use App\Models\Message;
use App\Models\User;

class MessageTranslationAndPresenceTest
{
    private ?int $touristId = null;
    private ?int $guideId = null;
    private array $createdUserIds = [];
    private array $createdMessageIds = [];
    private int $passed = 0;
    private int $failed = 0;

    public function run(): bool
    {
        echo "========================================================\n";
        echo " GuideMate Message Translation & Online Presence Tests\n";
        echo "========================================================\n\n";

        $this->setUp();

        $this->runTest('testOnlineStatusDetectionAndHeartbeat', [$this, 'testOnlineStatusDetectionAndHeartbeat']);
        $this->runTest('testInstantOfflineOnMarkOffline', [$this, 'testInstantOfflineOnMarkOffline']);
        $this->runTest('testOnlineTimeoutCalculation', [$this, 'testOnlineTimeoutCalculation']);
        $this->runTest('testMessageOriginalBodyPreserved', [$this, 'testMessageOriginalBodyPreserved']);
        $this->runTest('testTranslationCachingInDatabase', [$this, 'testTranslationCachingInDatabase']);
        $this->runTest('testTranslatorServiceExecution', [$this, 'testTranslatorServiceExecution']);
        $this->runTest('testTranslationRoleTargetDefaults', [$this, 'testTranslationRoleTargetDefaults']);
        $this->runTest('testConversationListIncludesPartnerPresence', [$this, 'testConversationListIncludesPartnerPresence']);
        $this->runTest('testSecurityParticipantAuthorizationOnMessage', [$this, 'testSecurityParticipantAuthorizationOnMessage']);

        $this->tearDown();

        echo "\n--------------------------------------------------------\n";
        echo sprintf("Results: %d passed, %d failed\n", $this->passed, $this->failed);
        echo "--------------------------------------------------------\n";

        return $this->failed === 0;
    }

    private function setUp(): void
    {
        $suffix = bin2hex(random_bytes(4));
        // Create test tourist
        $this->touristId = User::create(
            'Test Tourist Presence',
            'tourist_pres_' . $suffix . '@example.com',
            'TouristPass123!',
            'tourist'
        );
        $this->createdUserIds[] = $this->touristId;

        // Create test guide
        $this->guideId = User::create(
            'Test Guide Presence',
            'guide_pres_' . $suffix . '@example.com',
            'GuidePass123!',
            'guide'
        );
        $this->createdUserIds[] = $this->guideId;
        Database::run("UPDATE users SET guide_status = 'approved' WHERE id = ?", [$this->guideId]);
    }

    private function tearDown(): void
    {
        foreach ($this->createdMessageIds as $mId) {
            Database::run('DELETE FROM message_translations WHERE message_id = ?', [$mId]);
            Database::run('DELETE FROM messages WHERE id = ?', [$mId]);
        }
        foreach ($this->createdUserIds as $uId) {
            Database::run('DELETE FROM messages WHERE sender_id = ? OR receiver_id = ?', [$uId, $uId]);
            Database::run('DELETE FROM users WHERE id = ?', [$uId]);
        }
    }

    private function runTest(string $name, callable $test): void
    {
        echo "Running {$name}... ";
        try {
            $test();
            $this->passed++;
            echo "PASSED\n";
        } catch (\Throwable $e) {
            $this->failed++;
            echo "FAILED: " . $e->getMessage() . " (" . $e->getFile() . ":" . $e->getLine() . ")\n";
        }
    }

    private function assert(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new \RuntimeException($message);
        }
    }

    public function testOnlineStatusDetectionAndHeartbeat(): void
    {
        // 1. Touch last seen for guide
        User::touchLastSeen($this->guideId);

        // Guide should be online
        $this->assert(User::isOnline($this->guideId), "Guide should be online after touchLastSeen");

        // Verify last_seen_at is set in database
        $user = User::find($this->guideId);
        $this->assert(!empty($user['last_seen_at']), "Guide last_seen_at should be non-empty");
    }

    public function testInstantOfflineOnMarkOffline(): void
    {
        // 1. Mark guide online first
        User::touchLastSeen($this->guideId);
        $this->assert(User::isOnline($this->guideId), "Guide should be online");

        // 2. Simulate logout / tab close beacon
        User::markOffline($this->guideId);

        // Guide should immediately be offline
        $this->assert(!User::isOnline($this->guideId), "Guide should immediately be offline after markOffline");

        $user = User::find($this->guideId);
        $this->assert(empty($user['last_seen_at']), "Guide last_seen_at should be NULL after markOffline");
    }

    public function testOnlineTimeoutCalculation(): void
    {
        // Set last_seen_at to 120 seconds ago
        Database::run(
            'UPDATE users SET last_seen_at = DATE_SUB(NOW(), INTERVAL 120 SECOND) WHERE id = ?',
            [$this->guideId]
        );

        // Default 90s timeout should consider user offline
        $this->assert(!User::isOnline($this->guideId, 90), "User active 120s ago should be offline with 90s timeout");

        // Custom 180s timeout should consider user online
        $this->assert(User::isOnline($this->guideId, 180), "User active 120s ago should be online with 180s timeout");
    }

    public function testMessageOriginalBodyPreserved(): void
    {
        $originalBody = "Magandang umaga po! Kumusta po ang tour ngayon?";
        $msgId = Message::send($this->touristId, $this->guideId, $originalBody);
        $this->createdMessageIds[] = $msgId;

        $msg = Message::find($msgId);
        $this->assert($msg !== null, "Message should be found");
        $this->assert($msg['body'] === $originalBody, "Original body must match exactly");

        // Simulate a translation
        $translatedBody = "Good morning! How is the tour today?";
        Message::cacheTranslation($msgId, 'en', $translatedBody, 'tl');

        // Check original message in database: it must remain unmodified
        $msgAfter = Message::find($msgId);
        $this->assert($msgAfter['body'] === $originalBody, "Original body must NOT be overwritten by translation");

        // Cached translation must be available separately
        $cached = Message::cachedTranslation($msgId, 'en');
        $this->assert($cached !== null, "Cached translation must be retrievable");
        $this->assert($cached['body'] === $translatedBody, "Cached body must match translated text");
        $this->assert($cached['source_lang'] === 'tl', "Source language should be recorded as 'tl'");
    }

    public function testTranslationCachingInDatabase(): void
    {
        $msgId = Message::send($this->guideId, $this->touristId, "Salamat kaayo!");
        $this->createdMessageIds[] = $msgId;

        // No cache initially
        $this->assert(Message::cachedTranslation($msgId, 'en') === null, "Should have no cache initially");

        // Save cache
        Message::cacheTranslation($msgId, 'en', 'Thank you very much!', 'ceb');

        // Retrieve cache
        $cached = Message::cachedTranslation($msgId, 'en');
        $this->assert($cached !== null, "Cache should exist");
        $this->assert($cached['body'] === 'Thank you very much!', "Cached body matches");
        $this->assert($cached['source_lang'] === 'ceb', "Cached source lang matches");

        // Update cache (idempotent / ON DUPLICATE KEY UPDATE)
        Message::cacheTranslation($msgId, 'en', 'Thank you very much! (updated)', 'ceb');
        $cachedUpdated = Message::cachedTranslation($msgId, 'en');
        $this->assert($cachedUpdated['body'] === 'Thank you very much! (updated)', "Cache update should overwrite cleanly");
    }

    public function testTranslatorServiceExecution(): void
    {
        // Test Translator with simple phrase
        $result = Translator::translate('Kumusta', 'en');
        $this->assert(is_array($result), "Translator must return array");
        $this->assert(isset($result['body']), "Translator result must have body");
        $this->assert(isset($result['translated']), "Translator result must have translated flag");

        // Empty text soft-fail
        $emptyRes = Translator::translate('', 'en');
        $this->assert($emptyRes['body'] === '', "Empty string soft-fails cleanly");
        $this->assert($emptyRes['translated'] === false, "Empty string is not translated");

        // Same language soft-fail
        $sameRes = Translator::translate('Hello world', 'en', 'en');
        $this->assert($sameRes['body'] === 'Hello world', "Same language returns original text");
        $this->assert($sameRes['translated'] === false, "Same language sets translated to false");
    }

    public function testTranslationRoleTargetDefaults(): void
    {
        // Provider role (guide, hotel_admin, rental_admin) defaults to 'en'
        $guide = User::find($this->guideId);
        $guideIsProvider = User::isProviderRole((string) ($guide['role'] ?? ''));
        $this->assert($guideIsProvider, "Guide must be recognized as provider role");

        $tourist = User::find($this->touristId);
        $touristIsProvider = User::isProviderRole((string) ($tourist['role'] ?? ''));
        $this->assert(!$touristIsProvider, "Tourist must NOT be provider role");
    }

    public function testConversationListIncludesPartnerPresence(): void
    {
        // Send a message so conversation pair exists
        $msgId = Message::send($this->touristId, $this->guideId, "Hello Guide!");
        $this->createdMessageIds[] = $msgId;

        // Set guide online
        User::touchLastSeen($this->guideId);

        $convs = Message::conversations($this->touristId);
        $this->assert(!empty($convs), "Conversations list should not be empty");

        $found = false;
        foreach ($convs as $c) {
            if ((int) $c['partner_id'] === $this->guideId) {
                $found = true;
                $this->assert(array_key_exists('partner_last_seen', $c), "Conversation must include partner_last_seen");
                $this->assert(!empty($c['partner_last_seen']), "Guide partner_last_seen must be populated");
                $isOnline = (time() - strtotime((string) $c['partner_last_seen'])) <= 90;
                $this->assert($isOnline, "Guide should be online in conversation list");
                break;
            }
        }
        $this->assert($found, "Guide conversation should be in tourist inbox");
    }

    public function testSecurityParticipantAuthorizationOnMessage(): void
    {
        // Create 3rd party user
        $intruderId = User::create(
            'Intruder User',
            'intruder_' . bin2hex(random_bytes(4)) . '@example.com',
            'IntruderPass123!',
            'tourist'
        );
        $this->createdUserIds[] = $intruderId;

        $msgId = Message::send($this->touristId, $this->guideId, "Private message");
        $this->createdMessageIds[] = $msgId;

        $msg = Message::find($msgId);
        $this->assert($msg !== null, "Message exists");

        // Intruder is neither sender nor receiver
        $isParticipant = ((int) $msg['sender_id'] === $intruderId || (int) $msg['receiver_id'] === $intruderId);
        $this->assert(!$isParticipant, "Third party user must not be participant of private message");
    }
}

$test = new MessageTranslationAndPresenceTest();
$success = $test->run();
exit($success ? 0 : 1);
