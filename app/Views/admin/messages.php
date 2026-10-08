<?php
/**
 * @var array<int,array<string,mixed>> $conversations
 * @var array<string,mixed>|null $partner
 * @var array<int,array<string,mixed>> $thread
 * @var int $adminId
 */
$partnerId = $partner !== null ? (int) $partner['id'] : 0;
$partnerRole = (string) ($partner['role'] ?? '');
$partnerRoleLabel = \App\Models\User::PROVIDER_LABELS[$partnerRole] ?? ucfirst(str_replace('_', ' ', $partnerRole));
$partnerWarned = $partner !== null
    && \App\Models\User::isProviderRole($partnerRole)
    && (int) ($partner['guide_warned'] ?? 0) === 1;
?>
<h1>Messages</h1>
<p class="hint" style="margin-top:-.4rem;">Replies from guides, hotel partners and rental partners (including responses to warnings) appear here.</p>

<div class="panel">
    <div class="admin-msg">
        <!-- Conversation list -->
        <div class="admin-msg-list">
            <?php if ($conversations === []): ?>
                <div class="empty-state" style="padding:1.5rem 1rem;">No conversations yet.</div>
            <?php else: ?>
                <?php foreach ($conversations as $c): ?>
                    <?php $cid = (int) $c['partner_id']; ?>
                    <a href="<?= e(url('/admin/messages/' . $cid)) ?>"
                       class="admin-msg-conv <?= $cid === $partnerId ? 'is-active' : '' ?>">
                        <img src="<?= e(img_src($c['partner_avatar'] ?? null, 'avatar' . $cid)) ?>" alt="">
                        <div class="admin-msg-conv-main">
                            <div class="admin-msg-conv-top">
                                <span class="admin-msg-conv-name"><?= e($c['partner_name']) ?></span>
                                <span class="admin-msg-conv-flags">
                                    <?php if ((int) ($c['partner_warned'] ?? 0) === 1): ?>
                                        <span class="pill pill-disputed">Restricted</span>
                                    <?php endif; ?>
                                    <?php if ((int) ($c['unread'] ?? 0) > 0): ?>
                                        <span class="badge"><?= (int) $c['unread'] ?></span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="admin-msg-conv-preview"><?= e(mb_strimwidth((string) $c['last_body'], 0, 48, '…')) ?></div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Thread + reply -->
        <div class="admin-msg-thread">
            <?php if ($partner === null): ?>
                <div class="empty-state" style="margin:auto;padding:2rem;">Select a conversation to read and reply.</div>
            <?php else: ?>
                <div class="admin-msg-head">
                    <img src="<?= e(img_src($partner['avatar'] ?? null, 'avatar' . $partnerId)) ?>" alt="">
                    <div>
                        <strong><?= e($partner['name']) ?></strong>
                        <span class="hint"><?= e($partnerRoleLabel) ?></span>
                    </div>
                    <?php if ($partnerWarned): ?>
                        <span class="pill pill-disputed">Restricted</span>
                    <?php endif; ?>
                </div>

                <?php if ($partnerWarned): ?>
                    <div class="admin-msg-warn">
                        <div>
                            <strong>Booking actions are restricted</strong>
                            <p>
                                This partner cannot Confirm, Complete, or Cancel bookings until you unrestrict them.
                                After you confirm they are not harming tourists, click Unrestrict account.
                            </p>
                            <?php if (!empty($partner['guide_warning_note'])): ?>
                                <p class="hint mb-0">Warning reason: <?= e((string) $partner['guide_warning_note']) ?></p>
                            <?php endif; ?>
                        </div>
                        <?= \App\Core\View::partial('partials/admin-unrestrict', [
                            'id' => $partnerId,
                            'name' => (string) $partner['name'],
                            'return' => '/admin/messages/' . $partnerId,
                        ]) ?>
                    </div>
                <?php endif; ?>

                <div class="admin-msg-body" id="adminMsgBody">
                    <?php if ($thread === []): ?>
                        <div class="empty-state" style="margin:auto;">No messages yet.</div>
                    <?php else: ?>
                        <?php foreach ($thread as $m): ?>
                            <?php $mine = (int) $m['sender_id'] === $adminId; ?>
                            <div class="admin-bubble <?= $mine ? 'mine' : 'theirs' ?>">
                                <?= nl2br(e((string) $m['body'])) ?>
                                <span class="admin-bubble-time"><?= e(loc_date((string) $m['created_at'])) ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <form method="post" action="<?= e(url('/admin/messages/send')) ?>" class="admin-msg-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="receiver_id" value="<?= $partnerId ?>">
                    <textarea name="body" class="input" rows="2" placeholder="Type your reply…" required></textarea>
                    <button type="submit" class="btn btn-primary">Send</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
(function () {
    var body = document.getElementById('adminMsgBody');
    if (body) { body.scrollTop = body.scrollHeight; }
})();
</script>
