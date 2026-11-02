const fs = require('fs');
let c = fs.readFileSync('app/listing/[slug].tsx', 'utf8');

// 1. Add activeBooking state
const stateTarget = `  const [selectedSessionId, setSelectedSessionId] = useState<number | null>(null);`;
const stateReplacement = `  const [selectedSessionId, setSelectedSessionId] = useState<number | null>(null);
  const [activeBooking, setActiveBooking] = useState<{
    id: number;
    status: string;
    status_label: string;
    booking_date: string;
    booking_time?: string;
  } | null>(null);`;
c = c.replace(stateTarget, stateReplacement);

// 2. In load(), store data.active_booking
const loadTarget = `      setAlreadyBooked(!!data.already_booked);`;
const loadReplacement = `      setAlreadyBooked(!!data.already_booked);
      setActiveBooking(data.active_booking || null);`;
c = c.replace(loadTarget, loadReplacement);

// 3. Update openBooking signature and duplicate check
const openBookingSig = `  const openBooking = async (opts?: {
    date?: string;
    startTime?: string;
    endTime?: string;
    allowDateChange?: boolean;
  }) => {`;

const openBookingNewSig = `  const openBooking = async (opts?: {
    date?: string;
    startTime?: string;
    endTime?: string;
    sessionId?: number;
    allowDateChange?: boolean;
  }) => {`;
c = c.replace(openBookingSig, openBookingNewSig);

const openBookingDuplicateCheck = `    if (!hasSchedule && alreadyBooked) {
      Alert.alert('Already booked', 'You already have an approved booking for this experience.');
      return;
    }`;

const openBookingNewDuplicateCheck = `    if (activeBooking) {
      const statusText = activeBooking.status_label || 'Active';
      const dateText = activeBooking.booking_date ? \` (Date: \${formatScheduleDate(activeBooking.booking_date)})\` : '';
      Alert.alert(
        'Active Booking Exists',
        \`You already have an active booking for this tour (Status: \${statusText}\${dateText}).\\n\\nYou can book this tour again once your current experience is marked as completed.\`
      );
      return;
    }`;
c = c.replace(openBookingDuplicateCheck, openBookingNewDuplicateCheck);

// 4. In openBooking, exact session synchronization from opts.sessionId
const openBookingSessionSync = `      if (schedules.length > 0) {
        const availFirst = schedules.find((s) => s.is_available !== false && (s.available_slots === undefined || s.available_slots > 0)) || schedules[0];
        setSelectedSessionId(availFirst.id);
        if (!initialDate) {
          initialDate = availFirst.schedule_date;
          initialStartTime = initialStartTime || (availFirst.start_time ? availFirst.start_time.slice(0, 5) : '09:00');
          initialEndTime = initialEndTime || (availFirst.end_time ? availFirst.end_time.slice(0, 5) : '17:00');
        }
      }`;

const openBookingSessionSyncNew = `      if (schedules.length > 0) {
        let matchedSession: ApiListingSchedule | undefined;
        if (opts?.sessionId) {
          matchedSession = schedules.find((s) => s.id === opts.sessionId);
        } else if (opts?.date) {
          matchedSession = schedules.find((s) => s.schedule_date === opts.date && (!opts.startTime || s.start_time?.slice(0, 5) === opts.startTime));
        }

        if (matchedSession) {
          setSelectedSessionId(matchedSession.id);
          initialDate = matchedSession.schedule_date;
          initialStartTime = matchedSession.start_time ? matchedSession.start_time.slice(0, 5) : '09:00';
          initialEndTime = matchedSession.end_time ? matchedSession.end_time.slice(0, 5) : '17:00';
        } else {
          const availFirst = schedules.find((s) => s.is_available !== false && (s.available_slots === undefined || s.available_slots > 0)) || schedules[0];
          setSelectedSessionId(availFirst.id);
          if (!initialDate) {
            initialDate = availFirst.schedule_date;
            initialStartTime = initialStartTime || (availFirst.start_time ? availFirst.start_time.slice(0, 5) : '09:00');
            initialEndTime = initialEndTime || (availFirst.end_time ? availFirst.end_time.slice(0, 5) : '17:00');
          }
        }
      }`;
