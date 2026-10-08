import { Ionicons } from '@expo/vector-icons';
import { useFocusEffect, useRouter } from 'expo-router';
import React, { useCallback, useState } from 'react';
import { ActivityIndicator, FlatList, Image, StatusBar, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import FavoriteHeart from '../../components/FavoriteHeart';
import { EmptyState, RatingPill, ScreenTitle } from '../../components/ui';
import { clearSession, restoreSession } from '../../lib/authStore';
import { usePreferences } from '../../lib/preferences';
import { useTheme } from '../../lib/theme';
import { ApiListing, getFavorites, isUnauthorized, resolveImage } from '../../services/api';

export default function WishlistScreen() {
  const router = useRouter();
  const { t, formatPrice } = usePreferences();
  const insets = useSafeAreaInsets();
  const { colors, isDark, shadow } = useTheme();

  const [listings, setListings] = useState<ApiListing[]>([]);
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
      const data = await getFavorites();
      setListings(data.listings);
    } catch (e) {
      if (isUnauthorized(e)) {
        // Token expired/invalid — clear it and show the friendly sign-in prompt.
        await clearSession();
        setLoggedIn(false);
      } else {
        setError(e instanceof Error ? e.message : 'Failed to load your wishlist.');
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

  const renderItem = ({ item }: { item: ApiListing }) => (
    <TouchableOpacity
      style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }, shadow.sm]}
      activeOpacity={0.88}
      onPress={() => router.push({ pathname: '/listing/[slug]', params: { slug: item.slug } })}
    >
      <View style={styles.imageWrap}>
        <Image source={{ uri: resolveImage(item.image) }} style={styles.cardImage} />
        {item.rating > 0 ? (
          <View style={styles.ratingPos}>
            <RatingPill rating={item.rating} count={item.review_count} compact />
          </View>
        ) : null}
      </View>
      <View style={styles.cardBody}>
        {item.area ? (
          <View style={styles.areaRow}>
            <Ionicons name="location-sharp" size={11} color={colors.primary} />
            <Text style={[styles.area, { color: colors.textSub }]} numberOfLines={1}>{item.area}</Text>
          </View>
        ) : null}
        <Text style={[styles.cardTitle, { color: colors.text }]} numberOfLines={2}>{item.title}</Text>
        <Text style={[styles.price, { color: colors.accent }]}>
          {item.price > 0 ? formatPrice(item.price) : t('free')}
        </Text>
      </View>
      <FavoriteHeart
        listingId={item.id}
        favorited
        size={36}
        style={styles.heartWrap}
        onChange={(fav) => {
          // Tapping the heart here un-saves it — drop it from the list.
          if (!fav) setListings((prev) => prev.filter((l) => l.id !== item.id));
        }}
      />
    </TouchableOpacity>
  );

  return (
    <SafeAreaView style={[styles.container, { backgroundColor: colors.bgAlt }]} edges={['left', 'right']}>
      <StatusBar barStyle={isDark ? 'light-content' : 'dark-content'} />
      <View style={[styles.headerBar, { paddingTop: insets.top + 16 }]}>
        <ScreenTitle title={t('tab_wishlist')} subtitle="Places you've saved for later" />
      </View>

      {loading ? (
        <View style={styles.center}>
          <ActivityIndicator size="large" color={colors.primary} />
        </View>
      ) : !loggedIn ? (
        <EmptyState
          icon="heart-outline"
          title="Save your favorites"
          subtitle="Log in to see the listings you've saved."
          actionLabel="Log in"
          onAction={() => router.push('/(auth)/login')}
        />
      ) : (
        <FlatList
          data={listings}
          renderItem={renderItem}
          keyExtractor={(item) => String(item.id)}
          contentContainerStyle={styles.listContent}
          showsVerticalScrollIndicator={false}
          ListEmptyComponent={
            <EmptyState
              icon={error ? 'cloud-offline-outline' : 'heart-outline'}
              title={error ? 'Something went wrong' : 'No saved listings yet'}
              subtitle={error || 'Tap the heart on any listing to save it here.'}
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
  card: { flexDirection: 'row', alignItems: 'center', borderRadius: 18, overflow: 'hidden', marginBottom: 14, borderWidth: 1, padding: 8 },
  imageWrap: { width: 96, height: 96, borderRadius: 14, overflow: 'hidden' },
  cardImage: { width: '100%', height: '100%', resizeMode: 'cover', backgroundColor: '#00000011' },
  ratingPos: { position: 'absolute', top: 6, left: 6 },
  cardBody: { flex: 1, paddingHorizontal: 12 },
  areaRow: { flexDirection: 'row', alignItems: 'center', marginBottom: 3 },
  area: { fontSize: 11, fontWeight: '600', marginLeft: 3, flex: 1 },
  cardTitle: { fontSize: 15, fontWeight: '800', lineHeight: 19 },
  price: { fontSize: 14, fontWeight: '800', marginTop: 5 },
  heartWrap: { width: 36, height: 36, borderRadius: 18, alignItems: 'center', justifyContent: 'center', marginRight: 8 },
});
