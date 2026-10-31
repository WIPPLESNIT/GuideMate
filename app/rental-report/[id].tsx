import { Ionicons } from '@expo/vector-icons';
import * as ImagePicker from 'expo-image-picker';
import { useLocalSearchParams, useRouter } from 'expo-router';
import React, { useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Image,
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

const MAX_PHOTOS = 4;

export default function RentalReportScreen() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { colors, isDark } = useTheme();
  const params = useLocalSearchParams<{ id: string; title?: string }>();
  const rentalId = Number(params.id);
  const vehicleTitle = typeof params.title === 'string' ? params.title : 'this reservation';

  const [problemType, setProblemType] = useState('');
  const [description, setDescription] = useState('');
  const [photos, setPhotos] = useState<string[]>([]);
  const [submitting, setSubmitting] = useState(false);

  // ── Image Attachment Handling ──

  const promptImageSource = () => {
    if (photos.length >= MAX_PHOTOS) {
      Alert.alert('Limit reached', `You can attach up to ${MAX_PHOTOS} photos as evidence.`);
      return;
    }

    Alert.alert('Attach Picture', 'Choose how you want to attach evidence:', [
      { text: 'Take Photo', onPress: takePhotoWithCamera },
      { text: 'Choose from Library', onPress: pickImageFromLibrary },
      { text: 'Cancel', style: 'cancel' },
    ]);
  };

  const takePhotoWithCamera = async () => {
    try {
      const perm = await ImagePicker.requestCameraPermissionsAsync();
      if (!perm.granted) {
        Alert.alert('Camera permission needed', 'Please allow camera access in your device settings to take a photo.');
        return;
      }
      const result = await ImagePicker.launchCameraAsync({
        mediaTypes: ['images'],
        quality: 0.7,
      });
      if (result.canceled || !result.assets || result.assets.length === 0) return;
      const uri = result.assets[0].uri;
      if (uri) {
        setPhotos((prev) => [...prev, uri].slice(0, MAX_PHOTOS));
      }
    } catch (e: any) {
      Alert.alert('Camera Error', e?.message || 'Could not open camera.');
    }
  };

  const pickImageFromLibrary = async () => {
    try {
      const perm = await ImagePicker.requestMediaLibraryPermissionsAsync();
      if (!perm.granted) {
        Alert.alert('Permission needed', 'Please allow photo library access in your device settings to attach evidence.');
        return;
      }
      const remaining = MAX_PHOTOS - photos.length;
      const result = await ImagePicker.launchImageLibraryAsync({
        mediaTypes: ['images'],
        quality: 0.7,
        allowsMultipleSelection: true,
        selectionLimit: remaining,
      });
      if (result.canceled || !result.assets || result.assets.length === 0) return;
      const uris = result.assets.map((a) => a.uri).filter(Boolean);
      setPhotos((prev) => [...prev, ...uris].slice(0, MAX_PHOTOS));
    } catch (e: any) {
      Alert.alert('Gallery Error', e?.message || 'Could not open photo gallery.');
    }
  };

  const handlePhotoPress = (index: number, uri: string) => {
    Alert.alert('Photo Options', 'Choose an action for this attached picture:', [
      {
        text: 'Replace Photo',
        onPress: () => promptReplaceImage(index),
      },
      {
        text: 'Remove Photo',
        style: 'destructive',
        onPress: () => removePhoto(uri),
      },
      { text: 'Cancel', style: 'cancel' },
    ]);
  };

  const promptReplaceImage = (index: number) => {
    Alert.alert('Replace Picture', 'Choose a new picture for this slot:', [
      {
        text: 'Take Photo',
        onPress: async () => {
          try {
            const perm = await ImagePicker.requestCameraPermissionsAsync();
            if (!perm.granted) {
              Alert.alert('Permission needed', 'Please allow camera access in your device settings.');
              return;
            }
            const result = await ImagePicker.launchCameraAsync({ mediaTypes: ['images'], quality: 0.7 });
            if (result.canceled || !result.assets || result.assets.length === 0) return;
            const newUri = result.assets[0].uri;
            if (newUri) {
              setPhotos((prev) => {
                const next = [...prev];
                next[index] = newUri;
                return next;
              });
            }
          } catch (e: any) {
            Alert.alert('Camera Error', e?.message || 'Could not open camera.');
          }
        },
      },
      {
        text: 'Choose from Library',
        onPress: async () => {
          try {
            const perm = await ImagePicker.requestMediaLibraryPermissionsAsync();
            if (!perm.granted) {
              Alert.alert('Permission needed', 'Please allow photo library access in your device settings.');
              return;
            }
            const result = await ImagePicker.launchImageLibraryAsync({ mediaTypes: ['images'], quality: 0.7 });
            if (result.canceled || !result.assets || result.assets.length === 0) return;
            const newUri = result.assets[0].uri;
            if (newUri) {
              setPhotos((prev) => {
                const next = [...prev];
                next[index] = newUri;
                return next;
              });
            }
          } catch (e: any) {
            Alert.alert('Gallery Error', e?.message || 'Could not open photo gallery.');
          }
        },
      },
      { text: 'Cancel', style: 'cancel' },
    ]);
  };

  const removePhoto = (uri: string) => {
    setPhotos((prev) => prev.filter((p) => p !== uri));
  };

  // ── Form Submission ──

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
      await reportRental({
        rental_id: rentalId,
        problem_type: problemType,
        description: description.trim(),
        photos,
      });

      Alert.alert(
        'Report submitted',
        'Thanks — the rental owner will review your report. If approved, your full payment will be refunded.',
        [{ text: 'OK', onPress: () => router.back() }]
      );
    } catch (e: any) {
      if (isUnauthorized(e)) {
        Alert.alert('Session expired', 'Please sign in again to submit your report.');
      } else {
        const errorMsg = e instanceof Error ? e.message : 'Please check your connection and try again.';
        Alert.alert('Could not submit', errorMsg);
      }
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <SafeAreaView style={[styles.container, { backgroundColor: colors.bgAlt }]} edges={['left', 'right']}>
      <StatusBar barStyle={isDark ? 'light-content' : 'dark-content'} />

      <View style={[styles.header, { backgroundColor: colors.card, borderBottomColor: colors.border, paddingTop: insets.top + 8 }]}>
        <TouchableOpacity onPress={() => router.back()} style={styles.iconBtn} activeOpacity={0.7} accessibilityLabel="Back">
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
                accessibilityRole="radio"
                accessibilityState={{ selected }}
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
            textAlignVertical="top"
          />

          {/* ── Photo Evidence / Image Attachment Control ── */}
          <View style={styles.sectionHeaderRow}>
            <Text style={[styles.label, { color: colors.textSub, marginTop: 0 }]}>
              Attach pictures (optional)
            </Text>
            <Text style={[styles.counterText, { color: colors.textMute }]}>
              {photos.length}/{MAX_PHOTOS}
            </Text>
          </View>
          <Text style={[styles.photoSubhint, { color: colors.textMute }]}>
            Provide photos of vehicle defects, odometer, fuel level, or delivery issues.
          </Text>

          <View style={styles.photoRow}>
            {photos.map((uri, index) => (
              <View key={uri + index} style={styles.photoWrap}>
                <TouchableOpacity
                  activeOpacity={0.85}
                  onPress={() => handlePhotoPress(index, uri)}
                  style={styles.photoTouchable}
                  accessibilityLabel={`Attached picture ${index + 1}. Tap to replace or remove.`}
                >
                  <Image source={{ uri }} style={styles.photo} />
                </TouchableOpacity>
                <TouchableOpacity
                  style={styles.photoRemove}
                  onPress={() => removePhoto(uri)}
                  activeOpacity={0.8}
                  accessibilityLabel="Remove photo"
                >
                  <Ionicons name="close-circle" size={22} color="#EF4444" />
                </TouchableOpacity>
              </View>
            ))}

            {photos.length < MAX_PHOTOS ? (
              <TouchableOpacity
                style={[styles.addPhoto, { borderColor: colors.border, backgroundColor: colors.card }]}
                onPress={promptImageSource}
                activeOpacity={0.8}
                accessibilityRole="button"
                accessibilityLabel="Attach picture evidence"
              >
                <Ionicons name="camera-outline" size={24} color={colors.textSub} />
                <Text style={[styles.addPhotoText, { color: colors.textSub }]}>Attach picture</Text>
              </TouchableOpacity>
            ) : null}
          </View>

          <TouchableOpacity
            style={[styles.submitBtn, { backgroundColor: '#EF4444' }, submitting && { opacity: 0.6 }]}
            onPress={handleSubmit}
            disabled={submitting}
            activeOpacity={0.85}
            accessibilityRole="button"
            accessibilityLabel="Submit report"
          >
            {submitting ? (
              <View style={styles.submittingRow}>
                <ActivityIndicator color="#FFFFFF" size="small" />
                <Text style={[styles.submitText, { marginLeft: 8 }]}>Submitting...</Text>
              </View>
            ) : (
              <Text style={styles.submitText}>Submit report</Text>
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
  title: { fontSize: 18, fontWeight: '900', marginBottom: 6 },
  hint: { fontSize: 13, lineHeight: 19, marginBottom: 16 },
  label: { fontSize: 13, fontWeight: '700', marginBottom: 8, marginTop: 12 },
  sectionHeaderRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginTop: 16, marginBottom: 4 },
  counterText: { fontSize: 12, fontWeight: '600' },
  photoSubhint: { fontSize: 12, lineHeight: 17, marginBottom: 12 },
  typeRow: { flexDirection: 'row', alignItems: 'center', gap: 10, borderWidth: 1.5, borderRadius: 12, paddingVertical: 13, paddingHorizontal: 14, marginBottom: 8 },
  typeText: { fontSize: 14, fontWeight: '600', flex: 1 },
  input: { borderRadius: 12, borderWidth: 1, paddingHorizontal: 14, paddingVertical: 12, fontSize: 15 },
  textArea: { minHeight: 120, textAlignVertical: 'top' },
  photoRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 12 },
  photoWrap: { position: 'relative' },
  photoTouchable: { borderRadius: 12, overflow: 'hidden' },
  photo: { width: 80, height: 80, borderRadius: 12, backgroundColor: '#00000011' },
  photoRemove: { position: 'absolute', top: -8, right: -8, backgroundColor: '#FFFFFF', borderRadius: 11, elevation: 3, shadowColor: '#000', shadowOffset: { width: 0, height: 1 }, shadowOpacity: 0.2, shadowRadius: 2 },
  addPhoto: { width: 80, height: 80, borderRadius: 12, borderWidth: 1.5, borderStyle: 'dashed', alignItems: 'center', justifyContent: 'center', paddingHorizontal: 4 },
  addPhotoText: { fontSize: 10, fontWeight: '700', marginTop: 4, textAlign: 'center' },
  submitBtn: { borderRadius: 14, paddingVertical: 16, alignItems: 'center', marginTop: 24 },
  submittingRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center' },
  submitText: { color: '#FFFFFF', fontSize: 16, fontWeight: '800' },
});
