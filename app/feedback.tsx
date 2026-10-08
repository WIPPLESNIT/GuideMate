import { Ionicons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import React, { useEffect, useState } from 'react';
import {
    ActivityIndicator,
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
import { getSession } from '../lib/authStore';
import { FeedbackCategory, submitFeedback } from '../services/api';

const CATEGORIES: { key: FeedbackCategory; label: string; icon: keyof typeof Ionicons.glyphMap }[] = [
  { key: 'general', label: 'General', icon: 'chatbubble-ellipses-outline' },
  { key: 'bug', label: 'Bug', icon: 'bug-outline' },
  { key: 'feature', label: 'Feature', icon: 'bulb-outline' },
  { key: 'praise', label: 'Praise', icon: 'heart-outline' },
];

export default function FeedbackScreen() {
  const router = useRouter();
  const isDark = useColorScheme() === 'dark';
  const insets = useSafeAreaInsets();

  const [loggedIn, setLoggedIn] = useState(false);
  const [rating, setRating] = useState(0);
  const [category, setCategory] = useState<FeedbackCategory>('general');
  const [message, setMessage] = useState('');
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    getSession().then((s) => setLoggedIn(!!s));
  }, []);

  const theme = {
    bg: isDark ? '#0F1012' : '#F2F3F5',
    card: isDark ? '#1E2029' : '#FFFFFF',
    border: isDark ? '#2A2D38' : '#ECECEC',
    textMain: isDark ? '#FFFFFF' : '#1A1A1A',
    textSub: isDark ? '#9CA3AF' : '#8A8A8A',
    inputBg: isDark ? '#15161A' : '#F6F7F9',
    accent: '#22C55E',
    star: '#F59E0B',
    chipOffText: isDark ? '#CBD5E1' : '#475569',
  };

  const submit = async () => {
    const trimmed = message.trim();
    if (trimmed.length < 5) {
      Alert.alert('Add a little more', 'Please tell us a bit more so we can act on your feedback.');
      return;
    }
    if (!loggedIn && email.trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim())) {
      Alert.alert('Invalid email', 'Please enter a valid email address, or leave it blank.');
      return;
    }
    setSubmitting(true);
    try {
      const res = await submitFeedback({
        message: trimmed,
        rating: rating || undefined,
        category,
        name: !loggedIn ? name.trim() || undefined : undefined,
        email: !loggedIn ? email.trim() || undefined : undefined,
      });
      Alert.alert('Thank you!', res.message ?? 'Your feedback has been sent.', [
        { text: 'OK', onPress: () => router.back() },
      ]);
    } catch (e) {
      Alert.alert('Could not send', e instanceof Error ? e.message : 'Please try again.');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <SafeAreaView style={[styles.container, { backgroundColor: theme.bg }]} edges={['left', 'right']}>
      <StatusBar barStyle={isDark ? 'light-content' : 'dark-content'} backgroundColor={theme.card} />

      <View style={[styles.header, { backgroundColor: theme.card, borderBottomColor: theme.border, paddingTop: insets.top + 12 }]}>
        <TouchableOpacity onPress={() => router.back()} style={styles.headerBack} activeOpacity={0.7}>
          <Ionicons name="chevron-back" size={26} color={theme.textMain} />
        </TouchableOpacity>
        <Text style={[styles.headerTitle, { color: theme.textMain }]}>Leave feedback</Text>
        {loggedIn ? (
          <TouchableOpacity onPress={() => router.push('/my-feedback')} style={styles.headerBack} activeOpacity={0.7}>
            <Ionicons name="time-outline" size={23} color={theme.accent} />
          </TouchableOpacity>
        ) : (
          <View style={styles.headerBack} />
        )}
      </View>

      <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
        <ScrollView
          contentContainerStyle={{ padding: 16, paddingBottom: 40 }}
          keyboardShouldPersistTaps="handled"
          showsVerticalScrollIndicator={false}
        >
          <Text style={[styles.intro, { color: theme.textSub }]}>
            How is your experience with GuideMate? Your feedback helps us make the app better for every traveler.
          </Text>

          <Text style={[styles.label, { color: theme.textMain }]}>Your rating</Text>
          <View style={styles.starsRow}>
            {[1, 2, 3, 4, 5].map((n) => (
              <TouchableOpacity key={n} onPress={() => setRating(n === rating ? 0 : n)} activeOpacity={0.7} hitSlop={{ top: 8, bottom: 8, left: 4, right: 4 }}>
                <Ionicons
                  name={n <= rating ? 'star' : 'star-outline'}
                  size={38}
                  color={n <= rating ? theme.star : theme.textSub}
                  style={{ marginRight: 6 }}
                />
              </TouchableOpacity>
            ))}
          </View>

          <Text style={[styles.label, { color: theme.textMain }]}>What is this about?</Text>
          <View style={styles.chipsRow}>
            {CATEGORIES.map((c) => {
              const active = c.key === category;
              return (
                <TouchableOpacity
                  key={c.key}
                  style={[
                    styles.chip,
                    { backgroundColor: active ? theme.accent : theme.card, borderColor: active ? theme.accent : theme.border },
                  ]}
                  activeOpacity={0.8}
                  onPress={() => setCategory(c.key)}
                >
                  <Ionicons name={c.icon} size={16} color={active ? '#FFFFFF' : theme.chipOffText} style={{ marginRight: 6 }} />
                  <Text style={[styles.chipText, { color: active ? '#FFFFFF' : theme.chipOffText }]}>{c.label}</Text>
                </TouchableOpacity>
              );
            })}
          </View>

          <Text style={[styles.label, { color: theme.textMain }]}>Your feedback</Text>
          <TextInput
            style={[styles.textArea, { backgroundColor: theme.card, borderColor: theme.border, color: theme.textMain }]}
            placeholder="Tell us what you love, or what we can improve..."
            placeholderTextColor={theme.textSub}
            value={message}
            onChangeText={setMessage}
            multiline
            textAlignVertical="top"
            maxLength={2000}
          />
          <Text style={[styles.counter, { color: theme.textSub }]}>{message.length}/2000</Text>

          {!loggedIn ? (
            <>
              <Text style={[styles.label, { color: theme.textMain }]}>Your details (optional)</Text>
              <TextInput
                style={[styles.input, { backgroundColor: theme.card, borderColor: theme.border, color: theme.textMain }]}
                placeholder="Name"
                placeholderTextColor={theme.textSub}
                value={name}
                onChangeText={setName}
                maxLength={120}
              />
              <TextInput
                style={[styles.input, { backgroundColor: theme.card, borderColor: theme.border, color: theme.textMain, marginTop: 10 }]}
                placeholder="Email (so we can reply)"
                placeholderTextColor={theme.textSub}
                value={email}
                onChangeText={setEmail}
                keyboardType="email-address"
                autoCapitalize="none"
                autoCorrect={false}
              />
            </>
          ) : null}

          <TouchableOpacity
            style={[styles.submit, { backgroundColor: theme.accent }, submitting && { opacity: 0.6 }]}
            activeOpacity={0.85}
            onPress={submit}
            disabled={submitting}
          >
            {submitting ? (
              <ActivityIndicator color="#FFFFFF" />
            ) : (
              <>
                <Ionicons name="send" size={18} color="#FFFFFF" style={{ marginRight: 8 }} />
                <Text style={styles.submitText}>Send feedback</Text>
              </>
            )}
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
  headerBack: { width: 40, height: 32, alignItems: 'center', justifyContent: 'center' },
  headerTitle: { fontSize: 18, fontWeight: '700' },
  intro: { fontSize: 14, lineHeight: 20, marginBottom: 20 },
  label: { fontSize: 15, fontWeight: '700', marginBottom: 10, marginTop: 6 },
  starsRow: { flexDirection: 'row', marginBottom: 18 },
  chipsRow: { flexDirection: 'row', flexWrap: 'wrap', marginBottom: 12 },
  chip: {
    flexDirection: 'row',
    alignItems: 'center',
    borderWidth: 1,
    borderRadius: 22,
    paddingHorizontal: 14,
    paddingVertical: 9,
    marginRight: 8,
    marginBottom: 8,
  },
  chipText: { fontSize: 13.5, fontWeight: '600' },
  textArea: {
    borderWidth: 1,
    borderRadius: 14,
    padding: 14,
    minHeight: 130,
    fontSize: 15,
  },
  counter: { fontSize: 12, textAlign: 'right', marginTop: 6, marginBottom: 6 },
  input: {
    borderWidth: 1,
    borderRadius: 12,
    paddingHorizontal: 14,
    paddingVertical: 13,
    fontSize: 15,
  },
  submit: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 14,
    paddingVertical: 16,
    marginTop: 24,
  },
  submitText: { color: '#FFFFFF', fontSize: 16, fontWeight: '700' },
});
