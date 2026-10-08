import { Ionicons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import React, { useState } from 'react';
import { register as registerUser } from '../../lib/authStore';
import {
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
import { SafeAreaView } from 'react-native-safe-area-context';

const { height } = Dimensions.get('window');

export default function RegisterScreen() {
  const router = useRouter();
  const colorScheme = useColorScheme();
  const isDark = colorScheme === 'dark';

  const [fullName, setFullName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [showConfirm, setShowConfirm] = useState(false);
  const [agreedToTerms, setAgreedToTerms] = useState(false);
  const [error, setError] = useState('');
  const [submitting, setSubmitting] = useState(false);

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

  const handleClose = () => {
    router.back();
  };

  // Password policy: 8-12 characters with an uppercase letter, a number, and a special character.
  const validatePassword = (value: string): string | null => {
    if (value.length < 8 || value.length > 12) {
      return 'Password must be 8 to 12 characters long.';
    }
    if (!/[A-Z]/.test(value)) {
      return 'Password must include at least one uppercase letter.';
    }
    if (!/[0-9]/.test(value)) {
      return 'Password must include at least one number.';
    }
    if (!/[^A-Za-z0-9]/.test(value)) {
      return 'Password must include at least one special character.';
    }
    return null;
  };

  const handleRegister = async () => {
    setError('');

    if (!fullName.trim() || !email.trim() || !password.trim() || !confirmPassword.trim()) {
      setError('Please fill in all fields.');
      return;
    }

    const passwordError = validatePassword(password);
    if (passwordError) {
      setError(passwordError);
      return;
    }

    if (password !== confirmPassword) {
      setError('Passwords do not match.');
      return;
    }

    if (!agreedToTerms) {
      setError('Please accept the Terms of Service and Privacy Policy.');
      return;
    }

    setSubmitting(true);
    try {
      await registerUser(fullName.trim(), email.trim(), password);
      // Account created in the database — send the user to the login screen.
      router.replace('/(auth)/login');
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Registration failed. Please try again.');
    } finally {
      setSubmitting(false);
    }
  };

  const handleLogin = () => {
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
          <Text style={styles.taglineText}>Your journey starts here.</Text>
          <Text style={styles.taglineSubText}>Create an account to explore.</Text>
        </View>

        <TouchableOpacity style={styles.closeButton} onPress={handleClose} activeOpacity={0.8}>
          <Ionicons name="close" size={18} color="#FFFFFF" />
        </TouchableOpacity>
      </ImageBackground>

      {/* ── BOTTOM HALF: Register Panel ── */}
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
          <Text style={[styles.sectionLabel, { color: theme.textMain }]}>Create Account</Text>

          {/* Full Name */}
          <View style={[styles.inputWrapper, { backgroundColor: theme.inputBg }]}>
            <Ionicons name="person-outline" size={18} color={theme.placeholderColor} style={styles.inputIcon} />
            <TextInput
              style={[styles.textInput, { color: theme.textMain }]}
              placeholder="Full name"
              placeholderTextColor={theme.placeholderColor}
              value={fullName}
              onChangeText={setFullName}
              autoCapitalize="words"
              autoCorrect={false}
            />
          </View>

          {/* Email */}
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

          {/* Password */}
          <View style={[styles.inputWrapper, { backgroundColor: theme.inputBg }]}>
            <Ionicons name="lock-closed-outline" size={18} color={theme.placeholderColor} style={styles.inputIcon} />
            <TextInput
              style={[styles.textInput, { color: theme.textMain }]}
              placeholder="Password"
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

          {/* Confirm Password */}
          <View style={[styles.inputWrapper, { backgroundColor: theme.inputBg }]}>
            <Ionicons name="lock-closed-outline" size={18} color={theme.placeholderColor} style={styles.inputIcon} />
            <TextInput
              style={[styles.textInput, { color: theme.textMain }]}
              placeholder="Confirm password"
              placeholderTextColor={theme.placeholderColor}
              value={confirmPassword}
              onChangeText={setConfirmPassword}
              secureTextEntry={!showConfirm}
              autoCapitalize="none"
            />
            <TouchableOpacity onPress={() => setShowConfirm(!showConfirm)} activeOpacity={0.7}>
              <Ionicons
                name={showConfirm ? 'eye-off-outline' : 'eye-outline'}
                size={18}
                color={theme.placeholderColor}
              />
            </TouchableOpacity>
          </View>

          {/* Password requirements hint */}
          <Text style={[styles.hintText, { color: theme.textSub }]}>
            Password must be 8–12 characters and include an uppercase letter, a number, and a special character.
          </Text>

          {/* Inline error message */}
          {error ? (
            <View style={styles.errorRow}>
              <Ionicons name="alert-circle-outline" size={16} color="#EF4444" style={{ marginRight: 6 }} />
              <Text style={styles.errorText}>{error}</Text>
            </View>
          ) : null}

          {/* Terms & Privacy checkbox */}
          <TouchableOpacity
            style={styles.termsRow}
            onPress={() => setAgreedToTerms(!agreedToTerms)}
            activeOpacity={0.7}
          >
            <View
              style={[
                styles.checkbox,
                { borderColor: agreedToTerms ? theme.accent : theme.textSub },
                agreedToTerms && { backgroundColor: theme.accent },
              ]}
            >
              {agreedToTerms && <Ionicons name="checkmark" size={14} color="#FFFFFF" />}
            </View>
            <Text style={[styles.termsText, { color: theme.textSub }]}>
              I agree to the{' '}
              <Text style={[styles.termsLink, { color: theme.accent }]}>Terms of Service</Text>
              {' '}and{' '}
              <Text style={[styles.termsLink, { color: theme.accent }]}>Privacy Policy</Text>.
            </Text>
          </TouchableOpacity>

          {/* Create Account Button */}
          <TouchableOpacity
            style={[styles.createButton, { backgroundColor: theme.accent }, submitting && { opacity: 0.6 }]}
            onPress={handleRegister}
            activeOpacity={0.85}
            disabled={submitting}
          >
            <Text style={styles.createButtonText}>{submitting ? 'Creating...' : 'Create Account'}</Text>
          </TouchableOpacity>

          {/* Login redirect */}
          <View style={styles.loginRow}>
            <Text style={[styles.loginPrompt, { color: theme.textSub }]}>Already have an account? </Text>
            <TouchableOpacity onPress={handleLogin} activeOpacity={0.7}>
              <Text style={[styles.loginLink, { color: theme.accent }]}>Log in</Text>
            </TouchableOpacity>
          </View>

        </ScrollView>
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
    height: height * 0.36,
    width: '100%',
    justifyContent: 'space-between',
  },
  overlay: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: 'rgba(0,0,0,0.45)',
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
    paddingTop: 22,
    paddingBottom: 32,
  },
  sectionLabel: {
    fontSize: 20,
    fontWeight: '800',
    letterSpacing: 0.4,
    marginBottom: 18,
  },

  // Social buttons
  socialButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 50,
    paddingVertical: 14,
    marginBottom: 12,
  },
  socialButtonText: {
    fontSize: 15,
    fontWeight: '600',
    letterSpacing: 0.2,
  },
  googleG: {
    color: '#4CAF50',
    fontSize: 18,
    fontWeight: '800',
    marginRight: 10,
  },
  facebookF: {
    color: '#4A90D9',
    fontSize: 20,
    fontWeight: '800',
    marginRight: 10,
  },

  // Divider
  dividerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginVertical: 16,
  },
  dividerLine: {
    flex: 1,
    height: 1,
  },
  dividerText: {
    fontSize: 12,
    marginHorizontal: 10,
  },

  // Input fields
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

  // Password hint
  hintText: {
    fontSize: 11,
    lineHeight: 16,
    marginTop: 2,
    marginBottom: 4,
  },

  // Inline error
  errorRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginTop: 8,
  },
  errorText: {
    flex: 1,
    color: '#EF4444',
    fontSize: 12,
    fontWeight: '600',
  },

  // Terms
  termsRow: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    marginVertical: 14,
  },
  checkbox: {
    width: 22,
    height: 22,
    borderRadius: 5,
    borderWidth: 2,
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 10,
    marginTop: 1,
  },
  termsText: {
    flex: 1,
    fontSize: 12,
    lineHeight: 18,
  },
  termsLink: {
    fontWeight: '600',
  },

  // Create button
  createButton: {
    borderRadius: 50,
    paddingVertical: 15,
    alignItems: 'center',
    marginBottom: 16,
  },
  createButtonText: {
    color: '#FFFFFF',
    fontSize: 15,
    fontWeight: '700',
    letterSpacing: 0.3,
  },

  // Login redirect
  loginRow: {
    flexDirection: 'row',
    justifyContent: 'center',
    alignItems: 'center',
  },
  loginPrompt: {
    fontSize: 13,
  },
  loginLink: {
    fontSize: 13,
    fontWeight: '700',
  },
});