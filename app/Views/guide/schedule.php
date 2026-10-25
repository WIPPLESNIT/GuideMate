<?php
/**
 * @var array<string,mixed> $listing
 * @var array<int,array<string,mixed>> $schedule
 */
$schedule = $schedule ?? [];
?>
<div class="container">
    <div class="dash-layout">
        <?= \App\Core\View::partial('partials/dash-nav') ?>
        <div>
            <div class="breadcrumb"><a href="<?= e(url('/dashboard/listings')) ?>"><?= e(__('d_my_listings', 'My listings')) ?></a> › <?= e(__('g_schedule', 'Schedule')) ?></div>
            <h1><?= e(__('g_schedule', 'Schedule')) ?></h1>
            <p class="hint"><?= e($listing['title']) ?> — <?= e(__('g_schedule_hint', 'plot the dates and times you run this experience so travelers know when it is offered.')) ?></p>

            <div class="panel">
                <div class="panel-head"><h3><?= e(__('g_add_schedule', 'Add a schedule')) ?></h3></div>
                <div class="panel-body">
                    <form id="standaloneScheduleForm" method="post" action="<?= e(url('/dashboard/listings/' . $listing['id'] . '/schedule/add')) ?>" novalidate>
                        <?= csrf_field() ?>
                        <div id="standaloneScheduleError" class="form-alert" role="alert" style="display:none;margin-bottom:1rem;"></div>
                        <div class="form-grid-3" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(160px, 1fr));gap:1rem;">
                            <div class="field-row">
                                <label for="schedule_date"><?= e(__('th_date', 'Date')) ?> <span class="req">*</span></label>
                                <input class="input" type="date" id="schedule_date" name="schedule_date" min="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="field-row">
                                <label for="start_time"><?= e(__('g_start_time', 'Start time')) ?></label>
                                <input class="input" type="time" id="start_time" name="start_time">
                            </div>
                            <div class="field-row">
                                <label for="end_time"><?= e(__('g_end_time', 'End time')) ?></label>
                                <input class="input" type="time" id="end_time" name="end_time">
                            </div>
                        </div>
                        <div class="form-grid-2">
                            <div class="field-row">
                                <label for="capacity"><?= e(__('g_capacity', 'Slots / capacity (optional)')) ?></label>
                                <input class="input" type="number" id="capacity" name="capacity" min="1" placeholder="<?= e(__('g_capacity_ph', 'e.g. 10')) ?>">
                            </div>
                            <div class="field-row">
                                <label for="note"><?= e(__('g_note_optional', 'Note (optional)')) ?></label>
                                <input class="input" type="text" id="note" name="note" placeholder="<?= e(__('g_schedule_note_ph', 'e.g. Morning batch, meet at pier')) ?>">
                            </div>
                        </div>
                        <div class="mt-2" style="display:flex;gap:.6rem;">
                            <button class="btn btn-primary" type="submit"><?= e(__('g_add_to_schedule', 'Add to schedule')) ?></button>
                            <a class="btn btn-ghost" href="<?= e(url('/dashboard/listings')) ?>"><?= e(__('g_back_to_listings', 'Back to listings')) ?></a>
                        </div>
                    </form>
                    <script>
                    (function() {
                        var form = document.getElementById('standaloneScheduleForm');
                        var errBox = document.getElementById('standaloneScheduleError');
                        if (!form) return;
                        form.addEventListener('submit', function(e) {
                            var dateVal = (document.getElementById('schedule_date').value || '').trim();
                            var start = (document.getElementById('start_time').value || '').trim();
                            var end = (document.getElementById('end_time').value || '').trim();
                            if (!dateVal) {
                                e.preventDefault();
                                if (errBox) {
                                    errBox.textContent = 'Please choose a valid date for your schedule.';
                                    errBox.style.display = 'block';
                                }
                                document.getElementById('schedule_date').focus();
                                return;
                            }
                            if (start && end && end <= start) {
                                e.preventDefault();
                                if (errBox) {
                                    errBox.textContent = 'End time must be later than the start time.';
                                    errBox.style.display = 'block';
                                }
                                document.getElementById('end_time').focus();
                                return;
                            }
                            if (errBox) errBox.style.display = 'none';
                        });
                    })();
                    </script>
                </div>
            </div>

            <div class="panel">
                <div class="panel-head"><h3><?= e(__('g_upcoming_schedule', 'Upcoming schedule')) ?></h3></div>
                <div class="panel-body" style="padding:0;">
                    <?php if ($schedule === []): ?>
                        <p class="hint" style="padding:1.3rem;"><?= e(__('g_no_schedule', 'No scheduled sessions yet. Add your first one above.')) ?></p>
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th><?= e(__('th_date', 'Date')) ?></th>
                                    <th><?= e(__('g_time', 'Time')) ?></th>
                                    <th><?= e(__('g_capacity_short', 'Slots')) ?></th>
                                    <th><?= e(__('g_note', 'Note')) ?></th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($schedule as $s): ?>
                                <tr>
                                    <td><?= e(loc_date((string) $s['schedule_date'])) ?></td>
                                    <td>
                                        <?php
                                        if (!empty($s['start_time']) && !empty($s['end_time'])) {
                                            echo e(date('g:i a', strtotime((string) $s['start_time']))) . ' – ' . e(date('g:i a', strtotime((string) $s['end_time'])));
                                        } elseif (!empty($s['start_time'])) {
                                            echo e(date('g:i a', strtotime((string) $s['start_time'])));
                                        } else {
                                            echo '—';
                                        }
                                        ?>
                                    </td>
                                    <td><?= $s['capacity'] !== null ? (int) $s['capacity'] : '—' ?></td>
                                    <td><?= e($s['note'] ?? '—') ?></td>
                                    <td>
                                        <form method="post" action="<?= e(url('/dashboard/listings/' . $listing['id'] . '/schedule/remove')) ?>" onsubmit="return confirm('<?= e(__('g_schedule_remove_confirm', 'Remove this schedule entry?')) ?>');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="schedule_id" value="<?= (int) $s['id'] ?>">
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
