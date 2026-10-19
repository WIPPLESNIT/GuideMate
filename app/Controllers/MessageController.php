<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Translator;
use App\Models\ConversationSetting;
use App\Models\Listing;
use App\Models\Message;
use App\Models\User;

final class MessageController extends Controller
{
    public function index(): void
    {
        $this->renderMessages('inbox');
    }

    public function archived(): void
    {
        $this->renderMessages('archived');
    }

    public function thread(string $partner): void
    {
        $this->renderThread('inbox', (int) $partner);
    }

    public function archivedThread(string $partner): void
    {
        $this->renderThread('archived', (int) $partner);
    }

    public function pin(string $partner): void
    {
        $this->verifyCsrf();
        $userId = (int) Auth::id();
        $partnerId = $this->validPartnerId($partner);
        $settings = ConversationSetting::forPair($userId, $partnerId);
        $pinned = !((bool) ($settings['is_pinned'] ?? false));
        ConversationSetting::setPinned($userId, $partnerId, $pinned);
        flash('success', $pinned ? 'Conversation pinned.' : 'Conversation unpinned.');
        $settings = ConversationSetting::forPair($userId, $partnerId);
        $base = ($settings['is_archived'] ?? false) ? '/messages/archived/' : '/messages/';
        redirect($base . $partnerId);
    }

    public function archive(string $partner): void
    {
        $this->verifyCsrf();
        $userId = (int) Auth::id();
        $partnerId = $this->validPartnerId($partner);
        ConversationSetting::setArchived($userId, $partnerId, true);
        flash('info', 'Conversation archived.');
        redirect('/messages/archived');
    }

    public function unarchive(string $partner): void
    {
        $this->verifyCsrf();
        $userId = (int) Auth::id();
        $partnerId = $this->validPartnerId($partner);
        ConversationSetting::setArchived($userId, $partnerId, false);
        flash('success', 'Conversation moved back to your inbox.');
        redirect('/messages/' . $partnerId);
    }

    public function deleteConversation(string $partner): void
    {
        $this->verifyCsrf();
        $userId = (int) Auth::id();
        $partnerId = $this->validPartnerId($partner);
        $wasArchived = (bool) (ConversationSetting::forPair($userId, $partnerId)['is_archived'] ?? false);
        Message::deleteBetween($userId, $partnerId);
        ConversationSetting::remove($userId, $partnerId);
        flash('info', 'Conversation deleted.');
        redirect($wasArchived ? '/messages/archived' : '/messages');
    }

    public function poll(string $partner): void
    {
        $userId = (int) Auth::id();
        User::touchLastSeen($userId);
        $partnerId = $this->validPartnerId($partner);
        $since = (int) $this->input('since', 0);
        $messages = Message::since($userId, $partnerId, $since);
        if ($messages !== []) {
            Message::markRead($userId, $partnerId);
        }

        $user = Auth::user();
        $targetLang = strtolower(trim((string) $this->input('lang', '')));
        if ($targetLang === '') {
            $targetLang = User::isProviderRole((string) ($user['role'] ?? ''))
                ? 'en'
                : (string) ($_SESSION['_locale'] ?? 'en');
        }

        $autoTranslate = (bool) $this->input('auto_translate', false);
        $items = [];
        foreach ($messages as $m) {
            $item = $m;
            $isIncoming = (int) $m['sender_id'] !== $userId;
            $msgId = (int) $m['id'];

            $cached = Message::cachedTranslation($msgId, $targetLang);
            if ($cached !== null) {
                $item['translated_body'] = $cached['body'];
                $item['source_lang'] = $cached['source_lang'];
                $item['translated'] = true;
            } elseif ($isIncoming && $autoTranslate) {
                $res = Translator::translate((string) $m['body'], $targetLang, $m['source_lang'] ?? null);
                if ($res['translated']) {
                    Message::cacheTranslation($msgId, $targetLang, $res['body'], $res['source']);
                    if (empty($m['source_lang']) && !empty($res['source'])) {
                        Message::setSourceLang($msgId, (string) $res['source']);
                    }
                    $item['translated_body'] = $res['body'];
                    $item['source_lang'] = $res['source'];
                    $item['translated'] = true;
                }
            }
            $items[] = $item;
        }

        $partnerUser = User::find($partnerId);
        $this->json([
            'messages' => $items,
            'partner_online' => User::isOnline($partnerId),
            'partner_last_seen' => $partnerUser['last_seen_at'] ?? null,
        ]);
    }

