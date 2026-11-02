<?php
$filePath = __DIR__ . '/../app/listing/[slug].tsx';
$content = file_get_contents($filePath);
$content = str_replace("\r\n", "\n", $content);

// 1. Update factsRow for Duration vs Schedule
$oldFactsRow = <<< 'CODE'
          <View style={styles.factsRow}>
            {isHotel ? (
              <View style={[styles.factCard, { backgroundColor: theme.card, borderColor: theme.border }]}>
                <Ionicons name="time-outline" size={20} color="#10B981" />
                <Text style={[styles.factLabel, { color: theme.textSub }]}>Check-In / Out</Text>
                <Text style={[styles.factValue, { color: theme.textMain }]}>
                  {listing.check_in_out_label || listing.duration || '2:00 PM / 12:00 PM'}
                </Text>
              </View>
            ) : (
              <TouchableOpacity
                style={[styles.factCard, { backgroundColor: theme.card, borderColor: theme.border }]}
                activeOpacity={0.7}
                onPress={() => setScheduleModalOpen(true)}
                accessibilityRole="button"
                accessibilityLabel="View schedule for this attraction or tour"
              >
                <Ionicons name="calendar-outline" size={20} color="#10B981" />
                <Text style={[styles.factLabel, { color: theme.textSub }]}>Schedule</Text>
                <Text style={[styles.factValue, { color: theme.textMain }]} numberOfLines={1}>
                  {scheduleSummaryLabel}
                </Text>
                <View style={styles.factLinkRow}>
                  <Text style={[styles.factLink, { color: '#10B981' }]}>See schedule</Text>
                  <Ionicons name="chevron-forward" size={12} color="#10B981" />
                </View>
              </TouchableOpacity>
            )}
CODE;

$newFactsRow = <<< 'CODE'
          <View style={styles.factsRow}>
            {isHotel ? (
              <View style={[styles.factCard, { backgroundColor: theme.card, borderColor: theme.border }]}>
                <Ionicons name="time-outline" size={20} color="#10B981" />
                <Text style={[styles.factLabel, { color: theme.textSub }]}>Check-In / Out</Text>
                <Text style={[styles.factValue, { color: theme.textMain }]}>
                  {listing.check_in_out_label || listing.duration || '2:00 PM / 12:00 PM'}
                </Text>
              </View>
            ) : hasSchedule ? (
              <TouchableOpacity
                style={[styles.factCard, { backgroundColor: theme.card, borderColor: theme.border }]}
                activeOpacity={0.7}
                onPress={() => setScheduleModalOpen(true)}
                accessibilityRole="button"
                accessibilityLabel="View schedule for this attraction or tour"
              >
                <Ionicons name="calendar-outline" size={20} color="#10B981" />
                <Text style={[styles.factLabel, { color: theme.textSub }]}>Schedule</Text>
                <Text style={[styles.factValue, { color: theme.textMain }]} numberOfLines={1}>
                  {scheduleSummaryLabel}
                </Text>
                <View style={styles.factLinkRow}>
                  <Text style={[styles.factLink, { color: '#10B981' }]}>See schedule</Text>
                  <Ionicons name="chevron-forward" size={12} color="#10B981" />
                </View>
              </TouchableOpacity>
            ) : (
              <TouchableOpacity
                style={[styles.factCard, { backgroundColor: theme.card, borderColor: theme.border }]}
                activeOpacity={0.7}
                onPress={() => setDurationModalOpen(true)}
                accessibilityRole="button"
                accessibilityLabel="View duration and details"
              >
                <Ionicons name="time-outline" size={20} color="#10B981" />
                <Text style={[styles.factLabel, { color: theme.textSub }]}>Duration</Text>
                <Text style={[styles.factValue, { color: theme.textMain }]} numberOfLines={1}>
                  {listing.duration || 'Flexible'}
                </Text>
                <View style={styles.factLinkRow}>
                  <Text style={[styles.factLink, { color: '#10B981' }]}>See Details</Text>
                  <Ionicons name="chevron-forward" size={12} color="#10B981" />
                </View>
              </TouchableOpacity>
            )}
CODE;

if (!str_contains($content, $oldFactsRow)) {
    echo "ERROR: oldFactsRow not found!\n";
    exit(1);
}
$content = str_replace($oldFactsRow, $newFactsRow, $content);

// 2. Update declarations around isHotel, hasSchedule, isPerGroup, openBooking, and pricing calculations
$oldBookingLogic = <<< 'CODE'
  const isHotel = listing?.category === 'Hotels' || (listing as any)?.category_slug === 'hotels';
  const hotelNights = isHotel && hotelCheckIn && hotelCheckOut ? calculateNights(hotelCheckIn, hotelCheckOut) : 1;
  const daysCount = isHotel ? hotelNights : Math.max(1, bookingDates.length);
  const roomPrice = isHotel && selectedRoom ? selectedRoom.price_per_night : (listing ? listing.price : 0);
  const hotelTotal = roomPrice * hotelNights;
  const depositPct = isHotel && selectedRoom ? (selectedRoom.deposit_percentage ?? 20) : 100;
  const hotelDeposit = Math.round((hotelTotal * depositPct) / 100);
  const hotelBalance = Math.max(0, hotelTotal - hotelDeposit);
  const subtotal = isHotel ? hotelTotal : (listing ? listing.price * guests * daysCount : 0);
  const appliedVoucher = voucherCode ? findVoucher(voucherCode) : null;
  const voucherDiscount = appliedVoucher ? getVoucherDiscount(appliedVoucher, subtotal) : 0;
  const totalAmount = isHotel ? hotelDeposit : Math.max(0, subtotal - voucherDiscount);
CODE;

