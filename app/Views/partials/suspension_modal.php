<?php
/**
 * Account Suspension & Violation Warning Modal
 *
 * Displays a pop-up matching the community violation warning design when an
 * account has been suspended by an administrator. Allows the user to review the
 * reason and submit an appeal directly.
 *
 * @var array{id?: int, name?: string, email?: string, reason?: string, suspended_at?: string, has_pending_appeal?: bool}|null $suspendedUser
 */

if (empty($suspendedUser)) {
    return;
}

$userEmail = (string) ($suspendedUser['email'] ?? '');
$userName = (string) ($suspendedUser['name'] ?? '');
$reason = !empty($suspendedUser['reason'])
    ? (string) $suspendedUser['reason']
    : 'Violation of Community Guidelines';
$hasPending = !empty($suspendedUser['has_pending_appeal']);
?>

<div id="gmSuspensionOverlay" class="gm-suspension-overlay" role="dialog" aria-modal="true" aria-labelledby="gmSuspensionTitle">
    <div id="gmSuspensionCard" class="gm-suspension-card">
        <button type="button" class="gm-suspension-close" id="gmBtnCloseSuspension" aria-label="Close dialog">&times;</button>

        <!-- VIEW 1: Violation Warning Notice (Matching Mockup) -->
        <div id="gmSuspensionNoticeView">
            <div class="gm-suspension-icon-wrap">
                <svg width="68" height="60" viewBox="0 0 68 60" fill="none" xmlns="http://www.w3.org/2000/svg" class="gm-suspension-warn-icon" aria-hidden="true">
                    <!-- Red rounded triangle -->
                    <path d="M30.5359 4C32.0755 1.33333 35.9245 1.33333 37.4641 4L65.1769 52C66.7165 54.6667 64.792 58 61.7128 58H6.28718C3.20798 58 1.28348 54.6667 2.82308 52L30.5359 4Z" fill="#FF2B54"/>
                    <!-- Exclamation bar -->
                    <rect x="32" y="20" width="4" height="20" rx="2" fill="#FFFFFF"/>
                    <!-- Exclamation point -->
                    <circle cx="34" cy="47" r="2.5" fill="#FFFFFF"/>
                </svg>
            </div>

            <h2 id="gmSuspensionTitle" class="gm-suspension-title">Violation Warning</h2>
            
            <p class="gm-suspension-subtitle">
                Your account was suspended for violating our <strong>Community Guidelines</strong>.
            </p>

            <div class="gm-suspension-divider"></div>

            <div class="gm-suspension-body-section">
                <h3 class="gm-suspension-section-title">Your account is temporarily suspended</h3>
                <p class="gm-suspension-body-desc">
                    Due to multiple violations, your account has been temporarily suspended. You are currently unable to access GuideMate services or interact with other members.
                </p>
            </div>

            <div class="gm-suspension-reason-section">
                <h4 class="gm-suspension-reason-title">Reason for Removal</h4>
                <div class="gm-suspension-reason-box">
                    <p class="gm-suspension-reason-text"><?= e($reason) ?></p>
                </div>
            </div>

            <p class="gm-suspension-guidelines-link">
                Review our <a href="<?= e(url('/policy')) ?>" target="_blank" rel="noopener">Community Guidelines</a> to learn more.
            </p>

            <?php if ($hasPending): ?>
                <div class="gm-suspension-pending-notice">
                    <span class="gm-pending-badge">⏳ Appeal Under Review</span>
                    <p>We received your appeal and our moderation team is actively reviewing your account status.</p>
                </div>
                <button type="button" id="gmBtnAppealAgain" class="gm-btn-appeal-secondary">
                    Submit Additional Information
                </button>
            <?php else: ?>
                <button type="button" id="gmBtnAppeal" class="gm-btn-appeal">
                    Appeal
                </button>
            <?php endif; ?>

            <p class="gm-suspension-footer-hint">
                If you think we've made a mistake, you can submit an appeal.
            </p>
        </div>

        <!-- VIEW 2: Appeal Form -->
        <div id="gmSuspensionAppealView" style="display:none;">
            <div class="gm-appeal-header">
                <button type="button" id="gmBtnBackToNotice" class="gm-btn-back">
                    <span class="gm-arrow">←</span> Back
                </button>
                <h3 class="gm-appeal-title">Submit an Appeal</h3>
            </div>

            <div class="gm-appeal-context-box">
                <div class="gm-context-row">
                    <span class="gm-context-label">Account:</span>
                    <span class="gm-context-value"><?= e($userEmail) ?></span>
                </div>
                <div class="gm-context-row">
                    <span class="gm-context-label">Suspension Reason:</span>
                    <span class="gm-context-value"><?= e($reason) ?></span>
                </div>
            </div>

            <form id="gmAppealForm" method="post" action="<?= e(url('/appeal')) ?>" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="email" value="<?= e($userEmail) ?>">

                <div class="gm-field-row">
                    <label for="gmAppealMessage" class="gm-field-label">
                        Why should your suspension be reconsidered? <span class="gm-req">*</span>
                    </label>
                    <textarea
                        id="gmAppealMessage"
                        name="appeal_message"
                        rows="4"
                        required
                        class="gm-appeal-textarea"
                        placeholder="Please explain the situation or why you believe your suspension was in error. Provide any helpful context for the admin team..."
                    ></textarea>
                    <div id="gmAppealError" class="gm-field-error" style="display:none;"></div>
                </div>

                <div class="gm-appeal-actions">
                    <button type="submit" id="gmBtnSubmitAppeal" class="gm-btn-appeal">
                        <span id="gmBtnSubmitText">Submit Appeal</span>
                        <span id="gmBtnSubmitSpinner" style="display:none;" class="gm-spinner"></span>
                    </button>
                    <button type="button" id="gmBtnCancelAppeal" class="gm-btn-cancel">
                        Cancel
                    </button>
                </div>
            </form>
        </div>

        <!-- VIEW 3: Appeal Success Confirmation -->
        <div id="gmSuspensionSuccessView" style="display:none;">
            <div class="gm-success-icon-wrap">
                <svg width="64" height="64" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="32" cy="32" r="30" fill="#ecfdf5" stroke="#10b981" stroke-width="3"/>
                    <path d="M20 33L28 41L44 24" stroke="#10b981" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>

            <h3 class="gm-success-title">Appeal Submitted</h3>

            <p class="gm-success-desc">
                Your appeal has been received. Our administration team will review your account details and Community Guidelines compliance.
            </p>

            <button type="button" id="gmBtnDoneAppeal" class="gm-btn-done">
                Got it
            </button>
        </div>
    </div>
