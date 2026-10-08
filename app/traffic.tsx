import { Ionicons } from '@expo/vector-icons';
import * as Location from 'expo-location';
import { useRouter } from 'expo-router';
import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  ActivityIndicator,
  ScrollView,
  StatusBar,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import { WebView } from 'react-native-webview';
import { EmptyState } from '../components/ui';
import { useTheme } from '../lib/theme';
import { getMapsConfig } from '../services/api';

type Hotspot = { id: string; name: string; lat: number; lng: number; zoom: number };

const HOTSPOTS: Hotspot[] = [
  { id: 'city', name: 'Cebu City', lat: 10.3157, lng: 123.8854, zoom: 13 },
  { id: 'airport', name: 'Airport', lat: 10.307, lng: 123.9794, zoom: 13 },
  { id: 'itpark', name: 'IT Park', lat: 10.3275, lng: 123.9068, zoom: 14 },
  { id: 'colon', name: 'Colon', lat: 10.297, lng: 123.902, zoom: 15 },
  { id: 'srp', name: 'SRP', lat: 10.278, lng: 123.881, zoom: 13 },
  { id: 'south', name: 'South road', lat: 10.208, lng: 123.758, zoom: 11 },
];

function buildTrafficHtml(token: string, start: Hotspot): string {
  const cfg = JSON.stringify({
    token,
    style: 'mapbox://styles/mapbox/satellite-streets-v12',
    lat: start.lat,
    lng: start.lng,
    zoom: start.zoom,
  });
  return `<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
<link href="https://api.mapbox.com/mapbox-gl-js/v3.9.4/mapbox-gl.css" rel="stylesheet" />
<script src="https://api.mapbox.com/mapbox-gl-js/v3.9.4/mapbox-gl.js"></script>
<style>
  html, body, #map { margin: 0; padding: 0; height: 100%; width: 100%; overflow: hidden; background: #0b0f14; }
  .mapboxgl-ctrl-logo, .mapboxgl-ctrl-attrib { display: none !important; }
</style>
</head>
<body>
<div id="map"></div>
<script>
(function () {
  var CFG = ${cfg};
  mapboxgl.accessToken = CFG.token;
  var map = new mapboxgl.Map({
    container: 'map',
    style: CFG.style,
    center: [CFG.lng, CFG.lat],
    zoom: CFG.zoom,
    attributionControl: false
  });
  map.addControl(new mapboxgl.NavigationControl({ showCompass: false }), 'bottom-right');

  function addTraffic() {
    if (!map.getSource('mapbox-traffic')) {
      map.addSource('mapbox-traffic', { type: 'vector', url: 'mapbox://mapbox.mapbox-traffic-v1' });
    }
    if (!map.getLayer('traffic-lines')) {
      map.addLayer({
        id: 'traffic-lines',
        type: 'line',
        source: 'mapbox-traffic',
        'source-layer': 'traffic',
        paint: {
          'line-width': ['interpolate', ['linear'], ['zoom'], 10, 1.6, 16, 5],
          'line-color': [
            'match', ['get', 'congestion'],
            'low', '#22c55e',
            'moderate', '#eab308',
            'heavy', '#f97316',
            'severe', '#dc2626',
            '#64748b'
          ]
        }
      });
    }
  }

  map.on('load', addTraffic);
  map.on('style.load', addTraffic);

  var userMarker = null;
  window.__goTo = function (lng, lat, zoom) {
    map.flyTo({ center: [lng, lat], zoom: zoom || 13, essential: true });
  };
  window.__setUser = function (lat, lng) {
    if (!userMarker) {
      var el = document.createElement('div');
      el.style.cssText = 'width:16px;height:16px;border-radius:50%;background:#2563EB;border:3px solid #fff;box-shadow:0 0 0 2px rgba(37,99,235,0.35);';
      userMarker = new mapboxgl.Marker({ element: el }).setLngLat([lng, lat]).addTo(map);
    } else {
      userMarker.setLngLat([lng, lat]);
    }
  };
})();
</script>
</body>
</html>`;
}

