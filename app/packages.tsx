import { Ionicons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import React, { useCallback, useEffect, useMemo, useState } from 'react';
import {
  ActivityIndicator,
  FlatList,
  Image,
  Platform,
  RefreshControl,
  StatusBar,
  StyleSheet,
  Text,
  TouchableOpacity,
  useWindowDimensions,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { EmptyState, RatingPill } from '../components/ui';
import { usePreferences } from '../lib/preferences';
import { ThemeColors, useTheme } from '../lib/theme';
import { resolveImage } from '../services/api';
import { getPackageCoverSource } from '../services/packageImages';
import { getPackages, TravelPackage } from '../services/packageService';

/**
 * Single Travel Package Card with cover photo layout and visual treatment
 * matching the Things to Do cover photo design pattern and Reference Image 1.
 * Memoized to ensure smooth 60fps scrolling and prevent unnecessary list item re-renders.
 */
const PackageCardItem = React.memo(function PackageCardItem({
  item,
  onPress,
  isDark,
  colors,
  shadow,
  formatPrice,
}: {
  item: TravelPackage;
  onPress: (item: TravelPackage) => void;
  isDark: boolean;
  colors: ThemeColors;
  shadow: any;
  formatPrice: (amount: number) => string;
}) {
  const [isFavorited, setIsFavorited] = useState(false);

  const handlePress = useCallback(() => {
    onPress(item);
  }, [onPress, item]);

  const coverSource = useMemo(
    () => getPackageCoverSource(item.localImageKey, item.coverImage),
    [item.localImageKey, item.coverImage]
  );

  return (
    <TouchableOpacity
      style={[
        styles.card,
        {
          backgroundColor: colors.card,
          borderColor: colors.border,
        },
        shadow.sm,
      ]}
      onPress={handlePress}
      activeOpacity={0.9}
    >
      {/* 1. Cover Photo with Things to Do layout matching Reference Image 1 */}
      <View style={styles.imageWrap}>
        <Image source={coverSource} style={styles.cardImage} />

        {/* Favorite Heart Top-Left */}
        <TouchableOpacity
          style={styles.heart}
          onPress={() => setIsFavorited(!isFavorited)}
          activeOpacity={0.8}
          hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }}
          accessibilityLabel="Save package"
        >
          <Ionicons
            name={isFavorited ? 'heart' : 'heart-outline'}
            size={18}
            color={isFavorited ? colors.danger : '#FFFFFF'}
          />
        </TouchableOpacity>

        {/* Rating Pill Top-Right */}
        <View style={styles.ratingPos}>
          <RatingPill rating={item.rating} count={item.reviewCount} />
        </View>

        {/* Location Chip Bottom-Left */}
        {item.destination ? (
          <View style={styles.areaChip}>
            <Ionicons name="location-sharp" size={11} color="#FFFFFF" />
            <Text style={styles.areaChipText} numberOfLines={1}>
              {item.destination}
            </Text>
          </View>
        ) : null}

        {/* Highlight Badge Bottom-Right */}
        {item.badge ? (
          <View style={styles.badgePos}>
            <Ionicons name="sparkles" size={10} color="#FFFFFF" />
            <Text style={styles.badgeText}>
              {item.badge.toUpperCase()}
            </Text>
          </View>
        ) : null}
      </View>

      {/* 2. Card Content Body */}
      <View style={styles.cardBody}>
        {/* Title */}
        <Text style={[styles.cardTitle, { color: colors.text }]} numberOfLines={1}>
          {item.title}
        </Text>

        {/* Description / Summary */}
        {item.tagline || item.description ? (
          <Text style={[styles.cardDesc, { color: colors.textSub }]} numberOfLines={2}>
            {item.tagline || item.description}
          </Text>
        ) : null}

        {/* 3-Column Key Trip Attributes (Duration, Group Size, Assurance) */}
        <View
          style={[
            styles.tripAttributesBox,
            {
              backgroundColor: isDark ? colors.surface : colors.chipBg,
              borderColor: colors.border,
            },
          ]}
        >
          {/* Column 1: Duration */}
          <View style={styles.attributeCol}>
            <Ionicons name="time-outline" size={16} color={colors.primary} />
            <Text style={[styles.attributeLabel, { color: colors.textMute }]}>
              DURATION
            </Text>
            <Text style={[styles.attributeValue, { color: colors.text }]} numberOfLines={1}>
              {item.duration}
            </Text>
          </View>

          {/* Column 2: Group Size */}
          <View
            style={[
              styles.attributeCol,
              styles.attributeDivider,
              { borderColor: colors.border },
            ]}
          >
            <Ionicons name="people-outline" size={16} color={colors.primary} />
            <Text style={[styles.attributeLabel, { color: colors.textMute }]}>
              GROUP SIZE
            </Text>
            <Text style={[styles.attributeValue, { color: colors.text }]} numberOfLines={1}>
              Up to {item.maxGroupSize}
            </Text>
          </View>

          {/* Column 3: Assurance */}
          <View style={styles.attributeCol}>
            <Ionicons name="shield-checkmark-outline" size={16} color={colors.primary} />
            <Text style={[styles.attributeLabel, { color: colors.textMute }]}>
              ASSURANCE
            </Text>
            <Text style={[styles.attributeValue, { color: colors.primary }]} numberOfLines={1}>
              {item.assurance || 'Guaranteed'}
            </Text>
          </View>
        </View>

        {/* Host Information Banner */}
        <View
          style={[
            styles.hostInfoBanner,
            {
              backgroundColor: isDark ? colors.surface : colors.bgAlt,
              borderColor: colors.border,
            },
          ]}
        >
          {item.host?.avatar ? (
            <Image source={{ uri: resolveImage(item.host.avatar) }} style={styles.hostAvatar} />
          ) : (
            <View style={[styles.hostAvatar, styles.hostAvatarFallback, { backgroundColor: isDark ? colors.cardAlt : colors.chipBg }]}>
              <Ionicons name="person" size={15} color={colors.textMute} />
            </View>
          )}
          <View style={{ flex: 1, marginLeft: 10 }}>
            <View style={{ flexDirection: 'row', alignItems: 'center' }}>
              <Text
                style={[styles.hostName, { color: colors.text }]}
                numberOfLines={1}
              >
                {item.host.name}
              </Text>
              <View style={[styles.verifiedCheckPill, { backgroundColor: colors.primary }]}>
                <Ionicons name="checkmark" size={9} color="#FFFFFF" />
              </View>
            </View>
            <Text style={[styles.hostStatsSubtitle, { color: colors.textSub }]} numberOfLines={1}>
              {item.host.badge} • {item.host.toursCount}+ tours hosted
            </Text>
          </View>
        </View>

        {/* Bundled Inclusions Highlights */}
        {item.inclusions && item.inclusions.length > 0 ? (
          <View style={styles.inclusionsRow}>
            {item.inclusions.slice(0, 3).map((inc, index) => (
              <View
                key={index}
                style={[
                  styles.inclusionItemPill,
                  {
                    backgroundColor: isDark ? colors.surface : colors.chipBg,
                    borderColor: colors.border,
                  },
                ]}
              >
                <Ionicons name="checkmark-circle" size={13} color={colors.primary} />
                <Text
                  style={[styles.inclusionItemText, { color: colors.text }]}
                  numberOfLines={1}
                >
                  {inc.title}
                </Text>
              </View>
            ))}
          </View>
        ) : null}

        {/* Divider */}
        <View
          style={[
            styles.cardDivider,
            { backgroundColor: colors.border },
          ]}
        />

        {/* Card Footer: Pricing & Action Button matching Reference Image 1 */}
        <View style={styles.cardFooter}>
          <View>
            <Text style={[styles.priceTagLabel, { color: colors.textMute }]}>
              TOTAL PACKAGE PRICE
            </Text>
            <View style={styles.priceNumberRow}>
              <Text style={[styles.cardPriceAmount, { color: colors.accent }]}>
                {formatPrice(item.price)}
              </Text>
              <Text style={[styles.cardPriceNote, { color: colors.textMute }]}>
                {' '}/ single bundle
              </Text>
            </View>
          </View>

          <View style={[styles.viewDetailsBtn, { backgroundColor: colors.primary }]}>
            <Text style={styles.viewDetailsBtnText}>View</Text>
            <Ionicons name="arrow-forward" size={13} color="#FFFFFF" />
          </View>
        </View>
      </View>
    </TouchableOpacity>
  );
});