</div>

<style>
/* Scoped styles for the account suspension pop up dialog */
.gm-suspension-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    width: 100vw;
    height: 100vh;
    background-color: rgba(15, 23, 42, 0.72);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    z-index: 999999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
    box-sizing: border-box;
    opacity: 0;
    animation: gmFadeIn 0.22s ease-out forwards;
}

@keyframes gmFadeIn {
    to { opacity: 1; }
}

.gm-suspension-card {
    background: #ffffff;
    color: #111827;
    border-radius: 18px;
    max-width: 395px;
    width: 100%;
    padding: 26px 24px 22px;
    box-sizing: border-box;
    position: relative;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35), 0 0 0 1px rgba(0, 0, 0, 0.05);
    transform: scale(0.92);
    animation: gmScaleUp 0.25s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
}

@keyframes gmScaleUp {
    to { transform: scale(1); }
}

.gm-suspension-close {
    position: absolute;
    top: 10px;
    right: 12px;
    background: transparent;
    border: none;
    font-size: 26px;
    line-height: 1;
    color: #9ca3af;
    cursor: pointer;
    padding: 4px 8px;
    border-radius: 6px;
    transition: color 0.15s, background-color 0.15s;
    z-index: 2;
}

.gm-suspension-close:hover {
    color: #111827;
    background-color: #f3f4f6;
}

