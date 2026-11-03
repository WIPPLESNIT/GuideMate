<?php
/**
 * Admin Manage Listings (All Live & Approved Listings)
 *
 * Exclusively displays active live listings across Hotels, Attractions, and Things to Do.
 * Matches the filter control and search bar design from the Users section.
 *
 * @var array<int,array<string,mixed>>|null $listings
 * @var array<int,array<string,mixed>>|null $all
 * @var string|null $category
 * @var string|null $q
 * @var array<string,int>|null $categoryCounts
 * @var int|null $pendingGuideCount
 * @var int|null $pendingHotelsCount
 */

$items = $listings ?? $all ?? [];
$category = $category ?? 'all';
$q = (string) ($q ?? '');
$categoryCounts = $categoryCounts ?? \App\Models\Listing::countLiveByCategory();
$pendingGuideCount = $pendingGuideCount ?? \App\Models\Listing::pendingGuideListingsCount();
$pendingHotelsCount = $pendingHotelsCount ?? \App\Models\Listing::pendingHotelsCount();

// Normalize initial selected category
$validCategories = ['all', 'attractions', 'things-to-do', 'hotels'];
$selectedCategory = in_array($category, $validCategories, true) ? $category : 'all';

$categoryLabels = [
    'all' => 'All Live Listings',
    'attractions' => 'Attractions',
    'things-to-do' => 'Things to Do',
    'hotels' => 'Hotel & Stays',
];

$activeLabel = $categoryLabels[$selectedCategory] ?? 'All Live Listings';
$isFiltered = ($selectedCategory !== 'all');
?>

<div class="section-head" style="margin-bottom:1.25rem;">
    <div>
        <h1 class="mb-0">Manage listings</h1>
        <p class="hint mb-0">Manage all live and approved listings across Hotels, Attractions, and Things to Do.</p>
    </div>
</div>

