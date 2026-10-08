import { Ionicons } from '@expo/vector-icons';
import { useLocalSearchParams, useRouter } from 'expo-router';
import React, { useCallback, useEffect, useState } from 'react';
import { ActivityIndicator, FlatList, Image, RefreshControl, StatusBar, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import FavoriteHeart from '../components/FavoriteHeart';
import { EmptyState, RatingPill } from '../components/ui';
import { isHiddenCategorySlug, isHiddenListing } from '../lib/catalog';
import { usePreferences } from '../lib/preferences';
import { useTheme } from '../lib/theme';
import { ApiListing, getListings, resolveImage } from '../services/api';

export default function ListingsScreen() {
  const router = useRouter();
  const params = useLocalSearchParams<{ category?: string; q?: string; featured?: string }>();
  const categoryParam = params.category && !isHiddenCategorySlug(String(params.category))
    ? String(params.category)
    : undefined;
  const { t, formatPrice } = usePreferences();
  const { colors, isDark, shadow } = useTheme();

  const [listings, setListings] = useState<ApiListing[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState('');

  const title = params.featured
    ? 'Attractions'
    : params.q
      ? `Results for "${params.q}"`
      : categoryParam
        ? categoryParam.split('-').map((w) => w.charAt(0).toUpperCase() + w.slice(1)).join(' ')
        : 'Explore Cebu';

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const query: Record<string, string> = {};
      if (categoryParam) query.category = categoryParam;
      if (params.q) query.q = String(params.q);
      if (params.featured) query.featured = String(params.featured);
      const data = await getListings(query);
      setListings(data.listings.filter((item) => !isHiddenListing(item)));
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load listings.');
    } finally {
      setLoading(false);
    }
  }, [categoryParam, params.q, params.featured]);

  useEffect(() => {
    load();
  }, [load]);

  const onRefresh = useCallback(async () => {
    setRefreshing(true);
    await load();
    setRefreshing(false);
  }, [load]);

  const renderItem = ({ item }: { item: ApiListing }) => (
    <TouchableOpacity
      style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }, shadow.sm]}
      onPress={() => router.push({ pathname: '/listing/[slug]', params: { slug: item.slug } })}
      activeOpacity={0.9}
    >
      <View style={styles.imageWrap}>
        <Image source={{ uri: resolveImage(item.image) }} style={styles.cardImage} />
        {item.rating > 0 ? (
          <View style={styles.ratingPos}>
            <RatingPill rating={item.rating} count={item.review_count} />
          </View>
        ) : null}
        {item.area ? (
          <View style={styles.areaChip}>
            <Ionicons name="location-sharp" size={11} color="#FFFFFF" />
            <Text style={styles.areaChipText}>{item.area}</Text>
          </View>
        ) : null}
        {item.is_new ? (
          <View style={styles.newBadge}>
            <Ionicons name="sparkles" size={10} color="#FFFFFF" />
            <Text style={styles.newBadgeText}>NEW</Text>
          </View>
        ) : null}
        <FavoriteHeart listingId={item.id} favorited={item.favorited} style={styles.heart} />
      </View>
      <View style={styles.cardBody}>
        <Text style={[styles.cardTitle, { color: colors.text }]} numberOfLines={1}>{item.title}</Text>
        {item.summary ? (
          <Text style={[styles.cardDesc, { color: colors.textSub }]} numberOfLines={2}>{item.summary}</Text>
        ) : null}
        <View style={styles.cardFooter}>
          <Text style={[styles.cardPrice, { color: colors.accent }]}>
            {item.price > 0 ? formatPrice(item.price) : t('free')}
            <Text style={[styles.cardPriceUnit, { color: colors.textMute }]}>
              {item.price > 0 ? ` / ${item.price_unit || 'person'}` : ''}
            </Text>
          </Text>
          <View style={[styles.viewBtn, { backgroundColor: colors.primary }]}>
            <Text style={styles.viewBtnText}>View</Text>
            <Ionicons name="arrow-forward" size={13} color="#FFFFFF" />
          </View>
        </View>
      </View>
    </TouchableOpacity>
  );

  return (
    <SafeAreaView style={[styles.container, { backgroundColor: colors.bgAlt }]} edges={['top', 'left', 'right']}>
      <StatusBar barStyle={isDark ? 'light-content' : 'dark-content'} />

      <View style={styles.header}>
        <TouchableOpacity style={[styles.backButton, { backgroundColor: colors.card, borderColor: colors.border }]} onPress={() => router.back()} activeOpacity={0.7}>
          <Ionicons name="arrow-back" size={22} color={colors.text} />
        </TouchableOpacity>
        <Text style={[styles.headerTitle, { color: colors.text }]} numberOfLines={1}>{title}</Text>
        <View style={styles.backButton} />
      </View>

      {loading ? (
        <View style={styles.center}>
          <ActivityIndicator size="large" color={colors.primary} />
        </View>
      ) : (
        <FlatList
          data={listings}
          renderItem={renderItem}
          keyExtractor={(item) => String(item.id)}
          showsVerticalScrollIndicator={false}
          contentContainerStyle={styles.listContent}
          refreshControl={
            <RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor={colors.primary} colors={[colors.primary]} />
          }
          ListEmptyComponent={
            <EmptyState
              icon={error ? 'cloud-offline-outline' : 'search-outline'}
              title={error ? 'Something went wrong' : 'Nothing here yet'}
              subtitle={error || 'Listings will appear once added & approved in the web admin.'}
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
  center: { flexGrow: 1, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 30, paddingTop: 40 },
  header: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: 16, paddingTop: 8, paddingBottom: 10 },
  backButton: { width: 42, height: 42, borderRadius: 21, alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: 'transparent' },
  headerTitle: { flex: 1, fontSize: 18, fontWeight: '800', textAlign: 'center', marginHorizontal: 8 },
  listContent: { paddingHorizontal: 16, paddingBottom: 28, flexGrow: 1 },
  card: { borderRadius: 20, overflow: 'hidden', marginBottom: 16, borderWidth: 1 },
  imageWrap: { width: '100%', height: 180 },
  cardImage: { width: '100%', height: '100%', resizeMode: 'cover', backgroundColor: '#00000011' },
  ratingPos: { position: 'absolute', top: 10, right: 10 },
  heart: { position: 'absolute', top: 10, left: 10 },
  areaChip: {
    position: 'absolute',
    bottom: 10,
    left: 10,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 3,
    backgroundColor: 'rgba(0,0,0,0.5)',
    paddingVertical: 4,
    paddingHorizontal: 9,
    borderRadius: 999,
  },
  areaChipText: { color: '#FFFFFF', fontSize: 11, fontWeight: '700' },
  newBadge: {
    position: 'absolute', bottom: 10, right: 10,
    flexDirection: 'row', alignItems: 'center', gap: 3,
    backgroundColor: '#22C55E',
    paddingHorizontal: 8, paddingVertical: 4, borderRadius: 999,
  },
  newBadgeText: { color: '#FFFFFF', fontSize: 10, fontWeight: '900', letterSpacing: 0.5 },
  cardBody: { padding: 14 },
  cardTitle: { fontSize: 17, fontWeight: '800', marginBottom: 5, letterSpacing: -0.3 },
  cardDesc: { fontSize: 13, lineHeight: 19, marginBottom: 10 },
  cardFooter: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  cardPrice: { fontSize: 16, fontWeight: '900' },
  cardPriceUnit: { fontSize: 12, fontWeight: '500' },
  viewBtn: { flexDirection: 'row', alignItems: 'center', gap: 4, paddingVertical: 8, paddingHorizontal: 14, borderRadius: 999 },
  viewBtnText: { color: '#FFFFFF', fontSize: 13, fontWeight: '800' },
});
