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
$isHotel = in_array(((auth_user() ?? [])['role'] ?? ''), ['hotel_provider', 'hotel_admin'], true);
// Reopen the modal automatically if a create attempt came back with errors.
$openModal = $errors !== [];
$newBtnAttrs = 'href="' . e(url('/dashboard/listings?create=1')) . '" data-open-new-listing data-action="new-listing"';
?>
<div class="container">
    <div class="dash-layout">
        <?= \App\Core\View::partial('partials/dash-nav') ?>
        <div>
            <div class="section-head" style="margin-bottom:1rem;">
                <div><h1><?= $isHotel ? 'My hotels' : e(__('d_my_listings', 'My listings')) ?></h1></div>
                <div style="display:flex;align-items:center;gap:0.6rem;flex-wrap:wrap;">
                    <?php if (!$isHotel): ?>
                        <a href="<?= e(url('/dashboard/packages/create')) ?>" class="btn btn-primary btn-new-travel-package">
                            + New Travel Package
                        </a>
                    <?php endif; ?>
                    <a <?= $newBtnAttrs ?> class="btn btn-primary">+ <?= $isHotel ? 'New hotel listing' : e(__('d_new_listing', 'New listing')) ?></a>
                </div>
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
                        </div>
                    <?php else: ?>
                        <table class="table" style="vertical-align:middle;width:100%;">
                            <thead>
                                <tr>
                                    <th><?= e(__('th_title', 'Title')) ?></th>
                                    <th><?= e(__('th_category', 'Category')) ?></th>
                                    <th><?= e(__('th_price', 'Price')) ?></th>
                                    <th><?= e(__('th_status', 'Status')) ?></th>
                                    <th style="text-align:right;min-width:80px;"><?= e(__('th_actions', 'Actions')) ?></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($listings as $l): ?>
                                <tr>
                                    <td>
                                        <div style="display:flex;align-items:center;gap:0.75rem;">
                                            <?php if (!empty($l['cover_image'])): ?>
                                                <img src="<?= e(img_src($l['cover_image'], 'listing-' . $l['id'])) ?>" alt=""
                                                     style="width:48px;height:48px;border-radius:8px;object-fit:cover;flex-shrink:0;background:#f1f5f9;">
                                            <?php endif; ?>
                                            <div>
                                                <a href="<?= e(url('/listing/' . ($l['slug'] ?? $l['id']))) ?>" target="_blank" style="font-weight:700;color:var(--text, #0f172a);text-decoration:none;">
                                                    <?= e($l['title']) ?>
                                                </a>
                                                <div class="hint" style="font-size:0.8rem;margin-top:0.15rem;">
                                                    <?= e($l['area']) ?><?php if (!empty($l['address'])): ?> · <?= e($l['address']) ?><?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?= e($l['category_name']) ?>
                                    </td>
                                    <td>
                                        <strong style="color:var(--brand, #059669);"><?= money($l['price']) ?></strong>
                                        <?php if (($l['category_slug'] ?? '') === 'packages'): ?>
                                            <small class="hint">Package Price</small>
                                        <?php else: ?>
                                            <small class="hint"><?= e(__('g_unit_' . str_replace(' ', '_', (string) $l['price_unit']), (string) $l['price_unit'])) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="pill pill-<?= e($l['status']) ?>"><?= e(status_label((string) $l['status'])) ?></span>
                                        <?php if ($l['status'] === 'rejected' && !empty($l['rejection_reason'])): ?>
                                            <div style="font-size:0.75rem;color:#e11d48;margin-top:4px;max-width:180px;line-height:1.2;" title="<?= e($l['rejection_reason']) ?>">
                                                ⚠️ <?= e(mb_strimwidth($l['rejection_reason'], 0, 35, '...')) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:right;white-space:nowrap;">
                                        <div style="display:inline-flex;align-items:center;justify-content:flex-end;gap:6px;">
                                            <div class="res-action-wrap">
                                                <button type="button"
                                                        class="btn-res-action-trigger"
                                                        id="listingActionBtn_<?= (int) $l['id'] ?>"
                                                        aria-haspopup="true"
                                                        aria-expanded="false"
                                                        aria-controls="listingActionMenu_<?= (int) $l['id'] ?>"
                                                        title="Actions for <?= e($l['title']) ?>"
                                                        aria-label="Actions for <?= e($l['title']) ?>">
                                                    <?= admin_icon('more-vertical', 16) ?>
                                                </button>
                                                <div class="res-action-dropdown"
                                                     id="listingActionMenu_<?= (int) $l['id'] ?>"
                                                     role="menu"
                                                     aria-labelledby="listingActionBtn_<?= (int) $l['id'] ?>">
                                                    <?php
                                                    $isPkgListing = (($l['category_slug'] ?? '') === 'packages') || (($l['price_unit'] ?? '') === 'package');
                                                    ?>
                                                    <?php if ($isPkgListing): ?>
                                                        <a href="<?= e(url('/dashboard/packages/' . (int) $l['id'] . '/edit')) ?>"
                                                           class="res-action-item"
                                                           role="menuitem">
                                                            <?= admin_icon('edit', 14) ?> <?= e(__('g_edit', 'Edit')) ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <button type="button"
                                                                class="res-action-item"
                                                                onclick='openEditListingModal(<?= htmlspecialchars(json_encode($l, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, "UTF-8") ?>)'
                                                                role="menuitem">
                                                            <?= admin_icon('edit', 14) ?> <?= e(__('g_edit', 'Edit')) ?>
                                                        </button>
                                                        <?php if (!$isHotel): ?>
                                                        <button type="button"
                                                                class="res-action-item"
                                                                onclick='openScheduleModal(<?= (int) $l['id'] ?>, <?= htmlspecialchars(json_encode((string) $l['title'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, "UTF-8") ?>)'
                                                                role="menuitem">
                                                            <?= admin_icon('calendar', 14) ?> <?= e(__('g_schedule', 'Schedule')) ?>
                                                        </button>
                                                        <?php endif; ?>
                                                        <a href="<?= e(url('/listing/' . ($l['slug'] ?? $l['id']))) ?>" target="_blank" class="res-action-item" role="menuitem">
                                                            <?= admin_icon('eye', 14) ?> <?= e(__('d_view_live', 'View Live Listing')) ?>
                                                        </a>
                                                    <?php endif; ?>
                                                    <div style="border-top:1px solid var(--line, #e2e8f0);margin:2px 0;"></div>
                                                    <form method="post" action="<?= e(url('/dashboard/listings/' . $l['id'] . '/delete')) ?>" class="res-action-form" onsubmit="return confirm('<?= e(__('g_delete_confirm', 'Delete this listing? This cannot be undone.')) ?>');">
                                                        <?= csrf_field() ?>
                                                        <button type="submit" class="res-action-item action-cancel" role="menuitem">
                                                            <?= admin_icon('trash', 14) ?> <?= e(__('g_delete', 'Delete')) ?>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
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

