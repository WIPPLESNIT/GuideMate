import { Ionicons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import * as ImagePicker from 'expo-image-picker';
import { useFocusEffect, useRouter } from 'expo-router';
import React, { useCallback, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Image,
  Modal,
  ScrollView,
  StatusBar,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import { AppButton, AppToast } from '../../components/ui';
import { getSession, restoreSession } from '../../lib/authStore';
import { usePreferences } from '../../lib/preferences';
import { useTheme } from '../../lib/theme';
import { apiMe, apiUpdateProfile, apiUploadAvatar, ApiUser, resolveImage } from '../../services/api';

type MenuRow = { icon: keyof typeof Ionicons.glyphMap; label: string; color: string; bg: string; onPress: () => void };

export default function AccountScreen() {
  const router = useRouter();
  const { t } = usePreferences();
  const insets = useSafeAreaInsets();
  const { colors, isDark, radius, shadow, gradients } = useTheme();

  const [user, setUser] = useState<ApiUser | null>(null);
  const [uploading, setUploading] = useState(false);

  const [bioOpen, setBioOpen] = useState(false);
  const [bioDraft, setBioDraft] = useState('');
  const [savingBio, setSavingBio] = useState(false);
  const [toast, setToast] = useState(false);
  const [toastKey, setToastKey] = useState(0);

  const showProfileUpdated = () => {
    setToast(true);
    setToastKey((n) => n + 1);
  };

  useFocusEffect(
    useCallback(() => {
      let active = true;
      (async () => {
        await restoreSession();
        const session = await getSession();
        if (!active) return;
        if (!session) {
          setUser(null);
          return;
        }
        // Show the signed-in profile immediately from the cached session, so a
        // logged-in user never sees the "Register" card just because a refresh
        // call is slow or the network is down.
        setUser((prev) => prev ?? {
          id: session.id,
          name: session.fullName,
          email: session.email,
          role: session.role,
          avatar: session.avatar,
          bio: '',
        });
        try {
          const res = await apiMe();
          if (active) setUser(res.user);
        } catch {
          // Keep the cached session profile shown above.
        }
      })();
      return () => {
        active = false;
      };
    }, [])
  );

  const requireLogin = (action: () => void) => {
    if (!user) {
      Alert.alert(
        'Login required',
        'Please register or log in to your account first to use this feature.',
        [
          { text: 'Cancel', style: 'cancel' },
          { text: 'Register / Log in', onPress: () => router.replace('/(auth)/login') },
        ]
      );
      return;
    }
    action();
  };

  const changePhoto = async () => {
    const perm = await ImagePicker.requestMediaLibraryPermissionsAsync();
    if (!perm.granted) {
      Alert.alert('Permission needed', 'Please allow photo access to update your profile picture.');
      return;
    }
    const result = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ['images'],
      allowsEditing: true,
      aspect: [1, 1],
      quality: 0.7,
    });
    if (result.canceled || !result.assets?.[0]?.uri) return;

    setUploading(true);
    try {
      const res = await apiUploadAvatar(result.assets[0].uri);
      setUser(res.user);
      showProfileUpdated();
    } catch (e) {
      Alert.alert('Upload failed', e instanceof Error ? e.message : 'Please try again.');
    } finally {
      setUploading(false);
    }
  };

  const openBio = () => {
    setBioDraft(user?.bio ?? '');
    setBioOpen(true);
  };

  const saveBio = async () => {
    setSavingBio(true);
    try {
      const res = await apiUpdateProfile({ bio: bioDraft.trim() });
      setUser(res.user);
      setBioOpen(false);
      showProfileUpdated();
    } catch (e) {
      Alert.alert('Could not save', e instanceof Error ? e.message : 'Please try again.');
    } finally {
      setSavingBio(false);
    }
  };

  const menu: MenuRow[] = [
    { icon: 'compass', label: t('explore'), color: '#FF8C00', bg: '#FFF2E6', onPress: () => router.push('/things-to-do') },
    { icon: 'calendar', label: t('bookings'), color: '#3B82F6', bg: '#E6F0FA', onPress: () => requireLogin(() => router.push('/(tabs)/trips')) },
    { icon: 'heart', label: t('saved'), color: '#EF4444', bg: '#FEE2E2', onPress: () => requireLogin(() => router.push('/(tabs)/wishlist')) },
    { icon: 'chatbubbles', label: 'Messages', color: '#6366F1', bg: '#E7E9FE', onPress: () => requireLogin(() => router.push('/messages')) },
    { icon: 'warning', label: 'Emergency SOS', color: '#EF4444', bg: '#FEE2E2', onPress: () => router.push('/sos') },
  ];

  const avatarUri = user?.avatar ? resolveImage(user.avatar) : '';

  return (
    <SafeAreaView style={[styles.container, { backgroundColor: colors.bgAlt }]} edges={['left', 'right']}>
      <StatusBar barStyle={isDark ? 'light-content' : 'dark-content'} />
      <ScrollView showsVerticalScrollIndicator={false} contentContainerStyle={{ paddingBottom: 32 }}>
        <View style={[styles.headerContainer, { paddingTop: insets.top + 16 }]}>
          <Text style={[styles.headerTitle, { color: colors.text }]}>{t('account_title')}</Text>
          <TouchableOpacity
            style={[styles.settingsButton, { backgroundColor: colors.chipBg }]}
            activeOpacity={0.7}
            onPress={() => router.push('/settings')}
          >
            <Ionicons name="settings-outline" size={21} color={colors.text} />
          </TouchableOpacity>
        </View>

        {/* Profile card */}
        {user ? (
          <LinearGradient
            colors={gradients.brand}
            start={{ x: 0, y: 0 }}
            end={{ x: 1, y: 1 }}
            style={[styles.profileCard, shadow.md]}
          >
            <TouchableOpacity style={styles.avatarWrap} activeOpacity={0.85} onPress={changePhoto} disabled={uploading}>
              {avatarUri ? (
                <Image source={{ uri: avatarUri }} style={styles.avatarImg} />
              ) : (
                <View style={styles.avatar}>
                  <Text style={styles.avatarText}>{user.name.trim().charAt(0).toUpperCase() || 'G'}</Text>
                </View>
              )}
              <View style={styles.cameraBadge}>
                {uploading ? (
                  <ActivityIndicator size="small" color="#0B7A4B" />
                ) : (
                  <Ionicons name="camera" size={14} color="#0B7A4B" />
                )}
              </View>
            </TouchableOpacity>

            <Text style={styles.profileName} numberOfLines={1}>{user.name}</Text>
            <Text style={styles.profileEmail} numberOfLines={1}>{user.email}</Text>

            <TouchableOpacity style={styles.bioRow} activeOpacity={0.8} onPress={openBio}>
              <Ionicons name="create-outline" size={14} color="rgba(255,255,255,0.9)" style={{ marginRight: 6 }} />
              <Text style={styles.bioText} numberOfLines={3}>
                {user.bio?.trim() ? user.bio : 'Add a short bio about yourself'}
              </Text>
            </TouchableOpacity>
          </LinearGradient>
        ) : (
          <View style={[styles.guestCard, { backgroundColor: colors.card, borderColor: colors.border }, shadow.sm]}>
            <View style={[styles.guestIcon, { backgroundColor: colors.chipBg }]}>
              <Ionicons name="person" size={30} color={colors.primary} />
            </View>
            <Text style={[styles.welcomeTitle, { color: colors.text }]}>{t('welcome_guest')}</Text>
            <Text style={[styles.welcomeSubtitle, { color: colors.textSub }]}>{t('guest_sub')}</Text>
            <AppButton label="Register" icon="person-add-outline" onPress={() => router.replace('/(auth)/register')} style={{ marginTop: 18, alignSelf: 'stretch' }} />
          </View>
        )}

        {/* Menu */}
        <View style={[styles.menuContainer, { backgroundColor: colors.card, borderColor: colors.border }, shadow.sm]}>
          {menu.map((row, idx) => (
            <TouchableOpacity
              key={row.label}
              style={[styles.menuItem, idx > 0 && { borderTopWidth: 1, borderColor: colors.border }]}
              activeOpacity={0.7}
              onPress={row.onPress}
            >
              <View style={[styles.menuIconTile, { backgroundColor: isDark ? colors.cardAlt : row.bg, borderRadius: radius.sm }]}>
                <Ionicons name={row.icon} size={19} color={row.color} />
              </View>
              <Text style={[styles.menuItemText, { color: colors.text }]}>{row.label}</Text>
              <Ionicons name="chevron-forward" size={18} color={colors.textMute} />
            </TouchableOpacity>
          ))}
        </View>

        <Text style={[styles.footerText, { color: colors.textMute }]}>GuideMate · Cebu, Philippines</Text>
        <Text style={[styles.footerVersion, { color: colors.textMute }]}>Version 1.0.0</Text>
      </ScrollView>

      {/* Bio editor */}
      <Modal visible={bioOpen} transparent animationType="fade" onRequestClose={() => setBioOpen(false)}>
        <View style={styles.modalBackdrop}>
          <View style={[styles.modalCard, { backgroundColor: colors.card }]}>
            <Text style={[styles.modalTitle, { color: colors.text }]}>Edit bio</Text>
            <TextInput
              style={[styles.bioInput, { backgroundColor: colors.bgAlt, color: colors.text }]}
              placeholder="Tell guides a little about yourself..."
              placeholderTextColor={colors.textMute}
              value={bioDraft}
              onChangeText={setBioDraft}
              multiline
              maxLength={300}
            />
            <View style={styles.modalActions}>
              <TouchableOpacity style={styles.modalBtn} onPress={() => setBioOpen(false)} disabled={savingBio}>
                <Text style={[styles.modalBtnText, { color: colors.textSub }]}>Cancel</Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={[styles.modalBtn, { backgroundColor: colors.primary, borderRadius: 10 }, savingBio && { opacity: 0.6 }]}
                onPress={saveBio}
                disabled={savingBio}
              >
                <Text style={[styles.modalBtnText, { color: '#FFFFFF' }]}>{savingBio ? 'Saving...' : 'Save'}</Text>
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>

      <AppToast
        key={toastKey}
        visible={toast}
        title="Profile Updated"
        subtitle="Your changes have been saved."
        top={insets.top + 8}
        onHide={() => setToast(false)}
      />
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1 },
  headerContainer: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: 20, paddingBottom: 16 },
  headerTitle: { fontSize: 28, fontWeight: '800', letterSpacing: -0.5 },
  settingsButton: { width: 42, height: 42, borderRadius: 21, alignItems: 'center', justifyContent: 'center' },
  profileCard: { borderRadius: 24, marginHorizontal: 20, paddingVertical: 26, paddingHorizontal: 20, alignItems: 'center', marginBottom: 18 },
  avatarWrap: { marginBottom: 12 },
  avatar: { width: 84, height: 84, borderRadius: 42, backgroundColor: 'rgba(255,255,255,0.25)', alignItems: 'center', justifyContent: 'center', borderWidth: 2, borderColor: 'rgba(255,255,255,0.5)' },
  avatarImg: { width: 84, height: 84, borderRadius: 42, borderWidth: 2, borderColor: 'rgba(255,255,255,0.6)' },
  avatarText: { color: '#FFFFFF', fontSize: 34, fontWeight: '900' },
  cameraBadge: { position: 'absolute', right: -2, bottom: -2, width: 28, height: 28, borderRadius: 14, backgroundColor: '#FFFFFF', alignItems: 'center', justifyContent: 'center', borderWidth: 2, borderColor: 'rgba(255,255,255,0.8)' },
  profileName: { color: '#FFFFFF', fontSize: 20, fontWeight: '800' },
  profileEmail: { color: 'rgba(255,255,255,0.85)', fontSize: 13, fontWeight: '500', marginTop: 3 },
  bioRow: { flexDirection: 'row', alignItems: 'flex-start', marginTop: 14, paddingHorizontal: 8, maxWidth: '100%' },
  bioText: { color: 'rgba(255,255,255,0.92)', fontSize: 13, lineHeight: 18, flexShrink: 1, textAlign: 'center' },
  guestCard: { borderRadius: 24, marginHorizontal: 20, paddingVertical: 28, paddingHorizontal: 22, alignItems: 'center', marginBottom: 18, borderWidth: 1 },
  guestIcon: { width: 64, height: 64, borderRadius: 32, alignItems: 'center', justifyContent: 'center', marginBottom: 14 },
  welcomeTitle: { fontSize: 19, fontWeight: '800', textAlign: 'center' },
  welcomeSubtitle: { fontSize: 14, textAlign: 'center', marginTop: 6, lineHeight: 20 },
  menuContainer: { borderRadius: 20, marginHorizontal: 20, overflow: 'hidden', borderWidth: 1 },
  menuItem: { flexDirection: 'row', alignItems: 'center', paddingVertical: 13, paddingHorizontal: 14 },
  menuIconTile: { width: 38, height: 38, alignItems: 'center', justifyContent: 'center', marginRight: 14 },
  menuItemText: { fontSize: 15, fontWeight: '700', flex: 1 },
  footerText: { fontSize: 12, fontWeight: '600', textAlign: 'center', marginTop: 28 },
  footerVersion: { fontSize: 11, fontWeight: '500', textAlign: 'center', marginTop: 3 },
  modalBackdrop: { flex: 1, backgroundColor: 'rgba(0,0,0,0.5)', alignItems: 'center', justifyContent: 'center', padding: 28 },
  modalCard: { width: '100%', borderRadius: 18, padding: 20 },
  modalTitle: { fontSize: 18, fontWeight: '800', marginBottom: 14 },
  bioInput: { minHeight: 96, borderRadius: 12, padding: 14, fontSize: 15, textAlignVertical: 'top' },
  modalActions: { flexDirection: 'row', justifyContent: 'flex-end', alignItems: 'center', marginTop: 16 },
  modalBtn: { paddingVertical: 11, paddingHorizontal: 22, marginLeft: 10 },
  modalBtnText: { fontSize: 15, fontWeight: '700' },
});
