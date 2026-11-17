import { Ionicons } from '@expo/vector-icons';
import { useLocalSearchParams, useRouter } from 'expo-router';
import React, { useCallback, useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Image,
  ScrollView,
  StatusBar,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { EmptyState } from '../../components/ui';
import { useTheme } from '../../lib/theme';
import {
  ApiChatPartner,
  ApiGuideReview,
  ApiListing,
  getGuideProfile,
  resolveImage,
} from '../../services/api';

export default function GuideProfileScreen() {
  const router = useRouter();
  const { id } = useLocalSearchParams<{ id: string }>();
  const { colors, isDark, shadow } = useTheme();

  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [guide, setGuide] = useState<ApiChatPartner | null>(null);
  const [listings, setListings] = useState<ApiListing[]>([]);
  const [reviews, setReviews] = useState<ApiGuideReview[]>([]);

  const loadProfile = useCallback(async () => {
    if (!id) return;
    try {
      setLoading(true);
      setError('');
      const data = await getGuideProfile(id);
      setGuide(data.guide);
      setListings(data.listings || []);
      setReviews(data.reviews || []);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load guide profile.');
    } finally {
      setLoading(false);
    }
  }, [id]);

  useEffect(() => {
    let active = true;
    const fetchProfile = async () => {
      if (!id) return;
      try {
        const data = await getGuideProfile(id);
        if (active) {
          setGuide(data.guide);
          setListings(data.listings || []);
          setReviews(data.reviews || []);
          setError('');
        }
      } catch (e) {
        if (active) {
          setError(e instanceof Error ? e.message : 'Failed to load guide profile.');
        }
      } finally {
        if (active) {
          setLoading(false);
        }
      }
    };
    void fetchProfile();
    return () => {
      active = false;
    };
  }, [id]);

  const initials = (name?: string) =>
    (name || 'G')
      .split(' ')
      .map((w) => w.charAt(0))
      .slice(0, 2)
      .join('')
      .toUpperCase();

  const specialtiesList = (guide?.specialties || '')
    .split(',')
    .map((s) => s.trim())
    .filter(Boolean);

  const formatMemberSince = (dateStr?: string) => {
    if (!dateStr) return 'Not Provided';
    const d = new Date(dateStr.replace(' ', 'T'));
    if (isNaN(d.getTime())) return 'Not Provided';
    return d.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
  };

  const formatReviewDate = (dateStr?: string) => {
    if (!dateStr) return '';
    const d = new Date(dateStr.replace(' ', 'T'));
    if (isNaN(d.getTime())) return dateStr;
    return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
  };

  return (
    <SafeAreaView style={[styles.container, { backgroundColor: colors.bgAlt }]} edges={['top', 'left', 'right']}>
      <StatusBar barStyle={isDark ? 'light-content' : 'dark-content'} />

      {/* Header */}
      <View style={styles.header}>
        <TouchableOpacity
          style={[styles.backButton, { backgroundColor: colors.card, borderColor: colors.border }]}
          onPress={() => router.back()}
          activeOpacity={0.7}
        >
          <Ionicons name="arrow-back" size={22} color={colors.text} />
        </TouchableOpacity>
        <Text style={[styles.headerTitle, { color: colors.text }]} numberOfLines={1}>
          Guide Profile
        </Text>
        <View style={styles.backButton} />
      </View>

      {loading ? (
        <View style={styles.center}>
          <ActivityIndicator size="large" color={colors.primary} />
        </View>
      ) : error || !guide ? (
        <View style={styles.center}>
          <EmptyState
            icon="alert-circle-outline"
            title="Guide Not Found"
            subtitle={error || 'Could not find details for this tour guide.'}
            actionLabel="Try Again"
            onAction={loadProfile}
          />
        </View>
      ) : (
        <ScrollView
          showsVerticalScrollIndicator={false}
          contentContainerStyle={styles.scrollContent}
        >
          {/* Guide Header Card */}
          <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }, shadow.sm]}>
            <View style={styles.avatarRow}>
              <View style={styles.avatarWrap}>
                {guide?.avatar ? (
                  <Image source={{ uri: resolveImage(guide.avatar) }} style={styles.avatar} />
                ) : (
                  <View style={[styles.avatar, styles.avatarFallback, { backgroundColor: colors.chipBg }]}>
                    <Text style={[styles.avatarInitials, { color: colors.primary }]}>
                      {initials(guide?.name)}
                    </Text>
                  </View>
                )}
                {guide?.online ? <View style={[styles.onlineDot, { borderColor: colors.card }]} /> : null}
              </View>

              <View style={styles.headerMeta}>
                <Text style={[styles.guideName, { color: colors.text }]}>
                  {guide?.name?.trim() ? guide.name : 'Not Provided'}
                </Text>

                <View style={styles.badgeRow}>
                  {guide?.is_verified ? (
                    <View style={styles.verifiedBadge}>
                      <Ionicons name="checkmark-circle" size={13} color="#15803D" />
                      <Text style={styles.verifiedText}>Verified Guide</Text>
                    </View>
                  ) : null}

                  <View style={[styles.roleBadge, { backgroundColor: colors.chipBg }]}>
                    <Text style={[styles.roleText, { color: colors.primary }]}>
                      {guide?.role ? 'Tour Guide' : 'Not Provided'}
                    </Text>
                  </View>
                </View>

                <View style={styles.locationRow}>
                  <Ionicons name="location-sharp" size={14} color="#EF4444" />
                  <Text style={[styles.locationText, { color: colors.textSub }]}>
                    {guide?.location?.trim() ? guide.location : 'Not Provided'}
                  </Text>
                </View>
              </View>
            </View>

            <TouchableOpacity
              style={[styles.primaryActionBtn, { backgroundColor: colors.primary }]}
              activeOpacity={0.85}
              onPress={() => guide?.id ? router.push(`/chat/${guide.id}` as any) : null}
            >
              <Ionicons name="chatbubble-ellipses" size={18} color="#FFFFFF" />
              <Text style={styles.primaryActionBtnText}>Message Guide</Text>
            </TouchableOpacity>
          </View>

          {/* About the Guide */}
          <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }, shadow.sm]}>
            <Text style={[styles.sectionTitle, { color: colors.text }]}>About the Guide</Text>
            <Text style={[styles.bioText, { color: colors.textSub }]}>
              {guide?.bio?.trim() ? guide.bio : 'Not Provided'}
            </Text>

            {/* 4-Stat Grid */}
            <View style={[styles.statsGrid, { backgroundColor: colors.bgAlt, borderColor: colors.border }]}>
              <View style={styles.statCol}>
                <Text style={[styles.statLabel, { color: colors.textMute }]}>EXPERIENCE</Text>
                <Text style={[styles.statValue, { color: colors.text }]}>
                  {guide?.experience?.trim() ? guide.experience : 'Not Provided'}
                </Text>
              </View>

              <View style={styles.statCol}>
                <Text style={[styles.statLabel, { color: colors.textMute }]}>LANGUAGES</Text>
                <Text style={[styles.statValue, { color: colors.text }]}>
                  {guide?.languages?.trim() ? guide.languages : 'Not Provided'}
                </Text>
              </View>

              <View style={styles.statCol}>
                <Text style={[styles.statLabel, { color: colors.textMute }]}>RATING</Text>
                <Text style={[styles.statValue, { color: colors.text }]}>
                  {guide?.rating && guide.rating > 0
                    ? `⭐ ${guide.rating.toFixed(1)} / 5.0`
                    : 'Not Provided'}
                </Text>
              </View>

              <View style={styles.statCol}>
                <Text style={[styles.statLabel, { color: colors.textMute }]}>MEMBER SINCE</Text>
                <Text style={[styles.statValue, { color: colors.text }]}>
                  {formatMemberSince(guide?.created_at)}
                </Text>
              </View>
            </View>

            {/* Specialties Section */}
            <View style={styles.specialtiesWrap}>
              <Text style={[styles.subSectionTitle, { color: colors.text }]}>Specialties</Text>
              {specialtiesList.length > 0 ? (
                <View style={styles.tagsContainer}>
                  {specialtiesList.map((spec, i) => (
                    <View key={i} style={[styles.specialtyPill, { backgroundColor: colors.chipBg, borderColor: colors.border }]}>
                      <Text style={[styles.specialtyText, { color: colors.primary }]}>{spec}</Text>
                    </View>
                  ))}
                </View>
              ) : (
                <Text style={[styles.emptyHint, { color: colors.textSub }]}>
                  Not Provided
                </Text>
              )}
            </View>
          </View>

          {/* Available Listings */}
          <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }, shadow.sm]}>
            <Text style={[styles.sectionTitle, { color: colors.text }]}>Available Listings</Text>
            {listings.length === 0 ? (
              <View style={styles.emptyCardContent}>
                <Ionicons name="compass-outline" size={32} color={colors.textMute} />
                <Text style={[styles.emptyHint, { color: colors.textMute, marginTop: 6 }]}>
                  No listings currently available for this guide.
                </Text>
              </View>
            ) : (
              <View style={styles.listingsList}>
                {listings.map((l) => (
                  <TouchableOpacity
                    key={l.id}
                    style={[styles.listingItem, { backgroundColor: colors.bgAlt, borderColor: colors.border }]}
                    activeOpacity={0.8}
                    onPress={() => router.push(`/listing/${l.slug}` as any)}
                  >
                    <Image
                      source={{ uri: resolveImage(l.image || l.photos?.[0] || '') }}
                      style={styles.listingThumb}
                    />
                    <View style={styles.listingInfo}>
                      <Text style={[styles.listingTitle, { color: colors.text }]} numberOfLines={1}>
                        {l.title}
                      </Text>
                      <Text style={[styles.listingCategory, { color: colors.textSub }]}>
                        {l.category || 'Experience'}
                      </Text>
                      {l.price > 0 ? (
                        <Text style={[styles.listingPrice, { color: colors.primary }]}>
                          ₱{l.price.toLocaleString()} <Text style={[styles.listingPriceUnit, { color: colors.textMute }]}>/ {l.price_unit || 'person'}</Text>
                        </Text>
                      ) : null}
                    </View>
                    <Ionicons name="chevron-forward" size={18} color={colors.textMute} />
                  </TouchableOpacity>
                ))}
              </View>
            )}
          </View>

          {/* Traveler Reviews */}
          <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }, shadow.sm]}>
            <View style={styles.reviewsHeaderRow}>
              <View>
                <Text style={[styles.sectionTitle, { color: colors.text }]}>Traveler Reviews</Text>
                <Text style={[styles.reviewsSub, { color: colors.textMute }]}>
                  {"Reviews from travelers who booked the guide's services."}
                </Text>
              </View>
              {reviews.length > 0 && guide?.rating ? (
                <View style={styles.ratingBadge}>
                  <Text style={[styles.ratingBadgeText, { color: colors.text }]}>
                    ⭐ {guide.rating.toFixed(1)}
                  </Text>
                </View>
              ) : null}
            </View>

            {reviews.length === 0 ? (
              <View style={styles.emptyCardContent}>
                <Ionicons name="chatbubbles-outline" size={32} color={colors.textMute} />
                <Text style={[styles.emptyHint, { color: colors.textMute, marginTop: 6 }]}>
                  No traveler reviews yet for this guide.
                </Text>
              </View>
            ) : (
              <View style={styles.reviewsList}>
                {reviews.map((rev) => (
                  <View key={rev.id} style={[styles.reviewItem, { backgroundColor: colors.bgAlt, borderColor: colors.border }]}>
                    <View style={styles.reviewTopRow}>
                      <View style={styles.reviewerMeta}>
                        {rev.user_avatar ? (
                          <Image source={{ uri: resolveImage(rev.user_avatar) }} style={styles.reviewAvatar} />
                        ) : (
                          <View style={[styles.reviewAvatar, styles.avatarFallback, { backgroundColor: colors.chipBg }]}>
                            <Text style={[styles.reviewInitials, { color: colors.primary }]}>
                              {initials(rev.user_name || 'Traveler')}
                            </Text>
                          </View>
                        )}
                        <View>
                          <Text style={[styles.reviewerName, { color: colors.text }]}>{rev.user_name}</Text>
                          {rev.listing_title ? (
                            <Text style={[styles.reviewListingTitle, { color: colors.textMute }]} numberOfLines={1}>
                              {rev.listing_title}
                            </Text>
                          ) : null}
                        </View>
                      </View>

                      <View style={styles.reviewRatingCol}>
                        <View style={styles.starsRow}>
                          {Array.from({ length: Math.max(1, Math.min(5, rev.rating)) }).map((_, si) => (
                            <Ionicons key={si} name="star" size={13} color="#F6B100" />
                          ))}
                        </View>
                        <Text style={[styles.reviewDate, { color: colors.textMute }]}>
                          {formatReviewDate(rev.created_at)}
                        </Text>
                      </View>
                    </View>

                    {rev.comment ? (
                      <Text style={[styles.reviewComment, { color: colors.textSub }]}>{rev.comment}</Text>
                    ) : null}
                  </View>
                ))}
              </View>
            )}
          </View>
        </ScrollView>
      )}
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1 },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 20 },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 16,
    paddingTop: 8,
    paddingBottom: 10,
  },
  backButton: {
    width: 42,
    height: 42,
    borderRadius: 21,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
  },
  headerTitle: { flex: 1, fontSize: 18, fontWeight: '800', textAlign: 'center', marginHorizontal: 8 },
  scrollContent: { paddingHorizontal: 16, paddingBottom: 40 },
  card: { borderRadius: 20, borderWidth: 1, padding: 18, marginBottom: 16 },
  avatarRow: { flexDirection: 'row', alignItems: 'center', gap: 14, marginBottom: 16 },
  avatarWrap: { width: 72, height: 72 },
  avatar: { width: 72, height: 72, borderRadius: 36, backgroundColor: '#00000011' },
  avatarFallback: { alignItems: 'center', justifyContent: 'center' },
  avatarInitials: { fontSize: 24, fontWeight: '800' },
  onlineDot: {
    position: 'absolute',
    right: 2,
    bottom: 2,
    width: 16,
    height: 16,
    borderRadius: 8,
    backgroundColor: '#22C55E',
    borderWidth: 2,
  },
  headerMeta: { flex: 1 },
  guideName: { fontSize: 20, fontWeight: '800', letterSpacing: -0.3, marginBottom: 6 },
  badgeRow: { flexDirection: 'row', alignItems: 'center', flexWrap: 'wrap', gap: 6, marginBottom: 6 },
  verifiedBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    backgroundColor: '#DCFCE7',
    paddingVertical: 3,
    paddingHorizontal: 8,
    borderRadius: 999,
  },
  verifiedText: { fontSize: 11, fontWeight: '800', color: '#15803D' },
  roleBadge: { paddingVertical: 3, paddingHorizontal: 8, borderRadius: 999 },
  roleText: { fontSize: 11, fontWeight: '800' },
  locationRow: { flexDirection: 'row', alignItems: 'center', gap: 4 },
  locationText: { fontSize: 13, fontWeight: '600' },
  primaryActionBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    paddingVertical: 13,
    borderRadius: 14,
  },
  primaryActionBtnText: { color: '#FFFFFF', fontSize: 15, fontWeight: '800' },
  sectionTitle: { fontSize: 17, fontWeight: '800', marginBottom: 8 },
  bioText: { fontSize: 14, lineHeight: 21, marginBottom: 16 },
  statsGrid: {
    borderRadius: 14,
    borderWidth: 1,
    padding: 14,
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 14,
    justifyContent: 'space-between',
  },
  statCol: { width: '46%' },
  statLabel: { fontSize: 10, fontWeight: '800', letterSpacing: 0.5, marginBottom: 4 },
  statValue: { fontSize: 15, fontWeight: '800' },
  specialtiesWrap: { marginTop: 16 },
  subSectionTitle: { fontSize: 14, fontWeight: '800', marginBottom: 8 },
  tagsContainer: { flexDirection: 'row', flexWrap: 'wrap', gap: 6 },
  specialtyPill: { paddingVertical: 5, paddingHorizontal: 12, borderRadius: 999, borderWidth: 1 },
  specialtyText: { fontSize: 12, fontWeight: '700' },
  emptyHint: { fontSize: 13, fontStyle: 'italic' },
  emptyCardContent: { alignItems: 'center', justifyContent: 'center', paddingVertical: 18 },
  listingsList: { gap: 10 },
  listingItem: {
    flexDirection: 'row',
    alignItems: 'center',
    borderRadius: 14,
    borderWidth: 1,
    padding: 10,
    gap: 12,
  },
  listingThumb: { width: 56, height: 56, borderRadius: 10, backgroundColor: '#00000011' },
  listingInfo: { flex: 1 },
  listingTitle: { fontSize: 14, fontWeight: '800', marginBottom: 2 },
  listingCategory: { fontSize: 12, marginBottom: 4 },
  listingPrice: { fontSize: 13, fontWeight: '800' },
  listingPriceUnit: { fontSize: 11, fontWeight: '600' },
  reviewsHeaderRow: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    justifyContent: 'space-between',
    marginBottom: 12,
  },
  reviewsSub: { fontSize: 12 },
  ratingBadge: { paddingVertical: 4, paddingHorizontal: 8, borderRadius: 8, backgroundColor: '#FEF3C7' },
  ratingBadgeText: { fontSize: 13, fontWeight: '800' },
  reviewsList: { gap: 10 },
  reviewItem: { borderRadius: 14, borderWidth: 1, padding: 12 },
  reviewTopRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 8,
  },
  reviewerMeta: { flexDirection: 'row', alignItems: 'center', gap: 10, flex: 1 },
  reviewAvatar: { width: 34, height: 34, borderRadius: 17, backgroundColor: '#00000011' },
  reviewInitials: { fontSize: 13, fontWeight: '800' },
  reviewerName: { fontSize: 13, fontWeight: '800' },
  reviewListingTitle: { fontSize: 11 },
  reviewRatingCol: { alignItems: 'flex-end', gap: 2 },
  starsRow: { flexDirection: 'row', gap: 2 },
  reviewDate: { fontSize: 11 },
  reviewComment: { fontSize: 13, lineHeight: 18 },
});