$newBookingLogic = <<< 'CODE'
  const isHotel = listing?.category === 'Hotels' || (listing as any)?.category_slug === 'hotels';
  const hasSchedule = !isHotel && schedules.length > 0;
  const isPerGroup = (listing?.price_unit || '').toLowerCase().includes('group');

  const selectedDateSessions = React.useMemo(() => {
    if (!hasSchedule || bookingDates.length === 0) return [];
    return schedules.filter((s) => s.schedule_date === bookingDates[0]);
  }, [hasSchedule, schedules, bookingDates]);

  const currentSession = React.useMemo(() => {
    if (!hasSchedule) return null;
    return selectedDateSessions.find((s) => s.start_time?.slice(0, 5) === bookingTime) || selectedDateSessions[0] || null;
  }, [hasSchedule, selectedDateSessions, bookingTime]);

  const hotelNights = isHotel && hotelCheckIn && hotelCheckOut ? calculateNights(hotelCheckIn, hotelCheckOut) : 1;
  const daysCount = isHotel ? hotelNights : Math.max(1, bookingDates.length);
  const roomPrice = isHotel && selectedRoom ? selectedRoom.price_per_night : (listing ? listing.price : 0);
  const hotelTotal = roomPrice * hotelNights;
  const depositPct = isHotel && selectedRoom ? (selectedRoom.deposit_percentage ?? 20) : 100;
  const hotelDeposit = Math.round((hotelTotal * depositPct) / 100);
  const hotelBalance = Math.max(0, hotelTotal - hotelDeposit);
  const subtotal = isHotel ? hotelTotal : (listing ? (isPerGroup ? listing.price * daysCount : listing.price * guests * daysCount) : 0);
  const appliedVoucher = voucherCode ? findVoucher(voucherCode) : null;
  const voucherDiscount = appliedVoucher ? getVoucherDiscount(appliedVoucher, subtotal) : 0;
  const totalAmount = isHotel ? hotelDeposit : Math.max(0, subtotal - voucherDiscount);
CODE;

if (!str_contains($content, $oldBookingLogic)) {
    echo "ERROR: oldBookingLogic not found!\n";
    exit(1);
}
$content = str_replace($oldBookingLogic, $newBookingLogic, $content);

// 3. Update openBooking to handle hasSchedule vs non-scheduled listings
$oldOpenBookingCanChange = <<< 'CODE'
    setCanChangeDate(opts?.allowDateChange ?? false);
CODE;

$newOpenBookingCanChange = <<< 'CODE'
    setCanChangeDate(schedules.length === 0 ? true : (opts?.allowDateChange ?? false));
CODE;

if (!str_contains($content, $oldOpenBookingCanChange)) {
    echo "ERROR: oldOpenBookingCanChange not found!\n";
    exit(1);
}
$content = str_replace($oldOpenBookingCanChange, $newOpenBookingCanChange, $content);

// 4. Update goToPayment to validate capacity and slot availability
$oldGoToPaymentStart = <<< 'CODE'
      if (bookingDates.length === 0) {
        Alert.alert('Pick date', 'Please choose a date from the calendar.');
        return;
      }
      // Use guide defined session times without prompting the tourist
      let currentStartTime = bookingTime;
      let currentEndTime = bookingEndTime;
      if (!currentStartTime || !currentEndTime) {
        const match = schedules.find((s) => s.schedule_date === bookingDates[0]) || schedules[0];
        if (match) {
          currentStartTime = currentStartTime || (match.start_time ? match.start_time.slice(0, 5) : '09:00');
          currentEndTime = currentEndTime || (match.end_time ? match.end_time.slice(0, 5) : '17:00');
        } else {
          currentStartTime = currentStartTime || '09:00';
          currentEndTime = currentEndTime || '17:00';
        }
        setBookingTime(currentStartTime);
        setBookingEndTime(currentEndTime);
      }
    }
CODE;

$newGoToPaymentStart = <<< 'CODE'
      if (bookingDates.length === 0) {
        Alert.alert('Pick date', 'Please choose a date from the calendar.');
        return;
      }

      if (hasSchedule) {
        const match = selectedDateSessions.find((s) => s.start_time?.slice(0, 5) === bookingTime) || selectedDateSessions[0];
        if (match) {
          const cap = match.capacity ?? listing?.capacity ?? 10;
          const slotsLeft = match.available_slots ?? cap;
          if (match.is_available === false || slotsLeft <= 0) {
            Alert.alert('Session Fully Booked', 'This session is already at maximum capacity. Please select another session or date.');
            return;
          }
          if (guests > slotsLeft) {
            Alert.alert('Capacity Exceeded', `Only ${slotsLeft} slot${slotsLeft === 1 ? '' : 's'} available for this session. Please adjust the number of guests.`);
            return;
          }
        }
      }

      if (listing?.capacity && guests > listing.capacity) {
        Alert.alert('Capacity Exceeded', `Maximum capacity is ${listing.capacity} guests for this experience.`);
        return;
      }

      // Use guide defined session times without prompting the tourist
      let currentStartTime = bookingTime;
      let currentEndTime = bookingEndTime;
      if (!currentStartTime || !currentEndTime) {
        const match = schedules.find((s) => s.schedule_date === bookingDates[0]) || schedules[0];
        if (match) {
          currentStartTime = currentStartTime || (match.start_time ? match.start_time.slice(0, 5) : '09:00');
          currentEndTime = currentEndTime || (match.end_time ? match.end_time.slice(0, 5) : '17:00');
        } else {
          currentStartTime = currentStartTime || '09:00';
          currentEndTime = currentEndTime || '17:00';
        }
        setBookingTime(currentStartTime);
        setBookingEndTime(currentEndTime);
      }
    }
CODE;

if (!str_contains($content, $oldGoToPaymentStart)) {
    echo "ERROR: oldGoToPaymentStart not found!\n";
    exit(1);
}
$content = str_replace($oldGoToPaymentStart, $newGoToPaymentStart, $content);

// 5. Update submitBooking to decrement schedule slots immediately
$oldSubmitBookingSync = <<< 'CODE'
      // Synchronize booking state immediately without manual refresh
      setAlreadyBooked(true);
      if (!isHotel && targetDate) {
        setBookedSlots((prev) => [...prev, { date: targetDate, time: finalStartTime || '00:00' }]);
      }
      setCanChangeDate(false);
