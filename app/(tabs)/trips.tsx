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

const STATUS_COLOR: Record<string, string> = {
  confirmed: '#22C55E',
  pending: '#F59E0B',
  completed: '#3B82F6',
  cancelled: '#EF4444',
  refunded: '#9CA3AF',
};

// Friendly labels shown to the tourist for each booking status.
const STATUS_LABEL: Record<string, string> = {
  pending: 'Awaiting confirmation',
  confirmed: 'Approved',
  completed: 'Completed',
  cancelled: 'Cancelled',
  refunded: 'Refunded',
  disputed: 'In review',
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
      setBookings(data.bookings);
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
    const statusColor = STATUS_COLOR[item.status] ?? '#9CA3AF';
    // The trip map only surfaces paid + confirmed/completed bookings server-side,
    // so only show the navigate action for those statuses.
    const canNavigate = item.status === 'confirmed' || item.status === 'completed';
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
          <View style={styles.metaRow}>
            <Ionicons name="calendar-outline" size={13} color={colors.textSub} />
            <Text style={[styles.metaText, { color: colors.textSub }]}>{item.booking_date}</Text>
            {item.booking_time ? (
              <>
                <Ionicons name="time-outline" size={13} color={colors.textSub} style={{ marginLeft: 12 }} />
                <Text style={[styles.metaText, { color: colors.textSub }]}>{formatTime(item.booking_time)}</Text>
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
});
