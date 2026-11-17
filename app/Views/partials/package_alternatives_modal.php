<!-- Package Cancellation / Alternatives Review Modal (Host Only) -->
<div id="pkgAlternativesModal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.7);z-index:9999;align-items:center;justify-content:center;padding:16px;">
    <div style="background:#FFFFFF;border-radius:16px;max-width:680px;width:100%;max-height:90vh;overflow:hidden;box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);display:flex;flex-direction:column;">
        <!-- Modal Header -->
        <div style="display:flex;align-items:center;justify-content:space-between;padding:18px 24px;border-bottom:1px solid #E2E8F0;background:#F8FAFC;">
            <div>
                <h3 style="margin:0;font-size:1.15rem;font-weight:800;color:#0F172A;">Manage Service Disruption</h3>
                <span id="pkgModalRefLabel" style="font-size:0.8rem;color:#64748B;font-weight:600;"></span>
            </div>
            <button type="button" onclick="closePackageOptionsModal()" style="border:none;background:transparent;cursor:pointer;font-size:1.4rem;color:#64748B;line-height:1;">&times;</button>
        </div>

        <!-- Modal Body -->
        <div style="padding:20px 24px;max-height:calc(85vh - 120px);overflow-y:auto;">
            <!-- Cancellation Alert Banner -->
            <div id="pkgCancellationNoticeBox" style="background:#FFFBEB;border:1px solid #FCD34D;border-radius:10px;padding:14px 16px;margin-bottom:20px;">
                <div style="display:flex;align-items:flex-start;gap:10px;">
                    <span style="font-size:1.3rem;line-height:1;">⚠️</span>
                    <div style="flex:1;">
                        <div id="pkgCancelledCategoryBadge" style="display:inline-block;padding:2px 8px;border-radius:6px;background:#FDE68A;color:#92400E;font-size:0.75rem;font-weight:800;text-transform:uppercase;margin-bottom:4px;"></div>
                        <div id="pkgCancelledProviderTitle" style="font-weight:800;color:#92400E;font-size:0.95rem;margin-bottom:4px;"></div>
                        <div id="pkgCancelledReason" style="font-size:0.85rem;color:#78350F;line-height:1.4;"></div>
                        <div id="pkgCancelledSchedule" style="font-size:0.8rem;color:#B45309;font-weight:600;margin-top:6px;"></div>
                    </div>
                </div>
                <div style="margin-top:10px;padding-top:8px;border-top:1px dashed #FDE68A;font-size:0.78rem;color:#92400E;display:flex;align-items:center;gap:6px;">
                    <span>✓</span> <strong>Status Protection:</strong> Other confirmed service providers remain confirmed and active.
                </div>
            </div>

            <!-- Explanatory Text for Host -->
            <p style="font-size:0.88rem;color:#334155;margin-bottom:16px;line-height:1.4;">
                As the package organizer, choose whether to <strong>replace</strong> the cancelled provider with an available service in the same category, or <strong>reschedule</strong> the service for an alternative date.
            </p>

            <!-- Host Options (A and B Only) -->
            <div style="display:flex;flex-direction:column;gap:14px;">
                <!-- Option A: Replace (FIRST) -->
                <div class="pkg-option-card" style="border:1.5px solid #E2E8F0;border-radius:12px;padding:16px;background:#FFFFFF;">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
                        <div style="display:flex;align-items:center;gap:10px;">
                            <span style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:50%;background:#ECFDF5;color:#059669;font-weight:800;font-size:0.85rem;">A</span>
                            <div>
                                <h4 style="margin:0;font-size:0.95rem;font-weight:700;color:#0F172A;">Option A: Replace the Cancelled Service</h4>
                                <small style="color:#64748B;">Find an alternative service provider in the same category without disrupting other bookings.</small>
                            </div>
                        </div>
                        <button type="button" id="btnToggleReplace" onclick="toggleOptionSection('replace')" class="btn btn-sm btn-primary" style="font-weight:700;white-space:nowrap;">
                            Browse Replacements
                        </button>
                    </div>
                    <!-- Replacement List Container -->
                    <div id="pkgReplacementContainer" style="display:none;margin-top:14px;padding-top:14px;border-top:1px solid #E2E8F0;">
                        <div id="pkgReplacementLoading" style="font-size:0.85rem;color:#64748B;text-align:center;padding:12px;">Loading available alternatives...</div>
                        <div id="pkgReplacementList" style="display:flex;flex-direction:column;gap:10px;"></div>
                    </div>
                </div>

                <!-- Option B: Reschedule (SECOND) -->
                <div class="pkg-option-card" style="border:1.5px solid #E2E8F0;border-radius:12px;padding:16px;background:#FFFFFF;">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
                        <div style="display:flex;align-items:center;gap:10px;">
                            <span style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:50%;background:#EFF6FF;color:#2563EB;font-weight:800;font-size:0.85rem;">B</span>
                            <div>
                                <h4 style="margin:0;font-size:0.95rem;font-weight:700;color:#0F172A;">Option B: Reschedule the Cancelled Service</h4>
                                <small style="color:#64748B;">Request an alternative date/schedule while keeping all other confirmed services unchanged.</small>
                            </div>
                        </div>
                        <button type="button" id="btnToggleReschedule" onclick="toggleOptionSection('reschedule')" class="btn btn-sm btn-ghost" style="font-weight:700;white-space:nowrap;border-color:#CBD5E1;">
                            Reschedule Date
                        </button>
                    </div>
                    <!-- Reschedule Form Container -->
                    <div id="pkgRescheduleContainer" style="display:none;margin-top:14px;padding-top:14px;border-top:1px solid #E2E8F0;">
                        <form id="pkgRescheduleForm" method="post" action="">
                            <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="item_id" id="rescheduleItemId" value="">
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                                <div>
                                    <label style="display:block;font-size:0.8rem;font-weight:700;color:#334155;margin-bottom:4px;">New Date *</label>
                                    <input type="date" name="new_date" id="rescheduleNewDate" required min="<?= date('Y-m-d') ?>" class="form-control" style="width:100%;padding:8px 10px;border:1px solid #CBD5E1;border-radius:8px;font-size:0.85rem;">
                                </div>
                                <div>
                                    <label style="display:block;font-size:0.8rem;font-weight:700;color:#334155;margin-bottom:4px;">New Time (Optional)</label>
                                    <input type="time" name="new_time" id="rescheduleNewTime" class="form-control" style="width:100%;padding:8px 10px;border:1px solid #CBD5E1;border-radius:8px;font-size:0.85rem;">
                                </div>
                            </div>
                            <div style="margin-top:10px;">
                                <label style="display:block;font-size:0.8rem;font-weight:700;color:#334155;margin-bottom:4px;">Notes for Provider (Optional)</label>
                                <input type="text" name="notes" placeholder="e.g. Adjusted check-in time coordinated with host" class="form-control" style="width:100%;padding:8px 10px;border:1px solid #CBD5E1;border-radius:8px;font-size:0.85rem;">
                            </div>
                            <div style="margin-top:12px;text-align:right;">
                                <button type="submit" class="btn btn-primary btn-sm" style="font-weight:700;">Submit Reschedule Request</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let currentPackageSummary = null;