CODE;

$newSubmitBookingSync = <<< 'CODE'
      // Synchronize booking state immediately without manual refresh
      setAlreadyBooked(true);
      if (!isHotel && targetDate) {
        setBookedSlots((prev) => [...prev, { date: targetDate, time: finalStartTime || '00:00' }]);
      }
      if (hasSchedule && targetDate) {
        setSchedules((prev) =>
          prev.map((s) => {
            const matchesDate = s.schedule_date === targetDate;
            const matchesTime = !finalStartTime || (s.start_time && s.start_time.slice(0, 5) === finalStartTime);
            if (matchesDate && matchesTime) {
              const prevBooked = s.booked_count ?? 0;
              const addedGuests = isPerGroup ? 1 : guests;
              const newBooked = prevBooked + addedGuests;
              const cap = s.capacity ?? listing.capacity ?? 10;
              const newSlots = Math.max(0, cap - newBooked);
              return {
                ...s,
                booked_count: newBooked,
                available_slots: newSlots,
                is_available: newSlots > 0,
              };
            }
            return s;
          })
        );
      }
      setCanChangeDate(!hasSchedule);
CODE;

if (!str_contains($content, $oldSubmitBookingSync)) {
    echo "ERROR: oldSubmitBookingSync not found!\n";
    exit(1);
}
$content = str_replace($oldSubmitBookingSync, $newSubmitBookingSync, $content);

// 6. Update Bottom bar: price unit and button label
$oldBottomBarPrice = <<< 'CODE'
                {listing.price > 0 ? (
                  <Text style={[styles.bookPer, { color: theme.textSub }]}> / {listing.price_unit || 'person'}</Text>
                ) : null}
CODE;

$newBottomBarPrice = <<< 'CODE'
                {listing.price > 0 ? (
                  <Text style={[styles.bookPer, { color: theme.textSub }]}>
                    {isPerGroup ? ' / group' : ` / ${listing.price_unit || 'person'}`}
                  </Text>
                ) : null}
CODE;

if (!str_contains($content, $oldBottomBarPrice)) {
    echo "ERROR: oldBottomBarPrice not found!\n";
    exit(1);
}
$content = str_replace($oldBottomBarPrice, $newBottomBarPrice, $content);

$oldBottomBarButton = <<< 'CODE'
              <Ionicons name="calendar" size={17} color="#FFFFFF" style={{ marginRight: 7 }} />
              <Text style={styles.bookButtonText}>{isHotel ? 'Reserve room' : 'Book now'}</Text>
CODE;

$newBottomBarButton = <<< 'CODE'
              <Ionicons name="calendar" size={17} color="#FFFFFF" style={{ marginRight: 7 }} />
              <Text style={styles.bookButtonText}>{isHotel ? 'Reserve room' : (hasSchedule ? 'Book Session' : 'Book now')}</Text>
CODE;

if (!str_contains($content, $oldBottomBarButton)) {
    echo "ERROR: oldBottomBarButton not found!\n";
    exit(1);
}
$content = str_replace($oldBottomBarButton, $newBottomBarButton, $content);

// 7. Update Schedule Modal: slot availability badge, disabled if full, and button text "Select Date"
$oldScheduleSessions = <<< 'CODE'
                        {/* Session details badges */}
                        <View style={styles.sessionPillsRow}>
                          <View style={[styles.sessionPill, { backgroundColor: theme.inputBg, borderColor: theme.border }]}>
                            <Ionicons name="time-outline" size={13} color="#10B981" style={{ marginRight: 5 }} />
                            <Text style={[styles.sessionPillText, { color: theme.textMain }]}>{timeRange}</Text>
                          </View>

                          {s.capacity ? (
                            <View style={[styles.sessionPill, { backgroundColor: theme.inputBg, borderColor: theme.border }]}>
                              <Ionicons name="people-outline" size={13} color="#10B981" style={{ marginRight: 5 }} />
                              <Text style={[styles.sessionPillText, { color: theme.textMain }]}>
                                {s.capacity} slot{s.capacity > 1 ? 's' : ''} max
                              </Text>
                            </View>
                          ) : null}
                        </View>

                        {/* Note if provided */}
                        {s.note ? (
                          <View style={[styles.sessionNoteBox, { backgroundColor: 'rgba(16,185,129,0.08)', borderColor: 'rgba(16,185,129,0.25)' }]}>
                            <Ionicons name="information-circle-outline" size={15} color="#10B981" style={{ marginRight: 6 }} />
                            <Text style={[styles.sessionNoteText, { color: theme.textSub }]}>{s.note}</Text>
                          </View>
                        ) : null}

                        {/* Book this session action */}
                        <TouchableOpacity
                          style={styles.sessionBookBtn}
                          activeOpacity={0.85}
                          onPress={() => {
                            setScheduleModalOpen(false);
                            openBooking({
                              date: s.schedule_date,
                              startTime: s.start_time ? s.start_time.slice(0, 5) : undefined,
                              endTime: s.end_time ? s.end_time.slice(0, 5) : undefined,
                              allowDateChange: false,
                            });
                          }}
                        >
                          <Ionicons name="calendar-outline" size={14} color="#FFFFFF" style={{ marginRight: 6 }} />
                          <Text style={styles.sessionBookBtnText}>Book this Session</Text>
                        </TouchableOpacity>
CODE;