<!-- Filter Toolbar matching the Users section reference -->
<div class="users-toolbar listings-toolbar" style="margin-bottom:1.25rem;">
    <div class="users-filters-group">
        <!-- Search Field (left side of the filter, matching User section) -->
        <div class="filter-control users-search-wrap">
            <span class="filter-icon" aria-hidden="true">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
            </span>
            <input type="text"
                   id="listingSearchInput"
                   class="filter-input users-search-input"
                   placeholder="Search"
                   value="<?= e($q) ?>"
                   aria-label="Search listings"
                   autocomplete="off"
                   spellcheck="false">
            <button type="button" id="listingSearchClear" class="users-search-clear" aria-label="Clear search" <?= $q === '' ? 'hidden' : '' ?>>&times;</button>
        </div>

        <!-- Category Custom Filter Dropdown -->
        <div class="filter-control filter-custom-dropdown" data-filter="category" id="listingFilterControl">
            <button type="button"
                    class="filter-dropdown-btn <?= $isFiltered ? 'is-filtered' : '' ?>"
                    id="listingFilterBtn"
                    aria-haspopup="listbox"
                    aria-expanded="false"
                    aria-controls="listingFilterPopover"
                    aria-label="Filter listings by category">
                <span class="filter-icon" aria-hidden="true">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect width="7" height="7" x="3" y="3" rx="1"></rect>
                        <rect width="7" height="7" x="14" y="3" rx="1"></rect>
                        <rect width="7" height="7" x="14" y="14" rx="1"></rect>
                        <rect width="7" height="7" x="3" y="14" rx="1"></rect>
                    </svg>
                </span>
                <span class="filter-dropdown-label" id="listingFilterLabel"><?= e($activeLabel) ?></span>
                <span class="filter-chevron" aria-hidden="true">▾</span>
            </button>
            <div class="filter-dropdown-popover" id="listingFilterPopover" role="listbox" aria-labelledby="listingFilterBtn" hidden>
                <div class="filter-popover-head">
                    <span class="filter-popover-title">Filter by Category</span>
                    <button type="button" class="filter-popover-reset" id="listingFilterReset">Reset</button>
                </div>
                <div class="filter-popover-options">
                    <div class="filter-popover-item <?= $selectedCategory === 'all' ? 'is-selected' : '' ?>"
                         role="option"
                         aria-selected="<?= $selectedCategory === 'all' ? 'true' : 'false' ?>"
                         data-value="all"
                         tabindex="<?= $selectedCategory === 'all' ? '0' : '-1' ?>">
                        <span class="filter-item-indicator" aria-hidden="true"></span>
                        <span class="filter-item-text">All Live Listings</span>
                        <span class="filter-item-count"><?= (int) ($categoryCounts['all'] ?? count($items)) ?></span>
                    </div>
                    <div class="filter-popover-item <?= $selectedCategory === 'attractions' ? 'is-selected' : '' ?>"
                         role="option"
                         aria-selected="<?= $selectedCategory === 'attractions' ? 'true' : 'false' ?>"
                         data-value="attractions"
                         tabindex="<?= $selectedCategory === 'attractions' ? '0' : '-1' ?>">
                        <span class="filter-item-indicator" aria-hidden="true"></span>
                        <span class="filter-item-text">Attractions</span>
                        <span class="filter-item-count"><?= (int) ($categoryCounts['attractions'] ?? 0) ?></span>
                    </div>
                    <div class="filter-popover-item <?= $selectedCategory === 'things-to-do' ? 'is-selected' : '' ?>"
                         role="option"
                         aria-selected="<?= $selectedCategory === 'things-to-do' ? 'true' : 'false' ?>"
                         data-value="things-to-do"
                         tabindex="<?= $selectedCategory === 'things-to-do' ? '0' : '-1' ?>">
                        <span class="filter-item-indicator" aria-hidden="true"></span>
                        <span class="filter-item-text">Things to Do</span>
                        <span class="filter-item-count"><?= (int) ($categoryCounts['things-to-do'] ?? 0) ?></span>
                    </div>
                    <div class="filter-popover-item <?= $selectedCategory === 'hotels' ? 'is-selected' : '' ?>"
                         role="option"
                         aria-selected="<?= $selectedCategory === 'hotels' ? 'true' : 'false' ?>"
                         data-value="hotels"
                         tabindex="<?= $selectedCategory === 'hotels' ? '0' : '-1' ?>">
                        <span class="filter-item-indicator" aria-hidden="true"></span>
                        <span class="filter-item-text">Hotel & Stays</span>
                        <span class="filter-item-count"><?= (int) ($categoryCounts['hotels'] ?? 0) ?></span>
                    </div>
                </div>
            </div>
            <!-- Synchronized backing select for accessibility and automated testing -->
            <select id="listingCategoryFilter" class="filter-select filter-select-hidden" aria-hidden="true" tabindex="-1" style="display:none;">
                <option value="all" <?= $selectedCategory === 'all' ? 'selected' : '' ?>>All Live Listings</option>
                <option value="attractions" <?= $selectedCategory === 'attractions' ? 'selected' : '' ?>>Attractions</option>
                <option value="things-to-do" <?= $selectedCategory === 'things-to-do' ? 'selected' : '' ?>>Things to Do</option>
                <option value="hotels" <?= $selectedCategory === 'hotels' ? 'selected' : '' ?>>Hotel & Stays</option>
            </select>
        </div>
    </div>
</div>

