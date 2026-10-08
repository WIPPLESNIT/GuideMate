import { Ionicons } from '@expo/vector-icons';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useEffect, useState } from 'react';
import {
  ActivityIndicator,
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
import { hasBiometricLogin, login as loginUser, loginWithBackendOAuth, loginWithBiometrics, loginWithSocial } from '../../lib/authStore';
import { getSavedName } from '../../lib/biometric';
import { usePreferences } from '../../lib/preferences';
import { isProviderConfigured, SocialProvider } from '../../lib/socialAuth';

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
  const [logoutToast, setLogoutToast] = useState(false);
  const [logoutToastKey, setLogoutToastKey] = useState(0);

  const { t } = usePreferences();

  // If the OAuth redirect route bounced back with an error, show it here.
  const { social_error, loggedOut } = useLocalSearchParams<{ social_error?: string | string[]; loggedOut?: string | string[] }>();
  useEffect(() => {
    const msg = Array.isArray(social_error) ? social_error[0] : social_error;
    if (msg) setError(msg);
  }, [social_error]);

  useEffect(() => {
    const flag = Array.isArray(loggedOut) ? loggedOut[0] : loggedOut;
    if (flag === '1') {
      setLogoutToast(true);
      setLogoutToastKey((n) => n + 1);
    }
  }, [loggedOut]);

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

  const handleSocial = async (provider: SocialProvider) => {
    if (socialBusy) return;
    setError('');
    setSocialBusy(provider);
    try {
      // Configured (Google or Facebook): real login via the Expo Go-friendly
      // backend flow. Unconfigured: anonymous demo fallback.
      if (isProviderConfigured(provider)) {
        await loginWithBackendOAuth(provider);
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
          {error ? (
            <View style={styles.errorRow}>
              <Ionicons name="alert-circle-outline" size={16} color="#EF4444" style={{ marginRight: 6 }} />
              <Text style={styles.errorText}>{error}</Text>
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
            <TouchableOpacity onPress={handleSignUp} activeOpacity={0.7}>
              <Text style={[styles.signUpLink, { color: theme.accent }]}>{t('sign_up')}</Text>
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
            onPress={() => handleSocial('google')}
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

      <AppToast
        key={logoutToastKey}
        visible={logoutToast}
        title="Logged Out"
        subtitle="You have been logged out successfully."
        top={insets.top + 8}
        onHide={() => setLogoutToast(false)}
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
    ...StyleSheet.absoluteFillObject,
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
    marginTop: 6,
  },
  signUpPrompt: {
    fontSize: 13,
  },
  signUpLink: {
    fontSize: 13,
    fontWeight: '700',
  },
});