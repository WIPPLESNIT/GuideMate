<?php
/**
 * @var ?array<string,mixed> $listing
 * @var array<int,array<string,mixed>> $categories
 * @var array<string,string> $errors
 */
$errors = $errors ?? [];
$editing = $listing !== null;
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
                                <label for="price"><?= e(__('g_price_label', 'Price (₱)')) ?></label>
                                <input class="input" id="price" name="price" type="number" step="0.01" min="0" value="<?= $val('price') ?>">
                                <?php if (isset($errors['price'])): ?><div class="field-error"><?= e($errors['price']) ?></div><?php endif; ?>
                            </div>
                            <div class="field-row">
                                <label for="price_unit"><?= e(__('g_price_unit', 'Price unit')) ?></label>
                                <select class="input" id="price_unit" name="price_unit">
                                    <?php foreach (['per person','per night','per tour','per day','per table'] as $u):
                                        $sel = ($editing ? $listing['price_unit'] : old('price_unit', 'per person')) === $u; ?>
                                        <option value="<?= e($u) ?>" <?= $sel ? 'selected' : '' ?>><?= e(__('g_unit_' . str_replace(' ', '_', $u), $u)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-grid-2">
                            <div class="field-row">
                                <label for="duration"><?= e(__('g_duration', 'Duration (optional)')) ?></label>
                                <input class="input" id="duration" name="duration" value="<?= $val('duration') ?>" placeholder="<?= e(__('g_duration_ph', 'e.g. 4-5 hours')) ?>">
                            </div>
                            <div class="field-row">
                                <label for="address"><?= e(__('g_address', 'Address (optional)')) ?></label>
                                <input class="input" id="address" name="address" value="<?= $val('address') ?>" placeholder="<?= e(__('g_address_ph', 'Landmark / address')) ?>">
                            </div>
                        </div>

                        <div class="field-row">
                            <label for="cover_file"><?= e(__('g_cover_photo', 'Cover photo')) ?></label>
                            <?php $currentCover = $editing ? (string) ($listing['cover_image'] ?? '') : ''; ?>
                            <?php if ($currentCover !== ''): ?>
                                <img id="coverPreview" src="<?= e(img_src($currentCover, 'listing' . $listing['id'])) ?>" alt="" style="width:100%;max-width:280px;height:160px;object-fit:cover;border-radius:12px;margin-bottom:.6rem;" onerror="this.onerror=null;this.src='https://picsum.photos/seed/listing<?= (int) $listing['id'] ?>/600/400';">
                            <?php else: ?>
                                <img id="coverPreview" src="" alt="" style="display:none;width:100%;max-width:280px;height:160px;object-fit:cover;border-radius:12px;margin-bottom:.6rem;">
                            <?php endif; ?>
                            <input class="file-input" type="file" id="cover_file" name="cover_file" accept=".jpg,.jpeg,.png,.webp">
                            <span class="hint"><?= e(__('g_cover_hint', 'Upload a JPG, PNG or WEBP (up to 5MB).')) ?> <?= $editing ? e(__('g_cover_keep', 'Leave blank to keep the current photo.')) : e(__('g_cover_placeholder', 'Leave blank to use an automatic placeholder.')) ?></span>
                            <?php if (isset($errors['cover_file'])): ?><div class="field-error"><?= e($errors['cover_file']) ?></div><?php endif; ?>
                        </div>

                        <?php if (!$editing): ?>
                        <div class="field-row">
                            <label for="cover_image"><?= e(__('g_cover_url', '…or paste a direct image URL (optional)')) ?></label>
                            <input class="input" id="cover_image" name="cover_image" value="<?= e(old('cover_image')) ?>" placeholder="https://example.com/photo.jpg">
                            <span class="hint"><?= e(__('g_cover_url_hint', 'Must be a direct link ending in .jpg, .png, etc. — not a share/page link. An uploaded photo takes priority.')) ?></span>
                            <?php if (isset($errors['cover_image'])): ?><div class="field-error"><?= e($errors['cover_image']) ?></div><?php endif; ?>
                        </div>
                        <?php endif; ?>

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