<?php if ($canCreate || $listings !== []): ?>
<!-- Add / Edit Listing pop-up modal -->
<div class="gm-modal" id="newListingModal" <?= $openModal ? '' : 'hidden' ?> aria-hidden="<?= $openModal ? 'false' : 'true' ?>">
    <div class="gm-modal-backdrop" data-close-modal></div>
    <div class="gm-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="newListingTitle">
        <div class="gm-modal-head">
            <div>
                <h2 id="newListingTitle" style="margin:0;"><?= e(__('g_create_listing_title', 'Create a listing')) ?></h2>
                <p id="newListingSubtitle" class="hint mb-0" style="font-size:0.85rem;margin-top:0.25rem;">Fill in the details for your tour or experience.</p>
            </div>
            <button type="button" class="gm-modal-close" data-close-modal aria-label="Close">&times;</button>
        </div>
        <div class="gm-modal-body">
            <?php if ($errors !== []): ?>
                <div class="form-alert" role="alert">Please fix the highlighted fields and try again.</div>
            <?php endif; ?>
            <form id="newListingForm" method="post" action="<?= e(url('/dashboard/listings')) ?>" enctype="multipart/form-data" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" id="nl_listing_id" name="listing_id" value="">

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
                        <input class="input" id="nl_price" name="price" type="number" step="0.01" min="0" value="<?= e(old('price')) ?>" placeholder="e.g. 2500">
                        <?php if (isset($errors['price'])): ?><div class="field-error"><?= e($errors['price']) ?></div><?php endif; ?>
                    </div>
                    <div class="field-row">
                        <label for="nl_price_unit"><?= e(__('g_price_unit', 'Price unit')) ?> <span class="req">*</span></label>
                        <?php $selectedUnit = strtolower((string) old('price_unit', 'per person')); ?>
                        <select class="input" id="nl_price_unit" name="price_unit" onchange="updateGuidePriceUnitHint(this.value)">
                            <option value="per person" <?= $selectedUnit === 'per person' ? 'selected' : '' ?>><?= e(__('g_unit_per_person', 'Per Person')) ?></option>
                            <option value="per group" <?= $selectedUnit === 'per group' ? 'selected' : '' ?>><?= e(__('g_unit_per_group', 'Per Group')) ?></option>
                        </select>
                        <span id="nl_price_unit_hint" class="hint" style="font-size:0.75rem;color:#64748b;display:block;margin-top:2px;"><?= $selectedUnit === 'per group' ? 'Fixed rate for the entire group/tour.' : 'Rate per individual tourist/guest.' ?></span>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="field-row">
                        <label for="nl_capacity">Slots / Capacity <span class="req">*</span></label>
                        <input class="input" id="nl_capacity" name="capacity" type="number" min="1" max="100" value="<?= e(old('capacity', '10')) ?>" placeholder="e.g. 10">
                        <span class="hint" style="font-size:0.75rem;color:#64748b;display:block;margin-top:2px;">Max participants allowed per booking session or group.</span>
                        <?php if (isset($errors['capacity'])): ?><div class="field-error"><?= e($errors['capacity']) ?></div><?php endif; ?>
                    </div>
                    <div class="field-row">
                        <label for="nl_duration"><?= e(__('g_duration', 'Duration (optional)')) ?></label>
                        <input class="input" id="nl_duration" name="duration" value="<?= e(old('duration')) ?>" placeholder="<?= e(__('g_duration_ph', 'e.g. 4-5 hours')) ?>">
                    </div>
                </div>

                <div class="field-row">
                    <label for="nl_address"><?= e(__('g_address', 'Address / Departure Point (optional)')) ?></label>
                    <input class="input" id="nl_address" name="address" value="<?= e(old('address')) ?>" placeholder="<?= e(__('g_address_ph', 'Landmark / meeting point')) ?>">
                </div>

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
                        <!-- Slot 0: Primary Cover -->
                        <div style="border:1px solid #e2e8f0;border-radius:8px;background:#ffffff;padding:0.7rem;display:flex;flex-direction:column;gap:0.4rem;">
                            <div style="display:flex;justify-content:space-between;align-items:center;">
                                <strong style="font-size:0.8rem;color:#0f172a;">Photo 1 (Cover) *</strong>
                                <span style="font-size:0.68rem;background:#dcfce7;color:#166534;font-weight:700;padding:1px 6px;border-radius:4px;">Cover</span>
                            </div>
                            <div id="gl_preview_wrap_0" style="position:relative;height:100px;background:#f1f5f9;border-radius:6px;overflow:hidden;display:flex;align-items:center;justify-content:center;border:1px solid #e2e8f0;">
                                <span id="nl_preview_wrap" style="display:none;" aria-hidden="true"></span>
                                <img id="gl_preview_img_0" src="" alt="Photo 1" style="width:100%;height:100%;object-fit:cover;display:none;">
                                <span id="gl_placeholder_0" style="font-size:0.75rem;color:#94a3b8;text-align:center;padding:4px;">📷 No photo uploaded</span>
                                <button type="button" id="gl_remove_btn_0" onclick="removeGuidePhoto(0)" style="display:none;position:absolute;top:4px;right:4px;background:rgba(0,0,0,0.65);color:#fff;border:none;border-radius:50%;width:22px;height:22px;font-size:12px;line-height:1;cursor:pointer;align-items:center;justify-content:center;" title="Remove Photo">&times;</button>
                            </div>
                            <input type="file" name="photo_file_0" id="gl_file_0" class="file-input" accept=".jpg,.jpeg,.png,.webp" style="font-size:0.75rem;width:100%;" onchange="handleGuideFileChange(0, this)">
                            <input type="text" name="photo_url_0" id="gl_url_0" class="input" placeholder="…or photo URL" style="font-size:0.78rem;padding:0.35rem 0.5rem;" oninput="handleGuideUrlChange(0, this.value)">
                            <input type="hidden" name="existing_photo_0" id="gl_existing_0" value="">
                            <input type="hidden" name="photo_removed_0" id="gl_removed_0" value="0">
                            <input type="hidden" name="cover_image" id="gl_cover_image" value="">
                        </div>

                        <!-- Slot 1: Gallery 2 -->
                        <div style="border:1px solid #e2e8f0;border-radius:8px;background:#ffffff;padding:0.7rem;display:flex;flex-direction:column;gap:0.4rem;">
                            <div style="display:flex;justify-content:space-between;align-items:center;">
                                <strong style="font-size:0.8rem;color:#0f172a;">Photo 2 (Gallery)</strong>
                                <span style="font-size:0.68rem;background:#f1f5f9;color:#64748b;font-weight:600;padding:1px 6px;border-radius:4px;">Optional</span>
                            </div>
                            <div id="gl_preview_wrap_1" style="position:relative;height:100px;background:#f1f5f9;border-radius:6px;overflow:hidden;display:flex;align-items:center;justify-content:center;border:1px solid #e2e8f0;">
                                <img id="gl_preview_img_1" src="" alt="Photo 2" style="width:100%;height:100%;object-fit:cover;display:none;">
                                <span id="gl_placeholder_1" style="font-size:0.75rem;color:#94a3b8;text-align:center;padding:4px;">📷 No photo uploaded</span>
                                <button type="button" id="gl_remove_btn_1" onclick="removeGuidePhoto(1)" style="display:none;position:absolute;top:4px;right:4px;background:rgba(0,0,0,0.65);color:#fff;border:none;border-radius:50%;width:22px;height:22px;font-size:12px;line-height:1;cursor:pointer;align-items:center;justify-content:center;" title="Remove Photo">&times;</button>
                            </div>
                            <input type="file" name="photo_file_1" id="gl_file_1" class="file-input" accept=".jpg,.jpeg,.png,.webp" style="font-size:0.75rem;width:100%;" onchange="handleGuideFileChange(1, this)">
                            <input type="text" name="photo_url_1" id="gl_url_1" class="input" placeholder="…or photo URL" style="font-size:0.78rem;padding:0.35rem 0.5rem;" oninput="handleGuideUrlChange(1, this.value)">
                            <input type="hidden" name="existing_photo_1" id="gl_existing_1" value="">
                            <input type="hidden" name="photo_removed_1" id="gl_removed_1" value="0">
                        </div>

                        <!-- Slot 2: Gallery 3 -->
                        <div style="border:1px solid #e2e8f0;border-radius:8px;background:#ffffff;padding:0.7rem;display:flex;flex-direction:column;gap:0.4rem;">
                            <div style="display:flex;justify-content:space-between;align-items:center;">
                                <strong style="font-size:0.8rem;color:#0f172a;">Photo 3 (Gallery)</strong>
                                <span style="font-size:0.68rem;background:#f1f5f9;color:#64748b;font-weight:600;padding:1px 6px;border-radius:4px;">Optional</span>
                            </div>
                            <div id="gl_preview_wrap_2" style="position:relative;height:100px;background:#f1f5f9;border-radius:6px;overflow:hidden;display:flex;align-items:center;justify-content:center;border:1px solid #e2e8f0;">
                                <img id="gl_preview_img_2" src="" alt="Photo 3" style="width:100%;height:100%;object-fit:cover;display:none;">
                                <span id="gl_placeholder_2" style="font-size:0.75rem;color:#94a3b8;text-align:center;padding:4px;">📷 No photo uploaded</span>
                                <button type="button" id="gl_remove_btn_2" onclick="removeGuidePhoto(2)" style="display:none;position:absolute;top:4px;right:4px;background:rgba(0,0,0,0.65);color:#fff;border:none;border-radius:50%;width:22px;height:22px;font-size:12px;line-height:1;cursor:pointer;align-items:center;justify-content:center;" title="Remove Photo">&times;</button>
                            </div>
                            <input type="file" name="photo_file_2" id="gl_file_2" class="file-input" accept=".jpg,.jpeg,.png,.webp" style="font-size:0.75rem;width:100%;" onchange="handleGuideFileChange(2, this)">
                            <input type="text" name="photo_url_2" id="gl_url_2" class="input" placeholder="…or photo URL" style="font-size:0.78rem;padding:0.35rem 0.5rem;" oninput="handleGuideUrlChange(2, this.value)">
                            <input type="hidden" name="existing_photo_2" id="gl_existing_2" value="">
                            <input type="hidden" name="photo_removed_2" id="gl_removed_2" value="0">
                        </div>
                    </div>
                </div>

                <div id="newListingError" class="form-alert" role="alert" style="display:none;"></div>

                <div class="gm-modal-foot">
                    <button type="button" class="btn btn-ghost" data-close-modal><?= e(__('booking_cancel', 'Cancel')) ?></button>
                    <button class="btn btn-primary" type="submit" id="newListingSubmitBtn"><?= e(__('g_submit_review', 'Submit for review')) ?></button>
                </div>
                <p id="newListingReviewHint" class="hint mt-2 mb-0"><?= e(__('g_new_reviewed', 'New listings are reviewed by an admin before going live.')) ?></p>
            </form>
        </div>
    </div>
