import { Ionicons } from '@expo/vector-icons';
import * as ImagePicker from 'expo-image-picker';
import { useLocalSearchParams, useRouter } from 'expo-router';
import React, { useCallback, useEffect, useMemo, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Dimensions,
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
import CalendarPicker from '../../components/CalendarPicker';
import { useTheme } from '../../lib/theme';
import {
  ApiRentalVehicle,
  createRentalRequest,
  getRentalVehicle,
  getRentalVehicles,
  isUnauthorized,
  PaymentMethod,
  resolveImage,
} from '../../services/api';

const TIME_SLOTS = ['07:00', '08:00', '09:00', '10:00', '11:00', '13:00', '15:00', '17:00'];

const ID_TYPES = [
  "Driver's License",
  'Passport',
  'National ID (PhilSys)',
  'UMID',
  'Postal ID',
  'Other government ID',
];

const QR_EWALLET = require('../../assets/images/payment/qr-ewallet.png');
const QR_INSTAPAY = require('../../assets/images/payment/qr-instapay.png');

const LOCAL_FALLBACKS: Record<string, number> = {
  scooter: require('../../assets/images/rentals/scooter.jpg'),
  motorcycle: require('../../assets/images/rentals/motorcycle.png'),
  ebike: require('../../assets/images/rentals/ebike.jpg'),
  sedan: require('../../assets/images/rentals/sedan.jpg'),
  suv: require('../../assets/images/rentals/suv.jpg'),
  van: require('../../assets/images/rentals/van.jpg'),
};

export default function VehicleDetailsScreen() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { colors, isDark, shadow } = useTheme();
  const { id } = useLocalSearchParams<{ id: string }>();

  const [vehicle, setVehicle] = useState<ApiRentalVehicle | null>(null);
  const [loading, setLoading] = useState(true);
  const [activeImageIndex, setActiveImageIndex] = useState(0);
  const [fullScreenImage, setFullScreenImage] = useState<string | null>(null);

  // Booking Modal State
  const [modalOpen, setModalOpen] = useState(false);
  const [pickupDate, setPickupDate] = useState('');
  const [pickupTime, setPickupTime] = useState('');
  const [rentalDays, setRentalDays] = useState(1);
  const [contactPhone, setContactPhone] = useState('');
  const [notes, setNotes] = useState('');
  const [submitting, setSubmitting] = useState(false);

  // Verification & Payment State
  const [idType, setIdType] = useState('');
  const [idNumber, setIdNumber] = useState('');
  const [idImage, setIdImage] = useState('');
  const [payMethod, setPayMethod] = useState<PaymentMethod | ''>('');
  const [payReference, setPayReference] = useState('');
  const [cardLast4, setCardLast4] = useState('');

  const loadVehicle = useCallback(async () => {
    if (!id) return;
    setLoading(true);
    try {
      try {
        const res = await getRentalVehicle(id);
        if (res.vehicle) {
          setVehicle(res.vehicle);
          return;
        }
      } catch {
        // Fallback to catalog lookup
      }

      const listRes = await getRentalVehicles();
      const match = listRes.vehicles.find((v) => String(v.id) === String(id) || v.slug === id);
      if (match) {
        setVehicle(match);
      } else {
        Alert.alert('Vehicle not found', 'This rental unit is no longer available.', [
          { text: 'Go Back', onPress: () => router.back() },
        ]);
      }
    } catch (e) {
      if (isUnauthorized(e)) {
        Alert.alert('Sign in required', 'Please sign in to view vehicle details.', [
          { text: 'Log in', onPress: () => router.replace('/(auth)/login') },
          { text: 'Cancel', style: 'cancel', onPress: () => router.back() },
        ]);
      } else {
        Alert.alert('Could not load vehicle', 'Please check your connection and try again.');
      }
    } finally {
      setLoading(false);
    }
  }, [id, router]);

  useEffect(() => {
    loadVehicle();
  }, [loadVehicle]);

  // Gallery image list
  const galleryImages = useMemo(() => {
    if (!vehicle) return [];
    const list: string[] = [];
    if (vehicle.images && Array.isArray(vehicle.images) && vehicle.images.length > 0) {
      vehicle.images.forEach((img) => {
        const resolved = resolveImage(img);
        if (resolved && !list.includes(resolved)) list.push(resolved);
      });
    }
    if (list.length === 0 && vehicle.image) {
      const resolved = resolveImage(vehicle.image);
      if (resolved) list.push(resolved);
    }
    return list;
  }, [vehicle]);

  const activeImageUri = galleryImages[activeImageIndex] || (vehicle?.image ? resolveImage(vehicle.image) : '');

  const total = useMemo(
    () => (vehicle ? vehicle.price_per_day * rentalDays : 0),
    [rentalDays, vehicle]
  );

  const formattedPrice = useMemo(() => {
    if (!vehicle) return '₱0.00';
    return `₱${vehicle.price_per_day.toLocaleString('en-PH', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })}`;
  }, [vehicle]);

  const openBookingModal = () => {
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
    setModalOpen(true);
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

  const submitRental = async () => {
    if (!vehicle) return;
    if (!pickupDate) {
      Alert.alert('Pickup date required', 'Please select a date for your pickup.');
      return;
    }
    if (!pickupTime) {
      Alert.alert('Pickup time required', 'Please pick a time slot for your pickup.');
      return;
    }
    if (!contactPhone.trim()) {
      Alert.alert('Phone number required', 'Please enter a contact number so the owner can reach you.');
      return;
    }
    if (!idType) {
      Alert.alert('ID type required', 'Please select which government ID you will present upon pickup.');
      return;
    }
    if (!idImage) {
      Alert.alert('ID photo required', 'Please attach a photo of your valid ID for verification.');
      return;
    }
    if (!payMethod) {
      Alert.alert('Payment method required', 'Please choose how you will pay the full amount up front.');
      return;
    }
    if ((payMethod === 'gcash' || payMethod === 'instapay') && !payReference.trim()) {
      Alert.alert('Reference number required', 'Please enter the transaction reference from your payment receipt.');
      return;
    }

    setSubmitting(true);
    try {
      await createRentalRequest({
        vehicle_id: String(vehicle.id),
        vehicle_name: vehicle.name,
        vehicle_type: vehicle.type,
        shop_name: vehicle.shop,
        location: vehicle.location,
        pickup_date: pickupDate,
        rental_days: rentalDays,
        price_per_day: vehicle.price_per_day,
        customer_phone: contactPhone.trim(),
        notes: [pickupTime ? `Pickup time: ${pickupTime}` : '', notes.trim()].filter(Boolean).join('\n'),
        id_type: idType,
        id_number: idNumber.trim() || undefined,
        id_document_uri: idImage,
        payment_method: payMethod,
        payment_reference: payReference.trim() || undefined,
        card_last4: cardLast4.trim() || undefined,
      });

      setModalOpen(false);
      Alert.alert(
        'Rental requested!',
        `Your booking request for the ${vehicle.name} has been submitted with full payment. ${vehicle.shop} will review your ID and prepare the unit.`,
        [
          { text: 'View My Rentals', onPress: () => router.push('/my-rentals') },
          { text: 'OK', style: 'cancel' },
        ]
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

  const handleMessageProvider = () => {
    if (!vehicle) return;
    const providerId = vehicle.provider?.id || vehicle.provider_id;
    if (providerId) {
      router.push(`/chat/${providerId}`);
    } else {
      Alert.alert('Direct chat', `Start a conversation with ${vehicle.shop} via your messages inbox.`, [
        { text: 'Go to Messages', onPress: () => router.push('/messages') },
        { text: 'Cancel', style: 'cancel' },
      ]);
    }
  };

  const providerInitials = (name: string) =>
    name
      .split(' ')
      .map((w) => w.charAt(0))
      .slice(0, 2)
      .join('')
      .toUpperCase();

  if (loading) {
    return (
      <SafeAreaView style={[styles.container, { backgroundColor: colors.bgAlt }]} edges={['top', 'left', 'right']}>
        <StatusBar barStyle={isDark ? 'light-content' : 'dark-content'} />
        <View style={styles.header}>
          <TouchableOpacity onPress={() => router.back()} style={styles.headerBtn} activeOpacity={0.7}>
            <Ionicons name="chevron-back" size={26} color={colors.text} />
          </TouchableOpacity>
          <Text style={[styles.headerTitle, { color: colors.text }]}>Vehicle Details</Text>
          <View style={{ width: 44 }} />
        </View>
        <View style={styles.centered}>
          <ActivityIndicator size="large" color={colors.primary} />
          <Text style={[styles.loadingText, { color: colors.textSub }]}>Loading vehicle details...</Text>
        </View>
      </SafeAreaView>
    );
  }

  if (!vehicle) {
    return (
      <SafeAreaView style={[styles.container, { backgroundColor: colors.bgAlt }]} edges={['top', 'left', 'right']}>
        <StatusBar barStyle={isDark ? 'light-content' : 'dark-content'} />
        <View style={styles.header}>
          <TouchableOpacity onPress={() => router.back()} style={styles.headerBtn} activeOpacity={0.7}>
            <Ionicons name="chevron-back" size={26} color={colors.text} />
          </TouchableOpacity>
          <Text style={[styles.headerTitle, { color: colors.text }]}>Vehicle Details</Text>
          <View style={{ width: 44 }} />
        </View>
        <View style={styles.centered}>
          <Ionicons name="alert-circle-outline" size={48} color={colors.textMute} />
          <Text style={[styles.emptyTitle, { color: colors.text }]}>Vehicle Unavailable</Text>
          <Text style={[styles.emptySub, { color: colors.textSub }]}>This listing may have been rented or removed.</Text>
          <TouchableOpacity style={[styles.backToListBtn, { backgroundColor: colors.primary }]} onPress={() => router.back()}>
            <Text style={styles.backToListText}>Return to Listings</Text>
          </TouchableOpacity>
        </View>
      </SafeAreaView>
    );
  }

  const provider = vehicle.provider || {
    id: vehicle.provider_id || 0,
    name: vehicle.provider_name || vehicle.shop,
    online: false,
    rating: vehicle.rating || 0,
    reviews_count: 0,
    response_time: 'Within an hour',
    completed_rentals: 0,
  };

  const requirements = vehicle.requirements || [
    "Valid Philippine Driver's License or International Driving Permit",
    "Government-issued ID or Passport held for the duration of the rental",
    "Full tank fuel policy (unit is delivered full and returned full)",
    "Certified helmet use mandatory at all times for riders and passengers",
    "Deposit of valid physical ID safely held by the partner until vehicle return",
  ];

  return (
    <SafeAreaView style={[styles.container, { backgroundColor: colors.bgAlt }]} edges={['top', 'left', 'right']}>
      <StatusBar barStyle={isDark ? 'light-content' : 'dark-content'} />

      {/* Top Header */}
      <View style={[styles.header, { backgroundColor: colors.card, borderBottomColor: colors.border }]}>
        <TouchableOpacity
          onPress={() => router.back()}
          style={styles.headerBtn}
          activeOpacity={0.7}
          accessibilityLabel="Back to vehicle listings"
        >
          <Ionicons name="chevron-back" size={26} color={colors.text} />
        </TouchableOpacity>
        <Text style={[styles.headerTitle, { color: colors.text }]} numberOfLines={1}>
          {vehicle.name}
        </Text>
        <TouchableOpacity onPress={() => router.push('/my-rentals')} style={styles.headerBtn} activeOpacity={0.7}>
          <Ionicons name="receipt-outline" size={22} color={colors.text} />
        </TouchableOpacity>
      </View>

      <ScrollView
        contentContainerStyle={[styles.scrollContent, { paddingBottom: insets.bottom + 100 }]}
        showsVerticalScrollIndicator={false}
      >
        <View style={styles.desktopWrapper}>
          {/* ── Photo Gallery Section ── */}
          <View style={[styles.galleryCard, { backgroundColor: colors.card, borderColor: colors.border }, shadow.sm]}>
            <TouchableOpacity
              activeOpacity={0.95}
              onPress={() => activeImageUri && setFullScreenImage(activeImageUri)}
              style={styles.mainImageWrap}
            >
              {activeImageUri ? (
                <Image source={{ uri: activeImageUri }} style={styles.mainImage} resizeMode="cover" />
              ) : (
                <View style={[styles.mainImage, { backgroundColor: colors.chipBg, alignItems: 'center', justifyContent: 'center' }]}>
                  <Ionicons name="car-sport" size={64} color={colors.primary} />
                </View>
              )}

              {/* Floating Overlay Badges matching reference */}
              <View style={styles.badgesRow}>
                <View style={styles.catBadge}>
                  <Text style={styles.catBadgeText}>{vehicle.type}</Text>
                </View>
                <View style={styles.verifiedBadge}>
                  <Text style={styles.verifiedBadgeText}>Verified Partner</Text>
                </View>
              </View>

              {/* Photos Counter */}
              {galleryImages.length > 1 && (
                <View style={styles.photoCountBadge}>
                  <Ionicons name="images" size={13} color="#FFFFFF" style={{ marginRight: 4 }} />
                  <Text style={styles.photoCountText}>
                    {activeImageIndex + 1} / {galleryImages.length}
                  </Text>
                </View>
              )}
            </TouchableOpacity>

            {/* Thumbnail Strip */}
            {galleryImages.length > 1 && (
              <ScrollView
                horizontal
                showsHorizontalScrollIndicator={false}
                contentContainerStyle={styles.thumbnailStrip}
              >
                {galleryImages.map((uri, idx) => (
                  <TouchableOpacity
                    key={uri + idx}
                    onPress={() => setActiveImageIndex(idx)}
                    activeOpacity={0.8}
                    style={[
                      styles.thumbnailWrap,
                      {
                        borderColor: idx === activeImageIndex ? colors.primary : colors.border,
                        borderWidth: idx === activeImageIndex ? 2.5 : 1,
                      },
                    ]}
                  >
                    <Image source={{ uri }} style={styles.thumbnail} resizeMode="cover" />
                  </TouchableOpacity>
                ))}
              </ScrollView>
            )}
          </View>

          {/* ── Vehicle Primary Overview Card ── */}
          <View style={[styles.sectionCard, { backgroundColor: colors.card, borderColor: colors.border }, shadow.sm]}>
            <View style={styles.locationRow}>
              <Text style={[styles.locationText, { color: isDark ? colors.textMute : '#64748B' }]} numberOfLines={1}>
                📍 {vehicle.location} {vehicle.address ? `· ${vehicle.address}` : ''}
              </Text>
            </View>

            <Text style={[styles.vehicleTitle, { color: colors.text }]}>{vehicle.name}</Text>

            <View style={styles.priceRow}>
              <View style={styles.priceWrap}>
                <Text style={[styles.priceAmount, { color: isDark ? '#FFFFFF' : '#0F172A' }]}>
                  {formattedPrice}
                </Text>
                <Text style={[styles.priceUnit, { color: isDark ? colors.textMute : '#8E99A8' }]}> per day</Text>
              </View>
              <View style={[styles.availBadge, { backgroundColor: isDark ? '#064E3B' : '#DCFCE7' }]}>
                <View style={styles.availDot} />
                <Text style={[styles.availText, { color: isDark ? '#34D399' : '#15803D' }]}>
                  {vehicle.is_available ? 'Available for Rent' : 'Reserved'}
                </Text>
              </View>
            </View>
          </View>

          {/* ── Key Specifications Grid ── */}
          <View style={[styles.sectionCard, { backgroundColor: colors.card, borderColor: colors.border }, shadow.sm]}>
            <Text style={[styles.sectionHeading, { color: colors.text }]}>Vehicle Specifications</Text>

            <View style={styles.specGrid}>
              <View style={[styles.specGridItem, { backgroundColor: isDark ? colors.cardAlt : '#F8FAFC' }]}>
                <Text style={[styles.specLabel, { color: colors.textSub }]}>Make / Brand</Text>
                <Text style={[styles.specValue, { color: colors.text }]}>{vehicle.make || 'Honda'}</Text>
              </View>
              <View style={[styles.specGridItem, { backgroundColor: isDark ? colors.cardAlt : '#F8FAFC' }]}>
                <Text style={[styles.specLabel, { color: colors.textSub }]}>Model</Text>
                <Text style={[styles.specValue, { color: colors.text }]}>{vehicle.model || vehicle.name}</Text>
              </View>
              <View style={[styles.specGridItem, { backgroundColor: isDark ? colors.cardAlt : '#F8FAFC' }]}>
                <Text style={[styles.specLabel, { color: colors.textSub }]}>Model Year</Text>
                <Text style={[styles.specValue, { color: colors.text }]}>{vehicle.year || 2022}</Text>
              </View>
              <View style={[styles.specGridItem, { backgroundColor: isDark ? colors.cardAlt : '#F8FAFC' }]}>
                <Text style={[styles.specLabel, { color: colors.textSub }]}>Category</Text>
                <Text style={[styles.specValue, { color: colors.text }]}>{vehicle.type}</Text>
              </View>
            </View>

            {vehicle.specs && vehicle.specs.length > 0 && (
              <>
                <Text style={[styles.subHeading, { color: colors.text, marginTop: 16 }]}>Features & Inclusions</Text>
                <View style={styles.specsChipsRow}>
                  {vehicle.specs.map((s) => (
                    <View
                      key={s}
                      style={[styles.inclusionChip, { backgroundColor: isDark ? colors.chipBg : '#F1F5F9' }]}
                    >
                      <Ionicons name="checkmark-circle" size={14} color="#16A34A" style={{ marginRight: 6 }} />
                      <Text style={[styles.inclusionText, { color: isDark ? colors.textSub : '#334155' }]}>{s}</Text>
                    </View>
                  ))}
                </View>
              </>
            )}
          </View>

          {/* ── Vehicle Overview & Condition ── */}
          {vehicle.description ? (
            <View style={[styles.sectionCard, { backgroundColor: colors.card, borderColor: colors.border }, shadow.sm]}>
              <Text style={[styles.sectionHeading, { color: colors.text }]}>Vehicle Overview</Text>
              <Text style={[styles.descriptionText, { color: isDark ? colors.textSub : '#334155' }]}>
                {vehicle.description}
              </Text>
            </View>
          ) : null}

          {/* ── Rental Requirements & Terms ── */}
          <View style={[styles.sectionCard, { backgroundColor: colors.card, borderColor: colors.border }, shadow.sm]}>
            <View style={styles.sectionHeaderRow}>
              <Ionicons name="shield-checkmark-outline" size={20} color={colors.primary} />
              <Text style={[styles.sectionHeading, { color: colors.text, marginLeft: 8 }]}>Rental Requirements</Text>
            </View>

            {requirements.map((req, i) => (
              <View key={i} style={styles.requirementItem}>
                <Ionicons name="checkmark" size={16} color="#16A34A" style={styles.reqIcon} />
                <Text style={[styles.requirementText, { color: isDark ? colors.textSub : '#475569' }]}>
                  {req}
                </Text>
              </View>
            ))}
          </View>

          {/* ── Pickup & Location Details ── */}
          <View style={[styles.sectionCard, { backgroundColor: colors.card, borderColor: colors.border }, shadow.sm]}>
            <View style={styles.sectionHeaderRow}>
              <Ionicons name="location-outline" size={20} color={colors.primary} />
              <Text style={[styles.sectionHeading, { color: colors.text, marginLeft: 8 }]}>Location & Pickup</Text>
            </View>
            <Text style={[styles.pickupArea, { color: colors.text }]}>
              📍 {vehicle.location}
            </Text>
            {vehicle.address ? (
              <Text style={[styles.pickupAddress, { color: colors.textSub }]}>
                {vehicle.address}
              </Text>
            ) : null}
            <Text style={[styles.pickupNote, { color: colors.textMute }]}>
              Pickup at the provider hub. Flexible delivery to your hotel or meeting point can be arranged with the partner via messaging.
            </Text>
          </View>

          {/* ── Vehicle Provider Information (Consistent with Guide Design) ── */}
          <View style={[styles.sectionCard, { backgroundColor: colors.card, borderColor: colors.border }, shadow.sm]}>
            <Text style={[styles.sectionHeading, { color: colors.text, marginBottom: 12 }]}>Rental Provider</Text>

            <View style={styles.providerCard}>
              <View style={styles.avatarWrap}>
                {provider.avatar ? (
                  <Image source={{ uri: resolveImage(provider.avatar) }} style={styles.avatar} />
                ) : (
                  <View style={[styles.avatar, styles.avatarFallback, { backgroundColor: colors.chipBg }]}>
                    <Text style={[styles.avatarInitials, { color: colors.primary }]}>
                      {providerInitials(provider.name)}
                    </Text>
                  </View>
                )}
                {provider.online ? <View style={[styles.onlineDot, { borderColor: colors.card }]} /> : null}
              </View>

              <View style={styles.providerInfo}>
                <Text style={[styles.providerName, { color: colors.text }]} numberOfLines={1}>
                  {provider.name}
                </Text>
                <View style={styles.providerBadgeRow}>
                  <View style={[styles.verifiedPartnerPill, { backgroundColor: '#0f5132' }]}>
                    <Ionicons name="shield-checkmark" size={11} color="#FFFFFF" style={{ marginRight: 4 }} />
                    <Text style={styles.verifiedPartnerText}>Verified Partner</Text>
                  </View>
                </View>
                <View style={styles.providerMetaRow}>
                  <Ionicons name="star" size={13} color="#F6B100" />
                  <Text style={[styles.providerMetaText, { color: colors.textSub }]}>
                    {provider.reviews_count && provider.reviews_count > 0 && provider.rating
                      ? `${provider.rating.toFixed(1)} (${provider.reviews_count} ${provider.reviews_count === 1 ? 'review' : 'reviews'})`
                      : `0 (${provider.reviews_count ?? 0} reviews)`}
                  </Text>
                  <Text style={[styles.dot, { color: colors.textMute }]}>·</Text>
                  <Ionicons name="checkmark-done" size={13} color={colors.primary} />
                  <Text style={[styles.providerMetaText, { color: colors.textSub }]}>
                    {provider.completed_rentals ?? 0} {provider.completed_rentals === 1 ? 'rental' : 'rentals'}
                  </Text>
                </View>
                <Text style={[styles.responseNote, { color: colors.textMute }]}>
                  ⚡ {provider.response_time || 'Typically replies in 15 mins'}
                </Text>
              </View>
            </View>

            {/* Prominent Message Provider Button */}
            <TouchableOpacity
              style={[styles.messageProviderBtn, { borderColor: colors.primary, backgroundColor: isDark ? '#064E3B22' : '#F0FDF4' }]}
              activeOpacity={0.85}
              onPress={handleMessageProvider}
            >
              <Ionicons name="chatbubble-ellipses" size={18} color={colors.primary} />
              <Text style={[styles.messageProviderText, { color: colors.primary }]}>Message Provider</Text>
            </TouchableOpacity>
          </View>
        </View>
      </ScrollView>

      {/* ── Fixed Bottom Action Bar (Mobile & Desktop) ── */}
      <View style={[styles.fixedBottomBar, { backgroundColor: colors.card, borderTopColor: colors.border, paddingBottom: Math.max(insets.bottom, 12) }]}>
        <View style={styles.bottomBarInner}>
          <View style={styles.bottomPriceWrap}>
            <View style={{ flexDirection: 'row', alignItems: 'baseline' }}>
              <Text style={[styles.bottomPrice, { color: isDark ? '#FFFFFF' : '#0F172A' }]}>
                {formattedPrice}
              </Text>
              <Text style={[styles.bottomUnit, { color: isDark ? colors.textMute : '#8E99A8' }]}> per</Text>
            </View>
            <Text style={[styles.bottomUnit, { color: isDark ? colors.textMute : '#8E99A8' }]}>day</Text>
          </View>

          <TouchableOpacity
            style={styles.rentUnitBtn}
            activeOpacity={0.88}
            onPress={openBookingModal}
          >
            <Text style={styles.rentUnitBtnText}>Rent Unit</Text>
          </TouchableOpacity>
        </View>
      </View>

      {/* ── Full Screen Image Modal ── */}
      <Modal visible={fullScreenImage !== null} transparent animationType="fade" onRequestClose={() => setFullScreenImage(null)}>
        <View style={styles.fullScreenBackdrop}>
          <TouchableOpacity style={styles.closeFullImageBtn} onPress={() => setFullScreenImage(null)}>
            <Ionicons name="close" size={28} color="#FFFFFF" />
          </TouchableOpacity>
          {fullScreenImage && (
            <Image source={{ uri: fullScreenImage }} style={styles.fullScreenImage} resizeMode="contain" />
          )}
        </View>
      </Modal>

      {/* ── Booking Confirmation Modal ── */}
      <Modal visible={modalOpen} transparent animationType="slide" onRequestClose={() => setModalOpen(false)}>
        <KeyboardAvoidingView style={styles.modalBackdrop} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
          <View style={[styles.modalCard, { backgroundColor: colors.bgAlt }]}>
            <View style={styles.modalHeader}>
              <Text style={[styles.modalTitle, { color: colors.text }]}>Reserve Vehicle</Text>
              <TouchableOpacity onPress={() => setModalOpen(false)}>
                <Ionicons name="close" size={24} color={colors.textSub} />
              </TouchableOpacity>
            </View>

            <ScrollView showsVerticalScrollIndicator={false} keyboardShouldPersistTaps="handled" contentContainerStyle={styles.modalScrollContent}>
              <View style={[styles.summaryCard, { backgroundColor: colors.card, borderColor: colors.border }]}>
                <Text style={[styles.summaryTitle, { color: colors.text }]}>{vehicle.name}</Text>
                <Text style={[styles.summarySub, { color: colors.textSub }]}>{vehicle.shop} · {vehicle.location}</Text>
                <Text style={[styles.summaryPrice, { color: colors.primary }]}>{formattedPrice} / day</Text>
              </View>

              <Text style={[styles.inputLabel, { color: colors.textSub }]}>Pickup date</Text>
              <CalendarPicker
                value={pickupDate}
                onChange={setPickupDate}
                theme={{ textMain: colors.text, textSub: colors.textSub, accent: colors.primary, border: colors.border, card: colors.card }}
              />

              <Text style={[styles.inputLabel, { color: colors.textSub }]}>Pickup time</Text>
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

              <Text style={[styles.inputLabel, { color: colors.textSub }]}>Rental days</Text>
              <View style={styles.stepperRow}>
                <TouchableOpacity style={[styles.stepBtn, { borderColor: colors.border }]} onPress={() => setRentalDays((d) => Math.max(1, d - 1))}>
                  <Ionicons name="remove" size={18} color={colors.text} />
                </TouchableOpacity>
                <Text style={[styles.stepCount, { color: colors.text }]}>{rentalDays}</Text>
                <TouchableOpacity style={[styles.stepBtn, { borderColor: colors.border }]} onPress={() => setRentalDays((d) => d + 1)}>
                  <Ionicons name="add" size={18} color={colors.text} />
                </TouchableOpacity>
              </View>

              <Text style={[styles.inputLabel, { color: colors.textSub }]}>Contact phone</Text>
              <TextInput
                value={contactPhone}
                onChangeText={setContactPhone}
                placeholder="09xx xxx xxxx"
                placeholderTextColor={colors.textSub}
                keyboardType="phone-pad"
                style={[styles.input, { backgroundColor: colors.card, borderColor: colors.border, color: colors.text }]}
              />

              <Text style={[styles.inputLabel, { color: colors.textSub }]}>Notes or special requests</Text>
              <TextInput
                value={notes}
                onChangeText={setNotes}
                placeholder="Airport delivery, extra helmet, etc."
                placeholderTextColor={colors.textSub}
                multiline
                style={[styles.textArea, { backgroundColor: colors.card, borderColor: colors.border, color: colors.text }]}
              />

              {/* ID Verification Section */}
              <View style={[styles.sectionDivider, { borderTopColor: colors.border }]} />
              <View style={styles.sectionHeadRow}>
                <Ionicons name="shield-checkmark" size={18} color={colors.primary} />
                <Text style={[styles.sectionHead, { color: colors.text }]}>Identity Verification</Text>
              </View>
              <Text style={[styles.sectionNote, { color: colors.textSub }]}>
                A valid ID is required to rent. It remains on hold with {vehicle.shop} and is returned when you bring the unit back.
              </Text>

              <Text style={[styles.inputLabel, { color: colors.textSub }]}>ID type</Text>
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

              <Text style={[styles.inputLabel, { color: colors.textSub }]}>ID number (optional)</Text>
              <TextInput
                value={idNumber}
                onChangeText={setIdNumber}
                placeholder="e.g. N01-23-456789"
                placeholderTextColor={colors.textSub}
                autoCapitalize="characters"
                style={[styles.input, { backgroundColor: colors.card, borderColor: colors.border, color: colors.text }]}
              />

              <Text style={[styles.inputLabel, { color: colors.textSub }]}>Photo of your ID</Text>
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
                  <Text style={[styles.idUploadText, { color: colors.textSub }]}>Upload photo of ID</Text>
                </TouchableOpacity>
              )}

              {/* Payment Section */}
              <View style={[styles.sectionDivider, { borderTopColor: colors.border }]} />
              <View style={styles.sectionHeadRow}>
                <Ionicons name="card" size={18} color={colors.primary} />
                <Text style={[styles.sectionHead, { color: colors.text }]}>Payment</Text>
              </View>
              <Text style={[styles.sectionNote, { color: colors.textSub }]}>
                Pay the full amount now to lock in your rental unit.
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
                    Scan the QR code, pay ₱{total.toLocaleString()}, then enter your reference number.
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
                  <Text style={[styles.inputLabel, { color: colors.textSub }]}>Last 4 digits of card</Text>
                  <TextInput
                    value={cardLast4}
                    onChangeText={(t) => setCardLast4(t.slice(0, 4))}
                    placeholder="1234"
                    placeholderTextColor={colors.textSub}
                    keyboardType="number-pad"
                    maxLength={4}
                    style={[styles.input, { backgroundColor: colors.card, borderColor: colors.border, color: colors.text }]}
                  />
                </>
              ) : null}

              {/* Total & Submit Button */}
              <View style={styles.totalRow}>
                <Text style={[styles.totalLabel, { color: colors.textSub }]}>Total amount</Text>
                <Text style={[styles.totalValue, { color: colors.primary }]}>₱{total.toLocaleString()}.00</Text>
              </View>

              <TouchableOpacity
                style={[styles.sendButton, { backgroundColor: '#16A34A', opacity: submitting ? 0.7 : 1 }]}
                onPress={submitRental}
                disabled={submitting}
                activeOpacity={0.85}
              >
                {submitting ? (
                  <ActivityIndicator color="#FFFFFF" />
                ) : (
                  <Text style={styles.sendButtonText}>Confirm &amp; Rent Unit</Text>
                )}
              </TouchableOpacity>
            </ScrollView>
          </View>
        </KeyboardAvoidingView>
      </Modal>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1 },
  centered: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 24 },
  loadingText: { marginTop: 12, fontSize: 14, fontWeight: '600' },
  emptyTitle: { fontSize: 18, fontWeight: '800', marginTop: 14 },
  emptySub: { fontSize: 13, marginTop: 6, textAlign: 'center' },
  backToListBtn: { marginTop: 18, paddingHorizontal: 20, paddingVertical: 10, borderRadius: 999 },
  backToListText: { color: '#FFFFFF', fontWeight: '700', fontSize: 14 },

  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 12,
    paddingVertical: 12,
    borderBottomWidth: StyleSheet.hairlineWidth,
  },
  headerBtn: { width: 44, height: 40, alignItems: 'center', justifyContent: 'center' },
  headerTitle: { fontSize: 17, fontWeight: '800', flex: 1, textAlign: 'center' },

  scrollContent: { padding: 16 },
  desktopWrapper: { maxWidth: 720, width: '100%', alignSelf: 'center' },

  // Gallery Card
  galleryCard: { borderRadius: 18, borderWidth: 1, overflow: 'hidden', marginBottom: 16 },
  mainImageWrap: { width: '100%', aspectRatio: 16 / 11, position: 'relative', overflow: 'hidden', backgroundColor: '#E2E8F0' },
  mainImage: { width: '100%', height: '100%' },
  badgesRow: {
    position: 'absolute',
    top: 14,
    left: 14,
    right: 14,
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
    zIndex: 2,
  },
  catBadge: {
    backgroundColor: 'rgba(255, 255, 255, 0.92)',
    paddingHorizontal: 12,
    paddingVertical: 5,
    borderRadius: 999,
  },
  catBadgeText: { color: '#0f5132', fontSize: 12, fontWeight: '700' },
  verifiedBadge: {
    backgroundColor: '#0f5132',
    paddingHorizontal: 12,
    paddingVertical: 5,
    borderRadius: 999,
  },
  verifiedBadgeText: { color: '#FFFFFF', fontSize: 12, fontWeight: '700' },
  photoCountBadge: {
    position: 'absolute',
    bottom: 12,
    right: 12,
    backgroundColor: 'rgba(0,0,0,0.65)',
    paddingHorizontal: 10,
    paddingVertical: 4.5,
    borderRadius: 999,
    flexDirection: 'row',
    alignItems: 'center',
  },
  photoCountText: { color: '#FFFFFF', fontSize: 11.5, fontWeight: '700' },

  thumbnailStrip: { padding: 12, gap: 10 },
  thumbnailWrap: { width: 72, height: 50, borderRadius: 8, overflow: 'hidden' },
  thumbnail: { width: '100%', height: '100%' },

  // Section Cards
  sectionCard: { borderRadius: 18, borderWidth: 1, padding: 16, marginBottom: 16 },
  locationRow: { marginBottom: 4 },
  locationText: { fontSize: 13, fontWeight: '600' },
  vehicleTitle: { fontSize: 21, fontWeight: '800', lineHeight: 28, marginBottom: 12 },
  priceRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: 10 },
  priceWrap: { flexDirection: 'row', alignItems: 'baseline' },
  priceAmount: { fontSize: 24, fontWeight: '900', letterSpacing: -0.4 },
  priceUnit: { fontSize: 14, fontWeight: '600' },
  availBadge: { flexDirection: 'row', alignItems: 'center', paddingHorizontal: 10, paddingVertical: 5, borderRadius: 999 },
  availDot: { width: 7, height: 7, borderRadius: 4, backgroundColor: '#16A34A', marginRight: 6 },
  availText: { fontSize: 12, fontWeight: '700' },

  sectionHeading: { fontSize: 17, fontWeight: '800' },
  sectionHeaderRow: { flexDirection: 'row', alignItems: 'center', marginBottom: 12 },
  subHeading: { fontSize: 14, fontWeight: '700', marginBottom: 8 },
  descriptionText: { fontSize: 14.5, lineHeight: 22, marginTop: 6 },

  // Specs Grid
  specGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 10, marginTop: 12 },
  specGridItem: { flex: 1, minWidth: '45%', padding: 12, borderRadius: 12 },
  specLabel: { fontSize: 11.5, fontWeight: '600', marginBottom: 2 },
  specValue: { fontSize: 14.5, fontWeight: '700' },

  specsChipsRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginTop: 6 },
  inclusionChip: { flexDirection: 'row', alignItems: 'center', paddingHorizontal: 11, paddingVertical: 6, borderRadius: 8 },
  inclusionText: { fontSize: 12.5, fontWeight: '600' },

  // Requirements
  requirementItem: { flexDirection: 'row', alignItems: 'flex-start', marginBottom: 10 },
  reqIcon: { marginTop: 2, marginRight: 8 },
  requirementText: { fontSize: 13.5, lineHeight: 19, flex: 1 },

  // Pickup
  pickupArea: { fontSize: 15, fontWeight: '700', marginTop: 4 },
  pickupAddress: { fontSize: 13.5, marginTop: 3 },
  pickupNote: { fontSize: 12, lineHeight: 17, marginTop: 8 },

  // Provider Card
  providerCard: { flexDirection: 'row', alignItems: 'center', marginTop: 4, marginBottom: 14 },
  avatarWrap: { position: 'relative', marginRight: 14 },
  avatar: { width: 56, height: 56, borderRadius: 28 },
  avatarFallback: { alignItems: 'center', justifyContent: 'center' },
  avatarInitials: { fontSize: 19, fontWeight: '800' },
  onlineDot: { position: 'absolute', bottom: 1, right: 1, width: 14, height: 14, borderRadius: 7, backgroundColor: '#22C55E', borderWidth: 2 },
  providerInfo: { flex: 1 },
  providerName: { fontSize: 16.5, fontWeight: '800' },
  providerBadgeRow: { flexDirection: 'row', marginTop: 3 },
  verifiedPartnerPill: { flexDirection: 'row', alignItems: 'center', paddingHorizontal: 8, paddingVertical: 3, borderRadius: 999 },
  verifiedPartnerText: { color: '#FFFFFF', fontSize: 10.5, fontWeight: '700' },
  providerMetaRow: { flexDirection: 'row', alignItems: 'center', marginTop: 5, gap: 5 },
  providerMetaText: { fontSize: 12, fontWeight: '600' },
  dot: { fontSize: 12 },
  responseNote: { fontSize: 11.5, fontWeight: '600', marginTop: 4 },
  messageProviderBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 11,
    borderRadius: 14,
    borderWidth: 1.5,
    gap: 8,
  },
  messageProviderText: { fontSize: 14, fontWeight: '800' },

  // Fixed Bottom Bar
  fixedBottomBar: {
    position: 'absolute',
    bottom: 0,
    left: 0,
    right: 0,
    borderTopWidth: 1,
    paddingTop: 10,
    paddingHorizontal: 16,
  },
  bottomBarInner: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    maxWidth: 720,
    width: '100%',
    alignSelf: 'center',
  },
  bottomPriceWrap: { justifyContent: 'center' },
  bottomPrice: { fontSize: 21, fontWeight: '900', letterSpacing: -0.3 },
  bottomUnit: { fontSize: 13, fontWeight: '600' },
  rentUnitBtn: {
    backgroundColor: '#16A34A',
    paddingHorizontal: 28,
    paddingVertical: 12,
    borderRadius: 999,
    alignItems: 'center',
    justifyContent: 'center',
    minWidth: 140,
  },
  rentUnitBtnText: { color: '#FFFFFF', fontSize: 15, fontWeight: '800' },

  // Full Screen Modal
  fullScreenBackdrop: { flex: 1, backgroundColor: '#000000E6', alignItems: 'center', justifyContent: 'center' },
  closeFullImageBtn: { position: 'absolute', top: 50, right: 20, zIndex: 10, padding: 8 },
  fullScreenImage: { width: Dimensions.get('window').width, height: Dimensions.get('window').height * 0.8 },

  // Booking Modal
  modalBackdrop: { flex: 1, backgroundColor: 'rgba(0,0,0,0.6)', justifyContent: 'center', padding: 16 },
  modalCard: { borderRadius: 26, paddingHorizontal: 18, paddingTop: 18, paddingBottom: 8, maxHeight: '88%' },
  modalScrollContent: { paddingBottom: 16 },
  modalHeader: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginBottom: 14 },
  modalTitle: { fontSize: 22, fontWeight: '800' },
  summaryCard: { borderRadius: 16, borderWidth: 1, padding: 14, marginBottom: 14 },
  summaryTitle: { fontSize: 17, fontWeight: '800' },
  summarySub: { marginTop: 3, fontSize: 13 },
  summaryPrice: { marginTop: 8, fontSize: 19, fontWeight: '800' },

  inputLabel: { marginTop: 12, marginBottom: 7, fontSize: 13, fontWeight: '700' },
  slotRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  slot: { borderWidth: 1, borderRadius: 12, paddingVertical: 9, paddingHorizontal: 13 },
  chip: { borderWidth: 1, borderRadius: 12, paddingVertical: 8, paddingHorizontal: 12 },
  stepperRow: { flexDirection: 'row', alignItems: 'center', gap: 14 },
  stepBtn: { width: 40, height: 40, borderWidth: 1, borderRadius: 12, alignItems: 'center', justifyContent: 'center' },
  stepCount: { fontSize: 18, fontWeight: '800', minWidth: 24, textAlign: 'center' },
  input: { borderWidth: 1, borderRadius: 14, paddingHorizontal: 14, paddingVertical: 11, fontSize: 14.5 },
  textArea: { borderWidth: 1, borderRadius: 14, paddingHorizontal: 14, paddingVertical: 11, fontSize: 14.5, minHeight: 80, textAlignVertical: 'top' },

  sectionDivider: { borderTopWidth: 1, marginTop: 18, marginBottom: 12 },
  sectionHeadRow: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  sectionHead: { fontSize: 16, fontWeight: '800' },
  sectionNote: { fontSize: 12, lineHeight: 17, marginTop: 5 },

  idUpload: { borderWidth: 1.5, borderStyle: 'dashed', borderRadius: 14, paddingVertical: 20, alignItems: 'center', justifyContent: 'center', marginTop: 4 },
  idUploadText: { fontSize: 13, fontWeight: '700', marginTop: 6 },
  idPreviewWrap: { position: 'relative', alignSelf: 'flex-start', marginTop: 6 },
  idPreview: { width: 160, height: 104, borderRadius: 12, backgroundColor: '#00000011' },
  idRemove: { position: 'absolute', top: -10, right: -10, backgroundColor: '#FFFFFF', borderRadius: 13 },

  qrWrap: { alignItems: 'center', backgroundColor: '#FFFFFF', borderRadius: 16, padding: 12, marginTop: 12 },
  qrImage: { width: 190, height: 190 },

  totalRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginTop: 16, marginBottom: 16 },
  totalLabel: { fontSize: 14, fontWeight: '700' },
  totalValue: { fontSize: 22, fontWeight: '900' },
  sendButton: { borderRadius: 18, paddingVertical: 14, alignItems: 'center' },
  sendButtonText: { color: '#FFFFFF', fontWeight: '800', fontSize: 15.5 },
});
