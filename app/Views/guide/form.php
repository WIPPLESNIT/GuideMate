<?php
/**
 * @var ?array<string,mixed> $listing
 * @var array<int,array<string,mixed>> $categories
 * @var array<string,string> $errors
 */
$errors = $errors ?? [];
$editing = $listing !== null;
$existingPhotos = $editing ? \App\Models\Listing::getPhotos($listing) : [];
$action = $editing ? url('/dashboard/listings/' . $listing['id']) : url('/dashboard/listings');
$val = fn(string $k, string $d = '') => e($editing ? (string) ($listing[$k] ?? $d) : old($k, $d));
?>
<div class="container">
    <div class="dash-layout">
        <?= \App\Core\View::partial('partials/dash-nav') ?>
        <div>
            <div class="breadcrumb"><a href="<?= e(url('/dashboard/listings')) ?>"><?= e(__('d_my_listings', 'My listings')) ?></a> › <?= $editing ? e(__('g_edit', 'Edit')) : e(__('g_create', 'Create')) ?></div>
            <h1><?= $editing ? e(__('g_edit_listing', 'Edit listing')) : e(__('g_create_listing_title', 'Create a listing')) ?></h1>

            <div class="panel" style="max-width:760px;">
                <div class="panel-body">
                    <form method="post" action="<?= e($action) ?>" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <?php if ($editing): ?><input type="hidden" name="listing_id" value="<?= (int) $listing['id'] ?>"><?php endif; ?>

                        <div class="field-row">
                            <label for="title"><?= e(__('th_title', 'Title')) ?></label>
                            <input class="input" id="title" name="title" value="<?= $val('title') ?>" placeholder="<?= e(__('g_title_ph', 'e.g. Kawasan Falls Canyoneering Adventure')) ?>">
                            <?php if (isset($errors['title'])): ?><div class="field-error"><?= e($errors['title']) ?></div><?php endif; ?>
                        </div>

                        <div class="form-grid-2">
                            <div class="field-row">
                                <label for="category_id"><?= e(__('th_category', 'Category')) ?></label>
                                <select class="input" id="category_id" name="category_id">
                                    <option value=""><?= e(__('g_choose', 'Choose...')) ?></option>
                                    <?php foreach ($categories as $c):
                                        $sel = (string) ($editing ? $listing['category_id'] : old('category_id')) === (string) $c['id']; ?>
                                        <option value="<?= (int) $c['id'] ?>" <?= $sel ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errors['category_id'])): ?><div class="field-error"><?= e($errors['category_id']) ?></div><?php endif; ?>
                            </div>
                            <div class="field-row">
                                <label for="area"><?= e(__('g_area', 'Area in Cebu')) ?></label>
                                <input class="input" id="area" name="area" value="<?= $val('area') ?>" placeholder="<?= e(__('g_area_ph', 'e.g. Moalboal, Cebu')) ?>">
                                <?php if (isset($errors['area'])): ?><div class="field-error"><?= e($errors['area']) ?></div><?php endif; ?>
                            </div>
                        </div>

                        <div class="field-row">
                            <label for="summary"><?= e(__('g_summary', 'Short summary')) ?></label>
                            <input class="input" id="summary" name="summary" value="<?= $val('summary') ?>" placeholder="<?= e(__('g_summary_ph', 'One sentence that sells it')) ?>" maxlength="255">
                        </div>

                        <div class="field-row">
                            <label for="description"><?= e(__('g_description', 'Full description')) ?></label>
                            <textarea class="input" id="description" name="description" rows="6" placeholder="<?= e(__('g_description_ph', "Describe the experience, what's included, what to bring...")) ?>"><?= $val('description') ?></textarea>
                            <?php if (isset($errors['description'])): ?><div class="field-error"><?= e($errors['description']) ?></div><?php endif; ?>
                        </div>

                        <div class="guide-docs" style="margin-bottom:1rem;">
                            <div class="guide-docs-head">
                                <strong><?= e(__('g_included_title', "What's included in the price")) ?></strong>
                                <span class="guide-docs-badge"><?= e(__('g_required_trust', 'Required for trust')) ?></span>
                            </div>
                            <p class="hint mb-0" style="margin-bottom:.75rem;"><?= e(__('g_included_hint', 'List everything covered by your booking price. Guides must not ask for extra payment after a tourist has paid unless it was clearly listed below.')) ?></p>
                        </div>

                        <div class="field-row">
                            <label for="included"><?= e(__('g_included_label', 'Included (one item per line)')) ?></label>
                            <textarea class="input" id="included" name="included" rows="4" placeholder="<?= e(__('g_included_ph', "e.g. Guide fee\nPickup from hotel\nSnorkeling gear\nLife vest")) ?>"><?= $val('included') ?></textarea>
                        </div>
                        <div class="field-row">
                            <label for="not_included"><?= e(__('g_not_included_label', 'Not included — optional extras (one item per line)')) ?></label>
                            <textarea class="input" id="not_included" name="not_included" rows="3" placeholder="<?= e(__('g_not_included_ph', 'e.g. Lunch\nPark entrance fee (₱100)')) ?>"><?= $val('not_included') ?></textarea>
                            <span class="hint"><?= e(__('g_not_included_hint', 'Only costs listed here may be requested separately. Everything else must be covered by the booking price.')) ?></span>
                        </div>

                        <div class="form-grid-2">
                            <div class="field-row">
                                <label for="price"><?= e(__('g_price_label', 'Price (₱)')) ?> <span class="req">*</span></label>
                                <input class="input" id="price" name="price" type="number" step="0.01" min="0" value="<?= $val('price') ?>" placeholder="e.g. 2500">
                                <?php if (isset($errors['price'])): ?><div class="field-error"><?= e($errors['price']) ?></div><?php endif; ?>
                            </div>
                            <div class="field-row">
                                <label for="price_unit"><?= e(__('g_price_unit', 'Price unit')) ?> <span class="req">*</span></label>
                                <?php $formUnit = strtolower((string) $val('price_unit', 'per person')); ?>
                                <select class="input" id="price_unit" name="price_unit" onchange="var h = document.getElementById('form_price_unit_hint'); if(h) h.textContent = this.value === 'per group' ? 'Fixed rate for the entire group/tour.' : 'Rate per individual tourist/guest.';">
                                    <option value="per person" <?= $formUnit === 'per person' ? 'selected' : '' ?>><?= e(__('g_unit_per_person', 'Per Person')) ?></option>
                                    <option value="per group" <?= $formUnit === 'per group' ? 'selected' : '' ?>><?= e(__('g_unit_per_group', 'Per Group')) ?></option>
                                </select>
                                <span id="form_price_unit_hint" class="hint" style="font-size:0.75rem;color:#64748b;display:block;margin-top:2px;"><?= $formUnit === 'per group' ? 'Fixed rate for the entire group/tour.' : 'Rate per individual tourist/guest.' ?></span>
                            </div>
                        </div>

                        <div class="form-grid-2">
                            <div class="field-row">
                                <label for="capacity">Slots / Capacity <span class="req">*</span></label>
                                <input class="input" id="capacity" name="capacity" type="number" min="1" max="100" value="<?= $val('capacity') ?: '10' ?>" placeholder="e.g. 10">
                                <span class="hint" style="font-size:0.75rem;color:#64748b;display:block;margin-top:2px;">Max participants allowed per booking session or group.</span>
                                <?php if (isset($errors['capacity'])): ?><div class="field-error"><?= e($errors['capacity']) ?></div><?php endif; ?>
                            </div>
                            <div class="field-row">
                                <label for="duration"><?= e(__('g_duration', 'Duration (optional)')) ?></label>
                                <input class="input" id="duration" name="duration" value="<?= $val('duration') ?>" placeholder="<?= e(__('g_duration_ph', 'e.g. 4-5 hours')) ?>">
                            </div>
                        </div>

                        <div class="field-row">
                            <label for="address"><?= e(__('g_address', 'Address / Departure Point (optional)')) ?></label>
                            <input class="input" id="address" name="address" value="<?= $val('address') ?>" placeholder="<?= e(__('g_address_ph', 'Landmark / meeting point')) ?>">
                        </div>

                        <?php
                        $existingPhotos = $editing ? \App\Models\Listing::getPhotos((int) $listing['id']) : [];
                        ?>
                        <!-- Structured 3-Photo Upload Section (Replicating Hotel Photo Upload Pattern) -->
                        <div class="field-row" style="background:#f8fafc;padding:0.85rem 1rem;border-radius:8px;border:1px dashed #cbd5e1;margin-bottom:1rem;">
                            <label style="font-weight:700;font-size:0.92rem;display:flex;align-items:center;justify-content:space-between;margin-bottom:0.25rem;color:var(--text, #0f172a);">
                                <span>📸 Experience Photos (Upload up to 3 photos)</span>
                                <span style="font-size:0.75rem;font-weight:600;background:#e0f2fe;color:#0284c7;padding:2px 8px;border-radius:12px;">Max 3 Photos</span>
                            </label>
                            <span class="hint" style="display:block;margin-bottom:0.75rem;font-size:0.8rem;color:#64748b;">
                                Upload high-quality JPG, PNG, or WEBP images (up to 5MB each) or paste direct photo URLs. Photo 1 will be used as the primary cover photo.
                            </span>

                            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(160px, 1fr));gap:0.75rem;">
                                <?php for ($slot = 0; $slot < 3; $slot++):
                                    $pSrc = $existingPhotos[$slot] ?? ($slot === 0 ? ($listing['cover_image'] ?? '') : '');
                                    $isCover = $slot === 0;
                                ?>
                                <div style="border:1px solid #e2e8f0;border-radius:8px;background:#ffffff;padding:0.7rem;display:flex;flex-direction:column;gap:0.4rem;">
                                    <div style="display:flex;justify-content:space-between;align-items:center;">
                                        <strong style="font-size:0.8rem;color:#0f172a;">Photo <?= $slot + 1 ?> <?= $isCover ? '(Cover) *' : '(Gallery)' ?></strong>
                                        <span style="font-size:0.68rem;<?= $isCover ? 'background:#dcfce7;color:#166534;font-weight:700;' : 'background:#f1f5f9;color:#64748b;font-weight:600;' ?>padding:1px 6px;border-radius:4px;"><?= $isCover ? 'Cover' : 'Optional' ?></span>
                                    </div>
                                    <div id="gf_preview_wrap_<?= $slot ?>" style="position:relative;height:100px;background:#f1f5f9;border-radius:6px;overflow:hidden;display:flex;align-items:center;justify-content:center;border:1px solid #e2e8f0;">
                                        <img id="gf_preview_img_<?= $slot ?>" src="<?= $pSrc ? e(img_src($pSrc, 'listing-' . ($listing['id'] ?? 0))) : '' ?>" alt="Photo <?= $slot + 1 ?>" style="width:100%;height:100%;object-fit:cover;<?= $pSrc ? '' : 'display:none;' ?>">
                                        <span id="gf_placeholder_<?= $slot ?>" style="font-size:0.75rem;color:#94a3b8;text-align:center;padding:4px;<?= $pSrc ? 'display:none;' : '' ?>">📷 No photo uploaded</span>
                                        <button type="button" id="gf_remove_btn_<?= $slot ?>" onclick="removeFormPhoto(<?= $slot ?>)" style="<?= $pSrc ? 'display:flex;' : 'display:none;' ?>position:absolute;top:4px;right:4px;background:rgba(0,0,0,0.65);color:#fff;border:none;border-radius:50%;width:22px;height:22px;font-size:12px;line-height:1;cursor:pointer;align-items:center;justify-content:center;" title="Remove Photo">&times;</button>
                                    </div>
                                    <input type="file" name="photo_file_<?= $slot ?>" id="gf_file_<?= $slot ?>" class="file-input" accept=".jpg,.jpeg,.png,.webp" style="font-size:0.75rem;width:100%;" onchange="handleFormFileChange(<?= $slot ?>, this)">
                                    <input type="text" name="photo_url_<?= $slot ?>" id="gf_url_<?= $slot ?>" class="input" placeholder="…or photo URL" style="font-size:0.78rem;padding:0.35rem 0.5rem;" oninput="handleFormUrlChange(<?= $slot ?>, this.value)">
                                    <input type="hidden" name="existing_photo_<?= $slot ?>" id="gf_existing_<?= $slot ?>" value="<?= e($pSrc) ?>">
                                    <input type="hidden" name="photo_removed_<?= $slot ?>" id="gf_removed_<?= $slot ?>" value="0">
                                    <?php if ($isCover): ?><input type="hidden" name="cover_image" id="gf_cover_image" value="<?= e($pSrc) ?>"><?php endif; ?>
                                </div>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <script>
                        function resolveFormImageUrl(path) {
                            if (!path) return '';
                            path = String(path).trim();
                            if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('data:image/') || path.startsWith('data:')) {
                                return path;
                            }
                            var base = (window.APP_BASE_URL !== undefined ? window.APP_BASE_URL : <?= json_encode(rtrim(url('/'), '/')) ?>) || '';
                            if (base && (path === base || path.startsWith(base + '/'))) {
                                return path.startsWith('/') ? path : '/' + path;
                            }
                            var clean = path.replace(/^\/+/, '');
                            return (base ? base + '/' : '/') + clean;
                        }
                        function handleFormFileChange(slot, input) {
                            if (!input.files || !input.files[0]) return;
                            var file = input.files[0];
                            var reader = new FileReader();
                            reader.onload = function(e) {
                                setFormSlotPreview(slot, e.target.result);
                                var remIn = document.getElementById('gf_removed_' + slot);
                                if (remIn) remIn.value = '0';
                            };
                            reader.readAsDataURL(file);
                        }
                        function handleFormUrlChange(slot, val) {
                            val = (val || '').trim();
                            var remIn = document.getElementById('gf_removed_' + slot);
                            if (val) {
                                setFormSlotPreview(slot, val);
                                if (remIn) remIn.value = '0';
                                if (slot === 0) {
                                    var cov = document.getElementById('gf_cover_image');
                                    if (cov) cov.value = val;
                                }
                            } else {
                                var exist = document.getElementById('gf_existing_' + slot);
                                if (exist && exist.value) {
                                    setFormSlotPreview(slot, exist.value);
                                } else {
                                    clearFormSlotPreview(slot);
                                }
                            }
                        }
                        function removeFormPhoto(slot) {
                            var fileIn = document.getElementById('gf_file_' + slot);
                            if (fileIn) fileIn.value = '';
                            var urlIn = document.getElementById('gf_url_' + slot);
                            if (urlIn) urlIn.value = '';
                            var existingIn = document.getElementById('gf_existing_' + slot);
                            if (existingIn) existingIn.value = '';
                            var remIn = document.getElementById('gf_removed_' + slot);
                            if (remIn) remIn.value = '1';
                            if (slot === 0) {
                                var cov = document.getElementById('gf_cover_image');
                                if (cov) cov.value = '';
                            }
                            clearFormSlotPreview(slot);
                        }
                        function setFormSlotPreview(slot, src) {
                            var img = document.getElementById('gf_preview_img_' + slot);
                            var ph = document.getElementById('gf_placeholder_' + slot);
                            var btn = document.getElementById('gf_remove_btn_' + slot);
                            if (img && ph && btn) {
                                var resolved = resolveFormImageUrl(src);
                                img.onload = function() {
                                    img.style.display = 'block';
                                    ph.style.display = 'none';
                                    btn.style.display = 'flex';
                                };
                                img.onerror = function() {
                                    img.style.display = 'none';
                                    ph.textContent = '⚠️ Image not found';
                                    ph.style.display = 'block';
                                    btn.style.display = 'flex';
                                };
                                img.src = resolved;
                                img.style.display = 'block';
                                ph.style.display = 'none';
                                btn.style.display = 'flex';
                            }
                        }
                        function clearFormSlotPreview(slot) {
                            var img = document.getElementById('gf_preview_img_' + slot);
                            var ph = document.getElementById('gf_placeholder_' + slot);
                            var btn = document.getElementById('gf_remove_btn_' + slot);
                            if (img && ph && btn) {
                                img.onload = null;
                                img.onerror = null;
                                img.src = '';
                                img.style.display = 'none';
                                ph.textContent = '📷 No photo uploaded';
                                ph.style.display = 'block';
                                btn.style.display = 'none';
                            }
                        }
                        </script>

                        <div class="mt-2" style="display:flex;gap:.6rem;">
                            <button class="btn btn-primary btn-lg" type="submit"><?= $editing ? e(__('g_save_changes', 'Save changes')) : e(__('g_submit_review', 'Submit for review')) ?></button>
                            <a class="btn btn-ghost btn-lg" href="<?= e(url('/dashboard/listings')) ?>"><?= e(__('booking_cancel', 'Cancel')) ?></a>
                        </div>
                        <?php if (!$editing): ?><p class="hint mt-2"><?= e(__('g_new_reviewed', 'New listings are reviewed by an admin before going live.')) ?></p><?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
