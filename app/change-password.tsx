import { Ionicons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import React, { useState } from 'react';
import {
    Alert,
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
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import { changePassword } from '../lib/authStore';

export default function ChangePasswordScreen() {
  const router = useRouter();
  const colorScheme = useColorScheme();
  const isDark = colorScheme === 'dark';
  const insets = useSafeAreaInsets();

  const [current, setCurrent] = useState('');
  const [next, setNext] = useState('');
  const [confirm, setConfirm] = useState('');
  const [error, setError] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const theme = {
    bg: isDark ? '#0F1012' : '#F2F3F5',
    card: isDark ? '#1E2029' : '#FFFFFF',
    border: isDark ? '#2A2D38' : '#ECECEC',
    textMain: isDark ? '#FFFFFF' : '#1A1A1A',
    textSub: isDark ? '#9CA3AF' : '#8A8A8A',
    accent: '#22C55E',
    danger: '#EF4444',
    inputBg: isDark ? '#15161A' : '#F1F5F9',
  };

  const validatePassword = (pwd: string): string | null => {
    if (pwd.length < 8 || pwd.length > 12) {
      return 'Password must be 8-12 characters long.';
    }
    if (!/[A-Z]/.test(pwd)) {
      return 'Password must include an uppercase letter.';
    }
    if (!/[0-9]/.test(pwd)) {
      return 'Password must include a number.';
    }
    if (!/[^A-Za-z0-9]/.test(pwd)) {
      return 'Password must include a special character.';
    }
    return null;
  };

  const handleSave = async () => {
    setError('');
    if (!current || !next || !confirm) {
      setError('Please fill in all fields.');
      return;
    }
    const policy = validatePassword(next);
    if (policy) {
      setError(policy);
      return;
    }
    if (next !== confirm) {
      setError('New passwords do not match.');
      return;
    }
    if (next === current) {
      setError('New password must be different from your current password.');
      return;
    }

    setSubmitting(true);
    try {
      await changePassword(current, next);
      Alert.alert('Password changed', 'Your password has been updated.', [
        { text: 'OK', onPress: () => router.back() },
      ]);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Could not change password.');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <SafeAreaView style={[styles.container, { backgroundColor: theme.bg }]}>
      <StatusBar barStyle={isDark ? 'light-content' : 'dark-content'} backgroundColor={theme.card} />

      <View style={[styles.header, { backgroundColor: theme.card, borderBottomColor: theme.border, paddingTop: insets.top + 12 }]}>
        <TouchableOpacity onPress={() => router.back()} style={styles.back} activeOpacity={0.7}>
          <Ionicons name="chevron-back" size={26} color={theme.textMain} />
        </TouchableOpacity>
        <Text style={[styles.headerTitle, { color: theme.textMain }]}>Change Password</Text>
        <View style={styles.back} />
      </View>

      <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
        <ScrollView contentContainerStyle={styles.body} keyboardShouldPersistTaps="handled">
          <View style={styles.field}>
            <Text style={[styles.label, { color: theme.textSub }]}>Current password</Text>
            <TextInput
              style={[styles.input, { backgroundColor: theme.inputBg, color: theme.textMain }]}
              secureTextEntry
              value={current}
              onChangeText={setCurrent}
              placeholder="Current password"
              placeholderTextColor={theme.textSub}
              autoCapitalize="none"
            />
          </View>
          <View style={styles.field}>
            <Text style={[styles.label, { color: theme.textSub }]}>New password</Text>
            <TextInput
              style={[styles.input, { backgroundColor: theme.inputBg, color: theme.textMain }]}
              secureTextEntry
              value={next}
              onChangeText={setNext}
              placeholder="New password"
              placeholderTextColor={theme.textSub}
              autoCapitalize="none"
            />
          </View>
          <View style={styles.field}>
            <Text style={[styles.label, { color: theme.textSub }]}>Confirm new password</Text>
            <TextInput
              style={[styles.input, { backgroundColor: theme.inputBg, color: theme.textMain }]}
              secureTextEntry
              value={confirm}
              onChangeText={setConfirm}
              placeholder="Confirm new password"
              placeholderTextColor={theme.textSub}
              autoCapitalize="none"
            />
          </View>

          <Text style={[styles.hint, { color: theme.textSub }]}>
            Use 8-12 characters with an uppercase letter, a number, and a special character.
          </Text>

          {error ? <Text style={[styles.error, { color: theme.danger }]}>{error}</Text> : null}

          <TouchableOpacity
            style={[styles.button, { backgroundColor: theme.accent }, submitting && { opacity: 0.6 }]}
            onPress={handleSave}
            disabled={submitting}
            activeOpacity={0.85}
          >
            <Text style={styles.buttonText}>{submitting ? 'Saving...' : 'Save'}</Text>
          </TouchableOpacity>
        </ScrollView>
      </KeyboardAvoidingView>
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
  back: { width: 40, height: 32, alignItems: 'center', justifyContent: 'center' },
  headerTitle: { fontSize: 18, fontWeight: '700' },
  body: { padding: 20 },
  field: { marginBottom: 16 },
  label: { fontSize: 13, fontWeight: '600', marginBottom: 7 },
  input: { borderRadius: 12, paddingVertical: 14, paddingHorizontal: 16, fontSize: 15 },
  hint: { fontSize: 12, lineHeight: 18, marginBottom: 8 },
  error: { fontSize: 13, fontWeight: '600', marginBottom: 8 },
  button: { borderRadius: 12, paddingVertical: 16, alignItems: 'center', marginTop: 12 },
  buttonText: { color: '#FFFFFF', fontSize: 16, fontWeight: '700' },
});
