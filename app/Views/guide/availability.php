<?php
/** @var array<string,mixed> $listing @var array<int,array<string,mixed>> $blocked */
?>
<div class="container">
    <div class="dash-layout">
        <?= \App\Core\View::partial('partials/dash-nav') ?>
        <div>
            <h1><?= e(__('g_availability', 'Availability')) ?></h1>
            <p class="hint"><?= e($listing['title']) ?> — <?= e(__('g_block_unavailable', 'block dates when you are unavailable.')) ?></p>

            <div class="panel">
                <div class="panel-head"><h3><?= e(__('g_block_a_date', 'Block a date')) ?></h3></div>
                <div class="panel-body">
                    <form method="post" action="<?= e(url('/dashboard/listings/' . $listing['id'] . '/availability/block')) ?>" class="form-grid-2">
                        <?= csrf_field() ?>
                        <div class="field-row">
                            <label for="blocked_date"><?= e(__('th_date', 'Date')) ?></label>
                            <input class="input" type="date" id="blocked_date" name="blocked_date" required>
                        </div>
                        <div class="field-row">
                            <label for="note"><?= e(__('g_note_optional', 'Note (optional)')) ?></label>
                            <input class="input" type="text" id="note" name="note" placeholder="<?= e(__('g_leave_ph', 'e.g. Personal leave')) ?>">
                        </div>
                        <div class="field-row" style="grid-column:1/-1;">
                            <button class="btn btn-primary" type="submit"><?= e(__('g_block_date_btn', 'Block date')) ?></button>
                            <a class="btn btn-ghost" href="<?= e(url('/dashboard/listings')) ?>"><?= e(__('g_back_to_listings', 'Back to listings')) ?></a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="panel">
                <div class="panel-head"><h3><?= e(__('g_blocked_dates', 'Blocked dates')) ?></h3></div>
                <div class="panel-body" style="padding:0;">
                    <?php if ($blocked === []): ?>
                        <p class="hint" style="padding:1.3rem;"><?= e(__('g_no_blocked', 'No blocked dates.')) ?></p>
                    <?php else: ?>
                        <table class="table">
                            <thead><tr><th><?= e(__('th_date', 'Date')) ?></th><th><?= e(__('g_note', 'Note')) ?></th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($blocked as $b): ?>
                                <tr>
                                    <td><?= e(loc_date((string) $b['blocked_date'])) ?></td>
                                    <td><?= e($b['note'] ?? '—') ?></td>
                                    <td>
                                        <form method="post" action="<?= e(url('/dashboard/listings/' . $listing['id'] . '/availability/unblock')) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="blocked_date" value="<?= e($b['blocked_date']) ?>">
                                            <button class="btn btn-ghost btn-sm" type="submit"><?= e(__('g_remove', 'Remove')) ?></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