$newScheduleSessions = <<< 'CODE'
                        {/* Session details badges */}
                        {(() => {
                          const cap = s.capacity ?? listing?.capacity ?? 10;
                          const slotsLeft = s.available_slots !== undefined ? s.available_slots : cap;
                          const isFull = s.is_available === false || slotsLeft <= 0;

                          return (
                            <>
                              <View style={styles.sessionPillsRow}>
                                <View style={[styles.sessionPill, { backgroundColor: theme.inputBg, borderColor: theme.border }]}>
                                  <Ionicons name="time-outline" size={13} color="#10B981" style={{ marginRight: 5 }} />
                                  <Text style={[styles.sessionPillText, { color: theme.textMain }]}>{timeRange}</Text>
                                </View>

                                <View style={[styles.sessionPill, { backgroundColor: theme.inputBg, borderColor: theme.border }]}>
                                  <Ionicons name="people-outline" size={13} color={isFull ? '#EF4444' : '#10B981'} style={{ marginRight: 5 }} />
                                  <Text style={[styles.sessionPillText, { color: isFull ? '#EF4444' : theme.textMain }]}>
                                    {isFull ? '0 slots left' : `${slotsLeft} of ${cap} slot${cap > 1 ? 's' : ''} available`}
                                  </Text>
                                </View>
                              </View>

                              {/* Note if provided */}
                              {s.note ? (
                                <View style={[styles.sessionNoteBox, { backgroundColor: 'rgba(16,185,129,0.08)', borderColor: 'rgba(16,185,129,0.25)' }]}>
                                  <Ionicons name="information-circle-outline" size={15} color="#10B981" style={{ marginRight: 6 }} />
                                  <Text style={[styles.sessionNoteText, { color: theme.textSub }]}>{s.note}</Text>
                                </View>
                              ) : null}

                              {/* Book this session action */}
                              <TouchableOpacity
                                style={[styles.sessionBookBtn, isFull && { backgroundColor: '#64748B', opacity: 0.6 }]}
                                disabled={isFull}
                                activeOpacity={0.85}
                                onPress={() => {
                                  setScheduleModalOpen(false);
                                  openBooking({
                                    date: s.schedule_date,
                                    startTime: s.start_time ? s.start_time.slice(0, 5) : undefined,
                                    endTime: s.end_time ? s.end_time.slice(0, 5) : undefined,
                                    allowDateChange: false,
                                  });
                                }}
                              >
                                <Ionicons name={isFull ? 'ban' : 'calendar-outline'} size={14} color="#FFFFFF" style={{ marginRight: 6 }} />
                                <Text style={styles.sessionBookBtnText}>
                                  {isFull ? 'Session Fully Booked' : 'Book this Session'}
                                </Text>
                              </TouchableOpacity>
                            </>
                          );
                        })()}
CODE;

if (!str_contains($content, $oldScheduleSessions)) {
    echo "ERROR: oldScheduleSessions not found!\n";
    exit(1);
}
$content = str_replace($oldScheduleSessions, $newScheduleSessions, $content);

// Schedule modal button rename
$oldScheduleBtnText = '<Text style={styles.schedulePrimaryBtnText}>Select Date & Book Tour</Text>';
$newScheduleBtnText = '<Text style={styles.schedulePrimaryBtnText}>Select Date</Text>';
if (!str_contains($content, $oldScheduleBtnText)) {
    echo "ERROR: oldScheduleBtnText not found!\n";
    exit(1);
}
$content = str_replace($oldScheduleBtnText, $newScheduleBtnText, $content);

// 8. Add Duration Details Modal immediately after Schedule Details Modal
$scheduleModalEnd = <<< 'CODE'
            </ScrollView>
          </View>
        </View>
      </Modal>
CODE;