/* Warning View elements */
.gm-suspension-icon-wrap {
    text-align: center;
    margin: 4px 0 14px;
}

.gm-suspension-warn-icon {
    display: inline-block;
    filter: drop-shadow(0 3px 6px rgba(255, 43, 84, 0.3));
}

.gm-suspension-title {
    font-size: 22px;
    font-weight: 800;
    color: #111827;
    text-align: center;
    margin: 0 0 8px;
    letter-spacing: -0.02em;
    line-height: 1.25;
}

.gm-suspension-subtitle {
    font-size: 13.5px;
    line-height: 1.45;
    color: #374151;
    text-align: center;
    margin: 0 0 16px;
    padding: 0 8px;
}

.gm-suspension-subtitle strong {
    color: #111827;
    font-weight: 700;
}

.gm-suspension-divider {
    height: 1px;
    background: #e5e7eb;
    margin: 0 -24px 16px;
    width: calc(100% + 48px);
}

.gm-suspension-body-section {
    text-align: left;
    margin-bottom: 14px;
}

.gm-suspension-section-title {
    font-size: 15px;
    font-weight: 700;
    color: #111827;
    margin: 0 0 6px;
    line-height: 1.35;
}

.gm-suspension-body-desc {
    font-size: 13px;
    line-height: 1.5;
    color: #4b5563;
    margin: 0;
}

.gm-suspension-reason-section {
    text-align: left;
    margin-bottom: 14px;
}

.gm-suspension-reason-title {
    font-size: 13.5px;
    font-weight: 700;
    color: #111827;
    margin: 0 0 6px;
}

.gm-suspension-reason-box {
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 10px 12px;
}

.gm-suspension-reason-text {
    font-size: 13.5px;
    color: #111827;
    font-weight: 500;
    margin: 0;
    line-height: 1.4;
    word-break: break-word;
}

.gm-suspension-guidelines-link {
    font-size: 12.5px;
    color: #6b7280;
    text-align: left;
    margin: 0 0 18px;
}

.gm-suspension-guidelines-link a {
    color: #1f2937;
    font-weight: 700;
    text-decoration: underline;
    text-underline-offset: 2px;
}

.gm-suspension-guidelines-link a:hover {
    color: #FF2B54;
}

/* Primary Appeal button */
.gm-btn-appeal {
    display: block;
    width: 100%;
    background: #FF2B54;
    color: #ffffff !important;
    border: none;
    border-radius: 8px;
    padding: 13px 16px;
    font-size: 15.5px;
    font-weight: 700;
    cursor: pointer;
    transition: background 0.15s, transform 0.05s, box-shadow 0.15s;
    outline: none;
    box-shadow: 0 3px 8px rgba(255, 43, 84, 0.28);
    text-align: center;
    box-sizing: border-box;
}

.gm-btn-appeal:hover {
    background: #E81A43;
    box-shadow: 0 4px 12px rgba(255, 43, 84, 0.38);
}

.gm-btn-appeal:active {
    transform: scale(0.99);
}

.gm-btn-appeal:disabled {
    opacity: 0.65;
    cursor: not-allowed;
}

.gm-btn-appeal-secondary {
    display: block;
    width: 100%;
    background: #f3f4f6;
    color: #374151;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    padding: 11px 16px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.15s;
    text-align: center;
}

.gm-btn-appeal-secondary:hover {
    background: #e5e7eb;
}

.gm-suspension-pending-notice {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 8px;
    padding: 10px 12px;
    margin-bottom: 12px;
    text-align: left;
    font-size: 12.5px;
    color: #1e40af;
}

.gm-pending-badge {
    display: inline-block;
    font-weight: 700;
    color: #1d4ed8;
    margin-bottom: 4px;
}

.gm-suspension-pending-notice p {
    margin: 0;
    line-height: 1.4;
}

.gm-suspension-footer-hint {
    font-size: 12px;
    color: #6b7280;
    text-align: center;
    margin: 12px 0 2px;
}

