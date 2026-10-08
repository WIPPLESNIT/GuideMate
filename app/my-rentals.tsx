import { Ionicons } from '@expo/vector-icons';
import { useFocusEffect, useRouter } from 'expo-router';
import React, { useCallback, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  FlatList,
  RefreshControl,
  StatusBar,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import { useTheme } from '../lib/theme';
import { ApiRental, getMyRentals, isUnauthorized } from '../services/api';

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

export default function MyRentalsScreen() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { colors, isDark, shadow } = useTheme();

  const [rentals, setRentals] = useState<ApiRental[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

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

  const renderItem = ({ item }: { item: ApiRental }) => (
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
    </View>
  );

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
});
