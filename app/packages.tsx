import { Ionicons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import React, { useCallback, useEffect, useMemo, useState } from 'react';
import {
  ActivityIndicator,
  FlatList,
  Image,
  RefreshControl,
  ScrollView,
  StatusBar,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { EmptyState, RatingPill } from '../components/ui';
import { usePreferences } from '../lib/preferences';
import { useTheme } from '../lib/theme';
import { resolveImage } from '../services/api';
import { getPackageCoverSource } from '../services/packageImages';
import { getPackages, TravelPackage } from '../services/packageService';



export default function PackagesScreen() {
  const router = useRouter();
  const { colors, isDark, shadow, radius } = useTheme();
  const { formatPrice } = usePreferences();

  const [packages, setPackages] = useState<TravelPackage[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const [error, setError] = useState('');

  const load = useCallback(async () => {
    setError('');
    try {
      const data = await getPackages({
        search: searchQuery,
      });
      setPackages(data);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load packages.');
    } finally {
      setLoading(false);
    }
  }, [searchQuery]);

  useEffect(() => {
    load();
  }, [load]);

  const onRefresh = useCallback(async () => {
    setRefreshing(true);
    await load();
    setRefreshing(false);
  }, [load]);

  const openPackageDetails = (pkg: TravelPackage) => {
    router.push({
      pathname: '/package/[id]',
      params: { id: pkg.id },
    });
  };

  const renderItem = ({ item }: { item: TravelPackage }) => {
    const coverSource = getPackageCoverSource(item.localImageKey, item.coverImage);

    return (
      <TouchableOpacity
        style={[
          styles.card,
          { backgroundColor: colors.card, borderColor: colors.border },
          shadow.md,
        ]}
        onPress={() => openPackageDetails(item)}
        activeOpacity={0.9}
      >
        <View style={styles.imageWrap}>
          <Image source={coverSource} style={styles.cardImage} />

          {/* Top Badges */}
          <View style={styles.topBadgesRow}>
            {item.badge ? (
              <View
                style={[
                  styles.specialBadge,
                  item.badge === 'Best Seller'
                    ? { backgroundColor: '#F59E0B' }
                    : item.badge === 'Featured'
                    ? { backgroundColor: '#8B5CF6' }
                    : { backgroundColor: '#10B981' },
                ]}
              >
                <Ionicons name="sparkles" size={11} color="#FFFFFF" />
                <Text style={styles.specialBadgeText}>{item.badge.toUpperCase()}</Text>
              </View>
            ) : (
              <View />
            )}

            <RatingPill rating={item.rating} count={item.reviewCount} />
          </View>

          {/* Duration Chip & Destination on image bottom */}
          <View style={styles.bottomImageOverlay}>
            <View style={styles.durationChip}>
              <Ionicons name="time-outline" size={12} color="#FFFFFF" />
              <Text style={styles.durationChipText}>{item.duration}</Text>
            </View>
            <View style={styles.locationChip}>
              <Ionicons name="location-sharp" size={12} color="#FFFFFF" />
              <Text style={styles.locationChipText} numberOfLines={1}>
                {item.destination}
              </Text>
            </View>
          </View>
        </View>

        <View style={styles.cardBody}>
          {/* Category Tag */}
          <View style={styles.metaHeaderRow}>
            <Text style={[styles.categoryLabel, { color: '#8B5CF6' }]}>
              {item.categoryLabel.toUpperCase()}
            </Text>
            <Text style={[styles.groupSizeText, { color: colors.textMute }]}>
              Max {item.maxGroupSize} guests
            </Text>
          </View>

          {/* Title */}
          <Text style={[styles.cardTitle, { color: colors.text }]} numberOfLines={2}>
            {item.title}
          </Text>

          {/* Tagline */}
          <Text style={[styles.cardTagline, { color: colors.textSub }]} numberOfLines={2}>
            {item.tagline}
          </Text>

          {/* Guide Host Info */}
          <View style={[styles.hostBanner, { backgroundColor: isDark ? '#1F2430' : '#F4F5F9' }]}>
            {item.host?.avatar ? (
              <Image source={{ uri: resolveImage(item.host.avatar) }} style={styles.hostAvatar} />
            ) : (
              <View style={[styles.hostAvatar, styles.hostAvatarFallback]}>
                <Ionicons name="person" size={14} color="#8B5CF6" />
              </View>
            )}
            <View style={{ flex: 1, marginLeft: 8 }}>
              <View style={styles.hostNameRow}>
                <Text style={[styles.hostName, { color: colors.text }]} numberOfLines={1}>
                  Hosted by {item.host.name}
                </Text>
                <Ionicons name="checkmark-circle" size={14} color="#10B981" style={{ marginLeft: 4 }} />
              </View>
              <Text style={[styles.hostRole, { color: colors.textSub }]} numberOfLines={1}>
                {item.host.badge} • {item.host.toursCount}+ tours
              </Text>
            </View>
          </View>

          {/* Inclusions Highlights */}
          <View style={styles.inclusionsRow}>
            {item.inclusions.slice(0, 4).map((inc, index) => (
              <View
                key={index}
                style={[
                  styles.inclusionPill,
                  { backgroundColor: isDark ? '#2D3344' : '#EAEFF8' },
                ]}
              >
                <Ionicons
                  name={inc.icon as any}
                  size={12}
                  color={isDark ? '#A78BFA' : '#6D28D9'}
                />
                <Text
                  style={[
                    styles.inclusionPillText,
                    { color: isDark ? '#E2E8F0' : '#334155' },
                  ]}
                  numberOfLines={1}
                >
                  {inc.title}
                </Text>
              </View>
            ))}
          </View>

          {/* Divider */}
          <View style={[styles.divider, { backgroundColor: colors.border }]} />

          {/* Card Footer: Price & View Action */}
          <View style={styles.cardFooter}>
            <View>
              <Text style={[styles.pricePerPersonLabel, { color: colors.textMute }]}>
                TOTAL PACKAGE PRICE
              </Text>
              <View style={styles.priceRow}>
                <Text style={[styles.cardPrice, { color: colors.text }]}>
                  {formatPrice(item.price)}
                </Text>
                <Text style={[styles.cardPriceUnit, { color: colors.textSub }]}> Package Price</Text>
                {item.originalPrice ? (
                  <Text style={[styles.originalPrice, { color: colors.textMute }]}>
                    {formatPrice(item.originalPrice)}
                  </Text>
                ) : null}
              </View>
            </View>

            <View style={[styles.viewBtn, { backgroundColor: '#8B5CF6' }]}>
              <Text style={styles.viewBtnText}>View details</Text>
              <Ionicons name="chevron-forward" size={15} color="#FFFFFF" />
            </View>
          </View>
        </View>
      </TouchableOpacity>
    );
  };

  return (
    <SafeAreaView
      style={[styles.container, { backgroundColor: colors.bgAlt }]}
      edges={['top', 'left', 'right']}
    >
      <StatusBar barStyle={isDark ? 'light-content' : 'dark-content'} />

      {/* Top Navigation Header */}
      <View style={styles.header}>
        <TouchableOpacity
          style={[styles.backButton, { backgroundColor: colors.card, borderColor: colors.border }]}
          onPress={() => router.back()}
          activeOpacity={0.7}
        >
          <Ionicons name="arrow-back" size={20} color={colors.text} />
        </TouchableOpacity>
        <View style={styles.headerTitleWrap}>
          <Text style={[styles.headerTitle, { color: colors.text }]}>Packages</Text>
          <Text style={[styles.headerSubtitle, { color: colors.textSub }]}>
            All-inclusive trips curated by local guides
          </Text>
        </View>
        <View style={styles.headerPlaceholder} />
      </View>

      {/* Search Input Bar */}
      <View style={styles.searchSection}>
        <View
          style={[
            styles.searchBar,
            { backgroundColor: colors.card, borderColor: colors.border },
            shadow.sm,
          ]}
        >
          <Ionicons name="search" size={18} color={colors.textMute} style={{ marginRight: 8 }} />
          <TextInput
            style={[styles.searchInput, { color: colors.text }]}
            placeholder="Search packages, destinations, or guides..."
            placeholderTextColor={colors.textMute}
            value={searchQuery}
            onChangeText={setSearchQuery}
            returnKeyType="search"
          />
          {searchQuery.length > 0 ? (
            <TouchableOpacity onPress={() => setSearchQuery('')} hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }}>
              <Ionicons name="close-circle" size={18} color={colors.textMute} />
            </TouchableOpacity>
          ) : null}
        </View>
      </View>



      {/* Content Section */}
      {loading ? (
        <View style={styles.center}>
          <ActivityIndicator size="large" color="#8B5CF6" />
          <Text style={[styles.loadingText, { color: colors.textSub }]}>Loading travel packages...</Text>
        </View>
      ) : (
        <FlatList
          data={packages}
          renderItem={renderItem}
          keyExtractor={(item) => item.id}
          showsVerticalScrollIndicator={false}
          contentContainerStyle={styles.listContent}
          refreshControl={
            <RefreshControl
              refreshing={refreshing}
              onRefresh={onRefresh}
              tintColor="#8B5CF6"
              colors={['#8B5CF6']}
            />
          }

          ListEmptyComponent={
            <EmptyState
              icon={error ? 'cloud-offline-outline' : 'cube-outline'}
              title={error ? 'Unable to load packages' : 'No packages found'}
              subtitle={
                error ||
                (searchQuery
                  ? `No travel packages match "${searchQuery}". Try a different keyword or category.`
                  : 'Check back soon for new travel packages.')
              }
              actionLabel={error ? 'Retry' : searchQuery ? 'Clear Search' : undefined}
              onAction={() => {
                if (error) load();
                else setSearchQuery('');
              }}
            />
          }
        />
      )}
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1 },
  center: {
    flexGrow: 1,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 30,
    paddingTop: 60,
  },
  loadingText: { marginTop: 12, fontSize: 14, fontWeight: '500' },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 16,
    paddingTop: 8,
    paddingBottom: 10,
  },
  backButton: {
    width: 40,
    height: 40,
    borderRadius: 20,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
  },
  headerTitleWrap: { flex: 1, alignItems: 'center', marginHorizontal: 8 },
  headerTitle: { fontSize: 20, fontWeight: '800', letterSpacing: -0.3 },
  headerSubtitle: { fontSize: 12, marginTop: 2, fontWeight: '500' },
  headerPlaceholder: { width: 40 },
  searchSection: {
    paddingHorizontal: 16,
    paddingTop: 4,
    paddingBottom: 8,
  },
  searchBar: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 14,
    height: 46,
    borderRadius: 14,
    borderWidth: 1,
  },
  searchInput: {
    flex: 1,
    fontSize: 14,
    fontWeight: '500',
    paddingVertical: 0,
  },
  filtersWrapper: {
    paddingBottom: 10,
  },
  filtersScrollContent: {
    paddingHorizontal: 16,
    gap: 8,
  },
  filterChip: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 7,
    paddingHorizontal: 14,
    borderRadius: 999,
    borderWidth: 1,
  },
  filterChipText: {
    fontSize: 13,
    fontWeight: '600',
  },
  highlightBanner: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 14,
    borderRadius: 16,
    borderWidth: 1,
    marginBottom: 16,
  },
  highlightIconBox: {
    width: 40,
    height: 40,
    borderRadius: 12,
    backgroundColor: '#EDE9FE',
    alignItems: 'center',
    justifyContent: 'center',
  },
  highlightTitle: {
    fontSize: 14,
    fontWeight: '800',
    letterSpacing: -0.2,
  },
  highlightDesc: {
    fontSize: 12,
    marginTop: 2,
    lineHeight: 16,
  },
  listContent: {
    paddingHorizontal: 16,
    paddingBottom: 32,
    flexGrow: 1,
  },
  card: {
    borderRadius: 22,
    overflow: 'hidden',
    marginBottom: 20,
    borderWidth: 1,
  },
  imageWrap: {
    width: '100%',
    height: 190,
    position: 'relative',
    backgroundColor: '#00000018',
  },
  cardImage: {
    width: '100%',
    height: '100%',
    resizeMode: 'cover',
  },
  topBadgesRow: {
    position: 'absolute',
    top: 12,
    left: 12,
    right: 12,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  specialBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 4,
    paddingHorizontal: 9,
    borderRadius: 999,
    gap: 4,
  },
  specialBadgeText: {
    color: '#FFFFFF',
    fontSize: 10,
    fontWeight: '900',
    letterSpacing: 0.5,
  },
  bottomImageOverlay: {
    position: 'absolute',
    bottom: 10,
    left: 12,
    right: 12,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  durationChip: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    backgroundColor: 'rgba(0, 0, 0, 0.7)',
    paddingVertical: 4,
    paddingHorizontal: 10,
    borderRadius: 999,
  },
  durationChipText: {
    color: '#FFFFFF',
    fontSize: 11,
    fontWeight: '800',
  },
  locationChip: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 3,
    backgroundColor: 'rgba(0, 0, 0, 0.6)',
    paddingVertical: 4,
    paddingHorizontal: 9,
    borderRadius: 999,
    maxWidth: '65%',
  },
  locationChipText: {
    color: '#FFFFFF',
    fontSize: 11,
    fontWeight: '600',
  },
  cardBody: {
    padding: 16,
  },
  metaHeaderRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 4,
  },
  categoryLabel: {
    fontSize: 11,
    fontWeight: '800',
    letterSpacing: 0.6,
  },
  groupSizeText: {
    fontSize: 11,
    fontWeight: '600',
  },
  cardTitle: {
    fontSize: 17,
    fontWeight: '800',
    lineHeight: 23,
    letterSpacing: -0.3,
    marginBottom: 4,
  },
  cardTagline: {
    fontSize: 13,
    lineHeight: 18,
    marginBottom: 12,
  },
  hostBanner: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 9,
    borderRadius: 12,
    marginBottom: 12,
  },
  hostAvatar: {
    width: 32,
    height: 32,
    borderRadius: 16,
    backgroundColor: '#E5E7EB',
  },
  hostAvatarFallback: {
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#EDE9FE',
  },
  hostNameRow: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  hostName: {
    fontSize: 12,
    fontWeight: '700',
  },
  hostRole: {
    fontSize: 11,
    fontWeight: '500',
    marginTop: 1,
  },
  inclusionsRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 6,
    marginBottom: 14,
  },
  inclusionPill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    paddingVertical: 4,
    paddingHorizontal: 8,
    borderRadius: 8,
    maxWidth: '48%',
  },
  inclusionPillText: {
    fontSize: 11,
    fontWeight: '600',
  },
  divider: {
    height: 1,
    marginBottom: 14,
  },
  cardFooter: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  pricePerPersonLabel: {
    fontSize: 9,
    fontWeight: '700',
    letterSpacing: 0.5,
  },
  priceRow: {
    flexDirection: 'row',
    alignItems: 'baseline',
    marginTop: 2,
    gap: 4,
  },
  cardPrice: {
    fontSize: 18,
    fontWeight: '900',
    letterSpacing: -0.5,
  },
  cardPriceUnit: {
    fontSize: 11,
    fontWeight: '500',
  },
  originalPrice: {
    fontSize: 12,
    textDecorationLine: 'line-through',
    marginLeft: 4,
  },
  viewBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    paddingVertical: 9,
    paddingHorizontal: 15,
    borderRadius: 999,
  },
  viewBtnText: {
    color: '#FFFFFF',
    fontSize: 13,
    fontWeight: '800',
  },
});