/* Appeal Form View elements */
.gm-appeal-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 14px;
}

.gm-btn-back {
    background: none;
    border: none;
    color: #4b5563;
    cursor: pointer;
    font-size: 13.5px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 6px;
    border-radius: 4px;
    transition: color 0.15s, background-color 0.15s;
}

.gm-btn-back:hover {
    color: #111827;
    background-color: #f3f4f6;
}

.gm-appeal-title {
    font-size: 18px;
    font-weight: 800;
    color: #111827;
    margin: 0;
    flex: 1;
}

.gm-appeal-context-box {
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 10px 12px;
    margin-bottom: 14px;
    font-size: 12.5px;
    text-align: left;
}

.gm-context-row {
    margin-bottom: 4px;
    line-height: 1.4;
}

.gm-context-row:last-child {
    margin-bottom: 0;
}

.gm-context-label {
    color: #6b7280;
    font-weight: 600;
    margin-right: 4px;
}

.gm-context-value {
    color: #111827;
    font-weight: 600;
    word-break: break-word;
}

.gm-field-row {
    text-align: left;
    margin-bottom: 14px;
}

.gm-field-label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: #111827;
    margin-bottom: 6px;
}

.gm-req {
    color: #ef4444;
}

.gm-appeal-textarea {
    width: 100%;
    box-sizing: border-box;
    padding: 10px 12px;
    font-size: 13px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    resize: vertical;
    min-height: 100px;
    font-family: inherit;
    line-height: 1.45;
    outline: none;
    transition: border-color 0.15s, box-shadow 0.15s;
    background: #ffffff;
    color: #111827;
}

.gm-appeal-textarea:focus {
    border-color: #FF2B54;
    box-shadow: 0 0 0 3px rgba(255, 43, 84, 0.15);
}

.gm-field-error {
    color: #ef4444;
    font-size: 12px;
    margin-top: 5px;
    font-weight: 500;
}

