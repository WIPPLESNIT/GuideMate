<?php
/**
 * Account Verification Gating Modal
 *
 * Prevents unverified or newly registered providers (guides, hotel partners, rental partners)
 * from creating listings until an administrator has approved their account.
 *
 * @var array<string,mixed>|null $user
 */
$currentUser = $user ?? auth_user();
if ($currentUser === null) {
    return;
}

$userRole = (string) ($currentUser['role'] ?? '');
if (!\App\Models\User::isProviderRole($userRole)) {
    return;
}

$userStatus = (string) ($currentUser['guide_status'] ?? 'none');
$isVerified = $userStatus === 'approved';
$adminNote = (string) ($currentUser['guide_review_note'] ?? '');
$verificationUrl = url('/documents');
?>
<div class="gm-modal" id="verificationGatingModal"
     data-user-verified="<?= $isVerified ? '1' : '0' ?>"
     data-user-status="<?= e($userStatus) ?>"
     data-status-endpoint="<?= e(url('/dashboard/verification/status')) ?>"
     data-verification-url="<?= e($verificationUrl) ?>"
     hidden aria-hidden="true">
    <div class="gm-modal-backdrop" data-close-verif-modal></div>
    <div class="gm-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="verifGatingTitle" aria-describedby="verifGatingDesc" style="max-width:540px;">
        <div class="gm-modal-head" style="align-items:flex-start;">
            <div style="display:flex;align-items:center;gap:0.75rem;">
                <div class="verif-modal-icon-badge" style="width:42px;height:42px;border-radius:10px;background:rgba(245,158,11,0.12);color:#d97706;display:flex;align-items:center;justify-content:center;font-size:1.35rem;flex-shrink:0;">
                    🛡️
                </div>
                <div>
                    <h2 id="verifGatingTitle" style="margin:0;font-size:1.25rem;font-weight:800;color:var(--text, #0f172a);">Account Verification Required</h2>
                    <p style="margin:0.15rem 0 0;font-size:0.85rem;color:var(--text-muted, #64748b);">Administrator review in progress</p>
                </div>
            </div>
            <button type="button" class="gm-modal-close" data-close-verif-modal aria-label="Close dialog">&times;</button>
        </div>

        <div class="gm-modal-body" id="verifGatingModalBody">
            <!-- Loading State -->
            <div id="verifLoadingState" style="display:none;padding:2.2rem 1rem;text-align:center;">
                <div class="verif-spinner" style="margin:0 auto 1rem;width:38px;height:38px;border:3px solid rgba(245,158,11,0.2);border-top-color:#d97706;border-radius:50%;animation:gmSpin 0.75s linear infinite;"></div>
                <p style="margin:0;font-weight:700;color:var(--text, #0f172a);font-size:1rem;">Checking account verification status...</p>
                <p class="hint" style="margin:0.35rem 0 0;font-size:0.85rem;">Please wait a moment while we check your verification with the server.</p>
            </div>

            <!-- Error State -->
            <div id="verifErrorState" style="display:none;padding:0.75rem 0;">
                <div class="status-banner banner-red" style="margin-bottom:1rem;">
                    <span class="status-icon">⚠️</span>
                    <div>
                        <strong>Unable to Check Verification Status</strong>
                        <p class="mb-0" id="verifErrorMessage">A network or server error occurred. Please verify your connection or visit your verification page directly.</p>
                    </div>
                </div>
                <div style="display:flex;gap:0.5rem;justify-content:flex-end;">
                    <button type="button" class="btn btn-ghost btn-sm" id="verifRetryBtn">Try Again</button>
                    <a href="<?= e($verificationUrl) ?>" class="btn btn-primary btn-sm">Go to Documents</a>
                </div>
            </div>

            <!-- Already Verified State -->
            <div id="verifApprovedState" style="display:none;padding:1.5rem 0.5rem;text-align:center;">
                <div style="width:58px;height:58px;border-radius:50%;background:#dcfce7;color:#15803d;display:flex;align-items:center;justify-content:center;font-size:1.8rem;margin:0 auto 1rem;">
                    ✓
                </div>
                <h3 style="margin:0 0 0.5rem;font-size:1.25rem;font-weight:800;color:#15803d;">Your Account is Verified!</h3>
                <p style="margin:0 0 1.25rem;color:var(--text-muted, #475569);font-size:0.95rem;line-height:1.5;">
                    Great news! Your partner account has been verified by an administrator. You now have full access to create and publish listings.
                </p>
                <div style="display:flex;gap:0.75rem;justify-content:center;">
                    <button type="button" class="btn btn-primary" id="verifProceedBtn">Continue to New Listing →</button>
                </div>
            </div>

            <!-- Default / Unverified Gating Content -->
            <div id="verifDefaultState">
                <!-- Status Banner inside Modal -->
                <div class="status-banner <?= $userStatus === 'rejected' ? 'banner-red' : 'banner-amber' ?>" id="verifStatusBanner" style="margin-bottom:1.1rem;">
                    <span class="status-icon" id="verifStatusIcon"><?= $userStatus === 'rejected' ? '⚠️' : '⏳' ?></span>
                    <div>
                        <strong id="verifStatusTitle">
                            <?= $userStatus === 'rejected' ? 'Application Needs Update' : ($userStatus === 'pending' ? 'Verification in Progress' : 'Verification Required') ?>
                        </strong>
                        <p class="mb-0" id="verifStatusText">
                            <?php if ($userStatus === 'rejected'): ?>
                                <?= $adminNote !== '' ? 'Admin note: ' . e($adminNote) : 'Your submitted documents were not approved. Please review and update your documents.' ?>
                            <?php elseif ($userStatus === 'pending'): ?>
                                An administrator is currently reviewing your account information and documents.
                            <?php else: ?>
                                Please submit your valid ID and business credentials for administrator review.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <div id="verifGatingDesc">
                    <h4 style="margin:0 0 0.4rem;font-size:0.95rem;font-weight:700;color:var(--text, #0f172a);">Why is account verification required?</h4>
                    <p style="margin:0 0 1rem;font-size:0.9rem;line-height:1.55;color:var(--text-muted, #475569);">
                        To ensure traveler safety, prevent unauthorized or fraudulent listings, and uphold high service standards across GuideMate, all partner accounts must be verified by an administrator before creating listings or accepting bookings.
                    </p>

                    <h4 style="margin:0 0 0.4rem;font-size:0.95rem;font-weight:700;color:var(--text, #0f172a);">What should you do now?</h4>
                    <p style="margin:0 0 0.5rem;font-size:0.9rem;line-height:1.55;color:var(--text-muted, #475569);">
                        Click <strong>Verify Account</strong> below to view your verification status or edit and update your submitted verification documents. Once an administrator approves your account, listing creation will be unlocked automatically.
                    </p>
                </div>

                <div class="gm-modal-foot" style="margin-top:1.4rem;padding-top:1rem;border-top:1px solid var(--line, #e2e8f0);display:flex;justify-content:flex-end;gap:0.75rem;">
                    <button type="button" class="btn btn-ghost" data-close-verif-modal id="verifCancelBtn">Cancel</button>
                    <a href="<?= e($verificationUrl) ?>" class="btn btn-primary" id="verifActionBtn">
                        Verify Account
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@keyframes gmSpin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
.verif-modal-icon-badge {
    box-shadow: 0 2px 6px rgba(245,158,11,0.15);
}
@media (max-width: 560px) {
    #verificationGatingModal .gm-modal-dialog {
        border-radius: 14px;
        margin: 1rem;
        max-height: 90vh;
    }
}
</style>
