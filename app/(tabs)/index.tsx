import { Ionicons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import { useFocusEffect, useRouter } from 'expo-router';
import React, { useCallback, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Dimensions,
  FlatList,
  Image,
  RefreshControl,
  StatusBar,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import FavoriteHeart from '../../components/FavoriteHeart';
import { EmptyState, RatingPill, SectionHeader } from '../../components/ui';
import { isHiddenCategorySlug, isHiddenListing } from '../../lib/catalog';
import { getSession } from '../../lib/authStore';
import { getSeenPlacesTotal, newPlacesCount, setSeenPlacesTotal } from '../../lib/newListings';
import { usePreferences } from '../../lib/preferences';
import { useTheme } from '../../lib/theme';
import { ApiListing, getHome, HomePayload, resolveImage } from '../../services/api';

const { width } = Dimensions.get('window');
const CARD_WIDTH = (width - 44) / 2;
const HERO_WIDTH = width * 0.78;

// Map backend category slugs to Ionicons + soft tints.
const CATEGORY_STYLE: Record<string, { icon: string; bg: string; color: string }> = {
  'things-to-do': { icon: 'compass', bg: '#FFF2E6', color: '#FF8C00' },
  'tour-guides': { icon: 'people', bg: '#E6F0FA', color: '#3B82F6' },
  hotels: { icon: 'bed', bg: '#FFF9E6', color: '#F59E0B' },
};

function categoryStyle(slug: string) {
  return CATEGORY_STYLE[slug] ?? { icon: 'apps', bg: '#FFEBEA', color: '#EF4444' };
}

const SERVICES: { key: string; label: string; icon: string; color: string; bg: string }[] = [
  { key: 'attractions', label: 'Attractions', icon: 'ticket', color: '#EC4899', bg: '#FCE7F3' },
  { key: 'car', label: 'Car Rentals', icon: 'car-sport', color: '#3B82F6', bg: '#E0EDFF' },
  { key: 'flights', label: 'Flights', icon: 'airplane', color: '#8B5CF6', bg: '#EDE9FE' },
  { key: 'weather', label: 'Weather', icon: 'partly-sunny', color: '#0EA5E9', bg: '#E0F2FE' },
  { key: 'traffic', label: 'Traffic', icon: 'car', color: '#F97316', bg: '#FFEDD5' },
];

export default function HomeScreen() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { t, formatPrice } = usePreferences();
  const { colors, isDark, radius, shadow, gradients, brand } = useTheme();

  const [data, setData] = useState<HomePayload | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState('');
  const [firstName, setFirstName] = useState('');
  // How many newly published places have appeared since the user last looked.
  const [newCount, setNewCount] = useState(0);

  const load = useCallback(async () => {
    setError('');
    try {
      const [home, session] = await Promise.all([getHome(), getSession()]);
      setData(home);
      setFirstName(session?.fullName?.trim().split(' ')[0] ?? '');

      // Detect new tours published by guides (after admin approval).
      const total = home.listings_total ?? 0;
      const seen = await getSeenPlacesTotal();
      if (seen < 0) {
        // First run on this device — set the baseline, don't nag about the
        // whole existing catalog.
        await setSeenPlacesTotal(total);
        setNewCount(0);
      } else {
        setNewCount(newPlacesCount(total, seen));
      }
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load.');
    } finally {
      setLoading(false);
    }
  }, []);

  // Remember the current catalog size as "seen" and hide the banner.
  const markPlacesSeen = useCallback(async () => {
    await setSeenPlacesTotal(data?.listings_total ?? 0);
    setNewCount(0);
  }, [data]);

  const openNewPlaces = useCallback(async () => {
    await markPlacesSeen();
    router.push('/things-to-do');
  }, [markPlacesSeen, router]);

  useFocusEffect(
    useCallback(() => {
      load();
    }, [load])
  );

  const onRefresh = useCallback(async () => {
    setRefreshing(true);
    await load();
    setRefreshing(false);
  }, [load]);

  const openListing = (slug: string) => router.push({ pathname: '/listing/[slug]', params: { slug } });
  const openCategory = (slug: string) =>
    // "Tour Guides" lists guide people, not listings — send it to the directory.
    slug === 'tour-guides'
      ? router.push('/guides')
      : router.push({ pathname: '/things-to-do', params: { category: slug } });
  const openAttractions = () => router.push({ pathname: '/things-to-do', params: { featured: '1' } });
  const openExplore = () => router.push('/things-to-do');
  const openSearch = () => router.push('/search');

  type GridItem = { key: string; label: string; icon: string; bg: string; color: string; onPress: () => void };

  const gridItems: GridItem[] = [
    ...(data?.categories ?? [])
      .filter((category) => !isHiddenCategorySlug(category.slug))
      .map((category) => {
      const s = categoryStyle(category.slug);
      return {
        key: `c-${category.id}`,
        label: category.name,
        icon: s.icon,
        bg: s.bg,
        color: s.color,
        onPress: () => openCategory(category.slug),
      };
    }),
    ...SERVICES.map((s) => ({
      key: `s-${s.key}`,
      label: s.label,
      icon: s.icon,
      bg: s.bg,
      color: s.color,
      // Every shortcut now navigates somewhere useful (no dead buttons).
      onPress: () => {
        if (s.key === 'attractions') return openAttractions();
        if (s.key === 'car') return router.push('/car-rentals');
        if (s.key === 'weather') return router.push('/weather');
        if (s.key === 'traffic') return router.push('/traffic');
        if (s.key === 'flights') {
          return Alert.alert('Coming Soon', 'Stay tuned! Flight bookings are on the way. ✈️');
        }
        return openExplore();
      },
    })),
  ];

  const featured = (data?.featured?.length ? data.featured : data?.recommended ?? []).filter((item) => !isHiddenListing(item));
  const listings = (data?.recommended?.length ? data.recommended : data?.featured ?? []).filter((item) => !isHiddenListing(item));

  const renderHero = ({ item }: { item: ApiListing }) => (
    <TouchableOpacity
      style={[styles.heroCard, { width: HERO_WIDTH }, shadow.md]}
      activeOpacity={0.9}
      onPress={() => openListing(item.slug)}
    >
      <Image source={{ uri: resolveImage(item.image) }} style={styles.heroImage} />
      <LinearGradient colors={gradients.hero} style={StyleSheet.absoluteFill as any} />
      <View style={styles.heroTopRow}>
        <View style={styles.featuredTag}>
          <Ionicons name="sparkles" size={11} color="#FFFFFF" />
          <Text style={styles.featuredTagText}>Featured</Text>
        </View>
        <View style={styles.heroTopRight}>
          <RatingPill rating={item.rating} count={item.review_count} />
          <FavoriteHeart listingId={item.id} favorited={item.favorited} size={34} />
        </View>
      </View>
      <View style={styles.heroBottom}>
        {item.area ? (
          <View style={styles.heroAreaRow}>
            <Ionicons name="location-sharp" size={12} color="#FFFFFF" />
            <Text style={styles.heroArea} numberOfLines={1}>{item.area}</Text>
          </View>
        ) : null}
        <Text style={styles.heroTitle} numberOfLines={2}>{item.title}</Text>
        <Text style={styles.heroPrice}>{item.price > 0 ? formatPrice(item.price) : t('free')}</Text>
      </View>
    </TouchableOpacity>
  );

  const renderGridItem = (item: GridItem) => (
    <TouchableOpacity key={item.key} style={styles.gridItem} activeOpacity={0.7} onPress={item.onPress}>
      <View style={[styles.gridIconBg, { backgroundColor: isDark ? colors.cardAlt : item.bg }]}>
        <Ionicons name={item.icon as any} size={24} color={item.color} />
      </View>
      <Text style={[styles.gridLabel, { color: colors.textSub }]} numberOfLines={1}>{item.label}</Text>
    </TouchableOpacity>
  );

  const renderPlaceCard = ({ item }: { item: ApiListing }) => (
    <TouchableOpacity
      style={[styles.placeCard, { backgroundColor: colors.card, borderColor: colors.border }, shadow.sm]}
      onPress={() => openListing(item.slug)}
      activeOpacity={0.88}
    >
      <View style={styles.placeImageWrap}>
        <Image source={{ uri: resolveImage(item.image) }} style={styles.placeImage} />
        {item.is_new ? (
          <View style={styles.newBadge}>
            <Ionicons name="sparkles" size={9} color="#FFFFFF" />
            <Text style={styles.newBadgeText}>NEW</Text>
          </View>
        ) : null}
        {item.rating > 0 ? (
          <View style={styles.placeRating}>
            <RatingPill rating={item.rating} count={item.review_count} compact />
          </View>
        ) : null}
        <FavoriteHeart listingId={item.id} favorited={item.favorited} size={32} style={styles.placeHeart} />
      </View>
      <View style={styles.placeBody}>
        {item.area ? (
          <View style={styles.placeAreaRow}>
            <Ionicons name="location-sharp" size={11} color={colors.primary} />
            <Text style={[styles.placeArea, { color: colors.textSub }]} numberOfLines={1}>{item.area}</Text>
          </View>
        ) : null}
        <Text style={[styles.placeName, { color: colors.text }]} numberOfLines={2}>{item.title}</Text>
        <Text style={[styles.placePrice, { color: colors.accent }]}>
          {item.price > 0 ? formatPrice(item.price) : t('free')}
          {item.price > 0 && item.price_unit ? (
            <Text style={[styles.placeUnit, { color: colors.textMute }]}> / {item.price_unit}</Text>
          ) : null}
        </Text>
      </View>
    </TouchableOpacity>
  );

  const ListHeader = (
    <HomeHeader
      colors={colors}
      shadow={shadow}
      t={t}
      firstName={firstName}
      openSearch={openSearch}
      featured={featured}
      renderHero={renderHero}
      openExplore={openExplore}
      openAccount={() => router.push('/(tabs)/account')}
      openMessages={() => router.push('/messages')}
      gridItems={gridItems}
      renderGridItem={renderGridItem}
      newCount={newCount}
      onOpenNewPlaces={openNewPlaces}
      onDismissNewPlaces={markPlacesSeen}
    />
  );

  if (loading) {
    return (
      <View style={[styles.center, { backgroundColor: colors.bg }]}>
        <ActivityIndicator size="large" color={colors.primary} />
      </View>
    );
  }

  return (
    <View style={{ flex: 1, backgroundColor: colors.bg }}>
      <StatusBar barStyle={isDark ? 'light-content' : 'dark-content'} />
      <FlatList
        style={{ flex: 1, paddingTop: insets.top + 6 }}
        data={listings}
        renderItem={renderPlaceCard}
        keyExtractor={(item) => String(item.id)}
        ListHeaderComponent={ListHeader}
        numColumns={2}
        columnWrapperStyle={styles.cardRowWrapper}
        showsVerticalScrollIndicator={false}
        contentContainerStyle={styles.scrollContent}
        keyboardShouldPersistTaps="handled"
        keyboardDismissMode="none"
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor={colors.primary} colors={[brand.primary]} />
        }
        ListEmptyComponent={
          <EmptyState
            icon={error ? 'cloud-offline-outline' : 'compass-outline'}
            title={error ? 'Something went wrong' : 'No listings yet'}
            subtitle={error || 'Add listings from the web admin to see them here.'}
            actionLabel={error ? 'Retry' : undefined}
            onAction={load}
          />
        }
      />
    </View>
  );
}

