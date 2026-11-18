<!-- Travel Package Details Modal -->
<div id="packageDetailsModal" class="modal-backdrop" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.65);z-index:99999;align-items:center;justify-content:center;overflow-y:auto;padding:1.5rem 1rem;">
    <div class="modal-dialog" style="background:#ffffff;border-radius:16px;max-width:760px;width:100%;box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);overflow:hidden;border:1px solid #E2E8F0;margin:auto;">
        <!-- Modal Header -->
        <div style="padding:1.25rem 1.75rem;background:#F8FAFC;border-bottom:1px solid #E2E8F0;display:flex;align-items:center;justify-content:space-between;">
            <div>
                <h3 id="pkgDetailsModalTitle" style="margin:0;font-size:1.25rem;font-weight:800;color:#0F172A;">Travel Package Details</h3>
                <div style="font-size:0.82rem;color:#64748B;margin-top:2px;">
                    Booking Reference: <strong id="pkgDetailsModalRef" style="color:#0F172A;">-</strong>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:0.75rem;">
                <span id="pkgDetailsModalStatusBadge" class="pill" style="font-weight:700;font-size:0.75rem;padding:3px 10px;border-radius:9999px;">-</span>
                <button type="button" onclick="closePackageDetailsModal()" style="background:transparent;border:none;font-size:1.5rem;line-height:1;color:#94A3B8;cursor:pointer;padding:0.25rem;">&times;</button>
            </div>
        </div>

        <!-- Modal Body -->
        <div id="pkgDetailsModalBody" style="padding:1.5rem 1.75rem;max-height:calc(85vh - 120px);overflow-y:auto;display:flex;flex-direction:column;gap:1.25rem;">
            
            <!-- Trip Overview Card -->
            <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:12px;padding:1rem 1.25rem;display:flex;flex-wrap:wrap;gap:1rem;justify-content:space-between;">
                <div>
                    <div style="font-size:0.75rem;font-weight:700;color:#64748B;text-transform:uppercase;">Scheduled Date</div>
                    <div id="pkgDetailsModalDate" style="font-size:0.95rem;font-weight:700;color:#0F172A;margin-top:2px;">-</div>
                </div>
                <div>
                    <div style="font-size:0.75rem;font-weight:700;color:#64748B;text-transform:uppercase;">Travelers / Guests</div>
                    <div id="pkgDetailsModalGuests" style="font-size:0.95rem;font-weight:700;color:#0F172A;margin-top:2px;">-</div>
                </div>
                <div>
                    <div style="font-size:0.75rem;font-weight:700;color:#64748B;text-transform:uppercase;">Total Package Amount</div>
                    <div id="pkgDetailsModalTotal" style="font-size:1.05rem;font-weight:800;color:#059669;margin-top:2px;">-</div>
                </div>
            </div>

            <!-- Provider Booking Approvals -->
            <div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:12px;padding:1.15rem 1.25rem;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.75rem;">
                    <h4 style="margin:0;font-size:0.95rem;font-weight:800;color:#0F172A;">Provider Booking Approvals</h4>
                    <span id="pkgDetailsApprovalsSummary" class="pill" style="font-size:0.7rem;font-weight:700;padding:2px 8px;border-radius:9999px;">-</span>
                </div>
                <div id="pkgDetailsApprovalsList" style="display:flex;flex-direction:column;gap:0.65rem;">
                    <!-- Dynamically generated rows -->
                </div>
            </div>

            <!-- Assigned Tour Guide Host -->
            <div id="pkgDetailsGuideSection" style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:12px;padding:1rem 1.25rem;">
                <h4 style="margin:0 0 0.5rem;font-size:0.85rem;font-weight:800;color:#059669;text-transform:uppercase;letter-spacing:0.02em;">Assigned Tour Guide Host</h4>
                <div style="display:flex;align-items:center;gap:0.75rem;">
                    <span style="font-size:1.5rem;">🧑‍💼</span>
                    <div>
                        <div id="pkgDetailsGuideName" style="font-size:0.95rem;font-weight:700;color:#0F172A;">-</div>
                        <div style="font-size:0.8rem;color:#64748B;">Licensed Regional Tour Guide · DOT Accredited</div>
                    </div>
                </div>
            </div>

            <!-- Selected Package Services -->
            <div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:12px;padding:1.25rem;">
                <h4 style="margin:0 0 1rem;font-size:1rem;font-weight:800;color:#0F172A;border-bottom:1px solid #E2E8F0;padding-bottom:0.5rem;">Selected Package Services</h4>

                <!-- Tours & Attractions -->
                <div id="pkgDetailsToursSection" style="margin-bottom:1.15rem;">
                    <div style="font-size:0.8rem;font-weight:800;color:#059669;text-transform:uppercase;margin-bottom:0.4rem;">Tours &amp; Attractions Included</div>
                    <div id="pkgDetailsToursList" style="display:flex;flex-direction:column;gap:0.4rem;font-size:0.9rem;color:#1E293B;">
                        <!-- Inclusions list -->
                    </div>
                </div>

                <!-- 1. Original Hotel Stay Accommodation -->
                <div id="pkgDetailsHotelSection" style="padding-top:0.85rem;border-top:1px solid #E2E8F0;margin-bottom:1rem;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.35rem;">
                        <span style="font-size:0.8rem;font-weight:800;color:#D97706;text-transform:uppercase;">Hotel Stay Accommodation</span>
                        <span id="pkgDetailsHotelOriginalStatus" class="pill" style="font-size:0.7rem;font-weight:700;padding:2px 8px;border-radius:9999px;display:none;">-</span>
                    </div>
                    <div id="pkgDetailsHotelName" style="font-size:0.95rem;font-weight:700;color:#0F172A;">-</div>
                    <div id="pkgDetailsHotelRoom" style="font-size:0.82rem;color:#475569;margin-top:2px;">-</div>
                    <div id="pkgDetailsHotelDates" style="font-size:0.78rem;color:#64748B;margin-top:2px;">-</div>
                    <div id="pkgDetailsHotelCancelledNotice" style="display:none;margin-top:0.4rem;font-size:0.75rem;font-weight:700;color:#DC2626;">
                        ✕ Cancelled by original hotel provider <span id="pkgDetailsHotelRefundBadge" style="display:none;margin-left:6px;padding:2px 8px;border-radius:4px;background:#DCFCE7;color:#15803D;font-size:0.72rem;font-weight:800;">✓ Refunded: ₱<span id="pkgDetailsHotelRefundAmt">0.00</span></span>
                    </div>
                </div>

                <!-- 2. Hotel Stay Accommodation Replacement (Directly Below Original Hotel) -->
                <div id="pkgDetailsHotelReplacementSection" style="display:none;padding:0.95rem 1.15rem;background:#FFFBEB;border:1.5px solid #F59E0B;border-radius:10px;margin-bottom:1rem;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.4rem;">
                        <span style="font-size:0.82rem;font-weight:800;color:#B45309;text-transform:uppercase;display:inline-flex;align-items:center;gap:4px;">
                            <span>🏨</span> Hotel Stay Accommodation Replacement
                        </span>
                        <span id="pkgDetailsHotelReplacementStatus" class="pill" style="font-size:0.72rem;font-weight:800;padding:2px 8px;border-radius:9999px;background:#FEF3C7;color:#92400E;border:1px solid #F59E0B;">⏳ Pending</span>
                    </div>
                    <div id="pkgDetailsHotelReplacementName" style="font-size:0.98rem;font-weight:800;color:#0F172A;">-</div>
                    <div id="pkgDetailsHotelReplacementRoom" style="font-size:0.85rem;color:#334155;margin-top:2px;font-weight:600;">-</div>
                    <div id="pkgDetailsHotelReplacementPricing" style="font-size:0.85rem;font-weight:700;color:#059669;margin-top:3px;">-</div>
                    <div id="pkgDetailsHotelReplacementDates" style="font-size:0.8rem;color:#475569;margin-top:2px;">-</div>
                    <div id="pkgDetailsHotelReplacementProvider" style="font-size:0.8rem;color:#475569;margin-top:2px;">-</div>
                </div>

                <!-- 3. Original Rental Transportation -->
                <div id="pkgDetailsVehicleSection" style="padding-top:0.85rem;border-top:1px solid #E2E8F0;margin-bottom:1rem;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.35rem;">
                        <span style="font-size:0.8rem;font-weight:800;color:#2563EB;text-transform:uppercase;">Rental Transportation</span>
                        <span id="pkgDetailsVehicleOriginalStatus" class="pill" style="font-size:0.7rem;font-weight:700;padding:2px 8px;border-radius:9999px;display:none;">-</span>
                    </div>
                    <div id="pkgDetailsVehicleName" style="font-size:0.95rem;font-weight:700;color:#0F172A;">-</div>
                    <div id="pkgDetailsVehicleType" style="font-size:0.82rem;color:#475569;margin-top:2px;">-</div>
                    <div id="pkgDetailsVehicleCancelledNotice" style="display:none;margin-top:0.4rem;font-size:0.75rem;font-weight:700;color:#DC2626;">
                        ✕ Cancelled by original rental provider <span id="pkgDetailsVehicleRefundBadge" style="display:none;margin-left:6px;padding:2px 8px;border-radius:4px;background:#DCFCE7;color:#15803D;font-size:0.72rem;font-weight:800;">✓ Refunded: ₱<span id="pkgDetailsVehicleRefundAmt">0.00</span></span>
                    </div>
                </div>

                <!-- 4. Rental Transportation Replacement (Directly Below Original Rental) -->
                <div id="pkgDetailsVehicleReplacementSection" style="display:none;padding:0.95rem 1.15rem;background:#EFF6FF;border:1.5px solid #3B82F6;border-radius:10px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.4rem;">
                        <span style="font-size:0.82rem;font-weight:800;color:#1D4ED8;text-transform:uppercase;display:inline-flex;align-items:center;gap:4px;">
                            <span>🛵</span> Rental Transportation Replacement
                        </span>
                        <span id="pkgDetailsVehicleReplacementStatus" class="pill" style="font-size:0.72rem;font-weight:800;padding:2px 8px;border-radius:9999px;background:#EFF6FF;color:#1D4ED8;border:1px solid #3B82F6;">⏳ Pending</span>
                    </div>
                    <div id="pkgDetailsVehicleReplacementName" style="font-size:0.98rem;font-weight:800;color:#0F172A;">-</div>
                    <div id="pkgDetailsVehicleReplacementModel" style="font-size:0.85rem;color:#334155;margin-top:2px;font-weight:600;">-</div>
                    <div id="pkgDetailsVehicleReplacementLocation" style="font-size:0.82rem;color:#475569;margin-top:2px;">-</div>
                    <div id="pkgDetailsVehicleReplacementSchedule" style="font-size:0.8rem;color:#475569;margin-top:2px;">-</div>
                    <div id="pkgDetailsVehicleReplacementProvider" style="font-size:0.8rem;color:#475569;margin-top:2px;">-</div>
                    <div id="pkgDetailsVehicleReplacementPricing" style="font-size:0.85rem;font-weight:700;color:#059669;margin-top:3px;">-</div>
                </div>

            </div>

        </div>

        <!-- Modal Footer -->
        <div style="padding:1rem 1.75rem;background:#F8FAFC;border-top:1px solid #E2E8F0;display:flex;justify-content:flex-end;">
            <button type="button" class="btn btn-ghost" onclick="closePackageDetailsModal()" style="font-weight:700;">Close</button>
        </div>
    </div>
