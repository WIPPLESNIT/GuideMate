import { Ionicons } from '@expo/vector-icons';
import Constants from 'expo-constants';
import * as Linking from 'expo-linking';
import { useRouter } from 'expo-router';
import React from 'react';
import {
    Alert,
    ScrollView,
    StatusBar,
    StyleSheet,
    Text,
    TouchableOpacity,
    useColorScheme,
    View,
} from 'react-native';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import { API_BASE_URL } from '../config';

const SUPPORT_EMAIL = 'support@guidemate.app';
// Point at our own GuideMate web backend. API_BASE_URL already resolves to the
// reachable host (localhost in dev, LAN IP on a real device), e.g.
// http://localhost/GuideMate/public
const WEBSITE = API_BASE_URL;

export default function AboutScreen() {
  const router = useRouter();
  const isDark = useColorScheme() === 'dark';
  const insets = useSafeAreaInsets();

  const version = Constants.expoConfig?.version ?? '1.0.0';

  const theme = {
    bg: isDark ? '#0F1012' : '#F2F3F5',
    card: isDark ? '#1E2029' : '#FFFFFF',
    border: isDark ? '#2A2D38' : '#ECECEC',
    textMain: isDark ? '#FFFFFF' : '#1A1A1A',
    textSub: isDark ? '#9CA3AF' : '#8A8A8A',
    accent: '#22C55E',
    iconBg: isDark ? '#15161A' : '#F1F5F9',
  };

  const open = async (url: string) => {
    try {
      const ok = await Linking.canOpenURL(url);
      if (ok) {
        await Linking.openURL(url);
      } else {
        Alert.alert('Unavailable', 'No app is available to open this link.');
      }
    } catch {
      Alert.alert('Unavailable', 'Could not open the link.');
    }
  };

  const Row = ({
    icon,
    label,
    value,
    onPress,
    first,
  }: {
    icon: keyof typeof Ionicons.glyphMap;
    label: string;
    value?: string;
    onPress?: () => void;
    first?: boolean;
  }) => (
    <TouchableOpacity
      style={[styles.row, !first && { borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: theme.border }]}
      activeOpacity={onPress ? 0.6 : 1}
      onPress={onPress}
    >
      <View style={[styles.rowIcon, { backgroundColor: theme.iconBg }]}>
        <Ionicons name={icon} size={18} color={theme.accent} />
      </View>
      <Text style={[styles.rowLabel, { color: theme.textMain }]}>{label}</Text>
      <View style={styles.rowRight}>
        {value ? <Text style={[styles.rowValue, { color: theme.textSub }]}>{value}</Text> : null}
        {onPress ? <Ionicons name="chevron-forward" size={18} color={theme.textSub} /> : null}
      </View>
    </TouchableOpacity>
  );

  return (
    <SafeAreaView style={[styles.container, { backgroundColor: theme.bg }]} edges={['left', 'right']}>
      <StatusBar barStyle={isDark ? 'light-content' : 'dark-content'} backgroundColor={theme.card} />

      <View style={[styles.header, { backgroundColor: theme.card, borderBottomColor: theme.border, paddingTop: insets.top + 12 }]}>
        <TouchableOpacity onPress={() => router.back()} style={styles.headerBack} activeOpacity={0.7}>
          <Ionicons name="chevron-back" size={26} color={theme.textMain} />
        </TouchableOpacity>
        <Text style={[styles.headerTitle, { color: theme.textMain }]}>About</Text>
        <View style={styles.headerBack} />
      </View>

      <ScrollView contentContainerStyle={{ paddingBottom: 40 }} showsVerticalScrollIndicator={false}>
        <View style={styles.hero}>
          <View style={[styles.logo, { backgroundColor: theme.accent }]}>
            <Ionicons name="compass" size={44} color="#FFFFFF" />
          </View>
          <Text style={[styles.appName, { color: theme.textMain }]}>GuideMate</Text>
          <Text style={[styles.tagline, { color: theme.textSub }]}>Your travel companion for Cebu</Text>
          <Text style={[styles.version, { color: theme.textSub }]}>Version {version}</Text>
        </View>

        <Text style={[styles.desc, { color: theme.textSub }]}>
          GuideMate helps travelers discover experiences, book trusted local guides, rent vehicles,
          and explore Cebu with confidence.
        </Text>

        <View style={[styles.group, { backgroundColor: theme.card }]}>
          <Row icon="chatbubble-ellipses-outline" label="Leave feedback" first onPress={() => router.push('/feedback')} />
          <Row icon="mail-outline" label="Contact support" onPress={() => open(`mailto:${SUPPORT_EMAIL}`)} />
          <Row icon="globe-outline" label="Visit website" onPress={() => open(`${WEBSITE}/`)} />
        </View>

        <View style={[styles.group, { backgroundColor: theme.card, marginTop: 16 }]}>
          <Row icon="document-text-outline" label="Terms of Service" first onPress={() => open(`${WEBSITE}/terms`)} />
          <Row icon="shield-checkmark-outline" label="Privacy Policy" onPress={() => open(`${WEBSITE}/privacy`)} />
        </View>

        <Text style={[styles.copyright, { color: theme.textSub }]}>
          © {new Date().getFullYear()} GuideMate. All rights reserved.
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
  hero: { alignItems: 'center', paddingTop: 32, paddingBottom: 20 },
  logo: {
    width: 88,
    height: 88,
    borderRadius: 24,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 16,
  },
  appName: { fontSize: 26, fontWeight: '800', letterSpacing: 0.5 },
  tagline: { fontSize: 14, marginTop: 4 },
  version: { fontSize: 13, marginTop: 10 },
  desc: { fontSize: 14, lineHeight: 21, paddingHorizontal: 24, textAlign: 'center', marginBottom: 22 },
  group: {},
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 18,
    paddingVertical: 15,
  },
  rowIcon: {
    width: 34,
    height: 34,
    borderRadius: 17,
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 14,
  },
  rowLabel: { flex: 1, fontSize: 15.5, fontWeight: '500' },
  rowRight: { flexDirection: 'row', alignItems: 'center' },
  rowValue: { fontSize: 14, marginRight: 6 },
  copyright: { fontSize: 12.5, textAlign: 'center', marginTop: 28 },
});