<div class="panel">
    <div class="panel-body vehicles-table-wrap" style="padding:0;">
        <?php if ($items === []): ?>
            <div class="empty-state" style="padding:3rem 1rem;">
                <div class="big">📋</div>
                <h3>No live listings found</h3>
                <p>There are no live approved listings matching your selection.</p>
            </div>
        <?php else: ?>
            <table class="table table-vehicles table-hotels table-admin-listings" id="adminListingsTable">
                <thead>
                    <tr>
                        <th class="col-vehicle">Listing</th>
                        <th class="col-partner">Host / Partner</th>
                        <th class="col-type">Category & Rate</th>
                        <th class="col-status">Status / Rating</th>
                        <th class="col-audit">Published</th>
                        <th class="col-actions" style="text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $initialVisibleCount = 0;
                foreach ($items as $l):
                    $thumb = !empty($l['cover_image']) ? $l['cover_image'] : 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=400&q=80';
                    $catSlug = (string) ($l['category_slug'] ?? '');
                    if ($catSlug === 'hotels') {
                        $catGroup = 'hotels';
                    } elseif ($catSlug === 'attractions' || $catSlug === 'attraction-and-tours') {
                        $catGroup = 'attractions';
                    } else {
                        $catGroup = 'things-to-do';
                    }

                    $isHotel = ($catGroup === 'hotels');
                    $isAttraction = ($catGroup === 'attractions');
                    $isThingsToDo = ($catGroup === 'things-to-do');
                    $isFeatured = (int) ($l['is_featured'] ?? 0) === 1;
                    $roomCount = (int) ($l['room_count'] ?? 0);
                    $schedCount = (int) ($l['schedule_count'] ?? 0);
                    $reviewCount = (int) ($l['review_count'] ?? 0);
                    $avgRating = (float) ($l['avg_rating'] ?? 0);

                    $titleLower = strtolower((string) ($l['title'] ?? ''));
                    $hostLower = strtolower((string) (($l['owner_name'] ?? '') . ' ' . ($l['owner_email'] ?? '')));
                    $areaLower = strtolower((string) ($l['area'] ?? ''));

                    $rowMatchesCat = ($selectedCategory === 'all' || $catGroup === $selectedCategory);
                    $rowMatchesSearch = true;
                    if ($q !== '') {
                        $qLower = strtolower($q);
                        $rowMatchesSearch = (str_contains($titleLower, $qLower) || str_contains($hostLower, $qLower) || str_contains($areaLower, $qLower));
                    }
                    $rowMatches = ($rowMatchesCat && $rowMatchesSearch);
                    if ($rowMatches) {
                        $initialVisibleCount++;
                    }
                ?>
                    <tr id="listing-row-<?= (int) $l['id'] ?>"
                        data-id="<?= (int) $l['id'] ?>"
                        data-category="<?= e($catGroup) ?>"
                        data-title="<?= e($titleLower) ?>"
                        data-host="<?= e($hostLower) ?>"
                        data-area="<?= e($areaLower) ?>"
                        class="listing-table-row"
                        <?= $rowMatches ? '' : 'hidden' ?>>
                        <td class="vehicle-cell col-vehicle" data-label="Listing">
                            <div class="vehicle-meta-wrap">
                                <img src="<?= e(img_src($thumb, 'listing-' . $l['id'])) ?>" alt="" class="vehicle-thumb" style="width:64px;height:48px;border-radius:6px;object-fit:cover;">
                                <div class="vehicle-info">
                                    <a href="<?= e(url('/listing/' . $l['slug'])) ?>" target="_blank" title="View public page"><strong class="vehicle-title"><?= e($l['title']) ?></strong></a>
                                    <div class="hint" style="font-size:0.75rem;margin-top:2px;"><?= e($l['area'] ?? '') ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="col-partner" data-label="Host / Partner">
                            <strong><?= e($l['owner_name'] ?? 'Partner') ?></strong>
                            <div class="hint" style="font-size:0.8rem;word-break:break-all;"><?= e($l['owner_email'] ?? '') ?></div>
                        </td>
                        <td class="col-type" data-label="Category & Rate">
                            <div style="margin-bottom:4px;">
                                <?php if ($isHotel): ?>
                                    <span class="pill" style="font-size:0.72rem;padding:2px 7px;background:rgba(59,130,246,0.15);color:#60a5fa;border:1px solid rgba(59,130,246,0.3);font-weight:600;border-radius:20px;">
                                        Hotels & Stays
                                    </span>
                                <?php elseif ($isAttraction): ?>
                                    <span class="pill" style="font-size:0.72rem;padding:2px 7px;background:rgba(236,72,153,0.15);color:#f472b6;border:1px solid rgba(236,72,153,0.3);font-weight:600;border-radius:20px;">
                                        Attraction
                                    </span>
                                <?php else: ?>
                                    <span class="pill" style="font-size:0.72rem;padding:2px 7px;background:rgba(16,185,129,0.15);color:#34d399;border:1px solid rgba(16,185,129,0.3);font-weight:600;border-radius:20px;">
                                        Things to Do
                                    </span>
                                <?php endif; ?>
                            </div>
                            <?php if ((float) ($l['price'] ?? 0) > 0): ?>
                                <strong><?= money((float) $l['price']) ?></strong>
                                <small class="hint">/ <?= e($l['price_unit'] ?? ($isHotel ? 'night' : 'person')) ?></small>
                            <?php else: ?>
                                <strong style="color:var(--brand, #10B981);"><?= $isHotel ? 'Contact' : 'Free' ?></strong>
                            <?php endif; ?>
                            <?php if ($isHotel && $roomCount > 0): ?>
                                <div class="hint" style="font-size:0.75rem;margin-top:2px;"><?= $roomCount ?> <?= $roomCount === 1 ? 'room' : 'rooms' ?></div>
                            <?php elseif (!$isHotel && $schedCount > 0): ?>
                                <div class="hint" style="font-size:0.75rem;margin-top:2px;"><?= $schedCount ?> <?= $schedCount === 1 ? 'session' : 'sessions' ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="col-status" data-label="Status / Rating">
                            <div style="display:flex;flex-direction:column;gap:4px;">
                                <div>
                                    <span class="pill pill-approved" style="font-size:0.72rem;padding:2px 7px;">Live</span>
                                    <?php if ($isFeatured): ?>
                                        <span class="pill" style="font-size:0.72rem;padding:2px 7px;background:rgba(245,158,11,0.15);color:#fbbf24;border:1px solid rgba(245,158,11,0.3);font-weight:600;margin-left:2px;">
                                            ⭐ Featured
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div style="font-size:0.8rem;">
                                    <?php if ($reviewCount > 0): ?>
                                        <span style="color:#fbbf24;font-weight:600;"><?= number_format($avgRating, 1) ?> ★</span>
                                        <span class="hint" style="font-size:0.75rem;">(<?= $reviewCount ?>)</span>
                                    <?php else: ?>
                                        <span class="hint" style="font-size:0.75rem;">No reviews yet</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td class="col-audit" data-label="Published" style="font-size:0.8rem;">
                            <?php if (!empty($l['reviewed_at'])): ?>
                                <div><span class="hint">Approved:</span> <?= e(date('M j, Y', strtotime((string) $l['reviewed_at']))) ?></div>
                            <?php elseif (!empty($l['submitted_at'])): ?>
                                <div><span class="hint">Submitted:</span> <?= e(date('M j, Y', strtotime((string) $l['submitted_at']))) ?></div>
                            <?php else: ?>
                                <div><span class="hint">Created:</span> <?= e(date('M j, Y', strtotime((string) $l['created_at']))) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="vehicle-actions-cell col-actions" data-label="Actions" style="text-align:right;vertical-align:middle;">
                            <div class="vehicle-actions-vertical">
                                <a href="<?= e(url('/listing/' . $l['slug'])) ?>" target="_blank" class="btn btn-ghost btn-sm btn-action-vertical">View</a>
                                <form method="post" action="<?= e(url('/admin/listings/' . (int) $l['id'] . '/feature')) ?>" class="vehicle-action-form">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-ghost btn-sm btn-action-vertical">
                                        <?= $isFeatured ? 'Unfeature' : 'Feature' ?>
                                    </button>
                                </form>
                                <form method="post" action="<?= e(url('/admin/listings/' . (int) $l['id'] . '/status')) ?>" class="vehicle-action-form" onsubmit="return confirm('Unpublish this listing? It will be removed from public view.');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="status" value="rejected">
                                    <button type="submit" class="btn btn-ghost btn-sm btn-action-vertical" style="color:var(--coral-600, #ef4444);">
                                        Unpublish
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                    <tr id="listingFilterEmptyRow" <?= $initialVisibleCount === 0 ? '' : 'style="display:none;"' ?>>
                        <td colspan="6" style="padding:0;">
                            <div class="empty-state" style="padding:3rem 1rem;">
                                <div class="big">📋</div>
                                <h3>No live listings found</h3>
                                <p>There are no live approved listings matching your selection.</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var searchInput = document.getElementById('listingSearchInput');
    var searchClear = document.getElementById('listingSearchClear');
    var btn = document.getElementById('listingFilterBtn');
    var pop = document.getElementById('listingFilterPopover');
    var labelEl = document.getElementById('listingFilterLabel');
    var resetBtn = document.getElementById('listingFilterReset');
    var selectEl = document.getElementById('listingCategoryFilter');
    var rows = Array.prototype.slice.call(document.querySelectorAll('.listing-table-row'));
    var emptyRow = document.getElementById('listingFilterEmptyRow');

    var currentCategory = '<?= e($selectedCategory) ?>';

    function closePopover() {
        if (!pop) return;
        pop.hidden = true;
        pop.classList.remove('open');
        if (btn) btn.setAttribute('aria-expanded', 'false');
    }

    function openPopover() {
        if (!pop) return;
        pop.hidden = false;
        pop.classList.add('open');
        if (btn) btn.setAttribute('aria-expanded', 'true');
        var sel = pop.querySelector('.filter-popover-item.is-selected');
        if (sel) sel.focus();
    }

    function applyFilters() {
        var q = searchInput ? searchInput.value.trim().toLowerCase() : '';
        if (searchClear) {
            searchClear.hidden = (q.length === 0);
        }

        var visibleCount = 0;
        rows.forEach(function (row) {
            var cat = row.getAttribute('data-category') || '';
            var matchesCat = (currentCategory === 'all') || (cat === currentCategory);

            var matchesSearch = true;
            if (q !== '') {
                var title = row.getAttribute('data-title') || '';
                var host = row.getAttribute('data-host') || '';
                var area = row.getAttribute('data-area') || '';
                matchesSearch = (title.indexOf(q) !== -1 || host.indexOf(q) !== -1 || area.indexOf(q) !== -1);
            }

            var show = matchesCat && matchesSearch;
            row.hidden = !show;
            if (show) visibleCount++;
        });

        if (emptyRow) {
            emptyRow.style.display = (visibleCount === 0) ? '' : 'none';
        }

        try {
            var newUrl = new URL(window.location.href);
            if (currentCategory === 'all') {
                newUrl.searchParams.delete('category');
            } else {
                newUrl.searchParams.set('category', currentCategory);
            }
            if (q === '') {
                newUrl.searchParams.delete('q');
            } else {
                newUrl.searchParams.set('q', q);
            }
            window.history.replaceState({}, '', newUrl.toString());
        } catch (err) {}
    }

    if (searchInput) {
        searchInput.addEventListener('input', applyFilters);
    }

    if (searchClear) {
        searchClear.addEventListener('click', function () {
            if (searchInput) {
                searchInput.value = '';
                searchInput.focus();
            }
            applyFilters();
        });
    }

    if (btn && pop) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            var isOpen = pop.classList.contains('open');
            if (isOpen) {
                closePopover();
            } else {
                openPopover();
            }
        });

        document.addEventListener('click', function (e) {
            if (!pop.contains(e.target) && e.target !== btn && !btn.contains(e.target)) {
                closePopover();
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && pop.classList.contains('open')) {
                closePopover();
                btn.focus();
            }
        });

        pop.querySelectorAll('.filter-popover-item').forEach(function (item) {
            item.addEventListener('click', function (e) {
                e.stopPropagation();
                var val = item.getAttribute('data-value') || 'all';
                var textEl = item.querySelector('.filter-item-text');
                var text = textEl ? textEl.textContent.trim() : val;
                
                currentCategory = val;
                var isFiltered = (val !== 'all');
                btn.classList.toggle('is-filtered', isFiltered);
                if (labelEl) {
                    labelEl.textContent = text || (val === 'all' ? 'All Live Listings' : val);
                }

                pop.querySelectorAll('.filter-popover-item').forEach(function (el) {
                    var isCur = (el.getAttribute('data-value') === val);
                    el.classList.toggle('is-selected', isCur);
                    el.setAttribute('aria-selected', isCur ? 'true' : 'false');
                    el.setAttribute('tabindex', isCur ? '0' : '-1');
                });

                if (selectEl) {
                    selectEl.value = val;
                }

                applyFilters();
                closePopover();
                btn.focus();
            });

            item.addEventListener('keydown', function (e) {
                var items = Array.prototype.slice.call(pop.querySelectorAll('.filter-popover-item'));
                var idx = items.indexOf(item);
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    var next = items[(idx + 1) % items.length];
                    if (next) next.focus();
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    var prev = items[(idx - 1 + items.length) % items.length];
                    if (prev) prev.focus();
                } else if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    item.click();
                }
            });
        });

        if (resetBtn) {
            resetBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                var allItem = pop.querySelector('.filter-popover-item[data-value="all"]');
                if (allItem) {
                    allItem.click();
                } else {
                    currentCategory = 'all';
                    btn.classList.remove('is-filtered');
                    if (labelEl) labelEl.textContent = 'All Live Listings';
                    applyFilters();
                    closePopover();
                    btn.focus();
                }
            });
        }
    }
});
</script>
