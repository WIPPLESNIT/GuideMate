import { Ionicons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import { useLocalSearchParams, useRouter } from 'expo-router';
import React, { useCallback, useEffect, useState } from 'react';
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
} from 'react-native';
import { ApiListing, createBooking, getListing, isUnauthorized, PaymentMethod, resolveImage, validateVoucher } from '../../services/api';
import { clearSession, restoreSession } from '../../lib/authStore';
import { usePreferences } from '../../lib/preferences';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useTheme } from '../../lib/theme';
import CalendarPicker from '../../components/CalendarPicker';
import FavoriteHeart from '../../components/FavoriteHeart';
import { findVoucher, getVoucherDiscount } from '../../lib/vouchers';

const QR_EWALLET = require('../../assets/images/payment/qr-ewallet.png');
const QR_INSTAPAY = require('../../assets/images/payment/qr-instapay.png');

type BookingStep = 'details' | 'method' | 'qr' | 'card';

type ReviewItem = {
  id: number;
  rating: number;
  comment: string;
  user_name: string;
  created_at: string;
};

// Selectable start times for an experience (24h values, shown in 12h format).
const TIME_SLOTS = [
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

function hostMessageLabel(listing: Pick<ApiListing, 'owner_name' | 'owner_role'>): string {
  if (listing.owner_role === 'hotel_admin') return 'Message the hotel';
  if (listing.owner_role === 'rental_admin') return 'Message the rental partner';
  return listing.owner_name ? `Message ${listing.owner_name}` : 'Message the guide';
}

export default function ListingDetailScreen() {
  const router = useRouter();
  const { slug } = useLocalSearchParams<{ slug: string }>();
  const { t, formatPrice } = usePreferences();
  const insets = useSafeAreaInsets();
  const { colors, gradients } = useTheme();

  const [listing, setListing] = useState<ApiListing | null>(null);
  const [alreadyBooked, setAlreadyBooked] = useState(false);
  const [favorited, setFavorited] = useState(false);
  const [canReview, setCanReview] = useState(false);
  const [bookedSlots, setBookedSlots] = useState<{ date: string; time: string }[]>([]);
  const [reviews, setReviews] = useState<ReviewItem[]>([]);
  const [reviewsOpen, setReviewsOpen] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  // Booking modal state
  const [modalOpen, setModalOpen] = useState(false);
  const [step, setStep] = useState<BookingStep>('details');
  const [bookingDate, setBookingDate] = useState('');
  const [bookingTime, setBookingTime] = useState('');
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
    border: colors.border,
    textMain: colors.text,
    textSub: colors.textSub,
    accent: colors.primary,
    inputBg: colors.chipBg,
  };

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const data = await getListing(String(slug));
      setListing(data.listing);
      setFavorited(!!data.favorited);
      setAlreadyBooked(!!data.already_booked);
      setCanReview(!!data.can_review);
      setBookedSlots(Array.isArray(data.booked_slots) ? data.booked_slots : []);
      setReviews(Array.isArray(data.reviews) ? data.reviews : []);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load this listing.');
    } finally {
      setLoading(false);
    }
  }, [slug]);

  useEffect(() => {
    load();
  }, [load]);

  const priceLabel = listing ? (listing.price > 0 ? formatPrice(listing.price) : t('free')) : '';

  const openBooking = async () => {
    if (alreadyBooked) {
      Alert.alert('Already booked', 'You already have an approved booking for this experience.');
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
    setBookingDate('');
    setBookingTime('');
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

  const subtotal = listing ? listing.price * guests : 0;
  const appliedVoucher = voucherCode ? findVoucher(voucherCode) : null;
  const voucherDiscount = appliedVoucher ? getVoucherDiscount(appliedVoucher, subtotal) : 0;
  const totalAmount = Math.max(0, subtotal - voucherDiscount);

  // Set of "YYYY-MM-DD|HH:MM" slots already taken, and dates where every slot
  // is taken (so the calendar can fully disable them).
  const bookedSet = React.useMemo(() => new Set(bookedSlots.map((s) => `${s.date}|${s.time}`)), [bookedSlots]);
  const fullyBookedDates = React.useMemo(() => {
    const counts: Record<string, number> = {};
    bookedSlots.forEach((s) => {
      counts[s.date] = (counts[s.date] ?? 0) + 1;
    });
    return Object.keys(counts).filter((d) => counts[d] >= TIME_SLOTS.length);
  }, [bookedSlots]);
  const isSlotBooked = (slot: string) => !!bookingDate && bookedSet.has(`${bookingDate}|${slot}`);

  // If the selected time becomes unavailable after picking a date, clear it.
  useEffect(() => {
    if (bookingTime && isSlotBooked(bookingTime)) {
      setBookingTime('');
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [bookingDate]);

  const goToPayment = () => {
    if (!bookingDate) {
      Alert.alert('Pick a date', 'Please choose a date from the calendar.');
      return;
    }
    if (!bookingTime) {
      Alert.alert('Pick a time', 'Please choose a start time for your experience.');
      return;
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
    if (!listing) {
      return;
    }
    setBooking(true);
    try {
      await createBooking({
        listing_id: listing.id,
        booking_date: bookingDate,
        booking_time: bookingTime,
        guests,
        payment_method: method,
        payment_reference: reference,
        card_last4: cardLast4,
        promo_code: voucherDiscount > 0 && appliedVoucher ? appliedVoucher.code : undefined,
      });
      setVoucherCode('');
      setVoucherDraft('');
      setModalOpen(false);
      Alert.alert(
        'Payment received',
        `Your payment for "${listing.title}" on ${bookingDate}${bookingTime ? ` at ${formatTimeLabel(bookingTime)}` : ''} was received. The partner still needs to confirm this booking. Track it under Trips — it will show as Awaiting confirmation until they accept it.`,
        [
          { text: 'View Trips', onPress: () => router.replace('/(tabs)/trips') },
          { text: 'OK' },
        ]
      );
    } catch (e) {
      if (isUnauthorized(e)) {
        await clearSession();
        setModalOpen(false);
        Alert.alert('Session expired', 'Please sign in again to complete your booking.', [
          { text: 'Cancel', style: 'cancel' },
          { text: 'Sign in', onPress: () => router.push('/(auth)/login') },
        ]);
      } else {
        Alert.alert('Booking failed', e instanceof Error ? e.message : 'Please try again.');
      }
    } finally {
      setBooking(false);
    }
  };

  const confirmQrPaid = () => {
    if (paymentReference.trim().length < 4) {
      Alert.alert('Reference required', 'Enter the reference / confirmation number from your payment app so the admin can verify it.');
      return;
    }
    submitBooking(qrMethod, paymentReference.trim());
  };

  const confirmCardPaid = () => {
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
        <View>
          <Image source={{ uri: resolveImage(listing.image) }} style={styles.hero} />
          <LinearGradient colors={gradients.hero} style={styles.heroOverlay} />
          <TouchableOpacity style={styles.backButton} onPress={() => router.back()} activeOpacity={0.8}>
            <Ionicons name="arrow-back" size={22} color="#FFFFFF" />
          </TouchableOpacity>
          <FavoriteHeart listingId={listing.id} favorited={favorited} size={40} style={styles.favButton} />
          <View style={styles.heroTextWrap}>
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
            <View style={[styles.factCard, { backgroundColor: theme.card, borderColor: theme.border }]}>
              <Ionicons name="time-outline" size={18} color={theme.accent} />
              <Text style={[styles.factLabel, { color: theme.textSub }]}>Duration</Text>
              <Text style={[styles.factValue, { color: theme.textMain }]}>{listing.duration || 'N/A'}</Text>
            </View>
            <TouchableOpacity
              style={[styles.factCard, { backgroundColor: theme.card, borderColor: theme.border }]}
              activeOpacity={0.7}
              onPress={() => setReviewsOpen(true)}
              accessibilityRole="button"
              accessibilityLabel="View reviews from other tourists"
            >
              <Ionicons name="star" size={18} color="#F6B100" />
              <Text style={[styles.factLabel, { color: theme.textSub }]}>Rating</Text>
              <Text style={[styles.factValue, { color: theme.textMain }]}>
                {listing.rating > 0 ? `${listing.rating} (${listing.review_count})` : 'No reviews'}
              </Text>
              <View style={styles.factLinkRow}>
                <Text style={[styles.factLink, { color: theme.accent }]}>See reviews</Text>
                <Ionicons name="chevron-forward" size={12} color={theme.accent} />
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
              style={[styles.messageGuideBtn, { borderColor: colors.accent }]}
              activeOpacity={0.85}
              onPress={() => router.push(`/chat/${listing.owner_id}`)}
            >
              <Ionicons name="chatbubble-ellipses-outline" size={17} color={colors.accent} style={{ marginRight: 8 }} />
              <Text style={[styles.messageGuideText, { color: colors.accent }]}>
                {hostMessageLabel(listing)}
              </Text>
            </TouchableOpacity>
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
          <Text style={[styles.bookPriceLabel, { color: theme.textSub }]}>Starting from</Text>
          <Text style={[styles.bookPrice, { color: colors.accent }]}>
            {priceLabel}
            {listing.price > 0 ? (
              <Text style={[styles.bookPer, { color: theme.textSub }]}> / {listing.price_unit || 'person'}</Text>
            ) : null}
          </Text>
        </View>
        {alreadyBooked ? (
          <View style={[styles.bookButton, styles.bookedButton]}>
            <Ionicons name="checkmark-circle" size={18} color="#FFFFFF" style={{ marginRight: 7 }} />
            <Text style={styles.bookButtonText}>Already booked</Text>
          </View>
        ) : (
          <TouchableOpacity onPress={openBooking} activeOpacity={0.88}>
            <LinearGradient
              colors={gradients.brand}
              start={{ x: 0, y: 0 }}
              end={{ x: 1, y: 1 }}
              style={styles.bookButton}
            >
              <Ionicons name="calendar" size={17} color="#FFFFFF" style={{ marginRight: 7 }} />
              <Text style={styles.bookButtonText}>Book now</Text>
            </LinearGradient>
          </TouchableOpacity>
        )}
      </View>

      {/* Booking modal (multi-step) */}
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

      <Modal visible={modalOpen} transparent animationType="slide" onRequestClose={() => setModalOpen(false)}>
        <View style={styles.modalBackdrop}>
          <View style={[styles.modalCard, { backgroundColor: theme.bg }]}>
            <View style={styles.modalHeader}>
              {step !== 'details' ? (
                <TouchableOpacity
                  onPress={() => setStep(step === 'method' ? 'details' : 'method')}
                  style={{ marginRight: 8 }}
                >
                  <Ionicons name="chevron-back" size={24} color={theme.textSub} />
                </TouchableOpacity>
              ) : null}
              <Text style={[styles.modalTitle, { color: theme.textMain }]}>
                {step === 'details' ? `Book ${listing.title}` : step === 'method' ? 'Payment method' : step === 'qr' ? 'Scan to pay' : 'Card payment'}
              </Text>
              <TouchableOpacity onPress={() => setModalOpen(false)}>
                <Ionicons name="close" size={24} color={theme.textSub} />
              </TouchableOpacity>
            </View>

            <ScrollView showsVerticalScrollIndicator={false} keyboardShouldPersistTaps="handled">
              {/* ── STEP 1: Date + Guests ── */}
              {step === 'details' ? (
                <>
                  <Text style={[styles.modalLabel, { color: theme.textSub }]}>Select a date</Text>
                  <CalendarPicker value={bookingDate} onChange={setBookingDate} theme={theme} disabledDates={fullyBookedDates} />

                  <Text style={[styles.modalLabel, { color: theme.textSub }]}>Select a start time</Text>
                  <View style={styles.timeGrid}>
                    {TIME_SLOTS.map((slot) => {
                      const selected = bookingTime === slot;
                      const booked = isSlotBooked(slot);
                      return (
                        <TouchableOpacity
                          key={slot}
                          activeOpacity={0.85}
                          disabled={booked}
                          onPress={() => setBookingTime(slot)}
                          style={[
                            styles.timeChip,
                            { backgroundColor: theme.inputBg, borderColor: theme.border },
                            selected && { backgroundColor: theme.accent, borderColor: theme.accent },
                            booked && { opacity: 0.4, borderColor: theme.border },
                          ]}
                        >
                          <Text
                            style={[
                              styles.timeChipText,
                              { color: selected ? '#FFFFFF' : theme.textMain },
                              booked && { textDecorationLine: 'line-through', color: theme.textSub },
                            ]}
                          >
                            {formatTimeLabel(slot)}
                          </Text>
                          {booked ? (
                            <Text style={[styles.bookedTag, { color: theme.textSub }]}>Booked</Text>
                          ) : null}
                        </TouchableOpacity>
                      );
                    })}
                  </View>
                  {bookingDate ? (
                    <Text style={[styles.slotHint, { color: theme.textSub }]}>
                      Crossed-out times are already booked for this date.
                    </Text>
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
                      style={[styles.stepBtn, { borderColor: theme.border }]}
                      onPress={() => setGuests((g) => g + 1)}
                    >
                      <Ionicons name="add" size={20} color={theme.textMain} />
                    </TouchableOpacity>
                  </View>

                  {listing.price > 0 ? (
                    <Text style={[styles.totalLine, { color: theme.textMain }]}>
                      {`Total: ${formatPrice(totalAmount)}`}
                    </Text>
                  ) : null}

                  <TouchableOpacity
                    style={[styles.confirmBtn, { backgroundColor: theme.accent }, booking && { opacity: 0.6 }]}
                    onPress={goToPayment}
                    disabled={booking}
                    activeOpacity={0.85}
                  >
                    <Text style={styles.confirmBtnText}>
                      {totalAmount > 0 ? 'Proceed to payment' : booking ? 'Booking...' : 'Confirm booking'}
                    </Text>
                  </TouchableOpacity>
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
                    <Text style={styles.confirmBtnText}>{booking ? 'Processing...' : "I've paid"}</Text>
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
                    <Text style={styles.confirmBtnText}>{booking ? 'Processing...' : `Pay ${formatPrice(totalAmount)}`}</Text>
                  </TouchableOpacity>
                </>
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
  heroOverlay: { ...StyleSheet.absoluteFillObject, backgroundColor: 'rgba(0,0,0,0.28)' },
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
  input: { borderRadius: 12, paddingVertical: 13, paddingHorizontal: 16, fontSize: 15 },
  timeGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginTop: 2 },
  timeChip: { paddingVertical: 9, paddingHorizontal: 14, borderRadius: 12, borderWidth: 1, alignItems: 'center' },
  timeChipText: { fontSize: 13, fontWeight: '700' },
  bookedTag: { fontSize: 9, fontWeight: '700', marginTop: 1 },
  slotHint: { fontSize: 12, marginTop: 8, fontStyle: 'italic' },
  guestRow: { flexDirection: 'row', alignItems: 'center' },
  stepBtn: { width: 42, height: 42, borderRadius: 12, borderWidth: 1, alignItems: 'center', justifyContent: 'center' },
  guestCount: { fontSize: 18, fontWeight: '800', marginHorizontal: 20 },
  totalLine: { fontSize: 16, fontWeight: '800', marginTop: 18 },
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
});
