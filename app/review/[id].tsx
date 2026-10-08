import { Ionicons } from '@expo/vector-icons';
import { useLocalSearchParams, useRouter } from 'expo-router';
import React, { useState } from 'react';
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
  View,
} from 'react-native';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import { useTheme } from '../../lib/theme';
import { isUnauthorized, submitReview } from '../../services/api';

export default function ReviewScreen() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { colors, isDark } = useTheme();
  const params = useLocalSearchParams<{ id: string; title?: string }>();
  const listingId = Number(params.id);
  const listingTitle = typeof params.title === 'string' ? params.title : 'this experience';

  const [rating, setRating] = useState(0);
  const [comment, setComment] = useState('');
  const [title, setTitle] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const handleSubmit = async () => {
    if (rating < 1) {
      Alert.alert('Add a rating', 'Please tap the stars to rate your experience.');
      return;
    }
    if (comment.trim().length < 3) {
      Alert.alert('Write a review', 'Please share a few words about your experience.');
      return;
    }
    setSubmitting(true);
    try {
      await submitReview({ listing_id: listingId, rating, comment: comment.trim(), title: title.trim() });
      Alert.alert('Thank you!', 'Your review has been posted.', [
        { text: 'OK', onPress: () => router.back() },
      ]);
    } catch (e) {
      if (isUnauthorized(e)) {
        Alert.alert('Session expired', 'Please sign in again to leave a review.');
      } else {
        Alert.alert('Could not post review', e instanceof Error ? e.message : 'Please try again.');
      }
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <SafeAreaView style={[styles.container, { backgroundColor: colors.bgAlt }]} edges={['left', 'right']}>
      <StatusBar barStyle={isDark ? 'light-content' : 'dark-content'} />

      <View style={[styles.header, { backgroundColor: colors.card, borderBottomColor: colors.border, paddingTop: insets.top + 8 }]}>
        <TouchableOpacity onPress={() => router.back()} style={styles.iconBtn} activeOpacity={0.7}>
          <Ionicons name="chevron-back" size={26} color={colors.text} />
        </TouchableOpacity>
        <Text style={[styles.headerTitle, { color: colors.text }]} numberOfLines={1}>Rate your guide</Text>
        <View style={styles.iconBtn} />
      </View>

      <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
        <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
          <Text style={[styles.listingTitle, { color: colors.text }]}>{listingTitle}</Text>
          <Text style={[styles.hint, { color: colors.textSub }]}>
            How was your experience? Your feedback helps other travelers and your guide.
          </Text>

          <View style={styles.starsRow}>
            {[1, 2, 3, 4, 5].map((star) => (
              <TouchableOpacity key={star} activeOpacity={0.7} onPress={() => setRating(star)} style={styles.star}>
                <Ionicons
                  name={star <= rating ? 'star' : 'star-outline'}
                  size={40}
                  color={star <= rating ? '#F6B100' : colors.textMute}
                />
              </TouchableOpacity>
            ))}
          </View>

          <Text style={[styles.label, { color: colors.textSub }]}>Title (optional)</Text>
          <TextInput
            style={[styles.input, { backgroundColor: colors.card, color: colors.text, borderColor: colors.border }]}
            placeholder="e.g. Amazing island hopping tour"
            placeholderTextColor={colors.textMute}
            value={title}
            onChangeText={setTitle}
            maxLength={80}
          />

          <Text style={[styles.label, { color: colors.textSub }]}>Your review</Text>
          <TextInput
            style={[styles.input, styles.textArea, { backgroundColor: colors.card, color: colors.text, borderColor: colors.border }]}
            placeholder="Tell others what made your tour great (or what could be better)..."
            placeholderTextColor={colors.textMute}
            value={comment}
            onChangeText={setComment}
            multiline
            maxLength={1000}
          />

          <TouchableOpacity
            style={[styles.submitBtn, { backgroundColor: colors.primary }, submitting && { opacity: 0.6 }]}
            onPress={handleSubmit}
            disabled={submitting}
            activeOpacity={0.85}
          >
            {submitting ? (
              <ActivityIndicator color="#FFFFFF" />
            ) : (
              <Text style={styles.submitText}>Post review</Text>
            )}
          </TouchableOpacity>
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1 },
  header: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: 8, paddingBottom: 12, borderBottomWidth: StyleSheet.hairlineWidth },
  iconBtn: { width: 40, height: 38, alignItems: 'center', justifyContent: 'center' },
  headerTitle: { fontSize: 18, fontWeight: '800', flex: 1, textAlign: 'center' },
  content: { padding: 20, paddingBottom: 40 },
  listingTitle: { fontSize: 20, fontWeight: '900', marginBottom: 6 },
  hint: { fontSize: 13, lineHeight: 19, marginBottom: 18 },
  starsRow: { flexDirection: 'row', justifyContent: 'center', marginBottom: 22 },
  star: { paddingHorizontal: 6 },
  label: { fontSize: 13, fontWeight: '700', marginBottom: 6, marginTop: 8 },
  input: { borderRadius: 12, borderWidth: 1, paddingHorizontal: 14, paddingVertical: 12, fontSize: 15 },
  textArea: { minHeight: 130, textAlignVertical: 'top' },
  submitBtn: { borderRadius: 14, paddingVertical: 16, alignItems: 'center', marginTop: 24 },
  submitText: { color: '#FFFFFF', fontSize: 16, fontWeight: '800' },
});