function isInAppMapUrl(url: string): boolean {
  if (
    url.startsWith('intent:') ||
    url.startsWith('market:') ||
    url.startsWith('geo:') ||
    url.startsWith('android-app:') ||
    url.startsWith('googlechrome:') ||
    url.startsWith('comgooglemaps:')
  ) {
    return false;
  }
  if (url.startsWith('about:') || url.startsWith('data:') || url.startsWith('blob:')) return true;
  try {
    const host = new URL(url).hostname;
    return host === 'api.mapbox.com' || host.endsWith('.mapbox.com');
  } catch {
    return false;
  }
}

export default function TrafficScreen() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { colors, isDark } = useTheme();
  const webRef = useRef<WebView>(null);

  const [spot, setSpot] = useState<Hotspot>(HOTSPOTS[0]);
  const [token, setToken] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [locating, setLocating] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const data = await getMapsConfig();
      setToken(data.mapbox_token ?? '');
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Could not load the traffic map.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  useEffect(() => {
    webRef.current?.injectJavaScript(`window.__goTo && window.__goTo(${spot.lng}, ${spot.lat}, ${spot.zoom}); true;`);
  }, [spot]);

  const html = useMemo(
    () => (token ? buildTrafficHtml(token, HOTSPOTS[0]) : ''),
    [token]
  );

  const goToMe = async () => {
    setLocating(true);
    try {
      const { status } = await Location.requestForegroundPermissionsAsync();
      if (status !== 'granted') return;
      const pos = await Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.Balanced });
      const next = {
        id: 'me',
        name: 'My location',
        lat: pos.coords.latitude,
        lng: pos.coords.longitude,
        zoom: 14,
      };
      setSpot(next);
      webRef.current?.injectJavaScript(
        `window.__setUser && window.__setUser(${next.lat}, ${next.lng}); true;`
      );
    } catch {
      // keep last hotspot
    } finally {
      setLocating(false);
    }
  };

  return (
    <SafeAreaView style={[styles.container, { backgroundColor: colors.bgAlt }]} edges={['left', 'right']}>
      <StatusBar barStyle={isDark ? 'light-content' : 'dark-content'} />

      <View style={[styles.header, { backgroundColor: colors.card, borderBottomColor: colors.border, paddingTop: insets.top + 10 }]}>
        <TouchableOpacity onPress={() => router.back()} style={styles.headerBtn} activeOpacity={0.7}>
          <Ionicons name="chevron-back" size={26} color={colors.text} />
        </TouchableOpacity>
        <View style={{ flex: 1 }}>
          <Text style={[styles.headerTitle, { color: colors.text }]}>Cebu Traffic</Text>
          <Text style={[styles.headerSub, { color: colors.textMute }]} numberOfLines={1}>
            Live congestion · {spot.name}
          </Text>
        </View>
        <View style={styles.headerBtn} />
      </View>

      <View style={[styles.toolbar, { backgroundColor: colors.card, borderBottomColor: colors.border }]}>
        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.chipRow}>
          {HOTSPOTS.map((item) => {
            const active = spot.id === item.id;
            return (
              <TouchableOpacity
                key={item.id}
                onPress={() => setSpot(item)}
                activeOpacity={0.8}
                style={[
                  styles.chip,
                  {
                    backgroundColor: active ? colors.primary : colors.cardAlt,
                    borderColor: active ? colors.primary : colors.border,
                  },
                ]}
              >
                <Text style={[styles.chipText, { color: active ? '#FFFFFF' : colors.text }]}>{item.name}</Text>
              </TouchableOpacity>
            );
          })}
        </ScrollView>
        <TouchableOpacity onPress={goToMe} activeOpacity={0.8} style={[styles.meBtn, { backgroundColor: colors.primary }]}>
          {locating ? <ActivityIndicator size="small" color="#FFFFFF" /> : <Ionicons name="locate" size={18} color="#FFFFFF" />}
        </TouchableOpacity>
      </View>

      {loading ? (
        <View style={styles.center}>
          <ActivityIndicator size="large" color={colors.primary} />
        </View>
      ) : error ? (
        <EmptyState
          icon="cloud-offline-outline"
          title="Traffic unavailable"
          subtitle={error}
          actionLabel="Retry"
          onAction={load}
        />
      ) : !token ? (
        <EmptyState
          icon="map-outline"
          title="Map unavailable"
          subtitle="The in-app traffic map could not start. Pull back and try again."
          actionLabel="Retry"
          onAction={load}
        />
      ) : (
        <View style={styles.mapWrap}>
          <WebView
            ref={webRef}
            originWhitelist={['https://*', 'http://*', 'about:blank']}
            source={{ html, baseUrl: 'https://api.mapbox.com' }}
            style={styles.web}
            javaScriptEnabled
            domStorageEnabled
            setSupportMultipleWindows={false}
            onShouldStartLoadWithRequest={(req) => isInAppMapUrl(req.url)}
            onOpenWindow={() => {}}
            startInLoadingState
            renderLoading={() => (
              <View style={[styles.loading, { backgroundColor: colors.bgAlt }]}>
                <ActivityIndicator size="large" color={colors.primary} />
              </View>
            )}
          />
          <View style={[styles.legend, { backgroundColor: colors.card }]}>
            <LegendDot color="#22C55E" label="Clear" colors={colors} />
            <LegendDot color="#EAB308" label="Slow" colors={colors} />
            <LegendDot color="#F97316" label="Heavy" colors={colors} />
            <LegendDot color="#DC2626" label="Standstill" colors={colors} />
          </View>
        </View>
      )}
    </SafeAreaView>
  );
}

