import { Ionicons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import { useFocusEffect, useRouter } from 'expo-router';
import React, { useCallback, useState } from 'react';
import { Alert, ScrollView, StatusBar, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import { Chip, ScreenTitle } from '../../components/ui';
import { getSession } from '../../lib/authStore';
import { useTheme } from '../../lib/theme';
import { VOUCHERS, formatVoucherMinimum } from '../../lib/vouchers';
import { ApiVoucher, getVouchers } from '../../services/api';

const LOCATIONS = ['Philippines'];

type SaleVoucher = {
  code: string;
  label: string;
  description: string;
  minSpend: number;
  expires: string;
  used: boolean;
};

function fromLocal(): SaleVoucher[] {
  return VOUCHERS.map((promo) => ({
    code: promo.code,
    label: promo.label,
    description: promo.description,
    minSpend: promo.minSpend,
    expires: promo.expires,
    used: false,
  }));
}

function fromApi(rows: ApiVoucher[]): SaleVoucher[] {
  return rows.map((promo) => ({
    code: promo.code,
    label: promo.label,
    description: promo.description,
    minSpend: promo.min_spend,
    expires: promo.expires,
    used: !!promo.used,
  }));
}

export default function SaleScreen() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { colors, isDark, radius, shadow, gradients } = useTheme();
  const [location, setLocation] = useState('Philippines');
  const [promos, setPromos] = useState<SaleVoucher[]>(fromLocal());

  const loadVouchers = useCallback(async () => {
    try {
      const data = await getVouchers();
      if (Array.isArray(data.vouchers) && data.vouchers.length) {
        setPromos(fromApi(data.vouchers));
        return;
      }
    } catch {
      // Keep the local catalog if the API is unreachable.
    }
    setPromos(fromLocal());
  }, []);

  useFocusEffect(
    useCallback(() => {
      loadVouchers();
    }, [loadVouchers])
  );

  const redeem = async (promo: SaleVoucher) => {
    if (promo.used) {
      Alert.alert('Already used', 'This voucher was already redeemed on your account. Each voucher can only be used once.');
      return;
    }
    const session = await getSession();
    if (!session) {
      Alert.alert('Sign in required', 'Sign in so this voucher can be saved to your account and used once at checkout.', [
        { text: 'Cancel', style: 'cancel' },
        { text: 'Sign in', onPress: () => router.push('/(auth)/login') },
      ]);
      return;
    }
    Alert.alert(
      'Voucher ready',
      `Use code ${promo.code} at checkout. It can only be redeemed once on this account.`,
      [
        { text: 'Browse deals', onPress: () => router.push('/things-to-do') },
        { text: 'Got it', style: 'cancel' },
      ]
    );
  };

  const visible = location === 'Philippines' ? promos : [];

  return (
    <SafeAreaView style={[styles.container, { backgroundColor: colors.bgAlt }]} edges={['left', 'right']}>
      <StatusBar barStyle={isDark ? 'light-content' : 'dark-content'} />
      <ScrollView
        style={{ flex: 1 }}
        contentContainerStyle={{ padding: 20, paddingTop: insets.top + 16, paddingBottom: 32 }}
        showsVerticalScrollIndicator={false}
      >
        <ScreenTitle title="Deals & Offers" subtitle="Save more on your next adventure" />

        <LinearGradient
          colors={gradients.sunset}
          start={{ x: 0, y: 0 }}
          end={{ x: 1, y: 1 }}
          style={[styles.banner, shadow.md]}
        >
          <View style={{ flex: 1 }}>
            <Text style={styles.bannerTag}>LIMITED TIME</Text>
            <Text style={styles.bannerTitle}>Up to 30% OFF{'\n'}Cebu experiences</Text>
            <TouchableOpacity style={styles.bannerBtn} activeOpacity={0.85} onPress={() => router.push('/things-to-do')}>
              <Text style={styles.bannerBtnText}>Explore now</Text>
              <Ionicons name="arrow-forward" size={14} color="#FF5A1F" />
            </TouchableOpacity>
          </View>
          <Ionicons name="pricetags" size={74} color="rgba(255,255,255,0.25)" style={styles.bannerIcon} />
        </LinearGradient>

        <Text style={[styles.sectionLabel, { color: colors.text }]}>Destination</Text>
        <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.chipRow}>
          {LOCATIONS.map((loc) => (
            <Chip key={loc} label={loc} active={location === loc} onPress={() => setLocation(loc)} />
          ))}
        </ScrollView>

        <Text style={[styles.sectionLabel, { color: colors.text }]}>Promo codes for {location}</Text>
        {visible.map((promo) => (
          <View
            key={promo.code}
            style={[
              styles.promoCard,
              { backgroundColor: colors.card, borderColor: colors.border, opacity: promo.used ? 0.72 : 1 },
              shadow.sm,
            ]}
          >
            <View style={[styles.promoBadge, { backgroundColor: isDark ? colors.cardAlt : '#FFF1EC' }]}>
              <Text style={[styles.promoBadgeText, { color: promo.used ? colors.textMute : colors.accent }]}>{promo.label}</Text>
            </View>
            <View style={{ flex: 1 }}>
              <Text style={[styles.promoDesc, { color: colors.text }]}>{promo.description}</Text>
              <Text style={[styles.promoMeta, { color: colors.textSub }]}>{formatVoucherMinimum(promo.minSpend)}</Text>
              <View style={styles.promoCodeRow}>
                <View style={[styles.codePill, { backgroundColor: colors.chipBg, borderColor: colors.border }]}>
                  <Ionicons name="pricetag" size={11} color={promo.used ? colors.textMute : colors.primary} />
                  <Text style={[styles.codeText, { color: colors.text }]}>{promo.code}</Text>
                </View>
                <Text style={[styles.expires, { color: colors.textMute }]}>
                  {promo.used ? 'Already used' : promo.expires}
                </Text>
              </View>
            </View>
            <TouchableOpacity
              style={[
                styles.redeemBtn,
                { backgroundColor: promo.used ? colors.border : colors.primary, borderRadius: radius.pill },
              ]}
              activeOpacity={promo.used ? 1 : 0.85}
              onPress={() => redeem(promo)}
              disabled={promo.used}
            >
              <Text style={[styles.redeemText, promo.used && { color: colors.textMute }]}>
                {promo.used ? 'Used' : 'Redeem'}
              </Text>
            </TouchableOpacity>
          </View>
        ))}
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1 },
  banner: { borderRadius: 22, padding: 20, marginTop: 18, marginBottom: 8, flexDirection: 'row', overflow: 'hidden' },
  bannerTag: { color: 'rgba(255,255,255,0.9)', fontSize: 11, fontWeight: '800', letterSpacing: 1 },
  bannerTitle: { color: '#FFFFFF', fontSize: 22, fontWeight: '900', marginTop: 8, letterSpacing: -0.5 },
  bannerBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    backgroundColor: '#FFFFFF',
    alignSelf: 'flex-start',
    paddingHorizontal: 16,
    paddingVertical: 9,
    borderRadius: 999,
    marginTop: 16,
  },
  bannerBtnText: { color: '#FF5A1F', fontWeight: '800', fontSize: 13 },
  bannerIcon: { position: 'absolute', right: 10, bottom: 6 },
  sectionLabel: { fontSize: 16, fontWeight: '800', marginTop: 22, marginBottom: 12, letterSpacing: -0.3 },
  chipRow: { flexDirection: 'row' },
  promoCard: { flexDirection: 'row', alignItems: 'center', borderRadius: 18, padding: 14, marginBottom: 12, borderWidth: 1 },
  promoBadge: { width: 58, height: 58, borderRadius: 14, alignItems: 'center', justifyContent: 'center', marginRight: 12 },
  promoBadgeText: { fontSize: 13, fontWeight: '900', textAlign: 'center' },
  promoDesc: { fontSize: 14, fontWeight: '800' },
  promoMeta: { fontSize: 12, marginTop: 3, fontWeight: '500' },
  promoCodeRow: { flexDirection: 'row', alignItems: 'center', marginTop: 8, gap: 8 },
  codePill: { flexDirection: 'row', alignItems: 'center', gap: 4, paddingHorizontal: 8, paddingVertical: 4, borderRadius: 8, borderWidth: 1 },
  codeText: { fontSize: 12, fontWeight: '800' },
  expires: { fontSize: 11, fontWeight: '500' },
  redeemBtn: { paddingVertical: 9, paddingHorizontal: 16, marginLeft: 10 },
  redeemText: { color: '#FFFFFF', fontWeight: '800', fontSize: 13 },
});
