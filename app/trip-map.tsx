import { Ionicons } from '@expo/vector-icons';
import * as Location from 'expo-location';
import { useLocalSearchParams, useRouter } from 'expo-router';
import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { ActivityIndicator, Alert, StatusBar, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import { WebView } from 'react-native-webview';
import { EmptyState } from '../components/ui';
import { getTripMap, isUnauthorized, TripPin } from '../services/api';
import { useTheme } from '../lib/theme';

type ActivePin = { id: number; lat: number; lng: number; title: string } | null;

// Self-contained map document loaded inside the WebView. Mirrors the proven web
// implementation (public/assets/js/trip-map.js): Mapbox satellite/streets, a
// route line + ETA via Mapbox Directions, and real street-level view through
// Mapillary with a Google Street View embed fallback. The tourist's live
// location is pushed in from the native side via window.__setUser().
function buildHtml(pins: TripPin[], mapboxToken: string, mapillaryToken: string, focusId: number): string {
  const data = JSON.stringify({ pins, mapboxToken, mapillaryToken, focusId });
  return `<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
<link href="https://api.mapbox.com/mapbox-gl-js/v3.9.4/mapbox-gl.css" rel="stylesheet" />
<link href="https://unpkg.com/mapillary-js@4.1.2/dist/mapillary.css" rel="stylesheet" />
<script src="https://api.mapbox.com/mapbox-gl-js/v3.9.4/mapbox-gl.js"></script>
<script src="https://unpkg.com/mapillary-js@4.1.2/dist/mapillary.js"></script>
<style>
  * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
  html, body { margin: 0; padding: 0; height: 100%; width: 100%; overflow: hidden; font-family: -apple-system, Roboto, Helvetica, Arial, sans-serif; background: #0b0f14; }
  #map { position: absolute; inset: 0; }
  .toolbar { position: absolute; top: 12px; left: 12px; right: 12px; display: flex; gap: 8px; z-index: 5; flex-wrap: wrap; }
  .seg { display: inline-flex; background: rgba(17,24,39,0.86); border-radius: 999px; padding: 4px; backdrop-filter: blur(6px); }
  .seg button { border: 0; background: transparent; color: #E5E7EB; font-size: 13px; font-weight: 700; padding: 7px 13px; border-radius: 999px; }
  .seg button.active { background: #22C55E; color: #fff; }
  .pill { border: 0; background: rgba(17,24,39,0.86); color: #E5E7EB; font-size: 13px; font-weight: 700; padding: 9px 13px; border-radius: 999px; display: inline-flex; align-items: center; gap: 6px; }
  .pill.active { background: #22C55E; color: #fff; }
  .eta { position: absolute; left: 12px; bottom: 16px; z-index: 5; background: rgba(17,24,39,0.9); color: #fff; border-radius: 14px; padding: 10px 14px; max-width: 74%; backdrop-filter: blur(6px); }
  .eta b { font-size: 16px; }
  .eta small { display: block; color: #9CA3AF; font-size: 12px; margin-top: 2px; }
  .hint { position: absolute; left: 12px; bottom: 16px; z-index: 5; color: #9CA3AF; font-size: 12px; background: rgba(17,24,39,0.86); padding: 8px 12px; border-radius: 12px; }
  .street { position: absolute; inset: 0; z-index: 20; background: #000; display: none; overflow: hidden; }
  .street.open { display: block; }
  .street .mly { position: absolute; inset: 0; width: 100%; height: 100%; border: 0; display: none; }
  /* Enlarge + offset the Google embed so its top "View on Google Maps" label and
     the bottom Google logo / keyboard-shortcuts / Terms bar are clipped away. */
  .street iframe { position: absolute; border: 0; top: -66px; left: -8px; width: calc(100% + 16px); height: calc(100% + 150px); }
  .street-close { position: absolute; top: 12px; right: 12px; z-index: 21; background: rgba(0,0,0,0.7); color: #fff; border: 0; border-radius: 999px; width: 40px; height: 40px; font-size: 20px; }
  .trip-pin { width: 18px; height: 18px; border-radius: 50%; background: #EF4444; border: 3px solid #fff; box-shadow: 0 0 0 2px rgba(0,0,0,0.25); }
  .trip-pin.up { background: #22C55E; }
  .user-puck { width: 24px; height: 24px; position: relative; }
  .user-puck:before { content: ''; position: absolute; inset: 4px; border-radius: 50%; background: #2563EB; border: 3px solid #fff; box-shadow: 0 0 0 2px rgba(37,99,235,0.4); }
  .user-puck .user-arrow { position: absolute; top: -7px; left: 50%; margin-left: -6px; width: 0; height: 0; border-left: 6px solid transparent; border-right: 6px solid transparent; border-bottom: 10px solid #2563EB; opacity: 0; transition: opacity .2s; }
  .user-puck.moving .user-arrow { opacity: 1; }
  .mapboxgl-ctrl-bottom-right { margin-bottom: 84px; }
  /* Hide the Mapbox wordmark logo (bottom-left) and the "i" attribution button. */
  .mapboxgl-ctrl-logo, .mapboxgl-ctrl-bottom-left { display: none !important; }
  .mapboxgl-ctrl-attrib { display: none !important; }
</style>
</head>
<body>
<div id="map"></div>
<div class="toolbar">
  <div class="seg">
    <button id="satBtn" class="active">Satellite</button>
    <button id="strBtn">Streets</button>
  </div>
  <div class="seg">
    <button id="driveBtn" class="active">Drive</button>
    <button id="walkBtn">Walk</button>
  </div>
  <button id="svBtn" class="pill">Street View</button>
  <button id="locBtn" class="pill">My location</button>
</div>
<div id="eta" class="hint">Getting route…</div>
<div id="street" class="street">
  <button class="street-close" id="streetClose">&times;</button>
  <iframe id="streetFrame" allowfullscreen></iframe>
  <div id="mlyViewer" class="mly"></div>
</div>
<script>
(function () {
  var CFG = ${data};
  var pins = CFG.pins || [];
  mapboxgl.accessToken = CFG.mapboxToken;

  var STYLES = { satellite: 'mapbox://styles/mapbox/satellite-streets-v12', streets: 'mapbox://styles/mapbox/streets-v12' };
  var ROUTE = 'trip-route';
  var activeStyle = 'satellite';
  var travelMode = 'driving';
  var userPos = null;
  var userHeading = null;
  var selected = null;
  var navMode = false;
  var lastRouteAt = 0;
  var bookingMarkers = {};
  var userMarker = null;
  var mlyViewer = null;

  var map = new mapboxgl.Map({
    container: 'map', style: STYLES.satellite,
    center: pins.length ? [pins[0].lng, pins[0].lat] : [123.8854, 10.3157],
    zoom: pins.length ? 14 : 11, maxZoom: 22, minZoom: 8, antialias: true,
  });
  map.addControl(new mapboxgl.NavigationControl({ visualizePitch: true }), 'bottom-right');

  function post(obj) {
    if (window.ReactNativeWebView) window.ReactNativeWebView.postMessage(JSON.stringify(obj));
  }
  function findPin(id) { for (var i=0;i<pins.length;i++){ if (pins[i].id===id) return pins[i]; } return null; }
  function fmtDur(sec) {
    var m = Math.round(sec/60);
    if (m < 60) return m + ' min';
    var h = Math.floor(m/60); var r = m%60;
    return h + ' hr' + (r ? ' ' + r + ' min' : '');
  }
  function fmtDist(mtr) { return mtr >= 1000 ? (mtr/1000).toFixed(1) + ' km' : Math.round(mtr) + ' m'; }

  function makeEl(cls) { var d = document.createElement('div'); d.className = cls; return d; }
  function makeUserEl() { var d = makeEl('user-puck'); var a = makeEl('user-arrow'); d.appendChild(a); return d; }

  function renderMarkers() {
    pins.forEach(function (p) {
      var el = makeEl('trip-pin' + (p.upcoming ? ' up' : ''));
      var mk = new mapboxgl.Marker({ element: el, anchor: 'center' }).setLngLat([p.lng, p.lat]).addTo(map);
      el.addEventListener('click', function (e) { e.stopPropagation(); selectPin(p.id); });
      bookingMarkers[p.id] = mk;
    });
  }

  function setupSources() {
    if (!map.getSource(ROUTE)) {
      map.addSource(ROUTE, { type: 'geojson', data: { type: 'FeatureCollection', features: [] } });
      map.addLayer({ id: 'route-line', type: 'line', source: ROUTE,
        layout: { 'line-cap': 'round', 'line-join': 'round' },
        paint: { 'line-color': '#0d9488', 'line-width': 6, 'line-opacity': 0.92 } });
    }
  }

  function drawRoute(pin) {
    var src = map.getSource(ROUTE);
    var eta = document.getElementById('eta');
    if (!src || !userPos || !pin) { if (src) src.setData({ type:'FeatureCollection', features: [] }); return; }
    var url = 'https://api.mapbox.com/directions/v5/mapbox/' + travelMode + '/'
      + userPos.lng + ',' + userPos.lat + ';' + pin.lng + ',' + pin.lat
      + '?geometries=geojson&overview=full&access_token=' + encodeURIComponent(CFG.mapboxToken);
    eta.className = 'hint'; eta.textContent = 'Getting route…';
    fetch(url).then(function(r){return r.json();}).then(function(data){
      if (!data.routes || !data.routes[0]) { src.setData({type:'FeatureCollection',features:[]}); eta.textContent='Route unavailable'; return; }
      var route = data.routes[0];
      src.setData({ type: 'Feature', geometry: route.geometry, properties: {} });
      eta.className = 'eta';
      var modeLabel = travelMode === 'walking' ? 'walk' : 'drive';
      eta.innerHTML = '<b>' + fmtDur(route.duration) + '</b> &middot; ' + fmtDist(route.distance)
        + '<small>' + modeLabel + ' to ' + (pin.title || 'destination') + '</small>';
      post({ type: 'eta', text: fmtDur(route.duration) + ' (' + fmtDist(route.distance) + ')' });
    }).catch(function(){ src.setData({type:'FeatureCollection',features:[]}); eta.textContent='Route unavailable'; });
  }

  function fitTo(pin) {
    if (userPos && pin) {
      var b = new mapboxgl.LngLatBounds();
      b.extend([userPos.lng, userPos.lat]); b.extend([pin.lng, pin.lat]);
      map.fitBounds(b, { padding: 90, maxZoom: 17, duration: 800 });
    } else if (pin) {
      map.flyTo({ center: [pin.lng, pin.lat], zoom: 16, duration: 800 });
    }
  }

  function selectPin(id) {
    var pin = findPin(id); if (!pin) return;
    selected = pin;
    post({ type: 'select', id: pin.id, lat: pin.lat, lng: pin.lng, title: pin.title });
    fitTo(pin); drawRoute(pin);
  }

  // Called from native with the device's GPS position (and heading when moving).
  window.__setUser = function (lat, lng, heading) {
    userPos = { lat: lat, lng: lng };
    if (typeof heading === 'number' && heading >= 0) userHeading = heading;

    if (!userMarker) {
      userMarker = new mapboxgl.Marker({ element: makeUserEl(), anchor: 'center', rotationAlignment: 'map' })
        .setLngLat([lng, lat]).addTo(map);
    } else {
      userMarker.setLngLat([lng, lat]);
    }
    var el = userMarker.getElement();
    if (userHeading != null) { userMarker.setRotation(userHeading); el.classList.add('moving'); }
    else { el.classList.remove('moving'); }

    // In follow mode keep the camera locked on the tourist, facing travel
    // direction — like a ride-hailing app tracking a moving vehicle.
    if (navMode) {
      map.easeTo({
        center: [lng, lat],
        zoom: Math.max(map.getZoom(), 16.5),
        pitch: 55,
        bearing: (userHeading != null ? userHeading : map.getBearing()),
        duration: 900,
      });
    }

    // Recalculate the live route + remaining minutes as they move (throttled in
    // follow mode so we don't spam the directions service).
    if (selected) {
      var now = Date.now();
      if (!navMode || now - lastRouteAt > 4000) { lastRouteAt = now; drawRoute(selected); }
    }
  };

  // Enter/exit in-app navigation (stays inside GuideMate — no Google Maps).
  window.__setNav = function (on) {
    navMode = !!on;
    if (navMode) {
      if (!selected && pins.length) {
        var up = pins.filter(function (p) { return p.upcoming; })[0];
        selectPin((up || pins[0]).id);
      }
      if (userPos) {
        map.easeTo({ center: [userPos.lng, userPos.lat], zoom: 17, pitch: 55, bearing: (userHeading != null ? userHeading : 0), duration: 900 });
        if (selected) drawRoute(selected);
      } else {
        post({ type: 'requestLocation' });
      }
    } else {
      map.easeTo({ pitch: 0, bearing: 0, duration: 700 });
      if (selected) fitTo(selected);
    }
  };

  // Street View (real ground-level imagery). Google embed works anywhere Google
  // has coverage; try Mapillary first when a token is present.
  function googleEmbed(lat, lng) {
    return 'https://www.google.com/maps?output=svembed&layer=c&cbll=' + encodeURIComponent(lat + ',' + lng) + '&cbp=0,90,0,0,0';
  }
  function openStreet(lat, lng) {
    var panel = document.getElementById('street');
    var frame = document.getElementById('streetFrame');
    var mlyEl = document.getElementById('mlyViewer');
    panel.classList.add('open');
    function fallback() { mlyEl.style.display='none'; frame.style.display='block'; frame.src = googleEmbed(lat, lng); }
    if (CFG.mapillaryToken && window.mapillary) {
      var u = 'https://graph.mapillary.com/images?access_token=' + encodeURIComponent(CFG.mapillaryToken)
        + '&fields=id&limit=1&radius=60&lat=' + lat + '&lng=' + lng;
      fetch(u).then(function(r){return r.json();}).then(function(d){
        var imgs = d && d.data ? d.data : [];
        if (!imgs.length) { fallback(); return; }
        frame.style.display='none'; mlyEl.style.display='block';
        if (!mlyViewer) mlyViewer = new mapillary.Viewer({ accessToken: CFG.mapillaryToken, container: 'mlyViewer', component: { cover: false } });
        mlyViewer.moveTo(String(imgs[0].id)).catch(fallback);
      }).catch(fallback);
    } else { fallback(); }
  }

  document.getElementById('streetClose').addEventListener('click', function () {
    document.getElementById('street').classList.remove('open');
    document.getElementById('streetFrame').src = 'about:blank';
  });
  document.getElementById('svBtn').addEventListener('click', function () {
    var t = selected || (userPos ? { lat: userPos.lat, lng: userPos.lng } : pins[0]);
    if (t) openStreet(t.lat, t.lng);
  });
  // Tap anywhere on the map to drop into Street View at that spot.
  map.on('click', function (e) { openStreet(e.lngLat.lat, e.lngLat.lng); });

  document.getElementById('satBtn').addEventListener('click', function () { setStyle('satellite', this); });
  document.getElementById('strBtn').addEventListener('click', function () { setStyle('streets', this); });
  function setStyle(name, btn) {
    if (name === activeStyle) return;
    activeStyle = name;
    document.getElementById('satBtn').classList.toggle('active', name==='satellite');
    document.getElementById('strBtn').classList.toggle('active', name==='streets');
    map.setStyle(STYLES[name]);
    map.once('style.load', function () { setupSources(); renderMarkers(); if (selected) drawRoute(selected); });
  }

  document.getElementById('driveBtn').addEventListener('click', function () { setMode('driving', this); });
  document.getElementById('walkBtn').addEventListener('click', function () { setMode('walking', this); });
  function setMode(m, btn) {
    if (m === travelMode) return;
    travelMode = m;
    document.getElementById('driveBtn').classList.toggle('active', m==='driving');
    document.getElementById('walkBtn').classList.toggle('active', m==='walking');
    if (selected) drawRoute(selected);
  }

  document.getElementById('locBtn').addEventListener('click', function () {
    if (userPos) map.flyTo({ center: [userPos.lng, userPos.lat], zoom: 15, duration: 700 });
    else post({ type: 'requestLocation' });
  });

  map.on('load', function () {
    setupSources();
    renderMarkers();
    var focus = CFG.focusId && findPin(CFG.focusId);
    if (focus) selectPin(CFG.focusId);
    else if (pins.length) { var up = pins.filter(function(p){return p.upcoming;})[0]; selectPin((up || pins[0]).id); }
    post({ type: 'ready' });
  });
})();
</script>
</body>
</html>`;
}

export default function TripMapScreen() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { colors, isDark } = useTheme();
  const params = useLocalSearchParams<{ booking?: string }>();
  const focusId = params.booking ? Number(params.booking) : 0;

  const webRef = useRef<WebView>(null);
  const [pins, setPins] = useState<TripPin[]>([]);
  const [mapboxToken, setMapboxToken] = useState('');
  const [mapillaryToken, setMapillaryToken] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [activePin, setActivePin] = useState<ActivePin>(null);
  const [etaText, setEtaText] = useState('');
  const [navMode, setNavMode] = useState(false);
  const watcher = useRef<Location.LocationSubscription | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const data = await getTripMap();
      setPins(data.bookings);
      setMapboxToken(data.mapbox_token);
      setMapillaryToken(data.mapillary_token);
    } catch (e) {
      if (isUnauthorized(e)) {
        Alert.alert('Sign in required', 'Please log in to view your trip map.', [
          { text: 'Cancel', style: 'cancel', onPress: () => router.back() },
          { text: 'Log in', onPress: () => router.replace('/(auth)/login') },
        ]);
      } else {
        setError(e instanceof Error ? e.message : 'Could not load your trip map.');
      }
    } finally {
      setLoading(false);
    }
  }, [router]);

  useEffect(() => {
    load();
  }, [load]);

  const pushUser = useCallback((lat: number, lng: number, heading?: number | null) => {
    const h = typeof heading === 'number' && heading >= 0 ? heading : 'null';
    webRef.current?.injectJavaScript(`window.__setUser && window.__setUser(${lat}, ${lng}, ${h}); true;`);
  }, []);

  const startLocation = useCallback(async () => {
    try {
      const { status } = await Location.requestForegroundPermissionsAsync();
      if (status !== 'granted') {
        setEtaText('Enable location for directions');
        return;
      }
      const pos = await Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.High });
      pushUser(pos.coords.latitude, pos.coords.longitude, pos.coords.heading);
      if (watcher.current) return;
      watcher.current = await Location.watchPositionAsync(
        { accuracy: Location.Accuracy.High, distanceInterval: 10, timeInterval: 3000 },
        (p) => pushUser(p.coords.latitude, p.coords.longitude, p.coords.heading)
      );
    } catch {
      setEtaText('Location unavailable');
    }
  }, [pushUser]);

  useEffect(() => {
    return () => {
      watcher.current?.remove();
    };
  }, []);

  // Toggle in-app follow/navigation mode. Stays inside GuideMate and tracks the
  // tourist live (no hand-off to Google Maps).
  const toggleNav = () => {
    const next = !navMode;
    setNavMode(next);
    startLocation();
    webRef.current?.injectJavaScript(`window.__setNav && window.__setNav(${next}); true;`);
  };

  const html = useMemo(
    () => buildHtml(pins, mapboxToken, mapillaryToken, focusId),
    [pins, mapboxToken, mapillaryToken, focusId]
  );

  const onMessage = (raw: string) => {
    try {
      const msg = JSON.parse(raw);
      if (msg.type === 'ready') startLocation();
      else if (msg.type === 'select') setActivePin({ id: msg.id, lat: msg.lat, lng: msg.lng, title: msg.title });
      else if (msg.type === 'eta') setEtaText(msg.text);
      else if (msg.type === 'requestLocation') startLocation();
    } catch {
      // ignore malformed messages
    }
  };

  const hasPins = pins.length > 0;

  return (
    <SafeAreaView style={[styles.container, { backgroundColor: colors.bgAlt }]} edges={['left', 'right', 'top']}>
      <StatusBar barStyle={isDark ? 'light-content' : 'dark-content'} />

      <View style={[styles.header, { backgroundColor: colors.card, borderBottomColor: colors.border }]}>
        <TouchableOpacity onPress={() => router.back()} style={styles.headerBtn} activeOpacity={0.7}>
          <Ionicons name="chevron-back" size={26} color={colors.text} />
        </TouchableOpacity>
        <View style={{ flex: 1 }}>
          <Text style={[styles.headerTitle, { color: colors.text }]} numberOfLines={1}>Trip Navigation</Text>
          {etaText ? <Text style={[styles.headerSub, { color: colors.textSub }]} numberOfLines={1}>{etaText}</Text> : null}
        </View>
        {hasPins ? (
          <TouchableOpacity
            onPress={toggleNav}
            style={[styles.navBtn, { backgroundColor: navMode ? '#EF4444' : colors.primary }]}
            activeOpacity={0.85}
          >
            <Ionicons name={navMode ? 'stop' : 'navigate'} size={15} color="#FFFFFF" />
            <Text style={styles.navBtnText}>{navMode ? 'Stop' : 'Start'}</Text>
          </TouchableOpacity>
        ) : (
          <View style={styles.headerBtn} />
        )}
      </View>

      {loading ? (
        <View style={styles.center}>
          <ActivityIndicator size="large" color={colors.primary} />
        </View>
      ) : error ? (
        <EmptyState
          icon="cloud-offline-outline"
          title="Something went wrong"
          subtitle={error}
          actionLabel="Retry"
          onAction={load}
        />
      ) : !hasPins ? (
        <EmptyState
          icon="map-outline"
          title="No locations to show yet"
          subtitle="Once you have a confirmed, paid booking, its map location and navigation will appear here."
          actionLabel="Explore Cebu"
          onAction={() => router.replace('/things-to-do')}
        />
      ) : !mapboxToken ? (
        <EmptyState
          icon="map-outline"
          title="Map unavailable"
          subtitle="The map service is not configured. Please try again later."
          actionLabel="Retry"
          onAction={load}
        />
      ) : (
        <WebView
          ref={webRef}
          originWhitelist={['*']}
          source={{ html }}
          style={styles.web}
          javaScriptEnabled
          domStorageEnabled
          geolocationEnabled
          allowsInlineMediaPlayback
          onMessage={(e) => onMessage(e.nativeEvent.data)}
          startInLoadingState
          renderLoading={() => (
            <View style={[styles.center, { backgroundColor: colors.bgAlt }]}>
              <ActivityIndicator size="large" color={colors.primary} />
            </View>
          )}
        />
      )}
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1 },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 8,
    paddingVertical: 10,
    borderBottomWidth: StyleSheet.hairlineWidth,
  },
  headerBtn: { width: 44, height: 40, alignItems: 'center', justifyContent: 'center' },
  headerTitle: { fontSize: 17, fontWeight: '800' },
  headerSub: { fontSize: 12, fontWeight: '600', marginTop: 1 },
  navBtn: { flexDirection: 'row', alignItems: 'center', gap: 5, paddingHorizontal: 14, height: 36, borderRadius: 999, marginRight: 6 },
  navBtnText: { color: '#FFFFFF', fontSize: 13, fontWeight: '800' },
  web: { flex: 1, backgroundColor: '#0b0f14' },
});
