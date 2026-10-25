import { Ionicons } from '@expo/vector-icons';
import { useFocusEffect, useRouter } from 'expo-router';
import React, { useCallback, useState } from 'react';
import { ActivityIndicator, FlatList, Image, StatusBar, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import { EmptyState, ScreenTitle } from '../../components/ui';
import { clearSession, restoreSession } from '../../lib/authStore';
import { usePreferences } from '../../lib/preferences';
import { useTheme } from '../../lib/theme';
import { ApiBooking, getBookings, isUnauthorized, resolveImage } from '../../services/api';

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
  pending: '#D97706',
  accepted: '#2563EB',
  deposit_paid: '#0D9488',
  checked_in: '#0284C7',
  completed: '#3B82F6',
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
  checked_in: 'Checked In',
  completed: 'Completed',
  cancelled: 'Cancelled',
  refunded: 'Refunded',
  disputed: 'In review',
  no_show: 'No-Show',
};

export default function TripsScreen() {
  const router = useRouter();
  const { formatPrice } = usePreferences();
  const insets = useSafeAreaInsets();
  const { colors, isDark, shadow } = useTheme();

  const [bookings, setBookings] = useState<ApiBooking[]>([]);
  const [loading, setLoading] = useState(true);
  const [loggedIn, setLoggedIn] = useState(true);
  const [error, setError] = useState('');

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    // restoreSession both returns the cached user AND re-applies the auth token,
    // so authenticated calls work even right after an app reload.
    const session = await restoreSession();
    if (!session) {
      setLoggedIn(false);
      setLoading(false);
      return;
    }
    setLoggedIn(true);
    try {
      const data = await getBookings();
      setBookings(Array.isArray(data?.bookings) ? data.bookings : []);
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
    }
  }, []);

  useFocusEffect(
    useCallback(() => {
      load();
    }, [load])
  );

  const renderItem = ({ item }: { item: ApiBooking }) => {
    const isHotel = Boolean(item.room_name || item.hotel_status || (item.deposit_amount && item.deposit_amount > 0));
    const effectiveStatus = item.hotel_status || item.status;
    const statusColor = STATUS_COLOR[effectiveStatus] ?? STATUS_COLOR[item.status] ?? '#9CA3AF';
    const canNavigate = item.status !== 'cancelled' && item.status !== 'refunded' && item.hotel_status !== 'cancelled';

    if (isHotel) {
      const checkInFormatted = formatDate(item.check_in_date || item.booking_date);
      const checkOutFormatted = item.check_out_date ? formatDate(item.check_out_date) : '';
      const nights = item.nights || 1;
      const deposit = item.deposit_amount ?? 0;
      const balance = item.balance_amount ?? Math.max(0, item.total_amount - deposit);
      const isConfirmed = item.hotel_status === 'confirmed' || item.status === 'confirmed';
      const isDepositPaid = item.hotel_status === 'deposit_paid';
      const isAccepted = item.hotel_status === 'accepted';
      const isCheckedIn = item.hotel_status === 'checked_in';

      return (
        <TouchableOpacity
          style={[styles.hotelCard, { backgroundColor: colors.card, borderColor: colors.border }, shadow.sm]}
          activeOpacity={0.88}
          onPress={() => router.push({ pathname: '/listing/[slug]', params: { slug: item.listing_slug } })}
        >
          {/* Header: Hotel Image, Title, Room Badge & Status */}
          <View style={styles.hotelHeaderRow}>
            <Image source={{ uri: resolveImage(item.image) }} style={styles.hotelImage} />
            <View style={styles.hotelHeaderBody}>
              <View style={styles.topRow}>
                <Text style={[styles.cardTitle, { color: colors.text }]} numberOfLines={1}>{item.listing_title}</Text>
                <View style={[styles.statusChip, { backgroundColor: statusColor + '22' }]}>
                  <View style={[styles.statusDot, { backgroundColor: statusColor }]} />
                  <Text style={[styles.statusText, { color: statusColor }]}>{item.status_label ?? STATUS_LABEL[effectiveStatus] ?? effectiveStatus}</Text>
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

          {/* Prominent Confirmation Banner */}
          {isConfirmed ? (
            <View style={[styles.confirmBanner, { backgroundColor: isDark ? '#064E3B33' : '#ECFDF5', borderColor: isDark ? '#05966966' : '#A7F3D0' }]}>
              <Ionicons name="checkmark-circle" size={18} color="#059669" />
              <View style={{ flex: 1, marginLeft: 8 }}>
                <Text style={[styles.confirmBannerTitle, { color: isDark ? '#34D399' : '#065F46' }]}>Reservation Confirmed · Room Held</Text>
                <Text style={[styles.confirmBannerSubtitle, { color: isDark ? '#A7F3D0' : '#047857' }]}>Your room is reserved and guaranteed for your stay.</Text>
              </View>
            </View>
          ) : isDepositPaid ? (
            <View style={[styles.confirmBanner, { backgroundColor: isDark ? '#0F766E33' : '#F0FDFA', borderColor: isDark ? '#14B8A666' : '#99F6E4' }]}>
              <Ionicons name="shield-checkmark" size={18} color="#0D9488" />
              <View style={{ flex: 1, marginLeft: 8 }}>
                <Text style={[styles.confirmBannerTitle, { color: isDark ? '#2DD4BF' : '#115E59' }]}>Deposit Paid · Verification in Progress</Text>
                <Text style={[styles.confirmBannerSubtitle, { color: isDark ? '#99F6E4' : '#0F766E' }]}>The hotel front desk is verifying your payment.</Text>
              </View>
            </View>
          ) : isAccepted ? (
            <View style={[styles.confirmBanner, { backgroundColor: isDark ? '#1E3A8A33' : '#EFF6FF', borderColor: isDark ? '#3B82F666' : '#BFDBFE' }]}>
              <Ionicons name="thumbs-up" size={18} color="#2563EB" />
              <View style={{ flex: 1, marginLeft: 8 }}>
                <Text style={[styles.confirmBannerTitle, { color: isDark ? '#93C5FD' : '#1E40AF' }]}>Reservation Accepted</Text>
                <Text style={[styles.confirmBannerSubtitle, { color: isDark ? '#BFDBFE' : '#1D4ED8' }]}>Reservation approved by hotel. Awaiting deposit confirmation.</Text>
              </View>
            </View>
          ) : isCheckedIn ? (
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
              <Text style={[styles.priceColDeposit, { color: '#059669' }]}>
                {deposit > 0 ? `✓ ${formatPrice(deposit)}` : '₱0'}
              </Text>
            </View>
            <View style={styles.priceCol}>
              <Text style={[styles.priceColLabel, { color: colors.textMute }]}>Remaining Due</Text>
              <Text style={[styles.priceColBalance, { color: balance > 0 ? '#D97706' : '#059669' }]}>
                {balance > 0 ? formatPrice(balance) : 'Settled'}
              </Text>
            </View>
          </View>

          {/* Front Desk Instruction Box */}
          {item.status !== 'cancelled' && item.hotel_status !== 'cancelled' ? (
            balance > 0 ? (
              <View style={[styles.frontDeskNotice, { backgroundColor: isDark ? '#78350F22' : '#FFFBEB', borderColor: isDark ? '#F59E0B44' : '#FDE68A' }]}>
                <Ionicons name="wallet-outline" size={16} color="#D97706" style={{ marginTop: 1 }} />
                <View style={{ flex: 1, marginLeft: 8 }}>
                  <Text style={[styles.frontDeskNoticeTitle, { color: isDark ? '#FCD34D' : '#92400E' }]}>
                    Pay remaining balance at front desk
                  </Text>
                  <Text style={[styles.frontDeskNoticeText, { color: isDark ? '#FDE68A' : '#78350F' }]}>
                    Please pay the remaining amount of <Text style={{ fontWeight: '800' }}>{formatPrice(balance)}</Text> at the hotel front desk upon arrival / check-in.
                  </Text>
                </View>
              </View>
            ) : (
              <View style={[styles.frontDeskNotice, { backgroundColor: isDark ? '#064E3B22' : '#F0FDF4', borderColor: isDark ? '#10B98144' : '#BBF7D0' }]}>
                <Ionicons name="checkmark-done-circle" size={16} color="#10B981" style={{ marginTop: 1 }} />
                <Text style={[styles.frontDeskNoticeText, { color: isDark ? '#6EE7B7' : '#166534', marginLeft: 8, flex: 1 }]}>
                  Your stay is fully settled. No remaining balance due at the front desk.
                </Text>
              </View>
            )
          ) : null}

          {/* Action buttons */}
          <View style={styles.hotelActionsRow}>
            {canNavigate ? (
              <TouchableOpacity
                style={[styles.navBtn, { backgroundColor: colors.primary }]}
                activeOpacity={0.85}
                onPress={() => router.push({ pathname: '/trip-map', params: { booking: String(item.id) } })}
              >
                <Ionicons name="navigate" size={14} color="#FFFFFF" />
                <Text style={styles.navBtnText}>Directions &amp; Map</Text>
              </TouchableOpacity>
            ) : null}
            {item.can_review ? (
              <TouchableOpacity
                style={[styles.actionBtn, { borderColor: colors.primary }]}
                activeOpacity={0.85}
                onPress={() => router.push({ pathname: '/review/[id]', params: { id: String(item.listing_id), title: item.listing_title } })}
              >
                <Ionicons name="star-outline" size={14} color={colors.primary} />
                <Text style={[styles.actionText, { color: colors.primary }]}>Rate stay</Text>
              </TouchableOpacity>
            ) : null}
            {item.dispute_status ? (
              <TouchableOpacity
                style={[styles.actionBtn, { borderColor: colors.textMute }]}
                activeOpacity={0.85}
                onPress={() => router.push({ pathname: '/report/[id]', params: { id: String(item.id), title: item.listing_title } })}
              >
                <Ionicons name="flag" size={14} color={colors.textMute} />
                <Text style={[styles.actionText, { color: colors.textMute }]}>View report</Text>
              </TouchableOpacity>
            ) : item.can_report ? (
              <TouchableOpacity
                style={[styles.actionBtn, { borderColor: '#EF4444' }]}
                activeOpacity={0.85}
                onPress={() => router.push({ pathname: '/report/[id]', params: { id: String(item.id), title: item.listing_title } })}
              >
                <Ionicons name="flag-outline" size={14} color="#EF4444" />
                <Text style={[styles.actionText, { color: '#EF4444' }]}>Report issue</Text>
              </TouchableOpacity>
            ) : null}
          </View>
        </TouchableOpacity>
      );
    }

    return (
      <TouchableOpacity
        style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }, shadow.sm]}
        activeOpacity={0.88}
        onPress={() => router.push({ pathname: '/listing/[slug]', params: { slug: item.listing_slug } })}
      >
        <Image source={{ uri: resolveImage(item.image) }} style={styles.cardImage} />
        <View style={styles.cardBody}>
          <View style={styles.topRow}>
            <Text style={[styles.cardTitle, { color: colors.text }]} numberOfLines={1}>{item.listing_title}</Text>
            <View style={[styles.statusChip, { backgroundColor: statusColor + '22' }]}>
              <View style={[styles.statusDot, { backgroundColor: statusColor }]} />
              <Text style={[styles.statusText, { color: statusColor }]}>{item.status_label ?? STATUS_LABEL[item.status] ?? item.status}</Text>
            </View>
          </View>
          <View style={[styles.metaRow, { flexWrap: 'wrap' }]}>
            <Ionicons name="calendar-outline" size={13} color={colors.textSub} />
            <Text style={[styles.metaText, { color: colors.textSub }]}>
              {item.booking_dates && item.booking_dates.length > 1
                ? `${item.booking_dates.length} days: ${item.booking_dates.join(', ')}`
                : item.booking_date}
            </Text>
            {item.booking_time ? (
              <>
                <Ionicons name="time-outline" size={13} color={colors.textSub} style={{ marginLeft: 12 }} />
                <Text style={[styles.metaText, { color: colors.textSub }]}>
                  {formatTime(item.booking_time)}
                  {item.booking_end_time ? ` – ${formatTime(item.booking_end_time)}` : ''}
                </Text>
              </>
            ) : null}
          </View>
          <View style={styles.metaRow}>
            <Ionicons name="people-outline" size={13} color={colors.textSub} />
            <Text style={[styles.metaText, { color: colors.textSub }]}>{item.guests} guest{item.guests > 1 ? 's' : ''}</Text>
          </View>
          <View style={styles.bottomRow}>
            <Text style={[styles.price, { color: colors.accent }]}>{formatPrice(item.total_amount)}</Text>
            {canNavigate ? (
              <TouchableOpacity
                style={[styles.navBtn, { backgroundColor: colors.primary }]}
                activeOpacity={0.85}
                onPress={() => router.push({ pathname: '/trip-map', params: { booking: String(item.id) } })}
              >
                <Ionicons name="navigate" size={14} color="#FFFFFF" />
                <Text style={styles.navBtnText}>Navigate</Text>
              </TouchableOpacity>
            ) : null}
          </View>

          {item.can_review || item.can_report || item.dispute_status ? (
            <View style={styles.actionsRow}>
              {item.can_review ? (
                <TouchableOpacity
                  style={[styles.actionBtn, { borderColor: colors.primary }]}
                  activeOpacity={0.85}
                  onPress={() => router.push({ pathname: '/review/[id]', params: { id: String(item.listing_id), title: item.listing_title } })}
                >
                  <Ionicons name="star-outline" size={14} color={colors.primary} />
                  <Text style={[styles.actionText, { color: colors.primary }]}>Rate your guide</Text>
                </TouchableOpacity>
              ) : null}
              {item.dispute_status ? (
                <TouchableOpacity
                  style={[styles.actionBtn, { borderColor: colors.textMute }]}
                  activeOpacity={0.85}
                  onPress={() => router.push({ pathname: '/report/[id]', params: { id: String(item.id), title: item.listing_title } })}
                >
                  <Ionicons name="flag" size={14} color={colors.textMute} />
                  <Text style={[styles.actionText, { color: colors.textMute }]}>View report</Text>
                </TouchableOpacity>
              ) : item.can_report ? (
                <TouchableOpacity
                  style={[styles.actionBtn, { borderColor: '#EF4444' }]}
                  activeOpacity={0.85}
                  onPress={() => router.push({ pathname: '/report/[id]', params: { id: String(item.id), title: item.listing_title } })}
                >
                  <Ionicons name="flag-outline" size={14} color="#EF4444" />
                  <Text style={[styles.actionText, { color: '#EF4444' }]}>Report a problem</Text>
                </TouchableOpacity>
              ) : null}
            </View>
          ) : null}
        </View>
      </TouchableOpacity>
    );
  };

  return (
    <SafeAreaView style={[styles.container, { backgroundColor: colors.bgAlt }]} edges={['left', 'right']}>
      <StatusBar barStyle={isDark ? 'light-content' : 'dark-content'} />
      <View style={[styles.headerBar, { paddingTop: insets.top + 16 }]}>
        <ScreenTitle title="My Trips" subtitle="Your bookings & upcoming adventures" />
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
          data={bookings}
          renderItem={renderItem}
          keyExtractor={(item) => String(item.id)}
          contentContainerStyle={styles.listContent}
          showsVerticalScrollIndicator={false}
          ListEmptyComponent={
            <EmptyState
              icon={error ? 'cloud-offline-outline' : 'calendar-outline'}
              title={error ? 'Something went wrong' : 'No bookings yet'}
              subtitle={error || 'Book an experience and it will show up here.'}
              actionLabel={error ? 'Retry' : 'Explore Cebu'}
              onAction={error ? load : () => router.push('/things-to-do')}
            />
          }
        />
      )}
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1 },
  headerBar: { paddingHorizontal: 20, paddingBottom: 12 },
  center: { flexGrow: 1, alignItems: 'center', justifyContent: 'center', paddingTop: 60 },
  listContent: { paddingHorizontal: 16, paddingBottom: 24, flexGrow: 1 },
  card: { flexDirection: 'row', borderRadius: 18, overflow: 'hidden', marginBottom: 14, borderWidth: 1, padding: 8 },
  cardImage: { width: 104, height: 104, borderRadius: 14, resizeMode: 'cover', backgroundColor: '#00000011' },
  cardBody: { flex: 1, paddingHorizontal: 12, justifyContent: 'center' },
  topRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  cardTitle: { fontSize: 15, fontWeight: '800', flex: 1, marginRight: 8 },
  metaRow: { flexDirection: 'row', alignItems: 'center', marginTop: 8 },
  metaText: { fontSize: 12, marginLeft: 4, fontWeight: '600' },
  bottomRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginTop: 8 },
  price: { fontSize: 16, fontWeight: '900' },
  navBtn: { flexDirection: 'row', alignItems: 'center', gap: 5, paddingHorizontal: 12, paddingVertical: 7, borderRadius: 999 },
  navBtnText: { color: '#FFFFFF', fontSize: 12, fontWeight: '800' },
  actionsRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginTop: 10 },
  actionBtn: { flexDirection: 'row', alignItems: 'center', gap: 5, borderWidth: 1.5, borderRadius: 999, paddingHorizontal: 12, paddingVertical: 7 },
  actionText: { fontSize: 12, fontWeight: '800' },
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
});
