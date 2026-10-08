<?php
/**
 * @var array<int,array<string,mixed>> $conversations
 * @var ?array<string,mixed> $partner
 * @var array<int,array<string,mixed>> $thread
 * @var ?array<string,mixed> $convSettings
 * @var string $folder
 */
$me = (int) auth_user()['id'];
$folder = $folder ?? 'inbox';
$isArchived = $folder === 'archived';
$convBase = $isArchived ? '/messages/archived/' : '/messages/';
// Providers (guides, rental & hotel admins) get the dashboard sidebar here too,
// so navigating to Messages doesn't lose the dashboard navigation.
$msgUser = auth_user();
$showDashNav = $msgUser !== null && \App\Models\User::isProviderRole((string) ($msgUser['role'] ?? ''));
?>
<div class="container">
    <?php if ($showDashNav): ?><div class="msg-shell"><?= \App\Core\View::partial('partials/dash-nav') ?><?php endif; ?>
    <div class="msg-layout">
        <div class="conv-list">
            <div class="conv-list-head">
                <span>Messages</span>
                <div class="conv-tabs">
                    <a href="<?= e(url('/messages')) ?>" class="conv-tab <?= !$isArchived ? 'active' : '' ?>">Inbox</a>
                    <a href="<?= e(url('/messages/archived')) ?>" class="conv-tab <?= $isArchived ? 'active' : '' ?>">Archived</a>
                </div>
            </div>
            <?php if ($conversations === []): ?>
                <div class="empty-state" style="padding:2rem 1rem;">
                    <p><?= $isArchived ? 'No archived conversations.' : 'No conversations yet.' ?></p>
                </div>
            <?php else: ?>
                <?php foreach ($conversations as $c): ?>
                    <a class="conv <?= ($partner && (int) $partner['id'] === (int) $c['partner_id']) ? 'active' : '' ?>"
                       href="<?= e(url($convBase . $c['partner_id'])) ?>">
                        <img src="<?= e(img_src($c['partner_avatar'] ?? null, 'avatar' . $c['partner_id'])) ?>" alt="">
                        <div style="min-width:0;flex:1;">
                            <div class="who"><?= e($c['partner_name']) ?>
                                <?php if ((int) ($c['is_pinned'] ?? 0) === 1): ?><span class="conv-pin" title="Pinned">📌</span><?php endif; ?>
                                <?php if (!$isArchived && (int) $c['unread'] > 0): ?><span class="badge"><?= (int) $c['unread'] ?></span><?php endif; ?>
                            </div>
                            <div class="preview"><?= e($c['last_body']) ?></div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="thread">
            <?php if ($partner === null): ?>
                <div class="empty-thread">
                    <div class="text-center">
                        <div class="big" style="font-size:3rem;"><?= $isArchived ? '📁' : '💬' ?></div>
                        <p><?= $isArchived ? 'Select an archived conversation to view it.' : 'Select a conversation to start chatting.' ?></p>
                    </div>
                </div>
            <?php else: ?>
                <div class="thread-head">
                    <div class="thread-head-main">
                        <span class="thread-head-name"><?= e($partner['name']) ?></span>
                        <span class="hint thread-head-role">· <?= e(ucfirst($partner['role'])) ?></span>
                        <?php if ($isArchived): ?><span class="pill pill-pending" style="margin-left:.5rem;font-size:.72rem;">Archived</span><?php endif; ?>
                    </div>
                    <div class="thread-menu">
                        <button type="button" class="thread-menu-btn" id="threadMenuBtn" aria-label="Conversation options" aria-expanded="false">⋯</button>
                        <div class="dropdown thread-dropdown" id="threadMenu">
                            <?php if ($isArchived): ?>
                                <form method="post" action="<?= e(url('/messages/' . $partner['id'] . '/unarchive')) ?>">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="link-button">Move to Inbox</button>
                                </form>
                            <?php else: ?>
                                <form method="post" action="<?= e(url('/messages/' . $partner['id'] . '/archive')) ?>">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="link-button">Archived</button>
                                </form>
                            <?php endif; ?>
                            <form method="post" action="<?= e(url('/messages/' . $partner['id'] . '/delete')) ?>" onsubmit="return confirm('Delete this entire conversation? This cannot be undone.');">
                                <?= csrf_field() ?>
                                <button type="submit" class="link-button">Delete Conversation</button>
                            </form>
                            <form method="post" action="<?= e(url('/messages/' . $partner['id'] . '/pin')) ?>">
                                <?= csrf_field() ?>
                                <button type="submit" class="link-button">Pin</button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="thread-body" id="threadBody" data-me-id="<?= $me ?>" data-partner-id="<?= (int) $partner['id'] ?>" data-poll-url="<?= e(url('/messages/' . $partner['id'] . '/poll')) ?>" data-last-id="<?= $thread !== [] ? (int) end($thread)['id'] : 0 ?>">
                    <?php if ($thread === []): ?>
                        <div class="empty-thread"><p class="hint">No messages yet. Say hello!</p></div>
                    <?php else: ?>
                        <?php foreach ($thread as $m): ?>
                            <div class="bubble <?= (int) $m['sender_id'] === $me ? 'out' : 'in' ?>">
                                <?= nl2br(e($m['body'])) ?>
                                <small><?= e(date('M j, g:i a', strtotime($m['created_at']))) ?></small>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <form class="thread-form" method="post" action="<?= e(url('/messages')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="receiver_id" value="<?= (int) $partner['id'] ?>">
                    <input class="input" type="text" name="body" placeholder="Type a message..." autocomplete="off" required autofocus>
                    <button class="btn btn-primary" type="submit">Send</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($showDashNav): ?></div><?php endif; ?>
</div>