</div>

<!-- Schedule Management Modal -->
<div class="gm-modal" id="scheduleModal" hidden aria-hidden="true">
    <div class="gm-modal-backdrop" data-close-schedule-modal></div>
    <div class="gm-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="scheduleModalTitle" style="max-width:680px;width:100%;">
        <div class="gm-modal-head">
            <div>
                <h2 id="scheduleModalTitle" style="margin:0;font-size:1.25rem;font-weight:700;">Manage Schedule</h2>
                <p id="scheduleModalSubtitle" class="hint mb-0" style="margin-top:0.25rem;font-size:0.85rem;">Plot the dates and times for this tour or experience.</p>
            </div>
            <button type="button" class="gm-modal-close" data-close-schedule-modal aria-label="Close dialog">&times;</button>
        </div>
        <div class="gm-modal-body" style="padding:1.25rem;max-height:75vh;overflow-y:auto;">
            <div id="scheduleFormError" class="form-alert" role="alert" style="display:none;margin-bottom:1rem;"></div>
            <div id="scheduleFormSuccess" class="status-banner banner-green" role="alert" style="display:none;margin-bottom:1rem;padding:0.65rem 0.9rem;">
                <span class="status-icon">✓</span>
                <span id="scheduleFormSuccessMsg" style="font-weight:600;font-size:0.875rem;"></span>
            </div>

            <!-- Add / Edit Schedule Form -->
            <div style="background:#f8fafc;border:1px solid var(--line, #e2e8f0);border-radius:8px;padding:1rem;margin-bottom:1.25rem;">
                <h4 id="scheduleFormTitle" style="margin:0 0 0.75rem 0;font-size:0.95rem;font-weight:700;color:var(--text, #0f172a);">
                    Add Scheduled Session
                </h4>
                <form id="scheduleModalForm" method="post" action="" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" id="sch_schedule_id" name="schedule_id" value="">
                    <input type="hidden" id="sch_listing_id" name="listing_id" value="">

                    <div class="form-grid-3" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(160px, 1fr));gap:0.75rem;margin-bottom:0.75rem;">
                        <div class="field-row" style="margin-bottom:0;">
                            <label for="sch_date" style="font-size:0.82rem;font-weight:600;display:block;margin-bottom:0.25rem;"><?= e(__('th_date', 'Date')) ?> <span class="req" style="color:var(--coral-600, #dc2626);">*</span></label>
                            <input class="input" type="date" id="sch_date" name="schedule_date" min="<?= date('Y-m-d') ?>" required style="width:100%;font-size:0.875rem;">
                        </div>
                        <div class="field-row" style="margin-bottom:0;">
                            <label for="sch_start_time" style="font-size:0.82rem;font-weight:600;display:block;margin-bottom:0.25rem;"><?= e(__('g_start_time', 'Start time')) ?></label>
                            <input class="input" type="time" id="sch_start_time" name="start_time" style="width:100%;font-size:0.875rem;">
                        </div>
                        <div class="field-row" style="margin-bottom:0;">
                            <label for="sch_end_time" style="font-size:0.82rem;font-weight:600;display:block;margin-bottom:0.25rem;"><?= e(__('g_end_time', 'End time')) ?></label>
                            <input class="input" type="time" id="sch_end_time" name="end_time" style="width:100%;font-size:0.875rem;">
                        </div>
                    </div>

                    <div class="form-grid-2" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(160px, 1fr));gap:0.75rem;margin-bottom:1rem;">
                        <div class="field-row" style="margin-bottom:0;">
                            <label for="sch_capacity" style="font-size:0.82rem;font-weight:600;display:block;margin-bottom:0.25rem;"><?= e(__('g_capacity', 'Slots / capacity (optional)')) ?></label>
                            <input class="input" type="number" id="sch_capacity" name="capacity" min="1" placeholder="<?= e(__('g_capacity_ph', 'e.g. 10')) ?>" style="width:100%;font-size:0.875rem;">
                        </div>
                        <div class="field-row" style="margin-bottom:0;">
                            <label for="sch_note" style="font-size:0.82rem;font-weight:600;display:block;margin-bottom:0.25rem;"><?= e(__('g_note_optional', 'Note (optional)')) ?></label>
                            <input class="input" type="text" id="sch_note" name="note" placeholder="<?= e(__('g_schedule_note_ph', 'e.g. Morning batch, meet at pier')) ?>" style="width:100%;font-size:0.875rem;">
                        </div>
                    </div>

                    <div style="display:flex;gap:0.5rem;align-items:center;justify-content:flex-end;">
                        <button type="button" class="btn btn-ghost btn-sm" id="sch_cancel_edit_btn" style="display:none;" onclick="resetScheduleForm()">Cancel Edit</button>
                        <button type="submit" class="btn btn-primary btn-sm" id="sch_submit_btn"><?= e(__('g_add_to_schedule', 'Add to schedule')) ?></button>
                    </div>
                </form>
            </div>

            <!-- Upcoming Scheduled Sessions Table -->
            <div>
                <h4 style="margin:0 0 0.5rem 0;font-size:0.95rem;font-weight:700;color:var(--text, #0f172a);">
                    Upcoming Scheduled Sessions
                </h4>
                <div id="sch_table_container" style="border:1px solid var(--line, #e2e8f0);border-radius:8px;overflow:hidden;background:#fff;">
                    <div id="sch_loading" style="padding:1.5rem;text-align:center;color:var(--text-muted, #64748b);font-size:0.875rem;">
                        Loading schedule...
                    </div>
                    <table class="table" id="sch_table" style="display:none;width:100%;margin:0;vertical-align:middle;font-size:0.85rem;">
                        <thead>
                            <tr style="background:#f8fafc;">
                                <th><?= e(__('th_date', 'Date')) ?></th>
                                <th><?= e(__('g_time', 'Time')) ?></th>
                                <th><?= e(__('g_capacity_short', 'Slots')) ?></th>
                                <th><?= e(__('g_note', 'Note')) ?></th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="sch_tbody">
                        </tbody>
                    </table>
                    <div id="sch_empty" style="display:none;padding:1.5rem;text-align:center;color:var(--text-muted, #64748b);font-size:0.875rem;">
                        <?= e(__('g_no_schedule', 'No scheduled sessions yet. Add your first one above.')) ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="gm-modal-foot" style="padding:0.75rem 1.25rem;border-top:1px solid var(--line, #e2e8f0);display:flex;justify-content:flex-end;">
            <button type="button" class="btn btn-ghost" data-close-schedule-modal><?= e(__('booking_close', 'Close')) ?></button>
        </div>
    </div>
