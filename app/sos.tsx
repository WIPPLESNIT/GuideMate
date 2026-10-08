import AsyncStorage from '@react-native-async-storage/async-storage';
import { Ionicons } from '@expo/vector-icons';
import * as Location from 'expo-location';
import { useRouter } from 'expo-router';
import React, { useCallback, useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Linking,
  Modal,
  Platform,
  ScrollView,
  Share,
  StatusBar,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import { useTheme } from '../lib/theme';

const CONTACT_KEY = 'guidemate_sos_contact';

type Hotline = { name: string; number: string; icon: keyof typeof Ionicons.glyphMap; color: string };

const HOTLINES: Hotline[] = [
  { name: 'National Emergency', number: '911', icon: 'alert-circle', color: '#EF4444' },
  { name: 'Philippine Red Cross', number: '143', icon: 'medkit', color: '#DC2626' },
  { name: 'Police (PNP)', number: '117', icon: 'shield', color: '#2563EB' },
  { name: 'Bureau of Fire Protection', number: '0322560541', icon: 'flame', color: '#F97316' },
  { name: 'Cebu City Disaster (CCDRRMO)', number: '0322611118', icon: 'warning', color: '#D97706' },
  { name: 'Philippine Coast Guard – Cebu', number: '0322322536', icon: 'boat', color: '#0891B2' },
];

const HOSPITALS: { name: string; area: string; number: string }[] = [
  { name: "Cebu Doctors' University Hospital", area: 'Cebu City · Capitol', number: '0322555555' },
  { name: 'Chong Hua Hospital', area: 'Cebu City · Fuente', number: '0322558000' },
  { name: 'Perpetual Succour Hospital', area: 'Cebu City · Gorordo', number: '0322338620' },
  { name: 'Vicente Sotto Memorial Medical Center', area: 'Cebu City · B. Rodriguez', number: '0322539891' },
];

export default function SosScreen() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { colors, isDark, shadow } = useTheme();

  const [coords, setCoords] = useState<{ lat: number; lng: number } | null>(null);
  const [address, setAddress] = useState('');
  const [locError, setLocError] = useState('');
  const [locating, setLocating] = useState(true);

  const [contact, setContact] = useState<{ name: string; phone: string } | null>(null);
  const [editOpen, setEditOpen] = useState(false);
  const [draftName, setDraftName] = useState('');
  const [draftPhone, setDraftPhone] = useState('');

  const fetchLocation = useCallback(async () => {
    setLocating(true);
    setLocError('');
    try {
      const { status } = await Location.requestForegroundPermissionsAsync();
      if (status !== 'granted') {
        setLocError('Location permission denied. Enable it to share your location in an emergency.');
        setLocating(false);
        return;
      }
      const pos = await Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.High });
      const lat = pos.coords.latitude;
      const lng = pos.coords.longitude;
      setCoords({ lat, lng });
      try {
        const geo = await Location.reverseGeocodeAsync({ latitude: lat, longitude: lng });
        if (geo[0]) {
          const a = geo[0];
          setAddress([a.name, a.street, a.district, a.city, a.region].filter(Boolean).join(', '));
        }
      } catch {
        // reverse geocode is optional
      }
    } catch {
      setLocError('Could not get your location. Make sure GPS is on.');
    } finally {
      setLocating(false);
    }
  }, []);

  useEffect(() => {
    fetchLocation();
    (async () => {
      const raw = await AsyncStorage.getItem(CONTACT_KEY);
      if (raw) {
        try {
          setContact(JSON.parse(raw));
        } catch {
          // ignore
        }
      }
    })();
  }, [fetchLocation]);

  const mapsLink = coords ? `https://maps.google.com/?q=${coords.lat},${coords.lng}` : '';

  const dial = (number: string) => {
    Linking.openURL(`tel:${number}`).catch(() => Alert.alert('Unable to call', 'Could not open the dialer on this device.'));
  };

  const confirmSos = () => {
    Alert.alert(
      'Call Emergency 911?',
      'This will call the national emergency hotline. Only use in a real emergency.',
      [
        { text: 'Cancel', style: 'cancel' },
        { text: 'Call 911', style: 'destructive', onPress: () => dial('911') },
      ]
    );
  };

  const shareLocation = async () => {
    if (!mapsLink) {
      Alert.alert('No location yet', 'Please wait for your location to load.');
      return;
    }
    try {
      await Share.share({ message: `I need help! My current location: ${mapsLink}${address ? `\n(${address})` : ''}` });
    } catch {
      // user cancelled
    }
  };

  const alertContact = () => {
    if (!contact) {
      setEditOpen(true);
      return;
    }
    const body = `EMERGENCY! I need help. My location: ${mapsLink || 'unavailable'}`;
    const sep = Platform.OS === 'ios' ? '&' : '?';
    Linking.openURL(`sms:${contact.phone}${sep}body=${encodeURIComponent(body)}`).catch(() =>
      Alert.alert('Unable to text', 'Could not open the messaging app.')
    );
  };

  const openEdit = () => {
    setDraftName(contact?.name ?? '');
    setDraftPhone(contact?.phone ?? '');
    setEditOpen(true);
  };

  const saveContact = async () => {
    const name = draftName.trim();
    const phone = draftPhone.trim();
    if (!phone) {
      Alert.alert('Phone required', 'Please enter a contact phone number.');
      return;
    }
    const c = { name: name || 'Emergency contact', phone };
    setContact(c);
    await AsyncStorage.setItem(CONTACT_KEY, JSON.stringify(c));
    setEditOpen(false);
  };

  return (
    <SafeAreaView style={[styles.container, { backgroundColor: colors.bgAlt }]} edges={['left', 'right']}>
      <StatusBar barStyle={isDark ? 'light-content' : 'dark-content'} />

      <View style={[styles.header, { backgroundColor: colors.card, borderBottomColor: colors.border, paddingTop: insets.top + 10 }]}>
        <TouchableOpacity onPress={() => router.back()} style={styles.headerBtn} activeOpacity={0.7}>
          <Ionicons name="chevron-back" size={26} color={colors.text} />
        </TouchableOpacity>
        <Text style={[styles.headerTitle, { color: colors.text }]}>Emergency SOS</Text>
        <View style={styles.headerBtn} />
      </View>

      <ScrollView contentContainerStyle={{ padding: 16, paddingBottom: 36 }} showsVerticalScrollIndicator={false}>
        {/* Big SOS button */}
        <TouchableOpacity activeOpacity={0.85} onPress={confirmSos} style={[styles.sosButton, shadow.md]}>
          <View style={styles.sosInner}>
            <Ionicons name="warning" size={40} color="#FFFFFF" />
            <Text style={styles.sosText}>SOS</Text>
            <Text style={styles.sosSub}>Tap to call 911</Text>
          </View>
        </TouchableOpacity>

        {/* Location card */}
        <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }, shadow.sm]}>
          <View style={styles.cardHead}>
            <Ionicons name="location" size={18} color={colors.primary} />
            <Text style={[styles.cardTitle, { color: colors.text }]}>My location</Text>
            <TouchableOpacity onPress={fetchLocation} hitSlop={10}>
              <Ionicons name="refresh" size={18} color={colors.textSub} />
            </TouchableOpacity>
          </View>

          {locating ? (
            <View style={styles.locRow}>
              <ActivityIndicator size="small" color={colors.primary} />
              <Text style={[styles.locText, { color: colors.textSub }]}>Locating you…</Text>
            </View>
          ) : locError ? (
            <Text style={[styles.locText, { color: '#EF4444' }]}>{locError}</Text>
          ) : (
            <>
              {address ? <Text style={[styles.address, { color: colors.text }]}>{address}</Text> : null}
              {coords ? (
                <Text style={[styles.coordText, { color: colors.textSub }]}>
                  {coords.lat.toFixed(5)}, {coords.lng.toFixed(5)}
                </Text>
              ) : null}
              <View style={styles.locActions}>
                <TouchableOpacity style={[styles.secondaryBtn, { borderColor: colors.primary }]} onPress={shareLocation} activeOpacity={0.8}>
                  <Ionicons name="share-social" size={16} color={colors.primary} style={{ marginRight: 6 }} />
                  <Text style={[styles.secondaryText, { color: colors.primary }]}>Share location</Text>
                </TouchableOpacity>
              </View>
            </>
          )}
        </View>

        {/* Emergency contact */}
        <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }, shadow.sm]}>
          <View style={styles.cardHead}>
            <Ionicons name="person-circle" size={18} color={colors.primary} />
            <Text style={[styles.cardTitle, { color: colors.text }]}>Emergency contact</Text>
            <TouchableOpacity onPress={openEdit} hitSlop={10}>
              <Ionicons name={contact ? 'create' : 'add-circle'} size={18} color={colors.textSub} />
            </TouchableOpacity>
          </View>
          {contact ? (
            <>
              <Text style={[styles.address, { color: colors.text }]}>{contact.name}</Text>
              <Text style={[styles.coordText, { color: colors.textSub }]}>{contact.phone}</Text>
              <View style={styles.locActions}>
                <TouchableOpacity style={[styles.dangerBtn, { backgroundColor: '#EF4444' }]} onPress={alertContact} activeOpacity={0.85}>
                  <Ionicons name="send" size={15} color="#FFFFFF" style={{ marginRight: 6 }} />
                  <Text style={styles.dangerText}>Send my location (SMS)</Text>
                </TouchableOpacity>
                <TouchableOpacity style={[styles.secondaryBtn, { borderColor: colors.primary, marginLeft: 8 }]} onPress={() => dial(contact.phone)} activeOpacity={0.8}>
                  <Ionicons name="call" size={15} color={colors.primary} />
                </TouchableOpacity>
              </View>
            </>
          ) : (
            <TouchableOpacity onPress={openEdit} activeOpacity={0.8}>
              <Text style={[styles.locText, { color: colors.textSub }]}>Add a trusted person to alert with one tap.</Text>
            </TouchableOpacity>
          )}
        </View>

        {/* Hotlines */}
        <Text style={[styles.section, { color: colors.text }]}>Emergency hotlines</Text>
        <View style={[styles.listCard, { backgroundColor: colors.card, borderColor: colors.border }, shadow.sm]}>
          {HOTLINES.map((h, i) => (
            <TouchableOpacity
              key={h.name}
              style={[styles.listRow, i > 0 && { borderTopWidth: 1, borderTopColor: colors.border }]}
              onPress={() => dial(h.number)}
              activeOpacity={0.7}
            >
              <View style={[styles.listIcon, { backgroundColor: h.color + '22' }]}>
                <Ionicons name={h.icon} size={18} color={h.color} />
              </View>
              <View style={{ flex: 1 }}>
                <Text style={[styles.listName, { color: colors.text }]}>{h.name}</Text>
                <Text style={[styles.listMeta, { color: colors.textSub }]}>{h.number}</Text>
              </View>
              <Ionicons name="call" size={20} color={colors.primary} />
            </TouchableOpacity>
          ))}
        </View>

        {/* Hospitals */}
        <Text style={[styles.section, { color: colors.text }]}>Nearby hospitals (Cebu)</Text>
        <View style={[styles.listCard, { backgroundColor: colors.card, borderColor: colors.border }, shadow.sm]}>
          {HOSPITALS.map((h, i) => (
            <TouchableOpacity
              key={h.name}
              style={[styles.listRow, i > 0 && { borderTopWidth: 1, borderTopColor: colors.border }]}
              onPress={() => dial(h.number)}
              activeOpacity={0.7}
            >
              <View style={[styles.listIcon, { backgroundColor: '#10B98122' }]}>
                <Ionicons name="medkit" size={18} color="#10B981" />
              </View>
              <View style={{ flex: 1 }}>
                <Text style={[styles.listName, { color: colors.text }]} numberOfLines={1}>{h.name}</Text>
                <Text style={[styles.listMeta, { color: colors.textSub }]}>{h.area}</Text>
              </View>
              <Ionicons name="call" size={20} color={colors.primary} />
            </TouchableOpacity>
          ))}
        </View>

        <Text style={[styles.fineprint, { color: colors.textMute }]}>
          In a life-threatening emergency, always call 911 first. Hotline numbers are provided for convenience and may change.
        </Text>
      </ScrollView>

      {/* Edit contact modal */}
      <Modal visible={editOpen} transparent animationType="fade" onRequestClose={() => setEditOpen(false)}>
        <View style={styles.modalBackdrop}>
          <View style={[styles.modalCard, { backgroundColor: colors.card }]}>
            <Text style={[styles.modalTitle, { color: colors.text }]}>Emergency contact</Text>
            <TextInput
              style={[styles.input, { backgroundColor: colors.bgAlt, color: colors.text }]}
              placeholder="Name (e.g. Mom)"
              placeholderTextColor={colors.textMute}
              value={draftName}
              onChangeText={setDraftName}
            />
            <TextInput
              style={[styles.input, { backgroundColor: colors.bgAlt, color: colors.text, marginTop: 10 }]}
              placeholder="Phone number"
              placeholderTextColor={colors.textMute}
              value={draftPhone}
              onChangeText={setDraftPhone}
              keyboardType="phone-pad"
            />
            <View style={styles.modalActions}>
              <TouchableOpacity style={styles.modalBtn} onPress={() => setEditOpen(false)}>
                <Text style={[styles.modalBtnText, { color: colors.textSub }]}>Cancel</Text>
              </TouchableOpacity>
              <TouchableOpacity style={[styles.modalBtn, { backgroundColor: colors.primary, borderRadius: 10 }]} onPress={saveContact}>
                <Text style={[styles.modalBtnText, { color: '#FFFFFF' }]}>Save</Text>
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1 },
  header: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: 8, paddingBottom: 12, borderBottomWidth: StyleSheet.hairlineWidth },
  headerBtn: { width: 44, height: 36, alignItems: 'center', justifyContent: 'center' },
  headerTitle: { fontSize: 18, fontWeight: '800' },
  sosButton: { alignSelf: 'center', width: 190, height: 190, borderRadius: 95, backgroundColor: '#EF4444', alignItems: 'center', justifyContent: 'center', marginTop: 10, marginBottom: 24, borderWidth: 8, borderColor: 'rgba(239,68,68,0.25)' },
  sosInner: { alignItems: 'center' },
  sosText: { color: '#FFFFFF', fontSize: 40, fontWeight: '900', letterSpacing: 2, marginTop: 4 },
  sosSub: { color: 'rgba(255,255,255,0.9)', fontSize: 13, fontWeight: '600', marginTop: 2 },
  card: { borderRadius: 16, borderWidth: 1, padding: 14, marginBottom: 14 },
  cardHead: { flexDirection: 'row', alignItems: 'center', marginBottom: 10 },
  cardTitle: { fontSize: 15, fontWeight: '800', flex: 1, marginLeft: 8 },
  locRow: { flexDirection: 'row', alignItems: 'center' },
  locText: { fontSize: 13, marginLeft: 8, lineHeight: 18, flex: 1 },
  address: { fontSize: 15, fontWeight: '700' },
  coordText: { fontSize: 13, fontWeight: '600', marginTop: 3 },
  locActions: { flexDirection: 'row', alignItems: 'center', marginTop: 12 },
  secondaryBtn: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', borderWidth: 1.5, borderRadius: 10, paddingVertical: 9, paddingHorizontal: 14 },
  secondaryText: { fontSize: 13, fontWeight: '700' },
  dangerBtn: { flex: 1, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', borderRadius: 10, paddingVertical: 11 },
  dangerText: { color: '#FFFFFF', fontSize: 14, fontWeight: '800' },
  section: { fontSize: 16, fontWeight: '800', marginTop: 8, marginBottom: 12 },
  listCard: { borderRadius: 16, borderWidth: 1, overflow: 'hidden', marginBottom: 14 },
  listRow: { flexDirection: 'row', alignItems: 'center', paddingVertical: 12, paddingHorizontal: 14 },
  listIcon: { width: 38, height: 38, borderRadius: 19, alignItems: 'center', justifyContent: 'center', marginRight: 12 },
  listName: { fontSize: 14, fontWeight: '700' },
  listMeta: { fontSize: 12, fontWeight: '600', marginTop: 2 },
  fineprint: { fontSize: 11, lineHeight: 16, marginTop: 8 },
  modalBackdrop: { flex: 1, backgroundColor: 'rgba(0,0,0,0.5)', alignItems: 'center', justifyContent: 'center', padding: 28 },
  modalCard: { width: '100%', borderRadius: 18, padding: 20 },
  modalTitle: { fontSize: 18, fontWeight: '800', marginBottom: 14 },
  input: { borderRadius: 12, padding: 14, fontSize: 15 },
  modalActions: { flexDirection: 'row', justifyContent: 'flex-end', alignItems: 'center', marginTop: 16 },
  modalBtn: { paddingVertical: 11, paddingHorizontal: 22, marginLeft: 10 },
  modalBtnText: { fontSize: 15, fontWeight: '700' },
});