function openOptionAModal(pkgRef, summaryOrId) {
    const modal = document.getElementById('pkgAlternativesModal');
    if (!modal) return;
    modal.style.display = 'flex';
    document.getElementById('pkgReplacementContainer').style.display = 'block';
    document.getElementById('pkgRescheduleContainer').style.display = 'none';

    if (summaryOrId && typeof summaryOrId === 'object' && summaryOrId.package_booking_ref && summaryOrId.cancelled_items && summaryOrId.cancelled_items.length > 0) {
        openPackageOptionsModal(summaryOrId, 'replace');
        return;
    }

    // Set temporary loading state
    document.getElementById('pkgModalRefLabel').textContent = 'Package Ref: #' + (pkgRef || '');
    document.getElementById('pkgCancelledCategoryBadge').textContent = 'Identifying Cancelled Service...';
    document.getElementById('pkgCancelledProviderTitle').textContent = 'Loading service disruption details...';
    document.getElementById('pkgCancelledReason').textContent = '';
    document.getElementById('pkgCancelledSchedule').textContent = '';
    document.getElementById('pkgReplacementLoading').style.display = 'block';
    document.getElementById('pkgReplacementLoading').textContent = 'Loading available replacement services...';
    document.getElementById('pkgReplacementList').innerHTML = '';

    fetch('<?= url('/bookings/package/') ?>' + encodeURIComponent(pkgRef) + '/summary')
        .then(res => res.json())
        .then(data => {
            if (data && data.summary) {
                openPackageOptionsModal(data.summary, 'replace');
            } else {
                // Direct fallback to load alternatives with auto-detection
                loadAlternatives(pkgRef, 0, 0, 0);
            }
        })
        .catch(() => {
            loadAlternatives(pkgRef, 0, 0, 0);
        });
}