function LegendDot({
  color,
  label,
  colors,
}: {
  color: string;
  label: string;
  colors: ReturnType<typeof useTheme>['colors'];
}) {
  return (
    <View style={styles.legendItem}>
      <View style={[styles.legendDot, { backgroundColor: color }]} />
      <Text style={[styles.legendLabel, { color: colors.textSub }]}>{label}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1 },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 8,
    paddingBottom: 10,
    borderBottomWidth: StyleSheet.hairlineWidth,
  },
  headerBtn: { width: 42, height: 42, alignItems: 'center', justifyContent: 'center' },
  headerTitle: { fontSize: 17, fontWeight: '800' },
  headerSub: { fontSize: 11, fontWeight: '600', marginTop: 1 },
  toolbar: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 10,
    paddingLeft: 12,
    paddingRight: 10,
    borderBottomWidth: StyleSheet.hairlineWidth,
    gap: 8,
  },
  chipRow: { alignItems: 'center', paddingRight: 4 },
  chip: {
    borderWidth: 1,
    borderRadius: 999,
    paddingVertical: 8,
    paddingHorizontal: 12,
    marginRight: 8,
  },
  chipText: { fontSize: 13, fontWeight: '800' },
  meBtn: { width: 40, height: 40, borderRadius: 20, alignItems: 'center', justifyContent: 'center' },
  mapWrap: { flex: 1 },
  web: { flex: 1, backgroundColor: 'transparent' },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  loading: { ...StyleSheet.absoluteFillObject, alignItems: 'center', justifyContent: 'center' },
  legend: {
    position: 'absolute',
    left: 12,
    bottom: 18,
    flexDirection: 'row',
    borderRadius: 14,
    paddingVertical: 8,
    paddingHorizontal: 10,
    gap: 10,
    elevation: 4,
    shadowColor: '#000',
    shadowOpacity: 0.18,
    shadowRadius: 8,
    shadowOffset: { width: 0, height: 2 },
  },
  legendItem: { flexDirection: 'row', alignItems: 'center', gap: 5 },
  legendDot: { width: 8, height: 8, borderRadius: 4 },
  legendLabel: { fontSize: 11, fontWeight: '700' },
});
