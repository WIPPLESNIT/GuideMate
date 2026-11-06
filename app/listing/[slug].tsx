import { Ionicons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import { useLocalSearchParams, useRouter } from 'expo-router';
import React, { useEffect, useState } from 'react';
import {
    ActivityIndicator,
    Alert,
    Image,
    Linking,
    Modal,
    ScrollView,
    StatusBar,
    StyleSheet,
    Text,
    TextInput,
    TouchableOpacity,
    View,
    useWindowDimensions,
} from 'react-native';
import { ApiHotelRoom, ApiListing, ApiListingSchedule, createBooking, getListing, isUnauthorized, PaymentMethod, resolveImage, validateVoucher } from '../../services/api';
import { clearSession, restoreSession } from '../../lib/authStore';
import { usePreferences } from '../../lib/preferences';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useTheme } from '../../lib/theme';
import CalendarPicker from '../../components/CalendarPicker';
import FavoriteHeart from '../../components/FavoriteHeart';
import { findVoucher, getVoucherDiscount } from '../../lib/vouchers';

const QR_EWALLET = require('../../assets/images/payment/qr-ewallet.png');
const QR_INSTAPAY = require('../../assets/images/payment/qr-instapay.png');

type BookingStep = 'details' | 'method' | 'qr' | 'card' | 'confirmed';

type ReviewItem = {
  id: number;
  rating: number;
  comment: string;
  user_name: string;
  created_at: string;
};

// Selectable start and end times for an experience (24h values, shown in 12h format).
const START_TIME_SLOTS = [
  '06:00', '07:00', '08:00', '09:00', '10:00', '11:00',
  '12:00', '13:00', '14:00', '15:00', '16:00', '17:00',
  '18:00', '19:00', '20:00',
];