export default function PackagesScreen() {
  const router = useRouter();
  const { colors, isDark, shadow } = useTheme();
  const { formatPrice } = usePreferences();
  const { width: windowWidth } = useWindowDimensions();

  const [packages, setPackages] = useState<TravelPackage[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState('');

  const load = useCallback(async () => {
    try {
      const data = await getPackages();
      setPackages(data);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load packages.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    let active = true;
    const fetchPackages = async () => {
      try {
        const data = await getPackages();
        if (active) setPackages(data);
      } catch (e) {
        if (active) setError(e instanceof Error ? e.message : 'Failed to load packages.');
      } finally {
        if (active) setLoading(false);
      }
    };
    void fetchPackages();
    return () => {
      active = false;
    };
  }, []);

  const onRefresh = useCallback(async () => {
    setRefreshing(true);
    await load();
    setRefreshing(false);
  }, [load]);

  const openPackageDetails = useCallback(
    (pkg: TravelPackage) => {
      router.push({
        pathname: '/package/[id]',
        params: { id: pkg.id },
      });
    },
    [router]
  );

  const numColumns = windowWidth >= 768 ? 2 : 1;

  const renderItem = useCallback(
    ({ item }: { item: TravelPackage }) => (
      <PackageCardItem
        item={item}
        onPress={openPackageDetails}
        isDark={isDark}
        colors={colors}
        shadow={shadow}
        formatPrice={formatPrice}
      />
    ),
    [openPackageDetails, isDark, colors, shadow, formatPrice]
  );

  return (
    <SafeAreaView
      style={[styles.container, { backgroundColor: colors.bgAlt }]}
      edges={['top', 'left', 'right']}
    >
      <StatusBar barStyle={isDark ? 'light-content' : 'dark-content'} />

      {/* Top Header Navigation: Back button to home + Travel Packages title */}
      <View style={styles.header}>
        <TouchableOpacity
          style={[
            styles.backButton,
            {
              backgroundColor: colors.card,
              borderColor: colors.border,
            },
          ]}
          onPress={() => {
            if (router.canGoBack()) {
              router.back();
            } else {
              router.replace('/(tabs)');
            }
          }}
          activeOpacity={0.7}
          accessibilityLabel="Back to Home"
        >
          <Ionicons name="arrow-back" size={20} color={colors.text} />
        </TouchableOpacity>

        <Text style={[styles.headerTitle, { color: colors.text }]} numberOfLines={1}>
          Travel Packages
        </Text>

        <View style={styles.headerSpacer} />
      </View>

      {/* Content Section */}
      {loading ? (
        <View style={styles.center}>
          <ActivityIndicator size="large" color={colors.primary} />
          <Text style={[styles.loadingText, { color: colors.textSub }]}>
            Loading travel packages...
          </Text>
        </View>
      ) : (
        <FlatList
          key={numColumns}
          data={packages}
          renderItem={renderItem}
          keyExtractor={(item) => item.id}
          numColumns={numColumns}
          columnWrapperStyle={numColumns > 1 ? styles.columnWrapper : undefined}
          showsVerticalScrollIndicator={false}
          contentContainerStyle={[styles.listContent, numColumns > 1 && styles.listContentMultiColumn]}
          initialNumToRender={4}
          maxToRenderPerBatch={4}
          windowSize={5}
          removeClippedSubviews={Platform.OS !== 'web'}
          refreshControl={
            <RefreshControl
              refreshing={refreshing}
              onRefresh={onRefresh}
              tintColor={colors.primary}
              colors={[colors.primary]}
            />
          }
          ListEmptyComponent={
            <EmptyState
              icon={error ? 'cloud-offline-outline' : 'cube-outline'}
              title={error ? 'Unable to load packages' : 'No packages found'}
              subtitle={
                error || 'No travel packages available at the moment. Please check back later.'
              }
              actionLabel={error ? 'Retry' : undefined}
              onAction={error ? load : undefined}
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
    paddingBottom: 12,
  },
  backButton: {
    width: 40,
    height: 40,
    borderRadius: 20,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
  },
  headerTitle: {
    fontSize: 18,
    fontWeight: '700',
    letterSpacing: -0.3,
    textAlign: 'center',
    flex: 1,
  },
  headerSpacer: {
    width: 40,
    height: 40,
  },
  listContent: {
    paddingHorizontal: 16,
    paddingTop: 8,
    paddingBottom: 36,
    flexGrow: 1,
  },
  listContentMultiColumn: {
    maxWidth: 1120,
    alignSelf: 'center',
    width: '100%',
  },
  columnWrapper: {
    gap: 16,
  },
  card: {
    borderRadius: 20,
    overflow: 'hidden',
    marginBottom: 20,
    borderWidth: 1,
  },
  imageWrap: {
    width: '100%',
    height: 190,
    position: 'relative',
    backgroundColor: '#00000011',
  },
  cardImage: {
    width: '100%',
    height: '100%',
    resizeMode: 'cover',
  },
  ratingPos: {
    position: 'absolute',
    top: 10,
    right: 10,
    zIndex: 5,
  },
  heart: {
    position: 'absolute',
    top: 10,
    left: 10,
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: 'rgba(0,0,0,0.5)',
    alignItems: 'center',
    justifyContent: 'center',
    zIndex: 5,
  },
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
    maxWidth: '65%',
    zIndex: 5,
  },
  areaChipText: {
    color: '#FFFFFF',
    fontSize: 11,
    fontWeight: '700',
  },
  badgePos: {
    position: 'absolute',
    bottom: 10,
    right: 10,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 3,
    backgroundColor: '#F59E0B',
    paddingHorizontal: 9,
    paddingVertical: 4,
    borderRadius: 999,
    zIndex: 5,
  },
  badgeText: {
    color: '#FFFFFF',
    fontSize: 10,
    fontWeight: '900',
    letterSpacing: 0.5,
  },
  cardBody: {
    padding: 16,
  },
  cardTitle: {
    fontSize: 17,
    fontWeight: '800',
    letterSpacing: -0.3,
    marginBottom: 4,
  },
  cardDesc: {
    fontSize: 13,
    lineHeight: 18,
    marginBottom: 12,
  },
  tripAttributesBox: {
    flexDirection: 'row',
    borderRadius: 12,
    paddingVertical: 10,
    paddingHorizontal: 6,
    borderWidth: 1,
    marginBottom: 12,
  },
  attributeCol: {
    flex: 1,
    alignItems: 'center',
    paddingHorizontal: 4,
  },
  attributeDivider: {
    borderLeftWidth: 1,
    borderRightWidth: 1,
  },
  attributeLabel: {
    fontSize: 9,
    fontWeight: '800',
    letterSpacing: 0.5,
    marginTop: 3,
  },
  attributeValue: {
    fontSize: 13,
    fontWeight: '800',
    marginTop: 2,
  },
  hostInfoBanner: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 9,
    borderRadius: 12,
    borderWidth: 1,
    marginBottom: 12,
  },
  hostAvatar: {
    width: 34,
    height: 34,
    borderRadius: 17,
    backgroundColor: '#334155',
  },
  hostAvatarFallback: {
    alignItems: 'center',
    justifyContent: 'center',
  },
  hostName: {
    fontSize: 12,
    fontWeight: '800',
  },
  verifiedCheckPill: {
    width: 14,
    height: 14,
    borderRadius: 7,
    alignItems: 'center',
    justifyContent: 'center',
    marginLeft: 4,
  },
  hostStatsSubtitle: {
    fontSize: 11,
    fontWeight: '600',
    marginTop: 1,
  },
  inclusionsRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 6,
    marginBottom: 14,
  },
  inclusionItemPill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 5,
    paddingVertical: 4,
    paddingHorizontal: 9,
    borderRadius: 8,
    borderWidth: 1,
  },
  inclusionItemText: {
    fontSize: 11,
    fontWeight: '700',
  },
  cardDivider: {
    height: 1,
    marginBottom: 14,
  },
  cardFooter: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  priceTagLabel: {
    fontSize: 9,
    fontWeight: '800',
    letterSpacing: 0.5,
  },
  priceNumberRow: {
    flexDirection: 'row',
    alignItems: 'baseline',
    gap: 2,
    marginTop: 2,
  },
  cardPriceAmount: {
    fontSize: 19,
    fontWeight: '900',
    letterSpacing: -0.5,
  },
  cardPriceNote: {
    fontSize: 11,
    fontWeight: '500',
  },
  viewDetailsBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    paddingVertical: 8,
    paddingHorizontal: 14,
    borderRadius: 999,
  },
  viewDetailsBtnText: {
    color: '#FFFFFF',
    fontSize: 13,
    fontWeight: '800',
  },
});
