<?php
/**
 * @var array<int,array<string,mixed>> $guides
 * @var array<int,array<int,array<string,mixed>>> $documents
 */

use App\Models\GuideDocument;
use App\Models\User;

$statusPill = [
    'pending' => 'pill-pending',
    'approved' => 'pill-approved',
    'rejected' => 'pill-cancelled',
    'none' => 'pill-confirmed',
];
?>
<div class="admin-page-header" style="margin-bottom:1.5rem;">
    <h1 style="color:#fff;font-size:1.85rem;font-weight:800;letter-spacing:-0.02em;margin-bottom:0.35rem;">Partner Applications</h1>
    <p class="hint mb-0" style="color:rgba(255,255,255,.55);font-size:0.95rem;">Pending tour guides, rental partners and hotel partners awaiting verification.</p>
</div>

<?php if ($guides === []): ?>
    <div class="panel">
        <div class="empty-state" style="padding:2.5rem 1rem;">
            <p>No partner applications yet.</p>
        </div>
    </div>
<?php else: ?>
    <?php foreach ($guides as $g): ?>
        <?php
        $status = (string) ($g['guide_status'] ?? 'none');
        $docs = $documents[(int) $g['id']] ?? [];
        $initial = strtoupper(substr((string) ($g['name'] ?? 'P'), 0, 1));
        ?>
        <div class="panel" style="margin-bottom:1.75rem;">
            <div class="panel-head" style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
                <div style="display:flex;align-items:center;gap:0.85rem;min-width:0;">
                    <div class="partner-app-avatar">
                        <?= e($initial) ?>
                    </div>
                    <div style="min-width:0;">
                        <h3 style="margin:0 0 0.25rem 0;display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;color:#fff;font-size:1.15rem;font-weight:700;">
                            <?= e($g['name']) ?>
                            <span class="pill pill-confirmed" style="background:rgba(45, 212, 191, 0.15);color:#2dd4bf;border:1px solid rgba(45, 212, 191, 0.3);font-size:0.72rem;padding:0.18rem 0.6rem;">
                                <?= e(User::PROVIDER_LABELS[$g['role']] ?? ucfirst((string) $g['role'])) ?>
                            </span>
                            <span class="pill <?= $statusPill[$status] ?? 'pill-pending' ?>" style="font-size:0.72rem;padding:0.18rem 0.6rem;">
                                <?= e(ucfirst($status)) ?>
                            </span>
                        </h3>
                        <p class="hint mb-0" style="color:rgba(255,255,255,0.55);font-size:0.85rem;">
                            <?= e($g['email']) ?> · Joined <?= e(date('M j, Y', strtotime($g['created_at']))) ?>
                        </p>
                    </div>
                </div>
                <div>
                    <a href="<?= e(url('/admin/users/' . $g['id'] . '/documents')) ?>" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:0.45rem;border-color:rgba(255,255,255,0.18);color:#fff;">
                        Documents
                        <?php if (count($docs) > 0): ?>
                            <span class="badge" style="background:var(--teal-600, #0d9488);color:#fff;padding:0.12rem 0.45rem;border-radius:999px;font-size:0.7rem;font-weight:700;">
                                <?= count($docs) ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </div>
            </div>

            <div class="panel-body">
                <div class="partner-docs-section">
                    <div class="partner-docs-header">
                        <h4 class="partner-docs-title">
                            Submitted Documents
                            <?php if ($docs !== []): ?>
                                <span class="partner-docs-badge"><?= count($docs) ?></span>
                            <?php endif; ?>
                        </h4>
                    </div>

                    <?php if ($docs === []): ?>
                        <div class="partner-docs-empty">
                            <div class="partner-docs-empty-icon">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                    <polyline points="14 2 14 8 20 8"></polyline>
                                    <line x1="9" y1="15" x2="15" y2="15"></line>
                                </svg>
                            </div>
                            <div class="partner-docs-empty-text">
                                <strong>No documents on file</strong>
                                <span>This applicant has not uploaded verification credentials yet.</span>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="partner-docs-grid">
                            <?php foreach ($docs as $d): ?>
                                <?php
                                $docId = (int) $d['id'];
                                $hasFile = !empty($d['file_path']);
                                $docUrl = $hasFile ? url($d['file_path']) : url('/documents/' . $docId);
                                $downloadUrl = url('/documents/download/' . $docId);
                                $docLabel = !empty($d['label']) ? $d['label'] : ($hasFile ? basename($d['file_path']) : 'Document #' . $docId);
                                $typeLabel = GuideDocument::typeLabel($d['doc_type']);
                                $ext = strtolower(pathinfo($d['file_path'] ?? $docLabel, PATHINFO_EXTENSION));
                                if ($ext === '') $ext = 'doc';
                                $isPdf = $ext === 'pdf';
                                $isImg = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg']);

                                $publicBase = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public';
                                $rel = ltrim(str_replace('/', DIRECTORY_SEPARATOR, (string) ($d['file_path'] ?? '')), DIRECTORY_SEPARATOR);
                                $absPath = $publicBase . DIRECTORY_SEPARATOR . $rel;
                                $fileSize = ($hasFile && is_file($absPath)) ? round(filesize($absPath) / 1024, 1) . ' KB' : null;
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
                                            <h5 class="partner-doc-title" title="<?= e($docLabel) ?>"><?= e($docLabel) ?></h5>
                                            <div class="partner-doc-meta">
                                                <span>Uploaded <?= e(date('M j, Y', strtotime($d['created_at']))) ?></span>
                                                <?php if ($fileSize): ?>
                                                    <span class="partner-doc-dot">·</span>
                                                    <span><?= e($fileSize) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="partner-doc-card-actions">
                                        <a href="<?= e($docUrl) ?>" target="_blank" rel="noopener" class="partner-doc-btn preview-btn"
                                           data-doc-preview="1"
                                           data-doc-id="<?= $docId ?>"
                                           data-doc-url="<?= e($docUrl) ?>"
                                           data-doc-label="<?= e($docLabel) ?>"
                                           data-doc-type="<?= e($typeLabel) ?>"
                                           data-doc-status="<?= e($status) ?>"
                                           data-doc-owner="<?= e($g['name'] ?? '') ?>">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                            Preview
                                        </a>
                                        <a href="<?= e($downloadUrl) ?>" class="partner-doc-btn" download title="Download file">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                                            Download
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($status === 'rejected' && !empty($g['guide_review_note'])): ?>
                    <div class="note-box" style="background:rgba(239,68,68,0.1);border-left:3px solid #ef4444;border-radius:8px;padding:0.75rem 1rem;color:#fca5a5;margin:1rem 0;">
                        <strong style="color:#ffffff;">Rejection note:</strong> <?= e($g['guide_review_note']) ?>
                    </div>
                <?php endif; ?>

                <div class="guide-actions" style="display:flex;align-items:center;gap:0.75rem;margin-top:1.25rem;padding-top:1.15rem;border-top:1px solid rgba(255,255,255,0.08);flex-wrap:wrap;">
                    <?php if ($status !== 'approved'): ?>
                        <form method="post" action="<?= e(url('/admin/guides/' . $g['id'] . '/approve')) ?>" style="margin:0;">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-primary btn-sm" style="display:inline-flex;align-items:center;gap:0.35rem;font-weight:700;padding:0.55rem 1.25rem;background:#1faa4e;border-color:#1faa4e;">
                                ✓ Approve
                            </button>
                        </form>
                    <?php endif; ?>
                    <form method="post" action="<?= e(url('/admin/guides/' . $g['id'] . '/reject')) ?>" class="reject-form" style="display:flex;align-items:center;gap:0.6rem;flex:1 1 320px;margin:0;">
                        <?= csrf_field() ?>
                        <input class="input" type="text" name="note" placeholder="Reason (optional, shown to the applicant)" style="flex:1 1 auto;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.18);color:#fff;border-radius:8px;padding:0.55rem 0.95rem;">
                        <button type="submit" class="btn btn-ghost btn-sm" style="border:1px solid rgba(239,68,68,0.4);color:#f87171;font-weight:600;padding:0.55rem 1.15rem;border-radius:8px;">Reject</button>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