type HomeHeaderProps = {
  colors: ReturnType<typeof useTheme>['colors'];
  shadow: ReturnType<typeof useTheme>['shadow'];
  t: ReturnType<typeof usePreferences>['t'];
  firstName: string;
  openSearch: () => void;
  featured: ApiListing[];
  renderHero: ({ item }: { item: ApiListing }) => React.ReactElement;
  openExplore: () => void;
  openAccount: () => void;
  openMessages: () => void;
  gridItems: { key: string; label: string; icon: string; bg: string; color: string; onPress: () => void }[];
  renderGridItem: (item: { key: string; label: string; icon: string; bg: string; color: string; onPress: () => void }) => React.ReactElement;
  newCount: number;
  onOpenNewPlaces: () => void;
  onDismissNewPlaces: () => void;
};

const HERO_SNAP = HERO_WIDTH + 14;
const AUTO_SCROLL_MS = 3500;

// Auto-playing featured carousel: advances on a timer and loops back to the
// first card after the last one. Pauses while the user is swiping, then resumes.
function FeaturedCarousel({
  data,
  renderHero,
}: {
  data: ApiListing[];
  renderHero: ({ item }: { item: ApiListing }) => React.ReactElement;
}) {
  const listRef = React.useRef<FlatList<ApiListing>>(null);
  const indexRef = React.useRef(0);
  const timerRef = React.useRef<ReturnType<typeof setInterval> | null>(null);

  const stop = React.useCallback(() => {
    if (timerRef.current) {
      clearInterval(timerRef.current);
      timerRef.current = null;
    }
  }, []);

  const start = React.useCallback(() => {
    if (timerRef.current || data.length <= 1) return;
    timerRef.current = setInterval(() => {
      const next = indexRef.current + 1 >= data.length ? 0 : indexRef.current + 1;
      indexRef.current = next;
      listRef.current?.scrollToOffset({ offset: next * HERO_SNAP, animated: true });
    }, AUTO_SCROLL_MS);
  }, [data.length]);

  React.useEffect(() => {
    start();
    return stop;
  }, [start, stop]);

  return (
    <FlatList
      ref={listRef}
      data={data}
      keyExtractor={(it) => `hero-${it.id}`}
      renderItem={renderHero}
      horizontal
      showsHorizontalScrollIndicator={false}
      snapToInterval={HERO_SNAP}
      decelerationRate="fast"
      contentContainerStyle={{ paddingRight: 16 }}
      ItemSeparatorComponent={() => <View style={{ width: 14 }} />}
      onScrollBeginDrag={stop}
      onMomentumScrollEnd={(e) => {
        indexRef.current = Math.round(e.nativeEvent.contentOffset.x / HERO_SNAP);
        start();
      }}
    />
  );
}

