import { Ionicons } from '@expo/vector-icons';
import React from 'react';
import { ScrollView, StatusBar, StyleSheet, Text, TouchableOpacity, useColorScheme, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

const FLIGHTS = [
  { route: 'Cebu to Manila', airline: 'Cebu Air', price: '₱2,450', time: '2h 05m' },
  { route: 'Cebu to Davao', airline: 'Air Philippines', price: '₱2,980', time: '1h 40m' },
  { route: 'Cebu to Seoul', airline: 'GuideMate Select', price: '₱12,800', time: '4h 35m' },
];

export default function FlightsScreen() {
  const colorScheme = useColorScheme();
  const isDark = colorScheme === 'dark';

  const theme = {
    bg: isDark ? '#111114' : '#F8F9FA',
    cardBg: isDark ? '#1E2029' : '#FFFFFF',
    textMain: isDark ? '#FFFFFF' : '#1A202C',
    textSub: isDark ? '#9CA3AF' : '#6B7280',
    accent: '#8B5CF6',
    border: isDark ? '#2A2D38' : '#E5E7EB',
  };

  return (
    <SafeAreaView style={[styles.container, { backgroundColor: theme.bg }]}>
      <StatusBar barStyle={isDark ? 'light-content' : 'dark-content'} backgroundColor={theme.bg} />
      <View style={styles.header}>
        <Text style={[styles.title, { color: theme.textMain }]}>Flights</Text>
        <Text style={[styles.subtitle, { color: theme.textSub }]}>Find cheap routes to and from Cebu.</Text>
      </View>
      <ScrollView contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        <View style={[styles.heroCard, { backgroundColor: theme.accent }]}>
          <Ionicons name="airplane" size={26} color="#FFFFFF" />
          <Text style={styles.heroTitle}>Save more on your next adventure</Text>
          <Text style={styles.heroText}>Up to 30% off selected flights and bundles.</Text>
          <TouchableOpacity style={styles.heroButton} activeOpacity={0.85}>
            <Text style={styles.heroButtonText}>Explore now</Text>
          </TouchableOpacity>
        </View>

        {FLIGHTS.map((flight) => (
          <View key={flight.route} style={[styles.card, { backgroundColor: theme.cardBg, borderColor: theme.border }]}>
            <View style={styles.cardRow}>
              <View>
                <Text style={[styles.cardRoute, { color: theme.textMain }]}>{flight.route}</Text>
                <Text style={[styles.cardAirline, { color: theme.textSub }]}>{flight.airline}</Text>
              </View>
              <Text style={[styles.cardPrice, { color: theme.accent }]}>{flight.price}</Text>
            </View>
            <View style={styles.metaRow}>
              <Ionicons name="time-outline" size={14} color={theme.textSub} />
              <Text style={[styles.metaText, { color: theme.textSub }]}>{flight.time}</Text>
            </View>
          </View>
        ))}
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1 },
  header: { paddingHorizontal: 20, paddingTop: 16, paddingBottom: 8 },
  title: { fontSize: 28, fontWeight: '800' },
  subtitle: { marginTop: 6, fontSize: 14 },
  content: { padding: 20, paddingBottom: 28 },
  heroCard: { borderRadius: 24, padding: 20, marginBottom: 18 },
  heroTitle: { color: '#FFFFFF', fontSize: 24, fontWeight: '900', marginTop: 10 },
  heroText: { color: '#FFFFFF', marginTop: 6, opacity: 0.9 },
  heroButton: { marginTop: 16, backgroundColor: '#FFFFFF', borderRadius: 22, alignSelf: 'flex-start', paddingVertical: 10, paddingHorizontal: 18 },
  heroButtonText: { color: '#8B5CF6', fontWeight: '800' },
  card: { borderRadius: 18, borderWidth: 1, padding: 16, marginBottom: 14 },
  cardRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start' },
  cardRoute: { fontSize: 16, fontWeight: '800' },
  cardAirline: { marginTop: 4, fontSize: 12 },
  cardPrice: { fontSize: 20, fontWeight: '900' },
  metaRow: { flexDirection: 'row', alignItems: 'center', marginTop: 10 },
  metaText: { marginLeft: 4, fontSize: 12, fontWeight: '600' },
});