c = c.replace(openBookingSessionSync, openBookingSessionSyncNew);

// 5. In Tour Schedule modal, pass sessionId to openBooking and check activeBooking
const tourScheduleBookBtn = `                              {/* Book this session action */}
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
                              >`;

const tourScheduleBookBtnNew = `                              {/* Book this session action */}
                              <TouchableOpacity
                                style={[styles.sessionBookBtn, isFull && { backgroundColor: '#64748B', opacity: 0.6 }]}
                                disabled={isFull}
                                activeOpacity={0.85}
                                onPress={() => {
                                  if (activeBooking) {
                                    Alert.alert(
                                      'Active Booking Exists',
                                      \`You already have an active booking for this tour (Status: \${activeBooking.status_label || 'Active'}). You can book again once your existing reservation is marked as completed.\`
                                    );
                                    return;
                                  }
                                  setScheduleModalOpen(false);
                                  openBooking({
                                    sessionId: s.id,
                                    date: s.schedule_date,
                                    startTime: s.start_time ? s.start_time.slice(0, 5) : undefined,
                                    endTime: s.end_time ? s.end_time.slice(0, 5) : undefined,
                                    allowDateChange: false,
                                  });
                                }}
                              >`;
c = c.replace(tourScheduleBookBtn, tourScheduleBookBtnNew);

// 6. In submitBooking(), update activeBooking state upon successful booking
const submitBookingSuccess = `      if (hasSchedule) {
        setBookingConfirmedData({
          sessionDate: targetDate,
          sessionTime: finalStartTime ? \`\${formatTimeLabel(finalStartTime)}\${finalEndTime ? \` – \${formatTimeLabel(finalEndTime)}\` : ''}\` : '',
          guests: guests,
          remainingSlots: sessionRemainingSlots,
          totalCapacity: sessionCap,
        });
        setStep('confirmed');
      } else {
        setModalOpen(false);
      }`;

const submitBookingSuccessNew = `      setActiveBooking({
        id: 0,
        status: 'confirmed',
        status_label: 'Confirmed',
        booking_date: targetDate,
        booking_time: finalStartTime || '',
      });
      if (hasSchedule) {
        setBookingConfirmedData({
          sessionDate: targetDate,
          sessionTime: finalStartTime ? \`\${formatTimeLabel(finalStartTime)}\${finalEndTime ? \` – \${formatTimeLabel(finalEndTime)}\` : ''}\` : '',
          guests: guests,
          remainingSlots: sessionRemainingSlots,
          totalCapacity: sessionCap,
        });
        setStep('confirmed');
      } else {
        setModalOpen(false);
      }`;
c = c.replace(submitBookingSuccess, submitBookingSuccessNew);

// 7. In bottom bar, update activeBooking button presentation
const bottomBarBtn = `        {!hasSchedule && alreadyBooked ? (
          <View style={[styles.bookButton, styles.bookedButton]}>
            <Ionicons name="checkmark-circle" size={18} color="#FFFFFF" style={{ marginRight: 7 }} />
            <Text style={styles.bookButtonText}>Already booked</Text>
          </View>
        ) : (`;

const bottomBarBtnNew = `        {activeBooking ? (
          <TouchableOpacity
            style={[styles.bookButton, styles.bookedButton]}
            onPress={() => {
              const statusLabel = activeBooking.status_label || 'Active';
              const dateInfo = activeBooking.booking_date ? \` (Date: \${formatScheduleDate(activeBooking.booking_date)})\` : '';
              Alert.alert(
                'Active Booking Exists',
                \`You already have an active booking for this tour (Status: \${statusLabel}\${dateInfo}).\\n\\nYou can book this tour again once your current experience is marked as completed.\`
              );
            }}
            activeOpacity={0.88}
          >
            <Ionicons name="time-outline" size={18} color="#FFFFFF" style={{ marginRight: 7 }} />
            <Text style={styles.bookButtonText}>{\`Already Booked (\${activeBooking.status_label})\`}</Text>
          </TouchableOpacity>
        ) : (`;
c = c.replace(bottomBarBtn, bottomBarBtnNew);

fs.writeFileSync('app/listing/[slug].tsx', c, 'utf8');
console.log('app/listing/[slug].tsx updated with session sync and duplicate booking prevention');
