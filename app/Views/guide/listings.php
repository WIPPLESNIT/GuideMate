<?php
/**
 * @var array<int,array<string,mixed>> $listings
 * @var array<int,array<string,mixed>> $categories
 * @var bool $canCreate
 * @var array<string,string> $errors
 */
$categories = $categories ?? [];
$canCreate = $canCreate ?? false;
$errors = $errors ?? [];
$isHotel = ((auth_user() ?? [])['role'] ?? '') === 'hotel_admin';
// Reopen the modal automatically if a create attempt came back with errors.
$openModal = $errors !== [];
$newBtnAttrs = $canCreate
    ? 'href="' . e(url('/dashboard/listings/create')) . '" data-open-new-listing'
    : 'href="' . e(url('/dashboard/verification')) . '"';
?>
<div class="container">
    <div class="dash-layout">
        <?= \App\Core\View::partial('partials/dash-nav') ?>
        <div>
            <div class="section-head" style="margin-bottom:1rem;">
                <div><h1><?= $isHotel ? 'My hotels' : e(__('d_my_listings', 'My listings')) ?></h1></div>
                <a <?= $newBtnAttrs ?> class="btn btn-primary">+ <?= $isHotel ? 'New hotel listing' : e(__('d_new_listing', 'New listing')) ?></a>
            </div>

            <div class="panel">
                <div class="panel-body" style="padding:0;overflow-x:auto;">
                    <?php if ($listings === []): ?>
                        <div class="empty-state" style="padding:3rem 1rem;">
                            <div class="big">📋</div>
                            <h3><?= $isHotel ? 'No hotel listings yet' : e(__('g_no_listings_title', 'No listings yet')) ?></h3>
                            <p><?= $isHotel
                                ? 'Create your first hotel listing so travelers can message you to inquire.'
                                : e(__('g_no_listings_body', 'Create your first listing to start receiving bookings.')) ?></p>
                            <a <?= $newBtnAttrs ?> class="btn btn-primary mt-2"><?= e(__('g_create_listing', 'Create listing')) ?></a>
                        </div>
                    <?php else: ?>
                        <table class="table">
                            <thead><tr><th><?= e(__('th_title', 'Title')) ?></th><th><?= e(__('th_category', 'Category')) ?></th><th><?= e(__('th_price', 'Price')) ?></th><th><?= e(__('th_status', 'Status')) ?></th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($listings as $l): ?>
                                <tr>
                                    <td><a href="<?= e(url('/listing/' . $l['slug'])) ?>"><strong><?= e($l['title']) ?></strong></a><br><small class="hint"><?= e($l['area']) ?></small></td>
                                    <td><?= e($l['category_name']) ?></td>
                                    <td><?= money($l['price']) ?> <small class="hint"><?= e(__('g_unit_' . str_replace(' ', '_', (string) $l['price_unit']), (string) $l['price_unit'])) ?></small></td>
                                    <td><span class="pill pill-<?= e($l['status']) ?>"><?= e(status_label((string) $l['status'])) ?></span></td>
                                    <td style="white-space:nowrap;">
                                        <a class="btn btn-ghost btn-sm" href="<?= e(url('/dashboard/listings/' . $l['id'] . '/edit')) ?>"><?= e(__('g_edit', 'Edit')) ?></a>
                                        <?php if (!$isHotel): ?>
                                        <a class="btn btn-ghost btn-sm" href="<?= e(url('/dashboard/listings/' . $l['id'] . '/schedule')) ?>"><?= e(__('g_schedule', 'Schedule')) ?></a>
                                        <?php endif; ?>
                                        <form method="post" action="<?= e(url('/dashboard/listings/' . $l['id'] . '/delete')) ?>" style="display:inline;" onsubmit="return confirm('<?= e(__('g_delete_confirm', 'Delete this listing? This cannot be undone.')) ?>');">
                                            <?= csrf_field() ?>
                                            <button class="btn btn-ghost btn-sm" type="submit" style="color:var(--coral-600);"><?= e(__('g_delete', 'Delete')) ?></button>
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

