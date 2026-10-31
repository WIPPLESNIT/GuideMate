import { Ionicons } from '@expo/vector-icons';
import { useFocusEffect, useRouter } from 'expo-router';
import React, { useCallback, useMemo, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  FlatList,
  KeyboardAvoidingView,
  Modal,
  Platform,
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
import { useTheme } from '../lib/theme';
import {
  ApiRental,
  cancelRental,
  getMyRentals,
  getRentalReturnDate,
  isRentalCompleted,
  isUnauthorized,
  submitRentalReview,
} from '../services/api';

const statusColor = (status: string): string => {
  switch (status) {
    case 'approved':
    case 'contacted':
    case 'completed':
      return '#16A34A';
    case 'refunded':
      return '#2563EB';
    case 'cancelled':
      return '#DC2626';
    default:
      return '#D97706';
  }
};

const ratingLabel = (val: number): string => {
  switch (val) {
    case 5:
      return '⭐ Excellent (5.0)';
    case 4:
      return '⭐ Very Good (4.0)';
    case 3:
      return '⭐ Good (3.0)';
    case 2:
      return '⭐ Fair (2.0)';
    case 1:
      return '⭐ Poor (1.0)';
    default:
      return 'Tap a star to rate';
  }
};

export default function MyRentalsScreen() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { colors, isDark, shadow } = useTheme();

  const [rentals, setRentals] = useState<ApiRental[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [viewMode, setViewMode] = useState<'active' | 'history'>('active');
  const [selectedDetailRental, setSelectedDetailRental] = useState<ApiRental | null>(null);
  const [isSubmittingCancel, setIsSubmittingCancel] = useState(false);

  const activeRentals = useMemo(
    () => rentals.filter((r) => !isRentalCompleted(r)),
    [rentals]
  );
  const historyRentals = useMemo(
    () => rentals.filter((r) => isRentalCompleted(r)),
    [rentals]
  );
  const displayedRentals = viewMode === 'active' ? activeRentals : historyRentals;

  // Rating & Review Modal State
  const [reviewModalVisible, setReviewModalVisible] = useState(false);
  const [selectedRental, setSelectedRental] = useState<ApiRental | null>(null);
  const [rating, setRating] = useState<number>(5);
  const [feedback, setFeedback] = useState<string>('');
  const [submittingReview, setSubmittingReview] = useState(false);
  const [reviewError, setReviewError] = useState<string | null>(null);

  const load = useCallback(async () => {
    try {
      const data = await getMyRentals();
      setRentals(data.rentals);
    } catch (e) {
      if (isUnauthorized(e)) {
        Alert.alert('Sign in required', 'Please log in to view your rentals.', [
          { text: 'OK', onPress: () => router.replace('/(auth)/login') },
        ]);
      } else {
        Alert.alert('Could not load', 'Check your connection and try again.');
      }
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [router]);

  useFocusEffect(
    useCallback(() => {
      load();
    }, [load])
  );

  const onRefresh = () => {
    setRefreshing(true);
    load();
  };

  const handleCancelRental = (rental: ApiRental) => {
    if (!rental.cancellation?.can_cancel) {
      Alert.alert(
        'Cancellation Unavailable',
        rental.cancellation?.ineligible_reason || 'This vehicle reservation is no longer eligible for cancellation.'
      );
      return;
    }

    const refundAmount = rental.cancellation?.refund_amount || 0;
    const confirmMessage = refundAmount > 0
      ? `Are you sure you want to cancel your vehicle reservation for "${rental.vehicle_name}"?\n\nA full refund of ₱${refundAmount.toLocaleString()} will be automatically initiated to your original payment method (1–3 business days).`
      : `Are you sure you want to cancel your vehicle reservation for "${rental.vehicle_name}"? No charges will be incurred.`;

    Alert.alert(
      'Cancel Reservation',
      confirmMessage,
      [
        { text: 'Keep Reservation', style: 'cancel' },
        {
          text: 'Confirm Cancellation',
          style: 'destructive',
          onPress: async () => {
            setIsSubmittingCancel(true);
            try {
              const res = await cancelRental(rental.id, 'Cancelled by tourist via mobile app');
              setSelectedDetailRental(null);
              await load();
              Alert.alert('Reservation Cancelled', res.message || 'Your vehicle reservation has been cancelled successfully.');
            } catch (err: any) {
              if (isUnauthorized(err)) {
                setSelectedDetailRental(null);
                Alert.alert('Session expired', 'Please log in again to manage your reservations.', [
                  { text: 'OK', onPress: () => router.replace('/(auth)/login') },
                ]);
              } else {
                Alert.alert('Cancellation Failed', err?.message || 'Could not cancel reservation. Please try again.');
              }
            } finally {
              setIsSubmittingCancel(false);
            }
          },
        },
      ]
    );
  };

  const openReviewModal = (item: ApiRental) => {
    setSelectedRental(item);
    setRating(item.review?.rating || 5);
    setFeedback(item.review?.feedback || '');
    setReviewError(null);
    setReviewModalVisible(true);
  };

  const closeReviewModal = () => {
    if (submittingReview) return;
    setReviewModalVisible(false);
    setSelectedRental(null);
    setReviewError(null);
  };

  const handleSubmitReview = async () => {
    if (!selectedRental) return;

    if (rating < 1 || rating > 5) {
      setReviewError('Please select a star rating between 1 and 5.');
      return;
    }

    if (feedback.trim().length < 3) {
      setReviewError('Please share at least a few words describing your experience.');
      return;
    }

    setSubmittingReview(true);
    setReviewError(null);

    try {
      const res = await submitRentalReview({
        rental_id: selectedRental.id,
        rating,
        feedback: feedback.trim(),
      });

      // Update the local rental state immediately so UI refreshes without waiting
      setRentals((prev) =>
        prev.map((r) =>
          r.id === selectedRental.id
            ? {
                ...r,
                can_review: true,
                has_reviewed: true,
                review: res.review,
              }
            : r
        )
      );

      setReviewModalVisible(false);
      Alert.alert(
        selectedRental.has_reviewed ? 'Review Updated' : 'Thank You!',
        res.message || 'Your rating and feedback have been saved.'
      );

      // Background reload to sync server metrics
      load();
    } catch (e: any) {
      if (isUnauthorized(e)) {
        Alert.alert('Session expired', 'Please sign in again to submit your review.');
      } else {
        setReviewError(e?.message || 'Could not submit review. Please try again.');
      }
    } finally {
      setSubmittingReview(false);
    }
  };

  const renderItem = ({ item }: { item: ApiRental }) => {
    const isCompleted = isRentalCompleted(item);
    const hasReviewed = !!item.has_reviewed && !!item.review;

    return (
      <TouchableOpacity
        style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }, shadow.sm]}
        activeOpacity={0.92}
        onPress={() => setSelectedDetailRental(item)}
      >
        <View style={styles.cardTop}>
          <View style={{ flex: 1 }}>
            <Text style={[styles.vehicle, { color: colors.text }]} numberOfLines={1}>{item.vehicle_name}</Text>
            <Text style={[styles.sub, { color: colors.textSub }]} numberOfLines={1}>{item.shop_name} · {item.location}</Text>
          </View>
          <View style={[styles.statusPill, { backgroundColor: statusColor(item.status) + '22' }]}>
            <Text style={[styles.statusText, { color: statusColor(item.status) }]}>{item.status_label}</Text>
          </View>
        </View>

        {isCompleted ? (
          <View style={[styles.historyBanner, { backgroundColor: isDark ? '#064E3B22' : '#F0FDF4', borderColor: isDark ? '#05966955' : '#BBF7D0' }]}>
            <Ionicons name="checkmark-circle" size={15} color="#059669" />
            <Text style={[styles.historyBannerText, { color: isDark ? '#34D399' : '#166534' }]}>
              {item.status === 'completed' ? 'Completed Vehicle Rental · In History' : `${item.status_label || 'Completed'} · In History`}
            </Text>
          </View>
        ) : null}

        <View style={[styles.divider, { backgroundColor: colors.border }]} />

        <View style={styles.metaRow}>
          <Ionicons name="calendar-outline" size={15} color={colors.textSub} />
          <Text style={[styles.metaText, { color: colors.textSub }]}>
            {new Date(item.pickup_date).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })} · {item.rental_days} day(s)
          </Text>
        </View>
        <View style={styles.metaRow}>
          <Ionicons name="cash-outline" size={15} color={colors.textSub} />
          <Text style={[styles.metaText, { color: colors.textSub }]}>
            ₱{item.total_amount.toLocaleString()} ·{' '}
            {item.payment_status === 'paid' ? 'Paid in full' : item.payment_status === 'refunded' ? 'Refunded' : 'Unpaid'}
          </Text>
        </View>

        {/* View Details & Receipt Trigger */}
        <TouchableOpacity
          style={[styles.viewDetailsBtn, { borderColor: colors.border, backgroundColor: isDark ? '#1E2330' : '#F1F5F9' }]}
          activeOpacity={0.8}
          onPress={() => setSelectedDetailRental(item)}
        >
          <Ionicons name="receipt-outline" size={14} color={colors.text} />
          <Text style={[styles.viewDetailsBtnText, { color: colors.text }]}>View Details &amp; Receipt</Text>
        </TouchableOpacity>

        {item.report_status === 'open' ? (
          <View style={[styles.reportBanner, { backgroundColor: '#EF444415' }]}>
            <Ionicons name="alert-circle" size={16} color="#EF4444" style={{ marginTop: 2 }} />
            <View style={{ flex: 1 }}>
              <Text style={[styles.reportText, { color: '#EF4444' }]}>
                Problem reported ({item.report_label}) — the owner is reviewing it.
              </Text>
              {item.report_photos && item.report_photos.length > 0 ? (
                <Text style={{ fontSize: 11, color: '#EF4444', marginTop: 3, fontWeight: '600' }}>
                  📷 {item.report_photos.length} {item.report_photos.length === 1 ? 'picture' : 'pictures'} attached as evidence
                </Text>
              ) : null}
            </View>
          </View>
        ) : item.report_status === 'refunded' ? (
          <View style={[styles.reportBanner, { backgroundColor: '#2563EB15' }]}>
            <Ionicons name="checkmark-circle" size={16} color="#2563EB" />
            <Text style={[styles.reportText, { color: '#2563EB' }]}>
              Refunded{item.owner_report_note ? ` — ${item.owner_report_note}` : '.'}
            </Text>
          </View>
        ) : item.report_status === 'rejected' ? (
          <View style={[styles.reportBanner, { backgroundColor: colors.chipBg }]}>
            <Ionicons name="information-circle" size={16} color={colors.textSub} />
            <Text style={[styles.reportText, { color: colors.textSub }]}>
              Report reviewed{item.owner_report_note ? ` — ${item.owner_report_note}` : '.'}
            </Text>
          </View>
        ) : null}

        {item.can_report ? (
          <TouchableOpacity
            style={[styles.reportBtn, { borderColor: '#EF4444' }]}
            activeOpacity={0.85}
            onPress={() =>
              router.push({ pathname: '/rental-report/[id]', params: { id: String(item.id), title: item.vehicle_name } })
            }
          >
            <Ionicons name="flag-outline" size={16} color="#EF4444" />
            <Text style={styles.reportBtnText}>Report a problem</Text>
          </TouchableOpacity>
        ) : null}

        {/* ── Rating and Feedback Section for Completed Rentals Only ── */}
        {isCompleted ? (
          hasReviewed ? (
            <View style={[styles.reviewedBox, { backgroundColor: isDark ? colors.cardAlt : '#F8FAFC', borderColor: colors.border }]}>
              <View style={styles.reviewedTopRow}>
                <View style={styles.reviewedStarsRow}>
                  {[1, 2, 3, 4, 5].map((star) => (
                    <Ionicons
                      key={star}
                      name={star <= (item.review?.rating || 0) ? 'star' : 'star-outline'}
                      size={15}
                      color="#F59E0B"
                    />
                  ))}
                  <Text style={[styles.reviewedRatingScore, { color: colors.text }]}>
                    {item.review?.rating}.0
                  </Text>
                </View>

                <TouchableOpacity
                  style={[styles.editReviewBtn, { borderColor: colors.primary, backgroundColor: isDark ? '#064E3B20' : '#F0FDF4' }]}
                  activeOpacity={0.75}
                  onPress={() => openReviewModal(item)}
                >
                  <Ionicons name="create-outline" size={14} color={colors.primary} />
                  <Text style={[styles.editReviewText, { color: colors.primary }]}>Edit Review</Text>
                </TouchableOpacity>
              </View>

              {item.review?.feedback ? (
                <Text style={[styles.reviewedFeedbackText, { color: colors.textSub }]} numberOfLines={3}>
                  "{item.review.feedback}"
                </Text>
              ) : null}
            </View>
          ) : (
            <TouchableOpacity
              style={[
                styles.rateReviewBtn,
                {
                  borderColor: '#F59E0B',
                  backgroundColor: isDark ? '#78350F22' : '#FFFBEB',
                },
              ]}
              activeOpacity={0.85}
              onPress={() => openReviewModal(item)}
            >
              <Ionicons name="star" size={16} color="#F59E0B" />
              <Text style={[styles.rateReviewBtnText, { color: isDark ? '#FBBF24' : '#D97706' }]}>
                Rate and Review
              </Text>
            </TouchableOpacity>
          )
        ) : null}
      </TouchableOpacity>
    );
  };

  return (
    <SafeAreaView style={[styles.container, { backgroundColor: colors.bgAlt }]} edges={['left', 'right']}>
      <StatusBar barStyle={isDark ? 'light-content' : 'dark-content'} />

      <View style={[styles.header, { backgroundColor: colors.card, borderBottomColor: colors.border, paddingTop: insets.top + 10 }]}>
        <TouchableOpacity onPress={() => router.back()} style={styles.headerBtn} activeOpacity={0.7}>
          <Ionicons name="chevron-back" size={26} color={colors.text} />
        </TouchableOpacity>
        <Text style={[styles.headerTitle, { color: colors.text }]}>
          {viewMode === 'active' ? 'My Rentals' : 'Rental History'}
        </Text>
        <TouchableOpacity
          style={[
            styles.historyIconBtn,
            { backgroundColor: viewMode === 'history' ? colors.primary : (isDark ? '#1E2330' : '#F1F5F9'), borderColor: colors.border }
          ]}
          activeOpacity={0.8}
          onPress={() => setViewMode(viewMode === 'active' ? 'history' : 'active')}
          accessibilityLabel={viewMode === 'active' ? 'View Rental History' : 'View Active Rentals'}
        >
          <Ionicons
            name={viewMode === 'history' ? 'time' : 'time-outline'}
            size={20}
            color={viewMode === 'history' ? '#FFFFFF' : colors.text}
          />
          {historyRentals.length > 0 ? (
            <View style={[styles.historyBadge, { backgroundColor: viewMode === 'history' ? '#FFFFFF' : '#EF4444' }]}>
              <Text style={[styles.historyBadgeText, { color: viewMode === 'history' ? colors.primary : '#FFFFFF' }]}>
                {historyRentals.length}
              </Text>
            </View>
          ) : null}
        </TouchableOpacity>
      </View>

      {/* Segmented Control: Active vs History */}
      <View style={[styles.segmentWrap, { backgroundColor: isDark ? '#1E2330' : '#F1F5F9', borderColor: colors.border }]}>
        <TouchableOpacity
          style={[styles.segmentBtn, viewMode === 'active' && [styles.segmentBtnActive, { backgroundColor: colors.card }]]}
          activeOpacity={0.8}
          onPress={() => setViewMode('active')}
        >
          <Ionicons name="car-sport" size={14} color={viewMode === 'active' ? colors.primary : colors.textMute} />
          <Text style={[styles.segmentText, { color: viewMode === 'active' ? colors.primary : colors.textSub, fontWeight: viewMode === 'active' ? '800' : '600' }]}>
            Active ({activeRentals.length})
          </Text>
        </TouchableOpacity>

        <TouchableOpacity
          style={[styles.segmentBtn, viewMode === 'history' && [styles.segmentBtnActive, { backgroundColor: colors.card }]]}
          activeOpacity={0.8}
          onPress={() => setViewMode('history')}
        >
          <Ionicons name="time" size={14} color={viewMode === 'history' ? colors.primary : colors.textMute} />
          <Text style={[styles.segmentText, { color: viewMode === 'history' ? colors.primary : colors.textSub, fontWeight: viewMode === 'history' ? '800' : '600' }]}>
            History ({historyRentals.length})
          </Text>
        </TouchableOpacity>
      </View>

      {loading ? (
        <View style={styles.centered}>
          <ActivityIndicator size="large" color={colors.primary} />
        </View>
      ) : displayedRentals.length === 0 ? (
        <View style={styles.centered}>
          <Ionicons name={viewMode === 'history' ? 'time-outline' : 'car-outline'} size={54} color={colors.textMute} />
          <Text style={[styles.emptyTitle, { color: colors.text }]}>
            {viewMode === 'history' ? 'No rental history' : 'No active rentals'}
          </Text>
          <Text style={[styles.emptySub, { color: colors.textSub }]}>
            {viewMode === 'history'
              ? 'When your vehicle rentals are completed or finished, they will appear here.'
              : 'Reserve a vehicle and it will show up here. Completed rentals go to History.'}
          </Text>
          {viewMode === 'active' ? (
            historyRentals.length > 0 ? (
              <TouchableOpacity
                style={[styles.browseBtn, { backgroundColor: colors.primary }]}
                onPress={() => setViewMode('history')}
                activeOpacity={0.85}
              >
                <Text style={styles.browseText}>View History ({historyRentals.length})</Text>
              </TouchableOpacity>
            ) : (
              <TouchableOpacity
                style={[styles.browseBtn, { backgroundColor: colors.primary }]}
                onPress={() => router.replace('/car-rentals')}
                activeOpacity={0.85}
              >
                <Text style={styles.browseText}>Browse rentals</Text>
              </TouchableOpacity>
            )
          ) : (
            <TouchableOpacity
              style={[styles.browseBtn, { backgroundColor: colors.primary }]}
              onPress={() => setViewMode('active')}
              activeOpacity={0.85}
            >
              <Text style={styles.browseText}>View Active Rentals</Text>
            </TouchableOpacity>
          )}
        </View>
      ) : (
        <FlatList
          data={displayedRentals}
          keyExtractor={(r) => String(r.id)}
          renderItem={renderItem}
          contentContainerStyle={{ padding: 16, paddingBottom: 32 }}
          ItemSeparatorComponent={() => <View style={{ height: 14 }} />}
          refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor={colors.primary} />}
          showsVerticalScrollIndicator={false}
        />
      )}

      {/* ── Rental Details & Receipt Modal ── */}
      <Modal
        visible={Boolean(selectedDetailRental)}
        transparent
        animationType="slide"
        onRequestClose={() => setSelectedDetailRental(null)}
      >
        <View style={styles.modalBackdrop}>
          <View style={[styles.modalCard, { backgroundColor: colors.card, borderColor: colors.border, maxHeight: '90%' }]}>
            <View style={styles.modalHeader}>
              <View style={{ flex: 1 }}>
                <Text style={[styles.modalTitle, { color: colors.text }]}>Rental Details &amp; Receipt</Text>
                <Text style={[styles.modalSubtitle, { color: colors.textSub }]}>
                  Reservation #{selectedDetailRental?.id} · {selectedDetailRental?.vehicle_type}
                </Text>
              </View>
              <TouchableOpacity onPress={() => setSelectedDetailRental(null)} style={styles.closeBtn} activeOpacity={0.7}>
                <Ionicons name="close" size={24} color={colors.textSub} />
              </TouchableOpacity>
            </View>

            <ScrollView showsVerticalScrollIndicator={false} contentContainerStyle={{ paddingBottom: 16 }}>
              {/* Status Banner */}
              {selectedDetailRental ? (
                <View
                  style={[
                    styles.historyBanner,
                    {
                      backgroundColor: isRentalCompleted(selectedDetailRental)
                        ? (isDark ? '#064E3B22' : '#F0FDF4')
                        : (isDark ? '#1E3A8A22' : '#EFF6FF'),
                      borderColor: isRentalCompleted(selectedDetailRental) ? '#10B98155' : '#3B82F655',
                      marginTop: 0,
                      marginBottom: 14,
                    },
                  ]}
                >
                  <Ionicons
                    name={isRentalCompleted(selectedDetailRental) ? 'checkmark-circle' : 'time'}
                    size={18}
                    color={isRentalCompleted(selectedDetailRental) ? '#059669' : '#2563EB'}
                  />
                  <View style={{ flex: 1 }}>
                    <Text
                      style={[
                        styles.historyBannerText,
                        {
                          color: isRentalCompleted(selectedDetailRental)
                            ? (isDark ? '#34D399' : '#166534')
                            : (isDark ? '#93C5FD' : '#1E40AF'),
                        },
                      ]}
                    >
                      {isRentalCompleted(selectedDetailRental)
                        ? 'Completed Rental · Stored in History'
                        : 'Active / Upcoming Rental'}
                    </Text>
                    <Text style={{ fontSize: 11, color: colors.textSub, marginTop: 1 }}>
                      {isRentalCompleted(selectedDetailRental)
                        ? 'Rental period completed. Accessible anytime in your History.'
                        : 'Your vehicle reservation is active or awaiting pickup.'}
                    </Text>
                  </View>
                </View>
              ) : null}

              {/* Vehicle & Shop Box */}
              <View style={[styles.detailSectionBox, { backgroundColor: isDark ? '#1E2330' : '#F8FAFC', borderColor: colors.border }]}>
                <Text style={[styles.detailSectionTitle, { color: colors.text }]}>Vehicle &amp; Provider</Text>
                <Text style={[styles.detailMainValue, { color: colors.text }]}>{selectedDetailRental?.vehicle_name}</Text>
                <Text style={[styles.detailSubValue, { color: colors.textSub }]}>
                  {selectedDetailRental?.shop_name} · {selectedDetailRental?.location || 'Cebu'}
                </Text>
                <View style={[styles.detailBadgeRow, { marginTop: 6 }]}>
                  <View style={[styles.detailPill, { backgroundColor: isDark ? '#1E3A8A33' : '#EFF6FF' }]}>
                    <Ionicons name="car-outline" size={13} color="#2563EB" />
                    <Text style={{ fontSize: 12, fontWeight: '700', color: isDark ? '#93C5FD' : '#1D4ED8', textTransform: 'capitalize' }}>
                      {selectedDetailRental?.vehicle_type}
                    </Text>
                  </View>
                </View>
              </View>

              {/* Schedule Box */}
              <View style={[styles.detailSectionBox, { backgroundColor: isDark ? '#1E2330' : '#F8FAFC', borderColor: colors.border, marginTop: 12 }]}>
                <Text style={[styles.detailSectionTitle, { color: colors.text }]}>Rental Schedule</Text>
                <View style={{ flexDirection: 'row', justifyContent: 'space-between', marginTop: 8 }}>
                  <View style={{ flex: 1 }}>
                    <Text style={[styles.detailFieldLabel, { color: colors.textMute }]}>PICKUP DATE</Text>
                    <Text style={[styles.detailFieldValue, { color: colors.text }]}>
                      {selectedDetailRental?.pickup_date
                        ? new Date(selectedDetailRental.pickup_date).toLocaleDateString(undefined, {
                            month: 'short',
                            day: 'numeric',
                            year: 'numeric',
                          })
                        : '—'}
                    </Text>
                  </View>
                  <Ionicons name="arrow-forward" size={14} color={colors.textMute} style={{ marginHorizontal: 8, marginTop: 14 }} />
                  <View style={{ flex: 1 }}>
                    <Text style={[styles.detailFieldLabel, { color: colors.textMute }]}>RETURN DATE</Text>
                    <Text style={[styles.detailFieldValue, { color: colors.text }]}>
                      {selectedDetailRental
                        ? new Date(getRentalReturnDate(selectedDetailRental)).toLocaleDateString(undefined, {
                            month: 'short',
                            day: 'numeric',
                            year: 'numeric',
                          })
                        : '—'}
                    </Text>
                  </View>
                </View>
                <View style={{ marginTop: 8, paddingTop: 8, borderTopWidth: 1, borderColor: colors.border }}>
                  <Text style={{ fontSize: 12, color: colors.textSub, fontWeight: '600' }}>
                    Rental Duration: <Text style={{ color: colors.text, fontWeight: '800' }}>{selectedDetailRental?.rental_days} day(s)</Text>
                  </Text>
                </View>
              </View>

              {/* Payment Receipt Box */}
              <View style={[styles.detailSectionBox, { backgroundColor: isDark ? '#1E2330' : '#F8FAFC', borderColor: colors.border, marginTop: 12 }]}>
                <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
                  <Text style={[styles.detailSectionTitle, { color: colors.text }]}>Payment &amp; Receipt</Text>
                  <View
                    style={{
                      backgroundColor: selectedDetailRental?.payment_status === 'paid' ? '#DCFCE7' : '#FEF3C7',
                      paddingHorizontal: 8,
                      paddingVertical: 2,
                      borderRadius: 6,
                    }}
                  >
                    <Text
                      style={{
                        fontSize: 11,
                        fontWeight: '800',
                        color: selectedDetailRental?.payment_status === 'paid' ? '#166534' : '#92400E',
                      }}
                    >
                      {selectedDetailRental?.payment_status === 'paid' ? '✓ PAID IN FULL' : (selectedDetailRental?.payment_status || 'UNPAID').toUpperCase()}
                    </Text>
                  </View>
                </View>

                <View style={{ flexDirection: 'row', justifyContent: 'space-between', marginTop: 12 }}>
                  <Text style={{ fontSize: 13, color: colors.textSub }}>Total Amount</Text>
                  <Text style={{ fontSize: 16, fontWeight: '900', color: colors.accent }}>
                    ₱{selectedDetailRental?.total_amount.toLocaleString()}
                  </Text>
                </View>
                <View style={{ flexDirection: 'row', justifyContent: 'space-between', marginTop: 6 }}>
                  <Text style={{ fontSize: 12, color: colors.textMute }}>Payment Method</Text>
                  <Text style={{ fontSize: 12, fontWeight: '600', color: colors.textSub }}>
                    {selectedDetailRental?.payment_method || 'Online Payment'}
                  </Text>
                </View>
                <View style={{ flexDirection: 'row', justifyContent: 'space-between', marginTop: 4 }}>
                  <Text style={{ fontSize: 12, color: colors.textMute }}>Booking Reference</Text>
                  <Text style={{ fontSize: 12, fontWeight: '700', color: colors.text }}>
                    RENT-{selectedDetailRental?.id}
                  </Text>
                </View>
              </View>

              {/* Cancellation & Refund Policy Box */}
              {selectedDetailRental?.cancellation ? (
                <View style={[styles.detailSectionBox, { backgroundColor: isDark ? '#1E2330' : '#F8FAFC', borderColor: colors.border, marginTop: 12 }]}>
                  <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
                    <Text style={[styles.detailSectionTitle, { color: colors.text }]}>Cancellation &amp; Refund Policy</Text>
                    <View
                      style={{
                        backgroundColor: selectedDetailRental.cancellation.can_cancel ? '#DCFCE7' : (isDark ? '#334155' : '#F1F5F9'),
                        paddingHorizontal: 8,
                        paddingVertical: 3,
                        borderRadius: 6,
                      }}
                    >
                      <Text
                        style={{
                          fontSize: 10,
                          fontWeight: '800',
                          color: selectedDetailRental.cancellation.can_cancel ? '#166534' : colors.textSub,
                        }}
                      >
                        {selectedDetailRental.cancellation.policy.toUpperCase()} POLICY
                      </Text>
                    </View>
                  </View>

                  <Text style={{ fontSize: 13, color: colors.textSub, marginTop: 6, lineHeight: 18 }}>
                    {selectedDetailRental.cancellation.policy_label}
                  </Text>

                  {selectedDetailRental.cancellation.can_cancel ? (
                    <View style={[styles.cancellationNoticeBox, { backgroundColor: isDark ? '#064E3B22' : '#F0FDF4', borderColor: isDark ? '#05966955' : '#BBF7D0' }]}>
                      <Ionicons name="shield-checkmark" size={16} color="#059669" />
                      <View style={{ flex: 1, marginLeft: 8 }}>
                        <Text style={{ fontSize: 12, fontWeight: '700', color: isDark ? '#34D399' : '#166534' }}>
                          Eligible for Free Cancellation
                        </Text>
                        <Text style={{ fontSize: 11, color: isDark ? '#A7F3D0' : '#15803D', marginTop: 2 }}>
                          Deadline: {selectedDetailRental.cancellation.deadline_formatted} ({selectedDetailRental.cancellation.remaining_text})
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
                          {selectedDetailRental.cancellation.ineligible_reason || 'Cancellation is unavailable for this reservation.'}
                        </Text>
                      </View>
                    </View>
                  )}

                  {selectedDetailRental.status === 'refunded' ? (
                    <View style={{ marginTop: 10, paddingTop: 10, borderTopWidth: 1, borderColor: colors.border }}>
                      <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
                        <Text style={{ fontSize: 12, color: colors.textMute }}>Refund Amount</Text>
                        <Text style={{ fontSize: 15, fontWeight: '900', color: '#059669' }}>
                          ₱{selectedDetailRental.cancellation.refund_amount.toLocaleString()}
                        </Text>
                      </View>
                      <View style={{ flexDirection: 'row', justifyContent: 'space-between', marginTop: 4 }}>
                        <Text style={{ fontSize: 12, color: colors.textMute }}>Refund Status</Text>
                        <Text style={{ fontSize: 12, fontWeight: '700', color: '#2563EB' }}>
                          Initiated ({selectedDetailRental.cancellation.processing_time})
                        </Text>
                      </View>
                    </View>
                  ) : selectedDetailRental.cancellation.can_cancel && selectedDetailRental.cancellation.refund_amount > 0 ? (
                    <View style={{ marginTop: 10, paddingTop: 8, borderTopWidth: 1, borderColor: colors.border, flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
                      <Text style={{ fontSize: 12, color: colors.textMute }}>Applicable Refund on Cancel</Text>
                      <Text style={{ fontSize: 14, fontWeight: '800', color: '#059669' }}>
                        ₱{selectedDetailRental.cancellation.refund_amount.toLocaleString()}
                      </Text>
                    </View>
                  ) : null}
                </View>
              ) : null}

              {/* Relevant Actions inside Details Modal */}
              <View style={{ marginTop: 16, gap: 10 }}>
                {selectedDetailRental && isRentalCompleted(selectedDetailRental) ? (
                  <TouchableOpacity
                    style={[
                      styles.rateReviewBtn,
                      { borderColor: '#F59E0B', backgroundColor: isDark ? '#78350F22' : '#FFFBEB', marginTop: 0 },
                    ]}
                    onPress={() => {
                      const item = selectedDetailRental;
                      setSelectedDetailRental(null);
                      openReviewModal(item);
                    }}
                  >
                    <Ionicons name="star" size={16} color="#F59E0B" />
                    <Text style={[styles.rateReviewBtnText, { color: isDark ? '#FBBF24' : '#D97706' }]}>
                      {selectedDetailRental.has_reviewed ? 'Edit Your Review & Rating' : 'Rate & Review This Vehicle'}
                    </Text>
                  </TouchableOpacity>
                ) : null}

                {selectedDetailRental?.can_report ? (
                  <TouchableOpacity
                    style={[styles.reportBtn, { borderColor: '#EF4444', marginTop: 0 }]}
                    onPress={() => {
                      const item = selectedDetailRental;
                      setSelectedDetailRental(null);
                      router.push({ pathname: '/rental-report/[id]', params: { id: String(item.id), title: item.vehicle_name } });
                    }}
                  >
                    <Ionicons name="flag-outline" size={16} color="#EF4444" />
                    <Text style={styles.reportBtnText}>Report a Problem</Text>
                  </TouchableOpacity>
                ) : null}

                {/* Cancel Reservation Action — hidden when completed, disabled when ineligible */}
                {selectedDetailRental &&
                !isRentalCompleted(selectedDetailRental) &&
                selectedDetailRental.status !== 'cancelled' &&
                selectedDetailRental.status !== 'refunded' ? (
                  <TouchableOpacity
                    style={[
                      styles.cancelActionBtn,
                      {
                        borderColor: selectedDetailRental.cancellation?.can_cancel ? '#EF4444' : colors.border,
                        backgroundColor: selectedDetailRental.cancellation?.can_cancel ? (isDark ? '#7F1D1D22' : '#FEF2F2') : (isDark ? '#1E2330' : '#F1F5F9'),
                        opacity: selectedDetailRental.cancellation?.can_cancel ? 1 : 0.6,
                      },
                    ]}
                    disabled={!selectedDetailRental.cancellation?.can_cancel || isSubmittingCancel}
                    onPress={() => handleCancelRental(selectedDetailRental)}
                  >
                    {isSubmittingCancel ? (
                      <ActivityIndicator size="small" color="#EF4444" />
                    ) : (
                      <>
                        <Ionicons
                          name="close-circle-outline"
                          size={16}
                          color={selectedDetailRental.cancellation?.can_cancel ? '#EF4444' : colors.textMute}
                        />
                        <Text
                          style={[
                            styles.cancelActionText,
                            { color: selectedDetailRental.cancellation?.can_cancel ? '#EF4444' : colors.textMute },
                          ]}
                        >
                          {selectedDetailRental.cancellation?.can_cancel ? 'Cancel Reservation' : 'Cancellation Unavailable'}
                        </Text>
                      </>
                    )}
                  </TouchableOpacity>
                ) : null}

                <TouchableOpacity
                  style={[styles.cancelModalBtn, { borderColor: colors.border }]}
                  onPress={() => setSelectedDetailRental(null)}
                >
                  <Text style={[styles.cancelModalBtnText, { color: colors.text }]}>Close</Text>
                </TouchableOpacity>
              </View>
            </ScrollView>
          </View>
        </View>
      </Modal>

      {/* ── Rate and Feedback Modal ── */}
      <Modal
        visible={reviewModalVisible}
        transparent
        animationType="fade"
        onRequestClose={closeReviewModal}
      >
        <KeyboardAvoidingView
          style={styles.modalBackdrop}
          behavior={Platform.OS === 'ios' ? 'padding' : undefined}
        >
          <View style={[styles.modalCard, { backgroundColor: colors.card, borderColor: colors.border }]}>
            <View style={styles.modalHeader}>
              <View style={{ flex: 1 }}>
                <Text style={[styles.modalTitle, { color: colors.text }]}>
                  {selectedRental?.has_reviewed ? 'Edit Your Review' : 'Rate & Review'}
                </Text>
                <Text style={[styles.modalSubtitle, { color: colors.textSub }]} numberOfLines={1}>
                  {selectedRental?.vehicle_name}
                </Text>
                <Text style={[styles.modalShopName, { color: colors.textMute }]} numberOfLines={1}>
                  Provider: {selectedRental?.shop_name}
                </Text>
              </View>
              <TouchableOpacity onPress={closeReviewModal} style={styles.closeBtn} activeOpacity={0.7}>
                <Ionicons name="close" size={24} color={colors.textSub} />
              </TouchableOpacity>
            </View>

            <ScrollView
              showsVerticalScrollIndicator={false}
              keyboardShouldPersistTaps="handled"
              contentContainerStyle={{ paddingBottom: 10 }}
            >
              {/* Star Rating Selector */}
              <View style={styles.starsContainer}>
                <View style={styles.starsRow}>
                  {[1, 2, 3, 4, 5].map((star) => (
                    <TouchableOpacity
                      key={star}
                      activeOpacity={0.7}
                      onPress={() => setRating(star)}
                      style={styles.starTouch}
                    >
                      <Ionicons
                        name={star <= rating ? 'star' : 'star-outline'}
                        size={38}
                        color={star <= rating ? '#F59E0B' : colors.textMute}
                      />
                    </TouchableOpacity>
                  ))}
                </View>
                <Text style={[styles.ratingLabelText, { color: rating > 0 ? '#F59E0B' : colors.textMute }]}>
                  {ratingLabel(rating)}
                </Text>
              </View>

              {/* Feedback Input */}
              <View style={styles.feedbackSection}>
                <View style={styles.feedbackHeaderRow}>
                  <Text style={[styles.fieldLabel, { color: colors.text }]}>Written Feedback</Text>
                  <Text style={[styles.charCount, { color: colors.textMute }]}>{feedback.length}/1000</Text>
                </View>
                <TextInput
                  style={[
                    styles.feedbackInput,
                    {
                      backgroundColor: isDark ? colors.cardAlt : '#F8FAFC',
                      color: colors.text,
                      borderColor: colors.border,
                    },
                  ]}
                  placeholder="Share details about the vehicle condition, provider communication, and handover experience..."
                  placeholderTextColor={colors.textMute}
                  value={feedback}
                  onChangeText={setFeedback}
                  multiline
                  numberOfLines={4}
                  maxLength={1000}
                  textAlignVertical="top"
                />
              </View>

              {/* Validation Error Banner */}
              {reviewError ? (
                <View style={styles.errorBanner}>
                  <Ionicons name="alert-circle" size={16} color="#DC2626" />
                  <Text style={styles.errorText}>{reviewError}</Text>
                </View>
              ) : null}

              {/* Action Buttons */}
              <View style={styles.modalActionsRow}>
                <TouchableOpacity
                  style={[styles.cancelModalBtn, { borderColor: colors.border }]}
                  onPress={closeReviewModal}
                  disabled={submittingReview}
                  activeOpacity={0.7}
                >
                  <Text style={[styles.cancelModalBtnText, { color: colors.textSub }]}>Cancel</Text>
                </TouchableOpacity>

                <TouchableOpacity
                  style={[
                    styles.submitModalBtn,
                    { backgroundColor: colors.primary },
                    submittingReview && { opacity: 0.6 },
                  ]}
                  onPress={handleSubmitReview}
                  disabled={submittingReview}
                  activeOpacity={0.85}
                >
                  {submittingReview ? (
                    <ActivityIndicator size="small" color="#FFFFFF" />
                  ) : (
                    <Text style={styles.submitModalBtnText}>
                      {selectedRental?.has_reviewed ? 'Save Changes' : 'Submit Review'}
                    </Text>
                  )}
                </TouchableOpacity>
              </View>
            </ScrollView>
          </View>
        </KeyboardAvoidingView>
      </Modal>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1 },
  centered: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 24 },
  header: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: 8, paddingBottom: 12, borderBottomWidth: StyleSheet.hairlineWidth },
  headerBtn: { width: 44, height: 36, alignItems: 'center', justifyContent: 'center' },
  headerTitle: { fontSize: 18, fontWeight: '800' },
  card: { borderRadius: 18, borderWidth: 1, padding: 16 },
  cardTop: { flexDirection: 'row', alignItems: 'flex-start', gap: 10 },
  vehicle: { fontSize: 16, fontWeight: '800' },
  sub: { fontSize: 12.5, fontWeight: '600', marginTop: 3 },
  statusPill: { paddingHorizontal: 10, paddingVertical: 5, borderRadius: 999 },
  statusText: { fontSize: 11, fontWeight: '800' },
  divider: { height: 1, marginVertical: 12 },
  metaRow: { flexDirection: 'row', alignItems: 'center', gap: 8, marginBottom: 6 },
  metaText: { fontSize: 13, fontWeight: '600' },
  reportBanner: { flexDirection: 'row', alignItems: 'center', gap: 8, borderRadius: 12, padding: 10, marginTop: 8 },
  reportText: { fontSize: 12.5, fontWeight: '700', flex: 1, lineHeight: 18 },
  reportBtn: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8, borderWidth: 1.5, borderRadius: 12, paddingVertical: 11, marginTop: 12 },
  reportBtnText: { color: '#EF4444', fontSize: 14, fontWeight: '800' },
  emptyTitle: { fontSize: 18, fontWeight: '800', marginTop: 14 },
  emptySub: { fontSize: 13.5, marginTop: 6, textAlign: 'center' },
  browseBtn: { marginTop: 18, paddingHorizontal: 26, paddingVertical: 12, borderRadius: 999 },
  browseText: { color: '#FFFFFF', fontWeight: '800', fontSize: 14 },

  // Rate and Review Button for Unreviewed Rentals
  rateReviewBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    borderWidth: 1.5,
    borderRadius: 12,
    paddingVertical: 11,
    marginTop: 12,
  },
  rateReviewBtnText: {
    fontSize: 14,
    fontWeight: '800',
  },

  // Reviewed Box for Already Reviewed Rentals
  reviewedBox: {
    borderRadius: 12,
    borderWidth: 1,
    padding: 12,
    marginTop: 12,
  },
  reviewedTopRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  reviewedStarsRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 3,
  },
  reviewedRatingScore: {
    fontSize: 13,
    fontWeight: '800',
    marginLeft: 4,
  },
  editReviewBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 5,
    paddingHorizontal: 10,
    paddingVertical: 5,
    borderRadius: 8,
    borderWidth: 1,
  },
  editReviewText: {
    fontSize: 12.5,
    fontWeight: '700',
  },
  reviewedFeedbackText: {
    fontSize: 12.5,
    fontStyle: 'italic',
    lineHeight: 18,
    marginTop: 8,
  },

  // Modal Styles
  modalBackdrop: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.65)',
    justifyContent: 'center',
    padding: 16,
  },
  modalCard: {
    borderRadius: 22,
    borderWidth: 1,
    padding: 20,
    maxHeight: '90%',
  },
  modalHeader: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    justifyContent: 'space-between',
    marginBottom: 16,
  },
  modalTitle: {
    fontSize: 20,
    fontWeight: '800',
  },
  modalSubtitle: {
    fontSize: 13.5,
    fontWeight: '700',
    marginTop: 3,
  },
  modalShopName: {
    fontSize: 12,
    fontWeight: '500',
    marginTop: 2,
  },
  closeBtn: {
    width: 32,
    height: 32,
    alignItems: 'center',
    justifyContent: 'center',
  },
  starsContainer: {
    alignItems: 'center',
    paddingVertical: 14,
  },
  starsRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  starTouch: {
    padding: 4,
  },
  ratingLabelText: {
    fontSize: 14,
    fontWeight: '700',
    marginTop: 8,
  },
  feedbackSection: {
    marginTop: 10,
  },
  feedbackHeaderRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 6,
  },
  fieldLabel: {
    fontSize: 13.5,
    fontWeight: '700',
  },
  charCount: {
    fontSize: 11,
    fontWeight: '500',
  },
  feedbackInput: {
    borderWidth: 1,
    borderRadius: 12,
    padding: 12,
    fontSize: 14,
    minHeight: 96,
  },
  errorBanner: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    backgroundColor: '#FEE2E2',
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 8,
    marginTop: 12,
  },
  errorText: {
    color: '#DC2626',
    fontSize: 12.5,
    fontWeight: '600',
    flex: 1,
  },
  modalActionsRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    marginTop: 18,
  },
  cancelModalBtn: {
    flex: 1,
    borderWidth: 1,
    borderRadius: 12,
    paddingVertical: 12,
    alignItems: 'center',
    justifyContent: 'center',
  },
  cancelModalBtnText: {
    fontSize: 14,
    fontWeight: '700',
  },
  submitModalBtn: {
    flex: 1.5,
    borderRadius: 12,
    paddingVertical: 12,
    alignItems: 'center',
    justifyContent: 'center',
  },
  submitModalBtnText: {
    color: '#FFFFFF',
    fontSize: 14,
    fontWeight: '800',
  },
  historyIconBtn: {
    width: 38,
    height: 38,
    borderRadius: 19,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
    position: 'relative',
    marginRight: 6,
  },
  historyBadge: {
    position: 'absolute',
    top: -4,
    right: -4,
    minWidth: 17,
    height: 17,
    borderRadius: 8.5,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 4,
  },
  historyBadgeText: {
    fontSize: 9,
    fontWeight: '800',
  },
  segmentWrap: {
    flexDirection: 'row',
    marginHorizontal: 16,
    marginTop: 10,
    marginBottom: 4,
    padding: 4,
    borderRadius: 12,
    borderWidth: 1,
    gap: 4,
  },
  segmentBtn: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 6,
    paddingVertical: 8,
    borderRadius: 8,
  },
  segmentBtnActive: {
    elevation: 2,
    shadowColor: '#000',
    shadowOpacity: 0.08,
    shadowOffset: { width: 0, height: 1 },
    shadowRadius: 2,
  },
  segmentText: {
    fontSize: 13,
  },
  historyBanner: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    borderWidth: 1,
    borderRadius: 8,
    paddingHorizontal: 10,
    paddingVertical: 6,
    marginTop: 10,
  },
  historyBannerText: {
    fontSize: 12,
    fontWeight: '700',
  },
  viewDetailsBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 6,
    borderWidth: 1,
    borderRadius: 10,
    paddingVertical: 9,
    marginTop: 10,
  },
  viewDetailsBtnText: {
    fontSize: 13,
    fontWeight: '700',
  },
  detailSectionBox: {
    borderWidth: 1,
    borderRadius: 14,
    padding: 14,
  },
  detailSectionTitle: {
    fontSize: 14,
    fontWeight: '800',
  },
  detailMainValue: {
    fontSize: 16,
    fontWeight: '800',
    marginTop: 4,
  },
  detailSubValue: {
    fontSize: 13,
    marginTop: 2,
  },
  detailBadgeRow: {
    flexDirection: 'row',
    gap: 6,
  },
  detailPill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 6,
  },
  detailFieldLabel: {
    fontSize: 10,
    fontWeight: '800',
    letterSpacing: 0.5,
  },
  detailFieldValue: {
    fontSize: 13,
    fontWeight: '700',
    marginTop: 2,
  },
  cancellationNoticeBox: {
    flexDirection: 'row',
    alignItems: 'center',
    borderWidth: 1,
    borderRadius: 10,
    padding: 10,
    marginTop: 10,
  },
  cancelActionBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    borderWidth: 1.5,
    borderRadius: 12,
    paddingVertical: 11,
  },
  cancelActionText: {
    fontSize: 14,
    fontWeight: '800',
  },
});
