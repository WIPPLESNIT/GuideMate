<?php
/**
 * Standalone Document Viewer & Preview Page
 * 
 * @var array<string,mixed> $doc
 * @var ?array<string,mixed> $owner
 * @var bool $fileExists
 * @var int $fileSize
 * @var string $relPath
 * @var string $mime
 * @var bool $isImage
 * @var bool $isPdf
 * @var string $docLabel
 * @var string $typeLabel
 * @var string $status
 * @var string $statusLabel
 * @var string $statusClass
 */
use App\Core\AdminAuth;
use App\Core\Auth;

$ext = strtolower(pathinfo($relPath, PATHINFO_EXTENSION));
$fileUrl = url('/' . $relPath);
$streamUrl = url('/documents/file/' . (int) $doc['id']);
$downloadUrl = url('/documents/download/' . (int) $doc['id']);
$isAdmin = AdminAuth::check() || Auth::isAdmin();
$backUrl = $isAdmin ? url('/admin/users/' . (int) $doc['user_id'] . '/documents') : url('/documents');
$backLabel = $isAdmin ? '← Back to User Documents' : '← Back to Documents';
?>
<div class="container" style="max-width:980px;margin:2rem auto;padding:0 1rem;">
    <div style="margin-bottom:1.25rem;">
        <a href="<?= e($backUrl) ?>" class="btn btn-ghost btn-sm" style="margin-bottom:0.75rem;">
            <?= e($backLabel) ?>
        </a>
        <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
            <div>
                <h1 style="margin:0 0 0.35rem 0;font-size:1.75rem;"><?= e($docLabel) ?></h1>
                <div style="display:flex;align-items:center;gap:0.6rem;flex-wrap:wrap;color:var(--ink-400);font-size:0.9rem;">
                    <span class="pill pill-pending"><?= e($typeLabel) ?></span>
                    <span class="pill <?= e($statusClass) ?>"><?= e($statusLabel) ?></span>
                    <span>Uploaded <?= e(date('F j, Y g:i A', strtotime($doc['created_at']))) ?></span>
                    <?php if ($owner !== null): ?>
                        <span>· Owner: <strong><?= e($owner['name']) ?></strong></span>
                    <?php endif; ?>
                </div>
            </div>
            <div style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;">
                <?php if ($fileExists): ?>
                    <a href="<?= e($streamUrl) ?>" target="_blank" rel="noopener" class="btn btn-ghost btn-sm">
                        Open Original ↗
                    </a>
                    <a href="<?= e($downloadUrl) ?>" class="btn btn-primary btn-sm">
                        Download File
                    </a>
                <?php else: ?>
                    <a href="<?= e(url('/dashboard/verification')) ?>" class="btn btn-primary btn-sm">
                        Re-upload Document
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (!$fileExists): ?>
        <div class="status-banner banner-red" style="margin-bottom:1.5rem;">
            <span class="status-icon">⚠️</span>
            <div>
                <strong>Document File Missing From Server</strong>
                <p class="mb-0">
                    The document record is active in the database, but the physical file is not found on disk.
                    Please visit the <a href="<?= e(url('/dashboard/verification')) ?>">verification page</a> to upload an updated copy of this document.
                </p>
            </div>
        </div>
    <?php elseif ($status === 'pending'): ?>
        <div class="status-banner banner-amber" style="margin-bottom:1.5rem;">
            <span class="status-icon">⏳</span>
            <div>
                <strong>Application Under Review</strong>
                <p class="mb-0">This document is currently pending administrator verification. Authorized staff can preview and review the file content below.</p>
            </div>
        </div>
    <?php endif; ?>

    <div class="panel" style="overflow:hidden;border-radius:14px;box-shadow:0 4px 20px rgba(0,0,0,0.06);">
        <div class="panel-head" style="display:flex;align-items:center;justify-content:space-between;padding:0.9rem 1.25rem;">
            <div style="display:flex;align-items:center;gap:0.5rem;">
                <h3 style="margin:0;font-size:1.1rem;">Document Preview</h3>
                <?php if ($isImage && $fileExists): ?>
                    <div style="display:inline-flex;align-items:center;gap:4px;margin-left:0.75rem;">
                        <button type="button" class="btn btn-ghost btn-sm" onclick="zoomDoc(-0.2)" title="Zoom out" style="padding:2px 8px;font-size:1rem;line-height:1;">−</button>
                        <span id="standaloneZoomLevel" style="font-size:0.8rem;font-weight:700;min-width:42px;text-align:center;">100%</span>
                        <button type="button" class="btn btn-ghost btn-sm" onclick="zoomDoc(0.2)" title="Zoom in" style="padding:2px 8px;font-size:1rem;line-height:1;">+</button>
                        <button type="button" class="btn btn-ghost btn-sm" onclick="resetDocZoom()" title="Reset zoom" style="padding:2px 8px;font-size:0.75rem;">Reset</button>
                    </div>
                <?php endif; ?>
            </div>
            <span class="hint"><?= $fileExists ? round($fileSize / 1024, 1) . ' KB · ' . strtoupper($ext) : 'File unavailable' ?></span>
        </div>
        <div class="panel-body" style="padding:1.5rem;background:#0b1920;display:flex;justify-content:center;align-items:center;min-height:420px;position:relative;overflow:auto;">
            <?php if (!$fileExists): ?>
                <div style="text-align:center;padding:3rem 1rem;color:rgba(255,255,255,0.7);">
                    <div style="font-size:3.5rem;margin-bottom:0.75rem;">📄❌</div>
                    <h3 style="margin-bottom:0.5rem;color:#fff;">Cannot preview missing file</h3>
                    <p class="hint" style="max-width:440px;margin:0 auto 1.5rem;color:rgba(255,255,255,0.6);">
                        The document at path <code><?= e($relPath) ?></code> was moved or deleted from the server.
                    </p>
                    <a href="<?= e(url('/dashboard/verification')) ?>" class="btn btn-primary">
                        Update Your Documents
                    </a>
                </div>
            <?php elseif ($isImage): ?>
                <div id="standaloneImgContainer" style="max-width:100%;text-align:center;transition:transform 0.15s ease-out;display:flex;justify-content:center;">
                    <img id="standaloneDocImg"
                         src="<?= e($streamUrl) ?>"
                         alt="<?= e($docLabel) ?>"
                         style="max-width:100%;max-height:75vh;object-fit:contain;border-radius:8px;box-shadow:0 8px 30px rgba(0,0,0,0.4);background:#ffffff;"
                         loading="eager">
                </div>
            <?php elseif ($isPdf): ?>
                <div style="width:100%;height:75vh;">
                    <iframe src="<?= e($streamUrl) ?>" style="width:100%;height:100%;border:none;border-radius:8px;background:#fff;" title="<?= e($docLabel) ?>">
                        <p style="color:#fff;text-align:center;padding:2rem;">
                            Your browser does not support inline PDFs. <a href="<?= e($downloadUrl) ?>" style="color:#38bdf8;">Download the PDF document</a> instead.
                        </p>
                    </iframe>
                </div>
            <?php else: ?>
                <div style="text-align:center;padding:3rem 1rem;color:#fff;">
                    <div style="font-size:3.5rem;margin-bottom:0.75rem;">📁</div>
                    <h3 style="margin-bottom:0.5rem;color:#fff;"><?= e($docLabel) ?></h3>
                    <p class="hint" style="margin-bottom:1.5rem;color:rgba(255,255,255,0.6);">This file type cannot be previewed directly in the browser.</p>
                    <a href="<?= e($downloadUrl) ?>" class="btn btn-primary">
                        Download <?= strtoupper($ext) ?> (<?= round($fileSize / 1024, 1) ?> KB)
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
(function() {
    let currentZoom = 1;
    window.zoomDoc = function(delta) {
        const img = document.getElementById('standaloneDocImg');
        const lbl = document.getElementById('standaloneZoomLevel');
        if (!img) return;
        currentZoom = Math.min(Math.max(0.4, currentZoom + delta), 3.0);
        img.style.transform = 'scale(' + currentZoom + ')';
        if (lbl) lbl.textContent = Math.round(currentZoom * 100) + '%';
    };
    window.resetDocZoom = function() {
        const img = document.getElementById('standaloneDocImg');
        const lbl = document.getElementById('standaloneZoomLevel');
        if (!img) return;
        currentZoom = 1;
        img.style.transform = 'scale(1)';
        if (lbl) lbl.textContent = '100%';
    };
})();
</script>
