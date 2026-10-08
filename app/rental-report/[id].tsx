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
import { isUnauthorized, RENTAL_REPORT_TYPES, reportRental } from '../../services/api';

export default function RentalReportScreen() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { colors, isDark } = useTheme();
  const params = useLocalSearchParams<{ id: string; title?: string }>();
  const rentalId = Number(params.id);
  const vehicleTitle = typeof params.title === 'string' ? params.title : 'this reservation';

  const [problemType, setProblemType] = useState('');
  const [description, setDescription] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const handleSubmit = async () => {
    if (!problemType) {
      Alert.alert('Choose a problem', 'Please select what went wrong.');
      return;
    }
    if (description.trim().length < 20) {
      Alert.alert('Add more detail', 'Please describe the problem in at least 20 characters.');
      return;
    }
    setSubmitting(true);
    try {
      await reportRental({ rental_id: rentalId, problem_type: problemType, description: description.trim() });
      Alert.alert(
        'Report submitted',
        'Thanks — the rental owner will review your report. If approved, your full payment will be refunded.',
        [{ text: 'OK', onPress: () => router.back() }]
      );
    } catch (e) {
      if (isUnauthorized(e)) {
        Alert.alert('Session expired', 'Please sign in again to submit your report.');
      } else {
        Alert.alert('Could not submit', e instanceof Error ? e.message : 'Please try again.');
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
        <Text style={[styles.headerTitle, { color: colors.text }]} numberOfLines={1}>Report a problem</Text>
        <View style={styles.iconBtn} />
      </View>

      <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
        <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
          <Text style={[styles.title, { color: colors.text }]}>{vehicleTitle}</Text>
          <Text style={[styles.hint, { color: colors.textSub }]}>
            Tell the rental owner what went wrong. If your report is approved, your full payment is refunded.
          </Text>

          <Text style={[styles.label, { color: colors.textSub }]}>What happened?</Text>
          {RENTAL_REPORT_TYPES.map((opt) => {
            const selected = problemType === opt.value;
            return (
              <TouchableOpacity
                key={opt.value}
                activeOpacity={0.8}
                onPress={() => setProblemType(opt.value)}
                style={[styles.typeRow, { backgroundColor: colors.card, borderColor: selected ? colors.primary : colors.border }]}
              >
                <Ionicons
                  name={selected ? 'radio-button-on' : 'radio-button-off'}
                  size={20}
                  color={selected ? colors.primary : colors.textMute}
                />
                <Text style={[styles.typeText, { color: colors.text }]}>{opt.label}</Text>
              </TouchableOpacity>
            );
          })}

          <Text style={[styles.label, { color: colors.textSub }]}>Describe the problem</Text>
          <TextInput
            style={[styles.input, styles.textArea, { backgroundColor: colors.card, color: colors.text, borderColor: colors.border }]}
            placeholder="Describe what happened in detail (at least 20 characters)..."
            placeholderTextColor={colors.textMute}
            value={description}
            onChangeText={setDescription}
            multiline
            maxLength={1000}
          />

          <TouchableOpacity
            style={[styles.submitBtn, { backgroundColor: '#EF4444' }, submitting && { opacity: 0.6 }]}
            onPress={handleSubmit}
            disabled={submitting}
            activeOpacity={0.85}
          >
            {submitting ? <ActivityIndicator color="#FFFFFF" /> : <Text style={styles.submitText}>Submit report</Text>}
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
  title: { fontSize: 18, fontWeight: '900', marginBottom: 6 },
  hint: { fontSize: 13, lineHeight: 19, marginBottom: 16 },
  label: { fontSize: 13, fontWeight: '700', marginBottom: 8, marginTop: 12 },
  typeRow: { flexDirection: 'row', alignItems: 'center', gap: 10, borderWidth: 1.5, borderRadius: 12, paddingVertical: 13, paddingHorizontal: 14, marginBottom: 8 },
  typeText: { fontSize: 14, fontWeight: '600', flex: 1 },
  input: { borderRadius: 12, borderWidth: 1, paddingHorizontal: 14, paddingVertical: 12, fontSize: 15 },
  textArea: { minHeight: 120, textAlignVertical: 'top' },
  submitBtn: { borderRadius: 14, paddingVertical: 16, alignItems: 'center', marginTop: 24 },
  submitText: { color: '#FFFFFF', fontSize: 16, fontWeight: '800' },
});
