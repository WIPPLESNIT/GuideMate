import { Ionicons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import * as ImagePicker from 'expo-image-picker';
import { useFocusEffect, useRouter } from 'expo-router';
import React, { useCallback, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Image,
  KeyboardAvoidingView,
  Modal,
  Platform,
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

  // Edit Profile Modal State
  const [editOpen, setEditOpen] = useState(false);
  const [nameDraft, setNameDraft] = useState('');
  const [phoneDraft, setPhoneDraft] = useState('');
  const [locationDraft, setLocationDraft] = useState('');
  const [experienceDraft, setExperienceDraft] = useState('');
  const [languagesDraft, setLanguagesDraft] = useState('');
  const [specialtiesDraft, setSpecialtiesDraft] = useState('');
  const [bioDraft, setBioDraft] = useState('');
  const [saving, setSaving] = useState(false);
  const [editError, setEditError] = useState('');

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
        // Show the signed-in profile immediately from the cached session
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
          // Keep the cached session profile
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

  const openEdit = () => {
    if (!user) return;
    setNameDraft(user.name || '');
    setPhoneDraft(user.phone || '');
    setLocationDraft(user.location || '');
    setExperienceDraft(user.experience || '');
    setLanguagesDraft(user.languages || '');
    setSpecialtiesDraft(user.specialties || '');
    setBioDraft(user.bio || '');
    setEditError('');
    setEditOpen(true);
  };

  const saveProfile = async () => {
    if (!nameDraft.trim()) {
      setEditError('Full name cannot be empty.');
      return;
    }
    setSaving(true);
    setEditError('');
    try {
      const res = await apiUpdateProfile({
        name: nameDraft.trim(),
        phone: phoneDraft.trim(),
        location: locationDraft.trim(),
        experience: experienceDraft.trim(),
        languages: languagesDraft.trim(),
        specialties: specialtiesDraft.trim(),
        bio: bioDraft.trim(),
      });
      setUser(res.user);
      setEditOpen(false);
      showProfileUpdated();
    } catch (e) {
      setEditError(e instanceof Error ? e.message : 'Failed to save changes. Please try again.');
    } finally {
      setSaving(false);
    }
  };

  const isGuide = user?.role === 'guide' || user?.role === 'provider';
  const isVerified = (user?.guide_status === 'approved') || !emptyValue(user?.is_verified);

  function emptyValue(val: any): boolean {
    return !val || val === '0' || val === 0;
  }

  const specialtiesList = (user?.specialties || '')
    .split(',')
    .map((s) => s.trim())
    .filter(Boolean);

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

            {/* Badges for Guides */}
            {isGuide ? (
              <View style={styles.badgeRow}>
                {isVerified ? (
                  <View style={styles.verifiedBadge}>
                    <Ionicons name="checkmark-circle" size={12} color="#15803D" />
                    <Text style={styles.verifiedText}>Verified Guide</Text>
                  </View>
                ) : null}
                <View style={styles.roleBadge}>
                  <Text style={styles.roleText}>Tour Guide</Text>
                </View>
                {user.location ? (
                  <View style={styles.locationBadge}>
                    <Ionicons name="location-sharp" size={12} color="#FFFFFF" />
                    <Text style={styles.locationBadgeText}>{user.location}</Text>
                  </View>
                ) : null}
              </View>
            ) : null}

            {/* Guide Meta Details */}
            {isGuide && (user.experience || user.languages) ? (
              <View style={styles.guideMetaBox}>
                {user.experience ? (
                  <Text style={styles.guideMetaItem}>
                    <Text style={styles.guideMetaKey}>Experience: </Text>
                    {user.experience}
                  </Text>
                ) : null}
                {user.languages ? (
                  <Text style={styles.guideMetaItem}>
                    <Text style={styles.guideMetaKey}>Languages: </Text>
                    {user.languages}
                  </Text>
                ) : null}
              </View>
            ) : null}

            {/* Specialties Pills */}
            {isGuide && specialtiesList.length > 0 ? (
              <View style={styles.specialtiesRow}>
                {specialtiesList.map((spec, si) => (
                  <View key={si} style={styles.specialtyChip}>
                    <Text style={styles.specialtyChipText}>{spec}</Text>
                  </View>
                ))}
              </View>
            ) : null}

            {/* Bio row */}
            <View style={styles.bioRow}>
              <Text style={styles.bioText} numberOfLines={3}>
                {user.bio?.trim() ? user.bio : 'Add a short bio about yourself to introduce who you are to travelers.'}
              </Text>
            </View>

            {/* Profile Action Buttons */}
            <View style={styles.profileActionsRow}>
              <TouchableOpacity
                style={styles.profileEditBtn}
                activeOpacity={0.85}
                onPress={openEdit}
              >
                <Ionicons name="create-outline" size={15} color="#FFFFFF" />
                <Text style={styles.profileEditBtnText}>Edit Profile</Text>
              </TouchableOpacity>

              {isGuide ? (
                <TouchableOpacity
                  style={styles.publicProfileBtn}
                  activeOpacity={0.85}
                  onPress={() => router.push(`/guide/${user.id}` as any)}
                >
                  <Ionicons name="eye-outline" size={15} color="#0B7A4B" />
                  <Text style={styles.publicProfileBtnText}>View Public Profile</Text>
                </TouchableOpacity>
              ) : null}
            </View>
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

      {/* ── Comprehensive Edit Profile Modal ── */}
      <Modal visible={editOpen} transparent animationType="slide" onRequestClose={() => setEditOpen(false)}>
        <KeyboardAvoidingView
          behavior={Platform.OS === 'ios' ? 'padding' : undefined}
          style={styles.modalBackdrop}
        >
          <View style={[styles.modalCard, { backgroundColor: colors.card, borderColor: colors.border }]}>
            <View style={styles.modalHeaderRow}>
              <View>
                <Text style={[styles.modalTitle, { color: colors.text }]}>Edit Profile</Text>
                <Text style={[styles.modalSub, { color: colors.textSub }]}>
                  {isGuide ? 'Update your personal details and public guide info.' : 'Update your personal details and bio.'}
                </Text>
              </View>
              <TouchableOpacity onPress={() => setEditOpen(false)} style={styles.modalCloseBtn}>
                <Ionicons name="close" size={20} color={colors.text} />
              </TouchableOpacity>
            </View>

            {editError ? (
              <View style={styles.errorBanner}>
                <Ionicons name="alert-circle" size={16} color="#DC2626" />
                <Text style={styles.errorBannerText}>{editError}</Text>
              </View>
            ) : null}

            <ScrollView showsVerticalScrollIndicator={false} style={{ maxHeight: 420 }} contentContainerStyle={{ paddingVertical: 4 }}>
              {/* Full Name */}
              <View style={styles.inputGroup}>
                <Text style={[styles.inputLabel, { color: colors.text }]}>
                  Full Name <Text style={{ color: '#EF4444' }}>*</Text>
                </Text>
                <TextInput
                  style={[styles.textInput, { backgroundColor: colors.bgAlt, color: colors.text, borderColor: colors.border }]}
                  placeholder="Your full name"
                  placeholderTextColor={colors.textMute}
                  value={nameDraft}
                  onChangeText={setNameDraft}
                />
              </View>

              {/* Phone & Location */}
              <View style={styles.inputRow}>
                <View style={[styles.inputGroup, { flex: 1 }]}>
                  <Text style={[styles.inputLabel, { color: colors.text }]}>Phone Number</Text>
                  <TextInput
                    style={[styles.textInput, { backgroundColor: colors.bgAlt, color: colors.text, borderColor: colors.border }]}
                    placeholder="e.g. +63 912 345 6789"
                    placeholderTextColor={colors.textMute}
                    value={phoneDraft}
                    onChangeText={setPhoneDraft}
                    keyboardType="phone-pad"
                  />
                </View>
                <View style={[styles.inputGroup, { flex: 1 }]}>
                  <Text style={[styles.inputLabel, { color: colors.text }]}>Location</Text>
                  <TextInput
                    style={[styles.textInput, { backgroundColor: colors.bgAlt, color: colors.text, borderColor: colors.border }]}
                    placeholder="e.g. Moalboal, Cebu"
                    placeholderTextColor={colors.textMute}
                    value={locationDraft}
                    onChangeText={setLocationDraft}
                  />
                </View>
              </View>

              {/* Guide-Specific Fields */}
              {isGuide ? (
                <>
                  <View style={styles.inputRow}>
                    <View style={[styles.inputGroup, { flex: 1 }]}>
                      <Text style={[styles.inputLabel, { color: colors.text }]}>Experience</Text>
                      <TextInput
                        style={[styles.textInput, { backgroundColor: colors.bgAlt, color: colors.text, borderColor: colors.border }]}
                        placeholder="e.g. 3+ Years"
                        placeholderTextColor={colors.textMute}
                        value={experienceDraft}
                        onChangeText={setExperienceDraft}
                      />
                    </View>
                    <View style={[styles.inputGroup, { flex: 1 }]}>
                      <Text style={[styles.inputLabel, { color: colors.text }]}>Languages</Text>
                      <TextInput
                        style={[styles.textInput, { backgroundColor: colors.bgAlt, color: colors.text, borderColor: colors.border }]}
                        placeholder="e.g. English, Cebuano"
                        placeholderTextColor={colors.textMute}
                        value={languagesDraft}
                        onChangeText={setLanguagesDraft}
                      />
                    </View>
                  </View>

                  <View style={styles.inputGroup}>
                    <Text style={[styles.inputLabel, { color: colors.text }]}>Specialties (comma-separated)</Text>
                    <TextInput
                      style={[styles.textInput, { backgroundColor: colors.bgAlt, color: colors.text, borderColor: colors.border }]}
                      placeholder="e.g. Snorkeling, Island Tours, Local Dining, Adventure Tours"
                      placeholderTextColor={colors.textMute}
                      value={specialtiesDraft}
                      onChangeText={setSpecialtiesDraft}
                    />
                  </View>
                </>
              ) : null}

              {/* Bio */}
              <View style={styles.inputGroup}>
                <Text style={[styles.inputLabel, { color: colors.text }]}>
                  {isGuide ? 'About you (shown to travelers)' : 'Bio'}
                </Text>
                <TextInput
                  style={[styles.bioInput, { backgroundColor: colors.bgAlt, color: colors.text, borderColor: colors.border }]}
                  placeholder={isGuide ? 'Describe your guiding background, local secrets, and what travelers can look forward to...' : 'Tell travelers and guides a little about yourself...'}
                  placeholderTextColor={colors.textMute}
                  value={bioDraft}
                  onChangeText={setBioDraft}
                  multiline
                  maxLength={1000}
                />
              </View>
            </ScrollView>

            <View style={styles.modalActions}>
              <TouchableOpacity
                style={[styles.modalBtn, { backgroundColor: colors.bgAlt }]}
                onPress={() => setEditOpen(false)}
                disabled={saving}
              >
                <Text style={[styles.modalBtnText, { color: colors.textSub }]}>Cancel</Text>
              </TouchableOpacity>

              <TouchableOpacity
                style={[styles.modalBtn, { backgroundColor: colors.primary, borderRadius: 12 }, saving && { opacity: 0.6 }]}
                onPress={saveProfile}
                disabled={saving}
              >
                {saving ? (
                  <ActivityIndicator size="small" color="#FFFFFF" />
                ) : (
                  <Text style={[styles.modalBtnText, { color: '#FFFFFF' }]}>Save Changes</Text>
                )}
              </TouchableOpacity>
            </View>
          </View>
        </KeyboardAvoidingView>
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
  profileCard: { borderRadius: 24, marginHorizontal: 20, paddingVertical: 24, paddingHorizontal: 20, alignItems: 'center', marginBottom: 18 },
  avatarWrap: { marginBottom: 10 },
  avatar: { width: 84, height: 84, borderRadius: 42, backgroundColor: 'rgba(255,255,255,0.25)', alignItems: 'center', justifyContent: 'center', borderWidth: 2, borderColor: 'rgba(255,255,255,0.5)' },
  avatarImg: { width: 84, height: 84, borderRadius: 42, borderWidth: 2, borderColor: 'rgba(255,255,255,0.6)' },
  avatarText: { color: '#FFFFFF', fontSize: 34, fontWeight: '900' },
  cameraBadge: { position: 'absolute', right: -2, bottom: -2, width: 28, height: 28, borderRadius: 14, backgroundColor: '#FFFFFF', alignItems: 'center', justifyContent: 'center', borderWidth: 2, borderColor: 'rgba(255,255,255,0.8)' },
  profileName: { color: '#FFFFFF', fontSize: 20, fontWeight: '800' },
  profileEmail: { color: 'rgba(255,255,255,0.85)', fontSize: 13, fontWeight: '500', marginTop: 3 },
  badgeRow: { flexDirection: 'row', alignItems: 'center', flexWrap: 'wrap', gap: 6, marginTop: 10 },
  verifiedBadge: { flexDirection: 'row', alignItems: 'center', gap: 4, backgroundColor: '#DCFCE7', paddingVertical: 3, paddingHorizontal: 8, borderRadius: 999 },
  verifiedText: { fontSize: 11, fontWeight: '800', color: '#15803D' },
  roleBadge: { backgroundColor: 'rgba(255,255,255,0.25)', paddingVertical: 3, paddingHorizontal: 8, borderRadius: 999 },
  roleText: { fontSize: 11, fontWeight: '800', color: '#FFFFFF' },
  locationBadge: { flexDirection: 'row', alignItems: 'center', gap: 3, backgroundColor: 'rgba(0,0,0,0.18)', paddingVertical: 3, paddingHorizontal: 8, borderRadius: 999 },
  locationBadgeText: { fontSize: 11, fontWeight: '600', color: '#FFFFFF' },
  guideMetaBox: { flexDirection: 'row', flexWrap: 'wrap', gap: 12, marginTop: 10, paddingVertical: 6, paddingHorizontal: 12, backgroundColor: 'rgba(255,255,255,0.15)', borderRadius: 10 },
  guideMetaItem: { fontSize: 12, color: '#FFFFFF' },
  guideMetaKey: { fontWeight: '700', color: 'rgba(255,255,255,0.85)' },
  specialtiesRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 5, justifyContent: 'center', marginTop: 8 },
  specialtyChip: { backgroundColor: 'rgba(255,255,255,0.22)', paddingVertical: 2, paddingHorizontal: 8, borderRadius: 999 },
  specialtyChipText: { color: '#FFFFFF', fontSize: 11, fontWeight: '700' },
  bioRow: { flexDirection: 'row', alignItems: 'flex-start', marginTop: 12, paddingHorizontal: 8, maxWidth: '100%' },
  bioText: { color: 'rgba(255,255,255,0.92)', fontSize: 13, lineHeight: 18, flexShrink: 1, textAlign: 'center' },
  profileActionsRow: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 16 },
  profileEditBtn: { flexDirection: 'row', alignItems: 'center', gap: 6, backgroundColor: 'rgba(255,255,255,0.25)', paddingVertical: 8, paddingHorizontal: 14, borderRadius: 999, borderWidth: 1, borderColor: 'rgba(255,255,255,0.4)' },
  profileEditBtnText: { color: '#FFFFFF', fontSize: 13, fontWeight: '700' },
  publicProfileBtn: { flexDirection: 'row', alignItems: 'center', gap: 6, backgroundColor: '#FFFFFF', paddingVertical: 8, paddingHorizontal: 14, borderRadius: 999 },
  publicProfileBtnText: { color: '#0B7A4B', fontSize: 13, fontWeight: '800' },
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
  modalBackdrop: { flex: 1, backgroundColor: 'rgba(0,0,0,0.5)', alignItems: 'center', justifyContent: 'center', padding: 20 },
  modalCard: { width: '100%', maxWidth: 440, borderRadius: 22, padding: 20, borderWidth: 1 },
  modalHeaderRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 14 },
  modalTitle: { fontSize: 19, fontWeight: '800', marginBottom: 2 },
  modalSub: { fontSize: 12 },
  modalCloseBtn: { width: 32, height: 32, borderRadius: 16, alignItems: 'center', justifyContent: 'center' },
  errorBanner: { flexDirection: 'row', alignItems: 'center', gap: 6, padding: 10, borderRadius: 10, backgroundColor: '#FEE2E2', marginBottom: 12 },
  errorBannerText: { fontSize: 12, fontWeight: '700', color: '#B91C1C', flex: 1 },
  inputGroup: { marginBottom: 12 },
  inputRow: { flexDirection: 'row', gap: 10 },
  inputLabel: { fontSize: 12, fontWeight: '700', marginBottom: 5 },
  textInput: { height: 44, borderRadius: 10, borderWidth: 1, paddingHorizontal: 12, fontSize: 14 },
  bioInput: { minHeight: 80, borderRadius: 10, borderWidth: 1, padding: 12, fontSize: 14, textAlignVertical: 'top' },
  modalActions: { flexDirection: 'row', justifyContent: 'flex-end', alignItems: 'center', gap: 10, marginTop: 14, paddingTop: 10, borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: '#E2E8F0' },
  modalBtn: { paddingVertical: 10, paddingHorizontal: 18, borderRadius: 10 },
  modalBtnText: { fontSize: 14, fontWeight: '700' },
});
