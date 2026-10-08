import { Ionicons } from '@expo/vector-icons';
import { useLocalSearchParams, useRouter } from 'expo-router';
import React, { useEffect, useMemo, useState } from 'react';
import { ActivityIndicator, StatusBar, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { WebView } from 'react-native-webview';
import { getSession } from '../../lib/authStore';

// Builds a self-contained page that embeds the Jitsi Meet (meet.jit.si) call
// inside the WebView via its external API — so calls happen IN our app, with no
// separate app to download.
function buildCallHtml(room: string, displayName: string, video: boolean): string {
  return `<!DOCTYPE html>
<html>
  <head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <style>html,body,#meet{height:100%;margin:0;background:#0B0F14;overflow:hidden}</style>
    <script src="https://meet.jit.si/external_api.js"></script>
  </head>
  <body>
    <div id="meet"></div>
    <script>
      function start(){
        try {
          var api = new JitsiMeetExternalAPI('meet.jit.si', {
            roomName: ${JSON.stringify(room)},
            parentNode: document.getElementById('meet'),
            width: '100%', height: '100%',
            userInfo: { displayName: ${JSON.stringify(displayName)} },
            configOverwrite: {
              startWithVideoMuted: ${video ? 'false' : 'true'},
              startWithAudioMuted: false,
              prejoinPageEnabled: false,
              disableDeepLinking: true
            },
            interfaceConfigOverwrite: {
              MOBILE_APP_PROMO: false,
              SHOW_JITSI_WATERMARK: false,
              TOOLBAR_BUTTONS: ['microphone','camera','hangup','tileview','toggle-camera']
            }
          });
          api.addEventListener('readyToClose', function(){
            window.ReactNativeWebView && window.ReactNativeWebView.postMessage('close');
          });
        } catch (e) {
          window.ReactNativeWebView && window.ReactNativeWebView.postMessage('error');
        }
      }
      if (window.JitsiMeetExternalAPI) start();
      else window.addEventListener('load', start);
    </script>
  </body>
</html>`;
}

export default function CallScreen() {
  const router = useRouter();
  const params = useLocalSearchParams<{ id: string; video?: string; name?: string }>();
  const partnerId = Number(params.id);
  const isVideo = params.video === '1';
  const partnerName = typeof params.name === 'string' ? params.name : 'Guide';

  const [meId, setMeId] = useState<number | null>(null);
  const [ready, setReady] = useState(false);

  useEffect(() => {
    (async () => {
      const session = await getSession();
      setMeId(session?.id ?? 0);
      setReady(true);
    })();
  }, []);

  // Deterministic room shared by both participants regardless of who calls.
  const room = useMemo(() => {
    const a = Math.min(meId ?? 0, partnerId);
    const b = Math.max(meId ?? 0, partnerId);
    return `GuideMateCall-${a}-${b}`;
  }, [meId, partnerId]);

  const html = useMemo(() => buildCallHtml(room, `User ${meId ?? ''}`.trim(), isVideo), [room, meId, isVideo]);

  return (
    <SafeAreaView style={styles.container} edges={['top', 'left', 'right', 'bottom']}>
      <StatusBar barStyle="light-content" />
      <View style={styles.topBar}>
        <Text style={styles.title} numberOfLines={1}>{isVideo ? 'Video call' : 'Voice call'} · {partnerName}</Text>
        <TouchableOpacity style={styles.endBtn} onPress={() => router.back()} activeOpacity={0.85}>
          <Ionicons name="close" size={20} color="#FFFFFF" />
        </TouchableOpacity>
      </View>

      {!ready ? (
        <View style={styles.center}>
          <ActivityIndicator size="large" color="#FFFFFF" />
          <Text style={styles.loadingText}>Connecting…</Text>
        </View>
      ) : (
        <WebView
          originWhitelist={['*']}
          source={{ html, baseUrl: 'https://meet.jit.si' }}
          style={styles.web}
          javaScriptEnabled
          domStorageEnabled
          allowsInlineMediaPlayback
          mediaPlaybackRequiresUserAction={false}
          mediaCapturePermissionGrantType="grant"
          onMessage={(e) => {
            if (e.nativeEvent.data === 'close') router.back();
          }}
          renderLoading={() => (
            <View style={styles.center}>
              <ActivityIndicator size="large" color="#FFFFFF" />
              <Text style={styles.loadingText}>Connecting…</Text>
            </View>
          )}
          startInLoadingState
        />
      )}
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#0B0F14' },
  topBar: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: 16, paddingVertical: 10 },
  title: { color: '#FFFFFF', fontSize: 15, fontWeight: '700', flex: 1, marginRight: 12 },
  endBtn: { width: 36, height: 36, borderRadius: 18, backgroundColor: '#EF4444', alignItems: 'center', justifyContent: 'center' },
  web: { flex: 1, backgroundColor: '#0B0F14' },
  center: { ...StyleSheet.absoluteFillObject, alignItems: 'center', justifyContent: 'center', backgroundColor: '#0B0F14' },
  loadingText: { color: '#FFFFFF', marginTop: 12, fontWeight: '600' },
});