function formatReviewDate(value: string): string {
  const d = new Date(value);
  if (isNaN(d.getTime())) return value;
  return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

function formatTimeLabel(value: string): string {
  const [hStr, mStr] = value.split(':');
  const h = Number(hStr);
  const period = h >= 12 ? 'PM' : 'AM';
  const h12 = h % 12 === 0 ? 12 : h % 12;
  return `${h12}:${mStr} ${period}`;
}

function formatScheduleDate(dateStr: string): string {
  const parts = dateStr.split('-');
  if (parts.length !== 3) return dateStr;
  const d = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
  if (isNaN(d.getTime())) return dateStr;
  return d.toLocaleDateString(undefined, {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
}

function formatScheduleDateShort(dateStr: string): string {
  const parts = dateStr.split('-');
  if (parts.length !== 3) return dateStr;
  const d = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
  if (isNaN(d.getTime())) return dateStr;
  return d.toLocaleDateString(undefined, {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
}

function formatTimeString(timeStr?: string | null): string {
  if (!timeStr) return '';
  const clean = timeStr.slice(0, 5);
  const [hStr, mStr] = clean.split(':');
  const h = parseInt(hStr, 10);
  if (isNaN(h)) return clean;
  const period = h >= 12 ? 'PM' : 'AM';
  const h12 = h % 12 === 0 ? 12 : h % 12;
  return `${h12}:${mStr || '00'} ${period}`;
}

function hostMessageLabel(listing: Pick<ApiListing, 'owner_name' | 'owner_role'>): string {
  if (listing.owner_role === 'hotel_provider' || listing.owner_role === 'hotel_admin') return 'Message the hotel';
  if (listing.owner_role === 'rental_provider' || listing.owner_role === 'rental_admin') return 'Message the rental partner';
  return listing.owner_name ? `Message ${listing.owner_name}` : 'Message the guide';
}

function getNextCalendarDay(dateStr: string): string {
  const parts = dateStr.split('-');
  if (parts.length === 3) {
    const y = parseInt(parts[0], 10);
    const m = parseInt(parts[1], 10) - 1;
    const d = parseInt(parts[2], 10);
    const utcDate = new Date(Date.UTC(y, m, d + 1));
    const yyyy = utcDate.getUTCFullYear();
    const mm = String(utcDate.getUTCMonth() + 1).padStart(2, '0');
    const dd = String(utcDate.getUTCDate()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd}`;
  }
  return dateStr;
}

function calculateNights(inDate: string, outDate: string): number {
  if (!inDate || !outDate) return 1;
  const inParts = inDate.split('-');
  const outParts = outDate.split('-');
  if (inParts.length === 3 && outParts.length === 3) {
    const inUtc = Date.UTC(parseInt(inParts[0], 10), parseInt(inParts[1], 10) - 1, parseInt(inParts[2], 10));
    const outUtc = Date.UTC(parseInt(outParts[0], 10), parseInt(outParts[1], 10) - 1, parseInt(outParts[2], 10));
    const diffDays = Math.round((outUtc - inUtc) / 86400000);
    return Math.max(1, diffDays);
  }
  return 1;
}

function getTodayString(): string {
  const now = new Date();
  const yyyy = now.getFullYear();
  const mm = String(now.getMonth() + 1).padStart(2, '0');
  const dd = String(now.getDate()).padStart(2, '0');
  return `${yyyy}-${mm}-${dd}`;
}

function getAmenityIcon(amenityName: string): keyof typeof Ionicons.glyphMap {
  const lower = amenityName.toLowerCase();
  if (lower.includes('wifi') || lower.includes('internet')) return 'wifi-outline';
  if (lower.includes('air') || lower.includes('ac') || lower.includes('condition')) return 'snow-outline';
  if (lower.includes('shower') || lower.includes('bath') || lower.includes('tub')) return 'water-outline';
  if (lower.includes('balcony') || lower.includes('terrace') || lower.includes('patio')) return 'sunny-outline';
  if (lower.includes('bar') || lower.includes('mini')) return 'wine-outline';
  if (lower.includes('tv') || lower.includes('cable') || lower.includes('screen')) return 'tv-outline';
  if (lower.includes('coffee') || lower.includes('tea')) return 'cafe-outline';
  if (lower.includes('safe') || lower.includes('deposit') || lower.includes('lock')) return 'lock-closed-outline';
  if (lower.includes('view') || lower.includes('sea') || lower.includes('ocean')) return 'eye-outline';
  if (lower.includes('bed') || lower.includes('king') || lower.includes('queen')) return 'bed-outline';
  if (lower.includes('desk') || lower.includes('work')) return 'briefcase-outline';
  if (lower.includes('pool')) return 'fish-outline';
  if (lower.includes('breakfast') || lower.includes('dining')) return 'restaurant-outline';
  return 'sparkles-outline';
}

export default function ListingDetailScreen() {
  const router = useRouter();
  const { slug } = useLocalSearchParams<{ slug: string }>();
  const { t, formatPrice } = usePreferences();
  const insets = useSafeAreaInsets();
  const { width: windowWidth } = useWindowDimensions();
  const { colors, gradients } = useTheme();

  const [listing, setListing] = useState<ApiListing | null>(null);
  const [hotelRooms, setHotelRooms] = useState<ApiHotelRoom[]>([]);
  const [selectedRoom, setSelectedRoom] = useState<ApiHotelRoom | null>(null);
  const [schedules, setSchedules] = useState<ApiListingSchedule[]>([]);
  const [scheduleModalOpen, setScheduleModalOpen] = useState(false);
  const [durationModalOpen, setDurationModalOpen] = useState(false);
  const [canChangeDate, setCanChangeDate] = useState(false);
  const [bookingViewMode, setBookingViewMode] = useState<'date' | 'session'>('date');
  const [selectedSessionId, setSelectedSessionId] = useState<number | null>(null);
  const [activeBooking, setActiveBooking] = useState<{
    id: number;
    status: string;
    status_label: string;
    booking_date: string;
    booking_time?: string;
  } | null>(null);
  const [bookingConfirmedData, setBookingConfirmedData] = useState<{
    sessionDate: string;
    sessionTime: string;
    guests: number;
    remainingSlots: number;
    totalCapacity: number;
  } | null>(null);

  const selectSessionBtnLabel = bookingViewMode === 'session' ? (
    <Text style={styles.selectDateBookTourBtnText}>Select Session</Text>
  ) : (
    <Text style={styles.selectDateBookTourBtnText}>Select Date</Text>
  );
  const [alreadyBooked, setAlreadyBooked] = useState(false);
  const [favorited, setFavorited] = useState(false);
  const [canReview, setCanReview] = useState(false);
  const [bookedSlots, setBookedSlots] = useState<{ date: string; time: string }[]>([]);
  const [reviews, setReviews] = useState<ReviewItem[]>([]);
  const [reviewsOpen, setReviewsOpen] = useState(false);
  const [gallery, setGallery] = useState<{ id: number; url: string }[]>([]);
  const [activeHeroSlide, setActiveHeroSlide] = useState(0);
  const heroScrollRef = React.useRef<any>(null);
  const [roomDetailModal, setRoomDetailModal] = useState<ApiHotelRoom | null>(null);
  const [activeRoomModalSlide, setActiveRoomModalSlide] = useState(0);
  const roomModalScrollRef = React.useRef<any>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  // Booking modal state
  const [modalOpen, setModalOpen] = useState(false);
  const [step, setStep] = useState<BookingStep>('details');
  const [bookingDates, setBookingDates] = useState<string[]>([]);
  const [hotelCheckIn, setHotelCheckIn] = useState('');
  const [hotelCheckOut, setHotelCheckOut] = useState('');
  const [hotelPickerTarget, setHotelPickerTarget] = useState<'check_in' | 'check_out'>('check_in');
  const [bookingTime, setBookingTime] = useState('');
  const [bookingEndTime, setBookingEndTime] = useState('');
  const [guests, setGuests] = useState(1);
  const [booking, setBooking] = useState(false);

  // Payment state
  const [qrMethod, setQrMethod] = useState<'gcash' | 'instapay'>('gcash');
  const [paymentReference, setPaymentReference] = useState('');
  const [cardName, setCardName] = useState('');
  const [cardNumber, setCardNumber] = useState('');
  const [cardExpiry, setCardExpiry] = useState('');
  const [cardCvv, setCardCvv] = useState('');
  const [voucherExpanded, setVoucherExpanded] = useState(false);
  const [voucherDraft, setVoucherDraft] = useState('');
  const [voucherCode, setVoucherCode] = useState('');

  const theme = {
    bg: colors.bg,
    card: colors.cardAlt,
    cardAlt: colors.cardAlt,
    border: colors.border,
    textMain: colors.text,
    textSub: colors.textSub,
    accent: colors.primary,
    inputBg: colors.chipBg,
  };

  useEffect(() => {
    let active = true;
    const fetchListing = async () => {
      try {
        const data = await getListing(String(slug));
        if (!active) return;
        const rooms = Array.isArray(data.rooms) ? data.rooms : [];
        setListing(data.listing);
        setHotelRooms(rooms);
        if (rooms.length > 0) setSelectedRoom(rooms.find((room) => room.status === 'available') || rooms[0]);
        setSchedules(Array.isArray(data.schedules) ? data.schedules : []);
        setFavorited(!!data.favorited);
        setAlreadyBooked(!!data.already_booked);
        setActiveBooking(data.active_booking || null);
        setCanReview(!!data.can_review);
        setBookedSlots(Array.isArray(data.booked_slots) ? data.booked_slots : []);
        setReviews(Array.isArray(data.reviews) ? data.reviews : []);
        setGallery(Array.isArray(data.gallery) ? data.gallery : []);
      } catch (e) {
        if (active) setError(e instanceof Error ? e.message : 'Failed to load this listing.');
      } finally {
        if (active) setLoading(false);
      }
    };
    void fetchListing();
    return () => {
      active = false;
    };
  }, [slug]);

  const heroSliderWidth = Math.min(windowWidth, 1024);

  const heroImage = listing?.image;
  const listingPhotos = listing?.photos;
  const heroPhotos = React.useMemo(() => {
    const list: string[] = [];
    if (Array.isArray(listingPhotos)) {
      for (const p of listingPhotos) {
        if (p && !list.includes(p)) list.push(p);
      }
    }
    if (heroImage && !list.includes(heroImage)) {
      list.unshift(heroImage);
    }
    if (Array.isArray(gallery)) {
      for (const g of gallery) {
        if (g?.url && !list.includes(g.url)) list.push(g.url);
      }
    }
    return list.length > 0 ? list : (heroImage ? [heroImage] : []);
  }, [listingPhotos, heroImage, gallery]);

  const scrollToHeroSlide = (index: number) => {
    if (index < 0 || index >= heroPhotos.length) return;
    heroScrollRef.current?.scrollTo({ x: index * heroSliderWidth, animated: true });
    setActiveHeroSlide(index);
  };

  const scheduleSummaryLabel = React.useMemo(() => {
    if (schedules.length === 1) {
      return formatScheduleDateShort(schedules[0].schedule_date);
    }
    if (schedules.length > 1) {
      return `${schedules.length} sessions available`;
    }
    return listing?.duration || 'Daily departures';
  }, [schedules, listing?.duration]);

  const modalRoomPhotos = React.useMemo(() => {
    if (!roomDetailModal) return [];
    const list: string[] = [];
    if (Array.isArray(roomDetailModal.photos)) {
      for (const p of roomDetailModal.photos) {
        if (p && !list.includes(p)) list.push(p);
      }
    }
    if (roomDetailModal.image && !list.includes(roomDetailModal.image)) {
      list.unshift(roomDetailModal.image);
    }
    return list;
  }, [roomDetailModal]);

  const priceLabel = listing ? (listing.price > 0 ? formatPrice(listing.price) : t('free')) : '';

  const openBooking = async (opts?: {
    date?: string;
    startTime?: string;
    endTime?: string;
    sessionId?: number;
    allowDateChange?: boolean;
  }) => {
    if (activeBooking) {
      const statusText = activeBooking.status_label || 'Active';
      const dateText = activeBooking.booking_date ? ` (Date: ${formatScheduleDate(activeBooking.booking_date)})` : '';
      Alert.alert(
        'Active Booking Exists',
        `You already have an active booking for this tour (Status: ${statusText}${dateText}).\n\nYou can book this tour again once your current experience is marked as completed.`
      );
      return;
    }
    // restoreSession re-applies the auth token (so the booking request is
    // authorized even right after an app reload) and tells us if we're logged in.
    const user = await restoreSession();
    if (!user) {
      Alert.alert('Sign in required', 'Please sign in to book this experience.', [
        { text: 'Cancel', style: 'cancel' },
        { text: 'Sign in', onPress: () => router.push('/(auth)/login') },
      ]);
      return;
    }
    const todayStr = getTodayString();
    const tomorrowStr = getNextCalendarDay(todayStr);
    const dayAfterTomorrowStr = getNextCalendarDay(tomorrowStr);
    setHotelCheckIn(tomorrowStr);
    setHotelCheckOut(dayAfterTomorrowStr);
    setHotelPickerTarget('check_in');

    if (!isHotel) {
      let initialDate = opts?.date;
      let initialStartTime = opts?.startTime;
      let initialEndTime = opts?.endTime;

      if (schedules.length > 0) {
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
      }
      if (!initialDate) {
        if (schedules.length > 0) {
          initialDate = schedules[0].schedule_date;
          initialStartTime = initialStartTime || (schedules[0].start_time ? schedules[0].start_time.slice(0, 5) : '09:00');
          initialEndTime = initialEndTime || (schedules[0].end_time ? schedules[0].end_time.slice(0, 5) : '17:00');
        } else {
          initialDate = tomorrowStr;
          initialStartTime = initialStartTime || '09:00';
          initialEndTime = initialEndTime || '17:00';
        }
      } else {
        const match = schedules.find((s) => s.schedule_date === initialDate);
        if (match) {
          initialStartTime = initialStartTime || (match.start_time ? match.start_time.slice(0, 5) : '09:00');
          initialEndTime = initialEndTime || (match.end_time ? match.end_time.slice(0, 5) : '17:00');
        }
      }

      updateBookingDates(initialDate ? [initialDate] : [tomorrowStr]);
      setBookingTime(initialStartTime || '09:00');
      setBookingEndTime(initialEndTime || '17:00');
    } else {
      setBookingDates([]);
      setBookingTime('');
      setBookingEndTime('');
    }

    setCanChangeDate(schedules.length === 0 ? true : (opts?.allowDateChange ?? false));
    if (schedules.length > 0) {
      setBookingViewMode('session');
    } else {
      setBookingViewMode('date');
    }
    setGuests(1);
    setStep('details');
    setQrMethod('gcash');
    setPaymentReference('');
    setCardName('');
    setCardNumber('');
    setCardExpiry('');
    setCardCvv('');
    setVoucherExpanded(false);
    setVoucherDraft('');
    setVoucherCode('');
    setModalOpen(true);
  };

  const isHotel = listing?.category === 'Hotels' || (listing as any)?.category_slug === 'hotels';
  const hasSchedule = !isHotel && schedules.length > 0;
  const isPerGroup = (listing?.price_unit || '').toLowerCase().includes('group');

  const totalAttractionCapacity = Math.max(1, listing?.total_capacity ?? listing?.capacity ?? 10);
  const activeTargetDate = bookingDates[0] || '';
  const dateBookedCount = (activeTargetDate && listing?.date_booked_counts?.[activeTargetDate] !== undefined)
    ? (listing.date_booked_counts[activeTargetDate] ?? 0)
    : (listing?.booked_count ?? 0);
  const attractionAvailableSlots = Math.max(
    0,
    listing?.available_slots !== undefined && !activeTargetDate
      ? listing.available_slots
      : (totalAttractionCapacity - dateBookedCount)
  );
  const isAttractionFullyBooked = !isHotel && (hasSchedule
    ? (schedules.length > 0 && schedules.every((s) => s.is_available === false || (s.available_slots !== undefined && s.available_slots <= 0)))
    : attractionAvailableSlots <= 0);

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

  // Set of "YYYY-MM-DD|HH:MM" slots already taken, and dates where every slot
  // is taken (so the calendar can fully disable them).
  const fullyBookedDates = React.useMemo(() => {
    const counts: Record<string, number> = {};
    bookedSlots.forEach((s) => {
      counts[s.date] = (counts[s.date] ?? 0) + 1;
    });
    return Object.keys(counts).filter((d) => counts[d] >= START_TIME_SLOTS.length);
  }, [bookedSlots]);
  const updateBookingDates = (dates: string[]) => {
    setBookingDates(dates);
    if (!isHotel && dates.length > 0) {
      const match = schedules.find((s) => s.schedule_date === dates[0]);
      if (match?.start_time) setBookingTime(match.start_time.slice(0, 5));
      if (match?.end_time) setBookingEndTime(match.end_time.slice(0, 5));
    }
  };

  const goToPayment = () => {
    if (isHotel) {
      if (!hotelCheckIn) {
        Alert.alert('Arrival date required', 'Please select your check-in date.');
        return;
      }
      if (!hotelCheckOut) {
        Alert.alert('Departure date required', 'Please select your check-out date.');
        return;
      }
      if (hotelCheckOut < hotelCheckIn) {
        Alert.alert('Invalid dates', 'Check-out date cannot be earlier than check-in date.');
        return;
      }
      if (hotelCheckOut === hotelCheckIn) {
        Alert.alert('Invalid stay', 'Check-out date must be at least one day after check-in date. A minimum stay is 1 night.');
        return;
      }
    } else {
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

      if (!hasSchedule) {
        if (attractionAvailableSlots <= 0) {
          Alert.alert('Fully Booked', 'This attraction currently has no slots available. Please select another date.');
          return;
        }
        if (guests > attractionAvailableSlots) {
          Alert.alert('Capacity Exceeded', `Only ${attractionAvailableSlots} slot${attractionAvailableSlots === 1 ? '' : 's'} available. Please adjust the number of guests.`);
          return;
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
    // Free listings skip the payment step entirely.
    if (totalAmount <= 0) {
      submitBooking('card');
      return;
    }
    setStep('method');
  };

  const formatCardNumber = (text: string) => {
    const digits = text.replace(/\D/g, '').slice(0, 16);
    const groups = digits.match(/.{1,4}/g);
    setCardNumber(groups ? groups.join(' ') : '');
  };

  const formatExpiry = (text: string) => {
    const digits = text.replace(/\D/g, '').slice(0, 4);
    if (digits.length <= 2) {
      setCardExpiry(digits);
    } else {
      setCardExpiry(`${digits.slice(0, 2)}/${digits.slice(2)}`);
    }
  };

  const applyVoucher = async () => {
    const voucher = findVoucher(voucherDraft);
    if (!voucher) {
      Alert.alert('Invalid voucher', 'Enter one of the vouchers shown in the Sale tab.');
      return;
    }
    if (subtotal < voucher.minSpend) {
      Alert.alert('Voucher not available', `This voucher needs a minimum spend of ${formatPrice(voucher.minSpend)}.`);
      return;
    }
    try {
      const check = await validateVoucher(voucher.code, subtotal);
      if (!check.valid) {
        Alert.alert('Voucher not available', check.message);
        return;
      }
    } catch (e) {
      if (isUnauthorized(e)) {
        Alert.alert('Sign in required', 'Sign in to redeem a voucher. Each code can only be used once per account.', [
          { text: 'Cancel', style: 'cancel' },
          { text: 'Sign in', onPress: () => router.push('/(auth)/login') },
        ]);
        return;
      }
      Alert.alert('Could not apply voucher', e instanceof Error ? e.message : 'Please try again.');
      return;
    }

    setVoucherCode(voucher.code);
    setVoucherDraft(voucher.code);
    setVoucherExpanded(false);
  };

  const clearVoucher = () => {
    setVoucherCode('');
    setVoucherDraft('');
    setVoucherExpanded(false);
  };

  const renderVoucherControls = () => (
    <View style={[styles.voucherCard, { backgroundColor: theme.card, borderColor: theme.border }]}>
      {voucherCode ? (
        <View style={styles.voucherApplied}>
          <View style={[styles.voucherAppliedIcon, { backgroundColor: theme.inputBg }]}>
            <Ionicons name="checkmark-circle" size={20} color={theme.accent} />
          </View>
          <View style={{ flex: 1, minWidth: 0 }}>
            <Text style={[styles.voucherSummaryTitle, { color: theme.textMain }]} numberOfLines={1}>
              {appliedVoucher?.code} applied
            </Text>
            <Text style={[styles.voucherSummaryText, { color: theme.textSub }]} numberOfLines={1}>
              {voucherDiscount > 0
                ? `You save ${formatPrice(voucherDiscount)}`
                : `Min. spend ${formatPrice(appliedVoucher?.minSpend ?? 0)}`}
            </Text>
          </View>
          <TouchableOpacity onPress={clearVoucher} hitSlop={8} activeOpacity={0.85}>
            <Text style={[styles.voucherClear, { color: theme.accent }]}>Remove</Text>
          </TouchableOpacity>
        </View>
      ) : (
        <>
          <TouchableOpacity
            style={styles.voucherToggle}
            activeOpacity={0.85}
            onPress={() => {
              setVoucherDraft(voucherCode);
              setVoucherExpanded((value) => !value);
            }}
          >
            <Ionicons name="pricetag-outline" size={18} color={theme.accent} />
            <View style={{ flex: 1, minWidth: 0, marginHorizontal: 10 }}>
              <Text style={[styles.voucherToggleTitle, { color: theme.textMain }]}>Have a voucher?</Text>
              <Text style={[styles.voucherToggleSub, { color: theme.textSub }]} numberOfLines={1}>
                Enter a Sale tab code
              </Text>
            </View>
            <Ionicons name={voucherExpanded ? 'chevron-up' : 'chevron-down'} size={18} color={theme.textSub} />
          </TouchableOpacity>

          {voucherExpanded ? (
            <View style={styles.voucherEntryRow}>
              <View style={[styles.voucherInputWrap, { backgroundColor: theme.inputBg, borderColor: theme.border }]}>
                <TextInput
                  style={[styles.voucherInput, { color: theme.textMain }]}
                  placeholder="CEBU6"
                  placeholderTextColor={theme.textSub}
                  value={voucherDraft}
                  onChangeText={setVoucherDraft}
                  autoCapitalize="characters"
                  autoCorrect={false}
                  returnKeyType="done"
                  onSubmitEditing={applyVoucher}
                />
              </View>
              <TouchableOpacity
                style={[styles.voucherApplyBtn, { backgroundColor: theme.accent }]}
                activeOpacity={0.85}
                onPress={applyVoucher}
              >
                <Text style={styles.voucherApplyText}>Apply</Text>
              </TouchableOpacity>
            </View>
          ) : null}
        </>
      )}

      {voucherCode && voucherDiscount <= 0 ? (
        <Text style={[styles.voucherHint, { color: theme.textSub }]}>
          This code is saved, but the current total is below the minimum spend.
        </Text>
      ) : null}
    </View>
  );

  const submitBooking = async (
    method: PaymentMethod,
    reference?: string,
    cardLast4?: string
  ) => {
    if (!listing || booking) {
      return;
    }
    setBooking(true);
    try {
      const targetDate = isHotel ? hotelCheckIn : (bookingDates[0] || getNextCalendarDay(getTodayString()));
      let finalStartTime = bookingTime;
      let finalEndTime = bookingEndTime;
      if (!isHotel && (!finalStartTime || !finalEndTime)) {
        const match = schedules.find((s) => s.schedule_date === targetDate) || schedules[0];
        if (match) {
          finalStartTime = finalStartTime || (match.start_time ? match.start_time.slice(0, 5) : '09:00');
          finalEndTime = finalEndTime || (match.end_time ? match.end_time.slice(0, 5) : '17:00');
        } else {
          finalStartTime = finalStartTime || '09:00';
          finalEndTime = finalEndTime || '17:00';
        }
      }

      const bookingRes = await createBooking({
        listing_id: listing.id,
        schedule_id: selectedSessionId || undefined,
        room_id: isHotel ? selectedRoom?.id : undefined,
        check_in_date: isHotel ? hotelCheckIn : undefined,
        check_out_date: isHotel ? hotelCheckOut : undefined,
        booking_date: isHotel ? hotelCheckIn : targetDate,
        booking_dates: isHotel ? [hotelCheckIn, hotelCheckOut] : [targetDate],
        booking_time: isHotel ? (listing.check_in_time ? listing.check_in_time.slice(0, 5) : '14:00') : finalStartTime,
        booking_end_time: isHotel ? (listing.check_out_time ? listing.check_out_time.slice(0, 5) : '12:00') : finalEndTime,
        guests,
        payment_method: method,
        payment_reference: reference,
        card_last4: cardLast4,
        promo_code: voucherDiscount > 0 && appliedVoucher ? appliedVoucher.code : undefined,
      });

      // Synchronize booking state immediately without manual refresh
      setAlreadyBooked(true);
      if (!isHotel && targetDate) {
        setBookedSlots((prev) => [...prev, { date: targetDate, time: finalStartTime || '00:00' }]);
      }
      let sessionRemainingSlots = 0;
      let sessionCap = 10;
      if (hasSchedule && targetDate) {
        setSchedules((prev) =>
          prev.map((s) => {
            const isTargetSession = selectedSessionId ? s.id === selectedSessionId : (s.schedule_date === targetDate && (!finalStartTime || s.start_time?.slice(0, 5) === finalStartTime));
            if (isTargetSession) {
              const prevBooked = s.booked_count ?? 0;
              const newBooked = prevBooked + guests;
              const cap = s.capacity ?? listing.capacity ?? 10;
              const newSlots = Math.max(0, cap - newBooked);
              sessionRemainingSlots = newSlots;
              sessionCap = cap;
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
      if (!isHotel) {
        setListing((prev) => {
          if (!prev) return prev;
          const addedGuests = isPerGroup ? 1 : guests;
          const prevBooked = prev.booked_count ?? 0;
          const newBooked = prevBooked + addedGuests;
          const totalCap = prev.total_capacity ?? prev.capacity ?? 10;
          const newAvail = Math.max(0, totalCap - newBooked);
          const prevDateCounts = prev.date_booked_counts ? { ...prev.date_booked_counts } : {};
          if (targetDate) {
            prevDateCounts[targetDate] = (prevDateCounts[targetDate] ?? 0) + addedGuests;
          }
          return {
            ...prev,
            booked_count: newBooked,
            available_slots: newAvail,
            is_available: newAvail > 0,
            date_booked_counts: prevDateCounts,
          };
        });
      }
      setCanChangeDate(!hasSchedule);

      setVoucherCode('');
      setVoucherDraft('');
      setActiveBooking({
        id: bookingRes?.booking?.id || 0,
        status: (bookingRes as any)?.booking?.status || 'pending',
        status_label: (bookingRes as any)?.booking?.status ? (((bookingRes as any).booking.status.charAt(0).toUpperCase()) + (bookingRes as any).booking.status.slice(1)) : 'Pending',
        booking_date: targetDate,
        booking_time: finalStartTime || '',
      });
      if (hasSchedule) {
        setBookingConfirmedData({
          sessionDate: targetDate,
          sessionTime: finalStartTime ? `${formatTimeLabel(finalStartTime)}${finalEndTime ? ` – ${formatTimeLabel(finalEndTime)}` : ''}` : '',
          guests: guests,
          remainingSlots: sessionRemainingSlots,
          totalCapacity: sessionCap,
        });
        setStep('confirmed');
      } else {
        setModalOpen(false);
      }
      const datesSummary = isHotel
        ? `${hotelNights} night${hotelNights > 1 ? 's' : ''} (${hotelCheckIn} to ${hotelCheckOut})`
        : targetDate;
      const timeSummary = isHotel
        ? ` · Check-in: ${listing.check_in_time_label || '2:00 PM'} · Check-out: ${listing.check_out_time_label || '12:00 PM'}`
        : (finalStartTime
            ? ` from ${formatTimeLabel(finalStartTime)}${finalEndTime ? ` to ${formatTimeLabel(finalEndTime)}` : ''}`
            : '');
      Alert.alert(
        'Payment received',
        `Your payment for "${listing.title}" on ${datesSummary}${timeSummary} was received. The partner still needs to confirm this booking. Track it under Trips — it will show as Awaiting confirmation until they accept it.`,
        [
          { text: 'View Trips', onPress: () => router.replace('/(tabs)/trips') },
          { text: 'OK' },
        ]
      );
    } catch (e) {
      if ((e as any)?.data?.active_booking) {
        setActiveBooking((e as any).data.active_booking);
        setAlreadyBooked(true);
      }
      if (isUnauthorized(e)) {
        await clearSession();
        setModalOpen(false);
        Alert.alert('Session expired', 'Please sign in again to complete your booking.', [
          { text: 'Cancel', style: 'cancel' },
          { text: 'Sign in', onPress: () => router.push('/(auth)/login') },
        ]);
      } else {
        const errorMsg = e instanceof Error ? e.message : 'Please try again.';
        const isDuplicate = errorMsg.toLowerCase().includes('already have an active booking');
        Alert.alert(
          isDuplicate ? 'Active Booking Exists' : 'Booking failed',
          errorMsg
        );
      }
    } finally {
      setBooking(false);
    }
  };

  const confirmQrPaid = () => {
    if (booking) return;
    if (paymentReference.trim().length < 4) {
      Alert.alert('Reference required', 'Enter the reference / confirmation number from your payment app so the admin can verify it.');
      return;
    }
    submitBooking(qrMethod, paymentReference.trim());
  };

  const confirmCardPaid = () => {
    if (booking) return;
    const digits = cardNumber.replace(/\D/g, '');
    if (cardName.trim().length < 2) {
      Alert.alert('Name required', 'Enter the cardholder name.');
      return;
    }
    if (digits.length < 15) {
      Alert.alert('Invalid card number', 'Please enter a valid card number.');
      return;
    }
    if (!/^\d{2}\/\d{2}$/.test(cardExpiry)) {
      Alert.alert('Invalid expiry', 'Enter the expiry date as MM/YY.');
      return;
    }
    if (cardCvv.length < 3) {
      Alert.alert('Invalid CVV', 'Enter the 3-digit security code.');
      return;
    }
    submitBooking('card', undefined, digits.slice(-4));
  };

  if (loading) {
    return (
      <View style={[styles.center, { backgroundColor: theme.bg }]}>
        <ActivityIndicator size="large" color={theme.accent} />
      </View>
    );
  }

  if (error || !listing) {
    return (
      <View style={[styles.center, { backgroundColor: theme.bg }]}>
        <Text style={{ color: theme.textMain, marginBottom: 12, textAlign: 'center', paddingHorizontal: 30 }}>
          {error || 'Listing not found.'}
        </Text>
        <TouchableOpacity onPress={() => router.back()}>
          <Text style={{ color: theme.accent, fontWeight: '700' }}>Go back</Text>
        </TouchableOpacity>
      </View>
    );
  }

  return (
    <View style={[styles.container, { backgroundColor: theme.bg }]}>
      <StatusBar barStyle="light-content" />

      <ScrollView showsVerticalScrollIndicator={false} contentContainerStyle={{ paddingBottom: 120 + insets.bottom }}>
        <View style={[styles.heroContainer, { width: heroSliderWidth, alignSelf: 'center' }]}>
          {heroPhotos.length > 0 ? (
            <ScrollView
              ref={heroScrollRef}
              horizontal
              pagingEnabled
              nestedScrollEnabled
              decelerationRate="fast"
              showsHorizontalScrollIndicator={false}
              onScroll={(e) => {
                const offsetX = e.nativeEvent.contentOffset.x;
                const idx = Math.round(offsetX / heroSliderWidth);
                if (idx !== activeHeroSlide && idx >= 0 && idx < heroPhotos.length) {
                  setActiveHeroSlide(idx);
                }
              }}
              onMomentumScrollEnd={(e) => {
                const offsetX = e.nativeEvent.contentOffset.x;
                const idx = Math.round(offsetX / heroSliderWidth);
                if (idx !== activeHeroSlide && idx >= 0 && idx < heroPhotos.length) {
                  setActiveHeroSlide(idx);
                }
              }}
              scrollEventThrottle={16}
              style={{ width: heroSliderWidth, height: 340 }}
            >
              {heroPhotos.map((p, idx) => (
                <View key={idx} style={{ width: heroSliderWidth, height: 340 }}>
                  <Image
                    source={{ uri: resolveImage(p) }}
                    style={[styles.hero, { width: heroSliderWidth, height: 340 }]}
                  />
                </View>
              ))}
            </ScrollView>
          ) : (
            <View style={[styles.hero, { width: heroSliderWidth, height: 340, backgroundColor: '#1E293B', alignItems: 'center', justifyContent: 'center' }]}>
              <Ionicons name="business-outline" size={48} color="#94A3B8" />
              <Text style={{ color: '#94A3B8', marginTop: 8, fontSize: 13, fontWeight: '600' }}>No photo uploaded</Text>
            </View>
          )}

          {/* Smooth dark gradient overlay for text readability */}
          <LinearGradient
            colors={['rgba(0,0,0,0.3)', 'transparent', 'rgba(11,13,18,0.88)']}
            style={styles.heroOverlay}
            pointerEvents="none"
          />

          {/* Top navigation controls: Back and Favorite */}
          <TouchableOpacity
            style={styles.backButton}
            onPress={() => router.back()}
            activeOpacity={0.8}
            accessibilityRole="button"
            accessibilityLabel="Go back"
          >
            <Ionicons name="arrow-back" size={22} color="#FFFFFF" />
          </TouchableOpacity>

          <FavoriteHeart listingId={listing.id} favorited={favorited} size={40} style={styles.favButton} />

          {/* Slide Indicator Dots & Counter Badge */}
          {heroPhotos.length > 1 ? (
            <View style={styles.heroIndicatorRow} pointerEvents="box-none">
              <View style={styles.heroDotsContainer}>
                {heroPhotos.map((_, idx) => (
                  <TouchableOpacity
                    key={idx}
                    onPress={() => scrollToHeroSlide(idx)}
                    style={[
                      styles.heroDot,
                      idx === activeHeroSlide ? styles.heroDotActive : styles.heroDotInactive,
                    ]}
                    accessibilityRole="button"
                    accessibilityLabel={`Go to slide ${idx + 1}`}
                  />
                ))}
              </View>
              <View style={styles.heroCounterPill} pointerEvents="none">
                <Ionicons name="camera-outline" size={11} color="#FFFFFF" style={{ marginRight: 4 }} />
                <Text style={styles.heroCounterText}>{activeHeroSlide + 1} / {heroPhotos.length}</Text>
              </View>
            </View>
          ) : null}

          {/* Location & Title overlay at bottom of hero */}
          <View style={styles.heroTextWrap} pointerEvents="none">
            {listing.area ? (
              <View style={styles.areaChip}>
                <Ionicons name="location-sharp" size={12} color="#FFFFFF" />
                <Text style={styles.areaChipText}>{listing.area}</Text>
              </View>
            ) : null}
            <Text style={styles.heroTitle}>{listing.title}</Text>
          </View>
        </View>

        <View style={styles.body}>
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
            <TouchableOpacity
              style={[styles.factCard, { backgroundColor: theme.card, borderColor: theme.border }]}
              activeOpacity={0.7}
              onPress={() => setReviewsOpen(true)}
              accessibilityRole="button"
              accessibilityLabel="View reviews from other tourists"
            >
              <Ionicons name="star" size={20} color="#FBBF24" />
              <Text style={[styles.factLabel, { color: theme.textSub }]}>Rating</Text>
              <Text style={[styles.factValue, { color: theme.textMain }]}>
                {listing.rating > 0 ? `${listing.rating} (${listing.review_count})` : 'No reviews'}
              </Text>
              <View style={styles.factLinkRow}>
                <Text style={[styles.factLink, { color: '#10B981' }]}>See reviews</Text>
                <Ionicons name="chevron-forward" size={12} color="#10B981" />
              </View>
            </TouchableOpacity>
          </View>

          {listing.category ? (
            <Text style={[styles.metaLine, { color: theme.textSub }]}>
              {listing.category}{listing.owner_name ? ` · by ${listing.owner_name}` : ''}
            </Text>
          ) : null}

          {listing.owner_id ? (
            <TouchableOpacity
              style={[styles.messageGuideBtn, { borderColor: '#F97316' }]}
              activeOpacity={0.85}
              onPress={() => router.push(`/chat/${listing.owner_id}`)}
              accessibilityRole="button"
              accessibilityLabel={hostMessageLabel(listing)}
            >
              <Ionicons name="chatbubble-ellipses-outline" size={18} color="#F97316" style={{ marginRight: 8 }} />
              <Text style={[styles.messageGuideText, { color: '#F97316' }]}>
                {hostMessageLabel(listing)}
              </Text>
            </TouchableOpacity>
          ) : null}

          {isHotel && hotelRooms.length > 0 ? (
            <>
              <Text style={[styles.sectionTitle, { color: theme.textMain }]}>Available Rooms & Suites</Text>
              <Text style={[styles.about, { color: theme.textSub, marginBottom: 14 }]}>
                Choose your room. Reserve with a partial deposit now — pay the rest at hotel check-in.
              </Text>
              {hotelRooms.map((room) => {
                const isAvail = room.status === 'available';
                const isCurrent = selectedRoom?.id === room.id;
                const roomDepPct = room.deposit_percentage ?? 20;
                const roomDepNow = Math.round((room.price_per_night * roomDepPct) / 100);
                const roomBal = Math.max(0, room.price_per_night - roomDepNow);
                const roomPhoto = (room.photos && room.photos.length > 0) ? room.photos[0] : room.image;

                return (
                  <TouchableOpacity
                    key={room.id}
                    activeOpacity={0.92}
                    onPress={() => {
                      setSelectedRoom(room);
                      setRoomDetailModal(room);
                      setActiveRoomModalSlide(0);
                    }}
                    style={[
                      styles.roomCard,
                      {
                        backgroundColor: theme.cardAlt || '#1E2330',
                        borderColor: isCurrent ? '#10B981' : theme.border,
                        borderWidth: isCurrent ? 2 : 1,
                      },
                      !isAvail && { opacity: 0.65 },
                    ]}
                    accessibilityRole="button"
                    accessibilityLabel={`${room.name}, ${room.bed_type || '1 King Bed'}, ${formatPrice(room.price_per_night)} per night`}
                  >
                    <View style={styles.roomImageWrap}>
                      {roomPhoto ? (
                        <Image source={{ uri: resolveImage(roomPhoto) }} style={styles.roomImage} />
                      ) : (
                        <View style={[styles.roomImage, { backgroundColor: '#1E293B', alignItems: 'center', justifyContent: 'center' }]}>
                          <Ionicons name="bed-outline" size={36} color="#94A3B8" />
                          <Text style={{ color: '#94A3B8', marginTop: 4, fontSize: 12, fontWeight: '600' }}>No photo uploaded</Text>
                        </View>
                      )}
                      <View style={styles.roomTapInspectBadge}>
                        <Ionicons name="expand-outline" size={12} color="#FFFFFF" style={{ marginRight: 4 }} />
                        <Text style={styles.roomTapInspectText}>Tap for room details</Text>
                      </View>
                    </View>

                    <View style={styles.roomBody}>
                      <View style={styles.roomHeaderRow}>
                        <Text style={[styles.roomTitle, { color: theme.textMain }]} numberOfLines={1}>
                          {room.name}
                        </Text>
                        <View
                          style={[
                            styles.roomBadge,
                            {
                              backgroundColor:
                                room.status === 'available'
                                  ? '#DCFCE7'
                                  : room.status === 'blocked'
                                  ? '#FEF3C7'
                                  : '#FEE2E2',
                            },
                          ]}
                        >
                          <Text
                            style={[
                              styles.roomBadgeText,
                              {
                                color:
                                  room.status === 'available'
                                    ? '#166534'
                                    : room.status === 'blocked'
                                    ? '#92400E'
                                    : '#991B1B',
                              },
                            ]}
                          >
                            {room.status === 'available'
                              ? 'Available'
                              : room.status === 'blocked'
                              ? 'Blocked'
                              : 'Booked'}
                          </Text>
                        </View>
                      </View>

                      <Text style={[styles.roomSpecs, { color: theme.textSub }]}>
                        {room.bed_type || '1 King Bed'} · Max {room.max_guests || 2} guests
                      </Text>

                      {room.description ? (
                        <Text style={[styles.roomDesc, { color: theme.textSub }]} numberOfLines={2}>
                          {room.description}
                        </Text>
                      ) : null}

                      <View style={styles.roomBottomRow}>
                        <View style={{ flex: 1, marginRight: 10 }}>
                          <Text style={[styles.roomPrice, { color: colors.accent }]}>
                            {formatPrice(room.price_per_night)}
                            <Text style={[styles.roomPriceUnit, { color: theme.textSub }]}> / night</Text>
                          </Text>
                          <Text style={styles.roomDepositHint}>
                            {roomDepPct}% deposit ({formatPrice(roomDepNow)}) today · {formatPrice(roomBal)} at check-in
                          </Text>
                        </View>

                        <TouchableOpacity
                          disabled={!isAvail}
                          onPress={(e) => {
                            e.stopPropagation?.();
                            setSelectedRoom(room);
                            if (isCurrent) {
                              openBooking();
                            }
                          }}
                          style={[
                            styles.roomSelectBtn,
                            isCurrent
                              ? { backgroundColor: '#10B981', borderColor: '#10B981' }
                              : isAvail
                              ? { backgroundColor: 'transparent', borderColor: '#10B981', borderWidth: 1.5 }
                              : { backgroundColor: theme.border, borderColor: theme.border },
                          ]}
                          activeOpacity={0.85}
                        >
                          <Text
                            style={[
                              styles.roomSelectBtnText,
                              { color: isCurrent ? '#FFFFFF' : isAvail ? '#10B981' : theme.textSub },
                            ]}
                          >
                            {isAvail ? (isCurrent ? 'Selected' : 'Select') : 'Unavailable'}
                          </Text>
                        </TouchableOpacity>
                      </View>
                    </View>
                  </TouchableOpacity>
                );
              })}
            </>
          ) : null}

          <Text style={[styles.sectionTitle, { color: theme.textMain }]}>About this experience</Text>
          <Text style={[styles.about, { color: theme.textSub }]}>
            {listing.description || listing.summary || 'No description provided yet.'}
          </Text>

          {listing.included ? (
            <>
              <Text style={[styles.sectionTitle, { color: theme.textMain }]}>{"What's included"}</Text>
              <Text style={[styles.about, { color: theme.textSub }]}>{listing.included}</Text>
            </>
          ) : null}

          {listing.nearest_hotel ? (
            <>
              <Text style={[styles.sectionTitle, { color: theme.textMain }]}>Nearest hotel</Text>
              <View style={styles.hotelCard}>
                <Image source={{ uri: resolveImage(listing.nearest_hotel.image) }} style={styles.hotelImage} />
                <View style={styles.hotelDistanceBadge}>
                  <Ionicons name="navigate" size={12} color="#0B3D2E" />
                  <Text style={styles.hotelDistanceText}>
                    {listing.nearest_hotel.distance_km} km away
                  </Text>
                </View>
                <View style={styles.hotelBody}>
                  <Text style={styles.hotelName} numberOfLines={1}>{listing.nearest_hotel.title}</Text>
                  <View style={styles.hotelMetaRow}>
                    <Ionicons name="location" size={13} color="#0B3D2E" />
                    <Text style={styles.hotelMeta} numberOfLines={1}>
                      {listing.nearest_hotel.area || listing.nearest_hotel.address}
                    </Text>
                  </View>
                  {listing.nearest_hotel.price ? (
                    <Text style={styles.hotelPrice}>
                      {formatPrice(listing.nearest_hotel.price)}
                      <Text style={styles.hotelPriceUnit}> / {listing.nearest_hotel.price_unit || 'night'}</Text>
                    </Text>
                  ) : null}
                  <TouchableOpacity
                    style={styles.hotelDirBtn}
                    activeOpacity={0.85}
                    onPress={() => {
                      const url = listing.nearest_hotel?.directions_url;
                      if (url) {
                        Linking.openURL(url).catch(() =>
                          Alert.alert('Unable to open maps', 'Please try again later.')
                        );
                      }
                    }}
                  >
                    <Ionicons name="navigate-circle" size={18} color="#0B3D2E" style={{ marginRight: 7 }} />
                    <Text style={styles.hotelDirText}>Get directions</Text>
                  </TouchableOpacity>
                </View>
              </View>
            </>
          ) : null}

          {canReview ? (
            <TouchableOpacity
              style={[styles.messageGuideBtn, { borderColor: '#F6B100' }]}
              activeOpacity={0.85}
              onPress={() => router.push({ pathname: '/review/[id]', params: { id: String(listing.id), title: listing.title } })}
            >
              <Ionicons name="star" size={17} color="#F6B100" style={{ marginRight: 8 }} />
              <Text style={[styles.messageGuideText, { color: '#F6B100' }]}>Write a review</Text>
            </TouchableOpacity>
          ) : null}
        </View>
      </ScrollView>

      {/* Sticky Book now bar */}
      <View
        style={[
          styles.bookBar,
          { backgroundColor: theme.bg, borderTopColor: theme.border, paddingBottom: Math.max(insets.bottom, 12) + 10 },
        ]}
      >
        <View>
          {isHotel ? (
            listing.price > 0 ? (
              <>
                <Text style={[styles.bookPriceLabel, { color: theme.textSub }]}>Starting from</Text>
                <Text style={[styles.bookPrice, { color: colors.accent }]}>
                  {formatPrice(listing.price)}
                  <Text style={[styles.bookPer, { color: theme.textSub }]}> / per night</Text>
                </Text>
              </>
            ) : (
              <Text style={{ fontSize: 15, fontWeight: '700', color: theme.textSub, fontStyle: 'italic' }}>
                Price unavailable
              </Text>
            )
          ) : (
            <>
              <Text style={[styles.bookPriceLabel, { color: theme.textSub }]}>{t('from')}</Text>
              <Text style={[styles.bookPrice, { color: colors.accent }]}>
                {priceLabel}
                {listing.price > 0 ? (
                  <Text style={[styles.bookPer, { color: theme.textSub }]}>
                    {isPerGroup ? ' / group' : ` / ${listing.price_unit || 'person'}`}
                  </Text>
                ) : null}
              </Text>
            </>
          )}
        </View>
        {activeBooking || alreadyBooked ? (
          <TouchableOpacity
            style={[styles.bookButton, styles.bookedButton]}
            onPress={() => {
              const statusLabel = activeBooking?.status_label || 'Active';
              const dateInfo = activeBooking?.booking_date ? ` (Date: ${formatScheduleDate(activeBooking.booking_date)})` : '';
              Alert.alert(
                'Active Booking Exists',
                `You already have an active booking for this tour (Status: ${statusLabel}${dateInfo}).\n\nYou can book this tour again once your current experience is marked as completed.`
              );
            }}
            activeOpacity={0.88}
          >
            <Ionicons name="time-outline" size={18} color="#FFFFFF" style={{ marginRight: 7 }} />
            <Text style={styles.bookButtonText}>Already Booked</Text>
          </TouchableOpacity>
        ) : (
          <TouchableOpacity onPress={() => openBooking({ allowDateChange: false })} activeOpacity={0.88}>
            <LinearGradient
              colors={gradients.brand}
              start={{ x: 0, y: 0 }}
              end={{ x: 1, y: 1 }}
              style={styles.bookButton}
            >
              <Ionicons name="calendar" size={17} color="#FFFFFF" style={{ marginRight: 7 }} />
              <Text style={styles.bookButtonText}>{isHotel ? 'Reserve room' : (hasSchedule ? 'Book Session' : 'Book now')}</Text>
            </LinearGradient>
          </TouchableOpacity>
        )}
      </View>

      {/* Booking modal (multi-step) */}
      {/* ── DETAILED ROOM VIEW MODAL / EXPANDABLE PANEL ── */}
      <Modal
        visible={!!roomDetailModal}
        transparent
        animationType="slide"
        onRequestClose={() => setRoomDetailModal(null)}
      >
        <View style={styles.modalBackdrop}>
          <View style={[styles.roomModalSheet, { backgroundColor: theme.bg }]}>
            {/* Modal Header */}
            <View style={styles.roomModalHeader}>
              <View style={{ flex: 1, marginRight: 12 }}>
                <Text style={[styles.roomModalSheetTitle, { color: theme.textMain }]} numberOfLines={1}>
                  {roomDetailModal?.name || 'Room Details'}
                </Text>
                <Text style={{ fontSize: 12, color: theme.textSub, marginTop: 2 }}>
                  {listing.title}
                </Text>
              </View>
              <TouchableOpacity
                onPress={() => setRoomDetailModal(null)}
                style={styles.roomModalCloseBtn}
                accessibilityRole="button"
                accessibilityLabel="Close room details"
              >
                <Ionicons name="close" size={20} color={theme.textMain} />
              </TouchableOpacity>
            </View>

            <ScrollView showsVerticalScrollIndicator={false} contentContainerStyle={{ paddingBottom: 30 }}>
              {/* Large Image / Gallery */}
              <View style={styles.roomModalGalleryWrap}>
                {modalRoomPhotos.length > 1 ? (
                  <ScrollView
                    ref={roomModalScrollRef}
                    horizontal
                    pagingEnabled
                    showsHorizontalScrollIndicator={false}
                    onScroll={(e) => {
                      const offsetX = e.nativeEvent.contentOffset.x;
                      const modalW = heroSliderWidth - 44;
                      const idx = Math.round(offsetX / modalW);
                      if (idx !== activeRoomModalSlide && idx >= 0 && idx < modalRoomPhotos.length) {
                        setActiveRoomModalSlide(idx);
                      }
                    }}
                    scrollEventThrottle={16}
                    style={{ width: '100%', height: 230 }}
                  >
                    {modalRoomPhotos.map((p, pIdx) => (
                      <Image
                        key={pIdx}
                        source={{ uri: resolveImage(p) }}
                        style={[styles.roomModalImage, { width: heroSliderWidth - 44 }]}
                      />
                    ))}
                  </ScrollView>
                ) : modalRoomPhotos.length > 0 ? (
                  <Image
                    source={{ uri: resolveImage(modalRoomPhotos[0]) }}
                    style={styles.roomModalImage}
                  />
                ) : (
                  <View style={[styles.roomModalImage, { backgroundColor: '#1E293B', alignItems: 'center', justifyContent: 'center' }]}>
                    <Ionicons name="bed-outline" size={48} color="#94A3B8" />
                    <Text style={{ color: '#94A3B8', marginTop: 8, fontSize: 13, fontWeight: '600' }}>No room photos uploaded</Text>
                  </View>
                )}

                {modalRoomPhotos.length > 1 ? (
                  <View style={styles.roomModalDotsWrap}>
                    {modalRoomPhotos.map((_, idx) => (
                      <View
                        key={idx}
                        style={[
                          styles.roomModalDot,
                          idx === activeRoomModalSlide ? styles.roomModalDotActive : styles.roomModalDotInactive,
                        ]}
                      />
                    ))}
                  </View>
                ) : null}
              </View>

              {roomDetailModal ? (
                <View style={{ marginTop: 16 }}>
                  {/* Title & Status */}
                  <View style={styles.roomModalTitleRow}>
                    <Text style={[styles.roomModalName, { color: theme.textMain }]}>
                      {roomDetailModal.name}
                    </Text>
                    <View
                      style={[
                        styles.roomBadge,
                        {
                          backgroundColor:
                            roomDetailModal.status === 'available'
                              ? '#DCFCE7'
                              : roomDetailModal.status === 'blocked'
                              ? '#FEF3C7'
                              : '#FEE2E2',
                        },
                      ]}
                    >
                      <Text
                        style={[
                          styles.roomBadgeText,
                          {
                            color:
                              roomDetailModal.status === 'available'
                                ? '#166534'
                                : roomDetailModal.status === 'blocked'
                                ? '#92400E'
                                : '#991B1B',
                          },
                        ]}
                      >
                        {roomDetailModal.status === 'available'
                          ? 'Available'
                          : roomDetailModal.status === 'blocked'
                          ? 'Blocked'
                          : 'Booked'}
                      </Text>
                    </View>
                  </View>

                  {/* Highlights Grid */}
                  <View style={styles.roomModalHighlights}>
                    <View style={[styles.roomHighlightCard, { backgroundColor: theme.card, borderColor: theme.border }]}>
                      <Ionicons name="bed-outline" size={18} color="#10B981" />
                      <Text style={[styles.roomHighlightLabel, { color: theme.textSub }]}>Bed Type</Text>
                      <Text style={[styles.roomHighlightValue, { color: theme.textMain }]}>
                        {roomDetailModal.bed_type || '1 King Bed'}
                      </Text>
                    </View>
                    <View style={[styles.roomHighlightCard, { backgroundColor: theme.card, borderColor: theme.border }]}>
                      <Ionicons name="people-outline" size={18} color="#10B981" />
                      <Text style={[styles.roomHighlightLabel, { color: theme.textSub }]}>Max Occupancy</Text>
                      <Text style={[styles.roomHighlightValue, { color: theme.textMain }]}>
                        {roomDetailModal.max_guests || 2} Guests
                      </Text>
                    </View>
                    <View style={[styles.roomHighlightCard, { backgroundColor: theme.card, borderColor: theme.border }]}>
                      <Ionicons name="shield-checkmark-outline" size={18} color="#10B981" />
                      <Text style={[styles.roomHighlightLabel, { color: theme.textSub }]}>Deposit Req.</Text>
                      <Text style={[styles.roomHighlightValue, { color: theme.textMain }]}>
                        {roomDetailModal.deposit_percentage ?? 20}%
                      </Text>
                    </View>
                  </View>

                  {/* Description */}
                  {roomDetailModal.description ? (
                    <View style={styles.roomModalSection}>
                      <Text style={[styles.roomModalSectionHeading, { color: theme.textMain }]}>
                        About this room
                      </Text>
                      <Text style={[styles.roomModalDescText, { color: theme.textSub }]}>
                        {roomDetailModal.description}
                      </Text>
                    </View>
                  ) : null}

                  {/* Amenities */}
                  {roomDetailModal.amenities ? (
                    <View style={styles.roomModalSection}>
                      <Text style={[styles.roomModalSectionHeading, { color: theme.textMain }]}>
                        Room Amenities & Features
                      </Text>
                      <View style={styles.roomModalAmenitiesGrid}>
                        {roomDetailModal.amenities
                          .split(',')
                          .map((a) => a.trim())
                          .filter(Boolean)
                          .map((item, idx) => (
                            <View
                              key={idx}
                              style={[styles.roomModalAmenityPill, { backgroundColor: theme.card, borderColor: theme.border }]}
                            >
                              <Ionicons name={getAmenityIcon(item)} size={15} color="#10B981" style={{ marginRight: 6 }} />
                              <Text style={[styles.roomModalAmenityText, { color: theme.textMain }]}>{item}</Text>
                            </View>
                          ))}
                      </View>
                    </View>
                  ) : null}

                  {/* Pricing Breakdown Card */}
                  <View style={[styles.roomModalPricingCard, { backgroundColor: theme.card, borderColor: theme.border }]}>
                    <View style={styles.roomModalPricingRow}>
                      <Text style={[styles.roomModalPricingLabel, { color: theme.textSub }]}>Nightly Rate</Text>
                      <Text style={[styles.roomModalPricingValue, { color: colors.accent }]}>
                        {formatPrice(roomDetailModal.price_per_night)}
                        <Text style={{ fontSize: 12, fontWeight: '500', color: theme.textSub }}> / night</Text>
                      </Text>
                    </View>
                    <View style={[styles.roomModalPricingRow, { marginTop: 8 }]}>
                      <Text style={[styles.roomModalPricingLabel, { color: theme.textSub }]}>
                        Online Deposit ({roomDetailModal.deposit_percentage ?? 20}%)
                      </Text>
                      <Text style={[styles.roomModalPricingValue, { color: '#10B981' }]}>
                        {formatPrice(Math.round((roomDetailModal.price_per_night * (roomDetailModal.deposit_percentage ?? 20)) / 100))}
                      </Text>
                    </View>
                    <View style={[styles.roomModalPricingRow, { marginTop: 8 }]}>
                      <Text style={[styles.roomModalPricingLabel, { color: theme.textSub }]}>Due at Front Desk</Text>
                      <Text style={[styles.roomModalPricingValue, { color: theme.textMain }]}>
                        {formatPrice(Math.max(0, roomDetailModal.price_per_night - Math.round((roomDetailModal.price_per_night * (roomDetailModal.deposit_percentage ?? 20)) / 100)))}
                      </Text>
                    </View>
                  </View>

                  {/* Actions */}
                  <View style={styles.roomModalActions}>
                    {roomDetailModal.status === 'available' ? (
                      <TouchableOpacity
                        activeOpacity={0.88}
                        onPress={() => {
                          setSelectedRoom(roomDetailModal);
                          setRoomDetailModal(null);
                          openBooking();
                        }}
                      >
                        <LinearGradient
                          colors={gradients.brand}
                          start={{ x: 0, y: 0 }}
                          end={{ x: 1, y: 1 }}
                          style={styles.roomModalPrimaryBtn}
                        >
                          <Ionicons name="calendar-outline" size={17} color="#FFFFFF" style={{ marginRight: 8 }} />
                          <Text style={styles.roomModalPrimaryBtnText}>Reserve this Room</Text>
                        </LinearGradient>
                      </TouchableOpacity>
                    ) : (
                      <View style={[styles.roomModalPrimaryBtn, { backgroundColor: '#64748B' }]}>
                        <Text style={styles.roomModalPrimaryBtnText}>Room Currently Unavailable</Text>
                      </View>
                    )}

                    <TouchableOpacity
                      style={[styles.roomModalSecondaryBtn, { borderColor: theme.border }]}
                      onPress={() => {
                        setSelectedRoom(roomDetailModal);
                        setRoomDetailModal(null);
                      }}
                      activeOpacity={0.8}
                    >
                      <Text style={[styles.roomModalSecondaryBtnText, { color: theme.textMain }]}>
                        {selectedRoom?.id === roomDetailModal.id ? 'Selected as Current Room' : 'Select Room'}
                      </Text>
                    </TouchableOpacity>
                  </View>
                </View>
              ) : null}
            </ScrollView>
          </View>
        </View>
      </Modal>

      {/* ── TOUR / ATTRACTION SCHEDULE DETAILS MODAL ── */}
      <Modal
        visible={scheduleModalOpen}
        transparent
        animationType="slide"
        onRequestClose={() => setScheduleModalOpen(false)}
      >
        <View style={styles.modalBackdrop}>
          <View style={[styles.scheduleModalSheet, { backgroundColor: theme.bg, borderTopColor: theme.border }]}>
            {/* Header */}
            <View style={styles.scheduleModalHeader}>
              <View style={{ flex: 1, marginRight: 12 }}>
                <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6, marginBottom: 2 }}>
                  <Ionicons name="calendar" size={18} color="#10B981" />
                  <Text style={[styles.scheduleModalTitle, { color: theme.textMain }]}>Tour Schedule</Text>
                </View>
                <Text style={{ fontSize: 13, color: theme.textSub }} numberOfLines={1}>
                  {listing.title}{listing.area ? ` · ${listing.area}` : ''}
                </Text>
              </View>
              <TouchableOpacity
                onPress={() => setScheduleModalOpen(false)}
                style={styles.scheduleModalCloseBtn}
                accessibilityRole="button"
                accessibilityLabel="Close schedule details"
              >
                <Ionicons name="close" size={20} color={theme.textMain} />
              </TouchableOpacity>
            </View>

            <ScrollView showsVerticalScrollIndicator={false} contentContainerStyle={{ paddingBottom: 30 }}>
              {/* Quick Highlights Grid: Duration · Operating Hours · Group Capacity */}
              <View style={styles.scheduleHighlightsRow}>
                <View style={[styles.scheduleHighlightCard, { backgroundColor: theme.card, borderColor: theme.border }]}>
                  <Ionicons name="time-outline" size={20} color="#10B981" />
                  <Text style={[styles.scheduleHighlightLabel, { color: theme.textSub }]}>Duration</Text>
                  <Text style={[styles.scheduleHighlightVal, { color: theme.textMain }]}>
                    {listing.duration || 'Flexible'}
                  </Text>
                </View>

                <View style={[styles.scheduleHighlightCard, { backgroundColor: theme.card, borderColor: theme.border }]}>
                  <Ionicons name="alarm-outline" size={20} color="#10B981" />
                  <Text style={[styles.scheduleHighlightLabel, { color: theme.textSub }]}>Operating</Text>
                  <Text style={[styles.scheduleHighlightVal, { color: theme.textMain }]}>
                    {schedules.length > 0 && schedules[0].start_time
                      ? `${formatTimeString(schedules[0].start_time)}`
                      : '6 AM - 8 PM'}
                  </Text>
                </View>

                <View style={[styles.scheduleHighlightCard, { backgroundColor: theme.card, borderColor: theme.border }]}>
                  <Ionicons name="people-outline" size={20} color="#10B981" />
                  <Text style={[styles.scheduleHighlightLabel, { color: theme.textSub }]}>Capacity</Text>
                  <Text style={[styles.scheduleHighlightVal, { color: theme.textMain }]}>
                    {schedules.length > 0 && schedules[0].capacity
                      ? `Max ${schedules[0].capacity}`
                      : 'Per person'}
                  </Text>
                </View>
              </View>

              {/* Meeting Point Card */}
              <View style={[styles.scheduleSectionCard, { backgroundColor: theme.card, borderColor: theme.border }]}>
                <View style={{ flexDirection: 'row', alignItems: 'flex-start', gap: 10 }}>
                  <View style={styles.scheduleIconBadge}>
                    <Ionicons name="location-sharp" size={18} color="#10B981" />
                  </View>
                  <View style={{ flex: 1 }}>
                    <Text style={[styles.scheduleSectionHeading, { color: theme.textMain }]}>
                      Meeting Point & Departure
                    </Text>
                    <Text style={[styles.scheduleAddressText, { color: theme.textSub }]}>
                      {listing.address || listing.area || 'Cebu, Philippines'}
                    </Text>
                    {listing.area ? (
                      <Text style={{ fontSize: 12, color: '#10B981', fontWeight: '600', marginTop: 3 }}>
                        Area: {listing.area}
                      </Text>
                    ) : null}

                    {/* Open in Maps / Directions link */}
                    <TouchableOpacity
                      style={styles.scheduleMapsBtn}
                      activeOpacity={0.8}
                      onPress={() => {
                        const query = listing.address || `${listing.title}, Cebu`;
                        const url = (listing.latitude && listing.longitude)
                          ? `https://www.google.com/maps/search/?api=1&query=${listing.latitude},${listing.longitude}`
                          : `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(query)}`;
                        Linking.openURL(url).catch(() => Alert.alert('Unable to open map', 'Please try again later.'));
                      }}
                    >
                      <Ionicons name="navigate-outline" size={14} color="#10B981" style={{ marginRight: 6 }} />
                      <Text style={styles.scheduleMapsBtnText}>Open in Google Maps</Text>
                    </TouchableOpacity>
                  </View>
                </View>
              </View>

              {/* Available Scheduled Sessions Section */}
              <View style={{ marginTop: 16 }}>
                <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginBottom: 10 }}>
                  <Text style={[styles.scheduleSectionHeading, { color: theme.textMain }]}>
                    Available Dates & Sessions
                  </Text>
                  {schedules.length > 0 ? (
                    <View style={styles.scheduleCountBadge}>
                      <Text style={styles.scheduleCountBadgeText}>
                        {schedules.length} scheduled session{schedules.length > 1 ? 's' : ''}
                      </Text>
                    </View>
                  ) : null}
                </View>

                {schedules.length > 0 ? (
                  schedules.map((s) => {
                    const timeRange = (s.start_time || s.end_time)
                      ? `${formatTimeString(s.start_time)}${s.end_time ? ` – ${formatTimeString(s.end_time)}` : ''}`
                      : 'Flexible timing';

                    return (
                      <View
                        key={s.id}
                        style={[styles.sessionCard, { backgroundColor: theme.card, borderColor: theme.border }]}
                      >
                        <View style={styles.sessionHeaderRow}>
                          <View style={{ flex: 1, marginRight: 8 }}>
                            <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
                              <Ionicons name="calendar-sharp" size={15} color="#10B981" />
                              <Text style={[styles.sessionDateText, { color: theme.textMain }]}>
                                {formatScheduleDate(s.schedule_date)}
                              </Text>
                            </View>
                          </View>
                          <View style={styles.sessionStatusBadge}>
                            <Text style={styles.sessionStatusBadgeText}>Available</Text>
                          </View>
                        </View>

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
                                  if (activeBooking) {
                                    Alert.alert(
                                      'Active Booking Exists',
                                      `You already have an active booking for this tour (Status: ${activeBooking.status_label || 'Active'}). You can book again once your existing reservation is marked as completed.`
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
                              >
                                <Ionicons name={isFull ? 'ban' : 'calendar-outline'} size={14} color="#FFFFFF" style={{ marginRight: 6 }} />
                                <Text style={styles.sessionBookBtnText}>
                                  {isFull ? 'Session Fully Booked' : 'Book this Session'}
                                </Text>
                              </TouchableOpacity>
                            </>
                          );
                        })()}
                      </View>
                    );
                  })
                ) : (
                  <View style={[styles.sessionCard, { backgroundColor: theme.card, borderColor: theme.border, padding: 18 }]}>
                    <View style={{ flexDirection: 'row', alignItems: 'center', gap: 10, marginBottom: 8 }}>
                      <Ionicons name="calendar-outline" size={24} color="#10B981" />
                      <Text style={[styles.sessionDateText, { color: theme.textMain, fontSize: 16 }]}>
                        Daily Flexible Departures
                      </Text>
                    </View>
                    <Text style={{ fontSize: 13, color: theme.textSub, lineHeight: 19 }}>
                      This tour runs daily. You can choose any available date and select your preferred start time (from 6:00 AM to 8:00 PM) during booking.
                    </Text>
                  </View>
                )}
              </View>

              {/* Scheduling Guidance / Notice */}
              <View style={[styles.scheduleNoticeCard, { backgroundColor: 'rgba(255,255,255,0.04)', borderColor: theme.border }]}>
                <Ionicons name="sparkles-outline" size={16} color="#FBBF24" style={{ marginRight: 8, marginTop: 1 }} />
                <View style={{ flex: 1 }}>
                  <Text style={[styles.scheduleNoticeHeading, { color: theme.textMain }]}>Tour Booking Information</Text>
                  <Text style={[styles.scheduleNoticeText, { color: theme.textSub }]}>
                    Tours are guided by verified local experts. Reserve in advance to ensure slot availability. Your reservation confirmation will be sent immediately upon booking.
                  </Text>
                </View>
              </View>

              {/* Bottom Primary Modal Action */}
              <View style={{ marginTop: 20 }}>
                {!hasSchedule ? (
                  <TouchableOpacity
                    activeOpacity={0.88}
                    onPress={() => {
                      setScheduleModalOpen(false);
                      openBooking({
                        allowDateChange: true,
                      });
                    }}
                  >
                    <LinearGradient
                      colors={gradients.brand}
                      start={{ x: 0, y: 0 }}
                      end={{ x: 1, y: 1 }}
                      style={styles.schedulePrimaryBtn}
                    >
                      <Ionicons name="calendar" size={17} color="#FFFFFF" style={{ marginRight: 8 }} />
                      <Text style={styles.schedulePrimaryBtnText}>Select Date</Text>
                    </LinearGradient>
                  </TouchableOpacity>
                ) : null}

                <TouchableOpacity
                  style={[
                    styles.scheduleSecondaryBtn,
                    { borderColor: theme.border },
                    hasSchedule && { marginTop: 0 }
                  ]}
                  onPress={() => setScheduleModalOpen(false)}
                  activeOpacity={0.8}
                >
                  <Text style={[styles.scheduleSecondaryBtnText, { color: theme.textMain }]}>Close</Text>
                </TouchableOpacity>
              </View>
            </ScrollView>
          </View>
        </View>
      </Modal>
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

              {/* Slots / Capacity Card (Dynamic Availability) */}
              <View style={[styles.durationInfoCard, { backgroundColor: theme.card, borderColor: theme.border }]}>
                <View style={{ flexDirection: 'row', alignItems: 'flex-start', justifyContent: 'space-between', gap: 10 }}>
                  <View style={{ flexDirection: 'row', alignItems: 'center', gap: 10, flex: 1 }}>
                    <View style={[styles.durationIconBox, { backgroundColor: isAttractionFullyBooked ? 'rgba(239, 68, 68, 0.12)' : 'rgba(59, 130, 246, 0.12)' }]}>
                      <Ionicons
                        name={isAttractionFullyBooked ? 'ban-outline' : 'people-outline'}
                        size={20}
                        color={isAttractionFullyBooked ? '#EF4444' : '#3B82F6'}
                      />
                    </View>
                    <View style={{ flex: 1 }}>
                      <Text style={{ fontSize: 11, fontWeight: '700', color: theme.textSub, textTransform: 'uppercase' }}>
                        Slots / Capacity
                      </Text>
                      <Text style={{ fontSize: 15, fontWeight: '700', color: isAttractionFullyBooked ? '#EF4444' : theme.textMain }}>
                        {isAttractionFullyBooked
                          ? 'Fully Booked · No slots available'
                          : `${attractionAvailableSlots} of ${totalAttractionCapacity} slots available`}
                      </Text>
                    </View>
                  </View>
                  <View style={[
                    styles.sessionSlotBadge,
                    { backgroundColor: isAttractionFullyBooked ? 'rgba(239, 68, 68, 0.15)' : 'rgba(16, 185, 129, 0.15)' }
                  ]}>
                    <Text style={{
                      fontSize: 11,
                      fontWeight: '700',
                      color: isAttractionFullyBooked ? '#EF4444' : '#10B981',
                    }}>
                      {isAttractionFullyBooked ? 'Fully Booked' : `Available Slots: ${attractionAvailableSlots}/${totalAttractionCapacity}`}
                    </Text>
                  </View>
                </View>
                <View style={{ marginTop: 6, paddingLeft: 46 }}>
                  <Text style={{ fontSize: 12, color: theme.textSub }}>
                    Total Capacity: {totalAttractionCapacity} slots · {isAttractionFullyBooked ? '0 slots remaining' : `${attractionAvailableSlots} slots remaining`}
                  </Text>
                  <Text style={{ fontSize: 12, color: theme.textSub, marginTop: 2 }}>
                    {isPerGroup
                      ? `Pricing is per group${listing.price > 0 ? ` (${formatPrice(listing.price)} total)` : ''}. Book once for your entire party.`
                      : `Pricing is per person${listing.price > 0 ? ` (${formatPrice(listing.price)} each)` : ''}. Rate is calculated based on number of participants.`}
                  </Text>
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

              {/* Bottom Action */}
              <View style={{ marginTop: 20 }}>
                <TouchableOpacity
                  activeOpacity={0.88}
                  disabled={isAttractionFullyBooked}
                  onPress={() => {
                    if (isAttractionFullyBooked) {
                      Alert.alert('Fully Booked', 'This attraction currently has no available slots.');
                      return;
                    }
                    setDurationModalOpen(false);
                    openBooking({ allowDateChange: true });
                  }}
                  style={isAttractionFullyBooked ? { opacity: 0.5 } : undefined}
                >
                  <LinearGradient
                    colors={isAttractionFullyBooked ? ['#64748B', '#475569'] : gradients.brand}
                    start={{ x: 0, y: 0 }}
                    end={{ x: 1, y: 1 }}
                    style={styles.schedulePrimaryBtn}
                  >
                    <Ionicons name={isAttractionFullyBooked ? 'ban' : 'calendar'} size={17} color="#FFFFFF" style={{ marginRight: 8 }} />
                    <Text style={styles.schedulePrimaryBtnText}>
                      {isAttractionFullyBooked ? 'Fully Booked' : 'Book now'}
                    </Text>
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

      {/* Reviews from other tourists — open by tapping the Rating card. */}
      <Modal visible={reviewsOpen} transparent animationType="slide" onRequestClose={() => setReviewsOpen(false)}>
        <View style={styles.modalBackdrop}>
          <View style={[styles.modalCard, { backgroundColor: theme.bg }]}>
            <View style={styles.modalHeader}>
              <Text style={[styles.modalTitle, { color: theme.textMain }]}>
                Reviews{listing.review_count > 0 ? ` (${listing.review_count})` : ''}
              </Text>
              <TouchableOpacity onPress={() => setReviewsOpen(false)} accessibilityLabel="Close reviews">
                <Ionicons name="close" size={24} color={theme.textSub} />
              </TouchableOpacity>
            </View>

            {listing.rating > 0 ? (
              <View style={styles.reviewSummary}>
                <Ionicons name="star" size={20} color="#F6B100" />
                <Text style={[styles.reviewSummaryScore, { color: theme.textMain }]}>
                  {listing.rating.toFixed(1)}
                </Text>
                <Text style={[styles.reviewSummaryCount, { color: theme.textSub }]}>
                  · {listing.review_count} {listing.review_count === 1 ? 'review' : 'reviews'}
                </Text>
              </View>
            ) : null}

            <ScrollView style={{ maxHeight: 460 }} showsVerticalScrollIndicator={false}>
              {reviews.length === 0 ? (
                <View style={styles.reviewEmpty}>
                  <Ionicons name="chatbubble-ellipses-outline" size={34} color={theme.textSub} />
                  <Text style={[styles.reviewEmptyText, { color: theme.textSub }]}>
                    No reviews yet. Be the first to share your experience after your trip!
                  </Text>
                </View>
              ) : (
                reviews.map((r) => (
                  <View key={r.id} style={[styles.reviewItem, { borderColor: theme.border }]}>
                    <View style={styles.reviewItemHead}>
                      <View style={[styles.reviewAvatar, { backgroundColor: theme.card }]}>
                        <Text style={[styles.reviewAvatarText, { color: theme.accent }]}>
                          {(r.user_name || 'T').charAt(0).toUpperCase()}
                        </Text>
                      </View>
                      <View style={{ flex: 1 }}>
                        <Text style={[styles.reviewName, { color: theme.textMain }]} numberOfLines={1}>
                          {r.user_name || 'Traveler'}
                        </Text>
                        <View style={styles.reviewStars}>
                          {[1, 2, 3, 4, 5].map((n) => (
                            <Ionicons
                              key={n}
                              name={n <= r.rating ? 'star' : 'star-outline'}
                              size={13}
                              color="#F6B100"
                            />
                          ))}
                        </View>
                      </View>
                      {r.created_at ? (
                        <Text style={[styles.reviewDate, { color: theme.textSub }]}>
                          {formatReviewDate(r.created_at)}
                        </Text>
                      ) : null}
                    </View>
                    {r.comment ? (
                      <Text style={[styles.reviewComment, { color: theme.textSub }]}>{r.comment}</Text>
                    ) : null}
                  </View>
                ))
              )}
            </ScrollView>
          </View>
        </View>
      </Modal>

      <Modal visible={modalOpen} transparent animationType="slide" onRequestClose={() => { setModalOpen(false); setBookingViewMode('date'); }}>
        <View style={styles.modalBackdrop}>
          <View style={[styles.modalCard, { backgroundColor: theme.bg }]}>
            <View style={styles.modalHeader}>
              {step !== 'details' && step !== 'confirmed' ? (
                <TouchableOpacity
                  onPress={() => setStep(step === 'method' ? 'details' : 'method')}
                  style={{ marginRight: 8 }}
                >
                  <Ionicons name="chevron-back" size={24} color={theme.textSub} />
                </TouchableOpacity>
              ) : null}
              <Text style={[styles.modalTitle, { color: theme.textMain }]}>
                {step === 'confirmed' ? 'Booking Confirmed' : step === 'details' ? (hasSchedule ? 'Book Tour Session' : `Book ${listing.title}`) : step === 'method' ? 'Payment method' : step === 'qr' ? 'Scan to pay' : 'Card payment'}
              </Text>
              <TouchableOpacity onPress={() => { setModalOpen(false); setBookingViewMode('date'); }}>
                <Ionicons name="close" size={24} color={theme.textSub} />
              </TouchableOpacity>
            </View>

            <ScrollView showsVerticalScrollIndicator={false} keyboardShouldPersistTaps="handled">
              {/* ── STEP 1: Date + Guests ── */}
              {step === 'details' ? (
                <>
                  {isHotel ? (
                    <>
                      {/* Hotel Stay Duration & Standard Time Badge */}
                      <View style={[styles.hotelStayBanner, { backgroundColor: theme.card, borderColor: theme.border }]}>
                        <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }}>
                          <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
                            <Ionicons name="moon" size={16} color={theme.accent} />
                            <Text style={{ fontSize: 15, fontWeight: '800', color: theme.textMain }}>
                              {hotelNights} night{hotelNights > 1 ? 's' : ''} stay
                            </Text>
                          </View>
                          <View style={{ backgroundColor: theme.accent + '20', paddingHorizontal: 8, paddingVertical: 3, borderRadius: 6 }}>
                            <Text style={{ fontSize: 11, fontWeight: '700', color: theme.accent }}>STANDARD STAY</Text>
                          </View>
                        </View>
                        <Text style={{ fontSize: 12, color: theme.textSub, marginTop: 4 }}>
                          🕒 {listing.check_in_schedule_label || `Check-in from ${listing.check_in_time_label || '2:00 PM'} · Check-out until ${listing.check_out_time_label || '12:00 PM'}`}
                        </Text>
                      </View>

                      {/* Check-In and Check-Out Date Selectors */}
                      <View style={{ flexDirection: 'row', gap: 10, marginTop: 12, marginBottom: 8 }}>
                        <TouchableOpacity
                          style={[
                            styles.hotelDateCard,
                            { backgroundColor: theme.card, borderColor: hotelPickerTarget === 'check_in' ? theme.accent : theme.border },
                            hotelPickerTarget === 'check_in' && { borderWidth: 2 },
                          ]}
                          activeOpacity={0.85}
                          onPress={() => setHotelPickerTarget('check_in')}
                        >
                          <Text style={[styles.hotelDateLabel, { color: hotelPickerTarget === 'check_in' ? theme.accent : theme.textSub }]}>
                            ARRIVAL / CHECK-IN
                          </Text>
                          <Text style={[styles.hotelDateValue, { color: theme.textMain }]}>
                            {hotelCheckIn || 'Select date'}
                          </Text>
                          <Text style={{ fontSize: 11, color: theme.textSub, marginTop: 2 }}>From {listing.check_in_time_label || '2:00 PM'}</Text>
                        </TouchableOpacity>

                        <TouchableOpacity
                          style={[
                            styles.hotelDateCard,
                            { backgroundColor: theme.card, borderColor: hotelPickerTarget === 'check_out' ? theme.accent : theme.border },
                            hotelPickerTarget === 'check_out' && { borderWidth: 2 },
                          ]}
                          activeOpacity={0.85}
                          onPress={() => setHotelPickerTarget('check_out')}
                        >
                          <Text style={[styles.hotelDateLabel, { color: hotelPickerTarget === 'check_out' ? theme.accent : theme.textSub }]}>
                            DEPARTURE / CHECK-OUT
                          </Text>
                          <Text style={[styles.hotelDateValue, { color: theme.textMain }]}>
                            {hotelCheckOut || 'Select date'}
                          </Text>
                          <Text style={{ fontSize: 11, color: theme.textSub, marginTop: 2 }}>Until {listing.check_out_time_label || '12:00 PM'}</Text>
                        </TouchableOpacity>
                      </View>

                      <Text style={[styles.modalSubHint, { color: theme.textSub, marginTop: 4, marginBottom: 10 }]}>
                        {hotelPickerTarget === 'check_in'
                          ? 'Select your arrival (check-in) date below:'
                          : 'Select your departure (check-out) date below (must be after check-in):'}
                      </Text>

                      <CalendarPicker
                        value={hotelPickerTarget === 'check_in' ? hotelCheckIn : hotelCheckOut}
                        onChange={(pickedDate) => {
                          if (hotelPickerTarget === 'check_in') {
                            setHotelCheckIn(pickedDate);
                            // Selecting a check-in date automatically sets the default check-out date
                            // to exactly one day after the selected check-in date (one-night stay).
                            setHotelCheckOut(getNextCalendarDay(pickedDate));
                            setHotelPickerTarget('check_out');
                          } else {
                            // Keep manual check-out date selection
                            setHotelCheckOut(pickedDate);
                          }
                        }}
                        minDate={hotelPickerTarget === 'check_in' ? getTodayString() : getNextCalendarDay(hotelCheckIn || getTodayString())}
                        theme={theme}
                      />
                    </>
                  ) : (
                    <>
                      {/* Header with status badge (Displayed in State 1 only) */}
                      {bookingViewMode === 'date' ? (
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
                      ) : null}

                      {/* Locked Date Info Banner (Displayed in State 1 only for scheduled tours) */}
                      {bookingViewMode === 'date' && hasSchedule ? (
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
                                onPress={() => {
                                  setCanChangeDate(true);
                                  setBookingViewMode((prev) => (prev === 'session' ? 'date' : 'session'));
                                }}
                              >
                                <Ionicons name="calendar" size={14} color="#FFFFFF" style={{ marginRight: 6 }} />
                                {selectSessionBtnLabel}
                              </TouchableOpacity>
                            </View>
                          </View>
                        </View>
                      ) : null}

                      {/* ── STATE 2: Available Dates & Sessions View (Image 2) ── */}
                      {bookingViewMode === 'session' && hasSchedule ? (
                        <View style={{ marginTop: 8 }}>
                          {/* Available Dates & Sessions Section Header matching Image 2 */}
                          <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginBottom: 14 }}>
                            <Text style={{ fontSize: 18, fontWeight: '700', color: theme.textMain }}>
                              Available Dates & Sessions
                            </Text>
                            {schedules.length > 0 ? (
                              <View style={[styles.scheduleCountBadge, { backgroundColor: 'rgba(16,185,129,0.12)' }]}>
                                <Text style={styles.scheduleCountBadgeText}>
                                  {schedules.length} scheduled session{schedules.length > 1 ? 's' : ''}
                                </Text>
                              </View>
                            ) : null}
                          </View>

                          {schedules.length > 0 ? (
                            schedules.map((s) => {
                              const timeRange = (s.start_time || s.end_time)
                                ? `${formatTimeString(s.start_time)}${s.end_time ? ` – ${formatTimeString(s.end_time)}` : ''}`
                                : 'Flexible timing';
                              const isCurrentSession = selectedSessionId === s.id;
                              const cap = s.capacity ?? listing?.capacity ?? 10;
                              const slotsLeft = s.available_slots !== undefined ? s.available_slots : Math.max(0, cap - (s.booked_count ?? 0));
                              const isFull = s.is_available === false || slotsLeft <= 0;

                              return (
                                <View
                                  key={s.id}
                                  style={[
                                    styles.sessionCard,
                                    { backgroundColor: theme.card, borderColor: isCurrentSession ? '#10B981' : theme.border, marginBottom: 14, borderRadius: 16, padding: 16 },
                                    isCurrentSession && { borderWidth: 2, backgroundColor: 'rgba(16, 185, 129, 0.05)' }
                                  ]}
                                >
                                  {/* Date and Status Badge Row matching Image 2 */}
                                  <View style={styles.sessionHeaderRow}>
                                    <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8 }}>
                                      <Ionicons name="calendar-sharp" size={16} color="#10B981" />
                                      <Text style={[styles.sessionDateText, { color: theme.textMain, fontSize: 16, fontWeight: '700' }]}>
                                        {formatScheduleDate(s.schedule_date)}
                                      </Text>
                                    </View>
                                    <View style={[
                                      styles.sessionStatusBadge,
                                      isFull
                                        ? { backgroundColor: 'rgba(239,68,68,0.15)' }
                                        : { backgroundColor: '#D1FAE5' }
                                    ]}>
                                      <Text style={[
                                        styles.sessionStatusBadgeText,
                                        isFull
                                          ? { color: '#EF4444' }
                                          : { color: '#065F46' }
                                      ]}>
                                        {isFull ? 'Fully Booked' : isCurrentSession ? 'Selected Session' : 'Available'}
                                      </Text>
                                    </View>
                                  </View>

                                  {/* Session detail badges (Time & Independent Slots) matching Image 2 */}
                                  <View style={[styles.sessionPillsRow, { marginTop: 10, marginBottom: s.note ? 10 : 12 }]}>
                                    <View style={[styles.sessionPill, { backgroundColor: theme.inputBg, borderColor: theme.border, borderRadius: 8, paddingHorizontal: 10, paddingVertical: 6 }]}>
                                      <Ionicons name="time-outline" size={14} color="#10B981" style={{ marginRight: 6 }} />
                                      <Text style={[styles.sessionPillText, { color: theme.textMain, fontSize: 13, fontWeight: '600' }]}>{timeRange}</Text>
                                    </View>

                                    <View style={[styles.sessionPill, { backgroundColor: theme.inputBg, borderColor: theme.border, borderRadius: 8, paddingHorizontal: 10, paddingVertical: 6 }]}>
                                      <Ionicons name="people-outline" size={14} color={isFull ? '#EF4444' : '#10B981'} style={{ marginRight: 6 }} />
                                      <Text style={[styles.sessionPillText, { color: isFull ? '#EF4444' : theme.textMain, fontSize: 13, fontWeight: '600' }]}>
                                        {isFull ? 'No slots available' : `${slotsLeft} of ${cap} slot${cap > 1 ? 's' : ''} available`}
                                      </Text>
                                    </View>
                                  </View>

                                  {/* Note box matching Image 2 */}
                                  {s.note ? (
                                    <View style={[styles.sessionNoteBox, { backgroundColor: 'rgba(16,185,129,0.06)', borderColor: 'rgba(16,185,129,0.22)', borderRadius: 10, paddingHorizontal: 12, paddingVertical: 8, marginBottom: 12 }]}>
                                      <Ionicons name="information-circle-outline" size={16} color="#10B981" style={{ marginRight: 6 }} />
                                      <Text style={[styles.sessionNoteText, { color: theme.textSub, fontSize: 13 }]}>{s.note}</Text>
                                    </View>
                                  ) : null}

                                  {/* Book this Session Action / Interactive Guest Selector */}
                                  {isFull ? (
                                    <TouchableOpacity
                                      style={[styles.sessionBookBtn, { backgroundColor: '#4B5563', opacity: 0.6, height: 46, borderRadius: 12 }]}
                                      disabled={true}
                                    >
                                      <Ionicons name="ban" size={15} color="#FFFFFF" style={{ marginRight: 6 }} />
                                      <Text style={[styles.sessionBookBtnText, { fontSize: 15, fontWeight: '700' }]}>Session Fully Booked</Text>
                                    </TouchableOpacity>
                                  ) : !isCurrentSession ? (
                                    <TouchableOpacity
                                      style={[styles.sessionBookBtn, { backgroundColor: '#10B981', height: 46, borderRadius: 12 }]}
                                      activeOpacity={0.85}
                                      onPress={() => {
                                        setSelectedSessionId(s.id);
                                        updateBookingDates([s.schedule_date]);
                                        if (s.start_time) setBookingTime(s.start_time.slice(0, 5));
                                        if (s.end_time) setBookingEndTime(s.end_time.slice(0, 5));
                                        setGuests(1);
                                      }}
                                    >
                                      <Ionicons name="calendar-outline" size={15} color="#FFFFFF" style={{ marginRight: 6 }} />
                                      <Text style={[styles.sessionBookBtnText, { fontSize: 15, fontWeight: '700' }]}>Book this Session</Text>
                                    </TouchableOpacity>
                                  ) : (
                                    /* Expanded session booking controls with guest selection & validation */
                                    <View style={{ marginTop: 4, paddingTop: 12, borderTopWidth: 1, borderTopColor: 'rgba(255,255,255,0.08)' }}>
                                      <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginBottom: 12 }}>
                                        <View style={{ flex: 1 }}>
                                          <Text style={{ fontSize: 13, fontWeight: '700', color: theme.textMain }}>
                                            Number of People
                                          </Text>
                                          <Text style={{ fontSize: 12, color: theme.textSub, marginTop: 2 }}>
                                            {slotsLeft} slot{slotsLeft > 1 ? 's' : ''} available for this session
                                          </Text>
                                        </View>
                                        <View style={styles.guestRow}>
                                          <TouchableOpacity
                                            style={[styles.stepBtn, { borderColor: theme.border }]}
                                            disabled={guests <= 1}
                                            onPress={() => setGuests((g) => Math.max(1, g - 1))}
                                          >
                                            <Ionicons name="remove" size={18} color={guests <= 1 ? theme.textSub : theme.textMain} />
                                          </TouchableOpacity>
                                          <Text style={[styles.guestCount, { color: theme.textMain }]}>{guests}</Text>
                                          <TouchableOpacity
                                            style={[
                                              styles.stepBtn,
                                              { borderColor: theme.border },
                                              guests >= slotsLeft && { opacity: 0.4 }
                                            ]}
                                            disabled={guests >= slotsLeft}
                                            onPress={() => {
                                              if (guests >= slotsLeft) {
                                                Alert.alert('Slot Limit', `Only ${slotsLeft} slot${slotsLeft > 1 ? 's are' : ' is'} available for this session.`);
                                                return;
                                              }
                                              setGuests((g) => g + 1);
                                            }}
                                          >
                                            <Ionicons name="add" size={18} color={guests >= slotsLeft ? theme.textSub : theme.textMain} />
                                          </TouchableOpacity>
                                        </View>
                                      </View>

                                      {/* Total Price for Session */}
                                      <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginBottom: 12, paddingVertical: 8, paddingHorizontal: 12, backgroundColor: 'rgba(255,255,255,0.03)', borderRadius: 8 }}>
                                        <Text style={{ fontSize: 13, fontWeight: '600', color: theme.textSub }}>
                                          Total ({isPerGroup ? 'Fixed group rate' : `₱${formatPrice(listing?.price || 0)} × ${guests} guest${guests > 1 ? 's' : ''}`}):
                                        </Text>
                                        <Text style={{ fontSize: 16, fontWeight: '700', color: theme.textMain }}>
                                          ₱{formatPrice(totalAmount)}
                                        </Text>
                                      </View>

                                      {/* Proceed to Payment Action */}
                                      <TouchableOpacity
                                        style={[styles.sessionBookBtn, { backgroundColor: '#10B981', height: 46, borderRadius: 12 }]}
                                        activeOpacity={0.88}
                                        onPress={() => {
                                          if (guests > slotsLeft) {
                                            Alert.alert('Capacity Exceeded', `Only ${slotsLeft} slot(s) available for this session.`);
                                            return;
                                          }
                                          goToPayment();
                                        }}
                                      >
                                        <Ionicons name="card-outline" size={16} color="#FFFFFF" style={{ marginRight: 6 }} />
                                        <Text style={[styles.sessionBookBtnText, { fontSize: 15, fontWeight: '700' }]}>
                                          Proceed to Payment ({formatPrice(totalAmount)})
                                        </Text>
                                      </TouchableOpacity>
                                    </View>
                                  )}
                                </View>
                              );
                            })
                          ) : (
                            <View style={{ paddingVertical: 24, alignItems: 'center' }}>
                              <Ionicons name="calendar-outline" size={36} color={theme.textSub} style={{ marginBottom: 8, opacity: 0.6 }} />
                              <Text style={{ fontSize: 15, fontWeight: '700', color: theme.textMain, marginBottom: 4 }}>No Scheduled Sessions</Text>
                              <Text style={{ fontSize: 13, color: theme.textSub, textAlign: 'center' }}>
                                There are currently no upcoming sessions scheduled for this tour. Please check back later.
                              </Text>
                            </View>
                          )}
                        </View>
                      ) : (
                        /* ── STATE 1: Calendar View (Image 1 for non-scheduled listings) ── */
                        <CalendarPicker
                          value={bookingDates[0] || ''}
                          onChange={(pickedDate) => {
                            if (!canChangeDate && hasSchedule) return;
                            updateBookingDates([pickedDate]);
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
                      )}
                    </>
                  )}

                  {/* Date details and Checkout - displayed in State 1 (Image 1) */}
                  {bookingViewMode === 'date' ? (
                    <>
                      {isHotel && hotelRooms.length > 0 ? (
                        <View style={{ marginTop: 12 }}>
                          <Text style={[styles.modalLabel, { color: theme.textSub }]}>Select room</Text>
                          <ScrollView horizontal showsHorizontalScrollIndicator={false} style={{ marginHorizontal: -4 }}>
                            {hotelRooms.map((rm) => {
                              const isCur = selectedRoom?.id === rm.id;
                              const isAvail = rm.status === 'available';
                              return (
                                <TouchableOpacity
                                  key={rm.id}
                                  disabled={!isAvail}
                                  onPress={() => setSelectedRoom(rm)}
                                  style={[
                                    styles.timeChip,
                                    { backgroundColor: theme.inputBg, borderColor: theme.border, marginHorizontal: 4, paddingVertical: 10, paddingHorizontal: 12 },
                                    isCur && { backgroundColor: theme.accent, borderColor: theme.accent },
                                    !isAvail && { opacity: 0.4 },
                                  ]}
                                >
                                  <Text style={[styles.timeChipText, { color: isCur ? '#FFFFFF' : theme.textMain }]}>
                                    {rm.name}
                                  </Text>
                                  <Text style={[styles.bookedTag, { color: isCur ? '#FFFFFF' : theme.textSub }]}>
                                    {formatPrice(rm.price_per_night)}/nt
                                  </Text>
                                </TouchableOpacity>
                              );
                            })}
                          </ScrollView>
                        </View>
                      ) : null}

                      {!isHotel && hasSchedule ? (
                        <View style={[styles.guideTimeCard, { backgroundColor: theme.card, borderColor: theme.border }]}>
                          <View style={{ flexDirection: 'row', alignItems: 'center', gap: 10, marginBottom: 6 }}>
                            <View style={styles.guideTimeIconBox}>
                              <Ionicons name="time" size={18} color="#10B981" />
                            </View>
                            <View style={{ flex: 1 }}>
                              <Text style={[styles.guideTimeLabel, { color: theme.textSub }]}>Tour Session Time</Text>
                              <Text style={[styles.guideTimeValue, { color: theme.textMain }]}>
                                {bookingTime ? `${formatTimeLabel(bookingTime)}${bookingEndTime ? ` – ${formatTimeLabel(bookingEndTime)}` : ''}` : 'Guide Defined Operating Hours'}
                              </Text>
                            </View>
                            <View style={styles.guideTimePill}>
                              <Ionicons name="shield-checkmark" size={12} color="#10B981" style={{ marginRight: 4 }} />
                              <Text style={styles.guideTimePillText}>Guide Defined</Text>
                            </View>
                          </View>
                          <Text style={[styles.guideTimeSubtext, { color: theme.textSub }]}>
                            The start and end times for this tour are predefined by the guide. You do not need to choose a time.
                          </Text>
                        </View>
                      ) : null}

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
                          style={[
                            styles.stepBtn,
                            { borderColor: theme.border },
                            (() => {
                              const maxAllowed = hasSchedule && currentSession
                                ? (currentSession.available_slots ?? listing?.capacity ?? 10)
                                : attractionAvailableSlots;
                              return guests >= maxAllowed ? { opacity: 0.5 } : undefined;
                            })()
                          ]}
                          onPress={() => {
                            const maxAllowed = hasSchedule && currentSession
                              ? (currentSession.available_slots ?? listing?.capacity ?? 10)
                              : attractionAvailableSlots;
                            if (guests >= maxAllowed) {
                              Alert.alert('Capacity Limit', `Only ${maxAllowed} slot${maxAllowed === 1 ? '' : 's'} available.`);
                              return;
                            }
                            setGuests((g) => Math.min(maxAllowed, g + 1));
                          }}
                        >
                          <Ionicons name="add" size={20} color={theme.textMain} />
                        </TouchableOpacity>
                      </View>
                      <Text style={{
                        fontSize: 12,
                        color: isAttractionFullyBooked ? '#EF4444' : theme.textSub,
                        fontWeight: isAttractionFullyBooked ? '700' : '400',
                        marginTop: 4,
                        marginBottom: 8
                      }}>
                        {isAttractionFullyBooked
                          ? 'Fully Booked · No slots available'
                          : hasSchedule && currentSession
                            ? `${currentSession.available_slots ?? listing?.capacity ?? 10} of ${currentSession.capacity ?? listing?.capacity ?? 10} slots available`
                            : `${attractionAvailableSlots} of ${totalAttractionCapacity} slots available (Available Slots: ${attractionAvailableSlots}/${totalAttractionCapacity})`}
                      </Text>

                      {isHotel && selectedRoom ? (
                        <View style={[styles.priceSummaryBox, { backgroundColor: theme.card, padding: 12, borderRadius: 12, borderWidth: 1, borderColor: theme.border }]}>
                          <View style={{ flexDirection: 'row', justifyContent: 'space-between', marginBottom: 4 }}>
                            <Text style={[styles.priceBreakdownText, { color: theme.textSub }]}>Total Stay ({hotelNights} night{hotelNights > 1 ? 's' : ''}):</Text>
                            <Text style={[styles.totalLine, { fontSize: 14, color: theme.textMain }]}>{formatPrice(hotelTotal)}</Text>
                          </View>
                          <View style={{ flexDirection: 'row', justifyContent: 'space-between', marginBottom: 4 }}>
                            <Text style={[styles.priceBreakdownText, { color: '#059669', fontWeight: '700' }]}>Deposit Due Today ({depositPct}%):</Text>
                            <Text style={[styles.totalLine, { color: '#059669' }]}>{formatPrice(hotelDeposit)}</Text>
                          </View>
                          <View style={{ flexDirection: 'row', justifyContent: 'space-between', borderTopWidth: 1, borderTopColor: theme.border, paddingTop: 6, marginTop: 4 }}>
                            <Text style={[styles.priceBreakdownText, { color: '#D97706', fontWeight: '700' }]}>Balance at Hotel Check-in:</Text>
                            <Text style={[styles.totalLine, { fontSize: 14, color: '#D97706' }]}>{formatPrice(hotelBalance)}</Text>
                          </View>
                          <Text style={[styles.slotHint, { color: theme.textSub, marginTop: 6 }]}>
                            🔒 Free cancellation up to 48 hours before check-in. Remaining balance is paid at the hotel front desk upon arrival.
                          </Text>
                        </View>
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

                      <TouchableOpacity
                        style={[
                          styles.confirmBtn,
                          { backgroundColor: isAttractionFullyBooked ? '#64748B' : theme.accent },
                          (booking || isAttractionFullyBooked) && { opacity: 0.6 }
                        ]}
                        onPress={goToPayment}
                        disabled={booking || isAttractionFullyBooked}
                        activeOpacity={0.85}
                      >
                        <Text style={styles.confirmBtnText}>
                          {isAttractionFullyBooked
                            ? 'Fully Booked'
                            : totalAmount > 0
                              ? 'Proceed to payment'
                              : booking
                                ? 'Booking...'
                                : 'Confirm booking'}
                        </Text>
                      </TouchableOpacity>
                    </>
                  ) : null}
                </>
              ) : null}

              {/* ── STEP 2: Choose payment method ── */}
              {step === 'method' ? (
                <>
                  <Text style={[styles.payTotal, { color: theme.textMain }]}>
                    Amount to pay: <Text style={{ color: theme.accent }}>{formatPrice(totalAmount)}</Text>
                  </Text>

                  {renderVoucherControls()}

                  <TouchableOpacity
                    style={[styles.methodRow, { borderColor: theme.border, backgroundColor: theme.card }]}
                    activeOpacity={0.8}
                    onPress={() => { setQrMethod('gcash'); setPaymentReference(''); setStep('qr'); }}
                  >
                    <View style={[styles.methodIcon, { backgroundColor: '#E0EDFF' }]}>
                      <Ionicons name="qr-code" size={22} color="#1d4ed8" />
                    </View>
                    <View style={{ flex: 1 }}>
                      <Text style={[styles.methodTitle, { color: theme.textMain }]}>GCash / e-Wallet</Text>
                      <Text style={[styles.methodSub, { color: theme.textSub }]}>Scan the QR with your e-wallet app</Text>
                    </View>
                    <Ionicons name="chevron-forward" size={20} color={theme.textSub} />
                  </TouchableOpacity>

                  <TouchableOpacity
                    style={[styles.methodRow, { borderColor: theme.border, backgroundColor: theme.card }]}
                    activeOpacity={0.8}
                    onPress={() => { setQrMethod('instapay'); setPaymentReference(''); setStep('qr'); }}
                  >
                    <View style={[styles.methodIcon, { backgroundColor: '#FEE2E2' }]}>
                      <Ionicons name="qr-code" size={22} color="#dc2626" />
                    </View>
                    <View style={{ flex: 1 }}>
                      <Text style={[styles.methodTitle, { color: theme.textMain }]}>InstaPay</Text>
                      <Text style={[styles.methodSub, { color: theme.textSub }]}>Scan with any bank app via InstaPay</Text>
                    </View>
                    <Ionicons name="chevron-forward" size={20} color={theme.textSub} />
                  </TouchableOpacity>

                  <TouchableOpacity
                    style={[styles.methodRow, { borderColor: theme.border, backgroundColor: theme.card }]}
                    activeOpacity={0.8}
                    onPress={() => setStep('card')}
                  >
                    <View style={[styles.methodIcon, { backgroundColor: '#D1FAE5' }]}>
                      <Ionicons name="card" size={22} color="#059669" />
                    </View>
                    <View style={{ flex: 1 }}>
                      <Text style={[styles.methodTitle, { color: theme.textMain }]}>Debit / Credit Card</Text>
                      <Text style={[styles.methodSub, { color: theme.textSub }]}>Visa, Mastercard, etc.</Text>
                    </View>
                    <Ionicons name="chevron-forward" size={20} color={theme.textSub} />
                  </TouchableOpacity>
                </>
              ) : null}

              {/* ── STEP 3a: QR payment ── */}
              {step === 'qr' ? (
                <>
                  <Text style={[styles.payTotal, { color: theme.textMain }]}>
                    Pay <Text style={{ color: theme.accent }}>{formatPrice(totalAmount)}</Text> via{' '}
                    {qrMethod === 'gcash' ? 'GCash / e-Wallet' : 'InstaPay'}
                  </Text>

                  {renderVoucherControls()}

                  <View style={styles.qrWrap}>
                    <Image
                      source={qrMethod === 'gcash' ? QR_EWALLET : QR_INSTAPAY}
                      style={styles.qrImage}
                      resizeMode="contain"
                    />
                  </View>
                  <Text style={[styles.qrHint, { color: theme.textSub }]}>
                    Open your app, scan this QR, pay the exact amount, then enter your reference number below.
                  </Text>

                  <Text style={[styles.modalLabel, { color: theme.textSub }]}>Reference / confirmation no.</Text>
                  <TextInput
                    style={[styles.input, { backgroundColor: theme.inputBg, color: theme.textMain }]}
                    placeholder="e.g. 1234567890"
                    placeholderTextColor={theme.textSub}
                    value={paymentReference}
                    onChangeText={setPaymentReference}
                    autoCapitalize="characters"
                  />

                  <TouchableOpacity
                    style={[styles.confirmBtn, { backgroundColor: theme.accent }, booking && { opacity: 0.6 }]}
                    onPress={confirmQrPaid}
                    disabled={booking}
                    activeOpacity={0.85}
                  >
                    {booking ? (
                      <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8 }}>
                        <ActivityIndicator size="small" color="#FFFFFF" />
                        <Text style={styles.confirmBtnText}>Verifying payment...</Text>
                      </View>
                    ) : (
                      <Text style={styles.confirmBtnText}>I&apos;ve paid</Text>
                    )}
                  </TouchableOpacity>
                </>
              ) : null}

              {/* ── STEP 3b: Card payment ── */}
              {step === 'card' ? (
                <>
                  <Text style={[styles.payTotal, { color: theme.textMain }]}>
                    Pay <Text style={{ color: theme.accent }}>{formatPrice(totalAmount)}</Text>
                  </Text>

                  {renderVoucherControls()}

                  <Text style={[styles.modalLabel, { color: theme.textSub }]}>Cardholder name</Text>
                  <TextInput
                    style={[styles.input, { backgroundColor: theme.inputBg, color: theme.textMain }]}
                    placeholder="Name on card"
                    placeholderTextColor={theme.textSub}
                    value={cardName}
                    onChangeText={setCardName}
                    autoCapitalize="words"
                  />

                  <Text style={[styles.modalLabel, { color: theme.textSub }]}>Card number</Text>
                  <TextInput
                    style={[styles.input, { backgroundColor: theme.inputBg, color: theme.textMain }]}
                    placeholder="1234 5678 9012 3456"
                    placeholderTextColor={theme.textSub}
                    value={cardNumber}
                    onChangeText={formatCardNumber}
                    keyboardType="number-pad"
                  />

                  <View style={styles.cardRow}>
                    <View style={{ flex: 1, marginRight: 10 }}>
                      <Text style={[styles.modalLabel, { color: theme.textSub }]}>Expiry (MM/YY)</Text>
                      <TextInput
                        style={[styles.input, { backgroundColor: theme.inputBg, color: theme.textMain }]}
                        placeholder="MM/YY"
                        placeholderTextColor={theme.textSub}
                        value={cardExpiry}
                        onChangeText={formatExpiry}
                        keyboardType="number-pad"
                      />
                    </View>
                    <View style={{ flex: 1 }}>
                      <Text style={[styles.modalLabel, { color: theme.textSub }]}>CVV</Text>
                      <TextInput
                        style={[styles.input, { backgroundColor: theme.inputBg, color: theme.textMain }]}
                        placeholder="123"
                        placeholderTextColor={theme.textSub}
                        value={cardCvv}
                        onChangeText={(t) => setCardCvv(t.replace(/\D/g, '').slice(0, 4))}
                        keyboardType="number-pad"
                        secureTextEntry
                      />
                    </View>
                  </View>

                  <TouchableOpacity
                    style={[styles.confirmBtn, { backgroundColor: theme.accent }, booking && { opacity: 0.6 }]}
                    onPress={confirmCardPaid}
                    disabled={booking}
                    activeOpacity={0.85}
                  >
                    {booking ? (
                      <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8 }}>
                        <ActivityIndicator size="small" color="#FFFFFF" />
                        <Text style={styles.confirmBtnText}>Processing payment...</Text>
                      </View>
                    ) : (
                      <Text style={styles.confirmBtnText}>{`Pay ${formatPrice(totalAmount)}`}</Text>
                    )}
                  </TouchableOpacity>
                </>
              ) : null}
              {step === 'confirmed' && bookingConfirmedData ? (
                <View style={{ paddingVertical: 20, alignItems: 'center' }}>
                  <View style={{ width: 72, height: 72, borderRadius: 36, backgroundColor: 'rgba(16,185,129,0.12)', alignItems: 'center', justifyContent: 'center', marginBottom: 16 }}>
                    <Ionicons name="checkmark-circle" size={54} color="#10B981" />
                  </View>
                  <Text style={{ fontSize: 20, fontWeight: '700', color: theme.textMain, marginBottom: 6, textAlign: 'center' }}>
                    Booking Confirmed!
                  </Text>
                  <Text style={{ fontSize: 13, color: theme.textSub, textAlign: 'center', marginBottom: 20, paddingHorizontal: 16 }}>
                    Your session has been successfully booked. Confirmation and booking details are saved below.
                  </Text>

                  <View style={{ width: '100%', borderRadius: 14, borderWidth: 1, borderColor: theme.border, backgroundColor: theme.card, padding: 16, marginBottom: 20 }}>
                    <View style={{ flexDirection: 'row', justifyContent: 'space-between', paddingBottom: 10, borderBottomWidth: 1, borderBottomColor: 'rgba(255,255,255,0.06)', marginBottom: 10 }}>
                      <Text style={{ fontSize: 13, color: theme.textSub }}>Tour Experience</Text>
                      <Text style={{ fontSize: 13, fontWeight: '700', color: theme.textMain, maxWidth: '60%' }} numberOfLines={1}>{listing.title}</Text>
                    </View>
                    <View style={{ flexDirection: 'row', justifyContent: 'space-between', paddingBottom: 10, borderBottomWidth: 1, borderBottomColor: 'rgba(255,255,255,0.06)', marginBottom: 10 }}>
                      <Text style={{ fontSize: 13, color: theme.textSub }}>Session Date</Text>
                      <Text style={{ fontSize: 13, fontWeight: '700', color: theme.textMain }}>{formatScheduleDate(bookingConfirmedData.sessionDate)}</Text>
                    </View>
                    {bookingConfirmedData.sessionTime ? (
                      <View style={{ flexDirection: 'row', justifyContent: 'space-between', paddingBottom: 10, borderBottomWidth: 1, borderBottomColor: 'rgba(255,255,255,0.06)', marginBottom: 10 }}>
                        <Text style={{ fontSize: 13, color: theme.textSub }}>Session Time</Text>
                        <Text style={{ fontSize: 13, fontWeight: '700', color: theme.textMain }}>{bookingConfirmedData.sessionTime}</Text>
                      </View>
                    ) : null}
                    <View style={{ flexDirection: 'row', justifyContent: 'space-between', paddingBottom: 10, borderBottomWidth: 1, borderBottomColor: 'rgba(255,255,255,0.06)', marginBottom: 10 }}>
                      <Text style={{ fontSize: 13, color: theme.textSub }}>Participants Booked</Text>
                      <Text style={{ fontSize: 13, fontWeight: '700', color: '#10B981' }}>{bookingConfirmedData.guests} participant{bookingConfirmedData.guests > 1 ? 's' : ''}</Text>
                    </View>
                    <View style={{ flexDirection: 'row', justifyContent: 'space-between' }}>
                      <Text style={{ fontSize: 13, color: theme.textSub }}>Session Availability</Text>
                      <Text style={{ fontSize: 13, fontWeight: '700', color: bookingConfirmedData.remainingSlots > 0 ? '#10B981' : '#EF4444' }}>
                        {bookingConfirmedData.remainingSlots > 0
                          ? `${bookingConfirmedData.remainingSlots} of ${bookingConfirmedData.totalCapacity} slots available`
                          : 'Fully Booked'}
                      </Text>
                    </View>
                  </View>

                  <TouchableOpacity
                    style={[styles.confirmBtn, { backgroundColor: '#10B981', width: '100%', marginBottom: 10 }]}
                    onPress={() => {
                      setStep('details');
                      setBookingViewMode('session');
                    }}
                    activeOpacity={0.88}
                  >
                    <Ionicons name="calendar-outline" size={16} color="#FFFFFF" style={{ marginRight: 6 }} />
                    <Text style={styles.confirmBtnText}>View Updated Sessions</Text>
                  </TouchableOpacity>

                  <TouchableOpacity
                    style={{ width: '100%', height: 44, borderRadius: 12, borderWidth: 1, borderColor: theme.border, alignItems: 'center', justifyContent: 'center' }}
                    onPress={() => setModalOpen(false)}
                    activeOpacity={0.85}
                  >
                    <Text style={{ fontSize: 14, fontWeight: '600', color: theme.textMain }}>Done</Text>
                  </TouchableOpacity>
                </View>
              ) : null}
            </ScrollView>
          </View>
        </View>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1 },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  hero: { width: '100%', height: 300, resizeMode: 'cover', backgroundColor: '#00000022' },
  heroOverlay: { ...(StyleSheet.absoluteFill as any), backgroundColor: 'rgba(0,0,0,0.28)' },
  backButton: {
    position: 'absolute',
    top: 44,
    left: 16,
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: 'rgba(0,0,0,0.4)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  favButton: { position: 'absolute', top: 44, right: 16 },
  heroTextWrap: { position: 'absolute', bottom: 18, left: 18, right: 18 },
  areaChip: {
    flexDirection: 'row',
    alignItems: 'center',
    alignSelf: 'flex-start',
    backgroundColor: 'rgba(0,0,0,0.45)',
    paddingVertical: 4,
    paddingHorizontal: 10,
    borderRadius: 20,
    marginBottom: 8,
  },
  areaChipText: { color: '#FFFFFF', fontSize: 12, fontWeight: '700', marginLeft: 4 },
  heroTitle: { color: '#FFFFFF', fontSize: 26, fontWeight: '900', letterSpacing: -0.5 },
  body: { padding: 20 },
  factsRow: { flexDirection: 'row', gap: 12, marginBottom: 12 },
  factCard: { flex: 1, borderRadius: 14, borderWidth: 1, paddingVertical: 14, paddingHorizontal: 14 },
  factLabel: { fontSize: 12, marginTop: 6 },
  factValue: { fontSize: 15, fontWeight: '700', marginTop: 2 },
  factLinkRow: { flexDirection: 'row', alignItems: 'center', gap: 2, marginTop: 6 },
  factLink: { fontSize: 12, fontWeight: '700' },
  reviewSummary: { flexDirection: 'row', alignItems: 'center', gap: 6, marginBottom: 6 },
  reviewSummaryScore: { fontSize: 22, fontWeight: '800' },
  reviewSummaryCount: { fontSize: 14, fontWeight: '600' },
  reviewEmpty: { alignItems: 'center', paddingVertical: 34, paddingHorizontal: 20, gap: 12 },
  reviewEmptyText: { fontSize: 14, textAlign: 'center', lineHeight: 20 },
  reviewItem: { paddingVertical: 14, borderTopWidth: StyleSheet.hairlineWidth },
  reviewItemHead: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  reviewAvatar: { width: 38, height: 38, borderRadius: 19, alignItems: 'center', justifyContent: 'center' },
  reviewAvatarText: { fontSize: 16, fontWeight: '800' },
  reviewName: { fontSize: 14, fontWeight: '700' },
  reviewStars: { flexDirection: 'row', gap: 1, marginTop: 3 },
  reviewDate: { fontSize: 12 },
  reviewComment: { fontSize: 14, lineHeight: 21, marginTop: 10 },
  metaLine: { fontSize: 13, fontWeight: '600' },
  messageGuideBtn: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', borderWidth: 1.5, borderRadius: 14, paddingVertical: 12, marginTop: 16 },
  messageGuideText: { fontSize: 15, fontWeight: '700' },
  sectionTitle: { fontSize: 17, fontWeight: '800', marginTop: 20, marginBottom: 10 },
  about: { fontSize: 14, lineHeight: 22 },
  hotelCard: {
    borderRadius: 18,
    overflow: 'hidden',
    backgroundColor: '#EAF7F0',
    borderWidth: 1.5,
    borderColor: '#0B3D2E',
    shadowColor: '#0B3D2E',
    shadowOpacity: 0.18,
    shadowRadius: 12,
    shadowOffset: { width: 0, height: 6 },
    elevation: 4,
  },
  hotelImage: { width: '100%', height: 150, backgroundColor: '#D4E9DE' },
  hotelDistanceBadge: {
    position: 'absolute',
    top: 12,
    left: 12,
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#FBE38A',
    paddingHorizontal: 10,
    paddingVertical: 5,
    borderRadius: 999,
  },
  hotelDistanceText: { fontSize: 12, fontWeight: '800', color: '#0B3D2E', marginLeft: 4 },
  hotelBody: { padding: 14 },
  hotelName: { fontSize: 16, fontWeight: '800', color: '#0B3D2E' },
  hotelMetaRow: { flexDirection: 'row', alignItems: 'center', marginTop: 4 },
  hotelMeta: { fontSize: 13, color: '#3B6B57', marginLeft: 4, flex: 1 },
  hotelPrice: { fontSize: 15, fontWeight: '800', color: '#0B3D2E', marginTop: 8 },
  hotelPriceUnit: { fontSize: 12, fontWeight: '600', color: '#3B6B57' },
  hotelDirBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#FBE38A',
    borderRadius: 12,
    paddingVertical: 11,
    marginTop: 12,
  },
  hotelDirText: { fontSize: 14, fontWeight: '800', color: '#0B3D2E' },
  bookBar: {
    position: 'absolute',
    left: 0,
    right: 0,
    bottom: 0,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 20,
    paddingTop: 12,
    paddingBottom: 26,
    borderTopWidth: 1,
  },
  bookPriceLabel: { fontSize: 11 },
  bookPrice: { fontSize: 18, fontWeight: '800' },
  bookPer: { fontSize: 12, fontWeight: '500' },
  bookButton: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', borderRadius: 14, paddingVertical: 15, paddingHorizontal: 32 },
  bookedButton: { backgroundColor: '#9CA3AF' },
  bookButtonText: { color: '#FFFFFF', fontSize: 15, fontWeight: '700' },
  modalBackdrop: { flex: 1, backgroundColor: 'rgba(0,0,0,0.5)', justifyContent: 'flex-end' },
  modalCard: { borderTopLeftRadius: 22, borderTopRightRadius: 22, padding: 22, paddingBottom: 34, maxHeight: '90%' },
  modalHeader: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginBottom: 16 },
  modalTitle: { fontSize: 18, fontWeight: '800', flex: 1, marginRight: 12 },
  modalLabel: { fontSize: 13, fontWeight: '600', marginBottom: 6, marginTop: 12 },
  modalSubHint: { fontSize: 12, marginBottom: 8, lineHeight: 16 },
  selectedDatesWrap: { marginTop: 10 },
  selectedDatesLabel: { fontSize: 12, fontWeight: '700', marginBottom: 6 },
  chipsContainer: { flexDirection: 'row', flexWrap: 'wrap', gap: 6 },
  dateChip: { flexDirection: 'row', alignItems: 'center', paddingVertical: 5, paddingHorizontal: 9, borderRadius: 8, borderWidth: 1 },
  dateChipText: { fontSize: 12, fontWeight: '700' },
  input: { borderRadius: 12, paddingVertical: 13, paddingHorizontal: 16, fontSize: 15 },
  timeGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginTop: 2 },
  hotelStayBanner: { borderRadius: 12, borderWidth: 1, padding: 12, marginTop: 10, marginBottom: 4 },
  hotelDateCard: { flex: 1, borderRadius: 12, borderWidth: 1, paddingVertical: 10, paddingHorizontal: 12 },
  hotelDateLabel: { fontSize: 10, fontWeight: '800', letterSpacing: 0.5 },
  hotelDateValue: { fontSize: 15, fontWeight: '800', marginTop: 4 },
  timeChip: { paddingVertical: 9, paddingHorizontal: 14, borderRadius: 12, borderWidth: 1, alignItems: 'center' },
  timeChipText: { fontSize: 13, fontWeight: '700' },
  bookedTag: { fontSize: 9, fontWeight: '700', marginTop: 1 },
  slotHint: { fontSize: 12, marginTop: 8, fontStyle: 'italic' },
  guestRow: { flexDirection: 'row', alignItems: 'center' },
  stepBtn: { width: 42, height: 42, borderRadius: 12, borderWidth: 1, alignItems: 'center', justifyContent: 'center' },
  guestCount: { fontSize: 18, fontWeight: '800', marginHorizontal: 20 },
  priceSummaryBox: { marginTop: 16 },
  totalLine: { fontSize: 16, fontWeight: '800' },
  priceBreakdownText: { fontSize: 12, marginTop: 3 },
  confirmBtn: { borderRadius: 14, paddingVertical: 15, alignItems: 'center', marginTop: 20 },
  confirmBtnText: { color: '#FFFFFF', fontSize: 15, fontWeight: '700' },
  payTotal: { fontSize: 16, fontWeight: '700', marginBottom: 16 },
  methodRow: {
    flexDirection: 'row',
    alignItems: 'center',
    borderWidth: 1,
    borderRadius: 14,
    padding: 14,
    marginBottom: 12,
  },
  methodIcon: { width: 44, height: 44, borderRadius: 12, alignItems: 'center', justifyContent: 'center', marginRight: 14 },
  methodTitle: { fontSize: 15, fontWeight: '700' },
  methodSub: { fontSize: 12, marginTop: 2 },
  qrWrap: { alignItems: 'center', backgroundColor: '#FFFFFF', borderRadius: 16, padding: 14, marginBottom: 12 },
  qrImage: { width: 230, height: 230 },
  qrHint: { fontSize: 13, lineHeight: 19, marginBottom: 4, textAlign: 'center' },
  cardRow: { flexDirection: 'row' },
  voucherCard: { borderWidth: 1, borderRadius: 16, marginBottom: 14, overflow: 'hidden' },
  voucherApplied: { flexDirection: 'row', alignItems: 'center', padding: 12, gap: 10 },
  voucherAppliedIcon: { width: 36, height: 36, borderRadius: 10, alignItems: 'center', justifyContent: 'center' },
  voucherSummaryTitle: { fontSize: 14, fontWeight: '800' },
  voucherSummaryText: { fontSize: 12, marginTop: 2 },
  voucherClear: { fontSize: 13, fontWeight: '800' },
  voucherToggle: { flexDirection: 'row', alignItems: 'center', paddingHorizontal: 12, paddingVertical: 12 },
  voucherToggleTitle: { fontSize: 14, fontWeight: '800' },
  voucherToggleSub: { fontSize: 12, marginTop: 2 },
  voucherEntryRow: { flexDirection: 'row', alignItems: 'center', paddingHorizontal: 12, paddingBottom: 12, gap: 8 },
  voucherInputWrap: { flex: 1, minWidth: 0, borderWidth: 1, borderRadius: 12, height: 44, justifyContent: 'center' },
  voucherInput: { paddingHorizontal: 12, paddingVertical: 0, fontSize: 15, height: 44 },
  voucherApplyBtn: { height: 44, borderRadius: 12, paddingHorizontal: 16, alignItems: 'center', justifyContent: 'center', flexShrink: 0 },
  voucherApplyText: { color: '#FFFFFF', fontSize: 13, fontWeight: '800' },
  voucherHint: { fontSize: 12, lineHeight: 17, paddingHorizontal: 12, paddingBottom: 12 },
  heroContainer: { position: 'relative', width: '100%', height: 340, backgroundColor: '#0B0D12', overflow: 'hidden' },
  heroIndicatorRow: {
    position: 'absolute',
    bottom: 74,
    left: 18,
    right: 18,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    zIndex: 9,
  },
  heroDotsContainer: { flexDirection: 'row', alignItems: 'center', gap: 5 },
  heroDot: { height: 6, borderRadius: 3 },
  heroDotActive: { width: 22, backgroundColor: '#FFFFFF' },
  heroDotInactive: { width: 6, backgroundColor: 'rgba(255,255,255,0.45)' },
  heroCounterPill: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: 'rgba(0,0,0,0.55)',
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 12,
  },
  heroCounterText: { color: '#FFFFFF', fontSize: 11, fontWeight: '700' },
  roomImageWrap: { position: 'relative', width: '100%', height: 190, backgroundColor: '#1E293B', overflow: 'hidden' },
  roomTapInspectBadge: {
    position: 'absolute',
    bottom: 8,
    right: 8,
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: 'rgba(0,0,0,0.65)',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 8,
  },
  roomTapInspectText: { color: '#FFFFFF', fontSize: 11, fontWeight: '600' },
  roomModalSheet: {
    borderTopLeftRadius: 24,
    borderTopRightRadius: 24,
    paddingHorizontal: 22,
    paddingTop: 18,
    paddingBottom: 34,
    maxHeight: '92%',
    borderTopWidth: 1,
  },
  roomModalHeader: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginBottom: 14 },
  roomModalSheetTitle: { fontSize: 18, fontWeight: '800' },
  roomModalCloseBtn: {
    width: 32,
    height: 32,
    borderRadius: 16,
    backgroundColor: 'rgba(255,255,255,0.1)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  roomModalGalleryWrap: { position: 'relative', width: '100%', height: 230, borderRadius: 16, overflow: 'hidden', backgroundColor: '#1E293B' },
  roomModalImage: { width: '100%', height: 230, resizeMode: 'cover' },
  roomModalDotsWrap: {
    position: 'absolute',
    bottom: 10,
    alignSelf: 'center',
    flexDirection: 'row',
    gap: 5,
    backgroundColor: 'rgba(0,0,0,0.45)',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 10,
  },
  roomModalDot: { height: 5, borderRadius: 2.5 },
  roomModalDotActive: { width: 16, backgroundColor: '#FFFFFF' },
  roomModalDotInactive: { width: 5, backgroundColor: 'rgba(255,255,255,0.45)' },
  roomModalTitleRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 },
  roomModalName: { fontSize: 20, fontWeight: '800', flex: 1, marginRight: 10 },
  roomModalHighlights: { flexDirection: 'row', gap: 10, marginBottom: 16 },
  roomHighlightCard: { flex: 1, borderRadius: 12, borderWidth: 1, padding: 10, alignItems: 'center' },
  roomHighlightLabel: { fontSize: 10.5, fontWeight: '600', marginTop: 4 },
  roomHighlightValue: { fontSize: 12.5, fontWeight: '700', marginTop: 2, textAlign: 'center' },
  roomModalSection: { marginBottom: 16 },
  roomModalSectionHeading: { fontSize: 15, fontWeight: '800', marginBottom: 8 },
  roomModalDescText: { fontSize: 13.5, lineHeight: 20 },
  roomModalAmenitiesGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  roomModalAmenityPill: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 6,
    paddingHorizontal: 10,
    borderRadius: 10,
    borderWidth: 1,
  },
  roomModalAmenityText: { fontSize: 12.5, fontWeight: '600' },
  roomModalPricingCard: { borderRadius: 14, borderWidth: 1, padding: 14, marginBottom: 18 },
  roomModalPricingRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  roomModalPricingLabel: { fontSize: 13, fontWeight: '600' },
  roomModalPricingValue: { fontSize: 14, fontWeight: '800' },
  roomModalActions: { gap: 10 },
  roomModalPrimaryBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 14,
    paddingVertical: 14,
  },
  roomModalPrimaryBtnText: { color: '#FFFFFF', fontSize: 15, fontWeight: '800' },
  roomModalSecondaryBtn: {
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 14,
    borderWidth: 1,
    paddingVertical: 12,
  },
  roomModalSecondaryBtnText: { fontSize: 14, fontWeight: '700' },
  roomCard: { borderRadius: 18, borderWidth: 1, marginBottom: 16, overflow: 'hidden' },
  roomImage: { width: '100%', height: 160, backgroundColor: '#E2E8F0' },
  roomBody: { padding: 14 },
  roomHeaderRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  roomTitle: { fontSize: 16, fontWeight: '800', flex: 1, marginRight: 8 },
  roomBadge: { paddingHorizontal: 8, paddingVertical: 4, borderRadius: 6 },
  roomBadgeText: { fontSize: 11, fontWeight: '700' },
  roomSpecs: { fontSize: 13, marginTop: 4, marginBottom: 6 },
  roomDesc: { fontSize: 13, lineHeight: 18, marginBottom: 10 },
  roomBottomRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-end', borderTopWidth: 1, borderTopColor: 'rgba(255,255,255,0.08)', paddingTop: 10 },
  roomPrice: { fontSize: 16, fontWeight: '800' },
  roomPriceUnit: { fontSize: 12, fontWeight: '500' },
  roomDepositHint: { fontSize: 11, color: '#059669', fontWeight: '600', marginTop: 2 },
  roomSelectBtn: {
    borderRadius: 10,
    paddingVertical: 9,
    paddingHorizontal: 16,
    alignItems: 'center',
    justifyContent: 'center',
    minWidth: 84,
  },
  roomSelectBtnText: { color: '#FFFFFF', fontSize: 13, fontWeight: '700', textAlign: 'center' },
  scheduleModalSheet: {
    borderTopLeftRadius: 24,
    borderTopRightRadius: 24,
    paddingHorizontal: 22,
    paddingTop: 18,
    paddingBottom: 34,
    maxHeight: '92%',
    borderTopWidth: 1,
  },
  scheduleModalHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 16,
  },
  scheduleModalTitle: {
    fontSize: 18,
    fontWeight: '800',
  },
  scheduleModalCloseBtn: {
    width: 32,
    height: 32,
    borderRadius: 16,
    backgroundColor: 'rgba(255,255,255,0.1)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  scheduleHighlightsRow: {
    flexDirection: 'row',
    gap: 10,
    marginBottom: 16,
  },
  scheduleHighlightCard: {
    flex: 1,
    borderRadius: 14,
    borderWidth: 1,
    paddingVertical: 12,
    paddingHorizontal: 10,
    alignItems: 'center',
  },
  scheduleHighlightLabel: {
    fontSize: 11,
    fontWeight: '600',
    marginTop: 5,
  },
  scheduleHighlightVal: {
    fontSize: 13,
    fontWeight: '800',
    marginTop: 2,
    textAlign: 'center',
  },
  scheduleSectionCard: {
    borderRadius: 16,
    borderWidth: 1,
    padding: 16,
    marginBottom: 14,
  },
  scheduleIconBadge: {
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: 'rgba(16,185,129,0.15)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  scheduleSectionHeading: {
    fontSize: 15,
    fontWeight: '800',
    marginBottom: 4,
  },
  scheduleAddressText: {
    fontSize: 13,
    lineHeight: 18,
    marginTop: 2,
  },
  scheduleMapsBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    alignSelf: 'flex-start',
    backgroundColor: 'rgba(16,185,129,0.12)',
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 8,
    marginTop: 10,
  },
  scheduleMapsBtnText: {
    color: '#10B981',
    fontSize: 12,
    fontWeight: '700',
  },
  scheduleCountBadge: {
    backgroundColor: 'rgba(16,185,129,0.15)',
    paddingHorizontal: 9,
    paddingVertical: 4,
    borderRadius: 8,
  },
  scheduleCountBadgeText: {
    color: '#10B981',
    fontSize: 11,
    fontWeight: '700',
  },
  sessionCard: {
    borderRadius: 16,
    borderWidth: 1,
    padding: 16,
    marginBottom: 12,
  },
  sessionHeaderRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 10,
  },
  sessionDateText: {
    fontSize: 15,
    fontWeight: '800',
  },
  sessionStatusBadge: {
    backgroundColor: '#DCFCE7',
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 6,
  },
  sessionStatusBadgeText: {
    color: '#166534',
    fontSize: 11,
    fontWeight: '700',
  },
  sessionPillsRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
    marginBottom: 10,
  },
  sessionPill: {
    flexDirection: 'row',
    alignItems: 'center',
    borderWidth: 1,
    borderRadius: 8,
    paddingHorizontal: 10,
    paddingVertical: 5,
  },
  sessionPillText: {
    fontSize: 12,
    fontWeight: '600',
  },
  sessionNoteBox: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    borderWidth: 1,
    borderRadius: 10,
    padding: 10,
    marginBottom: 12,
  },
  sessionNoteText: {
    flex: 1,
    fontSize: 12,
    lineHeight: 17,
  },
  sessionBookBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#10B981',
    borderRadius: 12,
    paddingVertical: 10,
    paddingHorizontal: 14,
  },
  sessionBookBtnText: {
    color: '#FFFFFF',
    fontSize: 13,
    fontWeight: '700',
  },
  scheduleNoticeCard: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    borderWidth: 1,
    borderRadius: 14,
    padding: 14,
    marginTop: 6,
    marginBottom: 14,
  },
  scheduleNoticeHeading: {
    fontSize: 13,
    fontWeight: '700',
    marginBottom: 2,
  },
  scheduleNoticeText: {
    fontSize: 12,
    lineHeight: 17,
  },
  schedulePrimaryBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 14,
    paddingVertical: 14,
  },
  schedulePrimaryBtnText: {
    color: '#FFFFFF',
    fontSize: 15,
    fontWeight: '800',
  },
  scheduleSecondaryBtn: {
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 14,
    borderWidth: 1,
    paddingVertical: 12,
    marginTop: 10,
  },
  scheduleSecondaryBtnText: {
    fontSize: 14,
    fontWeight: '700',
  },
  bookingDateHeaderRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 8,
  },
  bookingDateDisplay: {
    fontSize: 16,
    fontWeight: '800',
  },
  dateEditableBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    backgroundColor: '#DCFCE7',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 6,
  },
  dateEditableText: {
    color: '#166534',
    fontSize: 12,
    fontWeight: '700',
  },
  dateLockedBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    backgroundColor: '#FEF3C7',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 6,
  },
  dateLockedText: {
    color: '#92400E',
    fontSize: 12,
    fontWeight: '700',
  },
  dateLockedBanner: {
    borderWidth: 1,
    borderRadius: 12,
    padding: 12,
    marginBottom: 12,
  },
  dateLockedBannerTitle: {
    fontSize: 13,
    fontWeight: '700',
    marginBottom: 2,
  },
  dateLockedBannerSub: {
    fontSize: 12,
    lineHeight: 17,
    marginBottom: 8,
  },
  selectDateBookTourBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#10B981',
    borderRadius: 8,
    paddingVertical: 7,
    paddingHorizontal: 12,
    alignSelf: 'flex-start',
  },
  selectDateBookTourBtnText: {
    color: '#FFFFFF',
    fontSize: 12,
    fontWeight: '700',
  },
  guideTimeCard: {
    borderWidth: 1,
    borderRadius: 12,
    padding: 12,
    marginTop: 12,
    marginBottom: 4,
  },
  guideTimeIconBox: {
    width: 34,
    height: 34,
    borderRadius: 17,
    backgroundColor: 'rgba(16,185,129,0.12)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  guideTimeLabel: {
    fontSize: 11,
    fontWeight: '600',
    textTransform: 'uppercase',
    letterSpacing: 0.5,
  },
  guideTimeValue: {
    fontSize: 14,
    fontWeight: '800',
    marginTop: 1,
  },
  guideTimePill: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: 'rgba(16,185,129,0.12)',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 6,
  },
  guideTimePillText: {
    color: '#10B981',
    fontSize: 11,
    fontWeight: '700',
  },
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
