import { Ionicons } from '@expo/vector-icons';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Modal,
  Dimensions,
  ImageBackground,
  KeyboardAvoidingView,
  Platform,
  ScrollView,
  StatusBar,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  useColorScheme,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import HumanVerification from '../../components/HumanVerification';
import { AppToast } from '../../components/ui';
import { hasBiometricLogin, login as loginUser, loginWithBackendOAuth, loginWithBiometrics, loginWithSocial, loginWithSocialAccount } from '../../lib/authStore';
import { getSavedName } from '../../lib/biometric';
import { usePreferences } from '../../lib/preferences';
import { DEFAULT_GOOGLE_ACCOUNTS, getSavedGoogleAccounts, GoogleAccountProfile, isProviderConfigured, saveGoogleAccount, SocialProvider } from '../../lib/socialAuth';

const { height } = Dimensions.get('window');

export default function LoginScreen() {
  const router = useRouter();
  const colorScheme = useColorScheme();
  const isDark = colorScheme === 'dark';
  const insets = useSafeAreaInsets();

  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [verified, setVerified] = useState(false);
  const [error, setError] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [bioAvailable, setBioAvailable] = useState(false);
  const [savedName, setSavedName] = useState('');
  const [socialBusy, setSocialBusy] = useState<SocialProvider | null>(null);
  const [googleModalVisible, setGoogleModalVisible] = useState(false);
  const [googleAccounts, setGoogleAccounts] = useState<GoogleAccountProfile[]>(DEFAULT_GOOGLE_ACCOUNTS);
  const [addingGoogleAccount, setAddingGoogleAccount] = useState(false);
  const [newGoogleName, setNewGoogleName] = useState('');
  const [newGoogleEmail, setNewGoogleEmail] = useState('');
  const [logoutToastDismissed, setLogoutToastDismissed] = useState(false);

  const { t } = usePreferences();

  const { social_error, loggedOut } = useLocalSearchParams<{ social_error?: string | string[]; loggedOut?: string | string[] }>();
  const socialError = Array.isArray(social_error) ? social_error[0] : social_error;
  const logoutFlag = Array.isArray(loggedOut) ? loggedOut[0] : loggedOut;

  // Dynamic colors mapping based on system device theme
  const theme = {
    bg: isDark ? '#111114' : '#FFFFFF',
    inputBg: isDark ? '#1E2029' : '#F1F5F9',
    textMain: isDark ? '#FFFFFF' : '#1A202C',
    textSub: isDark ? '#6B7280' : '#888888',
    placeholderColor: isDark ? '#5A6070' : '#A0AEC0',
    dividerLine: isDark ? '#2A2D38' : '#E2E8F0',
    accent: '#22C55E', // Premium Green brand color
  };

  // Offer fingerprint login when the user previously saved their account.
  useEffect(() => {
    let active = true;
    (async () => {
      const ok = await hasBiometricLogin();
      if (!active) return;
      setBioAvailable(ok);
      if (ok) {
        const name = await getSavedName();
        if (active && name) setSavedName(name);
      }
    })();
    return () => {
      active = false;
    };
  }, []);

  const handleBiometricLogin = async () => {
    setError('');
    setSubmitting(true);
    try {
      await loginWithBiometrics();
      router.replace('/(tabs)');
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Fingerprint login failed.');
    } finally {
      setSubmitting(false);
    }
  };

  const handleClose = () => {
    router.replace('/(tabs)');
  };

  const handleLogin = async () => {
    setError('');

    if (!email.trim() || !password.trim()) {
      setError('Please enter your email and password.');
      return;
    }

    if (!verified) {
      setError('Please complete the "Verify you are human" check.');
      return;
    }

    setSubmitting(true);
    try {
      await loginUser(email.trim(), password);
      router.replace('/(tabs)');
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Login failed. Please try again.');
    } finally {
      setSubmitting(false);
    }
  };

  const handleGooglePress = async () => {
    setError('');
    try {
      const list = await getSavedGoogleAccounts();
      setGoogleAccounts(list);
    } catch {}
    setAddingGoogleAccount(false);
    setNewGoogleName('');
    setNewGoogleEmail('');
    setGoogleModalVisible(true);
  };

  const selectGoogleAccount = async (acc: GoogleAccountProfile) => {
    setSocialBusy('google');
    setGoogleModalVisible(false);
    try {
      if (isProviderConfigured('google')) {
        try {
          await loginWithBackendOAuth('google');
          router.replace('/(tabs)');
          return;
        } catch (oauthErr) {
          console.warn('[SocialAuth] Falling back to account demo login:', oauthErr);
        }
      }
      await loginWithSocialAccount('google', acc);
      router.replace('/(tabs)');
    } catch (e) {
      const msg = e instanceof Error ? e.message : 'Google sign-in failed.';
      if (!/cancel/i.test(msg)) setError(msg);
    } finally {
      setSocialBusy(null);
    }
  };

  const handleCreateGoogleAccount = async () => {
    if (!newGoogleEmail.trim()) {
      Alert.alert('Email Required', 'Please enter your Google account email.');
      return;
    }
    const email = newGoogleEmail.trim().toLowerCase();
    const name = newGoogleName.trim() || email.split('@')[0];
    const newProfile: GoogleAccountProfile = {
      id: `google-${Date.now()}`,
      name,
      email,
    };
    await saveGoogleAccount(newProfile);
    await selectGoogleAccount(newProfile);
  };

  const handleSocial = async (provider: SocialProvider) => {
    if (socialBusy) return;
    setError('');
    setSocialBusy(provider);
    try {
      // Configured (Google or Facebook): real login via the Expo Go-friendly
      // backend flow. Unconfigured: anonymous demo fallback.
      if (isProviderConfigured(provider)) {
        try {
          await loginWithBackendOAuth(provider);
        } catch (oauthErr) {
          const msg = oauthErr instanceof Error ? oauthErr.message : String(oauthErr);
          if (/not configured|could not reach|503/i.test(msg)) {
            console.warn('[SocialAuth] Backend OAuth unconfigured, falling back to instant demo login:', msg);
            await loginWithSocial(provider);
          } else {
            throw oauthErr;
          }
        }
      } else {
        await loginWithSocial(provider);
      }
      router.replace('/(tabs)');
    } catch (e) {
      const msg = e instanceof Error ? e.message : 'Sign-in failed. Please try again.';
      if (!/cancel/i.test(msg)) setError(msg);
    } finally {
      setSocialBusy(null);
    }
  };

  const handleForgotPassword = () => {
    router.push('/(auth)/forgot-password');
  };

  const handleSignUp = () => {
    router.push('/(auth)/register');
  };

  return (
    <View style={[styles.container, { backgroundColor: theme.bg }]}>
      <StatusBar barStyle={isDark ? 'light-content' : 'dark-content'} />

      {/* ── TOP HALF: Hero Image ── */}
      <ImageBackground
        source={require('../../assets/images/pexels-thefullonmonet-20233772.jpg')}
        style={styles.heroImage}
        resizeMode="cover"
      >
        <View style={styles.overlay} />

        <View style={styles.brandContainer}>
          <Text style={styles.brandText}>GUIDE{'\n'}MATE</Text>
        </View>

        <View style={styles.taglineContainer}>
          <Text style={styles.taglineText}>{t('login_welcome')}</Text>
          <Text style={styles.taglineSubText}>{t('login_subtitle')}</Text>
        </View>

        <View style={styles.topActions}>
          <TouchableOpacity style={styles.closeButton} onPress={handleClose} activeOpacity={0.8}>
            <Ionicons name="close" size={18} color="#FFFFFF" />
          </TouchableOpacity>
        </View>
      </ImageBackground>

      {/* ── BOTTOM HALF: Auth Panel ── */}
      <KeyboardAvoidingView
        style={{ flex: 1 }}
        behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
      >
        <ScrollView
          style={[styles.authPanel, { backgroundColor: theme.bg }]}
          contentContainerStyle={styles.authContent}
          keyboardShouldPersistTaps="handled"
          showsVerticalScrollIndicator={false}
        >
          <Text style={[styles.sectionLabel, { color: theme.textMain }]}>{t('login_title')}</Text>

          {/* Fingerprint quick login (only when an account is saved on device) */}
          {bioAvailable ? (
            <>
              <TouchableOpacity
                style={[styles.bioButton, { borderColor: theme.accent }, submitting && { opacity: 0.6 }]}
                onPress={handleBiometricLogin}
                activeOpacity={0.85}
                disabled={submitting}
              >
                <Ionicons name="finger-print" size={22} color={theme.accent} style={{ marginRight: 10 }} />
                <Text style={[styles.bioButtonText, { color: theme.accent }]}>
                  {savedName ? `${t('login_as')} ${savedName}` : t('login_fp')}
                </Text>
              </TouchableOpacity>

              <View style={styles.dividerRow}>
                <View style={[styles.dividerLine, { backgroundColor: theme.dividerLine }]} />
                <Text style={[styles.dividerText, { color: theme.textSub }]}>{t('or_password')}</Text>
                <View style={[styles.dividerLine, { backgroundColor: theme.dividerLine }]} />
              </View>
            </>
          ) : null}

          {/* Email Wrapper */}
          <View style={[styles.inputWrapper, { backgroundColor: theme.inputBg }]}>
            <Ionicons name="mail-outline" size={18} color={theme.placeholderColor} style={styles.inputIcon} />
            <TextInput
              style={[styles.textInput, { color: theme.textMain }]}
              placeholder={t('email_ph')}
              placeholderTextColor={theme.placeholderColor}
              value={email}
              onChangeText={setEmail}
              keyboardType="email-address"
              autoCapitalize="none"
              autoCorrect={false}
            />
          </View>

          {/* Password Wrapper */}
          <View style={[styles.inputWrapper, { backgroundColor: theme.inputBg }]}>
            <Ionicons name="lock-closed-outline" size={18} color={theme.placeholderColor} style={styles.inputIcon} />
            <TextInput
              style={[styles.textInput, { color: theme.textMain }]}
              placeholder={t('password_ph')}
              placeholderTextColor={theme.placeholderColor}
              value={password}
              onChangeText={setPassword}
              secureTextEntry={!showPassword}
              autoCapitalize="none"
            />
            <TouchableOpacity onPress={() => setShowPassword(!showPassword)} activeOpacity={0.7}>
              <Ionicons
                name={showPassword ? 'eye-off-outline' : 'eye-outline'}
                size={18}
                color={theme.placeholderColor}
              />
            </TouchableOpacity>
          </View>

          {/* Human verification (Cloudflare-style) */}
          <HumanVerification isDark={isDark} onVerifiedChange={setVerified} label={t('verify_human')} />

          {/* Inline error message */}
          {error || socialError ? (
            <View style={styles.errorRow}>
              <Ionicons name="alert-circle-outline" size={16} color="#EF4444" style={{ marginRight: 6 }} />
              <Text style={styles.errorText}>{error || socialError}</Text>
            </View>
          ) : null}

          {/* Action Button */}
          <TouchableOpacity
            style={[styles.loginButton, { backgroundColor: theme.accent }, submitting && { opacity: 0.6 }]}
            onPress={handleLogin}
            activeOpacity={0.85}
            disabled={submitting}
          >
            <Text style={styles.loginButtonText}>{submitting ? t('signing_in') : t('login_btn')}</Text>
          </TouchableOpacity>

          {/* Forgot Button */}
          <TouchableOpacity style={styles.forgotButton} onPress={handleForgotPassword} activeOpacity={0.7}>
            <Text style={[styles.forgotText, { color: theme.accent }]}>{t('forgot_pw')}</Text>
          </TouchableOpacity>

          {/*  Redirect Row */}
          <View style={styles.signUpRow}>
            <Text style={[styles.signUpPrompt, { color: theme.textSub }]}>{t('no_account')}</Text>
            <TouchableOpacity onPress={handleSignUp} activeOpacity={0.7} hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }}>
              <Text style={[styles.signUpLink, { color: theme.accent }]}>{t('sign_up') || 'Register here'}</Text>
            </TouchableOpacity>
          </View>

          {/* ── Social sign-in ── */}
          <View style={styles.dividerRow}>
            <View style={[styles.dividerLine, { backgroundColor: theme.dividerLine }]} />
            <Text style={[styles.dividerText, { color: theme.textSub }]}>{t('or_continue_with')}</Text>
            <View style={[styles.dividerLine, { backgroundColor: theme.dividerLine }]} />
          </View>

          <TouchableOpacity
            style={[styles.socialButton, { backgroundColor: theme.inputBg, borderColor: theme.dividerLine }, socialBusy && { opacity: 0.6 }]}
            onPress={handleGooglePress}
            activeOpacity={0.85}
            disabled={!!socialBusy}
          >
            {socialBusy === 'google' ? (
              <ActivityIndicator color={theme.textMain} />
            ) : (
              <>
                <Ionicons name="logo-google" size={19} color="#DB4437" style={{ marginRight: 10 }} />
                <Text style={[styles.socialText, { color: theme.textMain }]}>{t('continue_google')}</Text>
              </>
            )}
          </TouchableOpacity>

          {/* Continue as guest */}
          <TouchableOpacity style={styles.guestButton} onPress={handleClose} activeOpacity={0.7}>
            <Ionicons name="arrow-forward-outline" size={16} color={theme.textSub} style={{ marginRight: 6 }} />
            <Text style={[styles.guestText, { color: theme.textSub }]}>{t('continue_guest')}</Text>
          </TouchableOpacity>
        </ScrollView>
      </KeyboardAvoidingView>

      
      {/* ── Google Account Chooser Modal ── */}
      <Modal
        visible={googleModalVisible}
        transparent
        animationType="fade"
        onRequestClose={() => setGoogleModalVisible(false)}
      >
        <View style={styles.googleBackdrop}>
          <View style={[styles.googleCard, { backgroundColor: isDark ? '#1E293B' : '#FFFFFF', borderColor: theme.dividerLine }]}>
            {/* Header */}
            <View style={styles.googleHeader}>
              <Ionicons name="logo-google" size={32} color="#DB4437" />
              <Text style={[styles.googleTitle, { color: theme.textMain }]}>Choose an account</Text>
              <Text style={[styles.googleSub, { color: theme.textSub }]}>to continue to GuideMate</Text>
            </View>

            {!addingGoogleAccount ? (
              <ScrollView style={styles.accountsList} showsVerticalScrollIndicator={false}>
                {googleAccounts.map((acc, idx) => (
                  <TouchableOpacity
                    key={acc.id || acc.email + idx}
                    style={[styles.accountRow, { borderBottomColor: theme.dividerLine }]}
                    onPress={() => selectGoogleAccount(acc)}
                    activeOpacity={0.7}
                  >
                    <View style={[styles.avatarCircle, { backgroundColor: isDark ? '#334155' : '#E2E8F0' }]}>
                      <Text style={[styles.avatarLetter, { color: theme.accent }]}>
                        {(acc.name || acc.email)[0].toUpperCase()}
                      </Text>
                    </View>
                    <View style={styles.accountTextWrap}>
                      <Text style={[styles.accountName, { color: theme.textMain }]} numberOfLines={1}>
                        {acc.name}
                      </Text>
                      <Text style={[styles.accountEmail, { color: theme.textSub }]} numberOfLines={1}>
                        {acc.email}
                      </Text>
                    </View>
                    <Ionicons name="chevron-forward" size={18} color={theme.textSub} />
                  </TouchableOpacity>
                ))}

                {/* Add another account button */}
                <TouchableOpacity
                  style={[styles.accountRow, { borderBottomColor: 'transparent' }]}
                  onPress={() => setAddingGoogleAccount(true)}
                  activeOpacity={0.7}
                >
                  <View style={[styles.avatarCircle, { backgroundColor: isDark ? '#334155' : '#E2E8F0' }]}>
                    <Ionicons name="person-add-outline" size={18} color={theme.accent} />
                  </View>
                  <View style={styles.accountTextWrap}>
                    <Text style={[styles.accountName, { color: theme.accent }]}>Use another account</Text>
                  </View>
                </TouchableOpacity>
              </ScrollView>
            ) : (
              <View style={styles.newAccountForm}>
                <Text style={[styles.formLabel, { color: theme.textSub }]}>Full Name</Text>
                <TextInput
                  style={[styles.modalInput, { backgroundColor: theme.inputBg, color: theme.textMain, borderColor: theme.dividerLine }]}
                  placeholder="e.g. Marc Repuesto"
                  placeholderTextColor={theme.textSub}
                  value={newGoogleName}
                  onChangeText={setNewGoogleName}
                />

                <Text style={[styles.formLabel, { color: theme.textSub, marginTop: 12 }]}>Google Email Address</Text>
                <TextInput
                  style={[styles.modalInput, { backgroundColor: theme.inputBg, color: theme.textMain, borderColor: theme.dividerLine }]}
                  placeholder="e.g. user@gmail.com"
                  placeholderTextColor={theme.textSub}
                  value={newGoogleEmail}
                  onChangeText={setNewGoogleEmail}
                  keyboardType="email-address"
                  autoCapitalize="none"
                />

                <View style={styles.formButtonRow}>
                  <TouchableOpacity
                    style={[styles.formCancelBtn, { borderColor: theme.dividerLine }]}
                    onPress={() => setAddingGoogleAccount(false)}
                  >
                    <Text style={[styles.formBtnText, { color: theme.textSub }]}>Back</Text>
                  </TouchableOpacity>
                  <TouchableOpacity
                    style={[styles.formSubmitBtn, { backgroundColor: theme.accent }]}
                    onPress={handleCreateGoogleAccount}
                  >
                    <Text style={[styles.formBtnText, { color: '#FFFFFF' }]}>Continue</Text>
                  </TouchableOpacity>
                </View>
              </View>
            )}

            {/* Close / Cancel */}
            <TouchableOpacity
              style={[styles.modalCloseBtn, { borderTopColor: theme.dividerLine }]}
              onPress={() => setGoogleModalVisible(false)}
            >
              <Text style={[styles.modalCloseText, { color: theme.textSub }]}>Cancel</Text>
            </TouchableOpacity>
          </View>
        </View>
      </Modal>

      <AppToast
        key={logoutFlag}
        visible={logoutFlag === '1' && !logoutToastDismissed}
        title="Logged Out"
        subtitle="You have been logged out successfully."
        top={insets.top + 8}
        onHide={() => setLogoutToastDismissed(true)}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
  },
  heroImage: {
    height: height * 0.38,
    width: '100%',
    justifyContent: 'space-between',
  },
  overlay: {
    ...StyleSheet.absoluteFill,
    backgroundColor: 'rgba(0,0,0,0.52)',
  },
  brandContainer: {
    marginTop: 52,
    marginLeft: 22,
  },
  brandText: {
    color: '#FFFFFF',
    fontSize: 28,
    fontWeight: '900',
    lineHeight: 32,
    letterSpacing: 1.5,
    textShadowColor: 'rgba(0,0,0,0.5)',
    textShadowOffset: { width: 0, height: 1 },
    textShadowRadius: 6,
  },
  taglineContainer: {
    marginLeft: 22,
    marginBottom: 22,
  },
  taglineText: {
    color: '#FFFFFF',
    fontSize: 16,
    fontWeight: '700',
    letterSpacing: 0.3,
    textShadowColor: 'rgba(0,0,0,0.5)',
    textShadowOffset: { width: 0, height: 1 },
    textShadowRadius: 4,
  },
  taglineSubText: {
    color: 'rgba(255,255,255,0.92)',
    fontSize: 13,
    marginTop: 2,
    textShadowColor: 'rgba(0,0,0,0.5)',
    textShadowOffset: { width: 0, height: 1 },
    textShadowRadius: 4,
  },
  topActions: {
    position: 'absolute',
    top: 48,
    right: 20,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  langButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 5,
    height: 38,
    paddingHorizontal: 12,
    borderRadius: 19,
    borderWidth: 2,
    borderColor: '#FFFFFF',
    backgroundColor: 'rgba(0,0,0,0.2)',
  },
  langButtonText: {
    color: '#FFFFFF',
    fontSize: 13,
    fontWeight: '800',
    letterSpacing: 0.5,
  },
  closeButton: {
    width: 38,
    height: 38,
    borderRadius: 19,
    borderWidth: 2,
    borderColor: '#FFFFFF',
    alignItems: 'center',
    justifyContent: 'center',
  },
  authPanel: {
    flex: 1,
  },
  authContent: {
    paddingHorizontal: 20,
    paddingTop: 22,
    paddingBottom: 32,
  },
  sectionLabel: {
    fontSize: 20,
    fontWeight: '800',
    letterSpacing: 0.4,
    marginBottom: 18,
  },
  inputWrapper: {
    flexDirection: 'row',
    alignItems: 'center',
    borderRadius: 50,
    paddingVertical: 13,
    paddingHorizontal: 20,
    marginBottom: 12,
  },
  inputIcon: {
    marginRight: 10,
  },
  textInput: {
    flex: 1,
    fontSize: 15,
    padding: 0,
  },
  errorRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 12,
  },
  errorText: {
    flex: 1,
    color: '#EF4444',
    fontSize: 12,
    fontWeight: '600',
  },
  bioButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 50,
    borderWidth: 1.5,
    paddingVertical: 14,
    marginBottom: 16,
  },
  bioButtonText: {
    fontSize: 15,
    fontWeight: '700',
    letterSpacing: 0.2,
  },
  loginButton: {
    borderRadius: 50,
    paddingVertical: 15,
    alignItems: 'center',
    marginTop: 4,
    marginBottom: 14,
  },
  loginButtonText: {
    color: '#FFFFFF',
    fontSize: 15,
    fontWeight: '700',
    letterSpacing: 0.3,
  },
  forgotButton: {
    alignItems: 'center',
    marginBottom: 20,
  },
  forgotText: {
    fontSize: 13,
    fontWeight: '600',
  },
  dividerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 16,
  },
  dividerLine: {
    flex: 1,
    height: 1,
  },
  dividerText: {
    fontSize: 12,
    marginHorizontal: 10,
  },
  googleBackdrop: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.6)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 20,
  },
  googleCard: {
    width: '100%',
    maxWidth: 380,
    borderRadius: 24,
    borderWidth: 1,
    paddingTop: 24,
    overflow: 'hidden',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 8 },
    shadowOpacity: 0.25,
    shadowRadius: 16,
    elevation: 8,
  },
  googleHeader: {
    alignItems: 'center',
    paddingHorizontal: 20,
    marginBottom: 16,
  },
  googleTitle: {
    fontSize: 20,
    fontWeight: '800',
    marginTop: 10,
    letterSpacing: -0.3,
  },
  googleSub: {
    fontSize: 13,
    marginTop: 4,
    fontWeight: '500',
  },
  accountsList: {
    maxHeight: 280,
    paddingHorizontal: 16,
  },
  accountRow: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 12,
    borderBottomWidth: StyleSheet.hairlineWidth,
  },
  avatarCircle: {
    width: 40,
    height: 40,
    borderRadius: 20,
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 14,
  },
  avatarLetter: {
    fontSize: 16,
    fontWeight: '800',
  },
  accountTextWrap: {
    flex: 1,
    justifyContent: 'center',
  },
  accountName: {
    fontSize: 15,
    fontWeight: '700',
  },
  accountEmail: {
    fontSize: 12.5,
    marginTop: 2,
    fontWeight: '500',
  },
  newAccountForm: {
    paddingHorizontal: 20,
    paddingBottom: 16,
  },
  formLabel: {
    fontSize: 12,
    fontWeight: '700',
    marginBottom: 6,
    textTransform: 'uppercase',
    letterSpacing: 0.5,
  },
  modalInput: {
    borderWidth: 1,
    borderRadius: 12,
    paddingHorizontal: 14,
    paddingVertical: 10,
    fontSize: 14,
  },
  formButtonRow: {
    flexDirection: 'row',
    gap: 10,
    marginTop: 18,
  },
  formCancelBtn: {
    flex: 1,
    borderWidth: 1,
    borderRadius: 12,
    paddingVertical: 12,
    alignItems: 'center',
  },
  formSubmitBtn: {
    flex: 1,
    borderRadius: 12,
    paddingVertical: 12,
    alignItems: 'center',
  },
  formBtnText: {
    fontSize: 14,
    fontWeight: '700',
  },
  modalCloseBtn: {
    borderTopWidth: StyleSheet.hairlineWidth,
    paddingVertical: 14,
    alignItems: 'center',
    marginTop: 8,
  },
  modalCloseText: {
    fontSize: 14,
    fontWeight: '700',
  },
  socialButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 50,
    borderWidth: 1,
    paddingVertical: 14,
    marginBottom: 12,
  },
  socialText: {
    fontSize: 15,
    fontWeight: '700',
    letterSpacing: 0.2,
  },
  guestButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 18,
    paddingVertical: 6,
  },
  guestText: {
    fontSize: 14,
    fontWeight: '600',
  },
  modalBackdrop: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.45)',
    justifyContent: 'flex-end',
  },
  modalSheet: {
    borderTopLeftRadius: 20,
    borderTopRightRadius: 20,
    paddingBottom: 30,
  },
  modalHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 20,
    paddingVertical: 18,
  },
  modalTitle: { fontSize: 17, fontWeight: '700' },
  langOption: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 20,
    paddingVertical: 15,
    borderTopWidth: StyleSheet.hairlineWidth,
  },
  langOptionText: { fontSize: 16, fontWeight: '500' },
  signUpRow: {
    flexDirection: 'row',
    justifyContent: 'center',
    alignItems: 'center',
    marginTop: 4,
    marginBottom: 20,
  },
  signUpPrompt: {
    fontSize: 13,
  },
  signUpLink: {
    fontSize: 13,
    fontWeight: '700',
  },
});