    public function translate(string $id): void
    {
        $this->verifyCsrf();
        $userId = (int) Auth::id();
        $message = Message::find((int) $id);
        if ($message === null) {
            $this->json(['error' => 'Message not found.'], 404);
            return;
        }

        if ((int) $message['sender_id'] !== $userId && (int) $message['receiver_id'] !== $userId) {
            $this->json(['error' => 'Forbidden.'], 403);
            return;
        }

        $user = Auth::user();
        $targetLang = strtolower(trim((string) $this->input('target_lang', '')));
        if ($targetLang === '') {
            $targetLang = User::isProviderRole((string) ($user['role'] ?? ''))
                ? 'en'
                : (string) ($_SESSION['_locale'] ?? 'en');
        }

        $msgId = (int) $message['id'];
        $original = (string) $message['body'];
        $known = !empty($message['source_lang']) ? (string) $message['source_lang'] : null;

        $cached = Message::cachedTranslation($msgId, $targetLang);
        if ($cached !== null) {
            $this->json([
                'id' => $msgId,
                'translated' => true,
                'original' => $original,
                'body' => $cached['body'],
                'source_lang' => $cached['source_lang'],
                'target_lang' => $targetLang,
                'cached' => true,
            ]);
            return;
        }

        $result = Translator::translate($original, $targetLang, $known);
        if ($result['translated']) {
            Message::cacheTranslation($msgId, $targetLang, $result['body'], $result['source']);
            if ($known === null && !empty($result['source'])) {
                Message::setSourceLang($msgId, (string) $result['source']);
            }
            $this->json([
                'id' => $msgId,
                'translated' => true,
                'original' => $original,
                'body' => $result['body'],
                'source_lang' => $result['source'],
                'target_lang' => $targetLang,
                'cached' => false,
            ]);
            return;
        }

        $this->json([
            'id' => $msgId,
            'translated' => false,
            'original' => $original,
            'body' => $result['body'] ?? $original,
            'source_lang' => $result['source'] ?? $known,
            'target_lang' => $targetLang,
            'note' => 'Already in target language or translation unavailable.',
        ]);
    }

    public function pingPresence(): void
    {
        $userId = Auth::id();
        if ($userId !== null) {
            User::touchLastSeen((int) $userId);
        }
        $this->json(['ok' => true, 'timestamp' => time()]);
    }

    public function leavePresence(): void
    {
        $userId = Auth::id();
        if ($userId !== null) {
            User::markOffline((int) $userId);
        }
        $this->json(['ok' => true]);
    }

    /**
     * @param 'inbox'|'archived' $folder
     */
    private function renderMessages(string $folder): void
    {
        $userId = (int) Auth::id();
        User::touchLastSeen($userId);
        $user = Auth::user();
        $targetLang = User::isProviderRole((string) ($user['role'] ?? ''))
            ? 'en'
            : (string) ($_SESSION['_locale'] ?? 'en');

        $this->view('messages/index', [
            'title' => $folder === 'archived' ? 'Archived messages' : 'Messages',
            'folder' => $folder,
            'conversations' => $folder === 'archived'
                ? Message::archivedConversations($userId)
                : Message::conversations($userId),
            'partner' => null,
            'isPartnerOnline' => false,
            'targetLang' => $targetLang,
            'thread' => [],
            'convSettings' => null,
        ]);
    }

