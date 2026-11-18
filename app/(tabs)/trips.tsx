import { Ionicons } from '@expo/vector-icons';
import { useFocusEffect, useRouter } from 'expo-router';
import React, { useCallback, useMemo, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  FlatList,
  Image,
  Modal,
  RefreshControl,
  ScrollView,
  StatusBar,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import { EmptyState, ScreenTitle } from '../../components/ui';
import { clearSession, restoreSession, SessionUser } from '../../lib/authStore';
import { usePreferences } from '../../lib/preferences';
import { useTheme } from '../../lib/theme';
import {
  ApiBooking,
  cancelBooking,
  cancelFullPackage,
  continuePackageWithoutService,
  getBookings,
  getPackageAlternatives,
  isUnauthorized,
  PaymentMethod,
  payBookingBalance,
  replacePackageService,
  reschedulePackageService,
  resolveImage,
} from '../../services/api';

const QR_EWALLET = require('../../assets/images/payment/qr-ewallet.png');
const QR_INSTAPAY = require('../../assets/images/payment/qr-instapay.png');

// Turn a "HH:MM" (or "HH:MM:SS") value into a friendly 12-hour label.
function formatTime(value: string): string {
  const [hStr, mStr] = value.split(':');
  const h = Number(hStr);
  if (Number.isNaN(h)) return value;
  const period = h >= 12 ? 'PM' : 'AM';
  const h12 = h % 12 === 0 ? 12 : h % 12;
  return `${h12}:${mStr ?? '00'} ${period}`;
}

// Deterministic date formatter: '2026-10-25' -> 'Oct 25, 2026'
function formatDate(dateStr?: string): string {
  if (!dateStr) return '';
  const parts = dateStr.split('-');
  if (parts.length === 3) {
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const year = parts[0];
    const month = months[parseInt(parts[1], 10) - 1] || parts[1];
    const day = parseInt(parts[2], 10);
    return `${month} ${day}, ${year}`;
  }
  return dateStr;
}

const STATUS_COLOR: Record<string, string> = {
  confirmed: '#059669',
  approved: '#059669',
  contacted: '#059669',
  pending: '#D97706',
  accepted: '#2563EB',
  deposit_paid: '#0D9488',
  checked_in: '#0284C7',
  completed: '#059669',
  cancelled: '#EF4444',
  refunded: '#9CA3AF',
  no_show: '#6B7280',
};

// Friendly labels shown to the tourist for each booking status.
const STATUS_LABEL: Record<string, string> = {
  pending: 'Awaiting confirmation',
  accepted: 'Accepted',
  deposit_paid: 'Deposit Paid',
  confirmed: 'Confirmed',
  approved: 'Approved',
  checked_in: 'Checked In',
  contacted: 'Provider Contacted',
  completed: 'Completed',
  cancelled: 'Cancelled',
  refunded: 'Refunded',
  disputed: 'In review',
  no_show: 'No-Show',
};

/**
 * Trip history rule (user requirement):
 * - Show only Completed and Refunded trips in the History section.
 * - Do not display any other statuses in the History section.
 * - Active or upcoming trips continue to appear in the non-history section.
 */
function isTripHistory(b: ApiBooking): boolean {
  const overall = (b.package_overall_status || b.status || '').toLowerCase();
  const isCancelled = overall === 'cancelled' || overall === 'refunded' || b.status === 'cancelled' || b.status === 'refunded';
  if (isCancelled) {
    return true;
  }

  if (b.package_overall_status === 'action_required' || (b.has_cancellation && overall !== 'completed')) {
    return false;
  }

  const isPkg = Boolean(
    b.kind === 'package' ||
    b.is_package ||
    b.category_slug === 'packages' ||
    (typeof b.notes_raw === 'string' && (b.notes_raw.includes('"package_booking"') || b.notes_raw.includes('"type":"package_booking"'))) ||
    (b.package_details && (b.package_details.type === 'package_booking' || b.package_details.package_title)) ||
    (typeof b.listing_title === 'string' && b.listing_title.toLowerCase().includes('package'))
  );

  if (isPkg) {
    return overall === 'completed' || Boolean(b.is_completed);
  }

  if (b.status === 'completed' || b.status === 'refunded' || b.status === 'cancelled' || Boolean(b.is_completed)) {
    return true;
  }
  const isHotel = Boolean(
    b.kind === 'hotel' ||
    b.is_hotel ||
    b.category_slug === 'hotels' ||
    ((b.room_id && b.room_id > 0) && b.category_slug !== 'things-to-do' && b.category_slug !== 'attractions') ||
    (b.room_name && b.category_slug !== 'things-to-do' && b.category_slug !== 'attractions')
  );
  if (isHotel && (b.hotel_status === 'completed' || b.hotel_status === 'refunded' || b.hotel_status === 'cancelled' || b.hotel_status === 'no_show')) {
    return true;
  }
  return false;
}

function isBookingCompleted(b: ApiBooking | null | undefined): boolean {
  if (!b) return false;
  return isTripHistory(b);
}

export type TripUnifiedItem =
  | { id: string; kind: 'hotel'; data: ApiBooking; date: string; isHistory: boolean }
  | { id: string; kind: 'package'; data: ApiBooking; date: string; isHistory: boolean }
  | { id: string; kind: 'activity'; data: ApiBooking; date: string; isHistory: boolean }
  | { id: string; kind: 'attraction'; data: ApiBooking; date: string; isHistory: boolean };

export default function TripsScreen() {
  const router = useRouter();
  const { formatPrice } = usePreferences();
  const insets = useSafeAreaInsets();
  const { colors, isDark, shadow } = useTheme();

  const [bookings, setBookings] = useState<ApiBooking[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [loggedIn, setLoggedIn] = useState(true);
  const [currentUser, setCurrentUser] = useState<SessionUser | null>(null);
  const [error, setError] = useState('');
  const [viewMode, setViewMode] = useState<'active' | 'history'>('active');
  const [categoryFilter, setCategoryFilter] = useState<'all' | 'package' | 'hotel' | 'activity' | 'attraction'>('all');
  const [selectedPackageDetails, setSelectedPackageDetails] = useState<ApiBooking | null>(null);
  const [selectedPackageReceipt, setSelectedPackageReceipt] = useState<ApiBooking | null>(null);

  // Remaining balance payment state
  const [balanceModalVisible, setBalanceModalVisible] = useState(false);
  const [selectedBooking] = useState<ApiBooking | null>(null);
  const [balanceToPay] = useState(0);
  const [payStep, setPayStep] = useState<'method' | 'qr' | 'card'>('method');
  const [payMethod, setPayMethod] = useState<PaymentMethod>('gcash');
  const [payReference, setPayReference] = useState('');
  const [cardName, setCardName] = useState('');
  const [cardNumber, setCardNumber] = useState('');
  const [cardExpiry, setCardExpiry] = useState('');
  const [cardCvv, setCardCvv] = useState('');
  const [isSubmittingPay, setIsSubmittingPay] = useState(false);

  // Booking details & receipt modal state
  const [selectedBookingDetails, setSelectedBookingDetails] = useState<ApiBooking | null>(null);
  const [isSubmittingCancel, setIsSubmittingCancel] = useState(false);

  // Package Alternatives State
  const [pkgAlternativeTab, setPkgAlternativeTab] = useState<'none' | 'replace' | 'reschedule' | 'continue-without' | 'cancel-full'>('none');
  const [pkgAlternativesList, setPkgAlternativesList] = useState<any[]>([]);
  const [loadingAlternatives, setLoadingAlternatives] = useState(false);
  const [pkgRescheduleDate, setPkgRescheduleDate] = useState('');
  const [pkgRescheduleTime, setPkgRescheduleTime] = useState('');
  const [pkgRescheduleNotes, setPkgRescheduleNotes] = useState('');
  const [pkgCancelReason, setPkgCancelReason] = useState('');
  const [isSubmittingPkgOption, setIsSubmittingPkgOption] = useState(false);

  const handleLoadAlternatives = async (pkgRef: string, itemId: number) => {
    setLoadingAlternatives(true);
    try {
      const res = await getPackageAlternatives(pkgRef, itemId);
      setPkgAlternativesList(res.alternatives || []);
    } catch (err: any) {
      Alert.alert('Notice', err?.message || 'Could not load alternatives at this time.');
    } finally {
      setLoadingAlternatives(false);
    }
  };

  const handleSelectReplacement = async (pkgRef: string, itemId: number, alt: any) => {
    Alert.alert(
      'Confirm Replacement',
      `Replace with "${alt.title || alt.room_name}" (${formatPrice(alt.price)})? Only this service will be replaced; your other confirmed bookings remain intact.`,
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Confirm',
          onPress: async () => {
            setIsSubmittingPkgOption(true);
            try {
              const res = await replacePackageService(pkgRef, {
                item_id: itemId,
                provider_id: alt.provider_id,
                service_id: alt.listing_id || alt.vehicle_id || alt.provider_id,
                room_id: alt.room_id || 0,
              });
              Alert.alert('Service Replaced', res.message || 'Service replaced successfully. Your package is updated.');
              setSelectedPackageDetails(null);
              setPkgAlternativeTab('none');
              await load();
            } catch (err: any) {
              Alert.alert('Replacement Failed', err?.message || 'Could not replace service.');
            } finally {
              setIsSubmittingPkgOption(false);
            }
          },
        },
      ]
    );
  };

  const handleReschedule = async (pkgRef: string, itemId: number) => {
    if (!pkgRescheduleDate) {
      Alert.alert('Required', 'Please enter a new date for this service.');
      return;
    }
    setIsSubmittingPkgOption(true);
    try {
      const res = await reschedulePackageService(pkgRef, {
        item_id: itemId,
        new_date: pkgRescheduleDate,
        new_time: pkgRescheduleTime,
        notes: pkgRescheduleNotes,
      });
      Alert.alert('Rescheduled', res.message || 'Reschedule request submitted successfully.');
      setSelectedPackageDetails(null);
      setPkgAlternativeTab('none');
      await load();
    } catch (err: any) {
      Alert.alert('Reschedule Failed', err?.message || 'Could not submit reschedule request.');
    } finally {
      setIsSubmittingPkgOption(false);
    }
  };

  const handleContinueWithout = async (pkgRef: string, itemId: number, refundAmt: number) => {
    Alert.alert(
      'Continue Without Service',
      `Drop this cancelled service? A partial refund of ${formatPrice(refundAmt)} will be credited to your account. Your remaining confirmed services will continue as planned.`,
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Confirm & Continue',
          onPress: async () => {
            setIsSubmittingPkgOption(true);
            try {
              const res = await continuePackageWithoutService(pkgRef, { item_id: itemId });
              Alert.alert('Service Dropped', res.message || 'Service removed. Partial refund initiated.');
              setSelectedPackageDetails(null);
              setPkgAlternativeTab('none');
              await load();
            } catch (err: any) {
              Alert.alert('Action Failed', err?.message || 'Could not proceed without service.');
            } finally {
              setIsSubmittingPkgOption(false);
            }
          },
        },
      ]
    );
  };

  const handleCancelFullPackage = async (pkgRef: string, totalAmt: number) => {
    if (isSubmittingPkgOption) return;
    Alert.alert(
      'Cancel Entire Travel Package',
      `Cancelling this package will also cancel all associated provider bookings (including hotel reservations and vehicle rentals), and a full refund of ${formatPrice(totalAmt)} will be processed according to cancellation policies.\n\nDo you want to proceed?`,
      [
        { text: 'Keep Package', style: 'cancel' },
        {
          text: 'Confirm Cancellation',
          style: 'destructive',
          onPress: async () => {
            setIsSubmittingPkgOption(true);
            try {
              const res = await cancelFullPackage(pkgRef, pkgCancelReason || 'Cancelled by traveler');
              Alert.alert('Package Cancelled', res.message || 'Full travel package cancelled. Associated hotel reservations and vehicle rentals have been cancelled.');
              setSelectedPackageDetails(prev => prev ? {
                ...prev,
                status: 'cancelled',
                package_overall_status: 'cancelled',
                has_cancellation: false,
              } : null);
              setPkgAlternativeTab('none');
              await load();
            } catch (err: any) {
              Alert.alert('Cancellation Failed', err?.message || 'Could not cancel entire package.');
            } finally {
              setIsSubmittingPkgOption(false);
            }
          },
        },
      ]
    );
  };

  const handleCancelBooking = (booking: ApiBooking) => {
    if (!booking) return;

    if (!booking.cancellation?.can_cancel) {
      Alert.alert(
        'Cancellation Unavailable',
        booking.cancellation?.ineligible_reason || 'This booking is no longer eligible for cancellation.'
      );
      return;
    }

    const refundAmount = booking.cancellation?.refund_amount || 0;
    const confirmMessage = refundAmount > 0
      ? `Are you sure you want to cancel your booking for "${booking.listing_title}"?\n\nA full refund of ${formatPrice(refundAmount)} will be automatically initiated to your original payment method (1-3 business days).`
      : `Are you sure you want to cancel your booking for "${booking.listing_title}"? No charges will be incurred.`;

    Alert.alert(
      'Cancel Booking',
      confirmMessage,
      [
        { text: 'Keep Booking', style: 'cancel' },
        {
          text: 'Confirm Cancellation',
          style: 'destructive',
          onPress: async () => {
            setIsSubmittingCancel(true);
            try {
              const res = await cancelBooking(booking.id, 'Cancelled by tourist via mobile app');
              setSelectedBookingDetails(null);
              await load();
              Alert.alert('Booking Cancelled', res.message || 'Your booking has been cancelled successfully.');
            } catch (err: any) {
              if (isUnauthorized(err)) {
                await clearSession();
                setSelectedBookingDetails(null);
                setLoggedIn(false);
                Alert.alert('Session expired', 'Please log in again to manage your bookings.');
              } else {
                Alert.alert('Cancellation Failed', err?.message || 'Could not cancel booking. Please try again.');
              }
            } finally {
              setIsSubmittingCancel(false);
            }
          },
        },
      ]
    );
  };

  const formatCardNumber = (text: string) => {
    const cleaned = text.replace(/\D/g, '').slice(0, 16);
    const parts = cleaned.match(/.{1,4}/g);
    setCardNumber(parts ? parts.join(' ') : cleaned);
  };

  const formatExpiry = (text: string) => {
    const cleaned = text.replace(/\D/g, '').slice(0, 4);
    if (cleaned.length >= 3) {
      setCardExpiry(`${cleaned.slice(0, 2)}/${cleaned.slice(2)}`);
    } else {
      setCardExpiry(cleaned);
    }
  };

  const submitBalancePayment = async (
    method: PaymentMethod,
    reference?: string,
    last4?: string
  ) => {
    if (!selectedBooking) return;
    setIsSubmittingPay(true);
    try {
      await payBookingBalance(selectedBooking.id, {
        payment_method: method,
        payment_reference: reference,
        card_last4: last4,
      });
      setBalanceModalVisible(false);
      await load();
      Alert.alert(
        'Balance Paid Successfully!',
        `Your remaining balance of ${formatPrice(balanceToPay)} has been paid. Your stay at "${selectedBooking.listing_title}" is now fully settled.`
      );
    } catch (e) {
      if (isUnauthorized(e)) {
        await clearSession();
        setBalanceModalVisible(false);
        setLoggedIn(false);
        Alert.alert('Session expired', 'Please log in again to settle your balance.');
      } else {
        Alert.alert('Payment Failed', e instanceof Error ? e.message : 'Unable to complete payment.');
      }
    } finally {
      setIsSubmittingPay(false);
    }
  };

  const confirmQrPayment = () => {
    if (payReference.trim().length < 4) {
      Alert.alert('Reference Required', 'Please enter the transaction reference number from your payment receipt.');
      return;
    }
    submitBalancePayment(payMethod, payReference.trim());
  };

  const confirmCardPayment = () => {
    const digits = cardNumber.replace(/\D/g, '');
    if (cardName.trim().length < 2) {
      Alert.alert('Name Required', 'Please enter the cardholder name.');
      return;
    }
    if (digits.length < 15) {
      Alert.alert('Invalid Card Number', 'Please enter a valid card number.');
      return;
    }
    if (!/^\d{2}\/\d{2}$/.test(cardExpiry)) {
      Alert.alert('Invalid Expiry', 'Enter the card expiry date as MM/YY.');
      return;
    }
    if (cardCvv.length < 3) {
      Alert.alert('Invalid CVV', 'Enter the 3 or 4 digit CVV/CVC.');
      return;
    }
    submitBalancePayment('card', undefined, digits.slice(-4));
  };

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    // restoreSession both returns the cached user AND re-applies the auth token,
    // so authenticated calls work even right after an app reload.
    const session = await restoreSession();
    setCurrentUser(session);
    if (!session) {
      setLoggedIn(false);
      setLoading(false);
      return;
    }
    setLoggedIn(true);
    try {
      const bookingData = await getBookings().catch(() => ({ bookings: [] }));
      setBookings(Array.isArray(bookingData?.bookings) ? bookingData.bookings : []);
    } catch (e) {
      if (isUnauthorized(e)) {
        // Token expired/invalid — clear it and show the friendly sign-in prompt.
        await clearSession();
        setLoggedIn(false);
      } else {
        setError(e instanceof Error ? e.message : 'Failed to load your trips.');
      }
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  const onRefresh = useCallback(() => {
    setRefreshing(true);
    load();
  }, [load]);

  useFocusEffect(
    useCallback(() => {
      load();
      const interval = setInterval(async () => {
        try {
          const bookingData = await getBookings().catch(() => null);
          if (Array.isArray(bookingData?.bookings)) {
            setBookings(bookingData.bookings);
          }
        } catch {}
      }, 5000);
      return () => clearInterval(interval);
    }, [load])
  );

  const allItems: TripUnifiedItem[] = useMemo(() => {
    const list: TripUnifiedItem[] = [];

    for (const b of bookings) {
      // Sub-bookings of a parent travel package should never appear as a separate top-level trip card
      if (typeof b.notes_raw === 'string' && b.notes_raw.includes('"package_sub_booking"')) {
        continue;
      }

      const isHistory = isTripHistory(b);
      const isPkg = Boolean(
        b.kind === 'package' ||
        b.is_package ||
        b.category_slug === 'packages' ||
        (typeof b.notes_raw === 'string' && (b.notes_raw.includes('"package_booking"') || b.notes_raw.includes('"type":"package_booking"'))) ||
        (b.package_details && (b.package_details.type === 'package_booking' || b.package_details.package_title)) ||
        (typeof b.listing_title === 'string' && b.listing_title.toLowerCase().includes('package'))
      );

      const isHotel = !isPkg && Boolean(
        b.kind === 'hotel' ||
        b.is_hotel ||
        b.category_slug === 'hotels' ||
        ((b.room_id && b.room_id > 0) && b.category_slug !== 'things-to-do' && b.category_slug !== 'attractions') ||
        (b.room_name && b.category_slug !== 'things-to-do' && b.category_slug !== 'attractions')
      );

      const isActivity = !isPkg && !isHotel && Boolean(
        b.kind === 'activity' ||
        b.category_slug === 'things-to-do' ||
        b.category_slug === 'things_to_do'
      );

      if (isPkg) {
        list.push({
          id: `package-${b.id}`,
          kind: 'package',
          data: b,
          date: b.booking_date,
          isHistory,
        });
      } else if (isHotel) {
        list.push({
          id: `hotel-${b.id}`,
          kind: 'hotel',
          data: b,
          date: b.check_in_date || b.booking_date,
          isHistory,
        });
      } else if (isActivity) {
        list.push({
          id: `activity-${b.id}`,
          kind: 'activity',
          data: b,
          date: b.booking_date,
          isHistory,
        });
      } else {
        list.push({
          id: `attraction-${b.id}`,
          kind: 'attraction',
          data: b,
          date: b.booking_date,
          isHistory,
        });
      }
    }

    // Sort newest / nearest date first
    return list.sort((a, b) => (b.date || '').localeCompare(a.date || ''));
  }, [bookings]);

  const activeCount = useMemo(() => allItems.filter((i) => !i.isHistory).length, [allItems]);
  const historyCount = useMemo(() => allItems.filter((i) => i.isHistory).length, [allItems]);

  // Current viewMode items (active vs history)
  const currentModeItems = useMemo(
    () => allItems.filter((i) => (viewMode === 'active' ? !i.isHistory : i.isHistory)),
    [allItems, viewMode]
  );

  const packageCount = useMemo(() => currentModeItems.filter((i) => i.kind === 'package').length, [currentModeItems]);
  const hotelCount = useMemo(() => currentModeItems.filter((i) => i.kind === 'hotel').length, [currentModeItems]);
  const activityCount = useMemo(() => currentModeItems.filter((i) => i.kind === 'activity').length, [currentModeItems]);
  const attractionCount = useMemo(() => currentModeItems.filter((i) => i.kind === 'attraction').length, [currentModeItems]);

  const displayedItems = useMemo(() => {
    if (categoryFilter === 'all') return currentModeItems;
    return currentModeItems.filter((i) => i.kind === categoryFilter);
  }, [currentModeItems, categoryFilter]);

  const handleOpenMap = (item: ApiBooking) => {
    if (item.has_valid_location === false) {
      const msg = item.missing_location_message || 'The saved address could not be resolved to a valid map location.';
      Alert.alert('Location Unavailable', msg);
      return;
    }
    router.push({ pathname: '/trip-map', params: { booking: String(item.id) } });
  };

  const renderHotelItem = (item: ApiBooking, isCompleted: boolean) => {
    const effectiveStatus = item.hotel_status || item.status;
    const isCancelled = item.status === 'cancelled' || item.status === 'refunded' || item.hotel_status === 'cancelled' || item.hotel_status === 'refunded';
    const statusColorVal = STATUS_COLOR[effectiveStatus] ?? STATUS_COLOR[item.status] ?? '#9CA3AF';
    const canNavigate = !isCancelled;
    const checkInFormatted = formatDate(item.check_in_date || item.booking_date);
    const checkOutFormatted = item.check_out_date ? formatDate(item.check_out_date) : '';
    const nights = item.nights || 1;
    const deposit = item.deposit_amount ?? 0;
    const isSettled = item.payment_stage === 'settled' || item.hotel_status === 'completed';
    const isCheckedIn = item.hotel_status === 'checked_in';
    const isFullyPaid = isCheckedIn || isSettled || (item.payment_stage === 'paid' && (!item.balance_amount || item.balance_amount === 0)) || (deposit >= item.total_amount && item.total_amount > 0);
    const unpaidBalance = (isSettled || isCheckedIn || isFullyPaid)
      ? 0
      : (typeof item.balance_amount === 'number' && item.balance_amount > 0 ? item.balance_amount : Math.max(0, item.total_amount - deposit));
    const isConfirmed = item.hotel_status === 'confirmed' || item.status === 'confirmed';
    const isDepositPaid = item.hotel_status === 'deposit_paid';
    const isAccepted = item.hotel_status === 'accepted';

    return (
      <TouchableOpacity
        style={[styles.hotelCard, { backgroundColor: colors.card, borderColor: colors.border }, shadow.sm]}
        activeOpacity={0.92}
        onPress={() => setSelectedBookingDetails(item)}
      >
        {/* Header: Hotel Image, Title, Room Badge & Status */}
        <View style={styles.hotelHeaderRow}>
          <Image source={{ uri: resolveImage(item.image) }} style={styles.hotelImage} />
          <View style={styles.hotelHeaderBody}>
            <View style={styles.topRow}>
              <Text style={[styles.cardTitle, { color: colors.text }]} numberOfLines={1}>{item.listing_title}</Text>
              <View style={[styles.statusChip, { backgroundColor: statusColorVal + '22' }]}>
                <View style={[styles.statusDot, { backgroundColor: statusColorVal }]} />
                <Text style={[styles.statusText, { color: statusColorVal }]}>{item.status_label ?? STATUS_LABEL[effectiveStatus] ?? effectiveStatus}</Text>
              </View>
            </View>

            <View style={styles.hotelRoomBadge}>
              <Ionicons name="bed-outline" size={13} color="#2563EB" />
              <Text style={styles.hotelRoomBadgeText} numberOfLines={1}>
                {item.room_name || 'Room Reservation'}
              </Text>
            </View>

            <View style={[styles.metaRow, { marginTop: 4 }]}>
              <Ionicons name="location-outline" size={12} color={colors.textSub} />
              <Text style={[styles.metaText, { color: colors.textSub }]} numberOfLines={1}>{item.area}</Text>
            </View>
          </View>
        </View>

        {/* Completion History Banner if in History */}
        {isCompleted ? (
          <View style={[styles.historyBanner, { backgroundColor: isDark ? '#064E3B22' : '#F0FDF4', borderColor: isDark ? '#05966955' : '#BBF7D0' }]}>
            <Ionicons name="checkmark-circle" size={15} color="#059669" />
            <Text style={[styles.historyBannerText, { color: isDark ? '#34D399' : '#166534' }]}>
              {item.hotel_status === 'completed' || item.status === 'completed' ? 'Completed Hotel Stay \u2022 In History' : `${item.status_label || 'Archived'} \u2022 In History`}
            </Text>
          </View>
        ) : null}

        {/* Prominent Lifecycle Banner for Active Reservations */}
        {!isCompleted && isConfirmed ? (
          <View style={[styles.confirmBanner, { backgroundColor: isDark ? '#064E3B33' : '#ECFDF5', borderColor: isDark ? '#05966966' : '#A7F3D0' }]}>
            <Ionicons name="checkmark-circle" size={18} color="#059669" />
            <View style={{ flex: 1, marginLeft: 8 }}>
              <Text style={[styles.confirmBannerTitle, { color: isDark ? '#34D399' : '#065F46' }]}>{'Reservation Confirmed \u2022 Room Held'}</Text>
              <Text style={[styles.confirmBannerSubtitle, { color: isDark ? '#A7F3D0' : '#047857' }]}>Your room is reserved and guaranteed for your stay.</Text>
            </View>
          </View>
        ) : !isCompleted && isDepositPaid ? (
          <View style={[styles.confirmBanner, { backgroundColor: isDark ? '#0F766E33' : '#F0FDFA', borderColor: isDark ? '#14B8A666' : '#99F6E4' }]}>
            <Ionicons name="shield-checkmark" size={18} color="#0D9488" />
            <View style={{ flex: 1, marginLeft: 8 }}>
              <Text style={[styles.confirmBannerTitle, { color: isDark ? '#2DD4BF' : '#115E59' }]}>{'Deposit Paid \u2022 Verification in Progress'}</Text>
              <Text style={[styles.confirmBannerSubtitle, { color: isDark ? '#99F6E4' : '#0F766E' }]}>The hotel front desk is verifying your payment.</Text>
            </View>
          </View>
        ) : !isCompleted && isAccepted ? (
          <View style={[styles.confirmBanner, { backgroundColor: isDark ? '#1E3A8A33' : '#EFF6FF', borderColor: isDark ? '#3B82F666' : '#BFDBFE' }]}>
            <Ionicons name="thumbs-up" size={18} color="#2563EB" />
            <View style={{ flex: 1, marginLeft: 8 }}>
              <Text style={[styles.confirmBannerTitle, { color: isDark ? '#93C5FD' : '#1E40AF' }]}>Reservation Accepted</Text>
              <Text style={[styles.confirmBannerSubtitle, { color: isDark ? '#BFDBFE' : '#1D4ED8' }]}>Reservation approved by hotel. Awaiting deposit confirmation.</Text>
            </View>
          </View>
        ) : !isCompleted && isCheckedIn ? (
          <View style={[styles.confirmBanner, { backgroundColor: isDark ? '#082F4933' : '#F0F9FF', borderColor: isDark ? '#0284C766' : '#BAE6FD' }]}>
            <Ionicons name="key-outline" size={18} color="#0284C7" />
            <View style={{ flex: 1, marginLeft: 8 }}>
              <Text style={[styles.confirmBannerTitle, { color: isDark ? '#7DD3FC' : '#0369A1' }]}>Currently Checked In</Text>
              <Text style={[styles.confirmBannerSubtitle, { color: isDark ? '#BAE6FD' : '#0284C7' }]}>Enjoy your stay! Check-out will be processed at the front desk.</Text>
            </View>
          </View>
        ) : null}

        {/* Stay Dates & Guest Details */}
        <View style={[styles.hotelStaySection, { borderColor: colors.border, backgroundColor: isDark ? '#1E233044' : '#F8FAFC' }]}>
          <View style={styles.hotelStayRow}>
            <View style={styles.hotelDateCol}>
              <Text style={[styles.hotelStayLabel, { color: colors.textMute }]}>CHECK-IN</Text>
              <Text style={[styles.hotelStayValue, { color: colors.text }]}>{checkInFormatted}</Text>
            </View>
            <Ionicons name="arrow-forward" size={14} color={colors.textMute} style={{ marginHorizontal: 8, marginTop: 12 }} />
            <View style={styles.hotelDateCol}>
              <Text style={[styles.hotelStayLabel, { color: colors.textMute }]}>CHECK-OUT</Text>
              <Text style={[styles.hotelStayValue, { color: colors.text }]}>{checkOutFormatted || checkInFormatted}</Text>
            </View>
          </View>
          <View style={[styles.hotelMetaSubRow, { borderColor: colors.border }]}>
            <View style={styles.metaRow}>
              <Ionicons name="moon-outline" size={13} color={colors.textSub} />
              <Text style={[styles.metaText, { color: colors.textSub }]}>{nights} night{nights > 1 ? 's' : ''}</Text>
            </View>
            <View style={styles.metaRow}>
              <Ionicons name="people-outline" size={13} color={colors.textSub} />
              <Text style={[styles.metaText, { color: colors.textSub }]}>{item.guests} guest{item.guests > 1 ? 's' : ''}</Text>
            </View>
          </View>
        </View>

        {/* Financial Breakdown Grid */}
        <View style={[styles.hotelPricingGrid, { borderColor: colors.border }]}>
          <View style={styles.priceCol}>
            <Text style={[styles.priceColLabel, { color: colors.textMute }]}>Total Stay</Text>
            <Text style={[styles.priceColTotal, { color: colors.text }]}>{formatPrice(item.total_amount)}</Text>
          </View>
          <View style={[styles.priceCol, styles.priceColDivider, { borderColor: colors.border }]}>
            <Text style={[styles.priceColLabel, { color: colors.textMute }]}>Deposit Paid</Text>
            <Text style={[styles.priceColDeposit, { color: isCancelled ? (isDark ? '#60A5FA' : '#2563EB') : '#059669' }]}>
              {isCancelled ? 'Refunded' : (deposit > 0 ? formatPrice(deposit) : formatPrice(0))}
            </Text>
          </View>
          <View style={styles.priceCol}>
            <Text style={[styles.priceColLabel, { color: colors.textMute }]}>Remaining Due</Text>
            <Text style={[styles.priceColBalance, { color: isCancelled ? colors.textMute : ((isSettled || isCheckedIn || isFullyPaid) ? '#059669' : '#D97706') }]}>
              {isCancelled ? '-' : (isSettled ? 'Settled' : (isCheckedIn || isFullyPaid ? 'Paid' : formatPrice(unpaidBalance)))}
            </Text>
          </View>
        </View>

        {/* Front Desk Instruction Box or Cancellation Box */}
        {isCancelled ? (
          <View style={[styles.frontDeskNotice, { backgroundColor: isDark ? '#1E2330' : '#F8FAFC', borderColor: colors.border }]}>
            <Ionicons name="close-circle-outline" size={16} color="#EF4444" style={{ marginTop: 1 }} />
            <View style={{ flex: 1, marginLeft: 8 }}>
              <Text style={[styles.frontDeskNoticeTitle, { color: isDark ? '#F87171' : '#B91C1C' }]}>
                Booking was canceled.
              </Text>
              <Text style={[styles.frontDeskNoticeText, { color: colors.textSub }]}>
                {item.cancellation?.refund_amount && item.cancellation.refund_amount > 0
                  ? `A full refund of ${formatPrice(item.cancellation.refund_amount)} was initiated to your original payment method.`
                  : 'This reservation has been canceled. No balance is due.'}
              </Text>
            </View>
          </View>
        ) : isCheckedIn ? (
          <View style={[styles.frontDeskNotice, { backgroundColor: isDark ? '#064E3B22' : '#F0FDF4', borderColor: isDark ? '#10B98144' : '#BBF7D0' }]}>
            <Ionicons name="checkmark-done-circle" size={16} color="#10B981" style={{ marginTop: 1 }} />
            <Text style={[styles.frontDeskNoticeText, { color: isDark ? '#6EE7B7' : '#166534', marginLeft: 8, flex: 1 }]}>
              Remaining balance paid at check-in. No further payment required.
            </Text>
          </View>
        ) : isSettled ? (
          <View style={[styles.frontDeskNotice, { backgroundColor: isDark ? '#064E3B22' : '#F0FDF4', borderColor: isDark ? '#10B98144' : '#BBF7D0' }]}>
            <Ionicons name="checkmark-done-circle" size={16} color="#10B981" style={{ marginTop: 1 }} />
            <Text style={[styles.frontDeskNoticeText, { color: isDark ? '#6EE7B7' : '#166534', marginLeft: 8, flex: 1 }]}>
              Your stay is fully settled. No remaining balance due at the front desk.
            </Text>
          </View>
        ) : isFullyPaid ? (
          <View style={[styles.frontDeskNotice, { backgroundColor: isDark ? '#064E3B22' : '#F0FDF4', borderColor: isDark ? '#10B98144' : '#BBF7D0' }]}>
            <Ionicons name="checkmark-done-circle" size={16} color="#10B981" style={{ marginTop: 1 }} />
            <Text style={[styles.frontDeskNoticeText, { color: isDark ? '#6EE7B7' : '#166534', marginLeft: 8, flex: 1 }]}>
              Your stay is fully paid. No remaining balance due at the front desk.
            </Text>
          </View>
        ) : (
          <View style={[styles.frontDeskNotice, { backgroundColor: isDark ? '#78350F22' : '#FFFBEB', borderColor: isDark ? '#F59E0B44' : '#FDE68A' }]}>
            <Ionicons name="wallet-outline" size={16} color="#D97706" style={{ marginTop: 1 }} />
            <View style={{ flex: 1, marginLeft: 8 }}>
              <Text style={[styles.frontDeskNoticeTitle, { color: isDark ? '#FBBF24' : '#B45309' }]}>
                Pay remaining balance at front desk
              </Text>
              <Text style={[styles.frontDeskNoticeText, { color: colors.textSub }]}>
                Please pay the remaining amount of {formatPrice(unpaidBalance)} at the hotel front desk upon arrival / check-in.
              </Text>
            </View>
          </View>
        )}

        {/* Action buttons */}
        <View style={styles.hotelActionsRow}>
          <TouchableOpacity
            style={[styles.actionBtn, { borderColor: colors.border, backgroundColor: isDark ? '#1E2330' : '#F8FAFC' }]}
            activeOpacity={0.85}
            onPress={() => setSelectedBookingDetails(item)}
          >
            <Ionicons name="receipt-outline" size={14} color={colors.text} />
            <Text style={[styles.actionText, { color: colors.text }]}>Details &amp; Receipt</Text>
          </TouchableOpacity>
          {canNavigate ? (
            <TouchableOpacity
              style={[styles.navBtn, { backgroundColor: colors.primary }]}
              activeOpacity={0.85}
              onPress={() => handleOpenMap(item)}
            >
              <Ionicons name="navigate" size={14} color="#FFFFFF" />
              <Text style={styles.navBtnText}>Directions &amp; Map</Text>
            </TouchableOpacity>
          ) : null}
          {item.can_review ? (
            <TouchableOpacity
              style={[
                styles.actionBtn,
                {
                  borderColor: isDark ? '#EAB308' : '#D97706',
                  backgroundColor: isDark ? '#713F1222' : '#FEFCE8',
                },
              ]}
              activeOpacity={0.85}
              onPress={() => router.push({ pathname: '/review/[id]', params: { id: String(item.listing_id), title: item.listing_title } })}
            >
              <Ionicons name="star-outline" size={14} color={isDark ? '#FACC15' : '#D97706'} />
              <Text style={[styles.actionText, { color: isDark ? '#FACC15' : '#D97706', fontWeight: '700' }]}>Rate stay</Text>
            </TouchableOpacity>
          ) : null}
          {item.dispute_status || item.dispute_id ? (
            <TouchableOpacity
              style={[
                styles.actionBtn,
                {
                  borderColor: isDark ? '#475569' : '#94A3B8',
                  backgroundColor: isDark ? '#33415525' : '#F1F5F9',
                },
              ]}
              activeOpacity={0.85}
              onPress={() => router.push({ pathname: '/report/[id]', params: { id: String(item.id), title: item.listing_title, is_hotel: '1' } })}
            >
              <Ionicons name="document-text" size={14} color={isDark ? '#94A3B8' : '#475569'} />
              <Text style={[styles.actionText, { color: isDark ? '#94A3B8' : '#475569', fontWeight: '700' }]}>View Report</Text>
            </TouchableOpacity>
          ) : item.can_report ? (
            <TouchableOpacity
              style={[styles.actionBtn, { borderColor: '#EF4444' }]}
              activeOpacity={0.85}
              onPress={() => router.push({ pathname: '/report/[id]', params: { id: String(item.id), title: item.listing_title, is_hotel: '1' } })}
            >
              <Ionicons name="flag-outline" size={14} color="#EF4444" />
              <Text style={[styles.actionText, { color: '#EF4444' }]}>Report issue</Text>
            </TouchableOpacity>
          ) : null}
        </View>
      </TouchableOpacity>
    );
  };

  const renderAttractionItem = (item: ApiBooking, isCompleted: boolean) => {
    const statusColorVal = STATUS_COLOR[item.status] ?? '#9CA3AF';
    const canNavigate = item.status !== 'cancelled' && item.status !== 'refunded';

    return (
      <TouchableOpacity
        style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }, shadow.sm]}
        activeOpacity={0.92}
        onPress={() => setSelectedBookingDetails(item)}
      >
        {/* Header row: Attraction Image on left, Title, Status & Schedule on right */}
        <View style={styles.cardHeaderRow}>
          <Image source={{ uri: resolveImage(item.image) }} style={styles.cardImage} />
          <View style={styles.cardBody}>
            <View style={styles.topRow}>
              <Text style={[styles.cardTitle, { color: colors.text }]} numberOfLines={1}>{item.listing_title}</Text>
              <View style={[styles.statusChip, { backgroundColor: statusColorVal + '22' }]}>
                <View style={[styles.statusDot, { backgroundColor: statusColorVal }]} />
                <Text style={[styles.statusText, { color: statusColorVal }]}>{item.status_label ?? STATUS_LABEL[item.status] ?? item.status}</Text>
              </View>
            </View>

            <View style={[styles.metaRow, { marginTop: 4 }]}>
              <Ionicons name="location-outline" size={12} color={colors.textSub} />
              <Text style={[styles.metaText, { color: colors.textSub }]} numberOfLines={1}>{item.area || 'Cebu'}</Text>
            </View>

            <View style={styles.metaRow}>
              <Ionicons name="calendar-outline" size={13} color={colors.textSub} />
              <Text style={[styles.metaText, { color: colors.textSub }]}>
                {item.booking_dates && item.booking_dates.length > 1
                  ? `${item.booking_dates.length} days: ${item.booking_dates.join(', ')}`
                  : item.booking_date}
              </Text>
            </View>
            {item.booking_time ? (
              <View style={styles.metaRow}>
                <Ionicons name="time-outline" size={13} color={colors.textSub} />
                <Text style={[styles.metaText, { color: colors.textSub }]}>
                  {formatTime(item.booking_time)}
                  {item.booking_end_time ? ` - ${formatTime(item.booking_end_time)}` : ''}
                </Text>
              </View>
            ) : null}
            <View style={styles.metaRow}>
              <Ionicons name="people-outline" size={13} color={colors.textSub} />
              <Text style={[styles.metaText, { color: colors.textSub }]}>{item.guests} visitor{item.guests > 1 ? 's' : ''}</Text>
            </View>
          </View>
        </View>

        {/* Completion or Refund History Banner if in History (spans full card width) */}
        {isCompleted ? (
          <View style={[styles.historyBanner, { backgroundColor: item.status === 'refunded' ? (isDark ? '#1E3A8A22' : '#EFF6FF') : (isDark ? '#064E3B22' : '#F0FDF4'), borderColor: item.status === 'refunded' ? (isDark ? '#3B82F655' : '#BFDBFE') : (isDark ? '#05966955' : '#BBF7D0'), marginTop: 10 }]}>
            <Ionicons name={item.status === 'refunded' ? "refresh-circle" : "checkmark-circle"} size={14} color={item.status === 'refunded' ? "#2563EB" : "#059669"} />
            <Text style={[styles.historyBannerText, { color: item.status === 'refunded' ? (isDark ? '#93C5FD' : '#1D4ED8') : (isDark ? '#34D399' : '#166534') }]}>
              {item.status === 'refunded' ? 'Refunded Attraction \u2022 In History' : 'Completed Attraction \u2022 In History'}
            </Text>
          </View>
        ) : null}

        {/* Price on left, Navigate button on right (spans full card width with clean padding) */}
        <View style={styles.attractionPriceRow}>
          <Text style={[styles.attractionPrice, { color: colors.accent }]}>{formatPrice(item.total_amount)}</Text>
          {canNavigate ? (
            <TouchableOpacity
              style={[styles.attractionNavBtn, { backgroundColor: colors.primary }]}
              activeOpacity={0.85}
              onPress={() => handleOpenMap(item)}
            >
              <Ionicons name="navigate" size={14} color="#FFFFFF" />
              <Text style={styles.attractionNavBtnText}>Directions &amp; Map</Text>
            </TouchableOpacity>
          ) : null}
        </View>

        {/* Stacked Full-Width Action Buttons matching mockup */}
        <View style={styles.attractionActionsCol}>
          <TouchableOpacity
            style={[styles.attractionActionBtn, { borderColor: colors.border, backgroundColor: isDark ? '#1E2330' : '#F8FAFC' }]}
            activeOpacity={0.85}
            onPress={() => setSelectedBookingDetails(item)}
          >
            <Ionicons name="receipt-outline" size={15} color={colors.text} />
            <Text style={[styles.attractionActionText, { color: colors.text }]}>Details &amp; Receipt</Text>
          </TouchableOpacity>

          {item.can_review ? (
            <TouchableOpacity
              style={[
                styles.attractionActionBtn,
                {
                  borderColor: isDark ? '#EAB308' : '#D97706',
                  backgroundColor: isDark ? '#713F1222' : '#FEFCE8',
                },
              ]}
              activeOpacity={0.85}
              onPress={() => router.push({ pathname: '/review/[id]', params: { id: String(item.listing_id), title: item.listing_title } })}
            >
              <Ionicons name="star-outline" size={15} color={isDark ? '#FACC15' : '#D97706'} />
              <Text style={[styles.attractionActionText, { color: isDark ? '#FACC15' : '#D97706', fontWeight: '700' }]}>Rate &amp; Review Experience</Text>
            </TouchableOpacity>
          ) : null}

          {item.dispute_status || item.dispute_id ? (
            <TouchableOpacity
              style={[
                styles.attractionActionBtn,
                {
                  borderColor: isDark ? '#475569' : '#94A3B8',
                  backgroundColor: isDark ? '#33415525' : '#F1F5F9',
                },
              ]}
              activeOpacity={0.85}
              onPress={() => router.push({ pathname: '/report/[id]', params: { id: String(item.id), title: item.listing_title } })}
            >
              <Ionicons name="document-text" size={15} color={isDark ? '#94A3B8' : '#475569'} />
              <Text style={[styles.attractionActionText, { color: isDark ? '#94A3B8' : '#475569', fontWeight: '700' }]}>View Report</Text>
            </TouchableOpacity>
          ) : item.can_report ? (
            <TouchableOpacity
              style={[styles.attractionActionBtn, { borderColor: '#EF4444', backgroundColor: 'transparent' }]}
              activeOpacity={0.85}
              onPress={() => router.push({ pathname: '/report/[id]', params: { id: String(item.id), title: item.listing_title } })}
            >
              <Ionicons name="flag-outline" size={15} color="#EF4444" />
              <Text style={[styles.attractionActionText, { color: '#EF4444' }]}>Report Issue</Text>
            </TouchableOpacity>
          ) : null}
        </View>
      </TouchableOpacity>
    );
  };

  const renderActivityItem = (item: ApiBooking, isCompleted: boolean) => {
    const statusColorVal = STATUS_COLOR[item.status] ?? '#9CA3AF';
    const canNavigate = item.status !== 'cancelled' && item.status !== 'refunded';

    return (
      <TouchableOpacity
        style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }, shadow.sm]}
        activeOpacity={0.92}
        onPress={() => setSelectedBookingDetails(item)}
      >
        {/* Header row: Activity Image on left, Title, Status & Schedule on right */}
        <View style={styles.cardHeaderRow}>
          <Image source={{ uri: resolveImage(item.image) }} style={styles.cardImage} />
          <View style={styles.cardBody}>
            <View style={styles.topRow}>
              <Text style={[styles.cardTitle, { color: colors.text }]} numberOfLines={1}>{item.listing_title}</Text>
              <View style={[styles.statusChip, { backgroundColor: statusColorVal + '22' }]}>
                <View style={[styles.statusDot, { backgroundColor: statusColorVal }]} />
                <Text style={[styles.statusText, { color: statusColorVal }]}>{item.status_label ?? STATUS_LABEL[item.status] ?? item.status}</Text>
              </View>
            </View>

            {item.guide_name ? (
              <View style={[styles.metaRow, { marginTop: 4 }]}>
                <Ionicons name="person-outline" size={12} color={colors.textSub} />
                <Text style={[styles.metaText, { color: colors.textSub }]} numberOfLines={1}>{item.guide_name}</Text>
              </View>
            ) : null}

            <View style={[styles.metaRow, { marginTop: item.guide_name ? 4 : 4 }]}>
              <Ionicons name="location-outline" size={12} color={colors.textSub} />
              <Text style={[styles.metaText, { color: colors.textSub }]} numberOfLines={1}>{item.area || 'Cebu'}</Text>
            </View>

            <View style={styles.metaRow}>
              <Ionicons name="calendar-outline" size={13} color={colors.textSub} />
              <Text style={[styles.metaText, { color: colors.textSub }]}>
                {item.booking_dates && item.booking_dates.length > 1
                  ? `${item.booking_dates.length} days: ${item.booking_dates.join(', ')}`
                  : item.booking_date}
              </Text>
            </View>
            {item.booking_time ? (
              <View style={styles.metaRow}>
                <Ionicons name="time-outline" size={13} color={colors.textSub} />
                <Text style={[styles.metaText, { color: colors.textSub }]}>
                  {formatTime(item.booking_time)}
                  {item.booking_end_time ? ` - ${formatTime(item.booking_end_time)}` : ''}
                </Text>
              </View>
            ) : null}
            <View style={styles.metaRow}>
              <Ionicons name="people-outline" size={13} color={colors.textSub} />
              <Text style={[styles.metaText, { color: colors.textSub }]}>{item.guests} participant{item.guests > 1 ? 's' : ''}</Text>
            </View>
          </View>
        </View>

        {/* Completion or Refund History Banner if in History */}
        {isCompleted ? (
          <View style={[styles.historyBanner, { backgroundColor: item.status === 'refunded' ? (isDark ? '#1E3A8A22' : '#EFF6FF') : (isDark ? '#064E3B22' : '#F0FDF4'), borderColor: item.status === 'refunded' ? (isDark ? '#3B82F655' : '#BFDBFE') : (isDark ? '#05966955' : '#BBF7D0'), marginTop: 10 }]}>
            <Ionicons name={item.status === 'refunded' ? "refresh-circle" : "checkmark-circle"} size={14} color={item.status === 'refunded' ? "#2563EB" : "#059669"} />
            <Text style={[styles.historyBannerText, { color: item.status === 'refunded' ? (isDark ? '#93C5FD' : '#1D4ED8') : (isDark ? '#34D399' : '#166534') }]}>
              {item.status === 'refunded' ? 'Refunded Activity \u2022 In History' : 'Completed Activity \u2022 In History'}
            </Text>
          </View>
        ) : null}

        {/* Price on left, Directions & Map button on right */}
        <View style={styles.attractionPriceRow}>
          <Text style={[styles.attractionPrice, { color: colors.accent }]}>{formatPrice(item.total_amount)}</Text>
          {canNavigate ? (
            <TouchableOpacity
              style={[styles.attractionNavBtn, { backgroundColor: colors.primary }]}
              activeOpacity={0.85}
              onPress={() => handleOpenMap(item)}
            >
              <Ionicons name="navigate" size={14} color="#FFFFFF" />
              <Text style={styles.attractionNavBtnText}>Directions &amp; Map</Text>
            </TouchableOpacity>
          ) : null}
        </View>

        {/* Stacked Action Buttons */}
        <View style={styles.attractionActionsCol}>
          <TouchableOpacity
            style={[styles.attractionActionBtn, { borderColor: colors.border, backgroundColor: isDark ? '#1E2330' : '#F8FAFC' }]}
            activeOpacity={0.85}
            onPress={() => setSelectedBookingDetails(item)}
          >
            <Ionicons name="receipt-outline" size={15} color={colors.text} />
            <Text style={[styles.attractionActionText, { color: colors.text }]}>Details &amp; Receipt</Text>
          </TouchableOpacity>

          {item.can_review ? (
            <TouchableOpacity
              style={[
                styles.attractionActionBtn,
                {
                  borderColor: isDark ? '#EAB308' : '#D97706',
                  backgroundColor: isDark ? '#713F1222' : '#FEFCE8',
                },
              ]}
              activeOpacity={0.85}
              onPress={() => router.push({ pathname: '/review/[id]', params: { id: String(item.listing_id), title: item.listing_title } })}
            >
              <Ionicons name="star-outline" size={15} color={isDark ? '#FACC15' : '#D97706'} />
              <Text style={[styles.attractionActionText, { color: isDark ? '#FACC15' : '#D97706', fontWeight: '700' }]}>Rate your guide</Text>
            </TouchableOpacity>
          ) : null}

          {item.dispute_status || item.dispute_id ? (
            <TouchableOpacity
              style={[
                styles.attractionActionBtn,
                {
                  borderColor: isDark ? '#475569' : '#94A3B8',
                  backgroundColor: isDark ? '#33415525' : '#F1F5F9',
                },
              ]}
              activeOpacity={0.85}
              onPress={() => router.push({ pathname: '/report/[id]', params: { id: String(item.id), title: item.listing_title } })}
            >
              <Ionicons name="document-text" size={15} color={isDark ? '#94A3B8' : '#475569'} />
              <Text style={[styles.attractionActionText, { color: isDark ? '#94A3B8' : '#475569', fontWeight: '700' }]}>View Report</Text>
            </TouchableOpacity>
          ) : item.can_report ? (
            <TouchableOpacity
              style={[styles.attractionActionBtn, { borderColor: '#EF4444', backgroundColor: 'transparent' }]}
              activeOpacity={0.85}
              onPress={() => router.push({ pathname: '/report/[id]', params: { id: String(item.id), title: item.listing_title } })}
            >
              <Ionicons name="flag-outline" size={15} color="#EF4444" />
              <Text style={[styles.attractionActionText, { color: '#EF4444' }]}>Report Issue</Text>
            </TouchableOpacity>
          ) : null}
        </View>
      </TouchableOpacity>
    );
  };

  const renderPackageItem = (item: ApiBooking, isCompleted: boolean) => {
    const overallStatus = (item.package_overall_status || item.status || 'pending').toLowerCase();
    const isCancelled = overallStatus === 'cancelled' || overallStatus === 'refunded' || item.status === 'cancelled' || item.status === 'refunded';
    const isPkgCompleted = !isCancelled && (overallStatus === 'completed' || isCompleted || Boolean(item.is_completed));
    const isActionRequired = !isCancelled && !isPkgCompleted && (overallStatus === 'action_required' || Boolean(item.has_cancellation));
    const statusColorVal =
      isCancelled ? '#DC2626' :
      isActionRequired ? '#D97706' :
      isPkgCompleted ? '#059669' :
      overallStatus === 'confirmed' ? '#059669' :
      overallStatus === 'partially_confirmed' ? '#2563EB' :
      '#D97706';
    const statusLabelText =
      isCancelled ? (overallStatus === 'refunded' || item.status === 'refunded' ? 'Refunded' : 'Cancelled') :
      isActionRequired ? 'Action Required' :
      item.status_label || (
        isPkgCompleted ? 'Completed' :
        overallStatus === 'confirmed' ? 'Confirmed' :
        overallStatus === 'partially_confirmed' ? 'Awaiting Providers Confirmation' :
        'Pending Confirmations'
      );
    const pkgRefText = item.package_booking_ref || `BK-${item.id}`;
    const canNavigate = item.status !== 'cancelled' && item.status !== 'refunded';
    const details = item.package_details;
    const guideName = item.guide_name || details?.guide?.name || 'Marc Repuesto';
    const guideAvatar = item.guide_avatar || details?.guide?.avatar || '';

    return (
      <TouchableOpacity
        style={[styles.pkgCard, { backgroundColor: colors.card, borderColor: colors.border }, shadow.sm]}
        activeOpacity={0.92}
        onPress={() => setSelectedPackageDetails(item)}
      >
        {/* Header Row: Cover, Title, Badge, Status Chip */}
        <View style={styles.pkgHeaderRow}>
          <Image source={{ uri: resolveImage(item.image) }} style={styles.pkgImage} />
          <View style={{ flex: 1, justifyContent: 'center' }}>
            <View style={styles.topRow}>
              <Text style={[styles.cardTitle, { color: colors.text }]} numberOfLines={1}>
                {item.listing_title}
              </Text>
              <View style={[styles.statusChip, { backgroundColor: statusColorVal + '22' }]}>
                <View style={[styles.statusDot, { backgroundColor: statusColorVal }]} />
                <Text style={[styles.statusText, { color: statusColorVal }]}>
                  {statusLabelText}
                </Text>
              </View>
            </View>

            <View style={[styles.pkgCategoryPill, { backgroundColor: colors.card }]}>
              <Ionicons name="cube" size={11} color={colors.primary} />
              <Text style={[styles.pkgCategoryText, { color: colors.primary }]}>TRAVEL PACKAGE</Text>
            </View>

            <View style={[styles.metaRow, { marginTop: 4 }]}>
              <Ionicons name="location-outline" size={12} color={colors.textSub} />
              <Text style={[styles.metaText, { color: colors.textSub }]} numberOfLines={1}>
                {item.area || 'Moalboal & Badian, South Cebu'}
              </Text>
            </View>
          </View>
        </View>

        {/* Guide Host & Booking Ref Row */}
        <View style={[styles.pkgHostRow, { borderColor: colors.border }]}>
          {guideAvatar ? (
            <Image source={{ uri: resolveImage(guideAvatar) }} style={styles.pkgAvatar} />
          ) : (
            <View style={[styles.pkgAvatar, { alignItems: 'center', justifyContent: 'center', backgroundColor: colors.card }]}>
              <Ionicons name="person" size={15} color={colors.primary} />
            </View>
          )}
          <View style={{ flex: 1, marginLeft: 8 }}>
            <Text style={[styles.pkgHostName, { color: colors.text }]}>{guideName}</Text>
            <Text style={{ fontSize: 10, color: colors.textSub }}>Licensed Tour Guide Host</Text>
          </View>
          <View style={[styles.pkgRefPill, { backgroundColor: isDark ? '#1E293B' : '#F1F5F9' }]}>
            <Text style={[styles.pkgRefText, { color: colors.textSub }]}>{pkgRefText}</Text>
          </View>
        </View>

        {/* Warning Banner if Action Required */}
        {isActionRequired ? (
          <View style={{ marginHorizontal: 12, marginTop: 10, padding: 8, borderRadius: 8, backgroundColor: isDark ? 'rgba(217, 119, 6, 0.2)' : '#FEF3C7', borderWidth: 1, borderColor: '#F59E0B', flexDirection: 'row', alignItems: 'center', gap: 6 }}>
            <Ionicons name="warning" size={16} color="#D97706" />
            <Text style={{ fontSize: 11, fontWeight: '700', color: isDark ? '#FBBF24' : '#92400E', flex: 1 }}>
              Service provider cancelled. Tap to review options.
            </Text>
          </View>
        ) : null}

        {/* Provider Services Status Breakdown */}
        {(() => {
          const guideItem = item.provider_statuses?.find((p: any) => p.service_type === 'guide');
          const guideSt = guideItem?.status || (overallStatus === 'completed' ? 'completed' : (overallStatus === 'confirmed' && (!item.provider_statuses || item.provider_statuses.length === 0) ? 'confirmed' : 'pending'));

          const hotelItem = item.provider_statuses?.find((p: any) => p.service_type === 'hotel');
          const hasHotel = Boolean(hotelItem || details?.hotel_included || details?.accommodation);
          const hotelSt = hotelItem?.status || (hasHotel && overallStatus === 'completed' ? 'completed' : 'pending');

          const vehicleItem = item.provider_statuses?.find((p: any) => p.service_type === 'vehicle');
          const hasVehicle = Boolean(vehicleItem || details?.rental_included || details?.rental);
          const vehicleSt = vehicleItem?.status || (hasVehicle && overallStatus === 'completed' ? 'completed' : 'pending');

          const getStatusBadge = (st: string) => {
            if (st === 'completed') return { text: 'Completed', color: '#10B981', bg: isDark ? 'rgba(16, 185, 129, 0.2)' : '#DCFCE7' };
            if (st === 'confirmed' || st === 'approved' || st === 'contacted' || st === 'checked_in') return { text: 'Confirmed', color: '#059669', bg: isDark ? 'rgba(5, 150, 105, 0.2)' : '#ECFDF5' };
            if (st === 'rescheduled') return { text: 'Rescheduled', color: '#2563EB', bg: isDark ? 'rgba(37, 99, 235, 0.2)' : '#EFF6FF' };
            if (st === 'removed') return { text: 'Removed', color: '#64748B', bg: isDark ? 'rgba(100, 116, 139, 0.2)' : '#F1F5F9' };
            if (st === 'cancelled') return { text: 'Cancelled', color: '#DC2626', bg: isDark ? 'rgba(220, 38, 38, 0.2)' : '#FEF2F2' };
            if (st === 'rejected' || st === 'declined') return { text: 'Declined', color: '#DC2626', bg: isDark ? 'rgba(220, 38, 38, 0.2)' : '#FEF2F2' };
            return { text: 'Pending', color: '#D97706', bg: isDark ? 'rgba(217, 119, 6, 0.2)' : '#FEF3C7' };
          };

          const guideBadge = getStatusBadge(guideSt);
          const hotelBadge = getStatusBadge(hotelSt);
          const vehicleBadge = getStatusBadge(vehicleSt);

          return (
            <View style={{ marginHorizontal: 12, marginTop: 10, padding: 10, borderRadius: 10, backgroundColor: isDark ? '#1E2330' : '#F1F5F9' }}>
              <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginBottom: 6 }}>
                <Text style={{ fontSize: 11, fontWeight: '800', color: colors.textSub, textTransform: 'uppercase', letterSpacing: 0.5 }}>
                  Provider Services Status
                </Text>
                <Text style={{ fontSize: 10, fontWeight: '700', color: overallStatus === 'completed' ? '#10B981' : '#059669' }}>
                  {overallStatus === 'completed' ? 'All Services Done' : 'Live Sync'}
                </Text>
              </View>
              <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 6 }}>
                {/* Guide Provider */}
                <View style={{ flexDirection: 'row', alignItems: 'center', backgroundColor: guideBadge.bg, paddingHorizontal: 8, paddingVertical: 4, borderRadius: 6, gap: 4 }}>
                  <Text style={{ fontSize: 11 }}>🧑‍💼</Text>
                  <Text style={{ fontSize: 11, fontWeight: '600', color: colors.text }}>Guide:</Text>
                  <Text style={{ fontSize: 11, fontWeight: '800', color: guideBadge.color }}>{guideBadge.text}</Text>
                </View>

                {/* Hotel Provider (only rendered if included) */}
                {hasHotel ? (
                  <View style={{ flexDirection: 'row', alignItems: 'center', backgroundColor: hotelBadge.bg, paddingHorizontal: 8, paddingVertical: 4, borderRadius: 6, gap: 4 }}>
                    <Text style={{ fontSize: 11 }}>🏨</Text>
                    <Text style={{ fontSize: 11, fontWeight: '600', color: colors.text }}>Hotel:</Text>
                    <Text style={{ fontSize: 11, fontWeight: '800', color: hotelBadge.color }}>{hotelBadge.text}</Text>
                  </View>
                ) : null}

                {/* Vehicle Provider (only rendered if included) */}
                {hasVehicle ? (
                  <View style={{ flexDirection: 'row', alignItems: 'center', backgroundColor: vehicleBadge.bg, paddingHorizontal: 8, paddingVertical: 4, borderRadius: 6, gap: 4 }}>
                    <Text style={{ fontSize: 11 }}>🛵</Text>
                    <Text style={{ fontSize: 11, fontWeight: '600', color: colors.text }}>Vehicle:</Text>
                    <Text style={{ fontSize: 11, fontWeight: '800', color: vehicleBadge.color }}>{vehicleBadge.text}</Text>
                  </View>
                ) : null}
              </View>
            </View>
          );
        })()}

        {/* Dates & Pax Grid */}
        <View style={[styles.pkgDatesGrid, { borderColor: colors.border, backgroundColor: isDark ? '#1E233044' : '#F8FAFC' }]}>
          <View style={{ flex: 1 }}>
            <Text style={[styles.hotelStayLabel, { color: colors.textMute }]}>TRAVEL DATE</Text>
            <Text style={[styles.hotelStayValue, { color: colors.text }]}>{formatDate(item.booking_date)}</Text>
          </View>
          <View style={{ width: 1, height: 26, backgroundColor: colors.border }} />
          <View style={{ flex: 1 }}>
            <Text style={[styles.hotelStayLabel, { color: colors.textMute }]}>PARTICIPANTS</Text>
            <Text style={[styles.hotelStayValue, { color: colors.text }]}>
              {item.guests} guest{item.guests > 1 ? 's' : ''}
            </Text>
          </View>
        </View>

        {/* Pricing Row: Label Total Package Price, sub-label Package Price (never per person) */}
        <View style={styles.pkgPriceRow}>
          <View>
            <Text style={[styles.pkgPriceLabel, { color: colors.textMute }]}>Total Package Price</Text>
            <View style={{ flexDirection: 'row', alignItems: 'baseline' }}>
              <Text style={[styles.pkgPriceValue, { color: colors.text }]}>{formatPrice(item.total_amount)}</Text>
              <Text style={[styles.pkgPriceUnit, { color: colors.textSub }]}>Package Price</Text>
            </View>
          </View>
          <View style={{ backgroundColor: colors.card, paddingHorizontal: 10, paddingVertical: 4, borderRadius: 8, borderWidth: 1, borderColor: colors.border }}>
            <Text style={{ fontSize: 11, fontWeight: '800', color: colors.primary }}>ALL-IN-ONE BUNDLE</Text>
          </View>
        </View>

        {/* Action Buttons: Details, Receipt, Directions & Map */}
        <View style={styles.pkgActionsCol}>
          <View style={styles.pkgActionRow}>
            <TouchableOpacity
              style={[styles.attractionActionBtn, { flex: 1, borderColor: colors.border, backgroundColor: isDark ? '#1E2330' : '#F8FAFC', paddingVertical: 9 }]}
              activeOpacity={0.85}
              onPress={() => setSelectedPackageDetails(item)}
            >
              <Ionicons name="list-outline" size={15} color={colors.text} />
              <Text style={[styles.attractionActionText, { color: colors.text, fontSize: 12 }]}>Details</Text>
            </TouchableOpacity>

            <TouchableOpacity
              style={[styles.attractionActionBtn, { flex: 1, borderColor: colors.border, backgroundColor: isDark ? '#1E2330' : '#F8FAFC', paddingVertical: 9 }]}
              activeOpacity={0.85}
              onPress={() => setSelectedPackageReceipt(item)}
            >
              <Ionicons name="receipt-outline" size={15} color={colors.text} />
              <Text style={[styles.attractionActionText, { color: colors.text, fontSize: 12 }]}>Receipt</Text>
            </TouchableOpacity>
          </View>

          {canNavigate ? (
            <TouchableOpacity
              style={[styles.attractionActionBtn, { borderColor: colors.primary, backgroundColor: colors.primary, paddingVertical: 10 }]}
              activeOpacity={0.85}
              onPress={() => handleOpenMap(item)}
            >
              <Ionicons name="navigate" size={15} color="#FFFFFF" />
              <Text style={[styles.attractionActionText, { color: '#FFFFFF', fontSize: 13 }]}>Directions &amp; Map</Text>
            </TouchableOpacity>
          ) : null}

          {!isCancelled && isPkgCompleted ? (
            <View style={styles.pkgActionRow}>
              {item.can_review || overallStatus === 'completed' ? (
                <TouchableOpacity
                  style={[
                    styles.attractionActionBtn,
                    {
                      flex: 1,
                      borderColor: isDark ? '#EAB308' : '#D97706',
                      backgroundColor: isDark ? '#713F1222' : '#FEFCE8',
                      paddingVertical: 9,
                    },
                  ]}
                  activeOpacity={0.85}
                  onPress={() => router.push({ pathname: '/review/[id]', params: { id: String(item.listing_id), title: item.listing_title } })}
                >
                  <Ionicons name="star-outline" size={14} color={isDark ? '#FACC15' : '#D97706'} />
                  <Text style={[styles.attractionActionText, { color: isDark ? '#FACC15' : '#D97706', fontSize: 12, fontWeight: '700' }]}>Leave Review</Text>
                </TouchableOpacity>
              ) : null}
              {item.dispute_status || item.dispute_id ? (
                <TouchableOpacity
                  style={[
                    styles.attractionActionBtn,
                    {
                      flex: 1,
                      borderColor: isDark ? '#475569' : '#94A3B8',
                      backgroundColor: isDark ? '#33415525' : '#F1F5F9',
                      paddingVertical: 9,
                    },
                  ]}
                  activeOpacity={0.85}
                  onPress={() => router.push({ pathname: '/report/[id]', params: { id: String(item.id), title: item.listing_title } })}
                >
                  <Ionicons name="document-text" size={14} color={isDark ? '#94A3B8' : '#475569'} />
                  <Text style={[styles.attractionActionText, { color: isDark ? '#94A3B8' : '#475569', fontSize: 12, fontWeight: '700' }]}>View Report</Text>
                </TouchableOpacity>
              ) : item.can_report || overallStatus === 'completed' ? (
                <TouchableOpacity
                  style={[styles.attractionActionBtn, { flex: 1, borderColor: '#EF4444', paddingVertical: 9 }]}
                  activeOpacity={0.85}
                  onPress={() => router.push({ pathname: '/report/[id]', params: { id: String(item.id), title: item.listing_title } })}
                >
                  <Ionicons name="flag-outline" size={14} color="#EF4444" />
                  <Text style={[styles.attractionActionText, { color: '#EF4444', fontSize: 12 }]}>Report Issue</Text>
                </TouchableOpacity>
              ) : null}
            </View>
          ) : null}
        </View>
      </TouchableOpacity>
    );
  };

  const renderItem = ({ item }: { item: TripUnifiedItem }) => {
    if (item.kind === 'hotel') return renderHotelItem(item.data, item.isHistory);
    if (item.kind === 'package') return renderPackageItem(item.data, item.isHistory);
    if (item.kind === 'activity') return renderActivityItem(item.data, item.isHistory);
    return renderAttractionItem(item.data, item.isHistory);
  };

  return (
    <SafeAreaView style={[styles.container, { backgroundColor: colors.bgAlt }]} edges={['left', 'right']}>
      <StatusBar barStyle={isDark ? 'light-content' : 'dark-content'} />
      <View style={[styles.headerBar, { paddingTop: insets.top + 12 }]}>
        <View style={styles.headerTitleRow}>
          <View style={{ flex: 1 }}>
            <ScreenTitle
              title={viewMode === 'active' ? "My Trips" : "Trip History"}
              subtitle={viewMode === 'active' ? "Your active bookings & upcoming adventures" : "Completed hotel stays & attraction tours"}
            />
          </View>
          <TouchableOpacity
            style={[
              styles.historyIconBtn,
              { backgroundColor: viewMode === 'history' ? colors.primary : (isDark ? '#1E2330' : '#F1F5F9'), borderColor: colors.border }
            ]}
            activeOpacity={0.8}
            onPress={() => setViewMode(viewMode === 'active' ? 'history' : 'active')}
            accessibilityLabel={viewMode === 'active' ? "View Trip History" : "View Active Trips"}
          >
            <Ionicons
              name={viewMode === 'history' ? "time" : "time-outline"}
              size={20}
              color={viewMode === 'history' ? "#FFFFFF" : colors.text}
            />
            {historyCount > 0 ? (
              <View style={[styles.historyBadge, { backgroundColor: viewMode === 'history' ? '#FFFFFF' : '#EF4444' }]}>
                <Text style={[styles.historyBadgeText, { color: viewMode === 'history' ? colors.primary : '#FFFFFF' }]}>
                  {historyCount}
                </Text>
              </View>
            ) : null}
          </TouchableOpacity>
        </View>

        {/* Mode Segment: Active vs History */}
        <View style={[styles.modeSegmentWrap, { backgroundColor: isDark ? '#1E2330' : '#F1F5F9', borderColor: colors.border }]}>
          <TouchableOpacity
            style={[styles.modeSegmentBtn, viewMode === 'active' && [styles.modeSegmentBtnActive, { backgroundColor: colors.card }]]}
            activeOpacity={0.8}
            onPress={() => setViewMode('active')}
          >
            <Ionicons name="sparkles" size={14} color={viewMode === 'active' ? colors.primary : colors.textMute} />
            <Text style={[styles.modeSegmentText, { color: viewMode === 'active' ? colors.primary : colors.textSub, fontWeight: viewMode === 'active' ? '800' : '600' }]}>
              Active ({activeCount})
            </Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={[styles.modeSegmentBtn, viewMode === 'history' && [styles.modeSegmentBtnActive, { backgroundColor: colors.card }]]}
            activeOpacity={0.8}
            onPress={() => setViewMode('history')}
          >
            <Ionicons name="time" size={14} color={viewMode === 'history' ? colors.primary : colors.textMute} />
            <Text style={[styles.modeSegmentText, { color: viewMode === 'history' ? colors.primary : colors.textSub, fontWeight: viewMode === 'history' ? '800' : '600' }]}>
              History ({historyCount})
            </Text>
          </TouchableOpacity>
        </View>

        {/* Category Filters: All Trips, Hotel Stays, Attractions & Tours */}
        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.categoryScroll}>
          <TouchableOpacity
            style={[
              styles.catChip,
              categoryFilter === 'all'
                ? { backgroundColor: colors.primary, borderColor: colors.primary }
                : { backgroundColor: colors.card, borderColor: colors.border }
            ]}
            onPress={() => setCategoryFilter('all')}
          >
            <Text style={[styles.catChipText, { color: categoryFilter === 'all' ? '#FFFFFF' : colors.text }]}>
              All Trips ({currentModeItems.length})
            </Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={[
              styles.catChip,
              categoryFilter === 'package'
                ? { backgroundColor: colors.primary, borderColor: colors.primary }
                : { backgroundColor: colors.card, borderColor: colors.border }
            ]}
            onPress={() => setCategoryFilter('package')}
          >
            <Ionicons name="cube-outline" size={13} color={categoryFilter === 'package' ? '#FFFFFF' : colors.primary} />
            <Text style={[styles.catChipText, { color: categoryFilter === 'package' ? '#FFFFFF' : colors.text }]}>
              Packages ({packageCount})
            </Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={[
              styles.catChip,
              categoryFilter === 'hotel'
                ? { backgroundColor: colors.primary, borderColor: colors.primary }
                : { backgroundColor: colors.card, borderColor: colors.border }
            ]}
            onPress={() => setCategoryFilter('hotel')}
          >
            <Ionicons name="bed-outline" size={13} color={categoryFilter === 'hotel' ? '#FFFFFF' : '#3B82F6'} />
            <Text style={[styles.catChipText, { color: categoryFilter === 'hotel' ? '#FFFFFF' : colors.text }]}>
              Hotel &amp; Stays ({hotelCount})
            </Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={[
              styles.catChip,
              categoryFilter === 'activity'
                ? { backgroundColor: colors.primary, borderColor: colors.primary }
                : { backgroundColor: colors.card, borderColor: colors.border }
            ]}
            onPress={() => setCategoryFilter('activity')}
          >
            <Ionicons name="compass-outline" size={13} color={categoryFilter === 'activity' ? '#FFFFFF' : '#10B981'} />
            <Text style={[styles.catChipText, { color: categoryFilter === 'activity' ? '#FFFFFF' : colors.text }]}>
              Activities ({activityCount})
            </Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={[
              styles.catChip,
              categoryFilter === 'attraction'
                ? { backgroundColor: colors.primary, borderColor: colors.primary }
                : { backgroundColor: colors.card, borderColor: colors.border }
            ]}
            onPress={() => setCategoryFilter('attraction')}
          >
            <Ionicons name="ticket-outline" size={13} color={categoryFilter === 'attraction' ? '#FFFFFF' : '#EC4899'} />
            <Text style={[styles.catChipText, { color: categoryFilter === 'attraction' ? '#FFFFFF' : colors.text }]}>
              Attractions ({attractionCount})
            </Text>
          </TouchableOpacity>
        </ScrollView>
      </View>

      {loading ? (
        <View style={styles.center}>
          <ActivityIndicator size="large" color={colors.primary} />
        </View>
      ) : !loggedIn ? (
        <EmptyState
          icon="briefcase-outline"
          title="Track your trips"
          subtitle="Log in to view and manage your bookings."
          actionLabel="Log in"
          onAction={() => router.push('/(auth)/login')}
        />
      ) : (
        <FlatList
          data={displayedItems}
          renderItem={renderItem}
          keyExtractor={(item) => item.id}
          contentContainerStyle={styles.listContent}
          showsVerticalScrollIndicator={false}
          refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor={colors.primary} />}
          ListEmptyComponent={
            <EmptyState
              icon={error ? 'cloud-offline-outline' : (viewMode === 'history' ? 'time-outline' : 'sparkles-outline')}
              title={
                error
                  ? 'Something went wrong'
                  : viewMode === 'history'
                  ? 'No trip history yet'
                  : 'No active trips'
              }
              subtitle={
                error || (
                  viewMode === 'history'
                    ? (categoryFilter === 'hotel'
                        ? 'You have no completed hotel stays in your history.'
                        : categoryFilter === 'activity'
                        ? 'You have no completed activities in your history.'
                        : categoryFilter === 'attraction'
                        ? 'You have no completed attraction visits in your history.'
                        : categoryFilter === 'package'
                        ? 'You have no completed travel packages in your history.'
                        : 'When your hotel stays, activities, or tours are completed or refunded, they will appear here in your history.')
                    : (categoryFilter === 'hotel'
                        ? 'You have no active hotel reservations. Completed stays are saved in History.'
                        : categoryFilter === 'activity'
                        ? 'You have no active activity bookings right now. Completed activities are saved in History.'
                        : categoryFilter === 'attraction'
                        ? 'You have no active attraction bookings right now. Completed visits are saved in History.'
                        : categoryFilter === 'package'
                        ? 'You have no active travel package bookings right now. Completed packages are saved in History.'
                        : 'You have no active bookings right now. Completed trips are saved in History.')
                )
              }
              actionLabel={
                error
                  ? 'Retry'
                  : viewMode === 'active'
                  ? (historyCount > 0 ? `View History (${historyCount})` : 'Explore Cebu')
                  : 'View Active Trips'
              }
              onAction={
                error
                  ? load
                  : viewMode === 'active'
                  ? (historyCount > 0 ? () => setViewMode('history') : () => router.push('/things-to-do'))
                  : () => setViewMode('active')
              }
            />
          }
        />
      )}

      {/* ── Booking Details & Receipt Modal ── */}
      <Modal
        visible={Boolean(selectedBookingDetails)}
        transparent
        animationType="slide"
        onRequestClose={() => setSelectedBookingDetails(null)}
      >
        <View style={styles.modalBackdrop}>
          <View style={[styles.modalCard, { backgroundColor: colors.card, borderColor: colors.border }]}>
            {(() => {
              const isDetailsHotel = Boolean(
                selectedBookingDetails?.is_hotel ||
                selectedBookingDetails?.kind === 'hotel' ||
                selectedBookingDetails?.category_slug === 'hotels' ||
                ((selectedBookingDetails?.room_id && selectedBookingDetails.room_id > 0) && selectedBookingDetails?.category_slug !== 'things-to-do' && selectedBookingDetails?.category_slug !== 'attractions') ||
                (selectedBookingDetails?.room_name && selectedBookingDetails?.category_slug !== 'things-to-do' && selectedBookingDetails?.category_slug !== 'attractions')
              );
              const isDetailsActivity = !isDetailsHotel && Boolean(
                selectedBookingDetails?.kind === 'activity' ||
                selectedBookingDetails?.category_slug === 'things-to-do' ||
                selectedBookingDetails?.category_slug === 'things_to_do'
              );
              const isDetailsAttraction = !isDetailsHotel && !isDetailsActivity;

              return (
                <>
                  <View style={[styles.modalHeader, { borderBottomColor: colors.border }]}>
                    <View style={{ flex: 1 }}>
                      <Text style={[styles.modalTitle, { color: colors.text }]}>
                        {isDetailsHotel ? 'Hotel Stay Details & Receipt' : (isDetailsActivity ? 'Activity Details & Receipt' : 'Attraction Details & Receipt')}
                      </Text>
                      <Text style={[styles.modalSubtitle, { color: colors.textSub }]} numberOfLines={1}>
                        {`Booking #${selectedBookingDetails?.id} \u2022 ${selectedBookingDetails?.listing_title}`}
                      </Text>
                    </View>
                    <TouchableOpacity onPress={() => setSelectedBookingDetails(null)} style={styles.modalCloseBtn} activeOpacity={0.7}>
                      <Ionicons name="close" size={24} color={colors.textSub} />
                    </TouchableOpacity>
                  </View>

                  <ScrollView showsVerticalScrollIndicator={false} contentContainerStyle={{ padding: 18, paddingBottom: 24 }}>
                    {/* Status / History Banner */}
                    {selectedBookingDetails ? (
                      <View
                        style={[
                          styles.historyBanner,
                          {
                            backgroundColor: isBookingCompleted(selectedBookingDetails)
                              ? (isDark ? '#064E3B22' : '#F0FDF4')
                              : (isDark ? '#1E3A8A22' : '#EFF6FF'),
                            borderColor: isBookingCompleted(selectedBookingDetails) ? '#10B98155' : '#3B82F655',
                            marginTop: 0,
                            marginBottom: 14,
                          },
                        ]}
                      >
                        <Ionicons
                          name={isBookingCompleted(selectedBookingDetails) ? 'checkmark-circle' : 'sparkles'}
                          size={18}
                          color={isBookingCompleted(selectedBookingDetails) ? '#059669' : '#2563EB'}
                        />
                        <View style={{ flex: 1 }}>
                          <Text
                            style={[
                              styles.historyBannerText,
                              {
                                color: isBookingCompleted(selectedBookingDetails)
                                  ? (isDark ? '#34D399' : '#166534')
                                  : (isDark ? '#93C5FD' : '#1E40AF'),
                              },
                            ]}
                          >
                            {isBookingCompleted(selectedBookingDetails)
                              ? 'Completed Reservation \u2022 In History'
                              : 'Active / Upcoming Reservation'}
                          </Text>
                          <Text style={{ fontSize: 11, color: colors.textSub, marginTop: 1 }}>
                            {isBookingCompleted(selectedBookingDetails)
                              ? 'Stay or visit has concluded. Safely archived in your History.'
                              : 'Your reservation is active and upcoming.'}
                          </Text>
                        </View>
                      </View>
                    ) : null}

                    {/* Service Details Box */}
                    <View style={[styles.detailSectionBox, { backgroundColor: isDark ? '#1E2330' : '#F8FAFC', borderColor: colors.border }]}>
                      <Text style={[styles.detailSectionTitle, { color: colors.text }]}>Service Overview</Text>
                      <Text style={[styles.detailMainValue, { color: colors.text }]}>{selectedBookingDetails?.listing_title}</Text>
                      {isDetailsHotel && selectedBookingDetails?.room_name ? (
                        <View style={[styles.hotelRoomBadge, { marginTop: 6 }]}>
                          <Ionicons name="bed-outline" size={13} color="#2563EB" />
                          <Text style={styles.hotelRoomBadgeText}>{selectedBookingDetails.room_name}</Text>
                        </View>
                      ) : null}
                      <View style={[styles.metaRow, { marginTop: 6 }]}>
                        <Ionicons name="location-outline" size={13} color={colors.textSub} />
                        <Text style={[styles.metaText, { color: colors.textSub }]}>{selectedBookingDetails?.area || 'Cebu'}</Text>
                      </View>
                    </View>

                    {/* Schedule Box */}
                    <View style={[styles.detailSectionBox, { backgroundColor: isDark ? '#1E2330' : '#F8FAFC', borderColor: colors.border, marginTop: 12 }]}>
                      <Text style={[styles.detailSectionTitle, { color: colors.text }]}>
                        {isDetailsHotel ? 'Stay Schedule' : (isDetailsActivity ? 'Activity Schedule' : 'Visit Schedule')}
                      </Text>
                      {isDetailsHotel ? (
                        <>
                          <View style={{ flexDirection: 'row', justifyContent: 'space-between', marginTop: 8 }}>
                            <View style={{ flex: 1 }}>
                              <Text style={[styles.hotelStayLabel, { color: colors.textMute }]}>CHECK-IN</Text>
                              <Text style={[styles.hotelStayValue, { color: colors.text }]}>
                                {formatDate(selectedBookingDetails?.check_in_date || selectedBookingDetails?.booking_date)}
                              </Text>
                              <Text style={{ fontSize: 11, color: colors.textMute }}>From {selectedBookingDetails?.check_in_time_label || '2:00 PM'}</Text>
                            </View>
                            <Ionicons name="arrow-forward" size={14} color={colors.textMute} style={{ marginHorizontal: 8, marginTop: 12 }} />
                            <View style={{ flex: 1 }}>
                              <Text style={[styles.hotelStayLabel, { color: colors.textMute }]}>CHECK-OUT</Text>
                              <Text style={[styles.hotelStayValue, { color: colors.text }]}>
                                {formatDate(selectedBookingDetails?.check_out_date || selectedBookingDetails?.booking_date)}
                              </Text>
                              <Text style={{ fontSize: 11, color: colors.textMute }}>Until {selectedBookingDetails?.check_out_time_label || '12:00 PM'}</Text>
                            </View>
                          </View>
                          <View style={{ marginTop: 8, paddingTop: 8, borderTopWidth: 1, borderColor: colors.border, flexDirection: 'row', gap: 16 }}>
                            <Text style={{ fontSize: 12, color: colors.textSub, fontWeight: '600' }}>
                              Nights: <Text style={{ color: colors.text, fontWeight: '800' }}>{selectedBookingDetails?.nights || 1}</Text>
                            </Text>
                            <Text style={{ fontSize: 12, color: colors.textSub, fontWeight: '600' }}>
                              Guests: <Text style={{ color: colors.text, fontWeight: '800' }}>{selectedBookingDetails?.guests || 1}</Text>
                            </Text>
                          </View>
                        </>
                      ) : (
                        <>
                          <View style={[styles.metaRow, { marginTop: 6 }]}>
                            <Ionicons name="calendar-outline" size={14} color={colors.textSub} />
                            <Text style={[styles.metaText, { color: colors.text, fontWeight: '700' }]}>
                              {selectedBookingDetails?.booking_dates && selectedBookingDetails.booking_dates.length > 1
                                ? `${selectedBookingDetails.booking_dates.length} days: ${selectedBookingDetails.booking_dates.join(', ')}`
                                : formatDate(selectedBookingDetails?.booking_date)}
                            </Text>
                          </View>
                          {selectedBookingDetails?.booking_time ? (
                            <View style={[styles.metaRow, { marginTop: 4 }]}>
                              <Ionicons name="time-outline" size={14} color={colors.textSub} />
                              <Text style={[styles.metaText, { color: colors.textSub }]}>
                                {formatTime(selectedBookingDetails.booking_time)}
                                {selectedBookingDetails.booking_end_time ? ` - ${formatTime(selectedBookingDetails.booking_end_time)}` : ''}
                              </Text>
                            </View>
                          ) : null}
                          <View style={[styles.metaRow, { marginTop: 4 }]}>
                            <Ionicons name="people-outline" size={14} color={colors.textSub} />
                            <Text style={[styles.metaText, { color: colors.textSub }]}>
                              {selectedBookingDetails?.guests} {isDetailsActivity ? 'participant' : 'guest'}{selectedBookingDetails && selectedBookingDetails.guests > 1 ? 's' : ''}
                            </Text>
                          </View>
                        </>
                      )}
                    </View>

              {/* Itemized Payment & Receipt Box */}
              <View style={[styles.detailSectionBox, { backgroundColor: isDark ? '#1E2330' : '#F8FAFC', borderColor: colors.border, marginTop: 12 }]}>
                {(() => {
                  const isDetailsCancelled = selectedBookingDetails?.status === 'cancelled' || selectedBookingDetails?.status === 'refunded' || selectedBookingDetails?.hotel_status === 'cancelled' || selectedBookingDetails?.hotel_status === 'refunded' || Boolean(selectedBookingDetails?.cancellation?.cancelled_at);
                  const isDetailsSettled = !isDetailsCancelled && (
                    selectedBookingDetails?.payment_stage === 'settled' ||
                    selectedBookingDetails?.hotel_status === 'completed' ||
                    (!isDetailsHotel && selectedBookingDetails?.status === 'completed')
                  );
                  const isDetailsCheckedIn = !isDetailsCancelled && !isDetailsSettled && (
                    isDetailsHotel && selectedBookingDetails?.hotel_status === 'checked_in'
                  );
                  const isDetailsFullyPaid = !isDetailsCancelled && !isDetailsSettled && !isDetailsCheckedIn && (
                    isDetailsHotel
                      ? ((typeof selectedBookingDetails?.balance_amount === 'number' && selectedBookingDetails.balance_amount === 0 && selectedBookingDetails.payment_stage === 'paid') ||
                         ((selectedBookingDetails?.deposit_amount ?? 0) >= (selectedBookingDetails?.total_amount ?? 0) && (selectedBookingDetails?.total_amount ?? 0) > 0))
                      : (selectedBookingDetails?.payment_status === 'paid' || selectedBookingDetails?.payment_stage === 'paid')
                  );
                  const isDetailsPaid = isDetailsCheckedIn || isDetailsFullyPaid;
                  const detailsUnpaidBalance = (isDetailsSettled || isDetailsPaid)
                    ? 0
                    : (typeof selectedBookingDetails?.balance_amount === 'number' && selectedBookingDetails.balance_amount > 0
                        ? selectedBookingDetails.balance_amount
                        : Math.max(0, (selectedBookingDetails?.total_amount ?? 0) - (selectedBookingDetails?.deposit_amount ?? 0)));

                  return (
                    <>
                      <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
                        <Text style={[styles.detailSectionTitle, { color: colors.text }]}>Itemized Receipt</Text>
                        <View
                          style={{
                            flexDirection: 'row',
                            alignItems: 'center',
                            gap: 4,
                            backgroundColor: isDetailsCancelled
                              ? (isDark ? '#1E3A8A33' : '#EFF6FF')
                              : isDetailsSettled
                              ? '#DCFCE7'
                              : isDetailsPaid
                              ? (isDark ? '#064E3B33' : '#DCFCE7')
                              : '#FEF3C7',
                            paddingHorizontal: 8,
                            paddingVertical: 2,
                            borderRadius: 6,
                          }}
                        >
                          {(isDetailsSettled || isDetailsPaid) && !isDetailsCancelled ? (
                            <Ionicons name="checkmark-circle" size={12} color={isDetailsSettled ? '#166534' : '#059669'} />
                          ) : null}
                          <Text
                            style={{
                              fontSize: 11,
                              fontWeight: '800',
                              color: isDetailsCancelled
                                ? (isDark ? '#93C5FD' : '#2563EB')
                                : isDetailsSettled
                                ? '#166534'
                                : isDetailsPaid
                                ? (isDark ? '#34D399' : '#059669')
                                : '#92400E',
                            }}
                          >
                            {isDetailsCancelled ? 'Refunded' : (isDetailsSettled ? 'SETTLED' : isDetailsPaid ? 'PAID' : 'PARTIAL / PENDING')}
                          </Text>
                        </View>
                      </View>

                      <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginTop: 12 }}>
                        <Text style={{ fontSize: 13, color: colors.textSub }}>Total Amount</Text>
                        <Text style={{ fontSize: 16, fontWeight: '900', color: colors.text }}>
                          {selectedBookingDetails ? formatPrice(selectedBookingDetails.total_amount) : formatPrice(0)}
                        </Text>
                      </View>
                      {isDetailsHotel && selectedBookingDetails?.deposit_amount && selectedBookingDetails.deposit_amount > 0 ? (
                        <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginTop: 6 }}>
                          <Text style={{ fontSize: 12, color: colors.textSub }}>Deposit Paid</Text>
                          <Text style={{ fontSize: 13, fontWeight: '700', color: isDetailsCancelled ? colors.textMute : '#059669' }}>
                            {isDetailsCancelled ? '-' : formatPrice(selectedBookingDetails.deposit_amount)}
                          </Text>
                        </View>
                      ) : null}
                      <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginTop: 6 }}>
                        <Text style={{ fontSize: 12, color: colors.textMute }}>Remaining Balance</Text>
                        <Text
                          style={{
                            fontSize: 13,
                            fontWeight: '700',
                            color: isDetailsCancelled ? colors.textMute : (isDetailsSettled || isDetailsPaid ? '#059669' : '#D97706'),
                          }}
                        >
                          {isDetailsCancelled
                            ? '-'
                            : isDetailsSettled
                            ? `${formatPrice(0)} (Fully Settled)`
                            : isDetailsCheckedIn
                            ? `${formatPrice(0)} (Paid at Check-In)`
                            : isDetailsFullyPaid
                            ? `${formatPrice(0)} (Paid)`
                            : formatPrice(detailsUnpaidBalance)}
                        </Text>
                      </View>
                      <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginTop: 6 }}>
                        <Text style={{ fontSize: 12, color: colors.textMute }}>Booking Reference</Text>
                        <Text style={{ fontSize: 12, fontWeight: '700', color: colors.text }}>
                          BK-{selectedBookingDetails?.id}
                        </Text>
                      </View>
                    </>
                  );
                })()}
              </View>

              {/* Cancellation & Refund Policy Box */}
              {selectedBookingDetails?.cancellation ? (
                <View style={[styles.detailSectionBox, { backgroundColor: isDark ? '#1E2330' : '#F8FAFC', borderColor: colors.border, marginTop: 12 }]}>
                  <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
                    <Text style={[styles.detailSectionTitle, { color: colors.text }]}>Cancellation &amp; Refund Policy</Text>
                    <View
                      style={{
                        backgroundColor: selectedBookingDetails.cancellation.can_cancel ? '#DCFCE7' : (isDark ? '#334155' : '#F1F5F9'),
                        paddingHorizontal: 8,
                        paddingVertical: 3,
                        borderRadius: 6,
                      }}
                    >
                      <Text
                        style={{
                          fontSize: 10,
                          fontWeight: '800',
                          color: selectedBookingDetails.cancellation.can_cancel ? '#166534' : colors.textSub,
                        }}
                      >
                        {selectedBookingDetails.cancellation.policy.toUpperCase()} POLICY
                      </Text>
                    </View>
                  </View>

                  <Text style={{ fontSize: 13, color: colors.textSub, marginTop: 6, lineHeight: 18 }}>
                    {selectedBookingDetails.cancellation.policy_label}
                  </Text>

                  {selectedBookingDetails.cancellation.can_cancel ? (
                    <View style={[styles.cancellationNoticeBox, { backgroundColor: isDark ? '#064E3B22' : '#F0FDF4', borderColor: isDark ? '#05966955' : '#BBF7D0' }]}>
                      <Ionicons name="shield-checkmark" size={16} color="#059669" />
                      <View style={{ flex: 1, marginLeft: 8 }}>
                        <Text style={{ fontSize: 12, fontWeight: '700', color: isDark ? '#34D399' : '#166534' }}>
                          Eligible for Free Cancellation
                        </Text>
                        <Text style={{ fontSize: 11, color: isDark ? '#A7F3D0' : '#15803D', marginTop: 2 }}>
                          Deadline: {selectedBookingDetails.cancellation.deadline_formatted} ({selectedBookingDetails.cancellation.remaining_text})
                        </Text>
                      </View>
                    </View>
                  ) : (
                    <View style={[styles.cancellationNoticeBox, { backgroundColor: isDark ? '#1E293B' : '#F8FAFC', borderColor: colors.border }]}>
                      <Ionicons name="information-circle-outline" size={16} color={colors.textMute} />
                      <View style={{ flex: 1, marginLeft: 8 }}>
                        <Text style={{ fontSize: 12, fontWeight: '700', color: colors.text }}>
                          Cancellation Notice
                        </Text>
                        <Text style={{ fontSize: 11, color: colors.textSub, marginTop: 2 }}>
                          {selectedBookingDetails.cancellation.ineligible_reason || 'Cancellation is unavailable for this booking.'}
                        </Text>
                      </View>
                    </View>
                  )}

                  {selectedBookingDetails.status === 'refunded' || selectedBookingDetails.hotel_status === 'refunded' ? (
                    <View style={{ marginTop: 10, paddingTop: 10, borderTopWidth: 1, borderColor: colors.border }}>
                      <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
                        <Text style={{ fontSize: 12, color: colors.textMute }}>Refund Amount</Text>
                        <Text style={{ fontSize: 15, fontWeight: '900', color: '#059669' }}>
                          {formatPrice(selectedBookingDetails.cancellation.refund_amount)}
                        </Text>
                      </View>
                      <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start', marginTop: 6, gap: 12 }}>
                        <Text style={{ fontSize: 12, color: colors.textMute, flexShrink: 0 }}>Refund Status</Text>
                        <Text style={{ fontSize: 12, fontWeight: '700', color: '#2563EB', flex: 1, textAlign: 'right' }}>
                          Initiated ({selectedBookingDetails.cancellation.processing_time})
                        </Text>
                      </View>
                    </View>
                  ) : selectedBookingDetails.cancellation.can_cancel && selectedBookingDetails.cancellation.refund_amount > 0 ? (
                    <View style={{ marginTop: 10, paddingTop: 8, borderTopWidth: 1, borderColor: colors.border }}>
                      <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
                        <Text style={{ fontSize: 12, color: colors.textMute }}>Applicable Refund on Cancel</Text>
                        <Text style={{ fontSize: 14, fontWeight: '800', color: '#059669' }}>
                          {formatPrice(selectedBookingDetails.cancellation.refund_amount)}
                        </Text>
                      </View>
                      {selectedBookingDetails.cancellation.refund_description ? (
                        <Text style={{ fontSize: 11, fontWeight: '600', color: '#059669', marginTop: 2 }}>
                          {selectedBookingDetails.cancellation.refund_description}
                        </Text>
                      ) : null}
                      {selectedBookingDetails.cancellation.deposit_rule ? (
                        <View style={{ marginTop: 6, padding: 8, borderRadius: 8, backgroundColor: isDark ? '#064E3B18' : '#F0FDF4' }}>
                          <Text style={{ fontSize: 11, color: isDark ? '#6EE7B7' : '#15803D', lineHeight: 16 }}>
                            {selectedBookingDetails.cancellation.deposit_rule}
                          </Text>
                        </View>
                      ) : null}
                    </View>
                  ) : null}
                </View>
              ) : null}

              {/* Modal Action Buttons */}
              <View style={{ marginTop: 18, gap: 10 }}>
                {selectedBookingDetails?.listing_slug ? (
                  <TouchableOpacity
                    style={[styles.actionBtn, { borderColor: colors.primary, justifyContent: 'center', paddingVertical: 10 }]}
                    onPress={() => {
                      const slug = selectedBookingDetails.listing_slug;
                      setSelectedBookingDetails(null);
                      router.push({ pathname: '/listing/[slug]', params: { slug } });
                    }}
                  >
                    <Ionicons name="compass-outline" size={15} color={colors.primary} />
                    <Text style={[styles.actionText, { color: colors.primary }]}>View Listing Details</Text>
                  </TouchableOpacity>
                ) : null}

                {selectedBookingDetails?.can_review ? (
                  <TouchableOpacity
                    style={[
                      styles.actionBtn,
                      {
                        borderColor: isDark ? '#EAB308' : '#D97706',
                        backgroundColor: isDark ? '#713F1222' : '#FEFCE8',
                        justifyContent: 'center',
                        paddingVertical: 10,
                      },
                    ]}
                    onPress={() => {
                      const item = selectedBookingDetails;
                      setSelectedBookingDetails(null);
                      router.push({ pathname: '/review/[id]', params: { id: String(item.listing_id), title: item.listing_title } });
                    }}
                  >
                    <Ionicons name="star-outline" size={15} color={isDark ? '#FACC15' : '#D97706'} />
                    <Text style={[styles.actionText, { color: isDark ? '#FACC15' : '#D97706', fontWeight: '700' }]}>Rate &amp; Review Experience</Text>
                  </TouchableOpacity>
                ) : null}

                {selectedBookingDetails?.dispute_status || selectedBookingDetails?.dispute_id ? (
                  <TouchableOpacity
                    style={[
                      styles.actionBtn,
                      {
                        borderColor: isDark ? '#475569' : '#94A3B8',
                        backgroundColor: isDark ? '#33415525' : '#F1F5F9',
                        justifyContent: 'center',
                        paddingVertical: 10,
                      },
                    ]}
                    onPress={() => {
                      const item = selectedBookingDetails;
                      setSelectedBookingDetails(null);
                      router.push({ pathname: '/report/[id]', params: { id: String(item.id), title: item.listing_title, is_hotel: isDetailsHotel ? '1' : '0' } });
                    }}
                  >
                    <Ionicons name="document-text" size={15} color={isDark ? '#94A3B8' : '#475569'} />
                    <Text style={[styles.actionText, { color: isDark ? '#94A3B8' : '#475569', fontWeight: '700' }]}>View Report</Text>
                  </TouchableOpacity>
                ) : selectedBookingDetails?.can_report ? (
                  <TouchableOpacity
                    style={[styles.actionBtn, { borderColor: '#EF4444', justifyContent: 'center', paddingVertical: 10 }]}
                    onPress={() => {
                      const item = selectedBookingDetails;
                      setSelectedBookingDetails(null);
                      router.push({ pathname: '/report/[id]', params: { id: String(item.id), title: item.listing_title, is_hotel: isDetailsHotel ? '1' : '0' } });
                    }}
                  >
                    <Ionicons name="flag-outline" size={15} color="#EF4444" />
                    <Text style={[styles.actionText, { color: '#EF4444' }]}>Report Issue</Text>
                  </TouchableOpacity>
                ) : null}

                {/* Cancel Booking Action — hidden when completed, disabled when ineligible */}
                {selectedBookingDetails &&
                selectedBookingDetails.status !== 'completed' &&
                selectedBookingDetails.hotel_status !== 'completed' &&
                selectedBookingDetails.status !== 'cancelled' &&
                selectedBookingDetails.status !== 'refunded' &&
                selectedBookingDetails.hotel_status !== 'cancelled' &&
                selectedBookingDetails.hotel_status !== 'refunded' ? (
                  <TouchableOpacity
                    style={[
                      styles.actionBtn,
                      {
                        borderColor: selectedBookingDetails.cancellation?.can_cancel ? '#EF4444' : colors.border,
                        backgroundColor: selectedBookingDetails.cancellation?.can_cancel ? (isDark ? '#7F1D1D22' : '#FEF2F2') : (isDark ? '#1E2330' : '#F1F5F9'),
                        justifyContent: 'center',
                        paddingVertical: 11,
                        opacity: selectedBookingDetails.cancellation?.can_cancel ? 1 : 0.6,
                      },
                    ]}
                    disabled={!selectedBookingDetails.cancellation?.can_cancel || isSubmittingCancel}
                    onPress={() => handleCancelBooking(selectedBookingDetails)}
                  >
                    {isSubmittingCancel ? (
                      <ActivityIndicator size="small" color="#EF4444" />
                    ) : (
                      <>
                        <Ionicons
                          name="close-circle-outline"
                          size={16}
                          color={selectedBookingDetails.cancellation?.can_cancel ? '#EF4444' : colors.textMute}
                        />
                        <Text
                          style={[
                            styles.actionText,
                            { color: selectedBookingDetails.cancellation?.can_cancel ? '#EF4444' : colors.textMute },
                          ]}
                        >
                          {selectedBookingDetails.cancellation?.can_cancel ? 'Cancel Booking' : 'Cancellation Unavailable'}
                        </Text>
                      </>
                    )}
                  </TouchableOpacity>
                ) : null}

                <TouchableOpacity
                  style={[
                    styles.cancelModalBtn,
                    { borderColor: colors.border, paddingVertical: 12, alignItems: 'center', borderRadius: 12, borderWidth: 1 },
                  ]}
                  onPress={() => setSelectedBookingDetails(null)}
                >
                  <Text style={{ color: colors.text, fontWeight: '700' }}>Close</Text>
                </TouchableOpacity>
              </View>
            </ScrollView>
          </>
        );
      })()}
    </View>
  </View>