</div>

<script>
(function () {
    var baseListingsUrl = <?= json_encode(url('/dashboard/listings')) ?>;

    // -------------------------------------------------------------
    // Listing Creation & Editing Modal
    // -------------------------------------------------------------
    var modal = document.getElementById('newListingModal');
    var form = document.getElementById('newListingForm');
    var errorBox = document.getElementById('newListingError');
    var modalTitle = document.getElementById('newListingTitle');
    var modalSubtitle = document.getElementById('newListingSubtitle');
    var submitBtn = document.getElementById('newListingSubmitBtn');
    var reviewHint = document.getElementById('newListingReviewHint');
    var previewWrap = document.getElementById('nl_preview_wrap');
    var previewImg = document.getElementById('nl_preview_img');

    function resolveImageUrl(path) {
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

    window.handleGuideFileChange = function(slot, input) {
        if (!input.files || !input.files[0]) return;
        var file = input.files[0];
        var reader = new FileReader();
        reader.onload = function(e) {
            setGuideSlotPreview(slot, e.target.result);
            var remIn = document.getElementById('gl_removed_' + slot);
            if (remIn) remIn.value = '0';
        };
        reader.readAsDataURL(file);
    };

    window.handleGuideUrlChange = function(slot, val) {
        val = (val || '').trim();
        var remIn = document.getElementById('gl_removed_' + slot);
        if (val) {
            setGuideSlotPreview(slot, val);
            if (remIn) remIn.value = '0';
            if (slot === 0) {
                var cov = document.getElementById('gl_cover_image');
                if (cov) cov.value = val;
            }
        } else {
            var exist = document.getElementById('gl_existing_' + slot);
            if (exist && exist.value) {
                setGuideSlotPreview(slot, exist.value);
            } else {
                clearGuideSlotPreview(slot);
            }
        }
    };

    window.removeGuidePhoto = function(slot) {
        var fileIn = document.getElementById('gl_file_' + slot);
        if (fileIn) fileIn.value = '';
        var urlIn = document.getElementById('gl_url_' + slot);
        if (urlIn) urlIn.value = '';
        var existingIn = document.getElementById('gl_existing_' + slot);
        if (existingIn) existingIn.value = '';
        var remIn = document.getElementById('gl_removed_' + slot);
        if (remIn) remIn.value = '1';
        if (slot === 0) {
            var cov = document.getElementById('gl_cover_image');
            if (cov) cov.value = '';
        }
        clearGuideSlotPreview(slot);
    };

    function setGuideSlotPreview(slot, src) {
        var img = document.getElementById('gl_preview_img_' + slot);
        var ph = document.getElementById('gl_placeholder_' + slot);
        var btn = document.getElementById('gl_remove_btn_' + slot);
        if (img && ph && btn) {
            var resolved = resolveImageUrl(src);
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

    function clearGuideSlotPreview(slot) {
        var img = document.getElementById('gl_preview_img_' + slot);
        var ph = document.getElementById('gl_placeholder_' + slot);
        var btn = document.getElementById('gl_remove_btn_' + slot);
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

    function clearListingError() {
        if (errorBox) {
            errorBox.textContent = '';
            errorBox.style.display = 'none';
        }
    }

    function openModal(e) {
        if (e && typeof e.preventDefault === 'function') { e.preventDefault(); }
        openCreateListingModal();
    }

    function closeModal() {
        if (!modal) return;
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        clearListingError();
    }

    
    window.updateGuidePriceUnitHint = function (val) {
        var hint = document.getElementById('nl_price_unit_hint');
        if (!hint) return;
        if (val === 'per group') {
            hint.textContent = 'Fixed rate for the entire group/tour.';
        } else {
            hint.textContent = 'Rate per individual tourist/guest.';
        }
    };

    window.openCreateListingModal = function () {
        if (!modal) return;
        clearListingError();
        if (modalTitle) modalTitle.textContent = <?= json_encode(__('g_create_listing_title', 'Create a listing')) ?>;
        if (modalSubtitle) modalSubtitle.textContent = 'Fill in the details for your tour or experience.';
        if (submitBtn) submitBtn.textContent = <?= json_encode(__('g_submit_review', 'Submit for review')) ?>;
        if (reviewHint) reviewHint.textContent = <?= json_encode(__('g_new_reviewed', 'New listings are reviewed by an admin before going live.')) ?>;
        if (form) form.action = baseListingsUrl;

        var idEl = document.getElementById('nl_listing_id'); if (idEl) idEl.value = '';
        var tEl = document.getElementById('nl_title'); if (tEl) tEl.value = '';
        var cEl = document.getElementById('nl_category'); if (cEl) cEl.value = '';
        var aEl = document.getElementById('nl_area'); if (aEl) aEl.value = '';
        var sEl = document.getElementById('nl_summary'); if (sEl) sEl.value = '';
        var dEl = document.getElementById('nl_description'); if (dEl) dEl.value = '';
        var incEl = document.getElementById('nl_included'); if (incEl) incEl.value = '';
        var nincEl = document.getElementById('nl_not_included'); if (nincEl) nincEl.value = '';
        var pEl = document.getElementById('nl_price'); if (pEl) pEl.value = '';
        var puEl = document.getElementById('nl_price_unit'); if (puEl) { puEl.value = 'per person'; updateGuidePriceUnitHint('per person'); }
        var capEl = document.getElementById('nl_capacity'); if (capEl) capEl.value = '10';
        var durEl = document.getElementById('nl_duration'); if (durEl) durEl.value = '';
        var addEl = document.getElementById('nl_address'); if (addEl) addEl.value = '';

        for (var i = 0; i < 3; i++) {
            removeGuidePhoto(i);
            var remIn = document.getElementById('gl_removed_' + i);
            if (remIn) remIn.value = '0';
            var existingIn = document.getElementById('gl_existing_' + i);
            if (existingIn) existingIn.value = '';
        }

        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        var first = document.getElementById('nl_title');
        if (first) first.focus();
    };

    window.openEditListingModal = function (listing) {
        if (!modal || !listing) return;
        clearListingError();
        if (modalTitle) modalTitle.textContent = <?= json_encode(__('g_edit_listing_title', 'Edit listing')) ?>;
        if (modalSubtitle) modalSubtitle.textContent = 'Update your tour details, pricing, and itinerary.';
        if (submitBtn) submitBtn.textContent = <?= json_encode(__('g_save_changes', 'Save changes')) ?>;
        if (reviewHint) reviewHint.textContent = 'Updates to listings are saved immediately.';
        if (form) form.action = baseListingsUrl + '/' + listing.id;

        var idEl = document.getElementById('nl_listing_id'); if (idEl) idEl.value = listing.id;
        var tEl = document.getElementById('nl_title'); if (tEl) tEl.value = listing.title || '';
        var cEl = document.getElementById('nl_category'); if (cEl) cEl.value = listing.category_id || '';
        var aEl = document.getElementById('nl_area'); if (aEl) aEl.value = listing.area || '';
        var sEl = document.getElementById('nl_summary'); if (sEl) sEl.value = listing.summary || '';
        var dEl = document.getElementById('nl_description'); if (dEl) dEl.value = listing.description || '';
        var incEl = document.getElementById('nl_included'); if (incEl) incEl.value = listing.included || '';
        var nincEl = document.getElementById('nl_not_included'); if (nincEl) nincEl.value = listing.not_included || '';
        var pEl = document.getElementById('nl_price'); if (pEl) pEl.value = listing.price || '';
        var puEl = document.getElementById('nl_price_unit'); if (puEl) { puEl.value = 'per person'; updateGuidePriceUnitHint('per person'); }
        var capEl = document.getElementById('nl_capacity'); if (capEl) capEl.value = listing.capacity || 10;
        var durEl = document.getElementById('nl_duration'); if (durEl) durEl.value = listing.duration || '';
        var addEl = document.getElementById('nl_address'); if (addEl) addEl.value = listing.address || '';

        for (var i = 0; i < 3; i++) {
            clearGuideSlotPreview(i);
            var remIn = document.getElementById('gl_removed_' + i);
            if (remIn) remIn.value = '0';
            var existingIn = document.getElementById('gl_existing_' + i);
            if (existingIn) existingIn.value = '';
            var fileIn = document.getElementById('gl_file_' + i);
            if (fileIn) fileIn.value = '';
            var urlIn = document.getElementById('gl_url_' + i);
            if (urlIn) urlIn.value = '';
        }

        function populatePhotos(photoList, coverImg, isAsync) {
            var photos = Array.isArray(photoList) && photoList.length > 0
                ? photoList
                : (coverImg ? [coverImg] : []);

            photos.slice(0, 3).forEach(function(src, i) {
                if (!src) return;
                if (isAsync) {
                    var fileIn = document.getElementById('gl_file_' + i);
                    var urlIn = document.getElementById('gl_url_' + i);
                    var remIn = document.getElementById('gl_removed_' + i);
                    if ((fileIn && fileIn.files && fileIn.files.length > 0) || (urlIn && urlIn.value) || (remIn && remIn.value === '1')) {
                        return;
                    }
                }
                var exist = document.getElementById('gl_existing_' + i);
                if (exist) exist.value = src;
                setGuideSlotPreview(i, src);
            });
            if (coverImg || (photos && photos[0])) {
                var cov = document.getElementById('gl_cover_image');
                if (cov && (!isAsync || !cov.value)) cov.value = coverImg || photos[0];
            }
        }

        populatePhotos(listing.photos, listing.cover_image, false);

        if (listing.id) {
            fetch(baseListingsUrl + '/' + listing.id + '/edit', {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(function(res) { return res.ok ? res.json() : null; })
            .then(function(data) {
                if (data && data.listing && Array.isArray(data.listing.photos) && data.listing.photos.length > 0) {
                    populatePhotos(data.listing.photos, data.listing.cover_image, true);
                }
            })
            .catch(function() {});
        }

        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        var first = document.getElementById('nl_title');
        if (first) first.focus();
    };

    window.closeCreateListingModal = closeModal;

    document.querySelectorAll('[data-open-new-listing]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            var gatingModal = document.getElementById('verificationGatingModal');
            if (gatingModal && gatingModal.dataset.userVerified !== '1') {
                return;
            }
            e.preventDefault();
            openCreateListingModal();
        });
    });
    if (modal) {
        modal.querySelectorAll('[data-close-modal]').forEach(function (btn) {
            btn.addEventListener('click', closeModal);
        });
    }
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal && !modal.hidden) { closeModal(); }
    });

    if (form) {
        var required = [
            { id: 'nl_title', label: 'Title' },
            { id: 'nl_category', label: 'Category' },
            { id: 'nl_area', label: 'Area' },
            { id: 'nl_description', label: 'Description' },
            { id: 'nl_price', label: 'Price' },
            { id: 'nl_capacity', label: 'Slots / Capacity' }
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
    <?php if ($openModal): ?>openCreateListingModal();<?php endif; ?>

    // Auto-open modal if URL has ?create=1
    try {
        var params = new URLSearchParams(window.location.search);
        if (params.get('create') === '1') {
            openCreateListingModal();
            params.delete('create');
            var cleanSearch = params.toString() ? ('?' + params.toString()) : '';
            window.history.replaceState({}, document.title, window.location.pathname + cleanSearch + window.location.hash);
        }
    } catch (err) {}

    // -------------------------------------------------------------
    // Schedule Management Modal
    // -------------------------------------------------------------
    var schedModal = document.getElementById('scheduleModal');
    var schedForm = document.getElementById('scheduleModalForm');
    var schedError = document.getElementById('scheduleFormError');
    var schedSuccess = document.getElementById('scheduleFormSuccess');
    var schedSuccessMsg = document.getElementById('scheduleFormSuccessMsg');
    var schedLoading = document.getElementById('sch_loading');
    var schedTable = document.getElementById('sch_table');
    var schedTbody = document.getElementById('sch_tbody');
    var schedEmpty = document.getElementById('sch_empty');
    var currentListingId = null;

    function showSchedError(msg) {
        if (schedError) {
            schedError.textContent = msg;
            schedError.style.display = 'block';
            schedError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
    function clearSchedMessages() {
        if (schedError) schedError.style.display = 'none';
        if (schedSuccess) schedSuccess.style.display = 'none';
    }

    window.resetScheduleForm = function () {
        var sid = document.getElementById('sch_schedule_id'); if (sid) sid.value = '';
        var dt = document.getElementById('sch_date'); if (dt) dt.value = '';
        var st = document.getElementById('sch_start_time'); if (st) st.value = '';
        var et = document.getElementById('sch_end_time'); if (et) et.value = '';
        var cap = document.getElementById('sch_capacity'); if (cap) cap.value = '';
        var nt = document.getElementById('sch_note'); if (nt) nt.value = '';
        var ft = document.getElementById('scheduleFormTitle'); if (ft) ft.textContent = 'Add Scheduled Session';
        var sbtn = document.getElementById('sch_submit_btn'); if (sbtn) sbtn.textContent = <?= json_encode(__('g_add_to_schedule', 'Add to schedule')) ?>;
        var cbtn = document.getElementById('sch_cancel_edit_btn'); if (cbtn) cbtn.style.display = 'none';
        if (schedForm && currentListingId) {
            schedForm.action = baseListingsUrl + '/' + currentListingId + '/schedule/add';
        }
        clearSchedMessages();
    };

    function formatTime(timeStr) {
        if (!timeStr) return '';
        var parts = timeStr.split(':');
        var h = parseInt(parts[0], 10);
        var m = parts[1] || '00';
        if (isNaN(h)) return timeStr;
        var ampm = h >= 12 ? 'pm' : 'am';
        h = h % 12;
        h = h ? h : 12;
        return h + ':' + m + ' ' + ampm;
    }

    function renderScheduleTable(items) {
        if (schedLoading) schedLoading.style.display = 'none';
        if (!items || items.length === 0) {
            if (schedTable) schedTable.style.display = 'none';
            if (schedEmpty) schedEmpty.style.display = 'block';
            return;
        }
        if (schedEmpty) schedEmpty.style.display = 'none';
        if (schedTable) schedTable.style.display = 'table';
        if (!schedTbody) return;

        schedTbody.innerHTML = '';
        items.forEach(function (s) {
            var tr = document.createElement('tr');

            // Date
            var tdDate = document.createElement('td');
            tdDate.style.fontWeight = '600';
            tdDate.textContent = s.schedule_date;
            tr.appendChild(tdDate);

            // Time
            var tdTime = document.createElement('td');
            var timeLabel = '—';
            if (s.start_time && s.end_time) {
                timeLabel = formatTime(s.start_time) + ' – ' + formatTime(s.end_time);
            } else if (s.start_time) {
                timeLabel = formatTime(s.start_time);
            }
            tdTime.textContent = timeLabel;
            tr.appendChild(tdTime);

            // Slots
            var tdSlots = document.createElement('td');
            tdSlots.textContent = (s.capacity !== null && s.capacity !== undefined && s.capacity !== '') ? s.capacity : '—';
            tr.appendChild(tdSlots);

            // Note
            var tdNote = document.createElement('td');
            tdNote.textContent = s.note || '—';
            tr.appendChild(tdNote);

            // Actions
            var tdAct = document.createElement('td');
            tdAct.style.textAlign = 'right';
            tdAct.style.whiteSpace = 'nowrap';

            var editBtn = document.createElement('button');
            editBtn.type = 'button';
            editBtn.className = 'btn btn-ghost btn-sm';
            editBtn.style.padding = '3px 8px';
            editBtn.style.fontSize = '0.78rem';
            editBtn.textContent = 'Edit';
            editBtn.addEventListener('click', function () {
                document.getElementById('sch_schedule_id').value = s.id;
                document.getElementById('sch_date').value = s.schedule_date || '';
                document.getElementById('sch_start_time').value = s.start_time || '';
                document.getElementById('sch_end_time').value = s.end_time || '';
                document.getElementById('sch_capacity').value = (s.capacity !== null && s.capacity !== undefined) ? s.capacity : '';
                document.getElementById('sch_note').value = s.note || '';
                document.getElementById('scheduleFormTitle').textContent = 'Edit Scheduled Session';
                document.getElementById('sch_submit_btn').textContent = 'Update schedule';
                document.getElementById('sch_cancel_edit_btn').style.display = 'inline-block';
                if (schedForm && currentListingId) {
                    schedForm.action = baseListingsUrl + '/' + currentListingId + '/schedule/' + s.id + '/update';
                }
                clearSchedMessages();
                document.getElementById('sch_date').focus();
            });
            tdAct.appendChild(editBtn);

            var removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'btn btn-ghost btn-sm';
            removeBtn.style.padding = '3px 8px';
            removeBtn.style.fontSize = '0.78rem';
            removeBtn.style.color = 'var(--coral-600, #dc2626)';
            removeBtn.style.marginLeft = '4px';
            removeBtn.textContent = 'Remove';
            removeBtn.addEventListener('click', function () {
                if (!confirm('Remove this schedule entry?')) return;
                var csrfInput = schedForm ? schedForm.querySelector('input[name="_token"]') : null;
                var csrfToken = csrfInput ? csrfInput.value : '';
                var fd = new FormData();
                fd.append('_token', csrfToken);
                fd.append('schedule_id', s.id);

                fetch(baseListingsUrl + '/' + currentListingId + '/schedule/remove', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: fd
                })
                .then(function (r) {
                    if (!r.ok) {
                        return r.json().then(function (err) { throw err; }, function () {
                            throw new Error('Failed to remove schedule entry (HTTP ' + r.status + ').');
                        });
                    }
                    return r.json();
                })
                .then(function (d) {
                    if (d.success) {
                        renderScheduleTable(d.schedule || []);
                        if (schedSuccess && schedSuccessMsg) {
                            schedSuccessMsg.textContent = d.message || 'Schedule entry removed.';
                            schedSuccess.style.display = 'flex';
                        }
                    } else if (d.error) {
                        showSchedError(d.error);
                    }
                })
                .catch(function (err) {
                    showSchedError((err && err.error) ? err.error : (err && err.message ? err.message : 'Failed to remove schedule entry.'));
                });
            });
            tdAct.appendChild(removeBtn);

            tr.appendChild(tdAct);
            schedTbody.appendChild(tr);
        });
    }

    window.openScheduleModal = function (listingId, listingTitle) {
        if (!schedModal) return;
        currentListingId = listingId;
        clearSchedMessages();
        resetScheduleForm();

        document.getElementById('sch_listing_id').value = listingId;
        document.getElementById('scheduleModalTitle').textContent = 'Manage Schedule';
        document.getElementById('scheduleModalSubtitle').textContent = 'Schedule sessions for "' + listingTitle + '".';
        if (schedForm) {
            schedForm.action = baseListingsUrl + '/' + listingId + '/schedule/add';
        }

        if (schedLoading) schedLoading.style.display = 'block';
        if (schedTable) schedTable.style.display = 'none';
        if (schedEmpty) schedEmpty.style.display = 'none';

        schedModal.hidden = false;
        schedModal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';

        fetch(baseListingsUrl + '/' + listingId + '/schedule', {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) {
            if (!r.ok) {
                return r.json().then(function (err) { throw err; }, function () {
                    throw new Error('Failed to load schedule (HTTP ' + r.status + ').');
                });
            }
            return r.json();
        })
        .then(function (d) {
            renderScheduleTable(d.schedule || []);
        })
        .catch(function (err) {
            if (schedLoading) schedLoading.style.display = 'none';
            showSchedError((err && err.error) ? err.error : (err && err.message ? err.message : 'Failed to load schedule.'));
        });
    };

    window.closeScheduleModal = function () {
        if (!schedModal) return;
        schedModal.hidden = true;
        schedModal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        clearSchedMessages();
        resetScheduleForm();
    };

    if (schedModal) {
        schedModal.querySelectorAll('[data-close-schedule-modal]').forEach(function (b) {
            b.addEventListener('click', closeScheduleModal);
        });
    }
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && schedModal && !schedModal.hidden) {
            closeScheduleModal();
        }
    });

    if (schedForm) {
        schedForm.addEventListener('submit', function (e) {
            clearSchedMessages();
            var dateVal = (document.getElementById('sch_date').value || '').trim();
            var startTime = (document.getElementById('sch_start_time').value || '').trim();
            var endTime = (document.getElementById('sch_end_time').value || '').trim();

            if (!dateVal) {
                e.preventDefault();
                showSchedError('Please select a valid date.');
                document.getElementById('sch_date').focus();
                return;
            }

            if (startTime && endTime) {
                if (endTime <= startTime) {
                    e.preventDefault();
                    showSchedError('End time must be later than the start time.');
                    document.getElementById('sch_end_time').focus();
                    return;
                }
            }

            // Submit via AJAX for seamless modal experience
            e.preventDefault();
            var fd = new FormData(schedForm);
            var sbtn = document.getElementById('sch_submit_btn');
            if (sbtn) sbtn.disabled = true;

            fetch(schedForm.action, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: fd
            })
            .then(function (r) {
                if (!r.ok) {
                    return r.json().then(function (err) { throw err; }, function () {
                        throw new Error('Failed to save schedule (HTTP ' + r.status + ').');
                    });
                }
                return r.json();
            })
            .then(function (d) {
                if (sbtn) sbtn.disabled = false;
                if (d.success) {
                    renderScheduleTable(d.schedule || []);
                    resetScheduleForm();
                    if (schedSuccess && schedSuccessMsg) {
                        schedSuccessMsg.textContent = d.message || 'Schedule saved.';
                        schedSuccess.style.display = 'flex';
                    }
                } else if (d.error) {
                    showSchedError(d.error);
                }
            })
            .catch(function (err) {
                if (sbtn) sbtn.disabled = false;
                showSchedError((err && err.error) ? err.error : (err && err.message ? err.message : 'Failed to save schedule. Please check your input.'));
            });
        });
    }
})();
</script>
<?php else: ?>
<script>
(function () {
    try {
        var params = new URLSearchParams(window.location.search);
        if (params.get('create') === '1') {
            params.delete('create');
            var cleanSearch = params.toString() ? ('?' + params.toString()) : '';
            window.history.replaceState({}, document.title, window.location.pathname + cleanSearch + window.location.hash);
            var btn = document.querySelector('[data-open-new-listing]');
            if (btn) {
                btn.click();
            }
        }
    } catch (err) {}
})();
</script>
<?php endif; ?>