    /**
     * @param 'inbox'|'archived' $folder
     */
    private function renderThread(string $folder, int $partnerId): void
    {
        $userId = (int) Auth::id();
        User::touchLastSeen($userId);
        $user = Auth::user();
        $partnerUser = User::find($partnerId);
        if ($partnerUser === null) {
            abort(404, 'Conversation not found.');
        }

        $settings = ConversationSetting::forPair($userId, $partnerId);
        $isArchived = (bool) ($settings['is_archived'] ?? false);
        if ($folder === 'archived' && !$isArchived) {
            redirect('/messages/' . $partnerId);
        }
        if ($folder === 'inbox' && $isArchived) {
            redirect('/messages/archived/' . $partnerId);
        }

        Message::markRead($userId, $partnerId);

        $targetLang = User::isProviderRole((string) ($user['role'] ?? ''))
            ? 'en'
            : (string) ($_SESSION['_locale'] ?? 'en');

        $thread = Message::thread($userId, $partnerId);
        $translateFrom = max(0, count($thread) - 40);
        foreach ($thread as $i => &$m) {
            $isIncoming = (int) $m['sender_id'] !== $userId;
            $msgId = (int) $m['id'];
            $cached = Message::cachedTranslation($msgId, $targetLang);
            if ($cached !== null) {
                $m['cached_translation'] = $cached;
            } elseif ($isIncoming && $i >= $translateFrom) {
                $res = Translator::translate((string) $m['body'], $targetLang, $m['source_lang'] ?? null);
                if ($res['translated']) {
                    Message::cacheTranslation($msgId, $targetLang, $res['body'], $res['source']);
                    if (empty($m['source_lang']) && !empty($res['source'])) {
                        Message::setSourceLang($msgId, (string) $res['source']);
                    }
                    $m['cached_translation'] = [
                        'body' => $res['body'],
                        'source_lang' => $res['source'],
                    ];
                }
            }
        }
        unset($m);

        $this->view('messages/index', [
            'title' => $folder === 'archived' ? 'Archived messages' : 'Messages',
            'folder' => $folder,
            'conversations' => $folder === 'archived'
                ? Message::archivedConversations($userId)
                : Message::conversations($userId),
            'partner' => $partnerUser,
            'isPartnerOnline' => User::isOnline($partnerId),
            'targetLang' => $targetLang,
            'thread' => $thread,
            'convSettings' => $settings,
        ]);
    }

    public function send(): void
    {
        $this->verifyCsrf();
        $userId = (int) Auth::id();
        User::touchLastSeen($userId);
        $receiverId = (int) $this->input('receiver_id', 0);
        $body = trim((string) $this->input('body', ''));

        if ($receiverId === 0 || $receiverId === $userId || $body === '') {
            redirect('/messages' . ($receiverId ? '/' . $receiverId : ''));

        }
        if (User::find($receiverId) === null) {
            abort(404, 'Recipient not found.');
        }

        // Replying to an archived chat brings it back to the inbox.
        $settings = ConversationSetting::forPair($userId, $receiverId);
        if ($settings !== null && (bool) ($settings['is_archived'] ?? false)) {
            ConversationSetting::setArchived($userId, $receiverId, false);
        }

        Message::send($userId, $receiverId, $body);
        redirect('/messages/' . $receiverId);
    }

    public function contactGuide(string $id): void
    {
        $this->verifyCsrf();
        $listing = Listing::find((int) $id);
        if ($listing === null) {
            abort(404, 'Listing not found.');
        }

        $userId = (int) Auth::id();
        $guideId = (int) $listing['user_id'];
        if ($guideId === $userId) {
            flash('info', 'This is your own listing.');
            redirect('/listing/' . $listing['slug']);
        }

        $body = trim((string) $this->input('body', ''));
        if ($body === '') {
            $body = 'Hi! I am interested in "' . $listing['title'] . '". Could you share more details?';
        }

        Message::send($userId, $guideId, $body, (int) $listing['id']);
        $labels = host_labels((string) ($listing['owner_role'] ?? ''));
        flash('success', $labels['flash']);
        redirect('/messages/' . $guideId);
    }

    public function contactGuideProfile(string $id): void
    {
        $this->verifyCsrf();
        $guide = User::find((int) $id);
        if ($guide === null || ($guide['role'] ?? '') !== 'guide' || ($guide['guide_status'] ?? '') !== 'approved') {
            abort(404, 'Guide not found.');
        }

        $userId = (int) Auth::id();
        $guideId = (int) $guide['id'];
        if ($guideId === $userId) {
            flash('info', 'This is your own profile.');
            redirect('/tour-guides');
        }

        $body = trim((string) $this->input('body', ''));
        if ($body === '') {
            $body = 'Hi! I would like to book a tour with you. Are you available?';
        }

        Message::send($userId, $guideId, $body);
        flash('success', 'Message sent to the guide.');
        redirect('/messages/' . $guideId);
    }

    private function validPartnerId(string $partner): int
    {
        $partnerId = (int) $partner;
        $partnerUser = User::find($partnerId);
        if ($partnerUser === null || $partnerId === (int) Auth::id()) {
            abort(404, 'Conversation not found.');
        }
        return $partnerId;
    }
}
