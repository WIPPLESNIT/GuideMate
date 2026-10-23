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
$lastMsgId = $thread !== [] ? (int) end($thread)['id'] : 0;
?>
<h1>Messages</h1>
<p class="hint" style="margin-top:-.4rem;">Replies from guides, hotel partners and rental partners (including responses to warnings) appear here.</p>

<div class="panel admin-msg-panel">
    <div class="admin-msg">
        <!-- Conversation list -->
        <div class="admin-msg-list">
            <div class="admin-msg-list-head">
                <span>Conversations</span>
            </div>
            <?php if ($conversations === []): ?>
                <div class="empty-state" style="padding:2.5rem 1rem; text-align:center;">No conversations yet.</div>
            <?php else: ?>
                <?php foreach ($conversations as $c): ?>
                    <?php $cid = (int) $c['partner_id']; ?>
                    <a href="<?= e(url('/admin/messages/' . $cid)) ?>"
                       id="convItem-<?= $cid ?>"
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
                            <div class="admin-msg-conv-preview" id="convPreview-<?= $cid ?>"><?= e(mb_strimwidth((string) $c['last_body'], 0, 48, '…')) ?></div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Thread + reply -->
        <div class="admin-msg-thread">
            <?php if ($partner === null): ?>
                <div class="admin-empty-thread">
                    <div class="admin-empty-thread-icon">💬</div>
                    <p class="admin-empty-thread-title">No conversation selected</p>
                    <p class="hint">Choose a partner from the list to view and send messages.</p>
                </div>
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

                <div class="admin-msg-body" id="adminMsgBody"
                     data-partner-id="<?= $partnerId ?>"
                     data-admin-id="<?= $adminId ?>"
                     data-poll-url="<?= e(url('/admin/messages/' . $partnerId . '/poll')) ?>"
                     data-last-id="<?= $lastMsgId ?>">
                    <?php if ($thread === []): ?>
                        <div class="admin-empty-thread" id="adminEmptyThread">
                            <div class="admin-empty-thread-icon">👋</div>
                            <p class="admin-empty-thread-title">No messages yet</p>
                            <p class="hint">Send a message below to start the conversation.</p>
                        </div>
                    <?php else: ?>
                        <?php
                        $lastDate = null;
                        foreach ($thread as $m):
                            $mine = (int) $m['sender_id'] === $adminId;
                            $msgDate = date('Y-m-d', strtotime((string) $m['created_at']));
                            if ($msgDate !== $lastDate):
                                $lastDate = $msgDate;
                                $isToday = $msgDate === date('Y-m-d');
                                $isYesterday = $msgDate === date('Y-m-d', strtotime('-1 day'));
                                $dateLabel = $isToday ? 'Today' : ($isYesterday ? 'Yesterday' : date('F j, Y', strtotime($msgDate)));
                        ?>
                            <div class="admin-msg-date-pill"><span><?= e($dateLabel) ?></span></div>
                        <?php endif; ?>
                            <div class="admin-bubble <?= $mine ? 'mine' : 'theirs' ?>" data-msg-id="<?= (int) $m['id'] ?>">
                                <div class="admin-bubble-text"><?= nl2br(e((string) $m['body'])) ?></div>
                                <div class="admin-bubble-meta">
                                    <small><?= e(date('g:i a', strtotime((string) $m['created_at']))) ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <form method="post" action="<?= e(url('/admin/messages/send')) ?>" class="admin-msg-form" id="adminMsgForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="receiver_id" value="<?= $partnerId ?>">
                    <div class="admin-msg-input-wrap">
                        <textarea name="body"
                                  id="adminMsgInput"
                                  class="admin-msg-input"
                                  rows="1"
                                  placeholder="Type a message..."
                                  aria-label="Type a message"
                                  required></textarea>
                    </div>
                    <button type="submit" class="admin-msg-send-btn" id="adminMsgSendBtn">Send</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
