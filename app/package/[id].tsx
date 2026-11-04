import { Ionicons } from '@expo/vector-icons';
import { useLocalSearchParams, useRouter } from 'expo-router';
import React, { useCallback, useEffect, useMemo, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Dimensions,
  Image,
  Modal,
  ScrollView,
  StatusBar,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
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
import { API_BASE_URL } from '../../config';
import { api } from '../../services/api';
import { restoreSession } from '../../lib/authStore';
import { findVoucher, formatVoucherMinimum, getVoucherDiscount } from '../../lib/vouchers';

const { width } = Dimensions.get('window');

const QR_EWALLET = require('../../assets/images/payment/qr-ewallet.png');
const QR_INSTAPAY = require('../../assets/images/payment/qr-instapay.png');

type BookingStep = 'form' | 'payment' | 'confirmed';

export default function PackageDetailScreen() {
  const router = useRouter();
  const { id } = useLocalSearchParams<{ id: string }>();
  const insets = useSafeAreaInsets();
  const { colors, isDark, shadow, radius } = useTheme();
  const { formatPrice } = usePreferences();

  const [packageData, setPackageData] = useState<TravelPackage | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [activeImageIndex, setActiveImageIndex] = useState(0);
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
  const [payMethod, setPayMethod] = useState<'ewallet' | 'instapay' | 'card' | 'cash'>('ewallet');
  const [submitting, setSubmitting] = useState(false);
  const [bookingResult, setBookingResult] = useState<PackageBookingResponse | null>(null);

  // Voucher / Promo State
  const [voucherCodeInput, setVoucherCodeInput] = useState('');
  const [appliedVoucher, setAppliedVoucher] = useState<{ code: string; label: string; discount: number } | null>(null);
  const [voucherError, setVoucherError] = useState('');
  const [isValidatingVoucher, setIsValidatingVoucher] = useState(false);

  const loadData = useCallback(async () => {
    if (!id) return;
    setLoading(true);
    setError('');
    try {
      const pkg = await getPackageById(String(id));
      if (!pkg) {
        setError('Package not found.');
      } else {
        setPackageData(pkg);
      }
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load package details.');
    } finally {
      setLoading(false);
    }
  }, [id]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  // Calendar theme matching current app colors
  const calendarTheme = useMemo(
    () => ({
      textMain: colors.text,
      textSub: colors.textSub,
      accent: '#8B5CF6',
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

  const totalPrice = finalPayment;

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
    setBookingStep('payment');
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

  const handleConfirmBooking = async () => {
    if (!packageData) return;
    setSubmitting(true);
    try {
      const res = await bookTravelPackage({
        packageId: packageData.id,
        travelDate,
        guestsCount,
        contactName,
        contactPhone,
        notes: specialNotes,
        paymentMethod: payMethod,
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

  if (loading) {
    return (
      <SafeAreaView style={[styles.centerContainer, { backgroundColor: colors.bgAlt }]}>
        <ActivityIndicator size="large" color="#8B5CF6" />
        <Text style={[styles.loadingText, { color: colors.textSub }]}>Loading package details...</Text>
      </SafeAreaView>
    );
  }

  if (error || !packageData) {
    return (
      <SafeAreaView style={[styles.centerContainer, { backgroundColor: colors.bgAlt }]}>
        <Ionicons name="alert-circle-outline" size={48} color="#EF4444" />
        <Text style={[styles.errorTitle, { color: colors.text }]}>{error || 'Package not found'}</Text>
        <TouchableOpacity
          style={[styles.retryBtn, { backgroundColor: '#8B5CF6' }]}
          onPress={() => (error ? loadData() : router.back())}
        >
          <Text style={styles.retryBtnText}>{error ? 'Retry' : 'Go Back'}</Text>
        </TouchableOpacity>
      </SafeAreaView>
    );
  }

  const coverSource = getPackageCoverSource(packageData.localImageKey, packageData.coverImage);
  const activeItinerary = packageData.itinerary.find((d) => d.day === activeDay) || packageData.itinerary[0];

  return (
    <View style={[styles.container, { backgroundColor: colors.bgAlt }]}>
      <StatusBar barStyle="light-content" />

      <ScrollView showsVerticalScrollIndicator={false} contentContainerStyle={{ paddingBottom: 120 }}>
        {/* Top Hero Image Header */}
        <View style={styles.heroWrap}>
          <Image source={coverSource} style={styles.heroImage} />
          <View style={styles.heroGradientOverlay} />

          {/* Floating Navigation Controls */}
          <View style={[styles.heroNav, { top: insets.top + 10 }]}>
            <TouchableOpacity
              style={styles.heroCircleBtn}
              onPress={() => router.back()}
              activeOpacity={0.8}
            >
              <Ionicons name="arrow-back" size={20} color="#FFFFFF" />
            </TouchableOpacity>

            <View style={{ flexDirection: 'row', gap: 10 }}>
              <TouchableOpacity
                style={styles.heroCircleBtn}
                onPress={() => setIsSaved(!isSaved)}
                activeOpacity={0.8}
              >
                <Ionicons
                  name={isSaved ? 'heart' : 'heart-outline'}
                  size={20}
                  color={isSaved ? '#EF4444' : '#FFFFFF'}
                />
              </TouchableOpacity>
            </View>
          </View>

          {/* Hero Bottom Badges */}
          <View style={styles.heroBottomRow}>
            <View style={styles.heroCategoryPill}>
              <Ionicons name="cube" size={13} color="#FFFFFF" />
              <Text style={styles.heroCategoryText}>
                {packageData.categoryLabel.toUpperCase()} • {packageData.duration}
              </Text>
            </View>
            {packageData.badge ? (
              <View style={styles.heroSpecialPill}>
                <Ionicons name="sparkles" size={11} color="#FFFFFF" />
                <Text style={styles.heroSpecialText}>{packageData.badge.toUpperCase()}</Text>
              </View>
            ) : null}
          </View>
        </View>

        {/* Package Header Content */}
        <View style={[styles.mainSection, { backgroundColor: colors.card, borderColor: colors.border }, shadow.sm]}>
          <Text style={[styles.title, { color: colors.text }]}>{packageData.title}</Text>

          {/* Location & Rating */}
          <View style={styles.metaRow}>
            <View style={styles.locationWrap}>
              <Ionicons name="location-sharp" size={15} color="#8B5CF6" />
              <Text style={[styles.locationText, { color: colors.textSub }]}>{packageData.destination}</Text>
            </View>
            <View style={styles.ratingBadge}>
              <Ionicons name="star" size={14} color="#F59E0B" />
              <Text style={[styles.ratingNumber, { color: colors.text }]}>{packageData.rating.toFixed(1)}</Text>
              <Text style={[styles.reviewCount, { color: colors.textMute }]}>
                ({packageData.reviewCount} reviews)
              </Text>
            </View>
          </View>

          {/* Key Specs Grid */}
          <View style={[styles.specsGrid, { backgroundColor: isDark ? '#1C202B' : '#F8FAFC', borderColor: colors.border }]}>
            <View style={styles.specItem}>
              <Ionicons name="time-outline" size={18} color="#8B5CF6" />
              <Text style={[styles.specLabel, { color: colors.textMute }]}>Duration</Text>
              <Text style={[styles.specValue, { color: colors.text }]}>{packageData.duration}</Text>
            </View>
            <View style={styles.specDivider} />
            <View style={styles.specItem}>
              <Ionicons name="people-outline" size={18} color="#8B5CF6" />
              <Text style={[styles.specLabel, { color: colors.textMute }]}>Group Size</Text>
              <Text style={[styles.specValue, { color: colors.text }]}>Up to {packageData.maxGroupSize}</Text>
            </View>
            <View style={styles.specDivider} />
            <View style={styles.specItem}>
              <Ionicons name="shield-checkmark-outline" size={18} color="#10B981" />
              <Text style={[styles.specLabel, { color: colors.textMute }]}>Assurance</Text>
              <Text style={[styles.specValue, { color: '#10B981' }]}>Guaranteed</Text>
            </View>
          </View>

          {/* Summary / Description */}
          <View style={styles.descBlock}>
            <Text style={[styles.sectionHeading, { color: colors.text }]}>About This Package</Text>
            <Text style={[styles.descriptionText, { color: colors.textSub }]}>{packageData.description}</Text>
          </View>
        </View>

        {/* Tour Guide Organizer Card */}
        <View style={[styles.guideCard, { backgroundColor: colors.card, borderColor: colors.border }, shadow.sm]}>
          <View style={styles.guideCardHeader}>
            {packageData.host.avatar ? (
              <Image source={{ uri: packageData.host.avatar }} style={styles.guideAvatar} />
            ) : (
              <View style={[styles.guideAvatar, styles.guideAvatarFallback]}>
                <Ionicons name="person" size={24} color="#8B5CF6" />
              </View>
            )}
            <View style={{ flex: 1, marginLeft: 12 }}>
              <View style={{ flexDirection: 'row', alignItems: 'center' }}>
                <Text style={[styles.guideName, { color: colors.text }]}>{packageData.host.name}</Text>
                <Ionicons name="checkmark-circle" size={16} color="#10B981" style={{ marginLeft: 5 }} />
              </View>
              <Text style={[styles.guideRole, { color: colors.textSub }]}>{packageData.host.role}</Text>
              <Text style={[styles.guideStats, { color: '#8B5CF6' }]}>
                {packageData.host.badge} • {packageData.host.rating} ★ ({packageData.host.reviewCount} reviews) •{' '}
                {packageData.host.toursCount}+ tours hosted
              </Text>
            </View>
          </View>

          <Text style={[styles.guideBio, { color: colors.textSub }]}>"{packageData.host.bio}"</Text>

          <View style={styles.guideActionRow}>
            <TouchableOpacity
              style={[styles.messageGuideBtn, { borderColor: '#8B5CF6' }]}
              onPress={handleMessageGuide}
              activeOpacity={0.8}
            >
              <Ionicons name="chatbubble-ellipses-outline" size={16} color="#8B5CF6" />
              <Text style={styles.messageGuideBtnText}>Message Guide Host</Text>
            </TouchableOpacity>
          </View>
        </View>

        {/* All-in-One Inclusions Section */}
        <View style={[styles.inclusionsSection, { backgroundColor: colors.card, borderColor: colors.border }, shadow.sm]}>
          <View style={styles.inclusionsHeadingRow}>
            <Ionicons name="gift-outline" size={20} color="#8B5CF6" />
            <Text style={[styles.sectionHeading, { color: colors.text, marginLeft: 8 }]}>
              All-In-One Inclusions
            </Text>
          </View>
          <Text style={[styles.inclusionsSubheading, { color: colors.textSub }]}>
            Everything included in your single bundle reservation:
          </Text>

          <View style={styles.inclusionsList}>
            {packageData.inclusions.map((inc, i) => (
              <View key={i} style={[styles.inclusionCard, { backgroundColor: isDark ? '#1C202B' : '#F8FAFC' }]}>
                <View
                  style={[
                    styles.incIconBox,
                    {
                      backgroundColor:
                        inc.type === 'attraction'
                          ? '#FCE7F3'
                          : inc.type === 'activity'
                          ? '#EDE9FE'
                          : inc.type === 'accommodation'
                          ? '#FEF3C7'
                          : inc.type === 'rental'
                          ? '#E0EDFF'
                          : '#DCFCE7',
                    },
                  ]}
                >
                  <Ionicons
                    name={inc.icon as any}
                    size={18}
                    color={
                      inc.type === 'attraction'
                        ? '#DB2777'
                        : inc.type === 'activity'
                        ? '#7C3AED'
                        : inc.type === 'accommodation'
                        ? '#D97706'
                        : inc.type === 'rental'
                        ? '#2563EB'
                        : '#16A34A'
                    }
                  />
                </View>
                <View style={{ flex: 1, marginLeft: 12 }}>
                  <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }}>
                    <Text style={[styles.incTitle, { color: colors.text }]}>{inc.title}</Text>
                    <View
                      style={[
                        styles.incBadgePill,
                        {
                          backgroundColor:
                            inc.type === 'attraction'
                              ? '#FCE7F3'
                              : inc.type === 'activity'
                              ? '#EDE9FE'
                              : inc.type === 'accommodation'
                              ? '#FEF3C7'
                              : inc.type === 'rental'
                              ? '#E0EDFF'
                              : '#DCFCE7',
                        },
                      ]}
                    >
                      <Text
                        style={[
                          styles.incBadgeText,
                          {
                            color:
                              inc.type === 'attraction'
                                ? '#DB2777'
                                : inc.type === 'activity'
                                ? '#7C3AED'
                                : inc.type === 'accommodation'
                                ? '#D97706'
                                : inc.type === 'rental'
                                ? '#2563EB'
                                : '#16A34A',
                          },
                        ]}
                      >
                        {inc.badge}
                      </Text>
                    </View>
                  </View>
                  {inc.subtitle ? (
                    <Text style={[styles.incSubtitle, { color: colors.textSub }]}>{inc.subtitle}</Text>
                  ) : null}
                </View>
              </View>
            ))}
          </View>

          {/* Exclusions */}
          {packageData.exclusions && packageData.exclusions.length > 0 ? (
            <View style={styles.exclusionsBox}>
              <Text style={[styles.exclusionsTitle, { color: colors.textSub }]}>What's Not Included:</Text>
              {packageData.exclusions.map((exc, index) => (
                <View key={index} style={styles.exclusionItem}>
                  <Ionicons name="close-circle-outline" size={14} color="#EF4444" style={{ marginTop: 2 }} />
                  <Text style={[styles.exclusionText, { color: colors.textMute }]}>{exc}</Text>
                </View>
              ))}
            </View>
          ) : null}
        </View>

        {/* Day-by-Day Itinerary */}
        <View style={[styles.itinerarySection, { backgroundColor: colors.card, borderColor: colors.border }, shadow.sm]}>
          <View style={styles.inclusionsHeadingRow}>
            <Ionicons name="calendar-outline" size={20} color="#8B5CF6" />
            <Text style={[styles.sectionHeading, { color: colors.text, marginLeft: 8 }]}>
              Day-by-Day Itinerary
            </Text>
          </View>

          {/* Day Tabs */}
          {packageData.itinerary.length > 1 ? (
            <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.dayTabsScroll}>
              {packageData.itinerary.map((d) => {
                const isSelected = activeDay === d.day;
                return (
                  <TouchableOpacity
                    key={d.day}
                    style={[
                      styles.dayTab,
                      {
                        backgroundColor: isSelected ? '#8B5CF6' : isDark ? '#1C202B' : '#F1F5F9',
                        borderColor: isSelected ? '#8B5CF6' : colors.border,
                      },
                    ]}
                    onPress={() => setActiveDay(d.day)}
                  >
                    <Text style={[styles.dayTabText, { color: isSelected ? '#FFFFFF' : colors.text }]}>
                      Day {d.day}
                    </Text>
                  </TouchableOpacity>
                );
              })}
            </ScrollView>
          ) : null}

          {/* Selected Day Content */}
          <View style={styles.itineraryDayBody}>
            <Text style={[styles.itineraryDayTitle, { color: colors.text }]}>
              {activeItinerary.title}
            </Text>
            <Text style={[styles.itineraryDayDesc, { color: colors.textSub }]}>
              {activeItinerary.description}
            </Text>

            {/* Timed Schedule */}
            {activeItinerary.schedule && activeItinerary.schedule.length > 0 ? (
              <View style={styles.timelineList}>
                {activeItinerary.schedule.map((item, idx) => (
                  <View key={idx} style={styles.timelineItem}>
                    <View style={styles.timelineLeft}>
                      <View style={[styles.timelineDot, { backgroundColor: '#8B5CF6' }]} />
                      {idx < activeItinerary.schedule!.length - 1 ? (
                        <View style={[styles.timelineLine, { backgroundColor: colors.border }]} />
                      ) : null}
                    </View>
                    <View style={styles.timelineRight}>
                      <Text style={[styles.timelineTime, { color: '#8B5CF6' }]}>{item.time}</Text>
                      <Text style={[styles.timelineActivity, { color: colors.text }]}>{item.activity}</Text>
                    </View>
                  </View>
                ))}
              </View>
            ) : null}

            {/* Highlights */}
            <View style={styles.highlightsBox}>
              <Text style={[styles.highlightsHeading, { color: colors.text }]}>Day Highlights:</Text>
              {activeItinerary.highlights.map((hl, index) => (
                <View key={index} style={styles.highlightBulletRow}>
                  <Ionicons name="checkmark-sharp" size={14} color="#10B981" style={{ marginTop: 2 }} />
                  <Text style={[styles.highlightBulletText, { color: colors.textSub }]}>{hl}</Text>
                </View>
              ))}
            </View>
          </View>
        </View>

        {/* Accommodation Specs */}
        {packageData.accommodationDetail ? (
          <View style={[styles.specsCard, { backgroundColor: colors.card, borderColor: colors.border }, shadow.sm]}>
            <View style={styles.inclusionsHeadingRow}>
              <Ionicons name="bed-outline" size={20} color="#F59E0B" />
              <Text style={[styles.sectionHeading, { color: colors.text, marginLeft: 8 }]}>
                Included Hotel Details
              </Text>
            </View>
            <Text style={[styles.specHotelName, { color: colors.text }]}>
              {packageData.accommodationDetail.hotelName}
            </Text>
            <Text style={[styles.specRoomType, { color: colors.textSub }]}>
              {packageData.accommodationDetail.roomType} • {packageData.accommodationDetail.nights} Nights
            </Text>
            <View style={styles.amenitiesWrap}>
              {packageData.accommodationDetail.amenities.map((am, i) => (
                <View key={i} style={[styles.amenityPill, { backgroundColor: isDark ? '#232936' : '#FEF3C7' }]}>
                  <Ionicons name="checkmark-circle" size={12} color="#D97706" />
                  <Text style={[styles.amenityText, { color: isDark ? '#FEF3C7' : '#92400E' }]}>{am}</Text>
                </View>
              ))}
            </View>
          </View>
        ) : null}

        {/* Rental Vehicle Specs */}
        {packageData.rentalDetail ? (
          <View style={[styles.specsCard, { backgroundColor: colors.card, borderColor: colors.border }, shadow.sm]}>
            <View style={styles.inclusionsHeadingRow}>
              <Ionicons name="car-sport-outline" size={20} color="#3B82F6" />
              <Text style={[styles.sectionHeading, { color: colors.text, marginLeft: 8 }]}>
                Included Rental Vehicle
              </Text>
            </View>
            <Text style={[styles.specHotelName, { color: colors.text }]}>
              {packageData.rentalDetail.model}
            </Text>
            <Text style={[styles.specRoomType, { color: colors.textSub }]}>
              {packageData.rentalDetail.vehicleType} • {packageData.rentalDetail.duration}
            </Text>
            <View style={styles.amenitiesWrap}>
              {packageData.rentalDetail.features.map((feat, i) => (
                <View key={i} style={[styles.amenityPill, { backgroundColor: isDark ? '#1E293B' : '#E0EDFF' }]}>
                  <Ionicons name="checkmark-circle" size={12} color="#2563EB" />
                  <Text style={[styles.amenityText, { color: isDark ? '#93C5FD' : '#1E40AF' }]}>{feat}</Text>
                </View>
              ))}
            </View>
          </View>
        ) : null}

        {/* Cancellation & Policies */}
        {packageData.policy ? (
          <View style={[styles.specsCard, { backgroundColor: colors.card, borderColor: colors.border }, shadow.sm]}>
            <View style={styles.inclusionsHeadingRow}>
              <Ionicons name="document-text-outline" size={20} color="#10B981" />
              <Text style={[styles.sectionHeading, { color: colors.text, marginLeft: 8 }]}>
                Policies & Requirements
              </Text>
            </View>
            <View style={styles.policyRow}>
              <Text style={[styles.policyTitle, { color: colors.text }]}>Cancellation:</Text>
              <Text style={[styles.policyText, { color: colors.textSub }]}>{packageData.policy.cancellation}</Text>
            </View>
            <View style={styles.policyRow}>
              <Text style={[styles.policyTitle, { color: colors.text }]}>Requirements:</Text>
              {packageData.policy.requirements.map((req, i) => (
                <Text key={i} style={[styles.policyText, { color: colors.textSub, marginTop: 2 }]}>
                  • {req}
                </Text>
              ))}
            </View>
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
          <Text style={[styles.stickyPriceLabel, { color: colors.textMute }]}>Total Package Price</Text>
          <View style={{ flexDirection: 'row', alignItems: 'baseline', gap: 4 }}>
            <Text style={[styles.stickyPriceText, { color: colors.text }]}>
              {formatPrice(packageData.price)}
            </Text>
            <Text style={[styles.stickyPriceUnit, { color: colors.textMute }]}>Package Price</Text>
          </View>
        </View>

        <View style={styles.stickyActionsRow}>
          <TouchableOpacity
            style={[styles.inquireCircleBtn, { borderColor: colors.border }]}
            onPress={handleMessageGuide}
            activeOpacity={0.8}
          >
            <Ionicons name="chatbubble-ellipses-outline" size={20} color="#8B5CF6" />
          </TouchableOpacity>

          <TouchableOpacity
            style={[styles.bookMainBtn, { backgroundColor: '#8B5CF6' }]}
            onPress={handleStartBooking}
            activeOpacity={0.85}
          >
            <Text style={styles.bookMainBtnText}>Book Package</Text>
            <Ionicons name="arrow-forward" size={16} color="#FFFFFF" />
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
            <View style={styles.modalHeader}>
              <View>
                <Text style={[styles.modalTitle, { color: colors.text }]}>
                  {bookingStep === 'form'
                    ? 'Reserve Package'
                    : bookingStep === 'payment'
                    ? 'Review & Pay'
                    : 'Booking Confirmed!'}
                </Text>
                <Text style={[styles.modalSubtitle, { color: colors.textSub }]} numberOfLines={1}>
                  {packageData.title}
                </Text>
              </View>
              <TouchableOpacity
                onPress={() => setBookingVisible(false)}
                style={[styles.modalCloseBtn, { backgroundColor: isDark ? '#232936' : '#F1F5F9' }]}
              >
                <Ionicons name="close" size={20} color={colors.text} />
              </TouchableOpacity>
            </View>

            <ScrollView showsVerticalScrollIndicator={false} contentContainerStyle={{ padding: 16 }}>
              {/* STEP 1: FORM */}
              {bookingStep === 'form' ? (
                <View>
                  {/* Select Guests (Validated against minPax and maxPax) */}
                  <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
                    <Text style={[styles.formLabel, { color: colors.text, marginBottom: 0 }]}>Number of Guests</Text>
                    <Text style={{ fontSize: 12, color: colors.textSub, fontWeight: '600' }}>
                      Min: {minPax} pax · Max: {maxPax} pax
                    </Text>
                  </View>
                  <View style={[styles.stepperWrap, { backgroundColor: isDark ? '#1C202B' : '#F8FAFC', borderColor: colors.border, marginTop: 8 }]}>
                    <TouchableOpacity
                      style={[styles.stepperBtn, { opacity: guestsCount <= minPax ? 0.35 : 1 }]}
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
                      style={[styles.stepperBtn, { opacity: guestsCount >= maxPax ? 0.35 : 1 }]}
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
                    style={[styles.input, { backgroundColor: isDark ? '#1C202B' : '#F8FAFC', borderColor: colors.border, color: colors.text }]}
                    value={contactName}
                    onChangeText={setContactName}
                    placeholder="Enter full name"
                    placeholderTextColor={colors.textMute}
                  />

                  <Text style={[styles.formLabel, { color: colors.text, marginTop: 14 }]}>Contact Mobile Number</Text>
                  <TextInput
                    style={[styles.input, { backgroundColor: isDark ? '#1C202B' : '#F8FAFC', borderColor: colors.border, color: colors.text }]}
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
                    style={[styles.input, styles.textArea, { backgroundColor: isDark ? '#1C202B' : '#F8FAFC', borderColor: colors.border, color: colors.text }]}
                    value={specialNotes}
                    onChangeText={setSpecialNotes}
                    placeholder="Dietary preferences, hotel pickup location, flight arrival time..."
                    placeholderTextColor={colors.textMute}
                    multiline
                    numberOfLines={3}
                  />

                  {/* Section 5: Package Pricing Summary (Fixed Package Price) */}
                  <View style={[styles.calcSummaryBox, { backgroundColor: isDark ? '#231F3D' : '#F3EFFE', borderColor: '#C4B5FD' }]}>
                    <View style={styles.calcRow}>
                      <Text style={[styles.calcLabel, { color: isDark ? '#E9D5FF' : '#581C87' }]}>
                        Package Base Price ({guestsCount} guests)
                      </Text>
                      <Text style={[styles.calcValue, { color: isDark ? '#E9D5FF' : '#581C87', fontWeight: '700' }]}>
                        {formatPrice(packageBasePrice)}
                      </Text>
                    </View>
                    <View style={styles.calcRow}>
                      <Text style={[styles.calcLabel, { color: isDark ? '#C4B5FD' : '#6B21A8' }]}>
                        Applicable Taxes (5%)
                      </Text>
                      <Text style={[styles.calcValue, { color: isDark ? '#C4B5FD' : '#6B21A8' }]}>
                        {formatPrice(taxes)}
                      </Text>
                    </View>
                    <View style={styles.calcRow}>
                      <Text style={[styles.calcLabel, { color: isDark ? '#C4B5FD' : '#6B21A8' }]}>
                        Environmental Fees & Permits
                      </Text>
                      <Text style={[styles.calcValue, { color: isDark ? '#C4B5FD' : '#6B21A8' }]}>
                        {formatPrice(environmentalFees)}
                      </Text>
                    </View>
                    <View style={[styles.calcDivider, { backgroundColor: '#DDD6FE' }]} />
                    <View style={styles.calcRow}>
                      <Text style={[styles.calcTotalLabel, { color: isDark ? '#FFFFFF' : '#3B0764' }]}>Estimated Total</Text>
                      <Text style={[styles.calcTotalValue, { color: isDark ? '#FFFFFF' : '#3B0764' }]}>
                        {formatPrice(subtotal)}
                      </Text>
                    </View>
                  </View>

                  <TouchableOpacity
                    style={[styles.primaryActionBtn, { backgroundColor: '#8B5CF6' }]}
                    onPress={handleProceedToPayment}
                  >
                    <Text style={styles.primaryActionBtnText}>Proceed to Payment</Text>
                    <Ionicons name="arrow-forward" size={16} color="#FFFFFF" />
                  </TouchableOpacity>
                </View>
              ) : null}

              {/* STEP 2: PAYMENT & VOUCHER REDEMPTION */}
              {bookingStep === 'payment' ? (
                <View>
                  {/* Section 6: Enter Voucher Code */}
                  <View style={{ marginBottom: 16 }}>
                    <Text style={[styles.formLabel, { color: colors.text }]}>Enter Voucher Code</Text>
                    <View style={{ flexDirection: 'row', gap: 8, marginTop: 4 }}>
                      <TextInput
                        style={[
                          styles.input,
                          {
                            flex: 1,
                            backgroundColor: isDark ? '#1C202B' : '#F8FAFC',
                            borderColor: voucherError ? '#EF4444' : appliedVoucher ? '#10B981' : colors.border,
                            color: colors.text,
                            textTransform: 'uppercase',
                            fontWeight: '700',
                          },
                        ]}
                        value={voucherCodeInput}
                        onChangeText={(t) => {
                          setVoucherCodeInput(t);
                          setVoucherError('');
                        }}
                        placeholder="e.g. CEBU6, ISLAND300, STAY10"
                        placeholderTextColor={colors.textMute}
                        autoCapitalize="characters"
                        editable={!appliedVoucher}
                      />
                      {appliedVoucher ? (
                        <TouchableOpacity
                          style={{
                            backgroundColor: '#EF4444',
                            borderRadius: 10,
                            paddingHorizontal: 16,
                            justifyContent: 'center',
                            alignItems: 'center',
                          }}
                          onPress={() => {
                            setAppliedVoucher(null);
                            setVoucherCodeInput('');
                            setVoucherError('');
                          }}
                        >
                          <Text style={{ color: '#FFFFFF', fontWeight: '700', fontSize: 13 }}>Remove</Text>
                        </TouchableOpacity>
                      ) : (
                        <TouchableOpacity
                          style={{
                            backgroundColor: '#8B5CF6',
                            borderRadius: 10,
                            paddingHorizontal: 18,
                            justifyContent: 'center',
                            alignItems: 'center',
                          }}
                          onPress={handleApplyVoucher}
                          disabled={isValidatingVoucher || !voucherCodeInput.trim()}
                        >
                          {isValidatingVoucher ? (
                            <ActivityIndicator size="small" color="#FFFFFF" />
                          ) : (
                            <Text style={{ color: '#FFFFFF', fontWeight: '700', fontSize: 13 }}>Apply</Text>
                          )}
                        </TouchableOpacity>
                      )}
                    </View>

                    {appliedVoucher ? (
                      <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6, marginTop: 6, backgroundColor: isDark ? '#064E3B22' : '#ECFDF5', padding: 8, borderRadius: 8, borderWidth: 1, borderColor: '#A7F3D0' }}>
                        <Ionicons name="checkmark-circle" size={15} color="#10B981" />
                        <Text style={{ color: '#059669', fontSize: 12, fontWeight: '700' }}>
                          ✓ Voucher applied: {appliedVoucher.code} ({appliedVoucher.label}) — Saved {formatPrice(appliedVoucher.discount)}!
                        </Text>
                      </View>
                    ) : voucherError ? (
                      <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6, marginTop: 6, backgroundColor: isDark ? '#7F1D1D22' : '#FEF2F2', padding: 8, borderRadius: 8, borderWidth: 1, borderColor: '#FECACA' }}>
                        <Ionicons name="alert-circle" size={15} color="#EF4444" />
                        <Text style={{ color: '#DC2626', fontSize: 12, fontWeight: '600' }}>{voucherError}</Text>
                      </View>
                    ) : null}
                  </View>

                  {/* Section 5: Payment Summary Breakdown */}
                  <View style={[styles.calcSummaryBox, { backgroundColor: isDark ? '#1C202B' : '#F8FAFC', borderColor: colors.border, marginBottom: 16 }]}>
                    <Text style={{ fontSize: 11, fontWeight: '800', color: colors.textMute, textTransform: 'uppercase', marginBottom: 6 }}>
                      Payment Breakdown
                    </Text>
                    <View style={styles.calcRow}>
                      <Text style={[styles.calcLabel, { color: colors.textSub }]}>Total Package Price</Text>
                      <Text style={[styles.calcValue, { color: colors.text, fontWeight: '700' }]}>{formatPrice(packageBasePrice)}</Text>
                    </View>
                    <View style={styles.calcRow}>
                      <Text style={[styles.calcLabel, { color: colors.textSub }]}>Applicable Taxes (5%)</Text>
                      <Text style={[styles.calcValue, { color: colors.textSub }]}>{formatPrice(taxes)}</Text>
                    </View>
                    <View style={styles.calcRow}>
                      <Text style={[styles.calcLabel, { color: colors.textSub }]}>Environmental Fees & Permits</Text>
                      <Text style={[styles.calcValue, { color: colors.textSub }]}>{formatPrice(environmentalFees)}</Text>
                    </View>
                    {appliedVoucher ? (
                      <View style={styles.calcRow}>
                        <Text style={[styles.calcLabel, { color: '#059669', fontWeight: '700' }]}>
                          Voucher Discount ({appliedVoucher.code})
                        </Text>
                        <Text style={[styles.calcValue, { color: '#059669', fontWeight: '800' }]}>
                          -{formatPrice(appliedVoucher.discount)}
                        </Text>
                      </View>
                    ) : null}
                    <View style={[styles.calcDivider, { backgroundColor: colors.border }]} />
                    <View style={styles.calcRow}>
                      <Text style={[styles.calcTotalLabel, { color: colors.text, fontSize: 15 }]}>Final Payment</Text>
                      <Text style={[styles.calcTotalValue, { color: '#10B981', fontSize: 18 }]}>
                        {formatPrice(finalPayment)}
                      </Text>
                    </View>
                  </View>

                  <Text style={[styles.formLabel, { color: colors.text }]}>Choose Payment Method</Text>

                  {/* Payment Options */}
                  <View style={{ gap: 10, marginTop: 6 }}>
                    <TouchableOpacity
                      style={[
                        styles.payOptionCard,
                        {
                          borderColor: payMethod === 'ewallet' ? '#8B5CF6' : colors.border,
                          backgroundColor: payMethod === 'ewallet' ? (isDark ? '#2E1065' : '#F5F3FF') : isDark ? '#1C202B' : '#F8FAFC',
                        },
                      ]}
                      onPress={() => setPayMethod('ewallet')}
                    >
                      <Ionicons name="wallet-outline" size={20} color={payMethod === 'ewallet' ? '#8B5CF6' : colors.text} />
                      <View style={{ flex: 1, marginLeft: 10 }}>
                        <Text style={[styles.payOptionTitle, { color: colors.text }]}>GCash / Maya QR</Text>
                        <Text style={[styles.payOptionSub, { color: colors.textSub }]}>Instant scan to pay with e-Wallet</Text>
                      </View>
                      <Ionicons
                        name={payMethod === 'ewallet' ? 'radio-button-on' : 'radio-button-off'}
                        size={18}
                        color={payMethod === 'ewallet' ? '#8B5CF6' : colors.textMute}
                      />
                    </TouchableOpacity>

                    <TouchableOpacity
                      style={[
                        styles.payOptionCard,
                        {
                          borderColor: payMethod === 'instapay' ? '#8B5CF6' : colors.border,
                          backgroundColor: payMethod === 'instapay' ? (isDark ? '#2E1065' : '#F5F3FF') : isDark ? '#1C202B' : '#F8FAFC',
                        },
                      ]}
                      onPress={() => setPayMethod('instapay')}
                    >
                      <Ionicons name="business-outline" size={20} color={payMethod === 'instapay' ? '#8B5CF6' : colors.text} />
                      <View style={{ flex: 1, marginLeft: 10 }}>
                        <Text style={[styles.payOptionTitle, { color: colors.text }]}>Bank Transfer / InstaPay</Text>
                        <Text style={[styles.payOptionSub, { color: colors.textSub }]}>BDO, BPI, UnionBank or any bank QR</Text>
                      </View>
                      <Ionicons
                        name={payMethod === 'instapay' ? 'radio-button-on' : 'radio-button-off'}
                        size={18}
                        color={payMethod === 'instapay' ? '#8B5CF6' : colors.textMute}
                      />
                    </TouchableOpacity>

                    <TouchableOpacity
                      style={[
                        styles.payOptionCard,
                        {
                          borderColor: payMethod === 'cash' ? '#8B5CF6' : colors.border,
                          backgroundColor: payMethod === 'cash' ? (isDark ? '#2E1065' : '#F5F3FF') : isDark ? '#1C202B' : '#F8FAFC',
                        },
                      ]}
                      onPress={() => setPayMethod('cash')}
                    >
                      <Ionicons name="cash-outline" size={20} color={payMethod === 'cash' ? '#8B5CF6' : colors.text} />
                      <View style={{ flex: 1, marginLeft: 10 }}>
                        <Text style={[styles.payOptionTitle, { color: colors.text }]}>Pay on Tour / Downpayment</Text>
                        <Text style={[styles.payOptionSub, { color: colors.textSub }]}>Pay the guide host upon meeting in Moalboal</Text>
                      </View>
                      <Ionicons
                        name={payMethod === 'cash' ? 'radio-button-on' : 'radio-button-off'}
                        size={18}
                        color={payMethod === 'cash' ? '#8B5CF6' : colors.textMute}
                      />
                    </TouchableOpacity>
                  </View>

                  {/* QR code view if ewallet or instapay */}
                  {payMethod === 'ewallet' || payMethod === 'instapay' ? (
                    <View style={[styles.qrContainer, { backgroundColor: isDark ? '#1C202B' : '#F8FAFC', borderColor: colors.border }]}>
                      <Text style={[styles.qrTitle, { color: colors.text }]}>
                        Scan to Pay {formatPrice(finalPayment)}
                      </Text>
                      <Text style={[styles.qrSub, { color: colors.textSub }]}>
                        Scan with your banking or e-wallet app, then confirm booking below.
                      </Text>
                      <Image
                        source={payMethod === 'ewallet' ? QR_EWALLET : QR_INSTAPAY}
                        style={styles.qrImage}
                        resizeMode="contain"
                      />
                    </View>
                  ) : null}

                  <View style={{ flexDirection: 'row', gap: 10, marginTop: 20 }}>
                    <TouchableOpacity
                      style={[styles.backStepBtn, { borderColor: colors.border }]}
                      onPress={() => setBookingStep('form')}
                    >
                      <Text style={[styles.backStepBtnText, { color: colors.text }]}>Back</Text>
                    </TouchableOpacity>

                    <TouchableOpacity
                      style={[styles.primaryActionBtn, { flex: 2, backgroundColor: '#8B5CF6' }]}
                      onPress={handleConfirmBooking}
                      disabled={submitting}
                    >
                      {submitting ? (
                        <ActivityIndicator color="#FFFFFF" />
                      ) : (
                        <>
                          <Text style={styles.primaryActionBtnText}>Confirm Booking</Text>
                          <Ionicons name="checkmark-circle" size={16} color="#FFFFFF" />
                        </>
                      )}
                    </TouchableOpacity>
                  </View>
                </View>
              ) : null}

              {/* STEP 3: CONFIRMED */}
              {bookingStep === 'confirmed' && bookingResult ? (
                <View style={{ alignItems: 'center', paddingVertical: 10 }}>
                  <View style={styles.successIconCircle}>
                    <Ionicons name="checkmark" size={36} color="#FFFFFF" />
                  </View>

                  <Text style={[styles.successTitle, { color: colors.text }]}>Booking Confirmed!</Text>
                  <Text style={[styles.successRef, { color: '#8B5CF6' }]}>
                    Ref #{bookingResult.bookingId}
                  </Text>

                  <Text style={[styles.successDesc, { color: colors.textSub }]}>
                    {bookingResult.message}
                  </Text>

                  {/* Booking Receipt Summary */}
                  <View style={[styles.receiptCard, { backgroundColor: isDark ? '#1C202B' : '#F8FAFC', borderColor: colors.border }]}>
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
                      <Text style={[styles.receiptVal, { color: '#10B981', fontWeight: '700' }]}>
                        {bookingResult.guideName}
                      </Text>
                    </View>
                    <View style={styles.receiptRow}>
                      <Text style={[styles.receiptLabel, { color: colors.textMute }]}>Total Paid:</Text>
                      <Text style={[styles.receiptVal, { color: '#8B5CF6', fontWeight: '800' }]}>
                        {formatPrice(bookingResult.totalPrice)}
                      </Text>
                    </View>
                  </View>

                  <View style={{ width: '100%', gap: 10, marginTop: 20 }}>
                    <TouchableOpacity
                      style={[styles.primaryActionBtn, { backgroundColor: '#10B981' }]}
                      onPress={() => {
                        setBookingVisible(false);
                        router.replace('/(tabs)/trips');
                      }}
                    >
                      <Ionicons name="briefcase" size={16} color="#FFFFFF" />
                      <Text style={styles.primaryActionBtnText}>View in My Trips</Text>
                    </TouchableOpacity>

                    <TouchableOpacity
                      style={[styles.primaryActionBtn, { backgroundColor: '#8B5CF6' }]}
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

  heroWrap: { width: '100%', height: 290, position: 'relative' },
  heroImage: { width: '100%', height: '100%', resizeMode: 'cover' },
  heroGradientOverlay: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    backgroundColor: 'rgba(0, 0, 0, 0.35)',
  },
  heroNav: {
    position: 'absolute',
    left: 16,
    right: 16,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  heroCircleBtn: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: 'rgba(0,0,0,0.45)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  heroBottomRow: {
    position: 'absolute',
    bottom: 16,
    left: 16,
    right: 16,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  heroCategoryPill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 5,
    backgroundColor: 'rgba(139, 92, 246, 0.9)',
    paddingVertical: 5,
    paddingHorizontal: 12,
    borderRadius: 999,
  },
  heroCategoryText: { color: '#FFFFFF', fontSize: 11, fontWeight: '800', letterSpacing: 0.5 },
  heroSpecialPill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    backgroundColor: '#F59E0B',
    paddingVertical: 5,
    paddingHorizontal: 10,
    borderRadius: 999,
  },
  heroSpecialText: { color: '#FFFFFF', fontSize: 10, fontWeight: '900', letterSpacing: 0.5 },

  mainSection: {
    margin: 16,
    marginTop: -20,
    borderRadius: 22,
    padding: 18,
    borderWidth: 1,
  },
  title: { fontSize: 20, fontWeight: '800', lineHeight: 28, letterSpacing: -0.4 },
  metaRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginTop: 8,
  },
  locationWrap: { flexDirection: 'row', alignItems: 'center', gap: 4, maxWidth: '65%' },
  locationText: { fontSize: 13, fontWeight: '600' },
  ratingBadge: { flexDirection: 'row', alignItems: 'center', gap: 4 },
  ratingNumber: { fontSize: 13, fontWeight: '800' },
  reviewCount: { fontSize: 12 },

  specsGrid: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-around',
    paddingVertical: 12,
    borderRadius: 14,
    borderWidth: 1,
    marginTop: 14,
  },
  specItem: { alignItems: 'center', flex: 1 },
  specDivider: { width: 1, height: 28, backgroundColor: '#E2E8F0' },
  specLabel: { fontSize: 10, fontWeight: '600', marginTop: 4 },
  specValue: { fontSize: 12, fontWeight: '800', marginTop: 1 },

  descBlock: { marginTop: 16 },
  sectionHeading: { fontSize: 16, fontWeight: '800', letterSpacing: -0.2 },
  descriptionText: { fontSize: 14, lineHeight: 21, marginTop: 6 },

  guideCard: {
    marginHorizontal: 16,
    marginBottom: 16,
    borderRadius: 20,
    padding: 16,
    borderWidth: 1,
  },
  guideCardHeader: { flexDirection: 'row', alignItems: 'center' },
  guideAvatar: { width: 48, height: 48, borderRadius: 24, backgroundColor: '#E2E8F0' },
  guideAvatarFallback: { alignItems: 'center', justifyContent: 'center', backgroundColor: '#EDE9FE' },
  guideName: { fontSize: 15, fontWeight: '800' },
  guideRole: { fontSize: 12, fontWeight: '500', marginTop: 1 },
  guideStats: { fontSize: 11, fontWeight: '700', marginTop: 3 },
  guideBio: { fontSize: 13, fontStyle: 'italic', lineHeight: 18, marginTop: 10 },
  guideActionRow: { marginTop: 12 },
  messageGuideBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 6,
    paddingVertical: 9,
    borderRadius: 12,
    borderWidth: 1,
  },
  messageGuideBtnText: { color: '#8B5CF6', fontSize: 13, fontWeight: '700' },

  inclusionsSection: {
    marginHorizontal: 16,
    marginBottom: 16,
    borderRadius: 20,
    padding: 16,
    borderWidth: 1,
  },
  inclusionsHeadingRow: { flexDirection: 'row', alignItems: 'center', marginBottom: 2 },
  inclusionsSubheading: { fontSize: 12, marginBottom: 12 },
  inclusionsList: { gap: 10 },
  inclusionCard: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 10,
    borderRadius: 14,
  },
  incIconBox: {
    width: 36,
    height: 36,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
  },
  incTitle: { fontSize: 13, fontWeight: '700', flex: 1 },
  incSubtitle: { fontSize: 11, marginTop: 2, lineHeight: 15 },
  incBadgePill: { paddingVertical: 2, paddingHorizontal: 6, borderRadius: 6, marginLeft: 6 },
  incBadgeText: { fontSize: 9, fontWeight: '800' },
  exclusionsBox: {
    marginTop: 14,
    paddingTop: 12,
    borderTopWidth: 1,
    borderTopColor: '#E2E8F033',
  },
  exclusionsTitle: { fontSize: 12, fontWeight: '700', marginBottom: 6 },
  exclusionItem: { flexDirection: 'row', gap: 6, marginBottom: 4 },
  exclusionText: { fontSize: 12, flex: 1, lineHeight: 16 },

  itinerarySection: {
    marginHorizontal: 16,
    marginBottom: 16,
    borderRadius: 20,
    padding: 16,
    borderWidth: 1,
  },
  dayTabsScroll: { gap: 8, marginVertical: 12 },
  dayTab: {
    paddingVertical: 7,
    paddingHorizontal: 16,
    borderRadius: 999,
    borderWidth: 1,
  },
  dayTabText: { fontSize: 13, fontWeight: '700' },
  itineraryDayBody: { marginTop: 4 },
  itineraryDayTitle: { fontSize: 15, fontWeight: '800' },
  itineraryDayDesc: { fontSize: 13, marginTop: 4, lineHeight: 18 },

  timelineList: { marginTop: 14, paddingLeft: 4 },
  timelineItem: { flexDirection: 'row', marginBottom: 12 },
  timelineLeft: { alignItems: 'center', width: 20, marginRight: 8 },
  timelineDot: { width: 10, height: 10, borderRadius: 5, marginTop: 4 },
  timelineLine: { width: 2, flex: 1, marginTop: 4 },
  timelineRight: { flex: 1 },
  timelineTime: { fontSize: 11, fontWeight: '800' },
  timelineActivity: { fontSize: 13, fontWeight: '500', marginTop: 2 },

  highlightsBox: {
    marginTop: 12,
    padding: 12,
    borderRadius: 12,
    backgroundColor: '#00000008',
  },
  highlightsHeading: { fontSize: 13, fontWeight: '700', marginBottom: 6 },
  highlightBulletRow: { flexDirection: 'row', gap: 6, marginBottom: 4 },
  highlightBulletText: { fontSize: 12, flex: 1, lineHeight: 16 },

  specsCard: {
    marginHorizontal: 16,
    marginBottom: 16,
    borderRadius: 20,
    padding: 16,
    borderWidth: 1,
  },
  specHotelName: { fontSize: 15, fontWeight: '800', marginTop: 8 },
  specRoomType: { fontSize: 12, marginTop: 2 },
  amenitiesWrap: { flexDirection: 'row', flexWrap: 'wrap', gap: 6, marginTop: 10 },
  amenityPill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    paddingVertical: 4,
    paddingHorizontal: 8,
    borderRadius: 8,
  },
  amenityText: { fontSize: 11, fontWeight: '600' },

  policyRow: { marginTop: 10 },
  policyTitle: { fontSize: 13, fontWeight: '700' },
  policyText: { fontSize: 12, lineHeight: 17, marginTop: 2 },

  stickyBottomBar: {
    position: 'absolute',
    bottom: 0,
    left: 0,
    right: 0,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 16,
    paddingTop: 12,
    borderTopWidth: 1,
  },
  stickyPriceWrap: { justifyContent: 'center' },
  stickyPriceLabel: { fontSize: 10, fontWeight: '700', letterSpacing: 0.5 },
  stickyPriceText: { fontSize: 20, fontWeight: '900', letterSpacing: -0.5 },
  stickyPriceUnit: { fontSize: 11, fontWeight: '600' },
  stickyActionsRow: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  inquireCircleBtn: {
    width: 44,
    height: 44,
    borderRadius: 14,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  bookMainBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingVertical: 12,
    paddingHorizontal: 20,
    borderRadius: 14,
  },
  bookMainBtnText: { color: '#FFFFFF', fontSize: 14, fontWeight: '800' },

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
    borderBottomColor: '#E2E8F033',
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
    backgroundColor: '#8B5CF622',
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
  payOptionCard: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 12,
    borderRadius: 14,
    borderWidth: 1.5,
  },
  payOptionTitle: { fontSize: 13, fontWeight: '700' },
  payOptionSub: { fontSize: 11, marginTop: 1 },
  qrContainer: {
    marginTop: 14,
    padding: 14,
    borderRadius: 14,
    borderWidth: 1,
    alignItems: 'center',
  },
  qrTitle: { fontSize: 14, fontWeight: '800' },
  qrSub: { fontSize: 11, textAlign: 'center', marginTop: 2, marginBottom: 10 },
  qrImage: { width: 180, height: 180 },
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
    backgroundColor: '#10B981',
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