</div>

<script>
window.openPackageDetailsModal = function(summaryOrRef) {
    if (!summaryOrRef) return;
    if (typeof summaryOrRef === 'string') {
        fetch(window.APP_BASE_URL + '/bookings/package/' + encodeURIComponent(summaryOrRef) + '/summary')
            .then(res => res.json())
            .then(data => {
                if (data.summary) {
                    renderPackageDetails(data.summary);
                }
            })
            .catch(err => console.error(err));
        return;
    }
    renderPackageDetails(summaryOrRef);
};

window.closePackageDetailsModal = function() {
    const modal = document.getElementById('packageDetailsModal');
    if (modal) modal.style.display = 'none';
};

function renderPackageDetails(pkg) {
    const modal = document.getElementById('packageDetailsModal');
    if (!modal) return;

    const ref = pkg.package_booking_ref || ('BK-' + (pkg.parent_booking_id || pkg.id || ''));
    const details = pkg.package_details || {};
    const overallStatus = (pkg.overall_status || pkg.package_overall_status || 'pending').toLowerCase();
    
    document.getElementById('pkgDetailsModalRef').textContent = ref;
    document.getElementById('pkgDetailsModalTitle').textContent = details.package_title || pkg.listing_title || 'Travel Package Details';
    
    // Status Badge
    const statusBadge = document.getElementById('pkgDetailsModalStatusBadge');
    statusBadge.textContent = overallStatus.replace(/_/g, ' ').toUpperCase();
    statusBadge.className = 'pill pill-' + overallStatus;

    // Overview Cards
    document.getElementById('pkgDetailsModalDate').textContent = pkg.booking_date || details.booking_date || details.travel_date || 'Flexible Date';
    document.getElementById('pkgDetailsModalGuests').textContent = (pkg.guests || details.guests_count || 1) + ' Guest(s)';
    document.getElementById('pkgDetailsModalTotal').textContent = '₱' + Number(pkg.total_amount || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});

    // Guide section
    const guideName = pkg.guide_name || (details.guide ? details.guide.name : 'Marc Repuesto');
    document.getElementById('pkgDetailsGuideName').textContent = guideName;

    // Inclusions / Tours
    const toursList = document.getElementById('pkgDetailsToursList');
    toursList.innerHTML = '';
    const inclusions = details.inclusions || [];
    if (inclusions.length > 0) {
        inclusions.forEach(inc => {
            const itemDiv = document.createElement('div');
            itemDiv.innerHTML = '<span style="color:#059669;margin-right:6px;">✔</span><strong>' + (inc.title || inc.name || inc) + '</strong>' + (inc.subtitle ? ' <span style="color:#64748B;font-size:0.8rem;">(' + inc.subtitle + ')</span>' : '');
            toursList.appendChild(itemDiv);
        });
    } else {
        const itemDiv = document.createElement('div');
        itemDiv.innerHTML = '<span style="color:#059669;margin-right:6px;">✔</span>' + (pkg.listing_title || 'Guided Day Tours & Activities');
        toursList.appendChild(itemDiv);
    }

    // Provider Booking Approvals
    const approvalsList = document.getElementById('pkgDetailsApprovalsList');
    approvalsList.innerHTML = '';

    const items = pkg.items || [];
    const activeItems = pkg.active_items || items;
    const replacementHotel = pkg.replacement_hotel || details.accommodation_replacement;
    const replacementVehicle = pkg.replacement_vehicle || details.rental_replacement;

    // Original cancellations
    const allCancelled = pkg.all_cancelled_items || [];
    const hotelWasCancelled = allCancelled.some(i => i.service_type === 'hotel') || Boolean(replacementHotel);
    const vehicleWasCancelled = allCancelled.some(i => i.service_type === 'vehicle') || Boolean(replacementVehicle);

    // Guide approval row
    const guideItem = items.find(i => i.service_type === 'guide');
    const guidePayStatus = (guideItem && (guideItem.payment_status === 'refunded' || guideItem.refund_status === 'refunded')) ? 'Refunded' : 'Paid';
    const guidePayStyle = guidePayStatus === 'Refunded' ? 'background:#FEF2F2;color:#DC2626;border:1px solid #FCA5A5;' : 'background:#DCFCE7;color:#15803D;border:1px solid #86EFAC;';

    const guideApprovalRow = document.createElement('div');
    guideApprovalRow.style.cssText = 'display:flex;align-items:center;justify-content:space-between;font-size:0.88rem;';
    guideApprovalRow.innerHTML = `
        <div style="display:flex;align-items:center;gap:8px;">
            <span>🧑‍💼</span>
            <div>
                <strong>Tour Guide Host</strong>
                <div style="font-size:0.78rem;color:#64748B;">${guideName}</div>
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:6px;">
            <span class="pill" style="font-size:0.7rem;font-weight:700;padding:2px 7px;border-radius:9999px;${guidePayStyle}">${guidePayStatus}</span>
            <span class="pill pill-confirmed" style="font-size:0.72rem;font-weight:700;">✔ Confirmed</span>
        </div>
    `;
    approvalsList.appendChild(guideApprovalRow);

    // Hotel approval row
    const hasHotel = Boolean(details.hotel_included || details.accommodation || replacementHotel);
    if (hasHotel) {
        const hotelItem = items.find(i => i.service_type === 'hotel' && !i.is_replacement);
        const hName = replacementHotel ? (replacementHotel.hotel_name || 'Replacement Hotel') : (details.accommodation ? (details.accommodation.hotel_name || details.accommodation.name) : 'Partner Hotel');
        const hProv = replacementHotel ? (replacementHotel.provider_name || 'Hotel Partner') : (details.accommodation ? (details.accommodation.provider_name || 'Hotel Partner') : 'Hotel Partner');
        const hStatus = replacementHotel ? (replacementHotel.status || 'pending').toLowerCase() : (hotelItem ? (hotelItem.status || 'confirmed').toLowerCase() : 'confirmed');
        const hStatusLabel = (hStatus === 'confirmed' || hStatus === 'approved') ? '✔ Confirmed' : ((hStatus === 'cancelled' || hStatus === 'rejected') ? '✕ Declined' : '⏳ Pending');
        const hStatusClass = (hStatus === 'confirmed' || hStatus === 'approved') ? 'pill-confirmed' : ((hStatus === 'cancelled' || hStatus === 'rejected') ? 'pill-cancelled' : 'pill-warning');

        const hotelPayStatus = (hotelItem && (hotelItem.payment_status === 'refunded' || hotelItem.refund_status === 'refunded' || (hotelWasCancelled && !replacementHotel))) ? 'Refunded' : 'Paid';
        const hotelPayStyle = hotelPayStatus === 'Refunded' ? 'background:#FEF2F2;color:#DC2626;border:1px solid #FCA5A5;' : 'background:#DCFCE7;color:#15803D;border:1px solid #86EFAC;';

        const hotelApprovalRow = document.createElement('div');
        hotelApprovalRow.style.cssText = 'display:flex;align-items:center;justify-content:space-between;font-size:0.88rem;';
        hotelApprovalRow.innerHTML = `
            <div style="display:flex;align-items:center;gap:8px;">
                <span>🏨</span>
                <div>
                    <strong>Hotel Accommodation ${replacementHotel ? '<span style="font-size:0.75rem;color:#D97706;">(Replacement)</span>' : ''}</strong>
                    <div style="font-size:0.78rem;color:#64748B;">${hName} · ${hProv}</div>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:6px;">
                <span class="pill" style="font-size:0.7rem;font-weight:700;padding:2px 7px;border-radius:9999px;${hotelPayStyle}">${hotelPayStatus}</span>
                <span class="pill ${hStatusClass}" style="font-size:0.72rem;font-weight:700;">${hStatusLabel}</span>
            </div>
        `;
        approvalsList.appendChild(hotelApprovalRow);
    }

    // Vehicle approval row
    const hasVehicle = Boolean(details.rental_included || details.rental || replacementVehicle);
    if (hasVehicle) {
        const vehicleItem = items.find(i => i.service_type === 'vehicle' && !i.is_replacement);
        const vName = replacementVehicle ? (replacementVehicle.vehicle_name || replacementVehicle.model || 'Replacement Vehicle') : (details.rental ? (details.rental.vehicle_name || details.rental.name) : 'Rental Vehicle');
        const vProv = replacementVehicle ? (replacementVehicle.provider_name || 'Rental Partner') : (details.rental ? (details.rental.provider_name || 'Rental Partner') : 'Rental Partner');
        const vStatus = replacementVehicle ? (replacementVehicle.status || 'pending').toLowerCase() : (vehicleItem ? (vehicleItem.status || 'confirmed').toLowerCase() : 'confirmed');
        const vStatusLabel = (vStatus === 'confirmed' || vStatus === 'approved') ? '✔ Confirmed' : ((vStatus === 'cancelled' || vStatus === 'rejected') ? '✕ Declined' : '⏳ Pending');
        const vStatusClass = (vStatus === 'confirmed' || vStatus === 'approved') ? 'pill-confirmed' : ((vStatus === 'cancelled' || vStatus === 'rejected') ? 'pill-cancelled' : 'pill-warning');

        const vehiclePayStatus = (vehicleItem && (vehicleItem.payment_status === 'refunded' || vehicleItem.refund_status === 'refunded' || (vehicleWasCancelled && !replacementVehicle))) ? 'Refunded' : 'Paid';
        const vehiclePayStyle = vehiclePayStatus === 'Refunded' ? 'background:#FEF2F2;color:#DC2626;border:1px solid #FCA5A5;' : 'background:#DCFCE7;color:#15803D;border:1px solid #86EFAC;';

        const vehicleApprovalRow = document.createElement('div');
        vehicleApprovalRow.style.cssText = 'display:flex;align-items:center;justify-content:space-between;font-size:0.88rem;';
        vehicleApprovalRow.innerHTML = `
            <div style="display:flex;align-items:center;gap:8px;">
                <span>🛵</span>
                <div>
                    <strong>Vehicle Transportation ${replacementVehicle ? '<span style="font-size:0.75rem;color:#2563EB;">(Replacement)</span>' : ''}</strong>
                    <div style="font-size:0.78rem;color:#64748B;">${vName} · ${vProv}</div>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:6px;">
                <span class="pill" style="font-size:0.7rem;font-weight:700;padding:2px 7px;border-radius:9999px;${vehiclePayStyle}">${vehiclePayStatus}</span>
                <span class="pill ${vStatusClass}" style="font-size:0.72rem;font-weight:700;">${vStatusLabel}</span>
            </div>
        `;
        approvalsList.appendChild(vehicleApprovalRow);
    }

    // 1. Original Hotel Section
    const hotelSec = document.getElementById('pkgDetailsHotelSection');
    if (hasHotel) {
        hotelSec.style.display = 'block';
        const origH = details.accommodation || {};
        document.getElementById('pkgDetailsHotelName').textContent = origH.hotel_name || origH.name || pkg.room_name || 'Partner Hotel';
        document.getElementById('pkgDetailsHotelRoom').textContent = (origH.room_name || origH.roomType || 'Standard Room') + ' · ' + (pkg.nights || details.duration_nights || 1) + ' Night(s)';
        document.getElementById('pkgDetailsHotelDates').textContent = 'Check-in: ' + (pkg.check_in_date || pkg.booking_date) + ' from 02:00 PM · Check-out: ' + (pkg.check_out_date || pkg.booking_date) + ' at 12:00 PM';
        
        const origHNotice = document.getElementById('pkgDetailsHotelCancelledNotice');
        origHNotice.style.display = hotelWasCancelled ? 'block' : 'none';

        const hRefundBadge = document.getElementById('pkgDetailsHotelRefundBadge');
        const hRefundAmt = document.getElementById('pkgDetailsHotelRefundAmt');
        const hotelRefund = origH.refund_amount || origH.hotel_refund_amount || origH.allocated_share || origH.allocated_amount || 0;
        if (hotelWasCancelled && hotelRefund > 0) {
            hRefundBadge.style.display = 'inline-block';
            hRefundAmt.textContent = Number(hotelRefund).toLocaleString(undefined, {minimumFractionDigits: 2});
        } else {
            hRefundBadge.style.display = 'none';
        }
    } else {
        hotelSec.style.display = 'none';
    }

    // 2. Hotel Stay Accommodation Replacement
    const hotelRepSec = document.getElementById('pkgDetailsHotelReplacementSection');
    if (hotelWasCancelled && replacementHotel) {
        hotelRepSec.style.display = 'block';
        document.getElementById('pkgDetailsHotelReplacementName').textContent = replacementHotel.hotel_name || 'Replacement Hotel';
        document.getElementById('pkgDetailsHotelReplacementRoom').textContent = (replacementHotel.room_name || 'Standard Room') + ' (' + (replacementHotel.room_type || 'Standard') + ') · ' + (replacementHotel.nights || pkg.nights || 1) + ' Night(s)';
        document.getElementById('pkgDetailsHotelReplacementPricing').textContent = 'Pricing: ₱' + Number(replacementHotel.pricing || replacementHotel.allocated_amount || 0).toLocaleString(undefined, {minimumFractionDigits: 2}) + (replacementHotel.price_per_night ? ' (₱' + Number(replacementHotel.price_per_night).toLocaleString(undefined, {minimumFractionDigits: 2}) + ' / night)' : '');
        document.getElementById('pkgDetailsHotelReplacementDates').textContent = 'Reservation Dates: ' + (replacementHotel.check_in_date || pkg.booking_date) + ' to ' + (replacementHotel.check_out_date || pkg.booking_date);
        document.getElementById('pkgDetailsHotelReplacementProvider').textContent = 'Provider: ' + (replacementHotel.provider_name || 'Hotel Partner') + (replacementHotel.provider_phone ? ' · ' + replacementHotel.provider_phone : '') + (replacementHotel.provider_email ? ' · ' + replacementHotel.provider_email : '');
        
        const repStatus = (replacementHotel.status || 'pending').toLowerCase();
        const repStatusEl = document.getElementById('pkgDetailsHotelReplacementStatus');
        repStatusEl.textContent = (repStatus === 'confirmed' || repStatus === 'approved') ? '✔ Confirmed' : ((repStatus === 'cancelled' || repStatus === 'rejected') ? '✕ Declined' : '⏳ Pending');
        repStatusEl.style.background = (repStatus === 'confirmed' || repStatus === 'approved') ? '#DCFCE7' : ((repStatus === 'cancelled' || repStatus === 'rejected') ? '#FEF2F2' : '#FEF3C7');
        repStatusEl.style.color = (repStatus === 'confirmed' || repStatus === 'approved') ? '#15803D' : ((repStatus === 'cancelled' || repStatus === 'rejected') ? '#DC2626' : '#92400E');
    } else {
        hotelRepSec.style.display = 'none';
    }

    // 3. Original Vehicle Section
    const vehicleSec = document.getElementById('pkgDetailsVehicleSection');
    if (hasVehicle) {
        vehicleSec.style.display = 'block';
        const origV = details.rental || {};
        const vDays = origV.rental_days || origV.duration_days || details.duration_days || pkg.days_count || (function() {
            const m = (pkg.listing_title || details.package_title || '').match(/(\d+)\s*(?:d|day)/i);
            return m ? parseInt(m[1], 10) : 3;
        })();
        document.getElementById('pkgDetailsVehicleName').textContent = origV.vehicle_name || origV.name || 'Partner Rental Vehicle';
        document.getElementById('pkgDetailsVehicleType').textContent = (origV.vehicle_type || 'Vehicle') + ' · ' + vDays + ' Full Day(s)';
        
        const origVNotice = document.getElementById('pkgDetailsVehicleCancelledNotice');
        origVNotice.style.display = vehicleWasCancelled ? 'block' : 'none';

        const vRefundBadge = document.getElementById('pkgDetailsVehicleRefundBadge');
        const vRefundAmt = document.getElementById('pkgDetailsVehicleRefundAmt');
        const vehicleRefund = origV.refund_amount || origV.rental_refund_amount || origV.allocated_share || origV.allocated_amount || 0;
        if (vehicleWasCancelled && vehicleRefund > 0) {
            vRefundBadge.style.display = 'inline-block';
            vRefundAmt.textContent = Number(vehicleRefund).toLocaleString(undefined, {minimumFractionDigits: 2});
        } else {
            vRefundBadge.style.display = 'none';
        }
    } else {
        vehicleSec.style.display = 'none';
    }

    // 4. Rental Transportation Replacement
    const vehicleRepSec = document.getElementById('pkgDetailsVehicleReplacementSection');
    if (vehicleWasCancelled && replacementVehicle) {
        vehicleRepSec.style.display = 'block';
        const repVDays = replacementVehicle.rental_days || replacementVehicle.duration_days || vDays;
        const repCap = (replacementVehicle.passenger_capacity !== undefined && replacementVehicle.passenger_capacity !== null) ? replacementVehicle.passenger_capacity : (replacementVehicle.capacity !== undefined && replacementVehicle.capacity !== null ? replacementVehicle.capacity : 0);
        document.getElementById('pkgDetailsVehicleReplacementName').textContent = replacementVehicle.vehicle_name || replacementVehicle.model || 'Replacement Vehicle';
        document.getElementById('pkgDetailsVehicleReplacementModel').textContent = 'Model & Type: ' + (replacementVehicle.model || replacementVehicle.vehicle_name || 'Vehicle') + ' (' + (replacementVehicle.vehicle_type || 'Vehicle') + ') · ' + repVDays + ' Full Day(s) · Capacity: ' + repCap + ' Passengers';
        document.getElementById('pkgDetailsVehicleReplacementLocation').textContent = 'Pickup Location: ' + (replacementVehicle.location || 'Designated Meeting Location');
        document.getElementById('pkgDetailsVehicleReplacementSchedule').textContent = 'Pickup: ' + (replacementVehicle.pickup_date || pkg.booking_date) + ' at ' + (replacementVehicle.pickup_time || '09:00 AM') + ' · Return: ' + (replacementVehicle.return_date || replacementVehicle.pickup_date || pkg.booking_date) + ' at ' + (replacementVehicle.return_time || '05:00 PM');
        document.getElementById('pkgDetailsVehicleReplacementProvider').textContent = 'Replacement Rental Provider: ' + (replacementVehicle.provider_name || 'Rental Partner') + (replacementVehicle.provider_phone ? ' · ' + replacementVehicle.provider_phone : '') + (replacementVehicle.provider_email ? ' · ' + replacementVehicle.provider_email : '');
        document.getElementById('pkgDetailsVehicleReplacementPricing').textContent = 'Pricing: ₱' + Number(replacementVehicle.pricing || replacementVehicle.allocated_amount || 0).toLocaleString(undefined, {minimumFractionDigits: 2});

        const repVStatus = (replacementVehicle.status || 'pending').toLowerCase();
        const repVStatusEl = document.getElementById('pkgDetailsVehicleReplacementStatus');
        repVStatusEl.textContent = (repVStatus === 'confirmed' || repVStatus === 'approved') ? '✔ Confirmed' : ((repVStatus === 'cancelled' || repVStatus === 'rejected') ? '✕ Declined' : '⏳ Pending');
        repVStatusEl.style.background = (repVStatus === 'confirmed' || repVStatus === 'approved') ? '#DCFCE7' : ((repVStatus === 'cancelled' || repVStatus === 'rejected') ? '#FEF2F2' : '#EFF6FF');
        repVStatusEl.style.color = (repVStatus === 'confirmed' || repVStatus === 'approved') ? '#15803D' : ((repVStatus === 'cancelled' || repVStatus === 'rejected') ? '#DC2626' : '#1D4ED8');
    } else {
        vehicleRepSec.style.display = 'none';
    }

    modal.style.display = 'flex';
}
</script>
