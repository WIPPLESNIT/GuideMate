import { Ionicons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import { useLocalSearchParams, useRouter } from 'expo-router';
import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Image,
  Modal,
  ScrollView,
  StatusBar,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  useWindowDimensions,
  View,
} from 'react-native';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import CalendarPicker from '../../components/CalendarPicker';
import { usePreferences } from '../../lib/preferences';
import { useTheme } from '../../lib/theme';
import { getPackageCoverSource } from '../../services/packageImages';
import {
  bookTravelPackage,
  getPackageById,
  PackageBookingResponse,
  TravelPackage,
} from '../../services/packageService';
import { api, resolveImage } from '../../services/api';
import { restoreSession } from '../../lib/authStore';
import { findVoucher, formatVoucherMinimum, getVoucherDiscount } from '../../lib/vouchers';

const QR_EWALLET = require('../../assets/images/payment/qr-ewallet.png');
const QR_INSTAPAY = require('../../assets/images/payment/qr-instapay.png');

type BookingStep = 'form' | 'method' | 'qr' | 'card' | 'confirmed';

export default function PackageDetailScreen() {
  const router = useRouter();
  const { id } = useLocalSearchParams<{ id: string }>();
  const insets = useSafeAreaInsets();
  const { width: windowWidth } = useWindowDimensions();
  const heroSliderWidth = Math.min(windowWidth, 1024);
  const heroScrollRef = useRef<any>(null);
  const { colors, isDark, shadow } = useTheme();
  const { formatPrice } = usePreferences();

  const [packageData, setPackageData] = useState<TravelPackage | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [activeHeroSlide, setActiveHeroSlide] = useState(0);
  const [activeDay, setActiveDay] = useState(1);
  const [isSaved, setIsSaved] = useState(false);

  // Booking Modal State
  const [bookingVisible, setBookingVisible] = useState(false);
  const [bookingStep, setBookingStep] = useState<BookingStep>('form');
  const [guestsCount, setGuestsCount] = useState(2);
  const [travelDate, setTravelDate] = useState('');
  const [contactName, setContactName] = useState('Christian');
  const [contactPhone, setContactPhone] = useState('0917 123 4567');
  const [specialNotes, setSpecialNotes] = useState('');
  const [qrMethod, setQrMethod] = useState<'gcash' | 'instapay'>('gcash');
  const [paymentReference, setPaymentReference] = useState('');
  const [cardName, setCardName] = useState('');
  const [cardNumber, setCardNumber] = useState('');
  const [cardExpiry, setCardExpiry] = useState('');
  const [cardCvv, setCardCvv] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [bookingResult, setBookingResult] = useState<PackageBookingResponse | null>(null);

  // Voucher / Promo State
  const [voucherExpanded, setVoucherExpanded] = useState(false);
  const [voucherCodeInput, setVoucherCodeInput] = useState('');
  const [appliedVoucher, setAppliedVoucher] = useState<{ code: string; label: string; discount: number } | null>(null);
  const [voucherError, setVoucherError] = useState('');
  const [isValidatingVoucher, setIsValidatingVoucher] = useState(false);

  const loadData = useCallback(async () => {
    if (!id) return;
    try {
      const pkg = await getPackageById(String(id));
      if (!pkg) setError('Package not found.');
      else setPackageData(pkg);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load package details.');
    } finally {
      setLoading(false);
    }
  }, [id]);

  useEffect(() => {
    if (!id) return;
    let active = true;
    const fetchPackage = async () => {
      try {
        const pkg = await getPackageById(String(id));
        if (!active) return;
        if (pkg) setPackageData(pkg);
        else setError('Package not found.');
      } catch (e) {
        if (active) setError(e instanceof Error ? e.message : 'Failed to load package details.');
      } finally {
        if (active) setLoading(false);
      }
    };
    void fetchPackage();
    return () => {
      active = false;
    };
  }, [id]);

  // Calendar theme matching current app colors
  const calendarTheme = useMemo(
    () => ({
      textMain: colors.text,
      textSub: colors.textSub,
      accent: colors.primary,
      border: colors.border,
      card: colors.card,
    }),
    [colors]
  );

  // Section 4: Min and Max Pax capacity limits
  const minPax = useMemo(() => packageData?.minPax ?? packageData?.minGroupSize ?? 2, [packageData]);
  const maxPax = useMemo(() => packageData?.maxPax ?? packageData?.maxGroupSize ?? 8, [packageData]);

  // Section 5: Constant Total Package Price regardless of guest count
  const packageBasePrice = useMemo(() => {
    if (!packageData) return 8499;
    return packageData.price;
  }, [packageData]);

  const taxes = useMemo(() => {
    return Math.round(packageBasePrice * 0.05 * 100) / 100;
  }, [packageBasePrice]);

  const environmentalFees = 250;

  const subtotal = useMemo(() => {
    return packageBasePrice + taxes + environmentalFees;
  }, [packageBasePrice, taxes, environmentalFees]);

  const voucherDiscount = useMemo(() => {
    return appliedVoucher ? appliedVoucher.discount : 0;
  }, [appliedVoucher]);

  const finalPayment = useMemo(() => {
    return Math.max(0, subtotal - voucherDiscount);
  }, [subtotal, voucherDiscount]);


  // Showcase Photos (Matching Things to Do Cover Layout)
  const coverSource = useMemo(() => {
    if (!packageData) return null;
    return getPackageCoverSource(packageData.localImageKey, packageData.coverImage);
  }, [packageData]);

  const showcasePhotos = useMemo(() => {
    if (!packageData) return [];
    const list: any[] = coverSource ? [coverSource] : [];
    if (Array.isArray(packageData.gallery) && packageData.gallery.length > 0) {
      for (const g of packageData.gallery) {
        if (g && typeof g === 'string') {
          list.push({ uri: resolveImage(g) });
        }
      }
    }
    if (list.length < 2) {
      if (packageData.category === 'island-escape' || packageData.destination?.toLowerCase().includes('moalboal')) {
        list.push(require('../../assets/images/places/sardine.jpg'));
        list.push(require('../../assets/images/places/bantayan.jpg'));
      } else if (packageData.destination?.toLowerCase().includes('cebu') || packageData.category === 'cultural') {
        list.push(require('../../assets/images/places/templeofleah.jpg'));
        list.push(require('../../assets/images/places/magellan.jpg'));
      } else {
        list.push(require('../../assets/images/places/whaleshark.jpg'));
        list.push(require('../../assets/images/places/osmena.jpg'));
      }
    }
    return list;
  }, [coverSource, packageData]);

  const scrollToHeroSlide = useCallback((index: number) => {
    if (heroScrollRef.current) {
      heroScrollRef.current.scrollTo({
        x: index * heroSliderWidth,
        animated: true,
      });
      setActiveHeroSlide(index);
    }
  }, [heroSliderWidth]);

  const handleShowRating = useCallback(() => {
    if (!packageData) return;
    const ratingNum = packageData.rating > 0 ? packageData.rating.toFixed(1) : '5.0';
    const reviewsNum = packageData.reviewCount > 0 ? packageData.reviewCount : 0;
    Alert.alert(
      'Ratings & Reviews',
      `★ ${ratingNum} Rating (${reviewsNum} ${reviewsNum === 1 ? 'verified review' : 'verified reviews'})\n\nTraveler satisfaction rating for ${packageData.title}.`
    );
  }, [packageData]);

  const handleMessageGuide = () => {
    if (!packageData) return;
    router.push({
      pathname: '/chat/[id]',
      params: {
        id: String(packageData.host.id),
        name: packageData.host.name,
      },
    });
  };

  const handleStartBooking = () => {
    setGuestsCount((prev) => Math.max(minPax, prev));
    setBookingStep('form');
    setPaymentReference('');
    setCardName('');
    setCardNumber('');
    setCardExpiry('');
    setCardCvv('');
    setBookingVisible(true);
  };

  const handleProceedToPayment = () => {
    // Section 4: Minimum Pax Validation
    if (guestsCount < minPax) {
      return Alert.alert(
        'Minimum Participants Required',
        `This package requires a minimum of ${minPax} participants. Please select at least ${minPax} guests to continue.`
      );
    }
    // Section 4: Maximum Pax Validation
    if (guestsCount > maxPax) {
      return Alert.alert(
        'Maximum Capacity Exceeded',
        `This package can accommodate a maximum of ${maxPax} participants.`
      );
    }
    if (!travelDate) {
      return Alert.alert('Select Travel Date', 'Please select your preferred travel start date.');
    }
    if (!contactName.trim() || !contactPhone.trim()) {
      return Alert.alert('Contact Information', 'Please provide your name and phone number.');
    }
    setBookingStep('method');
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

  // Section 6: Voucher validation handler
  const handleApplyVoucher = async () => {
    const code = voucherCodeInput.trim().toUpperCase();
    if (!code) {
      setVoucherError('Please enter a voucher code.');
      return;
    }
    setIsValidatingVoucher(true);
    setVoucherError('');
    try {
      await restoreSession().catch(() => null);
      const data = await api<{ valid?: boolean; discount?: number; message?: string }>('/api/vouchers/validate', {
        method: 'POST',
        body: { code, subtotal },
      });
      if (data && data.valid) {
        setAppliedVoucher({
          code,
          label: 'Promo Discount',
          discount: Number(data.discount) || 0,
        });
        setVoucherError('');
        setIsValidatingVoucher(false);
        return;
      } else if (data && data.message) {
        setVoucherError(data.message);
        setIsValidatingVoucher(false);
        return;
      }
    } catch {}

    const localV = findVoucher(code);
    if (localV) {
      const disc = getVoucherDiscount(localV, subtotal);
      if (disc > 0) {
        setAppliedVoucher({
          code: localV.code,
          label: localV.label,
          discount: disc,
        });
        setVoucherError('');
      } else {
        setVoucherError(formatVoucherMinimum(localV.minSpend));
      }
    } else {
      setVoucherError('Invalid or expired voucher code.');
    }
    setIsValidatingVoucher(false);
  };

  const submitBooking = async (
    method: 'ewallet' | 'instapay' | 'card' | 'cash',
    reference?: string,
    cardLast4?: string
  ) => {
    if (!packageData || submitting) return;
    setSubmitting(true);
    try {
      const res = await bookTravelPackage({
        packageId: packageData.id,
        travelDate,
        guestsCount,
        contactName,
        contactPhone,
        notes: specialNotes,
        paymentMethod: method,
        paymentReference: reference || (cardLast4 ? `Card ending in ${cardLast4}` : undefined),
        packageBasePrice,
        taxes,
        environmentalFees,
        voucherCode: appliedVoucher?.code,
        voucherDiscount: appliedVoucher?.discount ?? 0,
        finalTotal: finalPayment,
      });
      setBookingResult(res);
      setBookingStep('confirmed');
    } catch (err) {
      const msg = err instanceof Error ? err.message : 'Could not complete booking. Please try again.';
      Alert.alert('Booking Error', msg);
    } finally {
      setSubmitting(false);
    }
  };

  const confirmQrPaid = () => {
    if (submitting) return;
    if (paymentReference.trim().length < 4) {
      Alert.alert(
        'Reference required',
        'Enter the reference / confirmation number from your payment app so the guide host can verify it.'
      );
      return;
    }
    submitBooking(qrMethod === 'gcash' ? 'ewallet' : 'instapay', paymentReference.trim());
  };

  const confirmCardPaid = () => {
    if (submitting) return;
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
      Alert.alert('Invalid CVV', 'Enter the 3- or 4-digit CVV from the back of your card.');
      return;
    }
    submitBooking('card', undefined, digits.slice(-4));
  };

  if (loading) {
    return (
      <SafeAreaView style={[styles.centerContainer, { backgroundColor: colors.bgAlt }]}>
        <ActivityIndicator size="large" color={colors.primary} />
        <Text style={[styles.loadingText, { color: colors.textSub }]}>Loading package details...</Text>
      </SafeAreaView>
    );
  }

  if (error || !packageData) {
    return (
      <SafeAreaView style={[styles.centerContainer, { backgroundColor: colors.bgAlt }]}>
        <Ionicons name="alert-circle-outline" size={48} color={colors.danger} />
        <Text style={[styles.errorTitle, { color: colors.text }]}>{error || 'Package not found'}</Text>
        <TouchableOpacity
          style={[styles.retryBtn, { backgroundColor: colors.primary }]}
          onPress={() => {
            if (error) {
              setLoading(true);
              setError('');
              void loadData();
            } else {
              router.back();
            }
          }}
        >
          <Text style={styles.retryBtnText}>{error ? 'Retry' : 'Go Back'}</Text>
        </TouchableOpacity>
      </SafeAreaView>
    );
  }

  const activeItinerary = packageData.itinerary?.find((d) => d.day === activeDay) || packageData.itinerary?.[0];

  return (
    <View style={[styles.container, { backgroundColor: colors.bgAlt }]}>
      <StatusBar barStyle={isDark ? 'light-content' : 'dark-content'} />

      <ScrollView showsVerticalScrollIndicator={false} contentContainerStyle={{ paddingBottom: 120 }}>
        {/* Cover Photo Hero Slider (Matching Things to Do & Reference Image 2) */}
        <View style={[styles.heroContainer, { width: heroSliderWidth, alignSelf: 'center' }]}>
          {showcasePhotos.length > 0 ? (
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
                if (idx !== activeHeroSlide && idx >= 0 && idx < showcasePhotos.length) {
                  setActiveHeroSlide(idx);
                }
              }}
              onMomentumScrollEnd={(e) => {
                const offsetX = e.nativeEvent.contentOffset.x;
                const idx = Math.round(offsetX / heroSliderWidth);
                if (idx !== activeHeroSlide && idx >= 0 && idx < showcasePhotos.length) {
                  setActiveHeroSlide(idx);
                }
              }}
              scrollEventThrottle={16}
              style={{ width: heroSliderWidth, height: 340 }}
            >
              {showcasePhotos.map((photo, idx) => (
                <View key={idx} style={{ width: heroSliderWidth, height: 340 }}>
                  <Image
                    source={typeof photo === 'string' ? { uri: resolveImage(photo) } : photo}
                    style={[styles.heroImage, { width: heroSliderWidth, height: 340 }]}
                    resizeMode="cover"
                  />
                </View>
              ))}
            </ScrollView>
          ) : (
            <View
              style={[
                styles.heroFallback,
                { width: heroSliderWidth, height: 340, backgroundColor: isDark ? '#171B24' : '#E2E8F0' },
              ]}
            >
              <Ionicons name="images-outline" size={48} color={colors.textMute} />
            </View>
          )}

          {/* Smooth dark gradient overlay for text readability */}
          <LinearGradient
            colors={['rgba(0,0,0,0.3)', 'transparent', 'rgba(11,13,18,0.92)']}
            style={styles.heroOverlay}
            pointerEvents="none"
          />

          {/* Top navigation controls: Back and Favorite */}
          <TouchableOpacity
            style={[styles.backButton, { top: insets.top + 8 }]}
            onPress={() => router.back()}
            activeOpacity={0.8}
            accessibilityRole="button"
            accessibilityLabel="Go back"
          >
            <Ionicons name="arrow-back" size={22} color="#FFFFFF" />
          </TouchableOpacity>

          <TouchableOpacity
            style={[styles.favButton, { top: insets.top + 8 }]}
            onPress={() => setIsSaved(!isSaved)}
            activeOpacity={0.8}
            accessibilityRole="button"
            accessibilityLabel="Save package"
          >
            <Ionicons
              name={isSaved ? 'heart' : 'heart-outline'}
              size={22}
              color={isSaved ? '#EF4444' : '#FFFFFF'}
            />
          </TouchableOpacity>

          {/* Slide Indicator Dots & Photo Counter Badge */}
          {showcasePhotos.length > 1 ? (
            <View style={styles.heroIndicatorRow} pointerEvents="box-none">
              <View style={styles.heroDotsContainer}>
                {showcasePhotos.map((_, idx) => (
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
                <Text style={styles.heroCounterText}>
                  {activeHeroSlide + 1} / {showcasePhotos.length}
                </Text>
              </View>
            </View>
          ) : null}

          {/* Location & Title overlay at bottom of hero matching Image 2 */}
          <View style={styles.heroTextWrap} pointerEvents="none">
            {packageData.destination ? (
              <View style={styles.areaChip}>
                <Ionicons name="location-sharp" size={12} color="#FFFFFF" />
                <Text style={styles.areaChipText}>{packageData.destination}</Text>
              </View>
            ) : null}
            <Text style={styles.heroTitle} numberOfLines={2}>
              {packageData.title}
            </Text>
          </View>
        </View>

        {/* 1. About This Experience Section (Matching Reference Image) */}
        <View
          style={[
            styles.mainSection,
            {
              backgroundColor: colors.card,
              borderColor: colors.border,
            },
            shadow.sm,
          ]}
        >
          {/* 3-Column Attributes Grid: Duration, Group Size, Rating */}
          <View
            style={[
              styles.specsGrid,
              {
                backgroundColor: colors.cardAlt,
                borderColor: colors.border,
              },
            ]}
          >
            {/* Col 1: Duration */}
            <View style={styles.specItem}>
              <Ionicons name="time-outline" size={18} color={colors.primary} />
              <Text style={[styles.specLabel, { color: colors.textMute }]}>DURATION</Text>
              <Text style={[styles.specValue, { color: colors.text }]}>{packageData.duration}</Text>
            </View>
            <View
              style={[
                styles.specDivider,
                { backgroundColor: colors.border },
              ]}
            />

            {/* Col 2: Group Size */}
            <View style={styles.specItem}>
              <Ionicons name="people-outline" size={18} color={colors.info} />
              <Text style={[styles.specLabel, { color: colors.textMute }]}>GROUP SIZE</Text>
              <Text style={[styles.specValue, { color: colors.text }]}>Up to {packageData.maxGroupSize}</Text>
            </View>
            <View
              style={[
                styles.specDivider,
                { backgroundColor: colors.border },
              ]}
            />

            {/* Col 3: Dynamic Rating (Clickable, Replaces Assurance) */}
            <TouchableOpacity
              style={styles.specItem}
              onPress={handleShowRating}
              activeOpacity={0.7}
              accessibilityRole="button"
              accessibilityLabel={`Rating: ${packageData.rating > 0 ? packageData.rating.toFixed(1) : '4.9'} stars. Click to view reviews.`}
            >
              <Ionicons name="star" size={18} color={colors.primary} />
              <Text style={[styles.specLabel, { color: colors.textMute }]}>RATING</Text>
              <Text style={[styles.specValue, { color: colors.primary }]}>
                {packageData.rating > 0 ? packageData.rating.toFixed(1) : '4.9'}
              </Text>
            </TouchableOpacity>
          </View>

          {/* About this experience */}
          <View style={styles.descBlock}>
            <Text style={[styles.sectionHeading, { color: colors.text }]}>
              About this experience
            </Text>
            <Text style={[styles.descriptionText, { color: colors.textSub }]}>
              {packageData.description}
            </Text>
          </View>

          {/* Experience Tags */}
          {packageData.tags && packageData.tags.length > 0 ? (
            <View style={[styles.tagsContainer, { borderTopColor: colors.border }]}>
              <Text style={[styles.tagsLabel, { color: colors.textMute }]}>
                EXPERIENCE TAGS:
              </Text>
              <View style={styles.tagsRow}>
                {packageData.tags.map((tag, i) => (
                  <View
                    key={i}
                    style={[
                      styles.tagPill,
                      {
                        backgroundColor: colors.cardAlt,
                        borderColor: colors.border,
                      },
                    ]}
                  >
                    <Text style={[styles.tagText, { color: colors.primary }]}>
                      #{tag.replace(/^#/, '')}
                    </Text>
                  </View>
                ))}
              </View>
            </View>
          ) : null}
        </View>

        {/* 2. Host Information Card */}
        <View
          style={[
            styles.guideCard,
            {
              backgroundColor: colors.card,
              borderColor: colors.border,
            },
            shadow.sm,
          ]}
        >
          <Text style={[styles.guideCategorySubtitle, { color: colors.textSub }]}>
            Host Information • Travel Packages by {packageData.host.name}
          </Text>

          <View style={styles.guideCardHeader}>
            {packageData.host.avatar ? (
              <Image source={{ uri: resolveImage(packageData.host.avatar) }} style={[styles.guideAvatar, { borderColor: colors.border }]} />
            ) : (
              <View style={[styles.guideAvatar, { backgroundColor: colors.cardAlt, borderColor: colors.border }]}>
                <Ionicons name="person" size={24} color={colors.primary} />
              </View>
            )}
            <View style={{ flex: 1, marginLeft: 14 }}>
              <View style={{ flexDirection: 'row', alignItems: 'center' }}>
                <Text style={[styles.guideName, { color: colors.text }]}>
                  {packageData.host.name}
                </Text>
                <View style={[styles.verifiedCheckBadge, { backgroundColor: colors.primary }]}>
                  <Ionicons name="checkmark" size={10} color="#FFFFFF" />
                </View>
              </View>
              <Text style={[styles.guideRole, { color: colors.textSub }]}>
                {packageData.host.role}
              </Text>
              <Text style={[styles.guideStats, { color: colors.primary }]}>
                {packageData.host.badge} •{' '}
                {packageData.host.rating > 0 ? `${packageData.host.rating.toFixed(1)} ★ ` : ''}(
                {packageData.host.reviewCount} {packageData.host.reviewCount === 1 ? 'review' : 'reviews'}) •{' '}
                {packageData.host.toursCount}+ tours hosted
              </Text>
            </View>
          </View>

          {packageData.host.bio ? (
            <View
              style={[
                styles.guideBioBox,
                {
                  backgroundColor: colors.cardAlt,
                  borderColor: colors.border,
                },
              ]}
            >
              <Text style={[styles.guideBio, { color: colors.text }]}>
                &quot;{packageData.host.bio}&quot;
              </Text>
            </View>
          ) : null}

          <View style={styles.guideActionRow}>
            <TouchableOpacity
              style={[
                styles.messageGuideBtn,
                {
                  borderColor: colors.accent,
                  backgroundColor: isDark ? 'rgba(255, 106, 61, 0.08)' : 'rgba(255, 106, 61, 0.05)',
                },
              ]}
              onPress={handleMessageGuide}
              activeOpacity={0.8}
            >
              <Ionicons name="chatbubble-ellipses-outline" size={17} color={colors.accent} />
              <Text style={[styles.messageGuideBtnText, { color: colors.accent }]}>
                Message Guide Host
              </Text>
            </TouchableOpacity>
          </View>
        </View>

        {/* 2. All-in-One Inclusions Section */}
        <View
          style={[
            styles.inclusionsSection,
            {
              backgroundColor: colors.card,
              borderColor: colors.border,
            },
            shadow.sm,
          ]}
        >
          <View style={styles.inclusionsHeadingRow}>
            <Ionicons name="gift-outline" size={20} color={colors.primary} />
            <Text style={[styles.sectionHeading, { color: colors.text, marginLeft: 8 }]}>
              All-In-One Inclusions
            </Text>
          </View>
          <Text style={[styles.inclusionsSubheading, { color: colors.textSub }]}>
            Everything included in your single bundle reservation:
          </Text>

          <View style={styles.inclusionsList}>
            {packageData.inclusions.map((inc, i) => {
              const isAttraction = inc.type === 'attraction';
              const isActivity = inc.type === 'activity';
              const isHotel = inc.type === 'accommodation';
              const isRental = inc.type === 'rental';

              const boxBg = isAttraction
                ? isDark ? 'rgba(244, 63, 94, 0.15)' : '#FCE7F3'
                : isActivity
                ? isDark ? 'rgba(15, 169, 104, 0.15)' : '#DCFCE7'
                : isHotel
                ? isDark ? 'rgba(245, 158, 11, 0.15)' : '#FEF3C7'
                : isRental
                ? isDark ? 'rgba(59, 130, 246, 0.15)' : '#DBEAFE'
                : isDark ? 'rgba(15, 169, 104, 0.15)' : '#DCFCE7';

              const iconColor = isAttraction
                ? '#F43F5E'
                : isActivity
                ? colors.primary
                : isHotel
                ? colors.gold
                : isRental
                ? colors.info
                : colors.primary;

              return (
                <View
                  key={i}
                  style={[
                    styles.inclusionCard,
                    {
                      backgroundColor: colors.cardAlt,
                      borderColor: colors.border,
                    },
                  ]}
                >
                  <View style={[styles.incIconBox, { backgroundColor: boxBg }]}>
                    <Ionicons name={inc.icon as any} size={18} color={iconColor} />
                  </View>
                  <View style={{ flex: 1, marginLeft: 12 }}>
                    <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }}>
                      <Text style={[styles.incTitle, { color: colors.text }]}>
                        {inc.title}
                      </Text>
                      <View style={[styles.incBadgePill, { backgroundColor: colors.chipBg }]}>
                        <Text style={[styles.incBadgeText, { color: colors.textSub }]}>
                          {inc.badge}
                        </Text>
                      </View>
                    </View>
                    {inc.subtitle ? (
                      <Text style={[styles.incSubtitle, { color: colors.textSub }]}>
                        {inc.subtitle}
                      </Text>
                    ) : null}
                  </View>
                </View>
              );
            })}
          </View>

          {/* Exclusions */}
          {packageData.exclusions && packageData.exclusions.length > 0 ? (
            <View
              style={[
                styles.exclusionsBox,
                { borderTopColor: colors.border },
              ]}
            >
              <Text style={[styles.exclusionsTitle, { color: colors.textSub }]}>
                What&apos;s Not Included:
              </Text>
              {packageData.exclusions.map((exc, index) => (
                <View key={index} style={styles.exclusionItem}>
                  <Ionicons name="close-circle-outline" size={15} color={colors.danger} style={{ marginTop: 2 }} />
                  <Text style={[styles.exclusionText, { color: colors.textSub }]}>
                    {exc}
                  </Text>
                </View>
              ))}
            </View>
          ) : null}
        </View>

        {/* 3. Day-by-Day Itinerary Section */}
        <View
          style={[
            styles.itinerarySection,
            {
              backgroundColor: colors.card,
              borderColor: colors.border,
            },
            shadow.sm,
          ]}
        >
          <View style={styles.inclusionsHeadingRow}>
            <Ionicons name="calendar-outline" size={20} color={colors.primary} />
            <Text style={[styles.sectionHeading, { color: colors.text, marginLeft: 8 }]}>
              Day-by-Day Itinerary
            </Text>
          </View>

          {/* Day Tabs */}
          {packageData.itinerary && packageData.itinerary.length > 1 ? (
            <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.dayTabsScroll}>
              {packageData.itinerary.map((d) => {
                const isSelected = activeDay === d.day;
                return (
                  <TouchableOpacity
                    key={d.day}
                    style={[
                      styles.dayTab,
                      isSelected
                        ? { backgroundColor: colors.primary, borderColor: colors.primary }
                        : {
                            backgroundColor: colors.cardAlt,
                            borderColor: colors.border,
                          },
                    ]}
                    onPress={() => setActiveDay(d.day)}
                    activeOpacity={0.8}
                  >
                    <Text
                      style={[
                        styles.dayTabText,
                        {
                          color: isSelected ? '#FFFFFF' : colors.textSub,
                          fontWeight: isSelected ? '700' : '600',
                        },
                      ]}
                    >
                      Day {d.day}
                    </Text>
                  </TouchableOpacity>
                );
              })}
            </ScrollView>
          ) : null}

          {/* Selected Day Content */}
          {activeItinerary ? (
            <View style={styles.itineraryDayBody}>
              <Text style={[styles.itineraryDayTitle, { color: colors.text }]}>
                {activeItinerary.title}
              </Text>
              <Text style={[styles.itineraryDayDesc, { color: colors.textSub }]}>
                {activeItinerary.description}
              </Text>

              {/* Timed Schedule Timeline */}
              {activeItinerary.schedule && activeItinerary.schedule.length > 0 ? (
                <View style={styles.timelineList}>
                  {activeItinerary.schedule.map((item, idx) => (
                    <View key={idx} style={styles.timelineItem}>
                      <View style={styles.timelineLeft}>
                        <View style={[styles.timelineDot, { backgroundColor: colors.primary }]} />
                        {idx < activeItinerary.schedule!.length - 1 ? (
                          <View
                            style={[
                              styles.timelineLine,
                              { backgroundColor: colors.border },
                            ]}
                          />
                        ) : null}
                      </View>
                      <View style={styles.timelineRight}>
                        <Text style={[styles.timelineTime, { color: colors.primary }]}>{item.time}</Text>
                        <Text style={[styles.timelineActivity, { color: colors.text }]}>
                          {item.activity}
                        </Text>
                      </View>
                    </View>
                  ))}
                </View>
              ) : null}

              {/* Day Highlights */}
              {activeItinerary.highlights && activeItinerary.highlights.length > 0 ? (
                <View
                  style={[
                    styles.highlightsBox,
                    { backgroundColor: colors.cardAlt, borderColor: colors.border },
                  ]}
                >
                  <Text style={[styles.highlightsHeading, { color: colors.text }]}>
                    Day Highlights:
                  </Text>
                  {activeItinerary.highlights.map((hl, index) => (
                    <View key={index} style={styles.highlightBulletRow}>
                      <Ionicons name="checkmark-sharp" size={14} color={colors.primary} style={{ marginTop: 2 }} />
                      <Text style={[styles.highlightBulletText, { color: colors.textSub }]}>
                        {hl}
                      </Text>
                    </View>
                  ))}
                </View>
              ) : null}
            </View>
          ) : null}
        </View>

        {/* 4. Included Accommodation Details */}
        <View
          style={[
            styles.specsCard,
            {
              backgroundColor: colors.card,
              borderColor: colors.border,
            },
            shadow.sm,
          ]}
        >
          <View style={styles.inclusionsHeadingRow}>
            <Ionicons name="bed-outline" size={20} color={colors.gold} />
            <Text
              accessibilityLabel="Included Hotel Details"
              style={[styles.sectionHeading, { color: colors.text, marginLeft: 8 }]}
            >
              Included Accommodation Details
            </Text>
          </View>

          {packageData.hotelIncluded && packageData.accommodationDetail ? (
            <View style={styles.hotelDetailsBody}>
              <Text style={[styles.specHotelName, { color: colors.text }]}>
                {packageData.accommodationDetail.hotelName}
              </Text>
              <Text style={[styles.specRoomType, { color: colors.textSub }]}>
                {packageData.accommodationDetail.roomType} • {packageData.accommodationDetail.nights} Nights
              </Text>
              {packageData.accommodationDetail.partnerName ? (
                <Text style={[styles.partnerText, { color: colors.primary }]}>
                  📍 {packageData.destination} • Partner: {packageData.accommodationDetail.partnerName}
                </Text>
              ) : null}
              <View style={styles.amenitiesWrap}>
                {packageData.accommodationDetail.amenities.map((am, i) => (
                  <View
                    key={i}
                    style={[
                      styles.amenityPill,
                      {
                        backgroundColor: colors.cardAlt,
                        borderColor: colors.border,
                      },
                    ]}
                  >
                    <Ionicons name="checkmark-circle" size={14} color={colors.primary} />
                    <Text style={[styles.amenityText, { color: colors.text }]}>
                      {am}
                    </Text>
                  </View>
                ))}
              </View>
            </View>
          ) : (
            <View
              style={[
                styles.noBundledBanner,
                {
                  backgroundColor: colors.cardAlt,
                  borderColor: colors.border,
                },
              ]}
            >
              <Ionicons name="information-circle-outline" size={20} color={colors.info} style={{ marginTop: 2 }} />
              <Text style={[styles.noBundledText, { color: colors.textSub }]}>
                No Hotel Stay Bundled — This travel package is designed as a day expedition or travelers arrange their own accommodations.
              </Text>
            </View>
          )}
        </View>

        {/* 5. Included Rental Vehicle */}
        <View
          style={[
            styles.specsCard,
            {
              backgroundColor: colors.card,
              borderColor: colors.border,
            },
            shadow.sm,
          ]}
        >
          <View style={styles.inclusionsHeadingRow}>
            <Ionicons name="car-sport-outline" size={20} color={colors.info} />
            <Text style={[styles.sectionHeading, { color: colors.text, marginLeft: 8 }]}>
              Included Rental Vehicle
            </Text>
          </View>

          {packageData.rentalIncluded && packageData.rentalDetail ? (
            <View style={styles.vehicleDetailsBody}>
              <View style={styles.vehicleHeaderRow}>
                <Text style={[styles.specHotelName, { color: colors.text, flex: 1 }]}>
                  {packageData.rentalDetail.model}
                </Text>
                <View style={[styles.transmissionPill, { backgroundColor: colors.chipBg, borderColor: colors.border }]}>
                  <Text style={[styles.transmissionText, { color: colors.info }]}>
                    {packageData.rentalDetail.features?.[0] || 'Automatic Transmission'}
                  </Text>
                </View>
              </View>

              <Text style={[styles.specRoomType, { color: colors.textSub }]}>
                {packageData.rentalDetail.vehicleType} • {packageData.rentalDetail.duration}
              </Text>

              <Text style={[styles.partnerText, { color: colors.textSub }]}>
                📍 {packageData.rentalDetail.location || packageData.destination} • Partner: {packageData.rentalDetail.partnerName || 'Miguel Yongzon'}
              </Text>

              {/* Feature pill */}
              <View style={styles.vehicleFeaturesWrap}>
                <View style={[styles.vehicleFeaturePill, { backgroundColor: colors.cardAlt, borderColor: colors.border }]}>
                  <Ionicons name="checkmark" size={13} color={colors.primary} />
                  <Text style={[styles.vehicleFeatureText, { color: colors.primary }]}>
                    {packageData.rentalDetail.features?.[0] || 'Automatic Transmission'}
                  </Text>
                </View>
              </View>

              {/* Handover & Drop-off Cards */}
              <View style={styles.handoverRow}>
                <View
                  style={[
                    styles.handoverBox,
                    {
                      backgroundColor: colors.cardAlt,
                      borderColor: colors.border,
                    },
                  ]}
                >
                  <Text style={[styles.handoverLabel, { color: colors.textMute }]}>
                    HANDOVER / PICKUP
                  </Text>
                  <Text style={[styles.handoverVal, { color: colors.text }]}>
                    {packageData.rentalDetail.handover || 'Day 1 - 01:00 PM'}
                  </Text>
                </View>

                <View
                  style={[
                    styles.handoverBox,
                    {
                      backgroundColor: colors.cardAlt,
                      borderColor: colors.border,
                    },
                  ]}
                >
                  <Text style={[styles.handoverLabel, { color: colors.textMute }]}>
                    DROP-OFF / RETURN
                  </Text>
                  <Text style={[styles.handoverVal, { color: colors.text }]}>
                    {packageData.rentalDetail.dropoff || `Day ${packageData.durationDays} - 12:00 PM`}
                  </Text>
                </View>
              </View>
            </View>
          ) : (
            <View
              style={[
                styles.noBundledBanner,
                {
                  backgroundColor: colors.cardAlt,
                  borderColor: colors.border,
                },
              ]}
            >
              <Ionicons name="information-circle-outline" size={20} color={colors.info} style={{ marginTop: 2 }} />
              <Text style={[styles.noBundledText, { color: colors.textSub }]}>
                No Rental Vehicle Bundled — Travelers arrange their own ground transportation or local transport.
              </Text>
            </View>
          )}
        </View>

        {/* 6. Policies & Requirements */}
        {packageData.policy ? (
          <View
            style={[
              styles.specsCard,
              {
                backgroundColor: colors.card,
                borderColor: colors.border,
              },
              shadow.sm,
            ]}
          >
            <View style={styles.inclusionsHeadingRow}>
              <Ionicons name="document-text-outline" size={20} color={colors.primary} />
              <Text style={[styles.sectionHeading, { color: colors.text, marginLeft: 8 }]}>
                Policies & Requirements
              </Text>
            </View>

            <View style={styles.policyBlock}>
              <Text style={[styles.policyHeaderLabel, { color: colors.textMute }]}>
                CANCELLATION POLICY
              </Text>
              <Text style={[styles.policyBodyText, { color: colors.textSub }]}>
                {packageData.policy.cancellation}
              </Text>
            </View>

            {packageData.policy.requirements && packageData.policy.requirements.length > 0 ? (
              <View style={[styles.policyBlock, { marginTop: 16 }]}>
                <Text style={[styles.policyHeaderLabel, { color: colors.textMute }]}>
                  TRIP REQUIREMENTS & REMINDERS
                </Text>
                <View style={styles.requirementsList}>
                  {packageData.policy.requirements.map((req, i) => (
                    <View key={i} style={styles.requirementItem}>
                      <Ionicons name="checkmark-sharp" size={15} color={colors.primary} style={{ marginTop: 2 }} />
                      <Text style={[styles.requirementText, { color: colors.textSub }]}>
                        {req}
                      </Text>
                    </View>
                  ))}
                </View>
              </View>
            ) : null}
          </View>
        ) : null}
      </ScrollView>

      {/* Sticky Bottom Floating Booking Bar */}
      <View
        style={[
          styles.stickyBottomBar,
          {
            backgroundColor: colors.card,
            borderTopColor: colors.border,
            paddingBottom: Math.max(insets.bottom, 12),
          },
          shadow.md,
        ]}
      >
        <View style={styles.stickyPriceWrap}>
          <Text style={[styles.stickyPriceLabel, { color: colors.textMute }]}>
            from
          </Text>
          <View style={{ flexDirection: 'row', alignItems: 'baseline', gap: 4 }}>
            <Text style={[styles.stickyPriceText, { color: colors.accent }]}>
              {formatPrice(packageData.price)}
            </Text>
            <Text style={[styles.stickyPriceUnit, { color: colors.textSub }]}>
              / single bundle
            </Text>
          </View>
        </View>

        <View style={styles.stickyActionsRow}>
          <TouchableOpacity
            style={[styles.bookMainBtn, { backgroundColor: colors.primary }]}
            onPress={handleStartBooking}
            activeOpacity={0.85}
          >
            <Ionicons name="calendar-outline" size={17} color="#FFFFFF" />
            <Text style={styles.bookMainBtnText}>Book Package</Text>
          </TouchableOpacity>
        </View>
      </View>

      {/* ========================================================================= */}
      {/* BOOKING MODAL                                                             */}
      {/* ========================================================================= */}
      <Modal
        visible={bookingVisible}
        animationType="slide"
        transparent
        onRequestClose={() => setBookingVisible(false)}
      >
        <View style={styles.modalBackdrop}>
          <View
            style={[
              styles.modalSheet,
              {
                backgroundColor: colors.card,
                paddingBottom: Math.max(insets.bottom, 20),
                maxHeight: '90%',
              },
            ]}
          >
            {/* Modal Header */}
            <View style={[styles.modalHeader, { borderBottomColor: colors.border }]}>
              <View style={{ flexDirection: 'row', alignItems: 'center', flex: 1, minWidth: 0 }}>
                {bookingStep !== 'form' && bookingStep !== 'confirmed' ? (
                  <TouchableOpacity
                    onPress={() => {
                      if (bookingStep === 'qr' || bookingStep === 'card') {
                        setBookingStep('method');
                      } else {
                        setBookingStep('form');
                      }
                    }}
                    style={{ marginRight: 10, padding: 2 }}
                    hitSlop={8}
                    accessibilityLabel="Back"
                  >
                    <Ionicons name="chevron-back" size={24} color={colors.text} />
                  </TouchableOpacity>
                ) : null}
                <View style={{ flex: 1, minWidth: 0 }}>
                  <Text style={[styles.modalTitle, { color: colors.text }]} numberOfLines={1}>
                    {bookingStep === 'form'
                      ? 'Reserve Package'
                      : bookingStep === 'method'
                      ? 'Payment method'
                      : bookingStep === 'qr'
                      ? 'Scan to pay'
                      : bookingStep === 'card'
                      ? 'Card payment'
                      : 'Booking Confirmed!'}
                  </Text>
                  {bookingStep === 'form' ? (
                    <Text style={[styles.modalSubtitle, { color: colors.textSub }]} numberOfLines={1}>
                      {packageData.title}
                    </Text>
                  ) : null}
                </View>
              </View>
              <TouchableOpacity
                onPress={() => setBookingVisible(false)}
                style={[styles.modalCloseBtn, { backgroundColor: colors.chipBg }]}
                hitSlop={8}
                accessibilityLabel="Close"
              >
                <Ionicons name="close" size={20} color={colors.text} />
              </TouchableOpacity>
            </View>

            <ScrollView
              showsVerticalScrollIndicator={false}
              keyboardShouldPersistTaps="handled"
              contentContainerStyle={{ padding: 16 }}
            >
              {/* STEP 1: FORM */}
              {bookingStep === 'form' ? (
                <View>
                  {/* Select Guests */}
                  <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
                    <Text style={[styles.formLabel, { color: colors.text, marginBottom: 0 }]}>Number of Guests</Text>
                    <Text style={{ fontSize: 12, color: colors.textSub, fontWeight: '600' }}>
                      Min: {minPax} pax · Max: {maxPax} pax
                    </Text>
                  </View>
                  <View style={[styles.stepperWrap, { backgroundColor: colors.cardAlt, borderColor: colors.border, marginTop: 8 }]}>
                    <TouchableOpacity
                      style={[styles.stepperBtn, { backgroundColor: colors.chipBg, opacity: guestsCount <= minPax ? 0.35 : 1 }]}
                      disabled={guestsCount <= minPax}
                      onPress={() => setGuestsCount((c) => Math.max(minPax, c - 1))}
                    >
                      <Ionicons name="remove" size={18} color={colors.text} />
                    </TouchableOpacity>
                    <View style={styles.stepperCountWrap}>
                      <Text style={[styles.stepperCountText, { color: colors.text }]}>{guestsCount}</Text>
                      <Text style={[styles.stepperGuestLabel, { color: colors.textMute }]}>
                        {guestsCount === 1 ? 'Guest' : 'Guests'} (Capacity: {minPax}–{maxPax})
                      </Text>
                    </View>
                    <TouchableOpacity
                      style={[styles.stepperBtn, { backgroundColor: colors.chipBg, opacity: guestsCount >= maxPax ? 0.35 : 1 }]}
                      disabled={guestsCount >= maxPax}
                      onPress={() => setGuestsCount((c) => Math.min(maxPax, c + 1))}
                    >
                      <Ionicons name="add" size={18} color={colors.text} />
                    </TouchableOpacity>
                  </View>

                  {/* Travel Start Date */}
                  <Text style={[styles.formLabel, { color: colors.text, marginTop: 18 }]}>
                    Select Start Date
                  </Text>
                  <View style={[styles.calendarWrap, { borderColor: colors.border }]}>
                    <CalendarPicker
                      value={travelDate}
                      onChange={(d) => setTravelDate(d)}
                      theme={calendarTheme}
                    />
                  </View>

                  {/* Contact Info */}
                  <Text style={[styles.formLabel, { color: colors.text, marginTop: 18 }]}>Lead Guest Name</Text>
                  <TextInput
                    style={[styles.input, { backgroundColor: colors.cardAlt, borderColor: colors.border, color: colors.text }]}
                    value={contactName}
                    onChangeText={setContactName}
                    placeholder="Enter full name"
                    placeholderTextColor={colors.textMute}
                  />

                  <Text style={[styles.formLabel, { color: colors.text, marginTop: 14 }]}>Contact Mobile Number</Text>
                  <TextInput
                    style={[styles.input, { backgroundColor: colors.cardAlt, borderColor: colors.border, color: colors.text }]}
                    value={contactPhone}
                    onChangeText={setContactPhone}
                    placeholder="e.g. 0917 123 4567"
                    placeholderTextColor={colors.textMute}
                    keyboardType="phone-pad"
                  />

                  <Text style={[styles.formLabel, { color: colors.text, marginTop: 14 }]}>
                    Special Requests / Notes for Guide (Optional)
                  </Text>
                  <TextInput
                    style={[styles.input, styles.textArea, { backgroundColor: colors.cardAlt, borderColor: colors.border, color: colors.text }]}
                    value={specialNotes}
                    onChangeText={setSpecialNotes}
                    placeholder="Dietary preferences, hotel pickup location, flight arrival time..."
                    placeholderTextColor={colors.textMute}
                    multiline
                    numberOfLines={3}
                  />

                  {/* Pricing Summary */}
                  <View style={[styles.calcSummaryBox, { backgroundColor: colors.cardAlt, borderColor: colors.border }]}>
                    <View style={styles.calcRow}>
                      <Text style={[styles.calcLabel, { color: colors.textSub }]}>
                        Package Base Price ({guestsCount} guests)
                      </Text>
                      <Text style={[styles.calcValue, { color: colors.text, fontWeight: '700' }]}>
                        {formatPrice(packageBasePrice)}
                      </Text>
                    </View>
                    <View style={styles.calcRow}>
                      <Text style={[styles.calcLabel, { color: colors.textSub }]}>
                        Applicable Taxes (5%)
                      </Text>
                      <Text style={[styles.calcValue, { color: colors.textSub }]}>
                        {formatPrice(taxes)}
                      </Text>
                    </View>
                    <View style={styles.calcRow}>
                      <Text style={[styles.calcLabel, { color: colors.textSub }]}>
                        Environmental Fees & Permits
                      </Text>
                      <Text style={[styles.calcValue, { color: colors.textSub }]}>
                        {formatPrice(environmentalFees)}
                      </Text>
                    </View>
                    <View style={[styles.calcDivider, { backgroundColor: colors.border }]} />
                    <View style={styles.calcRow}>
                      <Text style={[styles.calcTotalLabel, { color: colors.text }]}>Estimated Total</Text>
                      <Text style={[styles.calcTotalValue, { color: colors.accent }]}>
                        {formatPrice(subtotal)}
                      </Text>
                    </View>
                  </View>

                  <TouchableOpacity
                    style={[styles.primaryActionBtn, { backgroundColor: colors.primary }]}
                    onPress={handleProceedToPayment}
                  >
                    <Text style={styles.primaryActionBtnText}>Proceed to Payment</Text>
                  </TouchableOpacity>
                </View>
              ) : null}

              {/* STEP 2: PAYMENT METHOD (Matching Reference Image) */}
              {bookingStep === 'method' ? (
                <View>
                  <Text style={[styles.payTotal, { color: colors.text }]}>
                    Amount to pay: <Text style={{ color: colors.primary }}>{formatPrice(finalPayment)}</Text>
                  </Text>

                  {/* Voucher Card */}
                  <View style={[styles.voucherCard, { borderColor: colors.border, backgroundColor: colors.cardAlt }]}>
                    {appliedVoucher ? (
                      <View style={styles.voucherApplied}>
                        <View style={[styles.voucherAppliedIcon, { backgroundColor: isDark ? 'rgba(15, 169, 104, 0.2)' : '#DCFCE7' }]}>
                          <Ionicons name="checkmark-circle" size={20} color={colors.primary} />
                        </View>
                        <View style={{ flex: 1, minWidth: 0 }}>
                          <Text style={[styles.voucherSummaryTitle, { color: colors.text }]}>
                            {appliedVoucher.code} ({appliedVoucher.label})
                          </Text>
                          <Text style={[styles.voucherSummaryText, { color: colors.primary }]} numberOfLines={1}>
                            Saved {formatPrice(appliedVoucher.discount)}!
                          </Text>
                        </View>
                        <TouchableOpacity
                          onPress={() => {
                            setAppliedVoucher(null);
                            setVoucherCodeInput('');
                            setVoucherError('');
                          }}
                          hitSlop={8}
                          activeOpacity={0.85}
                        >
                          <Text style={[styles.voucherClear, { color: colors.accent }]}>Remove</Text>
                        </TouchableOpacity>
                      </View>
                    ) : (
                      <>
                        <TouchableOpacity
                          style={styles.voucherToggle}
                          activeOpacity={0.85}
                          onPress={() => setVoucherExpanded((v) => !v)}
                        >
                          <Ionicons name="pricetag-outline" size={20} color={colors.primary} />
                          <View style={{ flex: 1, minWidth: 0, marginHorizontal: 12 }}>
                            <Text style={[styles.voucherToggleTitle, { color: colors.text }]}>Have a voucher?</Text>
                            <Text style={[styles.voucherToggleSub, { color: colors.textSub }]} numberOfLines={1}>
                              Enter a Sale tab code
                            </Text>
                          </View>
                          <Ionicons name={voucherExpanded ? 'chevron-up' : 'chevron-down'} size={18} color={colors.textSub} />
                        </TouchableOpacity>

                        {voucherExpanded ? (
                          <View style={styles.voucherEntryRow}>
                            <View style={[styles.voucherInputWrap, { backgroundColor: colors.card, borderColor: voucherError ? colors.danger : colors.border }]}>
                              <TextInput
                                style={[styles.voucherInput, { color: colors.text }]}
                                placeholder="e.g. CEBU6"
                                placeholderTextColor={colors.textMute}
                                value={voucherCodeInput}
                                onChangeText={(t) => {
                                  setVoucherCodeInput(t);
                                  setVoucherError('');
                                }}
                                autoCapitalize="characters"
                                autoCorrect={false}
                                returnKeyType="done"
                                onSubmitEditing={handleApplyVoucher}
                              />
                            </View>
                            <TouchableOpacity
                              style={[styles.voucherApplyBtn, { backgroundColor: colors.primary }]}
                              activeOpacity={0.85}
                              onPress={handleApplyVoucher}
                              disabled={isValidatingVoucher || !voucherCodeInput.trim()}
                            >
                              {isValidatingVoucher ? (
                                <ActivityIndicator size="small" color="#FFFFFF" />
                              ) : (
                                <Text style={styles.voucherApplyText}>Apply</Text>
                              )}
                            </TouchableOpacity>
                          </View>
                        ) : null}
                      </>
                    )}

                    {voucherError ? (
                      <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6, paddingHorizontal: 12, paddingBottom: 10 }}>
                        <Ionicons name="alert-circle" size={14} color={colors.danger} />
                        <Text style={{ color: colors.danger, fontSize: 12, fontWeight: '600' }}>{voucherError}</Text>
                      </View>
                    ) : null}
                  </View>

                  {/* Method 1: GCash / e-Wallet */}
                  <TouchableOpacity
                    style={[styles.methodRow, { borderColor: colors.border, backgroundColor: colors.cardAlt }]}
                    activeOpacity={0.8}
                    onPress={() => {
                      setQrMethod('gcash');
                      setPaymentReference('');
                      setBookingStep('qr');
                    }}
                  >
                    <View style={[styles.methodIcon, { backgroundColor: '#E0EDFF' }]}>
                      <Ionicons name="qr-code" size={22} color="#1d4ed8" />
                    </View>
                    <View style={{ flex: 1 }}>
                      <Text style={[styles.methodTitle, { color: colors.text }]}>GCash / e-Wallet</Text>
                      <Text style={[styles.methodSub, { color: colors.textSub }]}>Scan the QR with your e-wallet app</Text>
                    </View>
                    <Ionicons name="chevron-forward" size={20} color={colors.textSub} />
                  </TouchableOpacity>

                  {/* Method 2: InstaPay */}
                  <TouchableOpacity
                    style={[styles.methodRow, { borderColor: colors.border, backgroundColor: colors.cardAlt }]}
                    activeOpacity={0.8}
                    onPress={() => {
                      setQrMethod('instapay');
                      setPaymentReference('');
                      setBookingStep('qr');
                    }}
                  >
                    <View style={[styles.methodIcon, { backgroundColor: '#FEE2E2' }]}>
                      <Ionicons name="qr-code" size={22} color="#dc2626" />
                    </View>
                    <View style={{ flex: 1 }}>
                      <Text style={[styles.methodTitle, { color: colors.text }]}>InstaPay</Text>
                      <Text style={[styles.methodSub, { color: colors.textSub }]}>Scan with any bank app via InstaPay</Text>
                    </View>
                    <Ionicons name="chevron-forward" size={20} color={colors.textSub} />
                  </TouchableOpacity>

                  {/* Method 3: Debit / Credit Card */}
                  <TouchableOpacity
                    style={[styles.methodRow, { borderColor: colors.border, backgroundColor: colors.cardAlt }]}
                    activeOpacity={0.8}
                    onPress={() => setBookingStep('card')}
                  >
                    <View style={[styles.methodIcon, { backgroundColor: '#D1FAE5' }]}>
                      <Ionicons name="card" size={22} color="#059669" />
                    </View>
                    <View style={{ flex: 1 }}>
                      <Text style={[styles.methodTitle, { color: colors.text }]}>Debit / Credit Card</Text>
                      <Text style={[styles.methodSub, { color: colors.textSub }]}>Visa, Mastercard, etc.</Text>
                    </View>
                    <Ionicons name="chevron-forward" size={20} color={colors.textSub} />
                  </TouchableOpacity>
                </View>
              ) : null}

              {/* STEP 3a: QR PAYMENT */}
              {bookingStep === 'qr' ? (
                <View>
                  <Text style={[styles.payTotal, { color: colors.text }]}>
                    Pay <Text style={{ color: colors.primary }}>{formatPrice(finalPayment)}</Text> via{' '}
                    {qrMethod === 'gcash' ? 'GCash / e-Wallet' : 'InstaPay'}
                  </Text>

                  <View style={styles.qrWrap}>
                    <Image
                      source={qrMethod === 'gcash' ? QR_EWALLET : QR_INSTAPAY}
                      style={styles.qrImage}
                      resizeMode="contain"
                    />
                  </View>
                  <Text style={[styles.qrHint, { color: colors.textSub }]}>
                    Open your app, scan this QR, pay the exact amount, then enter your reference number below.
                  </Text>

                  <Text style={[styles.modalLabel, { color: colors.textSub }]}>Reference / confirmation no.</Text>
                  <TextInput
                    style={[
                      styles.input,
                      {
                        backgroundColor: colors.cardAlt,
                        borderColor: colors.border,
                        color: colors.text,
                        marginBottom: 16,
                      },
                    ]}
                    placeholder="e.g. 1234567890"
                    placeholderTextColor={colors.textMute}
                    value={paymentReference}
                    onChangeText={setPaymentReference}
                    autoCapitalize="characters"
                  />

                  <TouchableOpacity
                    style={[styles.confirmBtn, { backgroundColor: colors.primary }, submitting && { opacity: 0.6 }]}
                    onPress={confirmQrPaid}
                    disabled={submitting}
                    activeOpacity={0.85}
                  >
                    {submitting ? (
                      <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8 }}>
                        <ActivityIndicator size="small" color="#FFFFFF" />
                        <Text style={styles.confirmBtnText}>Verifying payment...</Text>
                      </View>
                    ) : (
                      <Text style={styles.confirmBtnText}>I&apos;ve paid</Text>
                    )}
                  </TouchableOpacity>
                </View>
              ) : null}

              {/* STEP 3b: CARD PAYMENT */}
              {bookingStep === 'card' ? (
                <View>
                  <Text style={[styles.payTotal, { color: colors.text }]}>
                    Pay <Text style={{ color: colors.primary }}>{formatPrice(finalPayment)}</Text>
                  </Text>

                  <Text style={[styles.modalLabel, { color: colors.textSub }]}>Cardholder name</Text>
                  <TextInput
                    style={[
                      styles.input,
                      {
                        backgroundColor: colors.cardAlt,
                        borderColor: colors.border,
                        color: colors.text,
                        marginBottom: 12,
                      },
                    ]}
                    placeholder="Name on card"
                    placeholderTextColor={colors.textMute}
                    value={cardName}
                    onChangeText={setCardName}
                    autoCapitalize="words"
                  />

                  <Text style={[styles.modalLabel, { color: colors.textSub }]}>Card number</Text>
                  <TextInput
                    style={[
                      styles.input,
                      {
                        backgroundColor: colors.cardAlt,
                        borderColor: colors.border,
                        color: colors.text,
                        marginBottom: 12,
                      },
                    ]}
                    placeholder="1234 5678 9012 3456"
                    placeholderTextColor={colors.textMute}
                    value={cardNumber}
                    onChangeText={formatCardNumber}
                    keyboardType="number-pad"
                  />

                  <View style={styles.cardRow}>
                    <View style={{ flex: 1, marginRight: 10 }}>
                      <Text style={[styles.modalLabel, { color: colors.textSub }]}>Expiry (MM/YY)</Text>
                      <TextInput
                        style={[
                          styles.input,
                          {
                            backgroundColor: colors.cardAlt,
                            borderColor: colors.border,
                            color: colors.text,
                          },
                        ]}
                        placeholder="MM/YY"
                        placeholderTextColor={colors.textMute}
                        value={cardExpiry}
                        onChangeText={formatExpiry}
                        keyboardType="number-pad"
                      />
                    </View>
                    <View style={{ flex: 1 }}>
                      <Text style={[styles.modalLabel, { color: colors.textSub }]}>CVV</Text>
                      <TextInput
                        style={[
                          styles.input,
                          {
                            backgroundColor: colors.cardAlt,
                            borderColor: colors.border,
                            color: colors.text,
                          },
                        ]}
                        placeholder="123"
                        placeholderTextColor={colors.textMute}
                        value={cardCvv}
                        onChangeText={(t) => setCardCvv(t.replace(/\D/g, '').slice(0, 4))}
                        keyboardType="number-pad"
                        secureTextEntry
                      />
                    </View>
                  </View>

                  <TouchableOpacity
                    style={[
                      styles.confirmBtn,
                      { backgroundColor: colors.primary, marginTop: 18 },
                      submitting && { opacity: 0.6 },
                    ]}
                    onPress={confirmCardPaid}
                    disabled={submitting}
                    activeOpacity={0.85}
                  >
                    {submitting ? (
                      <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8 }}>
                        <ActivityIndicator size="small" color="#FFFFFF" />
                        <Text style={styles.confirmBtnText}>Processing payment...</Text>
                      </View>
                    ) : (
                      <Text style={styles.confirmBtnText}>{`Pay ${formatPrice(finalPayment)}`}</Text>
                    )}
                  </TouchableOpacity>
                </View>
              ) : null}

              {/* STEP 3: CONFIRMED */}
              {bookingStep === 'confirmed' && bookingResult ? (
                <View style={{ alignItems: 'center', paddingVertical: 10 }}>
                  <View style={[styles.successIconCircle, { backgroundColor: colors.primary }]}>
                    <Ionicons name="checkmark" size={36} color="#FFFFFF" />
                  </View>

                  <Text style={[styles.successTitle, { color: colors.text }]}>Booking Confirmed!</Text>
                  <Text style={[styles.successRef, { color: colors.primary }]}>
                    Ref #{bookingResult.bookingId}
                  </Text>

                  <Text style={[styles.successDesc, { color: colors.textSub }]}>
                    {bookingResult.message}
                  </Text>

                  {/* Booking Receipt Summary */}
                  <View style={[styles.receiptCard, { backgroundColor: colors.cardAlt, borderColor: colors.border }]}>
                    <View style={styles.receiptRow}>
                      <Text style={[styles.receiptLabel, { color: colors.textMute }]}>Package:</Text>
                      <Text style={[styles.receiptVal, { color: colors.text }]} numberOfLines={1}>
                        {bookingResult.packageTitle}
                      </Text>
                    </View>
                    <View style={styles.receiptRow}>
                      <Text style={[styles.receiptLabel, { color: colors.textMute }]}>Start Date:</Text>
                      <Text style={[styles.receiptVal, { color: colors.text }]}>{bookingResult.travelDate}</Text>
                    </View>
                    <View style={styles.receiptRow}>
                      <Text style={[styles.receiptLabel, { color: colors.textMute }]}>Guests:</Text>
                      <Text style={[styles.receiptVal, { color: colors.text }]}>{bookingResult.guestsCount} Persons</Text>
                    </View>
                    <View style={styles.receiptRow}>
                      <Text style={[styles.receiptLabel, { color: colors.textMute }]}>Guide Host:</Text>
                      <Text style={[styles.receiptVal, { color: colors.primary, fontWeight: '700' }]}>
                        {bookingResult.guideName}
                      </Text>
                    </View>
                    <View style={styles.receiptRow}>
                      <Text style={[styles.receiptLabel, { color: colors.textMute }]}>Total Paid:</Text>
                      <Text style={[styles.receiptVal, { color: colors.accent, fontWeight: '800' }]}>
                        {formatPrice(bookingResult.totalPrice)}
                      </Text>
                    </View>
                  </View>

                  <View style={{ width: '100%', gap: 10, marginTop: 20 }}>
                    <TouchableOpacity
                      style={[styles.primaryActionBtn, { backgroundColor: colors.primary }]}
                      onPress={() => {
                        setBookingVisible(false);
                        router.replace('/(tabs)/trips');
                      }}
                    >
                      <Ionicons name="briefcase" size={16} color="#FFFFFF" />
                      <Text style={styles.primaryActionBtnText}>View in My Trips</Text>
                    </TouchableOpacity>

                    <TouchableOpacity
                      style={[styles.primaryActionBtn, { backgroundColor: colors.accent }]}
                      onPress={() => {
                        setBookingVisible(false);
                        handleMessageGuide();
                      }}
                    >
                      <Ionicons name="chatbubble-ellipses" size={16} color="#FFFFFF" />
                      <Text style={styles.primaryActionBtnText}>Message Guide Host</Text>
                    </TouchableOpacity>

                    <TouchableOpacity
                      style={[styles.backStepBtn, { width: '100%', borderColor: colors.border }]}
                      onPress={() => setBookingVisible(false)}
                    >
                      <Text style={[styles.backStepBtnText, { color: colors.text }]}>Done</Text>
                    </TouchableOpacity>
                  </View>
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
  centerContainer: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: 24,
  },
  loadingText: { marginTop: 12, fontSize: 15, fontWeight: '500' },
  errorTitle: { fontSize: 18, fontWeight: '700', marginTop: 12, marginBottom: 16 },
  retryBtn: { paddingVertical: 10, paddingHorizontal: 22, borderRadius: 20 },
  retryBtnText: { color: '#FFFFFF', fontWeight: '700' },

  // Hero Cover Photo Slider (Matching Things to Do & Reference Image 2)
  heroContainer: {
    position: 'relative',
    width: '100%',
    height: 340,
    backgroundColor: '#0B0D12',
    overflow: 'hidden',
  },
  heroImage: { width: '100%', height: 340, resizeMode: 'cover' },
  heroFallback: { width: '100%', height: 340, alignItems: 'center', justifyContent: 'center' },
  heroOverlay: {
    ...StyleSheet.absoluteFill,
  },
  backButton: {
    position: 'absolute',
    left: 16,
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: 'rgba(0,0,0,0.4)',
    alignItems: 'center',
    justifyContent: 'center',
    zIndex: 10,
  },
  favButton: {
    position: 'absolute',
    right: 16,
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: 'rgba(0,0,0,0.4)',
    alignItems: 'center',
    justifyContent: 'center',
    zIndex: 10,
  },
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
  heroTitle: { color: '#FFFFFF', fontSize: 24, fontWeight: '900', letterSpacing: -0.5, lineHeight: 30 },

  // Main Section (About This Experience - Matching Reference Image)
  mainSection: {
    marginHorizontal: 16,
    marginTop: 16,
    borderRadius: 22,
    padding: 18,
    borderWidth: 1,
  },
  specsGrid: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-around',
    paddingVertical: 14,
    borderRadius: 16,
    borderWidth: 1,
  },
  specItem: { alignItems: 'center', justifyContent: 'center', flex: 1 },
  specDivider: { width: 1, height: 32 },
  specLabel: { fontSize: 10, fontWeight: '800', letterSpacing: 0.8, textTransform: 'uppercase', marginTop: 5 },
  specValue: { fontSize: 13, fontWeight: '800', marginTop: 2 },

  descBlock: { marginTop: 18 },
  sectionHeading: { fontSize: 17, fontWeight: '800', letterSpacing: -0.2 },
  descriptionText: { fontSize: 13.5, lineHeight: 22, marginTop: 8 },

  tagsContainer: {
    marginTop: 18,
    paddingTop: 14,
    borderTopWidth: 1,
  },
  tagsLabel: {
    fontSize: 11,
    fontWeight: '800',
    letterSpacing: 0.8,
    textTransform: 'uppercase',
    marginBottom: 10,
  },
  tagsRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  tagPill: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 20,
    borderWidth: 1,
  },
  tagText: {
    fontSize: 12,
    fontWeight: '700',
  },

  // Tour Guide Card (Matching Image 2)
  guideCard: {
    marginHorizontal: 16,
    marginTop: 14,
    borderRadius: 22,
    padding: 18,
    borderWidth: 1,
  },
  guideCategorySubtitle: {
    fontSize: 12.5,
    fontWeight: '600',
    marginBottom: 10,
  },
  guideCardHeader: { flexDirection: 'row', alignItems: 'center' },
  guideAvatar: { width: 56, height: 56, borderRadius: 28, borderWidth: 1.5, alignItems: 'center', justifyContent: 'center' },
  guideName: { fontSize: 17, fontWeight: '800' },
  verifiedCheckBadge: {
    width: 17,
    height: 17,
    borderRadius: 9,
    alignItems: 'center',
    justifyContent: 'center',
    marginLeft: 6,
  },
  guideRole: { fontSize: 13, fontWeight: '500', marginTop: 2 },
  guideStats: { fontSize: 12, fontWeight: '600', marginTop: 4 },
  guideBioBox: {
    marginTop: 12,
    marginBottom: 14,
    padding: 12,
    borderRadius: 12,
    borderWidth: 1,
  },
  guideBio: { fontSize: 13, fontStyle: 'italic', lineHeight: 20 },
  guideActionRow: { marginTop: 2 },
  messageGuideBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    paddingVertical: 12,
    borderRadius: 14,
    borderWidth: 1.5,
  },
  messageGuideBtnText: { fontSize: 14, fontWeight: '700' },

  // Inclusions Section
  inclusionsSection: {
    marginHorizontal: 16,
    marginTop: 16,
    borderRadius: 22,
    padding: 18,
    borderWidth: 1,
  },
  inclusionsHeadingRow: { flexDirection: 'row', alignItems: 'center', marginBottom: 2 },
  inclusionsSubheading: { fontSize: 13, marginTop: 4, marginBottom: 16 },
  inclusionsList: { gap: 10 },
  inclusionCard: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 12,
    borderRadius: 16,
    borderWidth: 1,
  },
  incIconBox: {
    width: 42,
    height: 42,
    borderRadius: 12,
    alignItems: 'center',
    justifyContent: 'center',
  },
  incTitle: { fontSize: 14, fontWeight: '700', flex: 1 },
  incSubtitle: { fontSize: 12, marginTop: 3, lineHeight: 16 },
  incBadgePill: { paddingVertical: 3, paddingHorizontal: 8, borderRadius: 6, marginLeft: 8 },
  incBadgeText: { fontSize: 10, fontWeight: '800' },
  exclusionsBox: {
    marginTop: 16,
    paddingTop: 14,
    borderTopWidth: 1,
  },
  exclusionsTitle: { fontSize: 13, fontWeight: '700', marginBottom: 8 },
  exclusionItem: { flexDirection: 'row', alignItems: 'flex-start', gap: 8, marginBottom: 6 },
  exclusionText: { fontSize: 13, flex: 1, lineHeight: 18 },

  // Itinerary Section
  itinerarySection: {
    marginHorizontal: 16,
    marginTop: 16,
    borderRadius: 22,
    padding: 18,
    borderWidth: 1,
  },
  dayTabsScroll: { gap: 10, marginVertical: 14 },
  dayTab: {
    paddingVertical: 8,
    paddingHorizontal: 20,
    borderRadius: 20,
    borderWidth: 1,
  },
  dayTabText: { fontSize: 13.5, fontWeight: '700' },
  itineraryDayBody: { marginTop: 4 },
  itineraryDayTitle: { fontSize: 16, fontWeight: '800' },
  itineraryDayDesc: { fontSize: 13.5, marginTop: 6, lineHeight: 20 },

  timelineList: { marginTop: 16, paddingLeft: 4 },
  timelineItem: { flexDirection: 'row', marginBottom: 14 },
  timelineLeft: { alignItems: 'center', width: 22, marginRight: 10 },
  timelineDot: { width: 10, height: 10, borderRadius: 5, marginTop: 4 },
  timelineLine: { width: 2, flex: 1, marginTop: 4 },
  timelineRight: { flex: 1 },
  timelineTime: { fontSize: 12, fontWeight: '800' },
  timelineActivity: { fontSize: 13.5, fontWeight: '600', marginTop: 2 },

  highlightsBox: {
    marginTop: 16,
    padding: 14,
    borderRadius: 14,
    borderWidth: 1,
  },
  highlightsHeading: { fontSize: 13.5, fontWeight: '800', marginBottom: 8 },
  highlightBulletRow: { flexDirection: 'row', alignItems: 'flex-start', gap: 8, marginBottom: 6 },
  highlightBulletText: { fontSize: 13, flex: 1, lineHeight: 19 },

  // Specs Card (Hotel, Vehicle, Policies)
  specsCard: {
    marginHorizontal: 16,
    marginTop: 16,
    borderRadius: 22,
    padding: 18,
    borderWidth: 1,
  },
  specHotelName: { fontSize: 16, fontWeight: '800', marginTop: 8 },
  specRoomType: { fontSize: 13, marginTop: 2, marginBottom: 8 },
  partnerText: { fontSize: 12.5, fontWeight: '600', marginTop: 4, marginBottom: 12 },
  hotelDetailsBody: { marginTop: 6 },
  amenitiesWrap: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  amenityPill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingVertical: 6,
    paddingHorizontal: 12,
    borderRadius: 20,
    borderWidth: 1,
  },
  amenityText: { fontSize: 12, fontWeight: '600' },

  noBundledBanner: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 10,
    padding: 14,
    borderRadius: 14,
    borderWidth: 1,
    marginTop: 12,
  },
  noBundledText: { flex: 1, fontSize: 13, lineHeight: 19, fontWeight: '500' },

  vehicleDetailsBody: { marginTop: 6 },
  vehicleHeaderRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 8 },
  transmissionPill: {
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 20,
    borderWidth: 1,
  },
  transmissionText: { fontSize: 11, fontWeight: '700' },
  vehicleFeaturesWrap: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginTop: 4, marginBottom: 14 },
  vehicleFeaturePill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 5,
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 20,
    borderWidth: 1,
  },
  vehicleFeatureText: { fontSize: 11.5, fontWeight: '700' },
  handoverRow: { flexDirection: 'row', gap: 10, marginTop: 4 },
  handoverBox: { flex: 1, padding: 12, borderRadius: 12, borderWidth: 1 },
  handoverLabel: { fontSize: 10, fontWeight: '800', letterSpacing: 0.8, textTransform: 'uppercase', marginBottom: 4 },
  handoverVal: { fontSize: 13, fontWeight: '800' },

  policyBlock: { marginTop: 14 },
  policyHeaderLabel: { fontSize: 11, fontWeight: '800', letterSpacing: 0.8, textTransform: 'uppercase', marginBottom: 6 },
  policyBodyText: { fontSize: 13, lineHeight: 19 },
  requirementsList: { gap: 8, marginTop: 4 },
  requirementItem: { flexDirection: 'row', alignItems: 'flex-start', gap: 8 },
  requirementText: { fontSize: 13, flex: 1, lineHeight: 19 },

  // Sticky Bottom Bar
  stickyBottomBar: {
    position: 'absolute',
    bottom: 0,
    left: 0,
    right: 0,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 18,
    paddingTop: 12,
    borderTopWidth: 1,
  },
  stickyPriceWrap: { justifyContent: 'center' },
  stickyPriceLabel: { fontSize: 10, fontWeight: '800', letterSpacing: 0.8, textTransform: 'uppercase' },
  stickyPriceText: { fontSize: 22, fontWeight: '900', letterSpacing: -0.5 },
  stickyPriceUnit: { fontSize: 11, fontWeight: '600', marginLeft: 4 },
  stickyActionsRow: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  inquireCircleBtn: {
    width: 46,
    height: 46,
    borderRadius: 14,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  bookMainBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    paddingVertical: 13,
    paddingHorizontal: 22,
    borderRadius: 14,
  },
  bookMainBtnText: { color: '#FFFFFF', fontSize: 14.5, fontWeight: '800' },

  // Booking Modal
  modalBackdrop: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.6)',
    justifyContent: 'flex-end',
  },
  modalSheet: {
    borderTopLeftRadius: 28,
    borderTopRightRadius: 28,
    overflow: 'hidden',
  },
  modalHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 16,
    paddingVertical: 14,
    borderBottomWidth: 1,
  },
  modalTitle: { fontSize: 17, fontWeight: '800' },
  modalSubtitle: { fontSize: 12, marginTop: 2, maxWidth: 260 },
  modalCloseBtn: {
    width: 32,
    height: 32,
    borderRadius: 16,
    alignItems: 'center',
    justifyContent: 'center',
  },
  formLabel: { fontSize: 13, fontWeight: '700', marginBottom: 6 },
  stepperWrap: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    padding: 8,
    borderRadius: 14,
    borderWidth: 1,
  },
  stepperBtn: {
    width: 38,
    height: 38,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
  },
  stepperCountWrap: { alignItems: 'center' },
  stepperCountText: { fontSize: 18, fontWeight: '800' },
  stepperGuestLabel: { fontSize: 11 },
  calendarWrap: {
    borderRadius: 14,
    borderWidth: 1,
    overflow: 'hidden',
    padding: 8,
  },
  input: {
    height: 44,
    borderRadius: 12,
    borderWidth: 1,
    paddingHorizontal: 12,
    fontSize: 14,
  },
  textArea: {
    height: 72,
    paddingTop: 10,
    textAlignVertical: 'top',
  },
  calcSummaryBox: {
    marginTop: 18,
    padding: 14,
    borderRadius: 14,
    borderWidth: 1,
  },
  calcRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 6,
  },
  calcLabel: { fontSize: 12, fontWeight: '500' },
  calcValue: { fontSize: 13, fontWeight: '600' },
  calcDivider: { height: 1, marginVertical: 6 },
  calcTotalLabel: { fontSize: 14, fontWeight: '800' },
  calcTotalValue: { fontSize: 18, fontWeight: '900' },
  primaryActionBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    height: 48,
    borderRadius: 14,
    marginTop: 16,
  },
  primaryActionBtnText: { color: '#FFFFFF', fontSize: 15, fontWeight: '800' },
  payTotal: { fontSize: 16, fontWeight: '700', marginBottom: 16 },
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
  voucherInputWrap: { flex: 1, minWidth: 0, borderWidth: 1, borderRadius: 12, height: 44, justifyContent: 'center', paddingHorizontal: 10 },
  voucherInput: { fontSize: 14, fontWeight: '700' },
  voucherApplyBtn: { height: 44, paddingHorizontal: 18, borderRadius: 12, alignItems: 'center', justifyContent: 'center' },
  voucherApplyText: { color: '#FFFFFF', fontSize: 13, fontWeight: '700' },
  methodRow: {
    flexDirection: 'row',
    alignItems: 'center',
    borderWidth: 1,
    borderRadius: 16,
    padding: 14,
    marginBottom: 12,
  },
  methodIcon: {
    width: 44,
    height: 44,
    borderRadius: 12,
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 14,
  },
  methodTitle: { fontSize: 15, fontWeight: '700' },
  methodSub: { fontSize: 12, marginTop: 2 },
  qrWrap: {
    alignItems: 'center',
    backgroundColor: '#FFFFFF',
    borderRadius: 16,
    padding: 14,
    marginBottom: 12,
  },
  qrImage: { width: 220, height: 220 },
  qrHint: { fontSize: 13, lineHeight: 19, marginBottom: 14, textAlign: 'center' },
  modalLabel: { fontSize: 13, fontWeight: '600', marginBottom: 6 },
  cardRow: { flexDirection: 'row', marginBottom: 6 },
  confirmBtn: {
    height: 48,
    borderRadius: 14,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 16,
  },
  confirmBtnText: { color: '#FFFFFF', fontSize: 15, fontWeight: '700' },
  backStepBtn: {
    flex: 1,
    height: 48,
    borderRadius: 14,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 16,
  },
  backStepBtnText: { fontSize: 14, fontWeight: '700' },
  successIconCircle: {
    width: 64,
    height: 64,
    borderRadius: 32,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 12,
  },
  successTitle: { fontSize: 20, fontWeight: '900' },
  successRef: { fontSize: 14, fontWeight: '700', marginTop: 4 },
  successDesc: {
    fontSize: 13,
    textAlign: 'center',
    marginTop: 8,
    lineHeight: 18,
    paddingHorizontal: 16,
  },
  receiptCard: {
    width: '100%',
    padding: 14,
    borderRadius: 14,
    borderWidth: 1,
    marginTop: 16,
    gap: 8,
  },
  receiptRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  receiptLabel: { fontSize: 12 },
  receiptVal: { fontSize: 13, fontWeight: '600', maxWidth: '65%' },
});
