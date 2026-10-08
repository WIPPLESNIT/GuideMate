<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
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
        $partnerId = $this->validPartnerId($partner);
        $since = (int) $this->input('since', 0);
        $messages = Message::since($userId, $partnerId, $since);
        if ($messages !== []) {
            Message::markRead($userId, $partnerId);
        }
        $this->json(['messages' => $messages]);
    }

    /**
     * @param 'inbox'|'archived' $folder
     */
    private function renderMessages(string $folder): void
    {
        $userId = (int) Auth::id();
        $this->view('messages/index', [
            'title' => $folder === 'archived' ? 'Archived messages' : 'Messages',
            'folder' => $folder,
            'conversations' => $folder === 'archived'
                ? Message::archivedConversations($userId)
                : Message::conversations($userId),
            'partner' => null,
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

        $this->view('messages/index', [
            'title' => $folder === 'archived' ? 'Archived messages' : 'Messages',
            'folder' => $folder,
            'conversations' => $folder === 'archived'
                ? Message::archivedConversations($userId)
                : Message::conversations($userId),
            'partner' => $partnerUser,
            'thread' => Message::thread($userId, $partnerId),
            'convSettings' => $settings,
        ]);
    }

    public function send(): void
    {
        $this->verifyCsrf();
        $userId = (int) Auth::id();
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
