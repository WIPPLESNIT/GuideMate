import AsyncStorage from '@react-native-async-storage/async-storage';
import { Ionicons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import React, { useEffect, useState } from 'react';
import {
    ScrollView,
    StatusBar,
    StyleSheet,
    Switch,
    Text,
    TouchableOpacity,
    useColorScheme,
    View,
} from 'react-native';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';

const STORAGE_KEY = 'guidemate.notif.prefs';

type Prefs = {
  push: boolean;
  bookings: boolean;
  messages: boolean;
  promotions: boolean;
  tips: boolean;
};

const DEFAULT_PREFS: Prefs = {
  push: true,
  bookings: true,
  messages: true,
  promotions: false,
  tips: true,
};

const ITEMS: { key: keyof Prefs; title: string; desc: string; master?: boolean }[] = [
  { key: 'push', title: 'Push notifications', desc: 'Turn all notifications on or off.', master: true },
  { key: 'bookings', title: 'Booking updates', desc: 'Confirmations, reminders and status changes.' },
  { key: 'messages', title: 'Messages', desc: 'New chat messages from guides and hosts.' },
  { key: 'promotions', title: 'Promotions & offers', desc: 'Discounts, deals and seasonal offers.' },
  { key: 'tips', title: 'Travel tips', desc: 'Handy suggestions for your Cebu trip.' },
];

export default function NotificationSettingsScreen() {
  const router = useRouter();
  const isDark = useColorScheme() === 'dark';
  const insets = useSafeAreaInsets();

  const [prefs, setPrefs] = useState<Prefs>(DEFAULT_PREFS);

  useEffect(() => {
    AsyncStorage.getItem(STORAGE_KEY).then((raw) => {
      if (raw) {
        try {
          setPrefs({ ...DEFAULT_PREFS, ...JSON.parse(raw) });
        } catch {
          // keep defaults
        }
      }
    });
  }, []);

  const persist = (next: Prefs) => {
    setPrefs(next);
    AsyncStorage.setItem(STORAGE_KEY, JSON.stringify(next)).catch(() => {});
  };

  const toggle = (key: keyof Prefs) => {
    if (key === 'push') {
      const on = !prefs.push;
      // Master switch: turning push off disables everything visually.
      persist({ ...prefs, push: on });
      return;
    }
    persist({ ...prefs, [key]: !prefs[key] });
  };

  const theme = {
    bg: isDark ? '#0F1012' : '#F2F3F5',
    card: isDark ? '#1E2029' : '#FFFFFF',
    border: isDark ? '#2A2D38' : '#ECECEC',
    textMain: isDark ? '#FFFFFF' : '#1A1A1A',
    textSub: isDark ? '#9CA3AF' : '#8A8A8A',
    accent: '#22C55E',
  };

  return (
    <SafeAreaView style={[styles.container, { backgroundColor: theme.bg }]} edges={['left', 'right']}>
      <StatusBar barStyle={isDark ? 'light-content' : 'dark-content'} backgroundColor={theme.card} />

      <View style={[styles.header, { backgroundColor: theme.card, borderBottomColor: theme.border, paddingTop: insets.top + 12 }]}>
        <TouchableOpacity onPress={() => router.back()} style={styles.headerBack} activeOpacity={0.7}>
          <Ionicons name="chevron-back" size={26} color={theme.textMain} />
        </TouchableOpacity>
        <Text style={[styles.headerTitle, { color: theme.textMain }]}>Notification settings</Text>
        <View style={styles.headerBack} />
      </View>

      <ScrollView contentContainerStyle={{ paddingVertical: 18 }} showsVerticalScrollIndicator={false}>
        <View style={[styles.group, { backgroundColor: theme.card }]}>
          {ITEMS.map((item, idx) => {
            const disabled = !item.master && !prefs.push;
            const value = item.master ? prefs.push : prefs.push && prefs[item.key];
            return (
              <View
                key={item.key}
                style={[
                  styles.row,
                  idx !== 0 && { borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: theme.border },
                  disabled && { opacity: 0.45 },
                ]}
              >
                <View style={styles.rowText}>
                  <Text style={[styles.rowTitle, { color: theme.textMain }]}>{item.title}</Text>
                  <Text style={[styles.rowDesc, { color: theme.textSub }]}>{item.desc}</Text>
                </View>
                <Switch
                  value={value}
                  onValueChange={() => toggle(item.key)}
                  disabled={disabled}
                  trackColor={{ false: isDark ? '#3A3D48' : '#D1D5DB', true: theme.accent }}
                  thumbColor="#FFFFFF"
                  ios_backgroundColor={isDark ? '#3A3D48' : '#D1D5DB'}
                />
              </View>
            );
          })}
        </View>

        <Text style={[styles.footnote, { color: theme.textSub }]}>
          These preferences control which alerts GuideMate shows you. You can change them anytime.
        </Text>
      </ScrollView>
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
  group: {},
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 20,
    paddingVertical: 16,
  },
  rowText: { flex: 1, paddingRight: 14 },
  rowTitle: { fontSize: 16, fontWeight: '600' },
  rowDesc: { fontSize: 13, marginTop: 3, lineHeight: 18 },
  footnote: { fontSize: 12.5, lineHeight: 18, paddingHorizontal: 20, marginTop: 16 },
});
