import { Ionicons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import React, { useCallback, useEffect, useState } from 'react';
import {
  ActivityIndicator,
  RefreshControl,
  ScrollView,
  StatusBar,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import { useTheme } from '../lib/theme';
import {
  fetchCebuWeather,
  weatherLook,
  weekdayLabel,
  WEATHER_PLACES,
  WeatherPlace,
  WeatherSnapshot,
} from '../lib/weather';

function Stat({
  icon,
  label,
  value,
  colors,
}: {
  icon: keyof typeof Ionicons.glyphMap;
  label: string;
  value: string;
  colors: ReturnType<typeof useTheme>['colors'];
}) {
  return (
    <View style={[styles.stat, { backgroundColor: colors.cardAlt, borderColor: colors.border }]}>
      <Ionicons name={icon} size={16} color={colors.primary} />
      <Text style={[styles.statValue, { color: colors.text }]}>{value}</Text>
      <Text style={[styles.statLabel, { color: colors.textMute }]}>{label}</Text>
    </View>
  );
}

export default function WeatherScreen() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { colors, isDark, shadow } = useTheme();

  const [place, setPlace] = useState<WeatherPlace>(WEATHER_PLACES[0]);
  const [data, setData] = useState<WeatherSnapshot | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState('');

  const load = useCallback(async (selected: WeatherPlace) => {
    setError('');
    try {
      const snapshot = await fetchCebuWeather(selected);
      setData(snapshot);
    } catch {
      setData(null);
      setError('Could not load the forecast. Check your connection and try again.');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useEffect(() => {
    setLoading(true);
    load(place);
  }, [place, load]);

  const look = data ? weatherLook(data.current.code, data.current.isDay) : null;

  return (
    <SafeAreaView style={[styles.container, { backgroundColor: colors.bgAlt }]} edges={['left', 'right']}>
      <StatusBar barStyle={isDark ? 'light-content' : 'dark-content'} />

      <View style={[styles.header, { backgroundColor: colors.card, borderBottomColor: colors.border, paddingTop: insets.top + 10 }]}>
        <TouchableOpacity onPress={() => router.back()} style={styles.headerBtn} activeOpacity={0.7}>
          <Ionicons name="chevron-back" size={26} color={colors.text} />
        </TouchableOpacity>
        <Text style={[styles.headerTitle, { color: colors.text }]}>Cebu Weather</Text>
        <View style={styles.headerBtn} />
      </View>

      <ScrollView
        contentContainerStyle={styles.content}
        showsVerticalScrollIndicator={false}
        refreshControl={
          <RefreshControl
            refreshing={refreshing}
            onRefresh={() => {
              setRefreshing(true);
              load(place);
            }}
            tintColor={colors.primary}
            colors={[colors.primary]}
          />
        }
      >
        <Text style={[styles.intro, { color: colors.textSub }]}>
          Live forecast for popular trip spots. No sign-in needed — pull to refresh.
        </Text>

        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.placeRow}>
          {WEATHER_PLACES.map((item) => {
            const active = item.id === place.id;
            return (
              <TouchableOpacity
                key={item.id}
                onPress={() => setPlace(item)}
                activeOpacity={0.8}
                style={[
                  styles.placeChip,
                  {
                    backgroundColor: active ? colors.primary : colors.card,
                    borderColor: active ? colors.primary : colors.border,
                  },
                ]}
              >
                <Text style={[styles.placeName, { color: active ? '#FFFFFF' : colors.text }]}>{item.name}</Text>
                <Text style={[styles.placeArea, { color: active ? 'rgba(255,255,255,0.85)' : colors.textMute }]}>{item.area}</Text>
              </TouchableOpacity>
            );
          })}
        </ScrollView>

        {loading && !data ? (
          <View style={styles.center}>
            <ActivityIndicator size="large" color={colors.primary} />
          </View>
        ) : error ? (
          <View style={[styles.errorCard, { backgroundColor: colors.card, borderColor: colors.border }, shadow.sm]}>
            <Ionicons name="cloud-offline-outline" size={36} color={colors.textMute} />
            <Text style={[styles.errorTitle, { color: colors.text }]}>Forecast unavailable</Text>
            <Text style={[styles.errorBody, { color: colors.textSub }]}>{error}</Text>
            <TouchableOpacity
              style={[styles.retryBtn, { backgroundColor: colors.primary }]}
              onPress={() => {
                setLoading(true);
                load(place);
              }}
            >
              <Text style={styles.retryText}>Try again</Text>
            </TouchableOpacity>
          </View>
        ) : data && look ? (
          <>
            <View style={[styles.hero, { backgroundColor: colors.card, borderColor: colors.border }, shadow.sm]}>
              <View style={styles.heroTop}>
                <View style={{ flex: 1 }}>
                  <Text style={[styles.heroPlace, { color: colors.textMute }]}>{place.name}</Text>
                  <Text style={[styles.heroTemp, { color: colors.text }]}>{data.current.temp}°</Text>
                  <Text style={[styles.heroLabel, { color: colors.text }]}>{look.label}</Text>
                </View>
                <View style={[styles.heroIcon, { backgroundColor: colors.cardAlt }]}>
                  <Ionicons name={look.icon} size={42} color={colors.primary} />
                </View>
              </View>
              <Text style={[styles.heroTip, { color: colors.textSub }]}>{look.tip}</Text>
            </View>

            <View style={styles.statsRow}>
              <Stat icon="thermometer-outline" label="Feels like" value={`${data.current.feelsLike}°`} colors={colors} />
              <Stat icon="water-outline" label="Humidity" value={`${data.current.humidity}%`} colors={colors} />
              <Stat icon="flag-outline" label="Wind" value={`${data.current.windKmh} km/h`} colors={colors} />
            </View>

            <Text style={[styles.sectionTitle, { color: colors.text }]}>Next 7 days</Text>
            <View style={[styles.weekCard, { backgroundColor: colors.card, borderColor: colors.border }]}>
              {data.daily.map((day, i) => {
                const dayLook = weatherLook(day.code, true);
                return (
                  <View
                    key={day.date}
                    style={[
                      styles.dayRow,
                      i < data.daily.length - 1 && { borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: colors.border },
                    ]}
                  >
                    <Text style={[styles.dayName, { color: colors.text }]}>{weekdayLabel(day.date)}</Text>
                    <View style={styles.dayMid}>
                      <Ionicons name={dayLook.icon} size={18} color={colors.primary} />
                      <Text style={[styles.dayRain, { color: colors.textMute }]}>{day.rainChance}%</Text>
                    </View>
                    <Text style={[styles.dayTemp, { color: colors.text }]}>
                      {day.high}° <Text style={{ color: colors.textMute }}>/ {day.low}°</Text>
                    </Text>
                  </View>
                );
              })}
            </View>
          </>
        ) : null}
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1 },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 8,
    paddingBottom: 10,
    borderBottomWidth: StyleSheet.hairlineWidth,
  },
  headerBtn: { width: 42, height: 42, alignItems: 'center', justifyContent: 'center' },
  headerTitle: { flex: 1, textAlign: 'center', fontSize: 17, fontWeight: '800' },
  content: { padding: 16, paddingBottom: 36 },
  intro: { fontSize: 13, lineHeight: 19, marginBottom: 14 },
  placeRow: { paddingBottom: 8, gap: 8 },
  placeChip: { borderWidth: 1, borderRadius: 16, paddingVertical: 10, paddingHorizontal: 14, marginRight: 8 },
  placeName: { fontSize: 14, fontWeight: '800' },
  placeArea: { fontSize: 11, fontWeight: '600', marginTop: 2 },
  center: { paddingVertical: 60, alignItems: 'center' },
  hero: { borderRadius: 22, borderWidth: 1, padding: 18, marginTop: 10 },
  heroTop: { flexDirection: 'row', alignItems: 'center' },
  heroPlace: { fontSize: 13, fontWeight: '700', textTransform: 'uppercase', letterSpacing: 0.4 },
  heroTemp: { fontSize: 52, fontWeight: '900', letterSpacing: -2, marginTop: 2 },
  heroLabel: { fontSize: 16, fontWeight: '700', marginTop: 2 },
  heroIcon: { width: 76, height: 76, borderRadius: 38, alignItems: 'center', justifyContent: 'center' },
  heroTip: { fontSize: 14, lineHeight: 20, marginTop: 14, fontWeight: '600' },
  statsRow: { flexDirection: 'row', gap: 8, marginTop: 12 },
  stat: { flex: 1, borderRadius: 16, borderWidth: 1, paddingVertical: 12, paddingHorizontal: 8, alignItems: 'center' },
  statValue: { fontSize: 15, fontWeight: '800', marginTop: 6 },
  statLabel: { fontSize: 11, fontWeight: '600', marginTop: 2 },
  sectionTitle: { fontSize: 16, fontWeight: '800', marginTop: 22, marginBottom: 10 },
  weekCard: { borderRadius: 18, borderWidth: 1, overflow: 'hidden' },
  dayRow: { flexDirection: 'row', alignItems: 'center', paddingHorizontal: 14, paddingVertical: 12 },
  dayName: { width: 72, fontSize: 14, fontWeight: '700' },
  dayMid: { flex: 1, flexDirection: 'row', alignItems: 'center', gap: 8 },
  dayRain: { fontSize: 12, fontWeight: '700' },
  dayTemp: { fontSize: 14, fontWeight: '800' },
  errorCard: { marginTop: 20, borderRadius: 20, borderWidth: 1, padding: 24, alignItems: 'center' },
  errorTitle: { fontSize: 16, fontWeight: '800', marginTop: 12 },
  errorBody: { fontSize: 13, textAlign: 'center', marginTop: 6, lineHeight: 19 },
  retryBtn: { marginTop: 14, borderRadius: 999, paddingVertical: 10, paddingHorizontal: 18 },
  retryText: { color: '#FFFFFF', fontWeight: '800' },
});
