import { Ionicons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import React, { useState } from 'react';
import {
    ActivityIndicator,
    Alert,
    Dimensions,
    ImageBackground,
    KeyboardAvoidingView,
    Linking,
    Platform,
    StatusBar,
    StyleSheet,
    Text,
    TextInput,
    TouchableOpacity,
    useColorScheme,
    View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { apiForgotPassword } from '../../services/api';

const { height } = Dimensions.get('window');

export default function ForgotPasswordScreen() {
  const router = useRouter();
  const colorScheme = useColorScheme();
  const isDark = colorScheme === 'dark';

  const [email, setEmail] = useState('');
  const [sent, setSent] = useState(false);
  const [loading, setLoading] = useState(false);
  const [statusMessage, setStatusMessage] = useState('');
  const [devResetUrl, setDevResetUrl] = useState<string | null>(null);

  const isValidEmail = (value: string) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim());

  // Dynamic colors mapping based on system device theme
  const theme = {
    bg: isDark ? '#111114' : '#FFFFFF',
    inputBg: isDark ? '#1E2029' : '#F1F5F9',
    textMain: isDark ? '#FFFFFF' : '#1A202C',
    textSub: isDark ? '#6B7280' : '#888888',
    placeholderColor: isDark ? '#5A6070' : '#A0AEC0',
    accent: '#22C55E', // Premium Green brand color
  };

  const handleClose = () => {
    router.back();
  };

  const handleSend = async () => {
    const trimmed = email.trim();
    if (!isValidEmail(trimmed)) {
      Alert.alert('Invalid email', 'Please enter a valid email address.');
      return;
    }

    setLoading(true);
    try {
      const res = await apiForgotPassword(trimmed);
      setStatusMessage(res.message);
      setDevResetUrl(res.dev_reset_url ?? null);
      setSent(true);
    } catch (err) {
      Alert.alert(
        'Something went wrong',
        err instanceof Error ? err.message : 'Could not send the reset link. Please try again.',
      );
    } finally {
      setLoading(false);
    }
  };

  const handleOpenResetLink = async () => {
    if (!devResetUrl) return;
    try {
      await Linking.openURL(devResetUrl);
    } catch {
      Alert.alert('Unable to open link', devResetUrl);
    }
  };

  const handleBackToLogin = () => {
    router.push('/(auth)/login');
  };

  return (
    <SafeAreaView style={[styles.container, { backgroundColor: theme.bg }]}>
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
          <Text style={styles.taglineText}>Forgot your password?</Text>
          <Text style={styles.taglineSubText}>No worries, we'll help you reset it.</Text>
        </View>

        <TouchableOpacity style={styles.closeButton} onPress={handleClose} activeOpacity={0.8}>
          <Ionicons name="close" size={18} color="#FFFFFF" />
        </TouchableOpacity>
      </ImageBackground>

      {/* ── BOTTOM HALF: Panel ── */}
      <KeyboardAvoidingView
        style={[styles.authPanel, { backgroundColor: theme.bg }]}
        behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
      >
        {!sent ? (
          // ── Step 1: Enter email ──
          <View style={styles.authContent}>
            <Text style={[styles.sectionLabel, { color: theme.textMain }]}>Reset Password</Text>
            <Text style={[styles.instructionText, { color: theme.textSub }]}>
              Enter the email address associated with your account and we'll send you a reset link.
            </Text>

            {/* Email input */}
            <View style={[styles.inputWrapper, { backgroundColor: theme.inputBg }]}>
              <Ionicons name="mail-outline" size={18} color={theme.placeholderColor} style={styles.inputIcon} />
              <TextInput
                style={[styles.textInput, { color: theme.textMain }]}
                placeholder="Email address"
                placeholderTextColor={theme.placeholderColor}
                value={email}
                onChangeText={setEmail}
                keyboardType="email-address"
                autoCapitalize="none"
                autoCorrect={false}
              />
            </View>

            {/* Send Reset Link button */}
            <TouchableOpacity
              style={[styles.sendButton, { backgroundColor: theme.accent, opacity: loading ? 0.7 : 1 }]}
              onPress={handleSend}
              activeOpacity={0.85}
              disabled={loading}
            >
              {loading ? (
                <ActivityIndicator color="#FFFFFF" />
              ) : (
                <Text style={styles.sendButtonText}>Send Reset Link</Text>
              )}
            </TouchableOpacity>

            {/* Back to login */}
            <TouchableOpacity style={styles.backButton} onPress={handleBackToLogin} activeOpacity={0.7}>
              <Ionicons name="arrow-back-outline" size={16} color={theme.accent} style={{ marginRight: 6 }} />
              <Text style={[styles.backText, { color: theme.accent }]}>Back to Login</Text>
            </TouchableOpacity>
          </View>
        ) : (
          // ── Step 2: Success state ──
          <View style={styles.authContent}>
            <View style={styles.successIconContainer}>
              <Ionicons name="checkmark-circle-outline" size={64} color={theme.accent} />
            </View>
            <Text style={[styles.sectionLabel, { color: theme.textMain }]}>Check your email</Text>
            <Text style={[styles.instructionText, { color: theme.textSub }]}>
              {statusMessage
                ? statusMessage
                : `We've sent a password reset link to ${email}. Please check your inbox and follow the instructions.`}
            </Text>

            {devResetUrl ? (
              <TouchableOpacity
                style={[styles.devLinkBox, { borderColor: theme.accent }]}
                onPress={handleOpenResetLink}
                activeOpacity={0.8}
              >
                <Ionicons name="open-outline" size={16} color={theme.accent} style={{ marginRight: 8 }} />
                <Text style={[styles.devLinkText, { color: theme.accent }]} numberOfLines={1}>
                  Open reset link now
                </Text>
              </TouchableOpacity>
            ) : null}

            {/* Back to login */}
            <TouchableOpacity style={[styles.sendButton, { backgroundColor: theme.accent }]} onPress={handleBackToLogin} activeOpacity={0.85}>
              <Text style={styles.sendButtonText}>Back to Login</Text>
            </TouchableOpacity>

            {/* Resend */}
            <TouchableOpacity style={styles.backButton} onPress={() => setSent(false)} activeOpacity={0.7}>
              <Text style={[styles.backText, { color: theme.textSub }]}>Didn't receive it? </Text>
              <Text style={[styles.resendLink, { color: theme.accent }]}>Resend</Text>
            </TouchableOpacity>
          </View>
        )}
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
  },

  // ── Hero ──
  heroImage: {
    height: height * 0.38,
    width: '100%',
    justifyContent: 'space-between',
  },
  overlay: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: 'rgba(0,0,0,0.42)',
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
  },
  taglineSubText: {
    color: 'rgba(255,255,255,0.65)',
    fontSize: 13,
    marginTop: 2,
  },
  closeButton: {
    position: 'absolute',
    top: 48,
    right: 20,
    width: 38,
    height: 38,
    borderRadius: 19,
    borderWidth: 2,
    borderColor: '#FFFFFF',
    alignItems: 'center',
    justifyContent: 'center',
  },

  // ── Auth Panel ──
  authPanel: {
    flex: 1,
  },
  authContent: {
    paddingHorizontal: 20,
    paddingTop: 28,
    paddingBottom: 32,
  },
  sectionLabel: {
    fontSize: 20,
    fontWeight: '800',
    letterSpacing: 0.4,
    marginBottom: 10,
  },
  instructionText: {
    fontSize: 13,
    lineHeight: 20,
    marginBottom: 24,
  },
  emailHighlight: {
    fontWeight: '600',
  },

  // Input
  inputWrapper: {
    flexDirection: 'row',
    alignItems: 'center',
    borderRadius: 50,
    paddingVertical: 13,
    paddingHorizontal: 20,
    marginBottom: 14,
  },
  inputIcon: {
    marginRight: 10,
  },
  textInput: {
    flex: 1,
    fontSize: 15,
    padding: 0,
  },

  // Send button
  sendButton: {
    borderRadius: 50,
    paddingVertical: 15,
    alignItems: 'center',
    marginBottom: 16,
  },
  sendButtonText: {
    color: '#FFFFFF',
    fontSize: 15,
    fontWeight: '700',
    letterSpacing: 0.3,
  },

  // Back button
  backButton: {
    flexDirection: 'row',
    justifyContent: 'center',
    alignItems: 'center',
  },
  backText: {
    fontSize: 13,
    fontWeight: '600',
  },
  resendLink: {
    fontSize: 13,
    fontWeight: '700',
    textDecorationLine: 'underline',
  },

  // Success
  successIconContainer: {
    alignItems: 'center',
    marginBottom: 16,
    marginTop: 8,
  },
  devLinkBox: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1.5,
    borderRadius: 50,
    paddingVertical: 13,
    paddingHorizontal: 20,
    marginBottom: 16,
  },
  devLinkText: {
    fontSize: 14,
    fontWeight: '700',
  },
});