function openOptionBModal(pkgRef, summaryOrId) {
    const modal = document.getElementById('pkgAlternativesModal');
    if (!modal) return;
    modal.style.display = 'flex';
    document.getElementById('pkgReplacementContainer').style.display = 'none';
    document.getElementById('pkgRescheduleContainer').style.display = 'block';

    if (summaryOrId && typeof summaryOrId === 'object' && summaryOrId.package_booking_ref && summaryOrId.cancelled_items && summaryOrId.cancelled_items.length > 0) {
        openPackageOptionsModal(summaryOrId, 'reschedule');
        return;
    }

    document.getElementById('pkgModalRefLabel').textContent = 'Package Ref: #' + (pkgRef || '');
    fetch('<?= url('/bookings/package/') ?>' + encodeURIComponent(pkgRef) + '/summary')
        .then(res => res.json())
        .then(data => {
            if (data && data.summary) {
                openPackageOptionsModal(data.summary, 'reschedule');
            }
        })
        .catch(() => {});
}

function openPackageOptionsModal(summary, defaultTab = 'replace') {
    currentPackageSummary = summary;
    const modal = document.getElementById('pkgAlternativesModal');
    if (!modal || !summary) return;

    document.getElementById('pkgModalRefLabel').textContent = 'Package Ref: #' + (summary.package_booking_ref || '');

    const cancelledItem = (summary.cancelled_items && summary.cancelled_items.length > 0) ? summary.cancelled_items[0] : null;
    if (cancelledItem) {
        const catLabel = cancelledItem.category_label || (
            cancelledItem.service_type === 'hotel' ? 'Hotel and Stay' :
            cancelledItem.service_type === 'vehicle' ? 'Vehicle Rental' :
            cancelledItem.service_type === 'activity' ? 'Things to Do' :
            cancelledItem.service_type === 'attraction' ? 'Attractions' : 'Tour Guide'
        );
        document.getElementById('pkgCancelledCategoryBadge').textContent = 'Cancelled Service: ' + catLabel;
        document.getElementById('pkgCancelledProviderTitle').textContent =
            (cancelledItem.provider_name || 'Service Provider') + ' has cancelled ' + (cancelledItem.service_name || 'service');
        document.getElementById('pkgCancelledReason').textContent =
            'Reason: ' + (cancelledItem.cancellation_reason || 'Provider unavailable for scheduled date');

        let sched = 'Travel schedule';
        if (cancelledItem.service_type === 'hotel') {
            sched = 'Stay dates: ' + (cancelledItem.hotel_check_in || 'Check-in') + ' to ' + (cancelledItem.hotel_check_out || 'Check-out');
        } else if (cancelledItem.service_type === 'vehicle') {
            sched = 'Pickup date: ' + (cancelledItem.rental_pickup_date || 'Pickup') + ' (' + (cancelledItem.rental_days_count || 1) + ' day(s))';
        }
        document.getElementById('pkgCancelledSchedule').textContent = 'Affected Schedule: ' + sched;

        // Populate form action URLs & item_id values
        const ref = encodeURIComponent(summary.package_booking_ref);
        document.getElementById('pkgRescheduleForm').action = '<?= url('/bookings/package/') ?>' + ref + '/reschedule';
        document.getElementById('rescheduleItemId').value = cancelledItem.id;

        // Display selected tab section
        if (defaultTab === 'replace') {
            document.getElementById('pkgReplacementContainer').style.display = 'block';
            document.getElementById('pkgRescheduleContainer').style.display = 'none';
            loadAlternatives(summary.package_booking_ref, cancelledItem.id, parseFloat(cancelledItem.allocated_amount || 0), parseFloat(summary.total_amount || 0));
        } else if (defaultTab === 'reschedule') {
            document.getElementById('pkgReplacementContainer').style.display = 'none';
            document.getElementById('pkgRescheduleContainer').style.display = 'block';
        }
    }

    modal.style.display = 'flex';
}

function closePackageOptionsModal() {
    const modal = document.getElementById('pkgAlternativesModal');
    if (modal) modal.style.display = 'none';
}

function toggleOptionSection(opt) {
    const containers = {
        'replace': document.getElementById('pkgReplacementContainer'),
        'reschedule': document.getElementById('pkgRescheduleContainer')
    };
    for (const key in containers) {
        if (key === opt) {
            containers[key].style.display = (containers[key].style.display === 'block') ? 'none' : 'block';
        } else {
            containers[key].style.display = 'none';
        }
    }
}