$durationModalCode = <<< 'CODE'

      {/* ── TOUR / ATTRACTION DURATION & DETAILS MODAL (Non-scheduled listings) ── */}
      <Modal
        visible={durationModalOpen}
        transparent
        animationType="slide"
        onRequestClose={() => setDurationModalOpen(false)}
      >
        <View style={styles.modalBackdrop}>
          <View style={[styles.scheduleModalSheet, { backgroundColor: theme.bg, borderTopColor: theme.border }]}>
            {/* Header */}
            <View style={styles.scheduleModalHeader}>
              <View style={{ flex: 1, marginRight: 12 }}>
                <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6, marginBottom: 2 }}>
                  <Ionicons name="time" size={18} color="#10B981" />
                  <Text style={[styles.scheduleModalTitle, { color: theme.textMain }]}>Duration & Details</Text>
                </View>
                <Text style={{ fontSize: 13, color: theme.textSub }} numberOfLines={1}>
                  {listing.title}{listing.area ? ` · ${listing.area}` : ''}
                </Text>
              </View>
              <TouchableOpacity
                onPress={() => setDurationModalOpen(false)}
                style={styles.scheduleModalCloseBtn}
                accessibilityRole="button"
                accessibilityLabel="Close duration details"
              >
                <Ionicons name="close" size={20} color={theme.textMain} />
              </TouchableOpacity>
            </View>

            <ScrollView showsVerticalScrollIndicator={false} contentContainerStyle={{ paddingBottom: 28 }}>
              {/* Duration Overview Card */}
              <View style={[styles.durationHeroCard, { backgroundColor: theme.card, borderColor: theme.border }]}>
                <View style={{ flexDirection: 'row', alignItems: 'center', gap: 10, marginBottom: 8 }}>
                  <View style={[styles.durationIconBox, { backgroundColor: 'rgba(16, 185, 129, 0.12)' }]}>
                    <Ionicons name="hourglass-outline" size={22} color="#10B981" />
                  </View>
                  <View style={{ flex: 1 }}>
                    <Text style={{ fontSize: 12, fontWeight: '700', color: '#10B981', textTransform: 'uppercase' }}>
                      Experience Duration
                    </Text>
                    <Text style={{ fontSize: 18, fontWeight: '800', color: theme.textMain }}>
                      {listing.duration || 'Flexible / Self-Paced'}
                    </Text>
                  </View>
                  <View style={[styles.durationBadge, { backgroundColor: 'rgba(16, 185, 129, 0.15)' }]}>
                    <Text style={{ fontSize: 11, fontWeight: '700', color: '#10B981' }}>Flexible</Text>
                  </View>
                </View>
                <Text style={{ fontSize: 13, color: theme.textSub, lineHeight: 19 }}>
                  This experience runs at a comfortable, flexible pace. You can explore the sights and activities without rigid departure times.
                </Text>
              </View>

              {/* Group Capacity Card */}
              <View style={[styles.durationInfoCard, { backgroundColor: theme.card, borderColor: theme.border }]}>
                <View style={{ flexDirection: 'row', alignItems: 'center', gap: 10 }}>
                  <View style={[styles.durationIconBox, { backgroundColor: 'rgba(59, 130, 246, 0.12)' }]}>
                    <Ionicons name="people-outline" size={20} color="#3B82F6" />
                  </View>
                  <View style={{ flex: 1 }}>
                    <Text style={{ fontSize: 11, fontWeight: '700', color: theme.textSub, textTransform: 'uppercase' }}>
                      Slots / Capacity
                    </Text>
                    <Text style={{ fontSize: 15, fontWeight: '700', color: theme.textMain }}>
                      Up to {listing.capacity ?? 10} participants per group
                    </Text>
                    <Text style={{ fontSize: 12, color: theme.textSub, marginTop: 2 }}>
                      Pricing is per group{listing.price > 0 ? ` (${formatPrice(listing.price)} total)` : ''}. Book once for your entire party.
                    </Text>
                  </View>
                </View>
              </View>

              {/* Recommended Hours */}
              <View style={[styles.durationInfoCard, { backgroundColor: theme.card, borderColor: theme.border }]}>
                <View style={{ flexDirection: 'row', alignItems: 'center', gap: 10 }}>
                  <View style={[styles.durationIconBox, { backgroundColor: 'rgba(245, 158, 11, 0.12)' }]}>
                    <Ionicons name="sunny-outline" size={20} color="#F59E0B" />
                  </View>
                  <View style={{ flex: 1 }}>
                    <Text style={{ fontSize: 11, fontWeight: '700', color: theme.textSub, textTransform: 'uppercase' }}>
                      Operating / Recommended Hours
                    </Text>
                    <Text style={{ fontSize: 15, fontWeight: '700', color: theme.textMain }}>
                      Daily · 8:00 AM – 5:00 PM
                    </Text>
                    <Text style={{ fontSize: 12, color: theme.textSub, marginTop: 2 }}>
                      Available for booking on any date. The guide will coordinate meeting times with you.
                    </Text>
                  </View>
                </View>
              </View>

              {/* Location & Meeting Point */}
              {(listing.address || listing.area) ? (
                <View style={[styles.durationInfoCard, { backgroundColor: theme.card, borderColor: theme.border }]}>
                  <View style={{ flexDirection: 'row', alignItems: 'center', gap: 10 }}>
                    <View style={[styles.durationIconBox, { backgroundColor: 'rgba(16, 185, 129, 0.12)' }]}>
                      <Ionicons name="location-outline" size={20} color="#10B981" />
                    </View>
                    <View style={{ flex: 1 }}>
                      <Text style={{ fontSize: 11, fontWeight: '700', color: theme.textSub, textTransform: 'uppercase' }}>
                        Meeting Point / Location
                      </Text>
                      <Text style={{ fontSize: 15, fontWeight: '700', color: theme.textMain }}>
                        {listing.address || listing.area}
                      </Text>
                      {listing.area && listing.address ? (
                        <Text style={{ fontSize: 12, color: theme.textSub, marginTop: 2 }}>
                          {listing.area}
                        </Text>
                      ) : null}
                    </View>
                  </View>
                </View>
              ) : null}

              {/* Inclusions */}
              {listing.included ? (
                <View style={[styles.durationInfoCard, { backgroundColor: theme.card, borderColor: theme.border }]}>
                  <Text style={{ fontSize: 13, fontWeight: '700', color: theme.textMain, marginBottom: 8 }}>
                    What's Included
                  </Text>
                  <Text style={{ fontSize: 13, color: theme.textSub, lineHeight: 19 }}>
                    {listing.included}
                  </Text>
                </View>
              ) : null}

              {/* Exclusions */}
              {listing.not_included ? (
                <View style={[styles.durationInfoCard, { backgroundColor: theme.card, borderColor: theme.border }]}>
                  <Text style={{ fontSize: 13, fontWeight: '700', color: theme.textMain, marginBottom: 8 }}>
                    Not Included / What to Bring
                  </Text>
                  <Text style={{ fontSize: 13, color: theme.textSub, lineHeight: 19 }}>
                    {listing.not_included}
                  </Text>
                </View>
              ) : null}

              {/* Bottom Action */}
              <View style={{ marginTop: 20 }}>
                <TouchableOpacity
                  activeOpacity={0.88}
                  onPress={() => {
                    setDurationModalOpen(false);
                    openBooking({ allowDateChange: true });
                  }}
                >
                  <LinearGradient
                    colors={gradients.brand}
                    start={{ x: 0, y: 0 }}
                    end={{ x: 1, y: 1 }}
                    style={styles.schedulePrimaryBtn}
                  >
                    <Ionicons name="calendar" size={17} color="#FFFFFF" style={{ marginRight: 8 }} />
                    <Text style={styles.schedulePrimaryBtnText}>Book now</Text>
                  </LinearGradient>
                </TouchableOpacity>

                <TouchableOpacity
                  style={[styles.scheduleSecondaryBtn, { borderColor: theme.border }]}
                  onPress={() => setDurationModalOpen(false)}
                  activeOpacity={0.8}
                >
                  <Text style={[styles.scheduleSecondaryBtnText, { color: theme.textMain }]}>Close</Text>
                </TouchableOpacity>
              </View>
            </ScrollView>
          </View>
        </View>
      </Modal>
CODE;

// Replace only the occurrence inside scheduleModalOpen
$targetScheduleModalEnd = "              </View>\n            </ScrollView>\n          </View>\n        </View>\n      </Modal>\n\n      {/* Reviews from other tourists";
$replacementScheduleModalEnd = "              </View>\n            </ScrollView>\n          </View>\n        </View>\n      </Modal>" . $durationModalCode . "\n\n      {/* Reviews from other tourists";