.gm-appeal-actions {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.gm-btn-cancel {
    background: transparent;
    color: #6b7280;
    border: none;
    padding: 9px 14px;
    font-size: 13.5px;
    font-weight: 600;
    cursor: pointer;
    border-radius: 6px;
    transition: color 0.15s;
}

.gm-btn-cancel:hover {
    color: #111827;
}

/* Spinner */
.gm-spinner {
    display: inline-block;
    width: 14px;
    height: 14px;
    border: 2px solid rgba(255, 255, 255, 0.35);
    border-radius: 50%;
    border-top-color: #ffffff;
    animation: gmSpin 0.7s linear infinite;
    vertical-align: middle;
}

@keyframes gmSpin {
    to { transform: rotate(360deg); }
}

/* Success View elements */
.gm-success-icon-wrap {
    text-align: center;
    margin: 8px 0 16px;
}

.gm-success-title {
    font-size: 20px;
    font-weight: 800;
    color: #111827;
    text-align: center;
    margin: 0 0 8px;
}

.gm-success-desc {
    font-size: 13.5px;
    line-height: 1.5;
    color: #4b5563;
    text-align: center;
    margin: 0 0 20px;
    padding: 0 8px;
}

.gm-btn-done {
    width: 100%;
    background: #10b981;
    color: #ffffff;
    border: none;
    border-radius: 8px;
    padding: 12px 16px;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    transition: background 0.15s;
}

.gm-btn-done:hover {
    background: #059669;
}
</style>

<script>
(function() {
    var overlay = document.getElementById('gmSuspensionOverlay');
    if (!overlay) return;

    var btnClose = document.getElementById('gmBtnCloseSuspension');
    var noticeView = document.getElementById('gmSuspensionNoticeView');
    var appealView = document.getElementById('gmSuspensionAppealView');
    var successView = document.getElementById('gmSuspensionSuccessView');

    var btnAppeal = document.getElementById('gmBtnAppeal');
    var btnAppealAgain = document.getElementById('gmBtnAppealAgain');
    var btnBack = document.getElementById('gmBtnBackToNotice');
    var btnCancel = document.getElementById('gmBtnCancelAppeal');
    var btnDone = document.getElementById('gmBtnDoneAppeal');

    var form = document.getElementById('gmAppealForm');
    var textarea = document.getElementById('gmAppealMessage');
    var errBox = document.getElementById('gmAppealError');
    var btnSubmit = document.getElementById('gmBtnSubmitAppeal');
    var submitText = document.getElementById('gmBtnSubmitText');
    var submitSpinner = document.getElementById('gmBtnSubmitSpinner');

    function closeOverlay() {
        overlay.style.opacity = '0';
        overlay.style.transition = 'opacity 0.18s ease-out';
        setTimeout(function() {
            if (overlay.parentNode) {
                overlay.parentNode.removeChild(overlay);
            }
        }, 180);
    }

    function showNotice() {
        if (noticeView) noticeView.style.display = 'block';
        if (appealView) appealView.style.display = 'none';
        if (successView) successView.style.display = 'none';
    }

    function showAppeal() {
        if (noticeView) noticeView.style.display = 'none';
        if (appealView) appealView.style.display = 'block';
        if (successView) successView.style.display = 'none';
        if (textarea) {
            setTimeout(function() { textarea.focus(); }, 100);
        }
    }

    function showSuccess() {
        if (noticeView) noticeView.style.display = 'none';
        if (appealView) appealView.style.display = 'none';
        if (successView) successView.style.display = 'block';
    }

    if (btnClose) btnClose.addEventListener('click', closeOverlay);
    if (btnDone) btnDone.addEventListener('click', closeOverlay);
    if (btnCancel) btnCancel.addEventListener('click', showNotice);
    if (btnBack) btnBack.addEventListener('click', showNotice);

    if (btnAppeal) btnAppeal.addEventListener('click', showAppeal);
    if (btnAppealAgain) btnAppealAgain.addEventListener('click', showAppeal);

    // Dismiss on backdrop click
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) {
            closeOverlay();
        }
    });

    // Dismiss on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeOverlay();
        }
    });

    // Handle AJAX form submission
    if (form) {
        form.addEventListener('submit', function(e) {
            var msg = textarea ? textarea.value.trim() : '';
            if (msg.length < 5) {
                e.preventDefault();
                if (errBox) {
                    errBox.textContent = 'Please enter at least 5 characters explaining your appeal.';
                    errBox.style.display = 'block';
                }
                if (textarea) textarea.focus();
                return;
            }

            if (errBox) {
                errBox.style.display = 'none';
            }

            // If fetch is supported, handle smoothly via AJAX
            if (window.fetch && window.FormData) {
                e.preventDefault();
                if (btnSubmit) {
                    btnSubmit.disabled = true;
                    if (submitText) submitText.style.display = 'none';
                    if (submitSpinner) submitSpinner.style.display = 'inline-block';
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
                .then(function(res) {
                    return res.json().then(function(data) {
                        return { status: res.status, data: data };
                    });
                })
                .then(function(result) {
                    if (btnSubmit) {
                        btnSubmit.disabled = false;
                        if (submitText) submitText.style.display = 'inline';
                        if (submitSpinner) submitSpinner.style.display = 'none';
                    }

                    if (result.status >= 200 && result.status < 300 && result.data && result.data.success) {
                        showSuccess();
                    } else {
                        var errMsg = (result.data && result.data.error) ? result.data.error : 'Failed to submit appeal. Please try again.';
                        if (errBox) {
                            errBox.textContent = errMsg;
                            errBox.style.display = 'block';
                        }
                    }
                })
                .catch(function(err) {
                    if (btnSubmit) {
                        btnSubmit.disabled = false;
                        if (submitText) submitText.style.display = 'inline';
                        if (submitSpinner) submitSpinner.style.display = 'none';
                    }
                    if (errBox) {
                        errBox.textContent = 'A network error occurred. Please try again.';
                        errBox.style.display = 'block';
                    }
                });
            }
        });
    }
})();
</script>
