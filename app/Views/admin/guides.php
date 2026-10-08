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
<h1>Partner applications</h1>
<p class="hint">Pending tour guides, rental partners and hotel partners awaiting verification.</p>

<?php if ($guides === []): ?>
    <div class="panel"><div class="empty-state" style="padding:2.5rem 1rem;"><p>No partner applications yet.</p></div></div>
<?php else: ?>
    <?php foreach ($guides as $g): ?>
        <?php $status = (string) ($g['guide_status'] ?? 'none'); $docs = $documents[(int) $g['id']] ?? []; ?>
        <div class="panel">
            <div class="panel-head">
                <div>
                    <h3 style="margin-bottom:.2rem;"><?= e($g['name']) ?>
                        <span class="pill pill-confirmed"><?= e(User::PROVIDER_LABELS[$g['role']] ?? ucfirst((string) $g['role'])) ?></span>
                        <span class="pill <?= $statusPill[$status] ?? 'pill-pending' ?>"><?= e(ucfirst($status)) ?></span>
                    </h3>
                    <p class="hint mb-0"><?= e($g['email']) ?> · joined <?= e(date('M j, Y', strtotime($g['created_at']))) ?></p>
                </div>
            </div>
            <div class="panel-body">
                <h4 style="margin:0 0 .6rem;">Submitted documents</h4>
                <?php if ($docs === []): ?>
                    <p class="hint">No documents on file.</p>
                <?php else: ?>
                    <ul class="doc-list">
                        <?php foreach ($docs as $d): ?>
                            <li>
                                <span class="doc-type"><?= e(GuideDocument::typeLabel($d['doc_type'])) ?></span>
                                <a href="<?= e(url($d['file_path'])) ?>" target="_blank" rel="noopener" class="doc-link">
                                    📎 <?= e($d['label'] ?: basename($d['file_path'])) ?> ↗
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <?php if ($status === 'rejected' && !empty($g['guide_review_note'])): ?>
                    <div class="note-box"><strong>Rejection note:</strong> <?= e($g['guide_review_note']) ?></div>
                <?php endif; ?>

                <div class="guide-actions">
                    <?php if ($status !== 'approved'): ?>
                        <form method="post" action="<?= e(url('/admin/guides/' . $g['id'] . '/approve')) ?>">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-primary btn-sm">✓ Approve</button>
                        </form>
                    <?php endif; ?>
                    <form method="post" action="<?= e(url('/admin/guides/' . $g['id'] . '/reject')) ?>" class="reject-form">
                        <?= csrf_field() ?>
                        <input class="input" type="text" name="note" placeholder="Reason (optional, shown to the applicant)">
                        <button type="submit" class="btn btn-ghost btn-sm">Reject</button>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