if (!str_contains($content, $targetScheduleModalEnd)) {
    echo "ERROR: targetScheduleModalEnd not found!\n";
    exit(1);
}
$content = str_replace($targetScheduleModalEnd, $replacementScheduleModalEnd, $content);

// 9. Update Booking Modal Step 1 (Dates, Locked banner, Available Sessions, Guest limit, Price breakdown)
$oldBookingModalDateSection = <<< 'CODE'
                      {/* Header with status badge */}
                      <View style={styles.bookingDateHeaderRow}>
                        <View style={{ flex: 1, marginRight: 8 }}>
                          <Text style={[styles.modalLabel, { color: theme.textSub, marginBottom: 2 }]}>
                            Tour Booking Date
                          </Text>
                          <Text style={[styles.bookingDateDisplay, { color: theme.textMain }]}>
                            {bookingDates[0] ? formatScheduleDate(bookingDates[0]) : 'Select date'}
                          </Text>
                        </View>
                        {canChangeDate ? (
                          <View style={styles.dateEditableBadge}>
                            <Ionicons name="create-outline" size={13} color="#10B981" />
                            <Text style={styles.dateEditableText}>Date Editable</Text>
                          </View>
                        ) : (
                          <View style={styles.dateLockedBadge}>
                            <Ionicons name="lock-closed" size={12} color="#D97706" />
                            <Text style={styles.dateLockedText}>Locked Date</Text>
                          </View>
                        )}
                      </View>

                      {/* Locked Date Info Banner with "Select Date & Book Tour" Unlock Button */}
                      {!canChangeDate ? (
                        <View style={[styles.dateLockedBanner, { backgroundColor: 'rgba(217, 119, 6, 0.08)', borderColor: 'rgba(217, 119, 6, 0.25)' }]}>
                          <View style={{ flexDirection: 'row', alignItems: 'flex-start', gap: 8 }}>
                            <Ionicons name="lock-closed-outline" size={17} color="#D97706" style={{ marginTop: 2 }} />
                            <View style={{ flex: 1 }}>
                              <Text style={[styles.dateLockedBannerTitle, { color: theme.textMain }]}>
                                Booking date is locked to prevent accidental changes
                              </Text>
                              <Text style={[styles.dateLockedBannerSub, { color: theme.textSub }]}>
                                This date matches your scheduled tour session. To pick another available date before confirming, tap below:
                              </Text>
                              <TouchableOpacity
                                style={styles.selectDateBookTourBtn}
                                activeOpacity={0.85}
                                onPress={() => setCanChangeDate(true)}
                              >
                                <Ionicons name="calendar" size={14} color="#FFFFFF" style={{ marginRight: 6 }} />
                                <Text style={styles.selectDateBookTourBtnText}>Select Date & Book Tour</Text>
                              </TouchableOpacity>
                            </View>
                          </View>
                        </View>
                      ) : (
                        <View style={[styles.dateLockedBanner, { backgroundColor: 'rgba(16, 185, 129, 0.08)', borderColor: 'rgba(16, 185, 129, 0.25)' }]}>
                          <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8 }}>
                            <Ionicons name="checkmark-circle-outline" size={17} color="#10B981" />
                            <Text style={[styles.dateLockedBannerTitle, { color: theme.textMain, flex: 1, fontSize: 13 }]}>
                              Tap any available date below to select your new tour date.
                            </Text>
                          </View>
                        </View>
                      )}

                      {/* Calendar Picker (read-only unless canChangeDate is true) */}
                      <CalendarPicker
                        value={bookingDates[0] || ''}
                        onChange={(pickedDate) => {
                          if (!canChangeDate) return;
                          setBookingDates([pickedDate]);
                          const match = schedules.find((s) => s.schedule_date === pickedDate);
                          if (match) {
                            if (match.start_time) setBookingTime(match.start_time.slice(0, 5));
                            if (match.end_time) setBookingEndTime(match.end_time.slice(0, 5));
                          }
                        }}
                        readOnly={!canChangeDate}
                        minDate={getTodayString()}
                        theme={theme}
                        disabledDates={fullyBookedDates}
                      />
CODE;

