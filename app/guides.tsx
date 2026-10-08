import { Ionicons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import React, { useCallback, useEffect, useState } from 'react';
import { ActivityIndicator, FlatList, Image, StatusBar, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { EmptyState } from '../components/ui';
import { useTheme } from '../lib/theme';
import { ApiChatPartner, getGuides, resolveImage } from '../services/api';

export default function GuidesScreen() {
  const router = useRouter();
  const { colors, isDark, shadow } = useTheme();

  const [guides, setGuides] = useState<ApiChatPartner[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const data = await getGuides();
      setGuides(data.guides);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load tour guides.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  const initials = (name: string) =>
    name
      .split(' ')
      .map((w) => w.charAt(0))
      .slice(0, 2)
      .join('')
      .toUpperCase();

  const renderItem = ({ item }: { item: ApiChatPartner }) => (
    <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }, shadow.sm]}>
      <View style={styles.avatarWrap}>
        {item.avatar ? (
          <Image source={{ uri: resolveImage(item.avatar) }} style={styles.avatar} />
        ) : (
          <View style={[styles.avatar, styles.avatarFallback, { backgroundColor: colors.chipBg }]}>
            <Text style={[styles.avatarInitials, { color: colors.primary }]}>{initials(item.name)}</Text>
          </View>
        )}
        {item.online ? <View style={[styles.onlineDot, { borderColor: colors.card }]} /> : null}
      </View>

      <View style={styles.info}>
        <View style={styles.nameRow}>
          <Text style={[styles.name, { color: colors.text }]} numberOfLines={1}>{item.name}</Text>
        </View>
        <View style={[styles.availPill, item.available === false ? styles.availPillBusy : styles.availPillFree]}>
          <View style={[styles.availDot, { backgroundColor: item.available === false ? '#EF4444' : '#22C55E' }]} />
          <Text style={[styles.availText, { color: item.available === false ? '#B91C1C' : '#15803D' }]}>
            {item.available === false ? 'On a tour' : 'Available'}
          </Text>
        </View>
        <View style={styles.metaRow}>
          <Ionicons name="star" size={13} color="#F6B100" />
          <Text style={[styles.metaText, { color: colors.textSub }]}>
            {item.rating && item.rating > 0 ? `${item.rating.toFixed(1)}` : 'New'}
            {item.review_count ? ` (${item.review_count})` : ''}
          </Text>
          <Text style={[styles.dot, { color: colors.textMute }]}>·</Text>
          <Ionicons name="checkmark-done" size={13} color={colors.primary} />
          <Text style={[styles.metaText, { color: colors.textSub }]}>{item.completed_tours ?? 0} tours</Text>
        </View>
        {item.bio ? (
          <Text style={[styles.bio, { color: colors.textSub }]} numberOfLines={2}>{item.bio}</Text>
        ) : null}
      </View>

      <TouchableOpacity
        style={[styles.msgBtn, { backgroundColor: colors.primary }]}
        activeOpacity={0.85}
        onPress={() => router.push(`/chat/${item.id}`)}
      >
        <Ionicons name="chatbubble-ellipses" size={16} color="#FFFFFF" />
        <Text style={styles.msgBtnText}>Message</Text>
      </TouchableOpacity>
    </View>
  );

  return (
    <SafeAreaView style={[styles.container, { backgroundColor: colors.bgAlt }]} edges={['top', 'left', 'right']}>
      <StatusBar barStyle={isDark ? 'light-content' : 'dark-content'} />

      <View style={styles.header}>
        <TouchableOpacity
          style={[styles.backButton, { backgroundColor: colors.card, borderColor: colors.border }]}
          onPress={() => router.back()}
          activeOpacity={0.7}
        >
          <Ionicons name="arrow-back" size={22} color={colors.text} />
        </TouchableOpacity>
        <Text style={[styles.headerTitle, { color: colors.text }]} numberOfLines={1}>Tour Guides</Text>
        <View style={styles.backButton} />
      </View>

      {loading ? (
        <View style={styles.center}>
          <ActivityIndicator size="large" color={colors.primary} />
        </View>
      ) : (
        <FlatList
          data={guides}
          renderItem={renderItem}
          keyExtractor={(item) => String(item.id)}
          showsVerticalScrollIndicator={false}
          contentContainerStyle={styles.listContent}
          ListHeaderComponent={
            guides.length > 0 ? (
              <Text style={[styles.subtitle, { color: colors.textSub }]}>
                Verified local guides in Cebu. Tap Message to plan your tour.
              </Text>
            ) : null
          }
          ListEmptyComponent={
            <EmptyState
              icon={error ? 'cloud-offline-outline' : 'people-outline'}
              title={error ? 'Something went wrong' : 'No tour guides yet'}
              subtitle={
                error ||
                'Guides appear here once an admin approves their verification. Ask your guide to finish verification in the web portal.'
              }
              actionLabel={error ? 'Retry' : undefined}
              onAction={load}
            />
          }
        />
      )}
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1 },
  center: { flexGrow: 1, alignItems: 'center', justifyContent: 'center', paddingTop: 40 },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 16,
    paddingTop: 8,
    paddingBottom: 10,
  },
  backButton: { width: 42, height: 42, borderRadius: 21, alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: 'transparent' },
  headerTitle: { flex: 1, fontSize: 18, fontWeight: '800', textAlign: 'center', marginHorizontal: 8 },
  listContent: { paddingHorizontal: 16, paddingBottom: 28, flexGrow: 1 },
  subtitle: { fontSize: 13, lineHeight: 19, marginBottom: 14 },
  card: { flexDirection: 'row', alignItems: 'center', borderRadius: 18, borderWidth: 1, padding: 12, marginBottom: 12 },
  avatarWrap: { width: 60, height: 60 },
  avatar: { width: 60, height: 60, borderRadius: 30, backgroundColor: '#00000011' },
  avatarFallback: { alignItems: 'center', justifyContent: 'center' },
  avatarInitials: { fontSize: 20, fontWeight: '800' },
  onlineDot: {
    position: 'absolute',
    right: 1,
    bottom: 1,
    width: 14,
    height: 14,
    borderRadius: 7,
    backgroundColor: '#22C55E',
    borderWidth: 2,
  },
  info: { flex: 1, paddingHorizontal: 12 },
  nameRow: { flexDirection: 'row', alignItems: 'center' },
  name: { fontSize: 16, fontWeight: '800', letterSpacing: -0.2, flexShrink: 1 },
  availPill: { flexDirection: 'row', alignItems: 'center', alignSelf: 'flex-start', gap: 5, paddingVertical: 2, paddingHorizontal: 8, borderRadius: 999, marginTop: 4 },
  availPillFree: { backgroundColor: '#DCFCE7' },
  availPillBusy: { backgroundColor: '#FEE2E2' },
  availDot: { width: 7, height: 7, borderRadius: 4 },
  availText: { fontSize: 11, fontWeight: '800' },
  metaRow: { flexDirection: 'row', alignItems: 'center', marginTop: 3, gap: 3 },
  metaText: { fontSize: 12, fontWeight: '600' },
  dot: { fontSize: 12, marginHorizontal: 3 },
  bio: { fontSize: 12, lineHeight: 17, marginTop: 4 },
  msgBtn: { flexDirection: 'row', alignItems: 'center', gap: 5, paddingVertical: 9, paddingHorizontal: 12, borderRadius: 999 },
  msgBtnText: { color: '#FFFFFF', fontSize: 13, fontWeight: '800' },
});
