<?php
/**
 * @var array<string,mixed> $targetUser
 * @var array<int,array<string,mixed>> $documents
 */
use App\Models\GuideDocument;
use App\Models\User;

$statusPill = [
    'pending' => 'pill-pending',
    'approved' => 'pill-approved',
    'rejected' => 'pill-cancelled',
    'none' => 'pill-confirmed',
];
$status = (string) ($targetUser['guide_status'] ?? 'none');
?>
<div style="margin-bottom:1.5rem;">
    <a href="<?= e(url('/admin/guides')) ?>" class="btn btn-ghost btn-sm" style="margin-bottom:0.75rem;">
        ← Back to Partner Applications
    </a>
    <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
        <div>
            <h1 style="margin:0 0 0.3rem 0;color:#fff;"><?= e($targetUser['name']) ?> · Documents</h1>
            <p class="hint mb-0" style="color:rgba(255,255,255,0.55);">
                <?= e($targetUser['email']) ?> · Role: <strong><?= e(User::PROVIDER_LABELS[$targetUser['role']] ?? ucfirst((string) $targetUser['role'])) ?></strong>
                · Status: <span class="pill <?= $statusPill[$status] ?? 'pill-pending' ?>"><?= e(ucfirst($status)) ?></span>
            </p>
        </div>
        <div style="display:flex;gap:0.5rem;">
            <a href="<?= e(url('/admin/users/' . $targetUser['id'] . '/edit')) ?>" class="btn btn-ghost">
                Manage User Verification
            </a>
        </div>
    </div>
</div>

<div class="panel">
    <div class="panel-head" style="display:flex;align-items:center;justify-content:space-between;">
        <h3 style="margin:0;color:#fff;">Submitted Verification Documents (<?= count($documents) ?>)</h3>
    </div>
    <div class="panel-body">
        <?php if ($documents === []): ?>
            <div class="partner-docs-empty" style="padding:2rem 1.5rem;justify-content:center;">
                <div class="partner-docs-empty-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="9" y1="15" x2="15" y2="15"></line></svg>
                </div>
                <div class="partner-docs-empty-text">
                    <strong>No documents on file</strong>
                    <span>No verification documents submitted on file for this user.</span>
                </div>
            </div>
        <?php else: ?>
            <div class="partner-docs-grid">
                <?php foreach ($documents as $d):
                    $docId = (int) $d['id'];
                    $hasFile = !empty($d['file_path']);
                    $fileExists = !empty($d['file_exists']);
                    $fileUrl = $hasFile ? url($d['file_path']) : url('/documents/' . $docId);
                    $downloadUrl = url('/documents/download/' . $docId);
                    $label = !empty($d['label']) ? $d['label'] : ($hasFile ? basename($d['file_path']) : 'Document #' . $docId);
                    $typeLabel = GuideDocument::typeLabel($d['doc_type']);
                    $ext = strtolower(pathinfo($d['file_path'] ?? $label, PATHINFO_EXTENSION));
                    if ($ext === '') $ext = 'doc';
                    $isPdf = $ext === 'pdf';
                    $isImg = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg']);
                    $sizeFormatted = ($fileExists && !empty($d['file_size'])) ? round($d['file_size'] / 1024, 1) . ' KB' : null;
                ?>
                    <div class="partner-doc-card">
                        <div class="partner-doc-card-top">
                            <span class="partner-doc-type-badge"><?= e($typeLabel) ?></span>
                            <span class="partner-doc-ext-badge"><?= e(strtoupper($ext)) ?></span>
                        </div>

                        <div class="partner-doc-card-body">
                            <div class="partner-doc-icon-wrap <?= $isPdf ? 'is-pdf' : ($isImg ? 'is-img' : '') ?>">
                                <?php if ($isPdf): ?>
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><path d="M9 13h6"></path><path d="M9 17h3"></path></svg>
                                <?php elseif ($isImg): ?>
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                                <?php else: ?>
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                                <?php endif; ?>
                            </div>
                            <div class="partner-doc-info">
                                <h5 class="partner-doc-title" title="<?= e($label) ?>"><?= e($label) ?></h5>
                                <div class="partner-doc-meta">
                                    <span>Uploaded <?= e(date('M j, Y', strtotime($d['created_at']))) ?></span>
                                    <?php if ($sizeFormatted): ?>
                                        <span class="partner-doc-dot">·</span>
                                        <span><?= e($sizeFormatted) ?></span>
                                    <?php elseif (!$fileExists): ?>
                                        <span class="partner-doc-dot">·</span>
                                        <span style="color:#ef4444;font-weight:600;">File missing</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="partner-doc-card-actions">
                            <?php if ($fileExists): ?>
                                <a href="<?= e($fileUrl) ?>" target="_blank" rel="noopener" class="partner-doc-btn preview-btn"
                                   data-doc-preview="1"
                                   data-doc-id="<?= $docId ?>"
                                   data-doc-url="<?= e($fileUrl) ?>"
                                   data-doc-label="<?= e($label) ?>"
                                   data-doc-type="<?= e($typeLabel) ?>"
                                   data-doc-status="<?= e($status) ?>"
                                   data-doc-owner="<?= e($targetUser['name'] ?? '') ?>">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                    Preview
                                </a>
                                <a href="<?= e($downloadUrl) ?>" class="partner-doc-btn" download title="Download file">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                                    Download
                                </a>
                            <?php else: ?>
                                <span class="pill pill-cancelled" style="font-size:0.75rem;">File Missing</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