$newBookingModalDateSection = <<< 'CODE'
                      {/* Header with status badge */}
                      <View style={styles.bookingDateHeaderRow}>
                        <View style={{ flex: 1, marginRight: 8 }}>
                          <Text style={[styles.modalLabel, { color: theme.textSub, marginBottom: 2 }]}>
                            Tour Booking Date
                          </Text>
                          <Text style={[styles.bookingDateDisplay, { color: theme.textMain }]}>
                            {bookingDates[0] ? formatScheduleDate(bookingDates[0]) : 'Select date'}
                          </Text>
                        </View>
                        {hasSchedule ? (
                          canChangeDate ? (
                            <View style={styles.dateEditableBadge}>
                              <Ionicons name="create-outline" size={13} color="#10B981" />
                              <Text style={styles.dateEditableText}>Date Editable</Text>
                            </View>
                          ) : (
                            <View style={styles.dateLockedBadge}>
                              <Ionicons name="lock-closed" size={12} color="#D97706" />
                              <Text style={styles.dateLockedText}>Locked Date</Text>
                            </View>
                          )
                        ) : null}
                      </View>

                      {/* Locked Date Info Banner (Scheduled listings only) */}
                      {hasSchedule ? (
                        !canChangeDate ? (
                          <View style={[styles.dateLockedBanner, { backgroundColor: 'rgba(217, 119, 6, 0.08)', borderColor: 'rgba(217, 119, 6, 0.25)' }]}>
                            <View style={{ flexDirection: 'row', alignItems: 'flex-start', gap: 8 }}>
                              <Ionicons name="lock-closed-outline" size={17} color="#D97706" style={{ marginTop: 2 }} />
                              <View style={{ flex: 1 }}>
                                <Text style={[styles.dateLockedBannerTitle, { color: theme.textMain }]}>
                                  Booking date is locked to prevent accidental changes
                                </Text>
                                <Text style={[styles.dateLockedBannerSub, { color: theme.textSub }]}>
                                  This date matches your scheduled tour session. To select another available date, tap below:
                                </Text>
                                <TouchableOpacity
                                  style={styles.selectDateBookTourBtn}
                                  activeOpacity={0.85}
                                  onPress={() => setCanChangeDate(true)}
                                >
                                  <Ionicons name="calendar" size={14} color="#FFFFFF" style={{ marginRight: 6 }} />
                                  <Text style={styles.selectDateBookTourBtnText}>Select Date</Text>
                                </TouchableOpacity>
                              </View>
                            </View>
                          </View>
                        ) : (
                          <View style={[styles.dateLockedBanner, { backgroundColor: 'rgba(16, 185, 129, 0.08)', borderColor: 'rgba(16, 185, 129, 0.25)' }]}>
                            <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8 }}>
                              <Ionicons name="checkmark-circle-outline" size={17} color="#10B981" />
                              <Text style={[styles.dateLockedBannerTitle, { color: theme.textMain, flex: 1, fontSize: 13 }]}>
                                Tap any available date below to select your new tour date.
                              </Text>
                            </View>
                          </View>
                        )
                      ) : null}

                      {/* Calendar Picker */}
                      <CalendarPicker
                        value={bookingDates[0] || ''}
                        onChange={(pickedDate) => {
                          if (!canChangeDate && hasSchedule) return;
                          setBookingDates([pickedDate]);
                          if (hasSchedule) {
                            const matching = schedules.filter((s) => s.schedule_date === pickedDate);
                            const availMatch = matching.find((s) => s.is_available !== false && (s.available_slots === undefined || s.available_slots > 0)) || matching[0];
                            if (availMatch) {
                              if (availMatch.start_time) setBookingTime(availMatch.start_time.slice(0, 5));
                              if (availMatch.end_time) setBookingEndTime(availMatch.end_time.slice(0, 5));
                            } else {
                              setBookingTime('');
                              setBookingEndTime('');
                            }
                          }
                        }}
                        readOnly={!canChangeDate && hasSchedule}
                        minDate={getTodayString()}
                        theme={theme}
                        disabledDates={fullyBookedDates}
                      />

                      {/* Available Sessions for Selected Date (Scheduled listings only) */}
                      {hasSchedule ? (
                        <View style={{ marginTop: 14 }}>
                          <Text style={[styles.modalLabel, { color: theme.textSub, marginBottom: 8 }]}>
                            Available Sessions for {bookingDates[0] ? formatScheduleDateShort(bookingDates[0]) : 'Selected Date'}
                          </Text>
                          {selectedDateSessions.length > 0 ? (
                            selectedDateSessions.map((session) => {
                              const isSelected = bookingTime === (session.start_time ? session.start_time.slice(0, 5) : '');
                              const cap = session.capacity ?? listing?.capacity ?? 10;
                              const slotsLeft = session.available_slots !== undefined ? session.available_slots : cap;
                              const isFull = session.is_available === false || slotsLeft <= 0;

                              return (
                                <TouchableOpacity
                                  key={session.id}
                                  disabled={isFull}
                                  onPress={() => {
                                    if (session.start_time) setBookingTime(session.start_time.slice(0, 5));
                                    if (session.end_time) setBookingEndTime(session.end_time.slice(0, 5));
                                  }}
                                  style={[
                                    styles.sessionSelectCard,
                                    { backgroundColor: theme.card, borderColor: isSelected ? '#10B981' : theme.border },
                                    isSelected && { borderWidth: 2, backgroundColor: 'rgba(16, 185, 129, 0.08)' },
                                    isFull && { opacity: 0.5, backgroundColor: theme.inputBg },
                                  ]}
                                  activeOpacity={0.8}
                                >
                                  <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }}>
                                    <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8 }}>
                                      <Ionicons
                                        name={isSelected ? 'radio-button-on' : 'radio-button-off'}
                                        size={18}
                                        color={isSelected ? '#10B981' : isFull ? theme.textSub : theme.border}
                                      />
                                      <Text style={{ fontSize: 14, fontWeight: '700', color: isSelected ? '#10B981' : theme.textMain }}>
                                        {session.start_time ? formatTimeLabel(session.start_time.slice(0, 5)) : 'TBD'}
                                        {session.end_time ? ` – ${formatTimeLabel(session.end_time.slice(0, 5))}` : ''}
                                      </Text>
                                    </View>
                                    <View style={[
                                      styles.sessionSlotBadge,
                                      { backgroundColor: isFull ? 'rgba(239, 68, 68, 0.15)' : 'rgba(16, 185, 129, 0.15)' }
                                    ]}>
                                      <Text style={{
                                        fontSize: 11,
                                        fontWeight: '700',
                                        color: isFull ? '#EF4444' : '#10B981'
                                      }}>
                                        {isFull ? 'Fully Booked' : `${slotsLeft} slot${slotsLeft === 1 ? '' : 's'} available`}
                                      </Text>
                                    </View>
                                  </View>
                                  {session.note ? (
                                    <Text style={{ fontSize: 12, color: theme.textSub, marginTop: 4, marginLeft: 26 }}>
                                      {session.note}
                                    </Text>
                                  ) : null}
                                </TouchableOpacity>
                              );
                            })
                          ) : (
                            <View style={[styles.noSessionsCard, { backgroundColor: theme.inputBg, borderColor: theme.border }]}>
                              <Ionicons name="information-circle-outline" size={18} color="#D97706" style={{ marginRight: 8 }} />
                              <Text style={{ fontSize: 13, color: theme.textSub, flex: 1 }}>
                                No tour sessions scheduled for this date. Please pick a highlighted date on the calendar.
                              </Text>
                            </View>
                          )}
                        </View>
                      ) : null}
CODE;

if (!str_contains($content, $oldBookingModalDateSection)) {
    echo "ERROR: oldBookingModalDateSection not found!\n";
    exit(1);
}
$content = str_replace($oldBookingModalDateSection, $newBookingModalDateSection, $content);

