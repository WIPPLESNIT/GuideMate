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
    <a href="<?= e(url('/admin/users/' . $targetUser['id'] . '/edit')) ?>" class="btn btn-ghost btn-sm" style="margin-bottom:0.75rem;">
        ← Back to User Account
    </a>
    <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
        <div>
            <h1 style="margin:0 0 0.3rem 0;"><?= e($targetUser['name']) ?> · Documents</h1>
            <p class="hint mb-0">
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
        <h3 style="margin:0;">Submitted Verification Documents (<?= count($documents) ?>)</h3>
    </div>
    <div class="panel-body">
        <?php if ($documents === []): ?>
            <div class="empty-state" style="padding:2.5rem 1rem;text-align:center;">
                <p>No documents on file for this user.</p>
            </div>
        <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:0.75rem;">
                <?php foreach ($documents as $d):
                    $docId = (int) $d['id'];
                    $hasFile = !empty($d['file_path']);
                    $fileExists = !empty($d['file_exists']);
                    $fileUrl = $hasFile ? url($d['file_path']) : url('/documents/' . $docId);
                    $downloadUrl = url('/documents/download/' . $docId);
                    $label = !empty($d['label']) ? $d['label'] : ($hasFile ? basename($d['file_path']) : 'Document #' . $docId);
                    $ext = !empty($d['ext']) ? strtoupper($d['ext']) : 'FILE';
                ?>
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:0.9rem 1.1rem;background:#13262f;border:1px solid rgba(255,255,255,0.08);border-radius:10px;flex-wrap:wrap;">
                        <div style="display:flex;align-items:center;gap:0.85rem;min-width:0;flex:1;">
                            <div style="width:40px;height:40px;border-radius:8px;background:rgba(255,255,255,0.08);display:grid;place-items:center;font-weight:800;font-size:0.75rem;color:#0b7843;flex-shrink:0;">
                                <?= e($ext) ?>
                            </div>
                            <div style="min-width:0;">
                                <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;">
                                    <span style="font-weight:700;color:#fff;word-break:break-all;">
                                        <?= e($label) ?>
                                    </span>
                                    <span class="pill pill-pending" style="font-size:0.72rem;padding:0.15rem 0.5rem;">
                                        <?= e(GuideDocument::typeLabel($d['doc_type'])) ?>
                                    </span>
                                </div>
                                <div style="font-size:0.8rem;color:rgba(255,255,255,0.55);margin-top:0.15rem;">
                                    Uploaded <?= e(date('M j, Y g:i a', strtotime($d['created_at']))) ?>
                                    <?php if ($fileExists && !empty($d['file_size'])): ?>
                                        · <?= e(round($d['file_size'] / 1024, 1)) ?> KB
                                    <?php elseif (!$fileExists): ?>
                                        · <span style="color:#ef4444;font-weight:600;">File missing from disk</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div style="display:flex;align-items:center;gap:0.4rem;flex-shrink:0;">
                            <?php if ($fileExists): ?>
                                <a href="<?= e($fileUrl) ?>" target="_blank" rel="noopener" class="btn btn-ghost btn-sm"
                                   data-doc-preview="1"
                                   data-doc-id="<?= (int) $d['id'] ?>"
                                   data-doc-url="<?= e($fileUrl) ?>"
                                   data-doc-label="<?= e($label) ?>"
                                   data-doc-type="<?= e(GuideDocument::typeLabel($d['doc_type'])) ?>"
                                   data-doc-status="<?= e($status) ?>"
                                   data-doc-owner="<?= e($targetUser['name'] ?? '') ?>">
                                    Preview ↗
                                </a>
                                <a href="<?= e($downloadUrl) ?>" class="btn btn-ghost btn-sm">
                                    Download
                                </a>
                            <?php else: ?>
                                <span class="pill pill-cancelled" style="font-size:0.75rem;">Missing</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