(function () {
    var body = document.getElementById('adminMsgBody');
    if (body) {
        body.scrollTop = body.scrollHeight;
    }

    var form = document.getElementById('adminMsgForm');
    var input = document.getElementById('adminMsgInput');
    var sendBtn = document.getElementById('adminMsgSendBtn');

    if (!form || !input || !sendBtn || !body) return;

    var partnerId = parseInt(body.getAttribute('data-partner-id') || '0', 10);
    var pollUrl = body.getAttribute('data-poll-url');
    var lastId = parseInt(body.getAttribute('data-last-id') || '0', 10);

    function escapeHtml(str) {
        return (str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatTime(dateStr) {
        if (!dateStr) return '';
        var d = new Date(dateStr.replace(/-/g, '/'));
        if (isNaN(d.getTime())) return dateStr;
        var h = d.getHours();
        var m = d.getMinutes();
        var ampm = h >= 12 ? 'pm' : 'am';
        h = h % 12;
        if (h === 0) h = 12;
        return h + ':' + (m < 10 ? '0' + m : m) + ' ' + ampm;
    }

    function autoResize() {
        input.style.height = 'auto';
        var scrollH = input.scrollHeight;
        if (scrollH > 120) {
            input.style.height = '120px';
            input.style.overflowY = 'auto';
        } else if (scrollH > 42) {
            input.style.height = scrollH + 'px';
            input.style.overflowY = 'hidden';
        } else {
            input.style.height = '42px';
            input.style.overflowY = 'hidden';
        }
    }

    input.addEventListener('input', autoResize);

    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            if (input.value.trim() !== '') {
                submitMessage();
            }
        }
    });

    function appendMessageBubble(bodyText, timeText, isMine, msgId) {
        var empty = document.getElementById('adminEmptyThread');
        if (empty) { empty.remove(); }

        var bubble = document.createElement('div');
        bubble.className = 'admin-bubble ' + (isMine ? 'mine' : 'theirs');
        if (msgId) bubble.setAttribute('data-msg-id', String(msgId));

        var textDiv = document.createElement('div');
        textDiv.className = 'admin-bubble-text';
        textDiv.innerHTML = escapeHtml(bodyText).replace(/\n/g, '<br>');

        var metaDiv = document.createElement('div');
        metaDiv.className = 'admin-bubble-meta';
        var small = document.createElement('small');
        small.textContent = timeText;
        metaDiv.appendChild(small);

        bubble.appendChild(textDiv);
        bubble.appendChild(metaDiv);
        body.appendChild(bubble);

        body.scrollTop = body.scrollHeight;

        var preview = document.getElementById('convPreview-' + partnerId);
        if (preview) {
            var snippet = bodyText.length > 48 ? bodyText.substring(0, 48) + '…' : bodyText;
            preview.textContent = snippet;
        }
    }

    function submitMessage() {
        var text = input.value.trim();
        if (text === '') {
            input.focus();
            return;
        }

        var formData = new FormData(form);

        fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(function (r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        })
        .then(function (data) {
            if (data && data.success && data.message) {
                var m = data.message;
                lastId = Math.max(lastId, parseInt(m.id, 10));
                body.setAttribute('data-last-id', String(lastId));
                appendMessageBubble(m.body, m.time_formatted || formatTime(m.created_at), true, m.id);
                input.value = '';
                autoResize();
                input.focus();
            } else {
                form.submit();
            }
        })
        .catch(function () {
            form.submit();
        });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        submitMessage();
    });

    if (pollUrl && partnerId > 0) {
        setInterval(function () {
            fetch(pollUrl + '?since=' + lastId, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(function (r) {
                if (!r.ok) return null;
                return r.json();
            })
            .then(function (data) {
                if (!data || !data.messages || !data.messages.length) return;
                data.messages.forEach(function (m) {
                    var mId = parseInt(m.id, 10);
                    if (body.querySelector('[data-msg-id="' + mId + '"]')) {
                        lastId = Math.max(lastId, mId);
                        return;
                    }
                    lastId = Math.max(lastId, mId);
                    appendMessageBubble(m.body, m.time_formatted || formatTime(m.created_at), m.is_mine, m.id);
                });
                body.setAttribute('data-last-id', String(lastId));
            })
            .catch(function () {});
        }, 4000);
    }
})();
</script>
