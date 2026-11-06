import { Ionicons } from '@expo/vector-icons';
import * as ImagePicker from 'expo-image-picker';
import { useRouter } from 'expo-router';
import React, { useState } from 'react';
import {
  Alert,
  Image,
  ScrollView,
  StatusBar,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useTheme } from '../../lib/theme';
import { PACKAGE_LOCAL_IMAGES } from '../../services/packageImages';

const PRESET_COVERS: { key: string; label: string }[] = [
  { key: 'kawasan', label: 'Kawasan Falls' },
  { key: 'sardine', label: 'Moalboal Reef' },
  { key: 'whaleshark', label: 'Oslob Sanctuary' },
  { key: 'osmena', label: 'Osmeña Peak' },
  { key: 'bantayan', label: 'Bantayan Beach' },
];

export default function PackageCreateStep1Screen() {
  const router = useRouter();
  const { colors, isDark } = useTheme();

  // Form State for Step 1 - Empty defaults with clear placeholders
  const [title, setTitle] = useState('');
  const [badge] = useState('New Expedition');
  const [destination, setDestination] = useState('');
  const [meetingPoint, setMeetingPoint] = useState('');
  const [tagline, setTagline] = useState('');
  const [days, setDays] = useState(3);
  const [nights, setNights] = useState(2);
  const [maxPax, setMaxPax] = useState(8);
  const [description, setDescription] = useState('');
  const [selectedLocalKey, setSelectedLocalKey] = useState<string>('kawasan');
  const [customCoverUri, setCustomCoverUri] = useState<string | null>(null);

  // Custom Photo Picker
  const pickCustomImage = async () => {
    try {
      const res = await ImagePicker.launchImageLibraryAsync({
        mediaTypes: ['images'],
        allowsEditing: true,
        quality: 0.85,
      });

      if (!res.canceled && res.assets && res.assets.length > 0) {
        setCustomCoverUri(res.assets[0].uri);
      }
    } catch {
      Alert.alert('Image Picker', 'Could not open photo library.');
    }
  };

  const handleNext = () => {
    if (!title.trim()) {
      Alert.alert('Required Field', 'Please enter a package title.');
      return;
    }
    if (!destination.trim()) {
      Alert.alert('Required Field', 'Please enter the primary destination.');
      return;
    }
    if (!description.trim()) {
      Alert.alert('Required Field', 'Please enter the "About This Package" narrative description.');
      return;
    }

    Alert.alert(
      'Step 1 Complete',
      'Package Overview and Basic Information recorded successfully! Proceed to Step 2 (Host Details) when ready.',
      [{ text: 'OK' }]
    );
  };

  return (
    <SafeAreaView style={[styles.container, { backgroundColor: colors.bgAlt }]} edges={['top', 'left', 'right']}>
      <StatusBar barStyle={isDark ? 'light-content' : 'dark-content'} />

      {/* Top Header */}
      <View style={[styles.header, { borderBottomColor: colors.border }]}>
        <TouchableOpacity
          style={[styles.headerBackBtn, { backgroundColor: colors.chipBg }]}
          onPress={() => router.back()}
          activeOpacity={0.7}
        >
          <Ionicons name="arrow-back" size={20} color={colors.text} />
        </TouchableOpacity>

        <View style={{ flex: 1, marginLeft: 12 }}>
          <Text style={[styles.headerTitle, { color: colors.text }]}>Package Builder</Text>
          <Text style={[styles.headerSubtitle, { color: colors.textSub }]}>Step 1: Overview & Basic Info</Text>
        </View>

        <View style={styles.stepIndicator}>
          <Text style={styles.stepIndicatorText}>1 / 5</Text>
        </View>
      </View>

      {/* Stepper Progress Bar */}
      <View style={styles.progressRow}>
        <View style={[styles.progressBar, styles.progressActive]} />
        <View style={[styles.progressBar, { backgroundColor: colors.border }]} />
        <View style={[styles.progressBar, { backgroundColor: colors.border }]} />
        <View style={[styles.progressBar, { backgroundColor: colors.border }]} />
        <View style={[styles.progressBar, { backgroundColor: colors.border }]} />
      </View>

      <ScrollView showsVerticalScrollIndicator={false} contentContainerStyle={{ paddingBottom: 110 }}>
        
        {/* ======================================================== */}
        {/* STEP 1 FORM CONTROLS                                    */}
        {/* ======================================================== */}
        <View style={styles.formContainer}>
          <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginBottom: 4 }}>
            <Text style={[styles.formHeader, { color: colors.text, marginBottom: 0 }]}>Step 1: Basic Information</Text>
            <View style={{ backgroundColor: '#FFF7ED', borderColor: '#FED7AA', borderWidth: 1, paddingVertical: 3, paddingHorizontal: 9, borderRadius: 999 }}>
              <Text style={{ color: '#C2410C', fontSize: 11, fontWeight: '800' }}>✨ {badge}</Text>
            </View>
          </View>
          <Text style={[styles.formSubHeader, { color: colors.textSub }]}>
            Configure the package title, destination, trip duration, narrative description, and cover photo.
          </Text>

          {/* Package Title */}
          <Text style={[styles.label, { color: colors.text, marginTop: 16 }]}>
            Package Title <Text style={{ color: '#EF4444' }}>*</Text>
          </Text>
          <TextInput
            style={[styles.input, { backgroundColor: colors.card, borderColor: colors.border, color: colors.text }]}
            value={title}
            onChangeText={setTitle}
            placeholder="e.g. 3D2N South Cebu Expedition: Canyoneering, Moalboal Reef & NMAX Scooter"
            placeholderTextColor={colors.textMute}
          />

          {/* Destination */}
          <Text style={[styles.label, { color: colors.text, marginTop: 14 }]}>
            Primary Destination & Region <Text style={{ color: '#EF4444' }}>*</Text>
          </Text>
          <TextInput
            style={[styles.input, { backgroundColor: colors.card, borderColor: colors.border, color: colors.text }]}
            value={destination}
            onChangeText={setDestination}
            placeholder="e.g. Moalboal & Badian, South Cebu"
            placeholderTextColor={colors.textMute}
          />

          {/* Meeting Point */}
          <Text style={[styles.label, { color: colors.text, marginTop: 14 }]}>Jump-off / Meeting Point</Text>
          <TextInput
            style={[styles.input, { backgroundColor: colors.card, borderColor: colors.border, color: colors.text }]}
            value={meetingPoint}
            onChangeText={setMeetingPoint}
            placeholder="e.g. Panagsama Beach, Moalboal or Mactan Airport"
            placeholderTextColor={colors.textMute}
          />

          {/* Tagline / Hook */}
          <Text style={[styles.label, { color: colors.text, marginTop: 14 }]}>Package Tagline / Hook</Text>
          <TextInput
            style={[styles.input, { backgroundColor: colors.card, borderColor: colors.border, color: colors.text }]}
            value={tagline}
            onChangeText={setTagline}
            placeholder="e.g. All-inclusive 3-day South Cebu escape with Kawasan canyoneering, sea turtles, resort stay & NMAX scooter."
            placeholderTextColor={colors.textMute}
          />

          {/* Duration Counters */}
          <View style={[styles.specsControlCard, { backgroundColor: colors.card, borderColor: colors.border }]}>
            <Text style={[styles.specControlHeading, { color: colors.text }]}>Trip Duration & Capacity</Text>
            
            <View style={styles.counterRow}>
              <View style={styles.counterBox}>
                <Text style={[styles.counterLabel, { color: colors.textSub }]}>Days</Text>
                <View style={styles.stepperWrap}>
                  <TouchableOpacity
                    style={[styles.stepperBtn, { backgroundColor: colors.chipBg }]}
                    onPress={() => setDays((d) => Math.max(1, d - 1))}
                  >
                    <Text style={[styles.stepperBtnText, { color: colors.text }]}>−</Text>
                  </TouchableOpacity>
                  <Text style={[styles.stepperValue, { color: colors.text }]}>{days}</Text>
                  <TouchableOpacity
                    style={[styles.stepperBtn, { backgroundColor: colors.chipBg }]}
                    onPress={() => setDays((d) => Math.min(14, d + 1))}
                  >
                    <Text style={[styles.stepperBtnText, { color: colors.text }]}>+</Text>
                  </TouchableOpacity>
                </View>
              </View>

              <View style={styles.counterBox}>
                <Text style={[styles.counterLabel, { color: colors.textSub }]}>Nights</Text>
                <View style={styles.stepperWrap}>
                  <TouchableOpacity
                    style={[styles.stepperBtn, { backgroundColor: colors.chipBg }]}
                    onPress={() => setNights((n) => Math.max(0, n - 1))}
                  >
                    <Text style={[styles.stepperBtnText, { color: colors.text }]}>−</Text>
                  </TouchableOpacity>
                  <Text style={[styles.stepperValue, { color: colors.text }]}>{nights}</Text>
                  <TouchableOpacity
                    style={[styles.stepperBtn, { backgroundColor: colors.chipBg }]}
                    onPress={() => setNights((n) => Math.min(14, n + 1))}
                  >
                    <Text style={[styles.stepperBtnText, { color: colors.text }]}>+</Text>
                  </TouchableOpacity>
                </View>
              </View>

              <View style={styles.counterBox}>
                <Text style={[styles.counterLabel, { color: colors.textSub }]}>Max Pax</Text>
                <View style={styles.stepperWrap}>
                  <TouchableOpacity
                    style={[styles.stepperBtn, { backgroundColor: colors.chipBg }]}
                    onPress={() => setMaxPax((p) => Math.max(2, p - 1))}
                  >
                    <Text style={[styles.stepperBtnText, { color: colors.text }]}>−</Text>
                  </TouchableOpacity>
                  <Text style={[styles.stepperValue, { color: colors.text }]}>{maxPax}</Text>
                  <TouchableOpacity
                    style={[styles.stepperBtn, { backgroundColor: colors.chipBg }]}
                    onPress={() => setMaxPax((p) => Math.min(30, p + 1))}
                  >
                    <Text style={[styles.stepperBtnText, { color: colors.text }]}>+</Text>
                  </TouchableOpacity>
                </View>
              </View>
            </View>
          </View>

          {/* About This Package Narrative */}
          <View style={{ flexDirection: 'row', justifyContent: 'space-between', marginTop: 16, alignItems: 'center' }}>
            <Text style={[styles.label, { color: colors.text, marginBottom: 0 }]}>
              About This Package (Narrative) <Text style={{ color: '#EF4444' }}>*</Text>
            </Text>
            <Text style={{ fontSize: 11, color: colors.textMute }}>{description.length} chars</Text>
          </View>
          <TextInput
            style={[
              styles.textArea,
              { backgroundColor: colors.card, borderColor: colors.border, color: colors.text },
            ]}
            value={description}
            onChangeText={setDescription}
            multiline
            numberOfLines={6}
            placeholder="Describe the overall package experience, atmosphere, inclusions, and what makes it extraordinary..."
            placeholderTextColor={colors.textMute}
          />

          {/* Cover Photo Selection */}
          <Text style={[styles.label, { color: colors.text, marginTop: 16 }]}>Hero Cover Photo</Text>
          <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.photoChoiceRow}>
            {PRESET_COVERS.map((preset) => {
              const isSelected = selectedLocalKey === preset.key && !customCoverUri;
              const source = PACKAGE_LOCAL_IMAGES[preset.key];
              return (
                <TouchableOpacity
                  key={preset.key}
                  style={[styles.presetPhotoCard, isSelected && styles.presetPhotoSelected]}
                  onPress={() => {
                    setSelectedLocalKey(preset.key);
                    setCustomCoverUri(null);
                  }}
                  activeOpacity={0.8}
                >
                  <Image source={source} style={styles.presetPhotoImg} />
                  <View style={styles.presetPhotoLabelWrap}>
                    <Text style={styles.presetPhotoLabel} numberOfLines={1}>{preset.label}</Text>
                  </View>
                </TouchableOpacity>
              );
            })}

            {/* Custom Photo Button */}
            <TouchableOpacity
              style={[
                styles.uploadPhotoBtn,
                customCoverUri ? styles.presetPhotoSelected : { borderColor: colors.border, backgroundColor: colors.card },
              ]}
              onPress={pickCustomImage}
              activeOpacity={0.8}
            >
              {customCoverUri ? (
                <Image source={{ uri: customCoverUri }} style={styles.presetPhotoImg} />
              ) : (
                <View style={{ alignItems: 'center', justifyContent: 'center', padding: 8 }}>
                  <Ionicons name="camera-outline" size={24} color="#7C3AED" />
                  <Text style={{ fontSize: 11, color: '#7C3AED', fontWeight: '700', marginTop: 4 }}>
                    Custom Photo
                  </Text>
                </View>
              )}
            </TouchableOpacity>
          </ScrollView>
        </View>
      </ScrollView>

      {/* Floating Bottom Action Bar */}
      <View style={[styles.bottomBar, { backgroundColor: colors.card, borderTopColor: colors.border }]}>
        <TouchableOpacity
          style={[styles.cancelBtn, { borderColor: colors.border }]}
          onPress={() => {
            Alert.alert('Discard Package', 'Are you sure you want to cancel? Any unsaved package information will be discarded.', [
              { text: 'Keep Editing', style: 'cancel' },
              { text: 'Discard', style: 'destructive', onPress: () => router.back() },
            ]);
          }}
          activeOpacity={0.8}
        >
          <Text style={[styles.cancelBtnText, { color: colors.textSub }]}>Cancel</Text>
        </TouchableOpacity>

        <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8, flex: 2 }}>
          <TouchableOpacity
            style={[styles.backBtn, { borderColor: colors.border }]}
            onPress={() => router.back()}
            activeOpacity={0.8}
          >
            <Text style={[styles.backBtnText, { color: colors.text }]}>Back</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={styles.nextBtn}
            onPress={handleNext}
            activeOpacity={0.85}
          >
            <Text style={styles.nextBtnText}>Next</Text>
          </TouchableOpacity>
        </View>
      </View>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 16,
    paddingVertical: 12,
    borderBottomWidth: StyleSheet.hairlineWidth,
  },
  headerBackBtn: {
    width: 36,
    height: 36,
    borderRadius: 18,
    alignItems: 'center',
    justifyContent: 'center',
  },
  headerTitle: {
    fontSize: 16,
    fontWeight: '800',
    letterSpacing: -0.2,
  },
  headerSubtitle: {
    fontSize: 12,
    marginTop: 1,
  },
  stepIndicator: {
    backgroundColor: '#EDE9FE',
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 12,
  },
  stepIndicatorText: {
    fontSize: 12,
    fontWeight: '800',
    color: '#7C3AED',
  },
  progressRow: {
    flexDirection: 'row',
    gap: 6,
    paddingHorizontal: 16,
    paddingVertical: 8,
  },
  progressBar: {
    flex: 1,
    height: 4,
    borderRadius: 2,
  },
  progressActive: {
    backgroundColor: '#7C3AED',
  },

  // Form Controls
  formContainer: {
    paddingHorizontal: 16,
    marginTop: 16,
  },
  formHeader: {
    fontSize: 16,
    fontWeight: '800',
    letterSpacing: -0.2,
  },
  formSubHeader: {
    fontSize: 12,
    marginTop: 2,
    marginBottom: 12,
  },
  label: {
    fontSize: 13,
    fontWeight: '700',
    marginBottom: 6,
  },
  input: {
    borderWidth: 1,
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 10,
    fontSize: 13,
  },
  textArea: {
    borderWidth: 1,
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 10,
    fontSize: 13,
    textAlignVertical: 'top',
    height: 120,
    marginTop: 6,
  },
  chipRow: {
    gap: 8,
    paddingVertical: 2,
  },
  chip: {
    borderWidth: 1,
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 20,
  },
  chipActive: {
    backgroundColor: '#7C3AED',
    borderColor: '#6D28D9',
  },
  chipBadgeActive: {
    backgroundColor: '#F59E0B',
    borderColor: '#D97706',
  },
  chipText: {
    fontSize: 12,
    fontWeight: '700',
  },
  chipTextActive: {
    color: '#FFFFFF',
  },

  // Specs Matrix Controller Card
  specsControlCard: {
    borderWidth: 1,
    borderRadius: 12,
    padding: 12,
    marginTop: 16,
  },
  specControlHeading: {
    fontSize: 13,
    fontWeight: '800',
    marginBottom: 10,
  },
  counterRow: {
    flexDirection: 'row',
    gap: 8,
  },
  counterBox: {
    flex: 1,
    alignItems: 'center',
  },
  counterLabel: {
    fontSize: 11,
    fontWeight: '600',
    marginBottom: 4,
  },
  stepperWrap: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  stepperBtn: {
    width: 28,
    height: 28,
    borderRadius: 14,
    alignItems: 'center',
    justifyContent: 'center',
  },
  stepperBtnText: {
    fontSize: 15,
    fontWeight: '800',
  },
  stepperValue: {
    fontSize: 14,
    fontWeight: '800',
    minWidth: 20,
    textAlign: 'center',
  },

  // Photo Selection
  photoChoiceRow: {
    gap: 10,
    paddingVertical: 4,
  },
  presetPhotoCard: {
    width: 100,
    height: 80,
    borderRadius: 10,
    overflow: 'hidden',
    borderWidth: 1.5,
    borderColor: 'transparent',
  },
  presetPhotoSelected: {
    borderColor: '#7C3AED',
  },
  presetPhotoImg: {
    width: '100%',
    height: '100%',
    resizeMode: 'cover',
  },
  presetPhotoLabelWrap: {
    position: 'absolute',
    bottom: 0,
    left: 0,
    right: 0,
    backgroundColor: 'rgba(0,0,0,0.6)',
    paddingVertical: 2,
    paddingHorizontal: 4,
  },
  presetPhotoLabel: {
    color: '#FFFFFF',
    fontSize: 9,
    fontWeight: '700',
    textAlign: 'center',
  },
  uploadPhotoBtn: {
    width: 100,
    height: 80,
    borderRadius: 10,
    borderWidth: 1.5,
    borderStyle: 'dashed',
    alignItems: 'center',
    justifyContent: 'center',
    overflow: 'hidden',
  },

  // Floating Bottom Bar
  bottomBar: {
    position: 'absolute',
    bottom: 0,
    left: 0,
    right: 0,
    flexDirection: 'row',
    gap: 12,
    paddingHorizontal: 16,
    paddingVertical: 12,
    borderTopWidth: StyleSheet.hairlineWidth,
  },
  cancelBtn: {
    paddingVertical: 12,
    paddingHorizontal: 16,
    borderRadius: 25,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  cancelBtnText: {
    fontSize: 13,
    fontWeight: '600',
  },
  backBtn: {
    flex: 1,
    paddingVertical: 12,
    borderRadius: 25,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  backBtnText: {
    fontSize: 13,
    fontWeight: '600',
  },
  nextBtn: {
    flex: 1.4,
    backgroundColor: '#10B981',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 12,
    borderRadius: 25,
    shadowColor: '#10B981',
    shadowOffset: { width: 0, height: 3 },
    shadowOpacity: 0.3,
    shadowRadius: 6,
    elevation: 3,
  },
  nextBtnText: {
    color: '#FFFFFF',
    fontSize: 13,
    fontWeight: '800',
  },
});
