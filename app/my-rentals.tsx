import { Ionicons } from '@expo/vector-icons';
import { useFocusEffect, useRouter } from 'expo-router';
import React, { useCallback, useState } from 'react';
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
import { ApiRental, getMyRentals, isUnauthorized, submitRentalReview } from '../services/api';

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
    const isCompleted = item.status === 'completed' || item.can_review;
    const hasReviewed = !!item.has_reviewed && !!item.review;

    return (
      <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }, shadow.sm]}>
        <View style={styles.cardTop}>
          <View style={{ flex: 1 }}>
            <Text style={[styles.vehicle, { color: colors.text }]} numberOfLines={1}>{item.vehicle_name}</Text>
            <Text style={[styles.sub, { color: colors.textSub }]} numberOfLines={1}>{item.shop_name} · {item.location}</Text>
          </View>
          <View style={[styles.statusPill, { backgroundColor: statusColor(item.status) + '22' }]}>
            <Text style={[styles.statusText, { color: statusColor(item.status) }]}>{item.status_label}</Text>
          </View>
        </View>

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

        {item.report_status === 'open' ? (
          <View style={[styles.reportBanner, { backgroundColor: '#EF444415' }]}>
            <Ionicons name="alert-circle" size={16} color="#EF4444" />
            <Text style={[styles.reportText, { color: '#EF4444' }]}>
              Problem reported ({item.report_label}) — the owner is reviewing it.
            </Text>
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
      </View>
    );
  };

  return (
    <SafeAreaView style={[styles.container, { backgroundColor: colors.bgAlt }]} edges={['left', 'right']}>
      <StatusBar barStyle={isDark ? 'light-content' : 'dark-content'} />

      <View style={[styles.header, { backgroundColor: colors.card, borderBottomColor: colors.border, paddingTop: insets.top + 10 }]}>
        <TouchableOpacity onPress={() => router.back()} style={styles.headerBtn} activeOpacity={0.7}>
          <Ionicons name="chevron-back" size={26} color={colors.text} />
        </TouchableOpacity>
        <Text style={[styles.headerTitle, { color: colors.text }]}>My Rentals</Text>
        <View style={styles.headerBtn} />
      </View>

      {loading ? (
        <View style={styles.centered}>
          <ActivityIndicator size="large" color={colors.primary} />
        </View>
      ) : rentals.length === 0 ? (
        <View style={styles.centered}>
          <Ionicons name="car-outline" size={54} color={colors.textMute} />
          <Text style={[styles.emptyTitle, { color: colors.text }]}>No rentals yet</Text>
          <Text style={[styles.emptySub, { color: colors.textSub }]}>Reserve a vehicle and it will show up here.</Text>
          <TouchableOpacity style={[styles.browseBtn, { backgroundColor: colors.primary }]} onPress={() => router.replace('/car-rentals')} activeOpacity={0.85}>
            <Text style={styles.browseText}>Browse rentals</Text>
          </TouchableOpacity>
        </View>
      ) : (
        <FlatList
          data={rentals}
          keyExtractor={(r) => String(r.id)}
          renderItem={renderItem}
          contentContainerStyle={{ padding: 16, paddingBottom: 32 }}
          ItemSeparatorComponent={() => <View style={{ height: 14 }} />}
          refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor={colors.primary} />}
          showsVerticalScrollIndicator={false}
        />
      )}

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
});