// 10. Update Guest Counter max limit and Price breakdown
$oldGuestCounter = <<< 'CODE'
                  <Text style={[styles.modalLabel, { color: theme.textSub }]}>Guests</Text>
                  <View style={styles.guestRow}>
                    <TouchableOpacity
                      style={[styles.stepBtn, { borderColor: theme.border }]}
                      onPress={() => setGuests((g) => Math.max(1, g - 1))}
                    >
                      <Ionicons name="remove" size={20} color={theme.textMain} />
                    </TouchableOpacity>
                    <Text style={[styles.guestCount, { color: theme.textMain }]}>{guests}</Text>
                    <TouchableOpacity
                      style={[styles.stepBtn, { borderColor: theme.border }]}
                      onPress={() => setGuests((g) => g + 1)}
                    >
                      <Ionicons name="add" size={20} color={theme.textMain} />
                    </TouchableOpacity>
                  </View>
CODE;

$newGuestCounter = <<< 'CODE'
                  <Text style={[styles.modalLabel, { color: theme.textSub }]}>Guests</Text>
                  <View style={styles.guestRow}>
                    <TouchableOpacity
                      style={[styles.stepBtn, { borderColor: theme.border }]}
                      onPress={() => setGuests((g) => Math.max(1, g - 1))}
                    >
                      <Ionicons name="remove" size={20} color={theme.textMain} />
                    </TouchableOpacity>
                    <Text style={[styles.guestCount, { color: theme.textMain }]}>{guests}</Text>
                    <TouchableOpacity
                      style={[styles.stepBtn, { borderColor: theme.border }]}
                      onPress={() => {
                        const maxCap = Math.max(1, hasSchedule && currentSession ? (currentSession.available_slots ?? listing?.capacity ?? 20) : (listing?.capacity ?? 20));
                        setGuests((g) => Math.min(maxCap, g + 1));
                      }}
                    >
                      <Ionicons name="add" size={20} color={theme.textMain} />
                    </TouchableOpacity>
                  </View>
                  {listing?.capacity ? (
                    <Text style={{ fontSize: 11, color: theme.textSub, marginTop: 4, marginBottom: 8 }}>
                      Max {hasSchedule && currentSession ? (currentSession.available_slots ?? listing.capacity) : listing.capacity} participants allowed per session.
                    </Text>
                  ) : null}
CODE;

if (!str_contains($content, $oldGuestCounter)) {
    echo "ERROR: oldGuestCounter not found!\n";
    exit(1);
}
$content = str_replace($oldGuestCounter, $newGuestCounter, $content);

// 11. Update price breakdown for Per Group
$oldPriceSummary = <<< 'CODE'
                  ) : listing.price > 0 ? (
                    <View style={styles.priceSummaryBox}>
                      <Text style={[styles.totalLine, { color: theme.textMain }]}>
                        {`Total: ${formatPrice(totalAmount)}`}
                      </Text>
                      <Text style={[styles.priceBreakdownText, { color: theme.textSub }]}>
                        {bookingDates.length > 1
                          ? `${formatPrice(listing.price)} × ${guests} guest${guests > 1 ? 's' : ''} × ${bookingDates.length} days`
                          : `${formatPrice(listing.price)} × ${guests} guest${guests > 1 ? 's' : ''} (1 day)`}
                      </Text>
                    </View>
                  ) : null}
CODE;

$newPriceSummary = <<< 'CODE'
                  ) : listing.price > 0 ? (
                    <View style={styles.priceSummaryBox}>
                      <Text style={[styles.totalLine, { color: theme.textMain }]}>
                        {`Total: ${formatPrice(totalAmount)}`}
                      </Text>
                      <Text style={[styles.priceBreakdownText, { color: theme.textSub }]}>
                        {isPerGroup
                          ? `${formatPrice(listing.price)} (per group${listing.capacity ? ` · up to ${listing.capacity} guests` : ''})`
                          : (bookingDates.length > 1
                              ? `${formatPrice(listing.price)} × ${guests} guest${guests > 1 ? 's' : ''} × ${bookingDates.length} days`
                              : `${formatPrice(listing.price)} × ${guests} guest${guests > 1 ? 's' : ''} (1 day)`)}
                      </Text>
                    </View>
                  ) : null}
CODE;

if (!str_contains($content, $oldPriceSummary)) {
    echo "ERROR: oldPriceSummary not found!\n";
    exit(1);
}
$content = str_replace($oldPriceSummary, $newPriceSummary, $content);

// 12. Add new styles at end of StyleSheet
$oldStyleEnd = <<< 'CODE'
  guideTimeSubtext: {
    fontSize: 11.5,
    lineHeight: 16,
  },
});
CODE;

$newStyleEnd = <<< 'CODE'
  guideTimeSubtext: {
    fontSize: 11.5,
    lineHeight: 16,
  },
  durationHeroCard: {
    borderWidth: 1,
    borderRadius: 16,
    padding: 16,
    marginBottom: 12,
  },
  durationIconBox: {
    width: 40,
    height: 40,
    borderRadius: 12,
    alignItems: 'center',
    justifyContent: 'center',
  },
  durationBadge: {
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 6,
  },
  durationInfoCard: {
    borderWidth: 1,
    borderRadius: 14,
    padding: 14,
    marginBottom: 10,
  },
  sessionSelectCard: {
    borderWidth: 1,
    borderRadius: 12,
    padding: 12,
    marginBottom: 8,
  },
  sessionSlotBadge: {
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 6,
  },
  noSessionsCard: {
    flexDirection: 'row',
    alignItems: 'center',
    borderWidth: 1,
    borderRadius: 12,
    padding: 12,
    marginTop: 4,
  },
});
CODE;

if (!str_contains($content, $oldStyleEnd)) {
    echo "ERROR: oldStyleEnd not found!\n";
    exit(1);
}
$content = str_replace($oldStyleEnd, $newStyleEnd, $content);

file_put_contents($filePath, $content);
echo "SUCCESS: slug.tsx patched cleanly!\n";