function loadAlternatives(ref, itemId, originalAllocated, currentPackageTotal) {
    const listEl = document.getElementById('pkgReplacementList');
    const loadEl = document.getElementById('pkgReplacementLoading');
    if (!listEl || !loadEl) return;

    let resolvedItemId = itemId;
    if (resolvedItemId <= 0 && currentPackageSummary && currentPackageSummary.cancelled_items && currentPackageSummary.cancelled_items.length > 0) {
        resolvedItemId = currentPackageSummary.cancelled_items[0].id;
        originalAllocated = originalAllocated || parseFloat(currentPackageSummary.cancelled_items[0].allocated_amount || 0);
        currentPackageTotal = currentPackageTotal || parseFloat(currentPackageSummary.total_amount || 0);
    }

    loadEl.style.display = 'block';
    loadEl.textContent = 'Loading available alternatives...';
    listEl.innerHTML = '';

    fetch('<?= url('/bookings/package/') ?>' + encodeURIComponent(ref) + '/alternatives?item_id=' + resolvedItemId)
        .then(res => res.json())
        .then(data => {
            loadEl.style.display = 'none';
            listEl.innerHTML = '';
            const alts = data.alternatives || [];
            if (alts.length === 0) {
                listEl.innerHTML = '<div style="font-size:0.85rem;color:#64748B;text-align:center;padding:12px;">No available replacements found in this category. You may use Option B to reschedule the service date.</div>';
                return;
            }
            alts.forEach(alt => {
                const altPrice = parseFloat(alt.price || 0);
                const priceDiff = altPrice - originalAllocated;
                const newPkgTotal = currentPackageTotal + priceDiff;
                const priceDiffText = priceDiff > 0
                    ? '+ ₱' + priceDiff.toLocaleString('en-US', {minimumFractionDigits: 2})
                    : (priceDiff < 0 ? '- ₱' + Math.abs(priceDiff).toLocaleString('en-US', {minimumFractionDigits: 2}) : 'No price change');

                const card = document.createElement('div');
                card.style.cssText = 'display:flex;align-items:center;justify-content:space-between;padding:12px 14px;border:1px solid #E2E8F0;border-radius:8px;background:#F8FAFC;gap:12px;';
                card.innerHTML = `
                    <div style="flex:1;">
                        <strong style="display:block;font-size:0.9rem;color:#0F172A;">${alt.title || alt.room_name}</strong>
                        <div style="font-size:0.78rem;color:#64748B;margin-top:2px;">Provider: ${alt.provider_name} &bull; ${alt.area || alt.location || 'Cebu'}</div>
                        <div style="display:flex;align-items:center;gap:8px;margin-top:4px;font-size:0.82rem;">
                            <span style="color:#059669;font-weight:700;">Service Price: ₱${altPrice.toLocaleString('en-US', {minimumFractionDigits:2})}</span>
                            <span style="color:${priceDiff > 0 ? '#B45309' : (priceDiff < 0 ? '#059669' : '#64748B')};font-weight:600;font-size:0.76rem;">(${priceDiffText})</span>
                        </div>
                        <div style="font-size:0.76rem;color:#475569;margin-top:2px;">Recalculated Package Total: <strong>₱${newPkgTotal.toLocaleString('en-US', {minimumFractionDigits:2})}</strong></div>
                    </div>
                    <form method="post" action="<?= url('/bookings/package/') ?>${encodeURIComponent(ref)}/replace" style="margin:0;" onsubmit="return confirm('Confirm replacing service with ${alt.title || alt.room_name}? Updated package total: ₱${newPkgTotal.toLocaleString('en-US', {minimumFractionDigits:2})}');">
                        <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="item_id" value="${resolvedItemId}">
                        <input type="hidden" name="provider_id" value="${alt.provider_id}">
                        <input type="hidden" name="service_id" value="${alt.listing_id || alt.vehicle_id || alt.provider_id}">
                        <input type="hidden" name="room_id" value="${alt.room_id || 0}">
                        <button type="submit" class="btn btn-sm btn-primary" style="font-weight:700;white-space:nowrap;">Select &amp; Replace</button>
                    </form>
                `;
                listEl.appendChild(card);
            });
        })
        .catch(() => {
            loadEl.textContent = 'Could not load alternative providers at this time.';
        });
}
</script>
