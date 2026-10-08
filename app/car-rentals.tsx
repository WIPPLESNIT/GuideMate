import { Ionicons } from '@expo/vector-icons';
import * as ImagePicker from 'expo-image-picker';
import { useRouter } from 'expo-router';
import React, { useCallback, useEffect, useMemo, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  FlatList,
  Image,
  KeyboardAvoidingView,
  Modal,
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
import CalendarPicker from '../components/CalendarPicker';
import { useTheme } from '../lib/theme';
import { ApiRentalVehicle, createRentalRequest, getRentalVehicles, isUnauthorized, PaymentMethod } from '../services/api';

const TIME_SLOTS = ['07:00', '08:00', '09:00', '10:00', '11:00', '13:00', '15:00', '17:00'];

const ID_TYPES = [
  "Driver's License",
  'Passport',
  'National ID (PhilSys)',
  'UMID',
  'Postal ID',
  'Other government ID',
];

const QR_EWALLET = require('../assets/images/payment/qr-ewallet.png');
const QR_INSTAPAY = require('../assets/images/payment/qr-instapay.png');

// Each rental unit maps 1:1 to a real model, so these photos are model-accurate:
// scooter = Honda Click 125i, motorcycle = KTM 390 Adventure, ebike = City E-Bike,
// sedan = Toyota Vios, suv = Kia Sportage, van = Toyota HiAce Grandia.
const LOCAL_IMAGES: Record<string, number> = {
  scooter: require('../assets/images/rentals/scooter.jpg'),
  motorcycle: require('../assets/images/rentals/motorcycle.png'),
  ebike: require('../assets/images/rentals/ebike.jpg'),
  sedan: require('../assets/images/rentals/sedan.jpg'),
  suv: require('../assets/images/rentals/suv.jpg'),
  van: require('../assets/images/rentals/van.jpg'),
};

export default function CarRentalsScreen() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { colors, isDark, shadow } = useTheme();

  const [vehicles, setVehicles] = useState<ApiRentalVehicle[]>([]);
  const [loading, setLoading] = useState(true);
  const [selected, setSelected] = useState<ApiRentalVehicle | null>(null);
  const [pickupDate, setPickupDate] = useState('');
  const [pickupTime, setPickupTime] = useState('');
  const [rentalDays, setRentalDays] = useState(1);
  const [contactPhone, setContactPhone] = useState('');
  const [notes, setNotes] = useState('');
  const [submitting, setSubmitting] = useState(false);

  // Verification (valid ID held for the owner)
  const [idType, setIdType] = useState('');
  const [idNumber, setIdNumber] = useState('');
  const [idImage, setIdImage] = useState('');

  // Payment (full amount up front)
  const [payMethod, setPayMethod] = useState<PaymentMethod | ''>('');
  const [payReference, setPayReference] = useState('');
  const [cardLast4, setCardLast4] = useState('');

  const loadVehicles = useCallback(async () => {
    setLoading(true);
    try {
      const data = await getRentalVehicles();
      setVehicles(data.vehicles);
    } catch (e) {
      if (isUnauthorized(e)) {
        Alert.alert('Sign in required', 'Please log in to browse and request vehicle rentals.', [
          { text: 'Cancel', style: 'cancel', onPress: () => router.back() },
          { text: 'Log in', onPress: () => router.replace('/(auth)/login') },
        ]);
      } else {
        Alert.alert('Could not load rentals', 'Check your connection and try again.');
      }
    } finally {
      setLoading(false);
    }
  }, [router]);

  useEffect(() => {
    loadVehicles();
  }, [loadVehicles]);

  const total = useMemo(
    () => (selected ? selected.price_per_day * rentalDays : 0),
    [rentalDays, selected]
  );

  const openRental = (item: ApiRentalVehicle) => {
    setSelected(item);
    setPickupDate('');
    setPickupTime('');
    setRentalDays(1);
    setContactPhone('');
    setNotes('');
    setIdType('');
    setIdNumber('');
    setIdImage('');
    setPayMethod('');
    setPayReference('');
    setCardLast4('');
  };

  const pickIdPhoto = async () => {
    const perm = await ImagePicker.requestMediaLibraryPermissionsAsync();
    if (!perm.granted) {
      Alert.alert('Permission needed', 'Please allow photo access to attach a photo of your ID.');
      return;
    }
    const result = await ImagePicker.launchImageLibraryAsync({ mediaTypes: ['images'], quality: 0.6 });
    if (result.canceled) return;
    const uri = result.assets[0]?.uri;
    if (uri) setIdImage(uri);
  };

  const submitRequest = async () => {
    if (!selected) return;
    if (!pickupDate) {
      Alert.alert('Pickup date required', 'Please choose a pickup date.');
      return;
    }
    if (!pickupTime) {
      Alert.alert('Pickup time required', 'Please choose a pickup time.');
      return;
    }
    if (!/^09\d{9}$/.test(contactPhone.replace(/\s+/g, ''))) {
      Alert.alert('Phone required', 'Enter a valid contact phone so the rentals team can reach you.');
      return;
    }
    if (!idType) {
      Alert.alert('Select an ID', 'Please choose which valid ID you are presenting.');
      return;
    }
    if (!idImage) {
      Alert.alert('ID photo required', 'Please upload a clear photo of your valid ID. It stays on hold with the owner until you return the unit.');
      return;
    }
    if (!payMethod) {
      Alert.alert('Choose payment', 'Please select how you want to pay the full amount.');
      return;
    }
    if (payMethod === 'card') {
      if (!/^\d{4}$/.test(cardLast4)) {
        Alert.alert('Card details', 'Enter the last 4 digits of your card.');
        return;
      }
    } else if (payReference.trim().length < 4) {
      Alert.alert('Reference required', 'Enter the reference / confirmation number from your payment app.');
      return;
    }

    setSubmitting(true);
    try {
      await createRentalRequest({
        vehicle_id: selected.id,
        vehicle_name: selected.name,
        vehicle_type: selected.type,
        shop_name: selected.shop,
        location: selected.location,
        pickup_date: pickupDate,
        rental_days: rentalDays,
        price_per_day: selected.price_per_day,
        customer_phone: contactPhone.replace(/\s+/g, ''),
        notes: [pickupTime ? `Pickup time: ${pickupTime}` : '', notes].filter(Boolean).join(' · '),
        id_type: idType,
        id_number: idNumber.trim() || undefined,
        id_document_uri: idImage,
        payment_method: payMethod,
        payment_reference: payMethod === 'card' ? undefined : payReference.trim(),
        card_last4: payMethod === 'card' ? cardLast4 : undefined,
      });
      Alert.alert(
        'Reservation submitted',
        `You paid ₱${total.toLocaleString()} for your ${selected.type.toLowerCase()} rental. It is waiting for the rental partner to confirm. Track it under "My rentals".`,
        [{ text: 'OK', onPress: () => { setSelected(null); router.push('/my-rentals'); } }]
      );
    } catch (e) {
      if (isUnauthorized(e)) {
        Alert.alert('Sign in required', 'Please log in to reserve a vehicle.');
        return;
      }
      Alert.alert('Reservation failed', e instanceof Error ? e.message : 'Could not complete your reservation. Please try again.');
    } finally {
      setSubmitting(false);
    }
  };

  const imageSource = (item: ApiRentalVehicle) => {
    if (LOCAL_IMAGES[item.id]) {
      return LOCAL_IMAGES[item.id];
    }
    return item.image ? { uri: item.image } : undefined;
  };

  const renderItem = ({ item }: { item: ApiRentalVehicle }) => (
    <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }, shadow.sm]}>
      <View>
        {imageSource(item) ? (
          <Image source={imageSource(item)!} style={styles.image} resizeMode="cover" />
        ) : (
          <View style={[styles.image, { backgroundColor: colors.chipBg, alignItems: 'center', justifyContent: 'center' }]}>
            <Ionicons name="car-sport" size={42} color={colors.primary} />
          </View>
        )}
        <View style={[styles.typeBadge, { backgroundColor: colors.primary }]}>
          <Text style={styles.typeBadgeText}>{item.type}</Text>
        </View>
      </View>

      <View style={{ padding: 14 }}>
        <View style={styles.rowBetween}>
          <Text style={[styles.name, { color: colors.text }]} numberOfLines={1}>{item.name}</Text>
          <View style={styles.ratingPill}>
            <Ionicons name="star" size={12} color="#F6B100" />
            <Text style={[styles.ratingText, { color: colors.text }]}>{item.rating}</Text>
          </View>
        </View>

        <View style={styles.locationRow}>
          <Ionicons name="location" size={14} color={colors.primary} />
          <Text style={[styles.locationText, { color: colors.textSub }]} numberOfLines={1}>
            {item.shop} · {item.location}
          </Text>
        </View>

        <View style={styles.specsRow}>
          {item.specs.map((s) => (
            <View key={s} style={[styles.specChip, { backgroundColor: colors.chipBg }]}>
              <Text style={[styles.specText, { color: colors.textSub }]}>{s}</Text>
            </View>
          ))}
        </View>

        <View style={[styles.rowBetween, { marginTop: 12 }]}>
          <Text style={[styles.price, { color: colors.primary }]}>
            ₱{item.price_per_day.toLocaleString()}
            <Text style={[styles.priceUnit, { color: colors.textMute }]}> / day</Text>
          </Text>
          <TouchableOpacity style={[styles.reserveBtn, { backgroundColor: colors.primary }]} activeOpacity={0.85} onPress={() => openRental(item)}>
            <Text style={styles.reserveText}>Reserve</Text>
          </TouchableOpacity>
        </View>
      </View>
    </View>
  );

  return (
    <SafeAreaView style={[styles.container, { backgroundColor: colors.bgAlt }]} edges={['left', 'right']}>
      <StatusBar barStyle={isDark ? 'light-content' : 'dark-content'} />

      <View style={[styles.header, { backgroundColor: colors.card, borderBottomColor: colors.border, paddingTop: insets.top + 10 }]}>
        <TouchableOpacity onPress={() => router.back()} style={styles.headerBtn} activeOpacity={0.7}>
          <Ionicons name="chevron-back" size={26} color={colors.text} />
        </TouchableOpacity>
        <Text style={[styles.headerTitle, { color: colors.text }]}>Vehicle Rentals</Text>
        <TouchableOpacity onPress={() => router.push('/my-rentals')} style={styles.headerBtn} activeOpacity={0.7}>
          <Ionicons name="receipt-outline" size={22} color={colors.text} />
        </TouchableOpacity>
      </View>

      {loading ? (
        <View style={styles.centered}>
          <ActivityIndicator size="large" color={colors.primary} />
        </View>
      ) : (
        <FlatList
          data={vehicles}
          keyExtractor={(v) => v.id}
          renderItem={renderItem}
          contentContainerStyle={{ padding: 16, paddingBottom: 32 }}
          ItemSeparatorComponent={() => <View style={{ height: 16 }} />}
          ListHeaderComponent={
            <Text style={[styles.intro, { color: colors.textSub }]}>
              Explore Cebu your way — rent a scooter, motorcycle, e-bike, car, SUV or van from trusted local partners across the island.
            </Text>
          }
          showsVerticalScrollIndicator={false}
        />
      )}

      <Modal visible={selected !== null} transparent animationType="slide" onRequestClose={() => setSelected(null)}>
        <KeyboardAvoidingView
          style={styles.backdrop}
          behavior={Platform.OS === 'ios' ? 'padding' : undefined}
        >
          <View style={[styles.modalCard, { backgroundColor: colors.bgAlt }]}>
            <View style={styles.modalHeader}>
              <Text style={[styles.modalTitle, { color: colors.text }]}>Request booking</Text>
              <TouchableOpacity onPress={() => setSelected(null)}>
                <Ionicons name="close" size={24} color={colors.textSub} />
              </TouchableOpacity>
            </View>

            {selected ? (
              <ScrollView
                showsVerticalScrollIndicator={false}
                keyboardShouldPersistTaps="handled"
                contentContainerStyle={styles.modalScrollContent}
              >
                <View style={[styles.summaryCard, { backgroundColor: colors.card, borderColor: colors.border }]}>
                  <Text style={[styles.summaryTitle, { color: colors.text }]}>{selected.name}</Text>
                  <Text style={[styles.summarySub, { color: colors.textSub }]}>{selected.shop} · {selected.location}</Text>
                  <Text style={[styles.summaryPrice, { color: colors.primary }]}>₱{selected.price_per_day.toLocaleString()} / day</Text>
                </View>

                <Text style={[styles.label, { color: colors.textSub }]}>Pickup date</Text>
                <CalendarPicker
                  value={pickupDate}
                  onChange={setPickupDate}
                  theme={{ textMain: colors.text, textSub: colors.textSub, accent: colors.primary, border: colors.border, card: colors.card }}
                />

                <Text style={[styles.label, { color: colors.textSub }]}>Pickup time</Text>
                <View style={styles.slotRow}>
                  {TIME_SLOTS.map((slot) => (
                    <TouchableOpacity
                      key={slot}
                      style={[styles.slot, { borderColor: colors.border, backgroundColor: pickupTime === slot ? colors.primary : colors.card }]}
                      onPress={() => setPickupTime(slot)}
                    >
                      <Text style={{ color: pickupTime === slot ? '#FFFFFF' : colors.text, fontWeight: '700' }}>{slot}</Text>
                    </TouchableOpacity>
                  ))}
                </View>

                <Text style={[styles.label, { color: colors.textSub }]}>Rental days</Text>
                <View style={styles.stepperRow}>
                  <TouchableOpacity style={[styles.stepBtn, { borderColor: colors.border }]} onPress={() => setRentalDays((d) => Math.max(1, d - 1))}>
                    <Ionicons name="remove" size={18} color={colors.text} />
                  </TouchableOpacity>
                  <Text style={[styles.stepCount, { color: colors.text }]}>{rentalDays}</Text>
                  <TouchableOpacity style={[styles.stepBtn, { borderColor: colors.border }]} onPress={() => setRentalDays((d) => d + 1)}>
                    <Ionicons name="add" size={18} color={colors.text} />
                  </TouchableOpacity>
                </View>

                <Text style={[styles.label, { color: colors.textSub }]}>Contact phone</Text>
                <TextInput
                  value={contactPhone}
                  onChangeText={setContactPhone}
                  placeholder="09xx xxx xxxx"
                  placeholderTextColor={colors.textSub}
                  keyboardType="phone-pad"
                  style={[styles.input, { backgroundColor: colors.card, borderColor: colors.border, color: colors.text }]}
                />

                <Text style={[styles.label, { color: colors.textSub }]}>Notes</Text>
                <TextInput
                  value={notes}
                  onChangeText={setNotes}
                  placeholder="Driver needed, airport pickup, etc."
                  placeholderTextColor={colors.textSub}
                  multiline
                  style={[styles.textArea, { backgroundColor: colors.card, borderColor: colors.border, color: colors.text }]}
                />

                {/* ── Verification: valid ID held for the owner ── */}
                <View style={[styles.sectionDivider, { borderTopColor: colors.border }]} />
                <View style={styles.sectionHeadRow}>
                  <Ionicons name="shield-checkmark" size={18} color={colors.primary} />
                  <Text style={[styles.sectionHead, { color: colors.text }]}>Verification</Text>
                </View>
                <Text style={[styles.sectionNote, { color: colors.textSub }]}>
                  A valid ID is required to reserve. It stays on hold with {selected.shop} and is returned when you bring the unit back.
                </Text>

                <Text style={[styles.label, { color: colors.textSub }]}>ID type</Text>
                <View style={styles.slotRow}>
                  {ID_TYPES.map((t) => (
                    <TouchableOpacity
                      key={t}
                      style={[styles.chip, { borderColor: colors.border, backgroundColor: idType === t ? colors.primary : colors.card }]}
                      onPress={() => setIdType(t)}
                    >
                      <Text style={{ color: idType === t ? '#FFFFFF' : colors.text, fontWeight: '700', fontSize: 12 }}>{t}</Text>
                    </TouchableOpacity>
                  ))}
                </View>

                <Text style={[styles.label, { color: colors.textSub }]}>ID number (optional)</Text>
                <TextInput
                  value={idNumber}
                  onChangeText={setIdNumber}
                  placeholder="e.g. N01-23-456789"
                  placeholderTextColor={colors.textSub}
                  autoCapitalize="characters"
                  style={[styles.input, { backgroundColor: colors.card, borderColor: colors.border, color: colors.text }]}
                />

                <Text style={[styles.label, { color: colors.textSub }]}>Photo of your ID</Text>
                {idImage ? (
                  <View style={styles.idPreviewWrap}>
                    <Image source={{ uri: idImage }} style={styles.idPreview} resizeMode="cover" />
                    <TouchableOpacity style={styles.idRemove} onPress={() => setIdImage('')} activeOpacity={0.8}>
                      <Ionicons name="close-circle" size={26} color="#EF4444" />
                    </TouchableOpacity>
                  </View>
                ) : (
                  <TouchableOpacity
                    style={[styles.idUpload, { borderColor: colors.border, backgroundColor: colors.card }]}
                    onPress={pickIdPhoto}
                    activeOpacity={0.8}
                  >
                    <Ionicons name="camera-outline" size={26} color={colors.textSub} />
                    <Text style={[styles.idUploadText, { color: colors.textSub }]}>Upload ID photo</Text>
                  </TouchableOpacity>
                )}

                {/* ── Payment: pay the whole amount to reserve ── */}
                <View style={[styles.sectionDivider, { borderTopColor: colors.border }]} />
                <View style={styles.sectionHeadRow}>
                  <Ionicons name="card" size={18} color={colors.primary} />
                  <Text style={[styles.sectionHead, { color: colors.text }]}>Payment</Text>
                </View>
                <Text style={[styles.sectionNote, { color: colors.textSub }]}>
                  Pay the full amount now to lock in your reservation.
                </Text>

                <View style={styles.slotRow}>
                  {([
                    { key: 'gcash', label: 'GCash / e-Wallet' },
                    { key: 'instapay', label: 'InstaPay' },
                    { key: 'card', label: 'Card' },
                  ] as { key: PaymentMethod; label: string }[]).map((m) => (
                    <TouchableOpacity
                      key={m.key}
                      style={[styles.chip, { borderColor: colors.border, backgroundColor: payMethod === m.key ? colors.primary : colors.card }]}
                      onPress={() => { setPayMethod(m.key); setPayReference(''); setCardLast4(''); }}
                    >
                      <Text style={{ color: payMethod === m.key ? '#FFFFFF' : colors.text, fontWeight: '700', fontSize: 12 }}>{m.label}</Text>
                    </TouchableOpacity>
                  ))}
                </View>

                {payMethod === 'gcash' || payMethod === 'instapay' ? (
                  <>
                    <View style={styles.qrWrap}>
                      <Image source={payMethod === 'gcash' ? QR_EWALLET : QR_INSTAPAY} style={styles.qrImage} resizeMode="contain" />
                    </View>
                    <Text style={[styles.sectionNote, { color: colors.textSub, textAlign: 'center' }]}>
                      Scan the QR, pay ₱{total.toLocaleString()}, then enter your reference number.
                    </Text>
                    <TextInput
                      value={payReference}
                      onChangeText={setPayReference}
                      placeholder="Reference / confirmation no."
                      placeholderTextColor={colors.textSub}
                      autoCapitalize="characters"
                      style={[styles.input, { backgroundColor: colors.card, borderColor: colors.border, color: colors.text }]}
                    />
                  </>
                ) : null}

                {payMethod === 'card' ? (
                  <>
                    <Text style={[styles.label, { color: colors.textSub }]}>Last 4 digits of card</Text>
                    <TextInput
                      value={cardLast4}
                      onChangeText={(v) => setCardLast4(v.replace(/\D/g, '').slice(0, 4))}
                      placeholder="1234"
                      placeholderTextColor={colors.textSub}
                      keyboardType="number-pad"
                      maxLength={4}
                      style={[styles.input, { backgroundColor: colors.card, borderColor: colors.border, color: colors.text }]}
                    />
                  </>
                ) : null}

                <View style={styles.totalRow}>
                  <Text style={[styles.totalLabel, { color: colors.textSub }]}>Total to pay now</Text>
                  <Text style={[styles.totalValue, { color: colors.primary }]}>₱{total.toLocaleString()}</Text>
                </View>

                <TouchableOpacity
                  style={[styles.sendButton, { backgroundColor: colors.primary, opacity: submitting ? 0.7 : 1 }]}
                  onPress={submitRequest}
                  disabled={submitting}
                  activeOpacity={0.85}
                >
                  <Text style={styles.sendButtonText}>{submitting ? 'Processing…' : `Pay ₱${total.toLocaleString()} & reserve`}</Text>
                </TouchableOpacity>
              </ScrollView>
            ) : null}
          </View>
        </KeyboardAvoidingView>
      </Modal>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1 },
  centered: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  header: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: 8, paddingBottom: 12, borderBottomWidth: StyleSheet.hairlineWidth },
  headerBtn: { width: 44, height: 36, alignItems: 'center', justifyContent: 'center' },
  headerTitle: { fontSize: 18, fontWeight: '800' },
  intro: { fontSize: 13, lineHeight: 19, marginBottom: 16 },
  card: { borderRadius: 18, borderWidth: 1, overflow: 'hidden' },
  image: { width: '100%', height: 170 },
  typeBadge: { position: 'absolute', top: 12, left: 12, paddingHorizontal: 11, paddingVertical: 5, borderRadius: 999 },
  typeBadgeText: { color: '#FFFFFF', fontSize: 11, fontWeight: '800' },
  rowBetween: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  name: { fontSize: 16, fontWeight: '800', flex: 1, marginRight: 8 },
  ratingPill: { flexDirection: 'row', alignItems: 'center' },
  ratingText: { fontSize: 12, fontWeight: '700', marginLeft: 3 },
  locationRow: { flexDirection: 'row', alignItems: 'center', marginTop: 6 },
  locationText: { fontSize: 12, fontWeight: '600', marginLeft: 4, flex: 1 },
  specsRow: { flexDirection: 'row', flexWrap: 'wrap', marginTop: 10, gap: 7 },
  specChip: { paddingHorizontal: 10, paddingVertical: 5, borderRadius: 8 },
  specText: { fontSize: 11, fontWeight: '700' },
  price: { fontSize: 18, fontWeight: '900' },
  priceUnit: { fontSize: 12, fontWeight: '600' },
  reserveBtn: { paddingHorizontal: 22, paddingVertical: 10, borderRadius: 999 },
  reserveText: { color: '#FFFFFF', fontSize: 14, fontWeight: '800' },
  backdrop: { flex: 1, backgroundColor: 'rgba(0,0,0,0.58)', justifyContent: 'center', padding: 16 },
  modalCard: { borderRadius: 26, paddingHorizontal: 18, paddingTop: 18, paddingBottom: 8, maxHeight: '88%' },
  modalScrollContent: { paddingBottom: 12 },
  modalHeader: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginBottom: 12 },
  modalTitle: { fontSize: 24, fontWeight: '800' },
  summaryCard: { borderRadius: 18, borderWidth: 1, padding: 16, marginBottom: 16 },
  summaryTitle: { fontSize: 18, fontWeight: '800' },
  summarySub: { marginTop: 4, fontSize: 13 },
  summaryPrice: { marginTop: 10, fontSize: 22, fontWeight: '800' },
  label: { marginTop: 12, marginBottom: 8, fontSize: 13, fontWeight: '700' },
  slotRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  slot: { borderWidth: 1, borderRadius: 14, paddingVertical: 10, paddingHorizontal: 14 },
  chip: { borderWidth: 1, borderRadius: 12, paddingVertical: 9, paddingHorizontal: 13 },
  sectionDivider: { borderTopWidth: 1, marginTop: 20, marginBottom: 14 },
  sectionHeadRow: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  sectionHead: { fontSize: 17, fontWeight: '800' },
  sectionNote: { fontSize: 12.5, lineHeight: 18, marginTop: 6 },
  idUpload: { borderWidth: 1.5, borderStyle: 'dashed', borderRadius: 14, paddingVertical: 22, alignItems: 'center', justifyContent: 'center' },
  idUploadText: { fontSize: 13, fontWeight: '700', marginTop: 6 },
  idPreviewWrap: { position: 'relative', alignSelf: 'flex-start' },
  idPreview: { width: 160, height: 108, borderRadius: 12, backgroundColor: '#00000011' },
  idRemove: { position: 'absolute', top: -10, right: -10, backgroundColor: '#FFFFFF', borderRadius: 13 },
  qrWrap: { alignItems: 'center', backgroundColor: '#FFFFFF', borderRadius: 16, padding: 14, marginTop: 12 },
  qrImage: { width: 210, height: 210 },
  stepperRow: { flexDirection: 'row', alignItems: 'center', gap: 14 },
  stepBtn: { width: 40, height: 40, borderWidth: 1, borderRadius: 12, alignItems: 'center', justifyContent: 'center' },
  stepCount: { fontSize: 18, fontWeight: '800', minWidth: 24, textAlign: 'center' },
  input: { borderWidth: 1, borderRadius: 14, paddingHorizontal: 14, paddingVertical: 12, fontSize: 15 },
  textArea: { borderWidth: 1, borderRadius: 14, paddingHorizontal: 14, paddingVertical: 12, fontSize: 15, minHeight: 92, textAlignVertical: 'top' },
  totalRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginTop: 16, marginBottom: 16 },
  totalLabel: { fontSize: 14, fontWeight: '700' },
  totalValue: { fontSize: 22, fontWeight: '900' },
  sendButton: { borderRadius: 18, paddingVertical: 15, alignItems: 'center' },
  sendButtonText: { color: '#FFFFFF', fontWeight: '800', fontSize: 16 },
});