<?php if ($canCreate): ?>
<!-- Add New Listing pop-up modal -->
<div class="gm-modal" id="newListingModal" <?= $openModal ? '' : 'hidden' ?> aria-hidden="<?= $openModal ? 'false' : 'true' ?>">
    <div class="gm-modal-backdrop" data-close-modal></div>
    <div class="gm-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="newListingTitle">
        <div class="gm-modal-head">
            <h2 id="newListingTitle" style="margin:0;"><?= e(__('g_create_listing_title', 'Create a listing')) ?></h2>
            <button type="button" class="gm-modal-close" data-close-modal aria-label="Close">&times;</button>
        </div>
        <div class="gm-modal-body">
            <?php if ($errors !== []): ?>
                <div class="form-alert" role="alert">Please fix the highlighted fields and try again.</div>
            <?php endif; ?>
            <form id="newListingForm" method="post" action="<?= e(url('/dashboard/listings')) ?>" enctype="multipart/form-data" novalidate>
                <?= csrf_field() ?>

                <div class="field-row">
                    <label for="nl_title"><?= e(__('th_title', 'Title')) ?> <span class="req">*</span></label>
                    <input class="input" id="nl_title" name="title" value="<?= e(old('title')) ?>" placeholder="<?= e(__('g_title_ph', 'e.g. Kawasan Falls Canyoneering Adventure')) ?>">
                    <?php if (isset($errors['title'])): ?><div class="field-error"><?= e($errors['title']) ?></div><?php endif; ?>
                </div>

                <div class="form-grid-2">
                    <div class="field-row">
                        <label for="nl_category"><?= e(__('th_category', 'Category')) ?> <span class="req">*</span></label>
                        <select class="input" id="nl_category" name="category_id">
                            <option value=""><?= e(__('g_choose', 'Choose...')) ?></option>
                            <?php foreach ($categories as $c): $sel = (string) old('category_id') === (string) $c['id']; ?>
                                <option value="<?= (int) $c['id'] ?>" <?= $sel ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['category_id'])): ?><div class="field-error"><?= e($errors['category_id']) ?></div><?php endif; ?>
                    </div>
                    <div class="field-row">
                        <label for="nl_area"><?= e(__('g_area', 'Area in Cebu')) ?> <span class="req">*</span></label>
                        <input class="input" id="nl_area" name="area" value="<?= e(old('area')) ?>" placeholder="<?= e(__('g_area_ph', 'e.g. Moalboal, Cebu')) ?>">
                        <?php if (isset($errors['area'])): ?><div class="field-error"><?= e($errors['area']) ?></div><?php endif; ?>
                    </div>
                </div>

                <div class="field-row">
                    <label for="nl_summary"><?= e(__('g_summary', 'Short summary')) ?></label>
                    <input class="input" id="nl_summary" name="summary" value="<?= e(old('summary')) ?>" placeholder="<?= e(__('g_summary_ph', 'One sentence that sells it')) ?>" maxlength="255">
                </div>

                <div class="field-row">
                    <label for="nl_description"><?= e(__('g_description', 'Full description')) ?> <span class="req">*</span></label>
                    <textarea class="input" id="nl_description" name="description" rows="5" placeholder="<?= e(__('g_description_ph', "Describe the experience, what's included, what to bring...")) ?>"><?= e(old('description')) ?></textarea>
                    <?php if (isset($errors['description'])): ?><div class="field-error"><?= e($errors['description']) ?></div><?php endif; ?>
                </div>

                <div class="field-row">
                    <label for="nl_included"><?= e(__('g_included_label', 'Included (one item per line)')) ?></label>
                    <textarea class="input" id="nl_included" name="included" rows="3" placeholder="<?= e(__('g_included_ph', "e.g. Guide fee\nPickup from hotel\nSnorkeling gear\nLife vest")) ?>"><?= e(old('included')) ?></textarea>
                </div>
                <div class="field-row">
                    <label for="nl_not_included"><?= e(__('g_not_included_label', 'Not included — optional extras (one item per line)')) ?></label>
                    <textarea class="input" id="nl_not_included" name="not_included" rows="2" placeholder="<?= e(__('g_not_included_ph', 'e.g. Lunch\nPark entrance fee (₱100)')) ?>"><?= e(old('not_included')) ?></textarea>
                </div>

                <div class="form-grid-2">
                    <div class="field-row">
                        <label for="nl_price"><?= e(__('g_price_label', 'Price (₱)')) ?> <span class="req">*</span></label>
                        <input class="input" id="nl_price" name="price" type="number" step="0.01" min="0" value="<?= e(old('price')) ?>">
                        <?php if (isset($errors['price'])): ?><div class="field-error"><?= e($errors['price']) ?></div><?php endif; ?>
                    </div>
                    <div class="field-row">
                        <label for="nl_price_unit"><?= e(__('g_price_unit', 'Price unit')) ?></label>
                        <select class="input" id="nl_price_unit" name="price_unit">
                            <?php foreach (['per person','per night','per tour','per day','per table'] as $u): $sel = old('price_unit', 'per person') === $u; ?>
                                <option value="<?= e($u) ?>" <?= $sel ? 'selected' : '' ?>><?= e(__('g_unit_' . str_replace(' ', '_', $u), $u)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="field-row">
                        <label for="nl_duration"><?= e(__('g_duration', 'Duration (optional)')) ?></label>
                        <input class="input" id="nl_duration" name="duration" value="<?= e(old('duration')) ?>" placeholder="<?= e(__('g_duration_ph', 'e.g. 4-5 hours')) ?>">
                    </div>
                    <div class="field-row">
                        <label for="nl_address"><?= e(__('g_address', 'Address (optional)')) ?></label>
                        <input class="input" id="nl_address" name="address" value="<?= e(old('address')) ?>" placeholder="<?= e(__('g_address_ph', 'Landmark / address')) ?>">
                    </div>
                </div>

                <div class="field-row">
                    <label for="nl_cover_file"><?= e(__('g_cover_photo', 'Cover photo')) ?></label>
                    <input class="file-input" type="file" id="nl_cover_file" name="cover_file" accept=".jpg,.jpeg,.png,.webp">
                    <span class="hint"><?= e(__('g_cover_hint', 'Upload a JPG, PNG or WEBP (up to 5MB).')) ?> <?= e(__('g_cover_placeholder', 'Leave blank to use an automatic placeholder.')) ?></span>
                    <?php if (isset($errors['cover_file'])): ?><div class="field-error"><?= e($errors['cover_file']) ?></div><?php endif; ?>
                </div>
                <div class="field-row">
                    <label for="nl_cover_image"><?= e(__('g_cover_url', '…or paste a direct image URL (optional)')) ?></label>
                    <input class="input" id="nl_cover_image" name="cover_image" value="<?= e(old('cover_image')) ?>" placeholder="https://example.com/photo.jpg">
                    <?php if (isset($errors['cover_image'])): ?><div class="field-error"><?= e($errors['cover_image']) ?></div><?php endif; ?>
                </div>

                <div id="newListingError" class="form-alert" role="alert" style="display:none;"></div>

                <div class="gm-modal-foot">
                    <button type="button" class="btn btn-ghost" data-close-modal><?= e(__('booking_cancel', 'Cancel')) ?></button>
                    <button class="btn btn-primary" type="submit"><?= e(__('g_submit_review', 'Submit for review')) ?></button>
                </div>
                <p class="hint mt-2 mb-0"><?= e(__('g_new_reviewed', 'New listings are reviewed by an admin before going live.')) ?></p>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    var modal = document.getElementById('newListingModal');
    if (!modal) { return; }
    var form = document.getElementById('newListingForm');
    var errorBox = document.getElementById('newListingError');

    function openModal(e) {
        if (e) { e.preventDefault(); }
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        var first = modal.querySelector('input, select, textarea');
        if (first) { first.focus(); }
    }
    function closeModal() {
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    document.querySelectorAll('[data-open-new-listing]').forEach(function (btn) {
        btn.addEventListener('click', openModal);
    });
    modal.querySelectorAll('[data-close-modal]').forEach(function (btn) {
        btn.addEventListener('click', closeModal);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.hidden) { closeModal(); }
    });

    // Lightweight client-side validation mirroring the server rules.
    if (form) {
        var required = [
            { id: 'nl_title', label: 'Title' },
            { id: 'nl_category', label: 'Category' },
            { id: 'nl_area', label: 'Area' },
            { id: 'nl_description', label: 'Description' },
            { id: 'nl_price', label: 'Price' }
        ];
        form.addEventListener('submit', function (e) {
            var missing = [];
            required.forEach(function (f) {
                var el = document.getElementById(f.id);
                var empty = !el || String(el.value).trim() === '';
                if (el) { el.style.outline = empty ? '2px solid #DC2626' : ''; }
                if (empty) { missing.push(f.label); }
            });
            if (missing.length > 0) {
                e.preventDefault();
                errorBox.textContent = 'Please fill in: ' + missing.join(', ') + '.';
                errorBox.style.display = 'block';
                errorBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
    }
    <?php if ($openModal): ?>openModal();<?php endif; ?>
})();
</script>
<?php endif; ?>