function HomeHeader({
  colors,
  shadow,
  t,
  firstName,
  openSearch,
  featured,
  renderHero,
  openExplore,
  openAccount,
  openMessages,
  gridItems,
  renderGridItem,
  newCount,
  onOpenNewPlaces,
  onDismissNewPlaces,
}: HomeHeaderProps) {
  return (
    <View style={styles.headerContainer}>
      <View style={styles.greetRow}>
        <View style={{ flex: 1 }}>
          <Text style={[styles.greetHello, { color: colors.textSub }]}>
            {firstName ? `Hello, ${firstName} 👋` : 'Hello there 👋'}
          </Text>
          <View style={styles.locRow}>
            <Ionicons name="location" size={15} color={colors.primary} />
            <Text style={[styles.locText, { color: colors.text }]}>Cebu, Philippines</Text>
          </View>
        </View>
        <View style={styles.headerActions}>
          <TouchableOpacity
            style={[styles.avatarBtn, { backgroundColor: colors.chipBg }]}
            activeOpacity={0.8}
            onPress={openMessages}
            accessibilityRole="button"
            accessibilityLabel="Messages"
          >
            <Ionicons name="chatbubble-ellipses" size={20} color={colors.primary} />
          </TouchableOpacity>
          <TouchableOpacity
            style={[styles.avatarBtn, { backgroundColor: colors.chipBg }]}
            activeOpacity={0.8}
            onPress={openAccount}
            accessibilityRole="button"
            accessibilityLabel="Account"
          >
            <Ionicons name="person" size={20} color={colors.primary} />
          </TouchableOpacity>
        </View>
      </View>

      <TouchableOpacity
        style={[styles.searchBar, { backgroundColor: colors.card, borderColor: colors.border }, shadow.sm]}
        activeOpacity={0.8}
        onPress={openSearch}
      >
        <Ionicons name="search" size={19} color={colors.textMute} />
        <Text style={[styles.searchInput, { color: colors.textMute }]} numberOfLines={1}>{t('search_ph')}</Text>
        <View style={[styles.searchGo, { backgroundColor: colors.primary }]}>
          <Ionicons name="arrow-forward" size={16} color="#FFFFFF" />
        </View>
      </TouchableOpacity>

      {newCount > 0 ? (
        <TouchableOpacity
          style={[styles.newBanner, { backgroundColor: colors.primary }, shadow.sm]}
          activeOpacity={0.9}
          onPress={onOpenNewPlaces}
        >
          <View style={styles.newBannerIcon}>
            <Ionicons name="sparkles" size={18} color="#FFFFFF" />
          </View>
          <View style={{ flex: 1 }}>
            <Text style={styles.newBannerTitle} numberOfLines={1}>
              {newCount} new {newCount > 1 ? 'places' : 'place'} to explore!
            </Text>
            <Text style={styles.newBannerSub} numberOfLines={1}>
              Guides just added new tours — tap to see them.
            </Text>
          </View>
          <TouchableOpacity
            onPress={onDismissNewPlaces}
            hitSlop={{ top: 10, bottom: 10, left: 10, right: 10 }}
            style={styles.newBannerClose}
          >
            <Ionicons name="close" size={18} color="rgba(255,255,255,0.9)" />
          </TouchableOpacity>
        </TouchableOpacity>
      ) : null}

      {featured.length > 0 ? (
        <View style={styles.featuredSection}>
          <SectionHeader title="Featured experiences" actionLabel={t('see_all')} onAction={openExplore} />
          <FeaturedCarousel data={featured.slice(0, 8)} renderHero={renderHero} />
        </View>
      ) : null}

      <Text style={[styles.browseTitle, { color: colors.text }]}>Browse by category</Text>
      <View style={styles.categoriesGrid}>{gridItems.map(renderGridItem)}</View>

      <SectionHeader title={t('recommended')} actionLabel={t('see_all')} onAction={openExplore} />
    </View>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  scrollContent: { paddingBottom: 36 },
  headerContainer: { paddingHorizontal: 16 },

  greetRow: { flexDirection: 'row', alignItems: 'center', marginBottom: 18 },
  greetHello: { fontSize: 14, fontWeight: '600' },
  locRow: { flexDirection: 'row', alignItems: 'center', marginTop: 3 },
  locText: { fontSize: 19, fontWeight: '800', marginLeft: 4, letterSpacing: -0.4 },
  avatarBtn: { width: 46, height: 46, borderRadius: 23, alignItems: 'center', justifyContent: 'center' },
  headerActions: { flexDirection: 'row', alignItems: 'center', gap: 10 },

  searchBar: {
    flexDirection: 'row',
    alignItems: 'center',
    borderRadius: 16,
    borderWidth: 1,
    paddingLeft: 14,
    paddingRight: 6,
    height: 52,
    marginBottom: 24,
  },
  searchInput: { flex: 1, fontSize: 15, padding: 0, marginLeft: 8 },
  searchGo: { width: 40, height: 40, borderRadius: 12, alignItems: 'center', justifyContent: 'center' },

  newBanner: {
    flexDirection: 'row',
    alignItems: 'center',
    borderRadius: 16,
    padding: 12,
    marginBottom: 24,
    gap: 10,
  },
  newBannerIcon: {
    width: 38, height: 38, borderRadius: 12,
    backgroundColor: 'rgba(255,255,255,0.22)',
    alignItems: 'center', justifyContent: 'center',
  },
  newBannerTitle: { color: '#FFFFFF', fontSize: 14, fontWeight: '800', letterSpacing: -0.2 },
  newBannerSub: { color: 'rgba(255,255,255,0.9)', fontSize: 12, fontWeight: '600', marginTop: 1 },
  newBannerClose: { padding: 2 },

  featuredSection: { marginBottom: 24 },
  heroCard: { height: 200, borderRadius: 22, overflow: 'hidden', backgroundColor: '#00000011' },
  heroImage: { width: '100%', height: '100%', resizeMode: 'cover' },
  heroTopRow: {
    position: 'absolute',
    top: 12,
    left: 12,
    right: 12,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  heroTopRight: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  featuredTag: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    backgroundColor: '#FF6A3D',
    paddingHorizontal: 9,
    paddingVertical: 4,
    borderRadius: 999,
  },
  featuredTagText: { color: '#FFFFFF', fontSize: 11, fontWeight: '800' },
  heroBottom: { position: 'absolute', left: 14, right: 14, bottom: 14 },
  heroAreaRow: { flexDirection: 'row', alignItems: 'center', marginBottom: 4 },
  heroArea: { color: 'rgba(255,255,255,0.9)', fontSize: 12, fontWeight: '600', marginLeft: 3 },
  heroTitle: { color: '#FFFFFF', fontSize: 18, fontWeight: '800', letterSpacing: -0.3 },
  heroPrice: { color: '#FFFFFF', fontSize: 15, fontWeight: '800', marginTop: 4 },

  browseTitle: { fontSize: 18, fontWeight: '800', letterSpacing: -0.4, marginBottom: 14 },
  categoriesGrid: { flexDirection: 'row', flexWrap: 'wrap', marginBottom: 8 },
  gridItem: { width: '25%', alignItems: 'center', marginBottom: 18 },
  gridIconBg: { width: 58, height: 58, borderRadius: 18, justifyContent: 'center', alignItems: 'center', marginBottom: 8 },
  gridLabel: { fontSize: 11, fontWeight: '600', textAlign: 'center' },

  cardRowWrapper: { justifyContent: 'space-between', paddingHorizontal: 16, marginBottom: 14 },
  placeCard: { width: CARD_WIDTH, borderRadius: 18, overflow: 'hidden', borderWidth: 1 },
  placeImageWrap: { width: '100%', height: CARD_WIDTH * 0.82 },
  placeImage: { width: '100%', height: '100%', resizeMode: 'cover', backgroundColor: '#00000011' },
  placeRating: { position: 'absolute', top: 8, left: 8 },
  placeHeart: { position: 'absolute', top: 8, right: 8 },
  newBadge: {
    position: 'absolute', top: 8, left: 8, zIndex: 2,
    flexDirection: 'row', alignItems: 'center', gap: 3,
    backgroundColor: '#22C55E',
    paddingHorizontal: 7, paddingVertical: 3, borderRadius: 999,
  },
  newBadgeText: { color: '#FFFFFF', fontSize: 9, fontWeight: '900', letterSpacing: 0.5 },
  placeBody: { padding: 10 },
  placeAreaRow: { flexDirection: 'row', alignItems: 'center', marginBottom: 3 },
  placeArea: { fontSize: 11, fontWeight: '600', marginLeft: 3, flex: 1 },
  placeName: { fontSize: 14, fontWeight: '800', letterSpacing: -0.2, lineHeight: 18 },
  placePrice: { fontSize: 14, fontWeight: '800', marginTop: 6 },
  placeUnit: { fontSize: 11, fontWeight: '600' },
});
