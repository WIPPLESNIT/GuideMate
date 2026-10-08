import { Ionicons } from '@expo/vector-icons';
import { useFocusEffect, useRouter } from 'expo-router';
import React, { useCallback, useState } from 'react';
import {
    ActivityIndicator,
    FlatList,
    RefreshControl,
    StatusBar,
    StyleSheet,
    Text,
    TouchableOpacity,
    useColorScheme,
    View,
} from 'react-native';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import { getSession } from '../lib/authStore';
import { FeedbackStatus, getMyFeedback, isUnauthorized, MyFeedback } from '../services/api';

const CATEGORY_LABEL: Record<string, string> = {
  general: 'General',
  bug: 'Bug report',
  feature: 'Feature request',
  praise: 'Praise',
};

function statusMeta(status: FeedbackStatus) {
  switch (status) {
    case 'reviewed':
      return { label: 'Reviewed', color: '#22C55E', icon: 'checkmark-circle' as const };
    case 'archived':
      return { label: 'Closed', color: '#6B7280', icon: 'archive' as const };
    default:
      return { label: 'Pending review', color: '#F59E0B', icon: 'time' as const };
  }
}

export default function MyFeedbackScreen() {
  const router = useRouter();
  const isDark = useColorScheme() === 'dark';
  const insets = useSafeAreaInsets();

  const [items, setItems] = useState<MyFeedback[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [loggedIn, setLoggedIn] = useState(true);

  const theme = {
    bg: isDark ? '#0F1012' : '#F2F3F5',
    card: isDark ? '#1E2029' : '#FFFFFF',
    border: isDark ? '#2A2D38' : '#ECECEC',
    textMain: isDark ? '#FFFFFF' : '#1A1A1A',
    textSub: isDark ? '#9CA3AF' : '#8A8A8A',
    accent: '#22C55E',
    star: '#F59E0B',
    chipBg: isDark ? '#15161A' : '#F1F5F9',
  };

  const load = useCallback(async () => {
    const session = await getSession();
    if (!session) {
      setLoggedIn(false);
      setLoading(false);
      return;
    }
    setLoggedIn(true);
    try {
      const res = await getMyFeedback();
      setItems(res.feedback);
    } catch (e) {
      if (isUnauthorized(e)) setLoggedIn(false);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useFocusEffect(
    useCallback(() => {
      setLoading(true);
      load();
    }, [load])
  );

  const onRefresh = () => {
    setRefreshing(true);
    load();
  };

  const renderItem = ({ item }: { item: MyFeedback }) => {
    const meta = statusMeta(item.status);
    const date = new Date(item.created_at.replace(' ', 'T'));
    const dateLabel = isNaN(date.getTime())
      ? item.created_at
      : date.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
    return (
      <View style={[styles.card, { backgroundColor: theme.card, borderColor: theme.border }]}>
        <View style={styles.cardTop}>
          <View style={[styles.chip, { backgroundColor: theme.chipBg }]}>
            <Text style={[styles.chipText, { color: theme.textSub }]}>
              {CATEGORY_LABEL[item.category] ?? 'General'}
            </Text>
          </View>
          <View style={[styles.statusPill, { backgroundColor: meta.color + '22' }]}>
            <Ionicons name={meta.icon} size={13} color={meta.color} style={{ marginRight: 4 }} />
            <Text style={[styles.statusText, { color: meta.color }]}>{meta.label}</Text>
          </View>
        </View>

        {item.rating > 0 ? (
          <View style={styles.stars}>
            {[1, 2, 3, 4, 5].map((n) => (
              <Ionicons
                key={n}
                name={n <= item.rating ? 'star' : 'star-outline'}
                size={15}
                color={n <= item.rating ? theme.star : theme.textSub}
                style={{ marginRight: 2 }}
              />
            ))}
          </View>
        ) : null}

        <Text style={[styles.message, { color: theme.textMain }]}>{item.message}</Text>
        <Text style={[styles.date, { color: theme.textSub }]}>{dateLabel}</Text>
      </View>
    );
  };

  return (
    <SafeAreaView style={[styles.container, { backgroundColor: theme.bg }]} edges={['left', 'right']}>
      <StatusBar barStyle={isDark ? 'light-content' : 'dark-content'} backgroundColor={theme.card} />

      <View style={[styles.header, { backgroundColor: theme.card, borderBottomColor: theme.border, paddingTop: insets.top + 12 }]}>
        <TouchableOpacity onPress={() => router.back()} style={styles.headerBack} activeOpacity={0.7}>
          <Ionicons name="chevron-back" size={26} color={theme.textMain} />
        </TouchableOpacity>
        <Text style={[styles.headerTitle, { color: theme.textMain }]}>My feedback</Text>
        <View style={styles.headerBack} />
      </View>

      {loading ? (
        <View style={styles.center}>
          <ActivityIndicator color={theme.accent} size="large" />
        </View>
      ) : !loggedIn ? (
        <View style={styles.center}>
          <Ionicons name="lock-closed-outline" size={44} color={theme.textSub} />
          <Text style={[styles.emptyTitle, { color: theme.textMain }]}>Log in to view your feedback</Text>
          <TouchableOpacity
            style={[styles.cta, { backgroundColor: theme.accent }]}
            activeOpacity={0.85}
            onPress={() => router.replace('/(auth)/login')}
          >
            <Text style={styles.ctaText}>Log in or register</Text>
          </TouchableOpacity>
        </View>
      ) : items.length === 0 ? (
        <View style={styles.center}>
          <Ionicons name="chatbubble-ellipses-outline" size={44} color={theme.textSub} />
          <Text style={[styles.emptyTitle, { color: theme.textMain }]}>No feedback yet</Text>
          <Text style={[styles.emptySub, { color: theme.textSub }]}>
            When you leave feedback, you can track its status here.
          </Text>
          <TouchableOpacity
            style={[styles.cta, { backgroundColor: theme.accent }]}
            activeOpacity={0.85}
            onPress={() => router.replace('/feedback')}
          >
            <Text style={styles.ctaText}>Leave feedback</Text>
          </TouchableOpacity>
        </View>
      ) : (
        <FlatList
          data={items}
          keyExtractor={(it) => String(it.id)}
          renderItem={renderItem}
          contentContainerStyle={{ padding: 16, paddingBottom: 40 }}
          showsVerticalScrollIndicator={false}
          refreshControl={
            <RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor={theme.accent} colors={[theme.accent]} />
          }
        />
      )}
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1 },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 8,
    paddingVertical: 12,
    borderBottomWidth: StyleSheet.hairlineWidth,
  },
  headerBack: { width: 40, height: 32, alignItems: 'center', justifyContent: 'center' },
  headerTitle: { fontSize: 18, fontWeight: '700' },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 32 },
  emptyTitle: { fontSize: 17, fontWeight: '700', marginTop: 14 },
  emptySub: { fontSize: 14, textAlign: 'center', marginTop: 8, lineHeight: 20 },
  cta: { borderRadius: 14, paddingVertical: 14, paddingHorizontal: 28, marginTop: 20 },
  ctaText: { color: '#FFFFFF', fontSize: 15, fontWeight: '700' },
  card: {
    borderWidth: 1,
    borderRadius: 14,
    padding: 14,
    marginBottom: 12,
  },
  cardTop: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  chip: { borderRadius: 8, paddingHorizontal: 10, paddingVertical: 4 },
  chipText: { fontSize: 12, fontWeight: '600' },
  statusPill: { flexDirection: 'row', alignItems: 'center', borderRadius: 20, paddingHorizontal: 10, paddingVertical: 5 },
  statusText: { fontSize: 12.5, fontWeight: '700' },
  stars: { flexDirection: 'row', marginTop: 12 },
  message: { fontSize: 15, lineHeight: 21, marginTop: 10 },
  date: { fontSize: 12.5, marginTop: 10 },
});
