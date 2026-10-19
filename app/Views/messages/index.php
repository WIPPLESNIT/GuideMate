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
                <?php foreach ($conversations as $c):
                    $isConvOnline = !empty($c['partner_last_seen']) && (time() - strtotime((string) $c['partner_last_seen']) <= 90);
                ?>
                    <a class="conv <?= ($partner && (int) $partner['id'] === (int) $c['partner_id']) ? 'active' : '' ?>"
                       href="<?= e(url($convBase . $c['partner_id'])) ?>">
                        <div class="conv-avatar-wrap">
                            <img src="<?= e(img_src($c['partner_avatar'] ?? null, 'avatar' . $c['partner_id'])) ?>" alt="">
                            <span class="presence-dot <?= $isConvOnline ? 'online' : 'offline' ?>" id="convDot-<?= (int) $c['partner_id'] ?>" title="<?= $isConvOnline ? 'Online' : 'Offline' ?>"></span>
                        </div>
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
                        <span class="presence-badge <?= ($isPartnerOnline ?? false) ? 'online' : 'offline' ?>" id="partnerPresenceBadge" data-partner-id="<?= (int) $partner['id'] ?>">
                            <span class="presence-indicator"></span>
                            <span class="presence-text"><?= ($isPartnerOnline ?? false) ? 'Online' : 'Offline' ?></span>
                        </span>
                        <?php if ($isArchived): ?><span class="pill pill-pending" style="margin-left:.5rem;font-size:.72rem;">Archived</span><?php endif; ?>
                    </div>
                    <div class="thread-actions">
                        <button type="button" class="btn-auto-translate active" id="btnAutoTranslate" title="Automatically translate incoming messages" aria-pressed="true">
                            <svg class="trans-head-icon" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="m5 8 6 6"/>
                                <path d="m4 14 6-6 2-3"/>
                                <path d="M2 5h12"/>
                                <path d="M7 2h1"/>
                                <path d="m22 22-5-10-5 10"/>
                                <path d="M14 18h6"/>
                            </svg>
                            <span>Auto-translate</span>
                            <span class="auto-translate-badge" id="autoTranslateBadge">ON</span>
                        </button>
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
                </div>
                <div class="thread-body" id="threadBody"
                     data-me-id="<?= $me ?>"
                     data-partner-id="<?= (int) $partner['id'] ?>"
                     data-poll-url="<?= e(url('/messages/' . $partner['id'] . '/poll')) ?>"
                     data-target-lang="<?= e($targetLang ?? 'en') ?>"
                     data-last-id="<?= $thread !== [] ? (int) end($thread)['id'] : 0 ?>">
                    <?php if ($thread === []): ?>
                        <div class="empty-thread"><p class="hint">No messages yet. Say hello!</p></div>
                    <?php else: ?>
                        <?php foreach ($thread as $m):
                            $isMine = (int) $m['sender_id'] === $me;
                            $hasCached = !empty($m['cached_translation']);
                            $transBody = $hasCached ? (string) $m['cached_translation']['body'] : '';
                            $transSrc = $hasCached ? (string) ($m['cached_translation']['source_lang'] ?? '') : '';
                            $displayBody = ($hasCached && $transBody !== '') ? $transBody : (string) $m['body'];
                        ?>
                            <div class="bubble <?= $isMine ? 'out' : 'in' ?>"
                                 data-msg-id="<?= (int) $m['id'] ?>"
                                 data-original="<?= e($m['body']) ?>"
                                 data-translated="<?= e($transBody) ?>"
                                 data-source-lang="<?= e($transSrc) ?>"
                                 data-viewing="<?= $hasCached ? 'translated' : 'original' ?>">

                                <?php if (!$isMine): ?>
                                    <button type="button" class="bubble-trans-btn <?= $hasCached ? 'is-translated' : '' ?>"
                                            data-msg-id="<?= (int) $m['id'] ?>"
                                            title="<?= $hasCached ? 'Toggle translation' : 'Translate message' ?>"
                                            aria-label="Translate message">
                                        <svg class="trans-icon" viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="m5 8 6 6"/>
                                            <path d="m4 14 6-6 2-3"/>
                                            <path d="M2 5h12"/>
                                            <path d="M7 2h1"/>
                                            <path d="m22 22-5-10-5 10"/>
                                            <path d="M14 18h6"/>
                                        </svg>
                                    </button>
                                <?php endif; ?>

                                <div class="bubble-text" id="bubbleText-<?= (int) $m['id'] ?>"><?= nl2br(e($displayBody)) ?></div>

                                <?php if (!$isMine): ?>
                                    <div class="bubble-trans-row <?= $hasCached ? '' : 'hidden' ?>" id="transRow-<?= (int) $m['id'] ?>">
                                        <button type="button" class="bubble-trans-toggle" data-msg-id="<?= (int) $m['id'] ?>">
                                            <svg class="trans-toggle-icon" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="m5 8 6 6"/>
                                                <path d="m4 14 6-6 2-3"/>
                                                <path d="M2 5h12"/>
                                                <path d="M7 2h1"/>
                                                <path d="m22 22-5-10-5 10"/>
                                                <path d="M14 18h6"/>
                                            </svg>
                                            <span class="bubble-trans-label"><?= $hasCached ? 'Show original' : 'Show translation' ?></span>
                                        </button>
                                        <span class="bubble-trans-status" id="transStatus-<?= (int) $m['id'] ?>"></span>
                                    </div>
                                <?php endif; ?>

                                <div class="bubble-meta">
                                    <small><?= e(date('g:i a', strtotime($m['created_at']))) ?></small>
                                </div>
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