</Modal>

      {/* ── Balance Payment Modal ── */}
      <Modal
        visible={balanceModalVisible}
        transparent
        animationType="slide"
        onRequestClose={() => {
          if (!isSubmittingPay) setBalanceModalVisible(false);
        }}
      >
        <View style={styles.modalBackdrop}>
          <View style={[styles.modalCard, { backgroundColor: colors.card, borderColor: colors.border }]}>
            {/* Header */}
            <View style={[styles.modalHeader, { borderBottomColor: colors.border }]}>
              {payStep !== 'method' ? (
                <TouchableOpacity
                  onPress={() => setPayStep('method')}
                  disabled={isSubmittingPay}
                  style={styles.modalBackBtn}
                >
                  <Ionicons name="arrow-back" size={22} color={colors.text} />
                </TouchableOpacity>
              ) : null}
              <View style={{ flex: 1 }}>
                <Text style={[styles.modalTitle, { color: colors.text }]}>
                  {payStep === 'method'
                    ? 'Pay Remaining Balance'
                    : payStep === 'qr'
                    ? (payMethod === 'gcash' ? 'GCash / e-Wallet' : 'InstaPay QR')
                    : 'Card Payment'}
                </Text>
                <Text style={[styles.modalSubtitle, { color: colors.textSub }]} numberOfLines={1}>
                  {`${selectedBooking?.listing_title} \u2022 ${selectedBooking?.room_name || 'Room'}`}
                </Text>
              </View>
              <TouchableOpacity
                onPress={() => setBalanceModalVisible(false)}
                disabled={isSubmittingPay}
                style={styles.modalCloseBtn}
              >
                <Ionicons name="close" size={22} color={colors.textSub} />
              </TouchableOpacity>
            </View>

            <ScrollView showsVerticalScrollIndicator={false} keyboardShouldPersistTaps="handled" contentContainerStyle={{ padding: 18 }}>
              {/* Outstanding Balance Banner */}
              <View style={[styles.modalDueCard, { backgroundColor: isDark ? '#064E3B22' : '#F0FDF4', borderColor: isDark ? '#05966955' : '#A7F3D0' }]}>
                <View style={{ flex: 1 }}>
                  <Text style={[styles.modalDueLabel, { color: isDark ? '#A7F3D0' : '#047857' }]}>
                    OUTSTANDING BALANCE DUE
                  </Text>
                  <Text style={[styles.modalDueAmount, { color: isDark ? '#34D399' : '#059669' }]}>
                    {formatPrice(balanceToPay)}
                  </Text>
                </View>
                <View style={[styles.modalDueChip, { backgroundColor: isDark ? '#05966944' : '#DCFCE7' }]}>
                  <Ionicons name="key" size={12} color="#059669" />
                  <Text style={styles.modalDueChipText}>Checked In</Text>
                </View>
              </View>

              {/* Step 1: Select payment method */}
              {payStep === 'method' ? (
                <View style={{ marginTop: 16 }}>
                  <Text style={[styles.sectionHeading, { color: colors.text }]}>Select Payment Method</Text>

                  <TouchableOpacity
                    style={[styles.methodOption, { borderColor: colors.border, backgroundColor: isDark ? '#1E2330' : '#F8FAFC' }]}
                    activeOpacity={0.8}
                    onPress={() => {
                      setPayMethod('gcash');
                      setPayReference('');
                      setPayStep('qr');
                    }}
                  >
                    <View style={[styles.methodOptionIcon, { backgroundColor: '#E0EDFF' }]}>
                      <Ionicons name="qr-code" size={22} color="#1D4ED8" />
                    </View>
                    <View style={{ flex: 1, marginLeft: 12 }}>
                      <Text style={[styles.methodOptionTitle, { color: colors.text }]}>GCash / e-Wallet</Text>
                      <Text style={[styles.methodOptionDesc, { color: colors.textSub }]}>Scan QR with GCash or Maya</Text>
                    </View>
                    <Ionicons name="chevron-forward" size={18} color={colors.textSub} />
                  </TouchableOpacity>

                  <TouchableOpacity
                    style={[styles.methodOption, { borderColor: colors.border, backgroundColor: isDark ? '#1E2330' : '#F8FAFC' }]}
                    activeOpacity={0.8}
                    onPress={() => {
                      setPayMethod('instapay');
                      setPayReference('');
                      setPayStep('qr');
                    }}
                  >
                    <View style={[styles.methodOptionIcon, { backgroundColor: '#FEE2E2' }]}>
                      <Ionicons name="qr-code" size={22} color="#DC2626" />
                    </View>
                    <View style={{ flex: 1, marginLeft: 12 }}>
                      <Text style={[styles.methodOptionTitle, { color: colors.text }]}>InstaPay</Text>
                      <Text style={[styles.methodOptionDesc, { color: colors.textSub }]}>Scan with any Philippine banking app</Text>
                    </View>
                    <Ionicons name="chevron-forward" size={18} color={colors.textSub} />
                  </TouchableOpacity>

                  <TouchableOpacity
                    style={[styles.methodOption, { borderColor: colors.border, backgroundColor: isDark ? '#1E2330' : '#F8FAFC' }]}
                    activeOpacity={0.8}
                    onPress={() => setPayStep('card')}
                  >
                    <View style={[styles.methodOptionIcon, { backgroundColor: '#D1FAE5' }]}>
                      <Ionicons name="card" size={22} color="#059669" />
                    </View>
                    <View style={{ flex: 1, marginLeft: 12 }}>
                      <Text style={[styles.methodOptionTitle, { color: colors.text }]}>Debit / Credit Card</Text>
                      <Text style={[styles.methodOptionDesc, { color: colors.textSub }]}>Visa, Mastercard, JCB</Text>
                    </View>
                    <Ionicons name="chevron-forward" size={18} color={colors.textSub} />
                  </TouchableOpacity>
                </View>
              ) : null}

              {/* Step 2a: QR Code */}
              {payStep === 'qr' ? (
                <View style={{ marginTop: 12 }}>
                  <View style={[styles.qrContainer, { backgroundColor: '#FFFFFF', borderColor: colors.border }]}>
                    <Image
                      source={payMethod === 'gcash' ? QR_EWALLET : QR_INSTAPAY}
                      style={styles.qrImage}
                      resizeMode="contain"
                    />
                  </View>

                  <Text style={[styles.qrInstructions, { color: colors.textSub }]}>
                    Scan the QR code in your {payMethod === 'gcash' ? 'GCash / e-Wallet' : 'bank'} app, pay exactly{' '}
                    <Text style={{ fontWeight: '800', color: colors.text }}>{formatPrice(balanceToPay)}</Text>, then enter the reference number below.
                  </Text>

                  <Text style={[styles.inputLabel, { color: colors.textSub }]}>Reference / Confirmation Number</Text>
                  <TextInput
                    style={[styles.textInput, { backgroundColor: isDark ? '#1E2330' : '#F1F5F9', color: colors.text, borderColor: colors.border }]}
                    placeholder="e.g. 1029384756"
                    placeholderTextColor={colors.textMute}
                    value={payReference}
                    onChangeText={setPayReference}
                    autoCapitalize="characters"
                    editable={!isSubmittingPay}
                  />

                  <TouchableOpacity
                    style={[styles.submitPayBtn, { backgroundColor: '#059669' }, isSubmittingPay && { opacity: 0.6 }]}
                    onPress={confirmQrPayment}
                    disabled={isSubmittingPay}
                    activeOpacity={0.85}
                  >
                    {isSubmittingPay ? (
                      <ActivityIndicator size="small" color="#FFFFFF" />
                    ) : (
                      <>
                        <Ionicons name="checkmark-circle-outline" size={18} color="#FFFFFF" />
                        <Text style={styles.submitPayBtnText}>Confirm &amp; Settle Balance</Text>
                      </>
                    )}
                  </TouchableOpacity>
                </View>
              ) : null}

              {/* Step 2b: Card Payment */}
              {payStep === 'card' ? (
                <View style={{ marginTop: 12 }}>
                  <Text style={[styles.inputLabel, { color: colors.textSub }]}>Cardholder Name</Text>
                  <TextInput
                    style={[styles.textInput, { backgroundColor: isDark ? '#1E2330' : '#F1F5F9', color: colors.text, borderColor: colors.border }]}
                    placeholder="Full name as printed on card"
                    placeholderTextColor={colors.textMute}
                    value={cardName}
                    onChangeText={setCardName}
                    autoCapitalize="words"
                    editable={!isSubmittingPay}
                  />

                  <Text style={[styles.inputLabel, { color: colors.textSub }]}>Card Number</Text>
                  <TextInput
                    style={[styles.textInput, { backgroundColor: isDark ? '#1E2330' : '#F1F5F9', color: colors.text, borderColor: colors.border }]}
                    placeholder="1234 5678 9012 3456"
                    placeholderTextColor={colors.textMute}
                    value={cardNumber}
                    onChangeText={formatCardNumber}
                    keyboardType="number-pad"
                    editable={!isSubmittingPay}
                  />

                  <View style={{ flexDirection: 'row', gap: 12 }}>
                    <View style={{ flex: 1 }}>
                      <Text style={[styles.inputLabel, { color: colors.textSub }]}>Expiry (MM/YY)</Text>
                      <TextInput
                        style={[styles.textInput, { backgroundColor: isDark ? '#1E2330' : '#F1F5F9', color: colors.text, borderColor: colors.border }]}
                        placeholder="MM/YY"
                        placeholderTextColor={colors.textMute}
                        value={cardExpiry}
                        onChangeText={formatExpiry}
                        keyboardType="number-pad"
                        editable={!isSubmittingPay}
                      />
                    </View>
                    <View style={{ flex: 1 }}>
                      <Text style={[styles.inputLabel, { color: colors.textSub }]}>CVV</Text>
                      <TextInput
                        style={[styles.textInput, { backgroundColor: isDark ? '#1E2330' : '#F1F5F9', color: colors.text, borderColor: colors.border }]}
                        placeholder="123"
                        placeholderTextColor={colors.textMute}
                        value={cardCvv}
                        onChangeText={(t) => setCardCvv(t.replace(/\D/g, '').slice(0, 4))}
                        keyboardType="number-pad"
                        secureTextEntry
                        editable={!isSubmittingPay}
                      />
                    </View>
                  </View>

                  <TouchableOpacity
                    style={[styles.submitPayBtn, { backgroundColor: '#059669' }, isSubmittingPay && { opacity: 0.6 }]}
                    onPress={confirmCardPayment}
                    disabled={isSubmittingPay}
                    activeOpacity={0.85}
                  >
                    {isSubmittingPay ? (
                      <ActivityIndicator size="small" color="#FFFFFF" />
                    ) : (
                      <>
                        <Ionicons name="shield-checkmark-outline" size={18} color="#FFFFFF" />
                        <Text style={styles.submitPayBtnText}>Pay {formatPrice(balanceToPay)}</Text>
                      </>
                    )}
                  </TouchableOpacity>
                </View>
              ) : null}
            </ScrollView>
          </View>
        </View>
      </Modal>
      {/* ── Dedicated Travel Package Details Modal ── */}
      <Modal
        visible={Boolean(selectedPackageDetails)}
        transparent
        animationType="slide"
        onRequestClose={() => setSelectedPackageDetails(null)}
      >
        <View style={styles.modalBackdrop}>
          <View style={[styles.modalCard, { backgroundColor: colors.card, borderColor: colors.border }]}>
            <View style={[styles.modalHeader, { borderBottomColor: colors.border }]}>
              <View style={{ flex: 1 }}>
                <Text style={[styles.modalTitle, { color: colors.text }]}>Travel Package Details</Text>
                <Text style={[styles.modalSubtitle, { color: colors.textSub }]} numberOfLines={1}>
                  {`Booking #BK-${selectedPackageDetails?.id} \u2022 ${selectedPackageDetails?.listing_title}`}
                </Text>
              </View>
              <TouchableOpacity onPress={() => setSelectedPackageDetails(null)} style={styles.modalCloseBtn} activeOpacity={0.7}>
                <Ionicons name="close" size={24} color={colors.textSub} />
              </TouchableOpacity>
            </View>

            <ScrollView showsVerticalScrollIndicator={false} contentContainerStyle={{ padding: 18, paddingBottom: 24 }}>
              {selectedPackageDetails ? (() => {
                const details = selectedPackageDetails.package_details;
                const pkgRef = selectedPackageDetails.package_booking_ref || `BK-${selectedPackageDetails.id}`;
                const overallStatus = (selectedPackageDetails.package_overall_status || selectedPackageDetails.status || 'pending').toLowerCase();
                const guideName = selectedPackageDetails.guide_name || details?.guide?.name || 'Marc Repuesto';
                const guideAvatar = selectedPackageDetails.guide_avatar || details?.guide?.avatar || '';

                const guideItem = selectedPackageDetails.provider_statuses?.find((p: any) => p.service_type === 'guide');
                const guideSt = guideItem?.status || (overallStatus === 'completed' ? 'completed' : (overallStatus === 'confirmed' ? 'confirmed' : 'pending'));

                const hotelItem = selectedPackageDetails.provider_statuses?.find((p: any) => p.service_type === 'hotel');
                const hasHotel = Boolean(hotelItem || details?.hotel_included || details?.accommodation);
                const hotelSt = hotelItem?.hotel_reservation_status || hotelItem?.status || (hasHotel && overallStatus === 'completed' ? 'completed' : (details?.hotel_reservation_status === 'approved' ? 'confirmed' : 'pending'));
                const hotelObj = details?.accommodation || details?.accommodationDetail;

                const vehicleItem = selectedPackageDetails.provider_statuses?.find((p: any) => p.service_type === 'vehicle');
                const hasVehicle = Boolean(vehicleItem || details?.rental_included || details?.rental);
                const vehicleSt = vehicleItem?.rental_request_status || vehicleItem?.status || (hasVehicle && overallStatus === 'completed' ? 'completed' : (details?.rental_request_status === 'approved' ? 'confirmed' : 'pending'));
                const vehicleObj = details?.rental || details?.rentalDetail;

                const hotelReplacementObj = details?.accommodation_replacement || selectedPackageDetails.replacement_hotel;
                const originalHotelCancelled = Boolean(
                  (hotelItem && (hotelItem.status === 'cancelled' || hotelItem.status === 'rejected' || hotelItem.status === 'declined')) ||
                  selectedPackageDetails.all_cancelled_items?.some((c: any) => c.service_type === 'hotel') ||
                  selectedPackageDetails.cancelled_items?.some((c: any) => c.service_type === 'hotel') ||
                  (hotelReplacementObj && (hotelReplacementObj.is_replacement || hotelReplacementObj.replaces_item_id || details?.accommodation_replacement))
                );
                const hasHotelReplacement = Boolean(originalHotelCancelled && hotelReplacementObj);

                const vehicleReplacementObj = details?.rental_replacement || selectedPackageDetails.replacement_vehicle;
                const originalVehicleCancelled = Boolean(
                  (vehicleItem && (vehicleItem.status === 'cancelled' || vehicleItem.status === 'rejected' || vehicleItem.status === 'declined')) ||
                  selectedPackageDetails.all_cancelled_items?.some((c: any) => c.service_type === 'vehicle') ||
                  selectedPackageDetails.cancelled_items?.some((c: any) => c.service_type === 'vehicle') ||
                  (vehicleReplacementObj && (vehicleReplacementObj.is_replacement || vehicleReplacementObj.replaces_item_id || details?.rental_replacement))
                );
                const hasVehicleReplacement = Boolean(originalVehicleCancelled && vehicleReplacementObj);

                const activeHotelSt = hasHotelReplacement
                  ? (hotelReplacementObj?.status || hotelItem?.hotel_reservation_status || hotelItem?.status || 'pending').toLowerCase()
                  : hotelSt;
                const activeHotelConfirmed = hasHotelReplacement
                  ? (activeHotelSt === 'confirmed' || activeHotelSt === 'approved' || activeHotelSt === 'checked_in' || activeHotelSt === 'completed')
                  : (hotelSt === 'confirmed' || hotelSt === 'checked_in' || hotelSt === 'completed');

                const activeVehicleSt = hasVehicleReplacement
                  ? (vehicleReplacementObj?.status || vehicleItem?.rental_request_status || vehicleItem?.status || 'pending').toLowerCase()
                  : vehicleSt;
                const activeVehicleConfirmed = hasVehicleReplacement
                  ? (activeVehicleSt === 'confirmed' || activeVehicleSt === 'approved' || activeVehicleSt === 'contacted' || activeVehicleSt === 'completed')
                  : (vehicleSt === 'confirmed' || vehicleSt === 'approved' || vehicleSt === 'contacted' || vehicleSt === 'completed');

                const isCancelled = overallStatus === 'cancelled' || overallStatus === 'refunded' || selectedPackageDetails.status === 'cancelled' || selectedPackageDetails.status === 'refunded';

                const hotelIsActive = (hotelItem ? hotelItem.status !== 'removed' : Boolean(details?.hotel_included || details?.accommodation));
                const vehicleIsActive = (vehicleItem ? vehicleItem.status !== 'removed' : Boolean(details?.rental_included || details?.rental));

                const guideCompleted = guideSt === 'completed';
                const hotelCompleted = !hotelIsActive || activeHotelSt === 'completed';
                const vehicleCompleted = !vehicleIsActive || activeVehicleSt === 'completed';
                const isAllCompleted = guideCompleted && hotelCompleted && vehicleCompleted;

                const guideConfirmed = guideSt === 'confirmed' || guideSt === 'completed';
                const hotelConfirmed = !hotelIsActive || activeHotelConfirmed;
                const vehicleConfirmed = !vehicleIsActive || activeVehicleConfirmed;

                const isAllConfirmed = guideConfirmed && hotelConfirmed && vehicleConfirmed;
                const isPartiallyConfirmed = !isAllConfirmed && !isAllCompleted && (guideConfirmed || (hotelIsActive && hotelConfirmed) || (vehicleIsActive && vehicleConfirmed));

                const isPkgCompleted = !isCancelled && (overallStatus === 'completed' || Boolean(selectedPackageDetails.is_completed) || isAllCompleted);
                const isActionReq = !isCancelled && !isPkgCompleted && (overallStatus === 'action_required' || Boolean(selectedPackageDetails.has_cancellation));

                const isHost = currentUser?.role === 'guide' || Boolean(selectedPackageDetails.guide_id && currentUser?.id === selectedPackageDetails.guide_id);
                const isTraveler = !isHost;

                const derivedDays = (() => {
                  if (vehicleItem?.rental_days && Number(vehicleItem.rental_days) > 1) {
                    return Number(vehicleItem.rental_days);
                  }
                  if (vehicleObj?.rental_days && Number(vehicleObj.rental_days) > 1) {
                    return Number(vehicleObj.rental_days);
                  }
                  if (details?.duration_days && Number(details.duration_days) > 0) {
                    return Number(details.duration_days);
                  }
                  const titleAndDesc = `${selectedPackageDetails.listing_title || ''} ${(selectedPackageDetails as any).duration || ''} ${details?.duration || ''} ${details?.package_title || ''}`;
                  const m = titleAndDesc.match(/(\d+)\s*(?:d|day)/i);
                  if (m) {
                    return parseInt(m[1], 10);
                  }
                  if (vehicleItem?.rental_days && Number(vehicleItem.rental_days) > 0) {
                    return Number(vehicleItem.rental_days);
                  }
                  if (selectedPackageDetails.days_count && Number(selectedPackageDetails.days_count) > 1) {
                    return Number(selectedPackageDetails.days_count);
                  }
                  return 3;
                })();
                const derivedNights = Number(selectedPackageDetails.nights || details?.duration_nights || Math.max(1, derivedDays - 1));

                const hotelCheckIn = selectedPackageDetails.check_in_date || selectedPackageDetails.booking_date;
                const hotelCheckOut = selectedPackageDetails.check_out_date || (function() {
                  const d = new Date(hotelCheckIn);
                  d.setDate(d.getDate() + derivedNights);
                  return isNaN(d.getTime()) ? hotelCheckIn : d.toISOString().split('T')[0];
                })();

                const vehiclePickup = vehicleItem?.rental_pickup_date || selectedPackageDetails.booking_date;
                const vehicleReturn = vehicleItem?.rental_return_date || (function() {
                  const d = new Date(vehiclePickup);
                  d.setDate(d.getDate() + derivedDays);
                  return isNaN(d.getTime()) ? vehiclePickup : d.toISOString().split('T')[0];
                })();

                const approvalsBadgeText = isCancelled
                  ? (overallStatus === 'refunded' || selectedPackageDetails.status === 'refunded' ? 'REFUNDED' : 'CANCELLED')
                  : (isActionReq
                    ? 'ACTION REQUIRED'
                    : (isPkgCompleted
                      ? 'ALL COMPLETED'
                      : (isAllConfirmed ? 'ALL CONFIRMED' : (isPartiallyConfirmed ? 'PARTIALLY CONFIRMED' : 'PENDING APPROVALS'))));
                const approvalsBadgeBg = isCancelled
                  ? '#FEF2F2'
                  : (isActionReq
                    ? (isDark ? 'rgba(217, 119, 6, 0.25)' : '#FEF3C7')
                    : (isPkgCompleted
                      ? '#DCFCE7'
                      : (isAllConfirmed ? '#ECFDF5' : '#EFF6FF')));
                const approvalsBadgeColor = isCancelled
                  ? '#DC2626'
                  : (isActionReq
                    ? '#D97706'
                    : (isPkgCompleted
                      ? '#10B981'
                      : (isAllConfirmed ? '#059669' : (isPartiallyConfirmed ? '#2563EB' : '#D97706'))));

                const cancelledItem = (selectedPackageDetails.cancelled_items && selectedPackageDetails.cancelled_items.length > 0)
                  ? selectedPackageDetails.cancelled_items[0]
                  : (selectedPackageDetails.provider_statuses?.find((p: any) => p.status === 'cancelled' || p.status === 'rejected') || {
                      id: 0,
                      provider_name: 'Provider',
                      service_name: 'Service',
                      service_type: 'hotel',
                      cancellation_reason: 'Provider unavailable for scheduled date',
                      allocated_amount: 0,
                    });

                const pkgTotalAmt = Number(selectedPackageDetails.total_amount) || 0;
                const cancelledAllocatedAmt = Number(cancelledItem?.allocated_amount) || 0;
                const pkgNewTotalAmt = Math.max(0, pkgTotalAmt - cancelledAllocatedAmt);

                let cancelledScheduleText = formatDate(selectedPackageDetails.booking_date);
                if (cancelledItem?.service_type === 'hotel') {
                  cancelledScheduleText = `Stay: ${formatDate(cancelledItem.hotel_check_in || selectedPackageDetails.check_in_date || selectedPackageDetails.booking_date)} to ${formatDate(cancelledItem.hotel_check_out || selectedPackageDetails.check_out_date || selectedPackageDetails.booking_date)}`;
                } else if (cancelledItem?.service_type === 'vehicle') {
                  cancelledScheduleText = `Pickup: ${formatDate(cancelledItem.rental_pickup_date || selectedPackageDetails.booking_date)} (${cancelledItem.rental_days_count || 1} day(s))`;
                }

                const inclusionsList: any[] = Array.isArray(details?.inclusions) ? details.inclusions : [];
                const tourActivities = inclusionsList.filter((inc: any) => inc.type === 'attraction' || inc.type === 'activity');

                return (
                  <>
                    {/* Cancellation Alert Banner: Only shown for active packages needing action */}
                    {!isCancelled && isActionReq ? (
                      <View style={{ marginBottom: 14, padding: 14, borderRadius: 12, backgroundColor: isDark ? 'rgba(217, 119, 6, 0.15)' : '#FFFBEB', borderWidth: 1.5, borderColor: '#F59E0B' }}>
                        <View style={{ flexDirection: 'row', alignItems: 'flex-start', gap: 10 }}>
                          <Ionicons name="warning" size={22} color="#D97706" style={{ marginTop: 1 }} />
                          <View style={{ flex: 1 }}>
                            <Text style={{ fontSize: 13, fontWeight: '800', color: isDark ? '#FBBF24' : '#92400E' }}>
                              {cancelledItem?.provider_name ? `${cancelledItem.provider_name} has cancelled ${cancelledItem.service_name || 'service'}` : 'A service provider has cancelled'}
                            </Text>
                            <Text style={{ fontSize: 12, color: isDark ? '#FDE68A' : '#78350F', marginTop: 2, lineHeight: 16 }}>
                              Reason: {cancelledItem?.cancellation_reason || 'Provider unavailable for scheduled date'}
                            </Text>
                            <Text style={{ fontSize: 11, fontWeight: '700', color: isDark ? '#FCD34D' : '#B45309', marginTop: 4 }}>
                              Affected Schedule: {cancelledScheduleText}
                            </Text>
                          </View>
                        </View>
                        <View style={{ marginTop: 10, paddingTop: 8, borderTopWidth: 1, borderTopColor: isDark ? 'rgba(245, 158, 11, 0.3)' : '#FDE68A', flexDirection: 'row', alignItems: 'center', gap: 6 }}>
                          <Ionicons name="checkmark-circle" size={14} color="#059669" />
                          <Text style={{ fontSize: 11, fontWeight: '700', color: isDark ? '#6EE7B7' : '#065F46' }}>
                            Good news: Your other confirmed services remain active and unaffected.
                          </Text>
                        </View>
                      </View>
                    ) : null}

                    {/* Alternative Service Options: Only shown for active packages needing action */}
                    {!isCancelled && isActionReq ? (
                      <View style={[styles.detailSectionBox, { backgroundColor: isDark ? '#1E2330' : '#FFFFFF', borderColor: '#F59E0B', borderWidth: 1.5, marginBottom: 14, padding: 14 }]}>
                        <View style={{ marginBottom: 10 }}>
                          <Text style={{ fontSize: 14, fontWeight: '800', color: colors.text }}>
                            {isHost ? 'Manage Disrupted Service' : 'Package Disruption Options'}
                          </Text>
                          <Text style={{ fontSize: 11, color: colors.textSub, marginTop: 2, lineHeight: 15 }}>
                            {isHost ? (
                              'As the package host, select Option A to substitute with an available provider in the same category or Option B to reschedule the service date.'
                            ) : (
                              'A service provider has cancelled. You can select Option C to proceed with remaining services and receive a partial refund, or Option D to cancel the entire package with a 100% full refund.'
                            )}
                          </Text>
                        </View>

                        {/* HOST ONLY: Option A & Option B */}
                        {isHost ? (
                          <>
                        {/* Option A: Replace (FIRST) */}
                        <View style={{ borderRadius: 12, borderWidth: 1.5, borderColor: isDark ? '#334155' : '#E2E8F0', backgroundColor: colors.card, padding: 12, marginBottom: 10 }}>
                          <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }}>
                            <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8, flex: 1, marginRight: 8 }}>
                              <View style={{ width: 26, height: 26, borderRadius: 13, backgroundColor: '#ECFDF5', alignItems: 'center', justifyContent: 'center' }}>
                                <Text style={{ fontSize: 12, fontWeight: '800', color: '#059669' }}>A</Text>
                              </View>
                              <View style={{ flex: 1 }}>
                                <Text style={{ fontSize: 12, fontWeight: '800', color: colors.text }}>Option A: Replace Cancelled Service</Text>
                                <Text style={{ fontSize: 10, color: colors.textSub }}>Substitute with another available provider</Text>
                              </View>
                            </View>
                            <TouchableOpacity
                              style={{ paddingHorizontal: 10, paddingVertical: 6, borderRadius: 8, backgroundColor: colors.primary }}
                              onPress={() => {
                                if (pkgAlternativeTab === 'replace') {
                                  setPkgAlternativeTab('none');
                                } else {
                                  setPkgAlternativeTab('replace');
                                  handleLoadAlternatives(pkgRef, cancelledItem?.id || 0);
                                }
                              }}
                            >
                              <Text style={{ fontSize: 11, fontWeight: '700', color: '#FFFFFF' }}>
                                {pkgAlternativeTab === 'replace' ? 'Hide' : 'Browse'}
                              </Text>
                            </TouchableOpacity>
                          </View>

                          {pkgAlternativeTab === 'replace' ? (
                            <View style={{ marginTop: 10, paddingTop: 10, borderTopWidth: 1, borderTopColor: isDark ? '#334155' : '#E2E8F0' }}>
                              {loadingAlternatives ? (
                                <ActivityIndicator size="small" color={colors.primary} style={{ marginVertical: 12 }} />
                              ) : pkgAlternativesList.length === 0 ? (
                                <Text style={{ fontSize: 11, color: colors.textSub, textAlign: 'center', paddingVertical: 8 }}>
                                  No direct alternatives found. You may also select Option B (Reschedule) or Option C (Continue Without).
                                </Text>
                              ) : (
                                <View style={{ gap: 8 }}>
                                  {pkgAlternativesList.map((alt: any, aIdx: number) => (
                                    <View key={aIdx} style={{ padding: 10, borderRadius: 8, borderWidth: 1, borderColor: isDark ? '#334155' : '#E2E8F0', backgroundColor: isDark ? '#1E2330' : '#F8FAFC', flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }}>
                                      <View style={{ flex: 1, marginRight: 8 }}>
                                        <Text style={{ fontSize: 12, fontWeight: '700', color: colors.text }}>{alt.title || alt.room_name}</Text>
                                        <Text style={{ fontSize: 10, color: colors.textSub }}>{alt.provider_name} • {alt.area || alt.location || 'Cebu'}</Text>
                                        <Text style={{ fontSize: 11, fontWeight: '800', color: '#059669', marginTop: 2 }}>{formatPrice(alt.price)}</Text>
                                      </View>
                                      <TouchableOpacity
                                        style={{ paddingHorizontal: 12, paddingVertical: 6, borderRadius: 6, backgroundColor: colors.primary }}
                                        disabled={isSubmittingPkgOption}
                                        onPress={() => handleSelectReplacement(pkgRef, cancelledItem?.id || 0, alt)}
                                      >
                                        <Text style={{ fontSize: 11, fontWeight: '700', color: '#FFFFFF' }}>Select</Text>
                                      </TouchableOpacity>
                                    </View>
                                  ))}
                                </View>
                              )}
                            </View>
                          ) : null}
                        </View>

                        {/* Option B: Reschedule (SECOND) */}
                        <View style={{ borderRadius: 12, borderWidth: 1.5, borderColor: isDark ? '#334155' : '#E2E8F0', backgroundColor: colors.card, padding: 12, marginBottom: 10 }}>
                          <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }}>
                            <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8, flex: 1, marginRight: 8 }}>
                              <View style={{ width: 26, height: 26, borderRadius: 13, backgroundColor: '#EFF6FF', alignItems: 'center', justifyContent: 'center' }}>
                                <Text style={{ fontSize: 12, fontWeight: '800', color: '#2563EB' }}>B</Text>
                              </View>
                              <View style={{ flex: 1 }}>
                                <Text style={{ fontSize: 12, fontWeight: '800', color: colors.text }}>Option B: Reschedule Cancelled Service</Text>
                                <Text style={{ fontSize: 10, color: colors.textSub }}>Propose an alternative date/schedule</Text>
                              </View>
                            </View>
                            <TouchableOpacity
                              style={{ paddingHorizontal: 10, paddingVertical: 6, borderRadius: 8, borderWidth: 1, borderColor: isDark ? '#475569' : '#CBD5E1', backgroundColor: 'transparent' }}
                              onPress={() => setPkgAlternativeTab(pkgAlternativeTab === 'reschedule' ? 'none' : 'reschedule')}
                            >
                              <Text style={{ fontSize: 11, fontWeight: '700', color: colors.text }}>
                                {pkgAlternativeTab === 'reschedule' ? 'Hide' : 'Reschedule'}
                              </Text>
                            </TouchableOpacity>
                          </View>

                          {pkgAlternativeTab === 'reschedule' ? (
                            <View style={{ marginTop: 10, paddingTop: 10, borderTopWidth: 1, borderTopColor: isDark ? '#334155' : '#E2E8F0', gap: 8 }}>
                              <View>
                                <Text style={{ fontSize: 11, fontWeight: '700', color: colors.textSub, marginBottom: 4 }}>New Date *</Text>
                                <TextInput
                                  style={{ borderWidth: 1, borderColor: isDark ? '#475569' : '#CBD5E1', borderRadius: 8, padding: 8, fontSize: 12, color: colors.text, backgroundColor: isDark ? '#1E2330' : '#FFFFFF' }}
                                  placeholder="YYYY-MM-DD"
                                  placeholderTextColor={colors.textMute}
                                  value={pkgRescheduleDate}
                                  onChangeText={setPkgRescheduleDate}
                                />
                              </View>
                              <View>
                                <Text style={{ fontSize: 11, fontWeight: '700', color: colors.textSub, marginBottom: 4 }}>New Time (Optional)</Text>
                                <TextInput
                                  style={{ borderWidth: 1, borderColor: isDark ? '#475569' : '#CBD5E1', borderRadius: 8, padding: 8, fontSize: 12, color: colors.text, backgroundColor: isDark ? '#1E2330' : '#FFFFFF' }}
                                  placeholder="e.g. 09:00 AM"
                                  placeholderTextColor={colors.textMute}
                                  value={pkgRescheduleTime}
                                  onChangeText={setPkgRescheduleTime}
                                />
                              </View>
                              <View>
                                <Text style={{ fontSize: 11, fontWeight: '700', color: colors.textSub, marginBottom: 4 }}>Notes for Provider (Optional)</Text>
                                <TextInput
                                  style={{ borderWidth: 1, borderColor: isDark ? '#475569' : '#CBD5E1', borderRadius: 8, padding: 8, fontSize: 12, color: colors.text, backgroundColor: isDark ? '#1E2330' : '#FFFFFF' }}
                                  placeholder="e.g. Adjusted check-in coordinated with host"
                                  placeholderTextColor={colors.textMute}
                                  value={pkgRescheduleNotes}
                                  onChangeText={setPkgRescheduleNotes}
                                />
                              </View>
                              <TouchableOpacity
                                style={{ paddingVertical: 10, borderRadius: 8, backgroundColor: '#2563EB', alignItems: 'center', marginTop: 4 }}
                                disabled={isSubmittingPkgOption}
                                onPress={() => handleReschedule(pkgRef, cancelledItem?.id || 0)}
                              >
                                <Text style={{ fontSize: 12, fontWeight: '800', color: '#FFFFFF' }}>Submit Reschedule Request</Text>
                              </TouchableOpacity>
                            </View>
                          ) : null}
                        </View>
                        </>
                      ) : null}

                      {/* TRAVELER ONLY: Option C & Option D */}
                      {isTraveler ? (
                        <>
                        {/* Option C: Continue Without (THIRD) */}
                        <View style={{ borderRadius: 12, borderWidth: 1.5, borderColor: isDark ? '#334155' : '#E2E8F0', backgroundColor: colors.card, padding: 12, marginBottom: 10 }}>
                          <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }}>
                            <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8, flex: 1, marginRight: 8 }}>
                              <View style={{ width: 26, height: 26, borderRadius: 13, backgroundColor: '#F1F5F9', alignItems: 'center', justifyContent: 'center' }}>
                                <Text style={{ fontSize: 12, fontWeight: '800', color: '#475569' }}>C</Text>
                              </View>
                              <View style={{ flex: 1 }}>
                                <Text style={{ fontSize: 12, fontWeight: '800', color: colors.text }}>Option C: Continue Without Service</Text>
                                <Text style={{ fontSize: 10, color: colors.textSub }}>Drop service & receive partial refund</Text>
                              </View>
                            </View>
                            <TouchableOpacity
                              style={{ paddingHorizontal: 10, paddingVertical: 6, borderRadius: 8, borderWidth: 1, borderColor: isDark ? '#475569' : '#CBD5E1', backgroundColor: 'transparent' }}
                              onPress={() => setPkgAlternativeTab(pkgAlternativeTab === 'continue-without' ? 'none' : 'continue-without')}
                            >
                              <Text style={{ fontSize: 11, fontWeight: '700', color: colors.text }}>
                                {pkgAlternativeTab === 'continue-without' ? 'Hide' : 'Review'}
                              </Text>
                            </TouchableOpacity>
                          </View>

                          {pkgAlternativeTab === 'continue-without' ? (
                            <View style={{ marginTop: 10, paddingTop: 10, borderTopWidth: 1, borderTopColor: isDark ? '#334155' : '#E2E8F0' }}>
                              <View style={{ padding: 10, borderRadius: 8, backgroundColor: isDark ? '#1E2330' : '#F8FAFC', marginBottom: 10 }}>
                                <View style={{ flexDirection: 'row', justifyContent: 'space-between', marginBottom: 4 }}>
                                  <Text style={{ fontSize: 11, color: colors.textSub }}>Current Package Total:</Text>
                                  <Text style={{ fontSize: 11, fontWeight: '700', color: colors.text }}>{formatPrice(pkgTotalAmt)}</Text>
                                </View>
                                <View style={{ flexDirection: 'row', justifyContent: 'space-between', marginBottom: 4 }}>
                                  <Text style={{ fontSize: 11, color: '#059669', fontWeight: '700' }}>Refund for Removed Service:</Text>
                                  <Text style={{ fontSize: 11, color: '#059669', fontWeight: '800' }}>- {formatPrice(cancelledAllocatedAmt)}</Text>
                                </View>
                                <View style={{ flexDirection: 'row', justifyContent: 'space-between', paddingTop: 6, borderTopWidth: 1, borderTopColor: isDark ? '#334155' : '#CBD5E1' }}>
                                  <Text style={{ fontSize: 12, fontWeight: '800', color: colors.text }}>New Package Total:</Text>
                                  <Text style={{ fontSize: 12, fontWeight: '800', color: colors.primary }}>{formatPrice(pkgNewTotalAmt)}</Text>
                                </View>
                              </View>
                              <TouchableOpacity
                                style={{ paddingVertical: 10, borderRadius: 8, backgroundColor: colors.primary, alignItems: 'center' }}
                                disabled={isSubmittingPkgOption}
                                onPress={() => handleContinueWithout(pkgRef, cancelledItem?.id || 0, cancelledAllocatedAmt)}
                              >
                                <Text style={{ fontSize: 12, fontWeight: '800', color: '#FFFFFF' }}>Confirm &amp; Continue Package Trip</Text>
                              </TouchableOpacity>
                            </View>
                          ) : null}
                        </View>

                        {/* Option D: Cancel Entire Package (FOURTH) */}
                        <View style={{ borderRadius: 12, borderWidth: 1.5, borderColor: '#FECACA', backgroundColor: isDark ? 'rgba(239, 68, 68, 0.1)' : '#FEF2F2', padding: 12 }}>
                          <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }}>
                            <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8, flex: 1, marginRight: 8 }}>
                              <View style={{ width: 26, height: 26, borderRadius: 13, backgroundColor: '#FEE2E2', alignItems: 'center', justifyContent: 'center' }}>
                                <Text style={{ fontSize: 12, fontWeight: '800', color: '#DC2626' }}>D</Text>
                              </View>
                              <View style={{ flex: 1 }}>
                                <Text style={{ fontSize: 12, fontWeight: '800', color: '#991B1B' }}>Option D: Cancel Entire Package</Text>
                                <Text style={{ fontSize: 10, color: '#B91C1C' }}>Cancel all services & receive 100% full refund</Text>
                              </View>
                            </View>
                            <TouchableOpacity
                              style={{ paddingHorizontal: 10, paddingVertical: 6, borderRadius: 8, borderWidth: 1, borderColor: '#FECACA', backgroundColor: 'transparent' }}
                              onPress={() => setPkgAlternativeTab(pkgAlternativeTab === 'cancel-full' ? 'none' : 'cancel-full')}
                            >
                              <Text style={{ fontSize: 11, fontWeight: '700', color: '#DC2626' }}>
                                {pkgAlternativeTab === 'cancel-full' ? 'Hide' : 'Cancel Trip'}
                              </Text>
                            </TouchableOpacity>
                          </View>

                          {pkgAlternativeTab === 'cancel-full' ? (
                            <View style={{ marginTop: 10, paddingTop: 10, borderTopWidth: 1, borderTopColor: '#FECACA' }}>
                              <View style={{ padding: 10, borderRadius: 8, backgroundColor: isDark ? '#1E2330' : '#FFFFFF', borderWidth: 1, borderColor: '#FECACA', marginBottom: 10 }}>
                                <Text style={{ fontSize: 11, fontWeight: '700', color: '#991B1B', marginBottom: 4 }}>Important Consequences:</Text>
                                <Text style={{ fontSize: 10, color: isDark ? '#FCA5A5' : '#7F1D1D', lineHeight: 14 }}>
                                  • All remaining active reservations will be terminated.{'\n'}
                                  • A 100% full refund of {formatPrice(pkgTotalAmt)} will be credited to your account.{'\n'}
                                  • All participating providers will be released from their bookings.
                                </Text>
                              </View>
                              <TextInput
                                style={{ borderWidth: 1, borderColor: '#FECACA', borderRadius: 8, padding: 8, fontSize: 12, color: colors.text, backgroundColor: isDark ? '#1E2330' : '#FFFFFF', marginBottom: 8 }}
                                placeholder="Reason for cancellation (optional)"
                                placeholderTextColor={colors.textMute}
                                value={pkgCancelReason}
                                onChangeText={setPkgCancelReason}
                              />
                              <TouchableOpacity
                                style={{ paddingVertical: 10, borderRadius: 8, backgroundColor: '#DC2626', alignItems: 'center' }}
                                disabled={isSubmittingPkgOption}
                                onPress={() => handleCancelFullPackage(pkgRef, pkgTotalAmt)}
                              >
                                <Text style={{ fontSize: 12, fontWeight: '800', color: '#FFFFFF' }}>Confirm Full Package Cancellation</Text>
                              </TouchableOpacity>
                            </View>
                          ) : null}
                        </View>
                        </>
                      ) : null}
                      </View>
                    ) : null}

                    {/* Provider Booking Approvals Status Card */}
                    <View style={[styles.detailSectionBox, { backgroundColor: isDark ? '#1E2330' : '#F8FAFC', borderColor: colors.border }]}>
                      <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginBottom: 8 }}>
                        <Text style={[styles.detailSectionTitle, { color: colors.text, marginBottom: 0 }]}>Provider Booking Approvals</Text>
                        <View style={{ paddingHorizontal: 8, paddingVertical: 3, borderRadius: 6, backgroundColor: approvalsBadgeBg }}>
                          <Text style={{ fontSize: 11, fontWeight: '800', color: approvalsBadgeColor }}>
                            {approvalsBadgeText}
                          </Text>
                        </View>
                      </View>
                      <View style={{ gap: 8, marginTop: 4 }}>
                        {/* Guide */}
                        <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }}>
                          <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8 }}>
                            <Text style={{ fontSize: 15 }}>🧑‍💼</Text>
                            <View>
                              <Text style={{ fontSize: 13, fontWeight: '600', color: colors.text }}>Tour Guide Host</Text>
                              <Text style={{ fontSize: 11, color: colors.textSub }}>{guideName}</Text>
                            </View>
                          </View>
                          <Text style={{ fontSize: 12, fontWeight: '700', color: guideConfirmed ? '#059669' : (guideSt === 'rescheduled' ? '#2563EB' : (guideSt === 'removed' ? '#64748B' : (guideSt === 'cancelled' || guideSt === 'rejected' || guideSt === 'declined' ? '#DC2626' : '#D97706'))) }}>
                            {guideSt === 'completed' ? '\u2713 Completed' : (guideSt === 'confirmed' ? '\u2713 Confirmed' : (guideSt === 'rescheduled' ? '↺ Rescheduled' : (guideSt === 'removed' ? '— Removed' : (guideSt === 'cancelled' ? '\u2715 Cancelled' : (guideSt === 'rejected' || guideSt === 'declined' ? '\u2715 Declined' : '⏳ Pending')))))}
                          </Text>
                        </View>

                        {/* Hotel */}
                        {hasHotel ? (
                          <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }}>
                            <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8, flex: 1, marginRight: 8 }}>
                              <Text style={{ fontSize: 15 }}>🏨</Text>
                              <View style={{ flex: 1 }}>
                                <Text style={{ fontSize: 13, fontWeight: '600', color: colors.text }}>
                                  {hasHotelReplacement ? 'Hotel Accommodation (Replacement)' : 'Hotel Accommodation'}
                                </Text>
                                <Text style={{ fontSize: 11, color: colors.textSub }} numberOfLines={1}>
                                  {hasHotelReplacement
                                    ? `${hotelReplacementObj?.hotel_name || hotelItem?.service_name || 'Replacement Hotel'} \u2022 ${hotelReplacementObj?.provider_name || hotelItem?.provider_name || 'Hotel Partner'}`
                                    : (hotelObj?.name || hotelObj?.hotel_name || hotelItem?.service_name || 'Partner Hotel')}
                                </Text>
                              </View>
                            </View>
                            <Text style={{ fontSize: 12, fontWeight: '700', color: activeHotelConfirmed ? '#059669' : (activeHotelSt === 'rescheduled' ? '#2563EB' : (activeHotelSt === 'removed' ? '#64748B' : (activeHotelSt === 'cancelled' || activeHotelSt === 'rejected' || activeHotelSt === 'declined' ? '#DC2626' : '#D97706'))) }}>
                              {activeHotelSt === 'checked_in' ? '\u2713 Checked In' : (activeHotelSt === 'completed' ? '\u2713 Completed' : (activeHotelConfirmed ? '\u2713 Confirmed' : (activeHotelSt === 'rescheduled' ? '↺ Rescheduled' : (activeHotelSt === 'removed' ? '— Removed' : (activeHotelSt === 'cancelled' || activeHotelSt === 'rejected' || activeHotelSt === 'declined' ? '\u2715 Declined' : '⏳ Pending')))))}
                            </Text>
                          </View>
                        ) : null}

                        {/* Vehicle Rental */}
                        {hasVehicle ? (
                          <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }}>
                            <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8, flex: 1, marginRight: 8 }}>
                              <Text style={{ fontSize: 15 }}>🛵</Text>
                              <View style={{ flex: 1 }}>
                                <Text style={{ fontSize: 13, fontWeight: '600', color: colors.text }}>
                                  {hasVehicleReplacement ? 'Vehicle Transportation (Replacement)' : 'Vehicle Transportation'}
                                </Text>
                                <Text style={{ fontSize: 11, color: colors.textSub }} numberOfLines={1}>
                                  {hasVehicleReplacement
                                    ? `${vehicleReplacementObj?.vehicle_name || vehicleReplacementObj?.model || vehicleItem?.service_name || 'Replacement Vehicle'} \u2022 ${vehicleReplacementObj?.provider_name || vehicleItem?.provider_name || 'Rental Partner'}`
                                    : (vehicleObj?.name || vehicleObj?.vehicle_name || vehicleItem?.service_name || 'Rental Vehicle')}
                                </Text>
                              </View>
                            </View>
                            <Text style={{ fontSize: 12, fontWeight: '700', color: activeVehicleConfirmed ? '#059669' : (activeVehicleSt === 'rescheduled' ? '#2563EB' : (activeVehicleSt === 'removed' ? '#64748B' : (activeVehicleSt === 'cancelled' || activeVehicleSt === 'rejected' || activeVehicleSt === 'declined' ? '#DC2626' : '#D97706'))) }}>
                              {activeVehicleSt === 'contacted' ? '\u2713 Contacted' : (activeVehicleSt === 'completed' ? '\u2713 Completed' : (activeVehicleConfirmed ? '\u2713 Confirmed' : (activeVehicleSt === 'rescheduled' ? '↺ Rescheduled' : (activeVehicleSt === 'removed' ? '— Removed' : (activeVehicleSt === 'cancelled' || activeVehicleSt === 'rejected' || activeVehicleSt === 'declined' ? '\u2715 Declined' : '⏳ Pending')))))}
                            </Text>
                          </View>
                        ) : null}
                      </View>
                    </View>

                  {/* Guide Host Card */}
                  <View style={[styles.detailSectionBox, { backgroundColor: isDark ? '#1E2330' : '#F8FAFC', borderColor: colors.border, marginTop: 12 }]}>
                    <Text style={[styles.detailSectionTitle, { color: colors.text }]}>Assigned Tour Guide Host</Text>
                    <View style={{ flexDirection: 'row', alignItems: 'center', marginTop: 10 }}>
                      {selectedPackageDetails.guide_avatar ? (
                        <Image source={{ uri: resolveImage(selectedPackageDetails.guide_avatar) }} style={{ width: 44, height: 44, borderRadius: 22 }} />
                      ) : (
                        <View style={{ width: 44, height: 44, borderRadius: 22, backgroundColor: colors.card, alignItems: 'center', justifyContent: 'center' }}>
                          <Ionicons name="person" size={22} color={colors.primary} />
                        </View>
                      )}
                      <View style={{ flex: 1, marginLeft: 12 }}>
                        <Text style={{ fontSize: 15, fontWeight: '800', color: colors.text }}>
                          {selectedPackageDetails.guide_name || selectedPackageDetails.package_details?.guide?.name || 'Marc Repuesto'}
                        </Text>
                        <Text style={{ fontSize: 12, color: colors.textSub }}>{'Licensed Regional Tour Guide \u2022 DOT Accredited'}</Text>
                      </View>
                    </View>
                  </View>

                  {/* Included Services Overview */}
                  <View style={[styles.detailSectionBox, { backgroundColor: isDark ? '#1E2330' : '#F8FAFC', borderColor: colors.border, marginTop: 12 }]}>
                    <Text style={[styles.detailSectionTitle, { color: colors.text }]}>Selected Package Services</Text>

                    {/* Activities & Attractions */}
                    <View style={{ marginTop: 10 }}>
                      <Text style={{ fontSize: 12, fontWeight: '800', color: colors.primary, textTransform: 'uppercase' }}>Tours &amp; Attractions Included</Text>
                      <View style={{ marginTop: 8, gap: 8 }}>
                        {tourActivities.length > 0 ? (
                          tourActivities.map((act: any, idx: number) => (
                            <View key={idx} style={{ flexDirection: 'row', alignItems: 'flex-start', gap: 8 }}>
                              <Ionicons name="checkmark-circle" size={16} color="#10B981" style={{ marginTop: 1 }} />
                              <View style={{ flex: 1 }}>
                                <Text style={{ fontSize: 13, color: colors.text, fontWeight: '700' }}>{act.title}</Text>
                                {act.subtitle ? (
                                  <Text style={{ fontSize: 11, color: colors.textSub, marginTop: 1 }}>{act.subtitle}</Text>
                                ) : null}
                              </View>
                            </View>
                          ))
                        ) : (
                          <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8 }}>
                            <Ionicons name="checkmark-circle" size={16} color="#10B981" />
                            <Text style={{ fontSize: 13, color: colors.text, fontWeight: '600' }}>
                              {selectedPackageDetails.listing_title}
                            </Text>
                          </View>
                        )}
                      </View>
                    </View>

                    {/* Accommodation */}
                    <View style={{ marginTop: 12, paddingTop: 10, borderTopWidth: 1, borderColor: colors.border }}>
                      <Text style={{ fontSize: 12, fontWeight: '800', color: hasHotel ? '#D97706' : colors.textMute, textTransform: 'uppercase' }}>Hotel Stay Accommodation</Text>
                      {hasHotel ? (
                        <>
                          <Text style={{ fontSize: 14, fontWeight: '700', color: colors.text, marginTop: 4 }}>
                            {hotelObj?.name || hotelObj?.hotel_name || selectedPackageDetails.room_name || 'Partner Hotel'}
                          </Text>
                          <Text style={{ fontSize: 12, color: colors.textSub, marginTop: 2 }}>
                            {`${hotelObj?.roomType || hotelObj?.room_name || 'Selected Room'} \u2022 ${derivedNights} Night${((derivedNights) > 1) ? 's' : ''}`}
                          </Text>
                          <Text style={{ fontSize: 11, color: colors.textMute, marginTop: 2 }}>
                            {`Check-in: ${formatDate(hotelCheckIn)} from 02:00 PM \u2022 Check-out: ${formatDate(hotelCheckOut)} at 12:00 PM`}
                          </Text>
                          {originalHotelCancelled ? (
                            <View style={{ marginTop: 6, flexDirection: 'row', alignItems: 'center', gap: 6, flexWrap: 'wrap' }}>
                              <Text style={{ fontSize: 11, fontWeight: '700', color: '#DC2626' }}>✕ Cancelled by original hotel provider</Text>
                            </View>
                          ) : null}
                        </>
                      ) : (
                        <Text style={{ fontSize: 12, color: colors.textSub, marginTop: 4, fontStyle: 'italic' }}>
                          No hotel accommodation bundled in this package.
                        </Text>
                      )}
                    </View>

                    {/* Hotel Stay Accommodation Replacement */}
                    {hasHotelReplacement && hotelReplacementObj ? (
                      <View style={{ marginTop: 12, paddingTop: 10, borderTopWidth: 1, borderColor: colors.border }}>
                        <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }}>
                          <Text style={{ fontSize: 12, fontWeight: '800', color: '#D97706', textTransform: 'uppercase' }}>
                            Hotel Stay Accommodation Replacement
                          </Text>
                          <View style={{ paddingHorizontal: 6, paddingVertical: 2, borderRadius: 4, backgroundColor: activeHotelConfirmed ? '#DCFCE7' : ((activeHotelSt === 'cancelled' || activeHotelSt === 'rejected' || activeHotelSt === 'declined') ? '#FEF2F2' : '#FEF3C7') }}>
                            <Text style={{ fontSize: 10, fontWeight: '800', color: activeHotelConfirmed ? '#15803D' : ((activeHotelSt === 'cancelled' || activeHotelSt === 'rejected' || activeHotelSt === 'declined') ? '#DC2626' : '#B45309') }}>
                              {activeHotelConfirmed ? '✓ Confirmed' : ((activeHotelSt === 'cancelled' || activeHotelSt === 'rejected' || activeHotelSt === 'declined') ? '✕ Declined' : '⏳ Pending')}
                            </Text>
                          </View>
                        </View>
                        <Text style={{ fontSize: 14, fontWeight: '700', color: colors.text, marginTop: 4 }}>
                          {hotelReplacementObj.hotel_name || hotelReplacementObj.service_name || 'Replacement Partner Hotel'}
                        </Text>
                        <Text style={{ fontSize: 12, color: colors.textSub, marginTop: 2 }}>
                          {`${hotelReplacementObj.room_name || hotelReplacementObj.room_type || 'Selected Room'} (${hotelReplacementObj.room_type || 'Standard'}) \u2022 ${hotelReplacementObj.nights || selectedPackageDetails.nights || 1} Night${((hotelReplacementObj.nights || selectedPackageDetails.nights || 1) > 1) ? 's' : ''}`}
                        </Text>
                        <Text style={{ fontSize: 12, fontWeight: '700', color: colors.primary, marginTop: 2 }}>
                          {`Pricing: ₱${Number(hotelReplacementObj.pricing || hotelReplacementObj.allocated_amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${hotelReplacementObj.price_per_night ? `(₱${Number(hotelReplacementObj.price_per_night).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} / night)` : ''}`}
                        </Text>
                        <Text style={{ fontSize: 11, color: colors.textMute, marginTop: 2 }}>
                          {`Reservation Dates: ${formatDate(hotelReplacementObj.check_in_date || selectedPackageDetails.check_in_date || selectedPackageDetails.booking_date)} to ${formatDate(hotelReplacementObj.check_out_date || selectedPackageDetails.check_out_date || selectedPackageDetails.booking_date)}`}
                        </Text>
                        <Text style={{ fontSize: 11, color: colors.textSub, marginTop: 2 }}>
                          {`Provider: ${hotelReplacementObj.provider_name || 'Hotel Partner'}${hotelReplacementObj.provider_phone ? ` \u2022 ${hotelReplacementObj.provider_phone}` : ''}${hotelReplacementObj.provider_email ? ` \u2022 ${hotelReplacementObj.provider_email}` : ''}`}
                        </Text>
                      </View>
                    ) : null}

                    {/* Transport */}
                    <View style={{ marginTop: 12, paddingTop: 10, borderTopWidth: 1, borderColor: colors.border }}>
                      <Text style={{ fontSize: 12, fontWeight: '800', color: hasVehicle ? '#2563EB' : colors.textMute, textTransform: 'uppercase' }}>Rental Transportation</Text>
                      {hasVehicle ? (
                        <>
                          <Text style={{ fontSize: 14, fontWeight: '700', color: colors.text, marginTop: 4 }}>
                            {vehicleObj?.name || vehicleObj?.vehicle_name || 'Partner Rental Vehicle'}
                          </Text>
                          <Text style={{ fontSize: 12, color: colors.textSub, marginTop: 2 }}>
                            {`${derivedDays} Full Day${derivedDays > 1 ? 's' : ''} \u2022 ${vehicleObj?.vehicle_type || 'Vehicle'} \u2022 Handover Included`}
                          </Text>
                          <Text style={{ fontSize: 11, color: colors.textMute, marginTop: 2 }}>
                            {`Pickup: ${formatDate(vehiclePickup)} \u2022 Return: ${formatDate(vehicleReturn)}`}
                          </Text>
                          {originalVehicleCancelled ? (
                            <View style={{ marginTop: 6, flexDirection: 'row', alignItems: 'center', gap: 6, flexWrap: 'wrap' }}>
                              <Text style={{ fontSize: 11, fontWeight: '700', color: '#DC2626' }}>✕ Cancelled by original rental provider</Text>
                              {Boolean(vehicleItem?.refund_amount || vehicleItem?.refund_status === 'refunded' || vehicleObj?.refund_amount || vehicleObj?.refund_status === 'refunded' || vehicleItem?.rental_refund_amount) ? (
                                <View style={{ paddingHorizontal: 6, paddingVertical: 1, borderRadius: 4, backgroundColor: '#DCFCE7' }}>
                                  <Text style={{ fontSize: 10, fontWeight: '800', color: '#15803D' }}>
                                    {`✓ Refunded: ₱${Number(vehicleItem?.refund_amount || vehicleItem?.rental_refund_amount || vehicleObj?.refund_amount || vehicleItem?.allocated_amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`}
                                  </Text>
                                </View>
                              ) : null}
                            </View>
                          ) : null}
                        </>
                      ) : (
                        <Text style={{ fontSize: 12, color: colors.textSub, marginTop: 4, fontStyle: 'italic' }}>
                          No rental vehicle bundled in this package.
                        </Text>
                      )}
                    </View>

                    {/* Rental Transportation Replacement */}
                    {hasVehicleReplacement && vehicleReplacementObj ? (() => {
                      const repRentalDays = Math.max(1, Number(vehicleReplacementObj.rental_days || vehicleReplacementObj.duration_days || derivedDays));
                      const repPickup = vehicleReplacementObj.pickup_date || selectedPackageDetails.booking_date;
                      const repReturn = vehicleReplacementObj.return_date || (function() {
                        const d = new Date(repPickup);
                        d.setDate(d.getDate() + repRentalDays);
                        return isNaN(d.getTime()) ? repPickup : d.toISOString().split('T')[0];
                      })();

                      return (
                        <View style={{ marginTop: 12, paddingTop: 10, borderTopWidth: 1, borderColor: colors.border }}>
                          <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }}>
                            <Text style={{ fontSize: 12, fontWeight: '800', color: '#2563EB', textTransform: 'uppercase' }}>
                              Rental Transportation Replacement
                            </Text>
                            <View style={{ paddingHorizontal: 6, paddingVertical: 2, borderRadius: 4, backgroundColor: activeVehicleConfirmed ? '#DCFCE7' : ((activeVehicleSt === 'cancelled' || activeVehicleSt === 'rejected' || activeVehicleSt === 'declined') ? '#FEF2F2' : '#FEF3C7') }}>
                              <Text style={{ fontSize: 10, fontWeight: '800', color: activeVehicleConfirmed ? '#15803D' : ((activeVehicleSt === 'cancelled' || activeVehicleSt === 'rejected' || activeVehicleSt === 'declined') ? '#DC2626' : '#B45309') }}>
                                {activeVehicleConfirmed ? '✓ Confirmed' : ((activeVehicleSt === 'cancelled' || activeVehicleSt === 'rejected' || activeVehicleSt === 'declined') ? '✕ Declined' : '⏳ Pending')}
                              </Text>
                            </View>
                          </View>
                          <Text style={{ fontSize: 14, fontWeight: '700', color: colors.text, marginTop: 4 }}>
                            {vehicleReplacementObj.vehicle_name || vehicleReplacementObj.model || vehicleReplacementObj.service_name || 'Replacement Vehicle'}
                          </Text>
                          <Text style={{ fontSize: 12, color: colors.textSub, marginTop: 2 }}>
                            {`${repRentalDays} Full Day${repRentalDays > 1 ? 's' : ''} \u2022 ${vehicleReplacementObj.model || vehicleReplacementObj.vehicle_name || 'Vehicle'} (${vehicleReplacementObj.vehicle_type || 'Vehicle'}) \u2022 Capacity: ${vehicleReplacementObj.capacity || 4} Passengers`}
                          </Text>
                          <Text style={{ fontSize: 12, color: colors.textSub, marginTop: 2 }}>
                            {`Pickup Location: ${vehicleReplacementObj.location || 'Designated Location'}`}
                          </Text>
                          <Text style={{ fontSize: 11, color: colors.textMute, marginTop: 2 }}>
                            {`Pickup: ${formatDate(repPickup)} at ${vehicleReplacementObj.pickup_time || '09:00 AM'}`}
                          </Text>
                          <Text style={{ fontSize: 11, color: colors.textMute, marginTop: 2 }}>
                            {`Return: ${formatDate(repReturn)} at ${vehicleReplacementObj.return_time || '05:00 PM'}`}
                          </Text>
                          <Text style={{ fontSize: 11, color: colors.textSub, marginTop: 2 }}>
                            {`Replacement Rental Provider: ${vehicleReplacementObj.provider_name || 'Rental Partner'}${vehicleReplacementObj.provider_phone ? ` \u2022 ${vehicleReplacementObj.provider_phone}` : ''}${vehicleReplacementObj.provider_email ? ` \u2022 ${vehicleReplacementObj.provider_email}` : ''}`}
                          </Text>
                          <Text style={{ fontSize: 12, fontWeight: '700', color: colors.primary, marginTop: 2 }}>
                            {`Pricing: ₱${Number(vehicleReplacementObj.pricing || vehicleReplacementObj.allocated_amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`}
                          </Text>
                        </View>
                      );
                    })() : null}
                  </View>

                  {/* Day-by-Day Itinerary */}
                  <View style={[styles.detailSectionBox, { backgroundColor: isDark ? '#1E2330' : '#F8FAFC', borderColor: colors.border, marginTop: 12 }]}>
                    <Text style={[styles.detailSectionTitle, { color: colors.text }]}>Day-by-Day Itinerary</Text>
                    <View style={{ marginTop: 8, gap: 12 }}>
                      {Array.isArray(details?.itinerary) && details.itinerary.length > 0 ? (
                        details.itinerary.map((dayItem: any, dayIdx: number) => {
                          const dayNum = dayItem.day ?? (dayIdx + 1);
                          const dayTitle = dayItem.title ?? `Day ${dayNum}`;
                          const dayDesc = dayItem.description ?? '';
                          const scheduleList = Array.isArray(dayItem.schedule) ? dayItem.schedule : [];
                          const highlightsList = Array.isArray(dayItem.highlights) ? dayItem.highlights : [];

                          return (
                            <View key={dayIdx} style={{ paddingBottom: dayIdx < details.itinerary.length - 1 ? 10 : 0, borderBottomWidth: dayIdx < details.itinerary.length - 1 ? 1 : 0, borderBottomColor: colors.border }}>
                              <Text style={{ fontSize: 13, fontWeight: '800', color: colors.text }}>{dayTitle}</Text>
                              {dayDesc ? (
                                <Text style={{ fontSize: 12, color: colors.textSub, marginTop: 2 }}>{dayDesc}</Text>
                              ) : null}

                              {scheduleList.length > 0 ? (
                                <View style={{ marginTop: 6, gap: 4 }}>
                                  {scheduleList.map((slot: any, sIdx: number) => {
                                    const timeStr = typeof slot === 'string' ? '' : (slot.time || '');
                                    const actStr = typeof slot === 'string' ? slot : (slot.activity || slot.title || '');
                                    return (
                                      <Text key={sIdx} style={{ fontSize: 12, color: colors.textSub }}>
                                        {timeStr ? <Text style={{ fontWeight: '700', color: colors.text }}>{timeStr}: </Text> : null}
                                        {actStr}
                                      </Text>
                                    );
                                  })}
                                </View>
                              ) : null}

                              {highlightsList.length > 0 ? (
                                <View style={{ marginTop: 6, gap: 3 }}>
                                  {highlightsList.map((hl: string, hIdx: number) => (
                                    <View key={hIdx} style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
                                      <Ionicons name="sparkles-outline" size={12} color="#10B981" />
                                      <Text style={{ fontSize: 11, color: colors.textMute }}>{hl}</Text>
                                    </View>
                                  ))}
                                </View>
                              ) : null}
                            </View>
                          );
                        })
                      ) : (
                        <Text style={{ fontSize: 12, color: colors.textSub, fontStyle: 'italic' }}>
                          Guided daily schedule coordinated with your host upon arrival.
                        </Text>
                      )}
                    </View>
                  </View>

                  {/* Meeting Point & Jump-off */}
                  <View style={[styles.detailSectionBox, { backgroundColor: isDark ? '#1E2330' : '#F8FAFC', borderColor: colors.border, marginTop: 12 }]}>
                    <Text style={[styles.detailSectionTitle, { color: colors.text }]}>Meeting &amp; Departure Point</Text>
                    <Text style={{ fontSize: 13, fontWeight: '700', color: colors.text, marginTop: 4 }}>
                      {details?.meeting_point || selectedPackageDetails.address || selectedPackageDetails.area || 'Designated Meeting Point'}
                    </Text>
                    <Text style={{ fontSize: 12, color: colors.textSub, marginTop: 2 }}>
                      Your guide will meet you at the designated location or coordinate directly with you upon arrival.
                    </Text>
                  </View>

                  {/* Action Buttons */}
                  <View style={{ marginTop: 18, gap: 10 }}>
                    {!isCancelled && isPkgCompleted && (selectedPackageDetails.can_review || overallStatus === 'completed') ? (
                      <TouchableOpacity
                        style={[
                          styles.attractionActionBtn,
                          {
                            borderColor: isDark ? '#EAB308' : '#D97706',
                            backgroundColor: isDark ? '#713F1222' : '#FEFCE8',
                            paddingVertical: 12,
                          },
                        ]}
                        onPress={() => {
                          const item = selectedPackageDetails;
                          setSelectedPackageDetails(null);
                          router.push({ pathname: '/review/[id]', params: { id: String(item.listing_id), title: item.listing_title } });
                        }}
                      >
                        <Ionicons name="star-outline" size={16} color={isDark ? '#FACC15' : '#D97706'} />
                        <Text style={[styles.attractionActionText, { color: isDark ? '#FACC15' : '#D97706', fontWeight: '700' }]}>Leave Review</Text>
                      </TouchableOpacity>
                    ) : null}

                    {selectedPackageDetails.dispute_status || selectedPackageDetails.dispute_id ? (
                      <TouchableOpacity
                        style={[
                          styles.attractionActionBtn,
                          {
                            borderColor: isDark ? '#475569' : '#94A3B8',
                            backgroundColor: isDark ? '#33415525' : '#F1F5F9',
                            paddingVertical: 12,
                          },
                        ]}
                        onPress={() => {
                          const item = selectedPackageDetails;
                          setSelectedPackageDetails(null);
                          router.push({ pathname: '/report/[id]', params: { id: String(item.id), title: item.listing_title } });
                        }}
                      >
                        <Ionicons name="document-text" size={16} color={isDark ? '#94A3B8' : '#475569'} />
                        <Text style={[styles.attractionActionText, { color: isDark ? '#94A3B8' : '#475569', fontWeight: '700' }]}>View Report</Text>
                      </TouchableOpacity>
                    ) : (selectedPackageDetails.is_completed || selectedPackageDetails.status === 'completed' || selectedPackageDetails.package_overall_status === 'completed' || selectedPackageDetails.can_report) ? (
                      <TouchableOpacity
                        style={[styles.attractionActionBtn, { borderColor: '#EF4444', backgroundColor: isDark ? '#1E2330' : '#F8FAFC', paddingVertical: 12 }]}
                        onPress={() => {
                          const item = selectedPackageDetails;
                          setSelectedPackageDetails(null);
                          router.push({ pathname: '/report/[id]', params: { id: String(item.id), title: item.listing_title } });
                        }}
                      >
                        <Ionicons name="flag-outline" size={16} color="#EF4444" />
                        <Text style={[styles.attractionActionText, { color: '#EF4444' }]}>Report Issue</Text>
                      </TouchableOpacity>
                    ) : null}

                    <TouchableOpacity
                      style={[styles.attractionActionBtn, { borderColor: colors.primary, backgroundColor: colors.primary, paddingVertical: 12 }]}
                      onPress={() => {
                        const item = selectedPackageDetails;
                        setSelectedPackageDetails(null);
                        setSelectedPackageReceipt(item);
                      }}
                    >
                      <Ionicons name="receipt-outline" size={16} color="#FFFFFF" />
                      <Text style={[styles.attractionActionText, { color: '#FFFFFF' }]}>View Payment Receipt</Text>
                    </TouchableOpacity>

                    {/* Cancel Booking Button: Automatically hidden once all providers confirm */}
                    {!isCancelled && !isPkgCompleted && !isAllCompleted && overallStatus !== 'completed' && !isAllConfirmed && overallStatus !== 'confirmed' ? (
                      <TouchableOpacity
                        style={[styles.cancelModalBtn, { borderColor: '#EF4444', backgroundColor: isDark ? '#7F1D1D22' : '#FEF2F2', paddingVertical: 12 }]}
                        disabled={isSubmittingPkgOption}
                        onPress={() => handleCancelFullPackage(pkgRef, pkgTotalAmt)}
                      >
                        {isSubmittingPkgOption ? (
                          <ActivityIndicator size="small" color="#EF4444" />
                        ) : (
                          <>
                            <Ionicons name="close-circle-outline" size={16} color="#EF4444" />
                            <Text style={{ color: '#EF4444', fontWeight: '800' }}>Cancel Booking</Text>
                          </>
                        )}
                      </TouchableOpacity>
                    ) : null}

                    <TouchableOpacity
                      style={[styles.cancelModalBtn, { borderColor: colors.border }]}
                      onPress={() => setSelectedPackageDetails(null)}
                    >
                      <Text style={{ color: colors.text, fontWeight: '700' }}>Close Details</Text>
                    </TouchableOpacity>
                  </View>
                </>
              );
            })() : null}
            </ScrollView>
          </View>
        </View>
      </Modal>

      {/* ── Dedicated Payment Receipt Modal ── */}
      <Modal
        visible={Boolean(selectedPackageReceipt)}
        transparent
        animationType="slide"
        onRequestClose={() => setSelectedPackageReceipt(null)}
      >
        <View style={styles.modalBackdrop}>
          <View style={[styles.modalCard, { backgroundColor: colors.card, borderColor: colors.border }]}>
            <View style={[styles.modalHeader, { borderBottomColor: colors.border }]}>
              <View style={{ flex: 1 }}>
                <Text style={[styles.modalTitle, { color: colors.text }]}>Official Payment Receipt</Text>
                <Text style={[styles.modalSubtitle, { color: colors.textSub }]}>
                  Booking Ref: {selectedPackageReceipt?.package_booking_ref || ('BK-' + selectedPackageReceipt?.id)}
                </Text>
              </View>
              <TouchableOpacity onPress={() => setSelectedPackageReceipt(null)} style={styles.modalCloseBtn} activeOpacity={0.7}>
                <Ionicons name="close" size={24} color={colors.textSub} />
              </TouchableOpacity>
            </View>

            <ScrollView showsVerticalScrollIndicator={false} contentContainerStyle={{ padding: 18, paddingBottom: 24 }}>
              {selectedPackageReceipt ? (() => {
                const details = selectedPackageReceipt.package_details;
                const basePrice = details?.package_base_price ?? (selectedPackageReceipt.total_amount > 1000 ? Math.round((selectedPackageReceipt.total_amount - 250) / 1.05) : selectedPackageReceipt.total_amount);
                const taxes = details?.taxes ?? Math.round(basePrice * 0.05);
                const fees = details?.environmental_fees ?? 250;
                const discount = details?.voucher_discount ?? 0;
                const voucherCode = details?.voucher_code ?? '';
                const overallStatus = (selectedPackageReceipt.package_overall_status || selectedPackageReceipt.status || 'pending').toLowerCase();
                const isCompleted = overallStatus === 'completed';
                const isConfirmed = overallStatus === 'confirmed';
                const isCancelled = overallStatus === 'cancelled' || overallStatus === 'refunded';

                const bannerBg = (isCompleted || isConfirmed)
                  ? (isDark ? '#064E3B33' : '#ECFDF5')
                  : isCancelled
                  ? (isDark ? '#7F1D1D22' : '#FEF2F2')
                  : (isDark ? '#78350F22' : '#FFFBEB');

                const bannerBorder = (isCompleted || isConfirmed)
                  ? (isDark ? '#05966955' : '#A7F3D0')
                  : isCancelled
                  ? (isDark ? '#EF444455' : '#FECACA')
                  : '#F59E0B66';

                const bannerIcon = isCompleted
                  ? 'checkmark-done-circle'
                  : isConfirmed
                  ? 'checkmark-circle'
                  : isCancelled
                  ? 'close-circle'
                  : 'time-outline';

                const bannerIconColor = (isCompleted || isConfirmed)
                  ? '#059669'
                  : isCancelled
                  ? '#DC2626'
                  : '#D97706';

                const bannerTitleColor = (isCompleted || isConfirmed)
                  ? (isDark ? '#34D399' : '#065F46')
                  : isCancelled
                  ? (isDark ? '#F87171' : '#991B1B')
                  : (isDark ? '#FBBF24' : '#B45309');

                const bannerSubColor = (isCompleted || isConfirmed)
                  ? (isDark ? '#A7F3D0' : '#047857')
                  : isCancelled
                  ? (isDark ? '#FCA5A5' : '#B91C1C')
                  : (isDark ? '#FDE68A' : '#92400E');

                const bannerTitle = isCompleted
                  ? 'PACKAGE TRIP COMPLETED'
                  : isConfirmed
                  ? 'PAYMENT SETTLED & ALL CONFIRMED'
                  : isCancelled
                  ? 'PACKAGE BOOKING CANCELLED'
                  : 'PAYMENT RECEIVED \u2022 AWAITING PROVIDERS';

                const bannerSubtitle = isCompleted
                  ? 'All reserved tour guide, hotel stay, and vehicle rental services were completed successfully.'
                  : isConfirmed
                  ? 'All participating service providers have accepted. Package is fully confirmed.'
                  : isCancelled
                  ? 'This package booking has been cancelled and archived in your Trip History.'
                  : 'Total package payment is complete. Booking requests are pending provider confirmations.';

                return (
                  <>
                    {/* Status Badge */}
                    <View style={[styles.historyBanner, { backgroundColor: bannerBg, borderColor: bannerBorder, marginTop: 0, marginBottom: 14 }]}>
                      <Ionicons name={bannerIcon as any} size={18} color={bannerIconColor} />
                      <View style={{ flex: 1, marginLeft: 8 }}>
                        <Text style={{ fontSize: 13, fontWeight: '800', color: bannerTitleColor }}>
                          {bannerTitle}
                        </Text>
                        <Text style={{ fontSize: 11, color: bannerSubColor, marginTop: 1 }}>
                          {bannerSubtitle}
                        </Text>
                      </View>
                    </View>

                    {/* Itemized Receipt Breakdown */}
                    <View style={[styles.detailSectionBox, { backgroundColor: isDark ? '#1E2330' : '#F8FAFC', borderColor: colors.border }]}>
                      <Text style={[styles.detailSectionTitle, { color: colors.text }]}>Itemized Breakdown</Text>
                      
                      <View style={{ flexDirection: 'row', justifyContent: 'space-between', marginTop: 12 }}>
                        <Text style={{ fontSize: 13, color: colors.textSub }}>Total Package Price</Text>
                        <Text style={{ fontSize: 14, fontWeight: '700', color: colors.text }}>{formatPrice(basePrice)}</Text>
                      </View>

                      <View style={{ flexDirection: 'row', justifyContent: 'space-between', marginTop: 8 }}>
                        <Text style={{ fontSize: 13, color: colors.textSub }}>Taxes &amp; Surcharges (5%)</Text>
                        <Text style={{ fontSize: 14, fontWeight: '700', color: colors.text }}>{formatPrice(taxes)}</Text>
                      </View>

                      <View style={{ flexDirection: 'row', justifyContent: 'space-between', marginTop: 8 }}>
                        <Text style={{ fontSize: 13, color: colors.textSub }}>Environmental &amp; Tourism Fees</Text>
                        <Text style={{ fontSize: 14, fontWeight: '700', color: colors.text }}>{formatPrice(fees)}</Text>
                      </View>

                      {discount > 0 ? (
                        <View style={{ flexDirection: 'row', justifyContent: 'space-between', marginTop: 8 }}>
                          <Text style={{ fontSize: 13, color: '#059669', fontWeight: '700' }}>
                            Voucher Discount ({voucherCode})
                          </Text>
                          <Text style={{ fontSize: 14, fontWeight: '800', color: '#059669' }}>
                            -{formatPrice(discount)}
                          </Text>
                        </View>
                      ) : null}

                      <View style={{ height: 1, backgroundColor: colors.border, marginVertical: 12 }} />

                      <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
                        <Text style={{ fontSize: 15, fontWeight: '800', color: colors.text }}>Total Amount Paid</Text>
                        <Text style={{ fontSize: 18, fontWeight: '900', color: '#059669' }}>
                          {formatPrice(selectedPackageReceipt.total_amount)}
                        </Text>
                      </View>
                    </View>

                    {/* Payment Transaction Details */}
                    <View style={[styles.detailSectionBox, { backgroundColor: isDark ? '#1E2330' : '#F8FAFC', borderColor: colors.border, marginTop: 12 }]}>
                      <Text style={[styles.detailSectionTitle, { color: colors.text }]}>Transaction Details</Text>

                      <View style={{ flexDirection: 'row', justifyContent: 'space-between', marginTop: 10 }}>
                        <Text style={{ fontSize: 12, color: colors.textMute }}>Transaction Ref</Text>
                        <Text style={{ fontSize: 12, fontWeight: '700', color: colors.text }}>
                          {selectedPackageReceipt.payment_reference || ('TXN-PKG-' + selectedPackageReceipt.id)}
                        </Text>
                      </View>

                      <View style={{ flexDirection: 'row', justifyContent: 'space-between', marginTop: 8 }}>
                        <Text style={{ fontSize: 12, color: colors.textMute }}>Payment Method</Text>
                        <Text style={{ fontSize: 12, fontWeight: '700', color: colors.text, textTransform: 'uppercase' }}>
                          {selectedPackageReceipt.payment_method || 'GCASH / E-WALLET'}
                        </Text>
                      </View>

                      <View style={{ flexDirection: 'row', justifyContent: 'space-between', marginTop: 8 }}>
                        <Text style={{ fontSize: 12, color: colors.textMute }}>Date Paid</Text>
                        <Text style={{ fontSize: 12, fontWeight: '700', color: colors.text }}>
                          {formatDate(selectedPackageReceipt.booking_date)}
                        </Text>
                      </View>

                      <View style={{ flexDirection: 'row', justifyContent: 'space-between', marginTop: 8 }}>
                        <Text style={{ fontSize: 12, color: colors.textMute }}>Booking Status</Text>
                        <Text style={{ fontSize: 12, fontWeight: '800', color: (isCompleted || isConfirmed) ? '#059669' : (isCancelled ? '#DC2626' : '#D97706'), textTransform: 'uppercase' }}>
                          {overallStatus}
                        </Text>
                      </View>
                    </View>

                    {/* Cancel Booking Button — only shown when eligible */}
                    <View style={{ marginTop: 18, gap: 10 }}>
                      {selectedPackageReceipt &&
                      selectedPackageReceipt.status !== 'completed' &&
                      selectedPackageReceipt.status !== 'cancelled' &&
                      selectedPackageReceipt.status !== 'refunded' &&
                      (!selectedPackageReceipt.package_booking_ref || (selectedPackageReceipt.package_overall_status !== 'confirmed' && selectedPackageReceipt.status !== 'confirmed')) &&
                      selectedPackageReceipt.cancellation?.can_cancel ? (
                        <TouchableOpacity
                          style={[styles.cancelModalBtn, { borderColor: '#EF4444', backgroundColor: isDark ? '#7F1D1D22' : '#FEF2F2' }]}
                          disabled={isSubmittingCancel}
                          onPress={() => {
                            const item = selectedPackageReceipt;
                            setSelectedPackageReceipt(null);
                            handleCancelBooking(item);
                          }}
                        >
                          {isSubmittingCancel ? (
                            <ActivityIndicator size="small" color="#EF4444" />
                          ) : (
                            <>
                              <Ionicons name="close-circle-outline" size={16} color="#EF4444" />
                              <Text style={{ color: '#EF4444', fontWeight: '800' }}>Cancel Booking</Text>
                            </>
                          )}
                        </TouchableOpacity>
                      ) : null}

                      {/* Close Button */}
                      <TouchableOpacity
                        style={[styles.cancelModalBtn, { borderColor: colors.border }]}
                        onPress={() => setSelectedPackageReceipt(null)}
                      >
                        <Text style={{ color: colors.text, fontWeight: '700' }}>Done</Text>
                      </TouchableOpacity>
                    </View>
                  </>
                );
              })() : null}
            </ScrollView>
          </View>
        </View>
      </Modal>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1 },
  headerBar: { paddingHorizontal: 20, paddingBottom: 12 },
  center: { flexGrow: 1, alignItems: 'center', justifyContent: 'center', paddingTop: 60 },
  listContent: { paddingHorizontal: 16, paddingBottom: 24, flexGrow: 1 },
  card: { borderRadius: 18, overflow: 'hidden', marginBottom: 14, borderWidth: 1, padding: 14 },
  cardHeaderRow: { flexDirection: 'row', alignItems: 'flex-start' },
  cardImage: { width: 88, height: 88, borderRadius: 14, resizeMode: 'cover', backgroundColor: '#00000011' },
  cardBody: { flex: 1, marginLeft: 12, justifyContent: 'center' },
  cardDivider: { height: 1, marginTop: 12, marginBottom: 10, opacity: 0.5 },
  topRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  cardTitle: { fontSize: 15, fontWeight: '800', flex: 1, marginRight: 8 },
  metaRow: { flexDirection: 'row', alignItems: 'center', marginTop: 5 },
  metaText: { fontSize: 12, marginLeft: 5, fontWeight: '600' },
  bottomRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  price: { fontSize: 17, fontWeight: '900' },
  navBtn: { flexDirection: 'row', alignItems: 'center', gap: 5, paddingHorizontal: 13, paddingVertical: 7, borderRadius: 999, marginRight: 2 },
  navBtnText: { color: '#FFFFFF', fontSize: 12, fontWeight: '800' },
  actionsRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginTop: 8, alignItems: 'center' },
  actionBtn: { flexDirection: 'row', alignItems: 'center', gap: 5, borderWidth: 1.5, borderRadius: 999, paddingHorizontal: 12, paddingVertical: 7 },
  actionText: { fontSize: 12, fontWeight: '800' },
  attractionPriceRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginTop: 14, marginBottom: 12 },
  attractionPrice: { fontSize: 21, fontWeight: '900' },
  attractionNavBtn: { flexDirection: 'row', alignItems: 'center', gap: 6, paddingHorizontal: 16, paddingVertical: 9, borderRadius: 999 },
  attractionNavBtnText: { color: '#FFFFFF', fontSize: 13, fontWeight: '800' },
  attractionActionsCol: { flexDirection: 'column', gap: 9, width: '100%' },
  attractionActionBtn: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8, borderWidth: 1.5, borderRadius: 999, paddingVertical: 12, width: '100%' },
  attractionActionText: { fontSize: 13, fontWeight: '800' },
  activityCategoryPill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    backgroundColor: '#ECFDF5',
    alignSelf: 'flex-start',
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 6,
    marginTop: 4,
  },
  activityCategoryText: {
    fontSize: 10,
    fontWeight: '800',
    color: '#059669',
    letterSpacing: 0.5,
  },
  attractionCategoryPill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    backgroundColor: '#FDF2F8',
    alignSelf: 'flex-start',
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 6,
    marginTop: 4,
  },
  attractionCategoryText: {
    fontSize: 10,
    fontWeight: '800',
    color: '#DB2777',
    letterSpacing: 0.5,
  },
  statusChip: { flexDirection: 'row', alignItems: 'center', gap: 4, paddingHorizontal: 9, paddingVertical: 4, borderRadius: 999 },
  statusDot: { width: 6, height: 6, borderRadius: 3 },
  statusText: { fontSize: 11, fontWeight: '800', textTransform: 'capitalize' },
  hotelCard: { borderRadius: 18, overflow: 'hidden', marginBottom: 16, borderWidth: 1, padding: 14 },
  hotelHeaderRow: { flexDirection: 'row', alignItems: 'flex-start', gap: 12 },
  hotelImage: { width: 68, height: 68, borderRadius: 12, resizeMode: 'cover', backgroundColor: '#00000011' },
  hotelHeaderBody: { flex: 1, justifyContent: 'center' },
  hotelRoomBadge: { flexDirection: 'row', alignItems: 'center', gap: 5, backgroundColor: '#EFF6FF', alignSelf: 'flex-start', paddingHorizontal: 8, paddingVertical: 3, borderRadius: 6, marginTop: 4 },
  hotelRoomBadgeText: { fontSize: 12, fontWeight: '700', color: '#1D4ED8' },
  confirmBanner: { flexDirection: 'row', alignItems: 'center', borderWidth: 1, borderRadius: 12, padding: 10, marginTop: 12 },
  confirmBannerTitle: { fontSize: 13, fontWeight: '800' },
  confirmBannerSubtitle: { fontSize: 11, fontWeight: '500', marginTop: 2 },
  hotelStaySection: { borderWidth: 1, borderRadius: 12, padding: 10, marginTop: 12 },
  hotelStayRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  hotelDateCol: { flex: 1 },
  hotelStayLabel: { fontSize: 10, fontWeight: '800', letterSpacing: 0.5 },
  hotelStayValue: { fontSize: 13, fontWeight: '700', marginTop: 2 },
  hotelMetaSubRow: { flexDirection: 'row', alignItems: 'center', gap: 16, borderTopWidth: 1, paddingTop: 8, marginTop: 8 },
  hotelPricingGrid: { flexDirection: 'row', borderWidth: 1, borderRadius: 12, marginTop: 12, overflow: 'hidden' },
  priceCol: { flex: 1, paddingVertical: 10, paddingHorizontal: 8, alignItems: 'center', justifyContent: 'center' },
  priceColDivider: { borderLeftWidth: 1, borderRightWidth: 1 },
  priceColLabel: { fontSize: 11, fontWeight: '600' },
  priceColTotal: { fontSize: 14, fontWeight: '800', marginTop: 3 },
  priceColDeposit: { fontSize: 14, fontWeight: '800', marginTop: 3 },
  priceColBalance: { fontSize: 14, fontWeight: '800', marginTop: 3 },
  frontDeskNotice: { flexDirection: 'row', alignItems: 'flex-start', borderWidth: 1, borderRadius: 12, padding: 10, marginTop: 10 },
  frontDeskNoticeTitle: { fontSize: 12, fontWeight: '800' },
  frontDeskNoticeText: { fontSize: 12, fontWeight: '500', marginTop: 2, lineHeight: 16 },
  hotelActionsRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginTop: 12, alignItems: 'center' },
  modalBackdrop: { flex: 1, backgroundColor: '#00000088', justifyContent: 'flex-end' },
  modalCard: { borderTopLeftRadius: 24, borderTopRightRadius: 24, borderTopWidth: 1, borderLeftWidth: 1, borderRightWidth: 1, maxHeight: '90%' },
  modalHeader: { flexDirection: 'row', alignItems: 'center', paddingHorizontal: 18, paddingVertical: 14, borderBottomWidth: 1 },
  modalBackBtn: { marginRight: 10, padding: 4 },
  modalCloseBtn: { padding: 4, marginLeft: 8 },
  modalTitle: { fontSize: 17, fontWeight: '800' },
  modalSubtitle: { fontSize: 12, marginTop: 2 },
  modalDueCard: { flexDirection: 'row', alignItems: 'center', borderWidth: 1, borderRadius: 14, padding: 14 },
  modalDueLabel: { fontSize: 11, fontWeight: '800', letterSpacing: 0.5 },
  modalDueAmount: { fontSize: 22, fontWeight: '900', marginTop: 3 },
  modalDueChip: { flexDirection: 'row', alignItems: 'center', gap: 4, paddingHorizontal: 8, paddingVertical: 4, borderRadius: 6 },
  modalDueChipText: { fontSize: 11, fontWeight: '700', color: '#059669' },
  sectionHeading: { fontSize: 14, fontWeight: '800', marginBottom: 10 },
  methodOption: { flexDirection: 'row', alignItems: 'center', borderWidth: 1, borderRadius: 14, padding: 12, marginBottom: 10 },
  methodOptionIcon: { width: 40, height: 40, borderRadius: 10, alignItems: 'center', justifyContent: 'center' },
  methodOptionTitle: { fontSize: 14, fontWeight: '700' },
  methodOptionDesc: { fontSize: 11, marginTop: 2 },
  qrContainer: { alignItems: 'center', justifyContent: 'center', padding: 14, borderRadius: 16, borderWidth: 1, alignSelf: 'center', marginBottom: 12 },
  qrImage: { width: 180, height: 180 },
  qrInstructions: { fontSize: 12, lineHeight: 18, textAlign: 'center', marginBottom: 14, paddingHorizontal: 8 },
  inputLabel: { fontSize: 12, fontWeight: '700', marginBottom: 6, marginTop: 8 },
  textInput: { borderWidth: 1, borderRadius: 10, paddingHorizontal: 12, paddingVertical: 10, fontSize: 14, fontWeight: '600' },
  submitPayBtn: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8, borderRadius: 12, paddingVertical: 14, marginTop: 18 },
  submitPayBtnText: { color: '#FFFFFF', fontSize: 15, fontWeight: '800' },
  headerTitleRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  historyIconBtn: { width: 38, height: 38, borderRadius: 19, borderWidth: 1, alignItems: 'center', justifyContent: 'center', position: 'relative' },
  historyBadge: { position: 'absolute', top: -4, right: -4, minWidth: 17, height: 17, borderRadius: 8.5, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 4 },
  historyBadgeText: { fontSize: 9, fontWeight: '800' },
  modeSegmentWrap: { flexDirection: 'row', marginTop: 10, marginBottom: 8, padding: 4, borderRadius: 12, borderWidth: 1, gap: 4 },
  modeSegmentBtn: { flex: 1, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 6, paddingVertical: 8, borderRadius: 8 },
  modeSegmentBtnActive: { elevation: 2, shadowColor: '#000', shadowOpacity: 0.08, shadowOffset: { width: 0, height: 1 }, shadowRadius: 2 },
  modeSegmentText: { fontSize: 13 },
  categoryScroll: { flexDirection: 'row', gap: 8, paddingVertical: 4 },
  catChip: { flexDirection: 'row', alignItems: 'center', gap: 5, paddingHorizontal: 12, paddingVertical: 6, borderRadius: 999, borderWidth: 1 },
  catChipText: { fontSize: 12, fontWeight: '700' },
  historyBanner: { flexDirection: 'row', alignItems: 'center', gap: 6, borderWidth: 1, borderRadius: 8, paddingHorizontal: 10, paddingVertical: 6, marginTop: 8 },
  historyBannerText: { fontSize: 12, fontWeight: '700' },
  detailSectionBox: { borderWidth: 1, borderRadius: 14, padding: 14 },
  detailSectionTitle: { fontSize: 14, fontWeight: '800' },
  detailMainValue: { fontSize: 16, fontWeight: '800', marginTop: 4 },
  cancellationNoticeBox: { flexDirection: 'row', alignItems: 'center', borderWidth: 1, borderRadius: 10, padding: 10, marginTop: 10 },
  cancelModalBtn: { flexDirection: 'row', gap: 6, borderWidth: 1, borderRadius: 12, paddingVertical: 12, alignItems: 'center', justifyContent: 'center' },
  pkgCard: { borderRadius: 18, overflow: 'hidden', marginBottom: 16, borderWidth: 1, padding: 14 },
  pkgHeaderRow: { flexDirection: 'row', alignItems: 'flex-start', gap: 12 },
  pkgImage: { width: 78, height: 78, borderRadius: 12, resizeMode: 'cover', backgroundColor: '#00000011' },
  pkgCategoryPill: { flexDirection: 'row', alignItems: 'center', gap: 4, alignSelf: 'flex-start', paddingHorizontal: 8, paddingVertical: 3, borderRadius: 6, marginTop: 4 },
  pkgCategoryText: { fontSize: 10, fontWeight: '800', letterSpacing: 0.5 },
  pkgHostRow: { flexDirection: 'row', alignItems: 'center', marginTop: 10, paddingTop: 10, borderTopWidth: 1 },
  pkgAvatar: { width: 30, height: 30, borderRadius: 15 },
  pkgHostName: { fontSize: 12, fontWeight: '700' },
  pkgRefPill: { marginLeft: 'auto', paddingHorizontal: 8, paddingVertical: 3, borderRadius: 6 },
  pkgRefText: { fontSize: 11, fontWeight: '800' },
  pkgDatesGrid: { flexDirection: 'row', alignItems: 'center', borderWidth: 1, borderRadius: 12, padding: 10, marginTop: 10, gap: 12 },
  pkgPriceRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginTop: 12, marginBottom: 8 },
  pkgPriceLabel: { fontSize: 10, fontWeight: '800', textTransform: 'uppercase', letterSpacing: 0.5 },
  pkgPriceValue: { fontSize: 20, fontWeight: '900', marginTop: 2 },
  pkgPriceUnit: { fontSize: 11, fontWeight: '700', marginLeft: 5 },
  pkgActionsCol: { flexDirection: 'column', gap: 8, marginTop: 6 },
  pkgActionRow: { flexDirection: 'row', gap: 8 },
});