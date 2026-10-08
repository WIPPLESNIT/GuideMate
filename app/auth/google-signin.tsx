import { Ionicons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import React, { useCallback, useEffect, useRef, useState } from 'react';
import {
  ActivityIndicator,
  StatusBar,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
  useColorScheme,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { WebView, type WebViewMessageEvent } from 'react-native-webview';
import { API_BASE_URL } from '../../config';
import {
  failGoogleSignIn,
  prepareGoogleAuthUrl,
  settleGoogleSignIn,
} from '../../lib/socialAuth';

const CHROME_UA =
  'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.6261.64 Mobile Safari/537.36';

const GOOGLE_ORIGINS =
  /accounts\.google\.|googleusercontent\.com|googleapis\.com|gstatic\.com|google\.com\/recaptcha|ogs\.google\.|ssl\.gstatic/i;

const CAPTURE_JS = `
(function() {
  function report() {
    try {
      var u = String(location.href || '');
      if (u && u !== 'about:blank') {
        window.ReactNativeWebView.postMessage(JSON.stringify({ type: 'nav', url: u }));
      }
    } catch (e) {}
  }
  report();
  document.addEventListener('DOMContentLoaded', report);
  window.addEventListener('hashchange', report);
  window.addEventListener('popstate', report);
})();
true;
`;

function queryParam(url: string, key: string): string {
  const match = new RegExp(`[?&#]${key}=([^&#]*)`).exec(url);
  return match ? decodeURIComponent(match[1].replace(/\+/g, ' ')) : '';
}

/** Google will 302 here. Do not let the WebView load ngrok (ERR_NGROK_3200). */
function isGoogleCallback(url: string): boolean {
  if (!url || url === 'about:blank') {
    return false;
  }
  if (GOOGLE_ORIGINS.test(url)) {
    return false;
  }
  if (/\.ngrok/i.test(url)) {
    return true;
  }
  if (/\/api\/auth\/google\/callback/i.test(url)) {
    return true;
  }
  return /[?&](?:code|error)=/.test(url);
}

export default function GoogleSignInScreen() {
  const router = useRouter();
  const isDark = useColorScheme() === 'dark';
  const handled = useRef(false);
  const webRef = useRef<WebView>(null);
  const [authUrl, setAuthUrl] = useState('');
  const [redirectUri, setRedirectUri] = useState('');
  const [bootError, setBootError] = useState('');
  const [busy, setBusy] = useState(false);

  const close = useCallback(
    (error?: Error) => {
      if (error) {
        failGoogleSignIn(error);
      }
      if (router.canGoBack()) {
        router.back();
      } else {
        router.replace('/(auth)/login');
      }
    },
    [router]
  );

  useEffect(() => {
    let active = true;
    (async () => {
      try {
        const prepared = await prepareGoogleAuthUrl();
        if (active) {
          setAuthUrl(prepared.authUrl);
          setRedirectUri(prepared.redirectUri);
        }
      } catch (e) {
        if (active) {
          setBootError(e instanceof Error ? e.message : 'Could not start Google sign-in.');
        }
      }
    })();
    return () => {
      active = false;
    };
  }, []);

  const finishWithUrl = useCallback(
    async (url: string) => {
      if (handled.current) return;
      const oauthError = queryParam(url, 'error');
      const code = queryParam(url, 'code');
      const state = queryParam(url, 'state');
      if (!code && !oauthError) {
        return;
      }
      handled.current = true;
      webRef.current?.stopLoading();
      setBusy(true);
      try {
        const res = await fetch(`${API_BASE_URL.replace(/\/+$/, '')}/api/auth/google/exchange`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
          body: JSON.stringify({
            code,
            state,
            oauth_error: oauthError,
            redirect_uri: redirectUri,
          }),
        });
        const data = (await res.json()) as { token?: string; error?: string };
        if (!res.ok || !data.token) {
          throw new Error(data.error || 'Google sign-in failed. Please try again.');
        }
        settleGoogleSignIn(data.token);
        if (router.canGoBack()) {
          router.back();
        } else {
          router.replace('/(tabs)');
        }
      } catch (e) {
        handled.current = false;
        setBusy(false);
        failGoogleSignIn(e instanceof Error ? e : new Error('Google sign-in failed.'));
        if (router.canGoBack()) {
          router.back();
        }
      }
    },
    [redirectUri, router]
  );

  const captureUrl = useCallback(
    (url?: string): boolean => {
      if (!url || handled.current) return false;
      if (!isGoogleCallback(url)) return false;
      webRef.current?.stopLoading();
      if (queryParam(url, 'code') || queryParam(url, 'error')) {
        void finishWithUrl(url);
      }
      return true;
    },
    [finishWithUrl]
  );

  const onMessage = useCallback(
    (event: WebViewMessageEvent) => {
      try {
        const payload = JSON.parse(event.nativeEvent.data) as { url?: string };
        if (payload.url) captureUrl(payload.url);
      } catch {
        captureUrl(event.nativeEvent.data);
      }
    },
    [captureUrl]
  );

  return (
    <SafeAreaView style={[styles.wrap, { backgroundColor: isDark ? '#0B0D12' : '#FFFFFF' }]} edges={['top']}>
      <StatusBar barStyle={isDark ? 'light-content' : 'dark-content'} />
      <View style={[styles.bar, { borderBottomColor: isDark ? '#222' : '#EEE' }]}>
        <TouchableOpacity
          onPress={() => close(new Error('Google sign-in was cancelled.'))}
          style={styles.close}
          hitSlop={12}
        >
          <Ionicons name="close" size={24} color={isDark ? '#FFF' : '#111'} />
        </TouchableOpacity>
        <Text style={[styles.title, { color: isDark ? '#FFF' : '#111' }]}>Sign in with Google</Text>
        <View style={styles.close} />
      </View>

      {bootError ? (
        <View style={styles.center}>
          <Text style={styles.err}>{bootError}</Text>
          <Text style={styles.hint}>Make sure XAMPP Apache is running, then try again.</Text>
        </View>
      ) : (
        <View style={styles.webWrap}>
          {authUrl ? (
            <WebView
              ref={webRef}
              source={{ uri: authUrl }}
              userAgent={CHROME_UA}
              originWhitelist={['https://*', 'http://*']}
              javaScriptEnabled
              domStorageEnabled
              thirdPartyCookiesEnabled
              sharedCookiesEnabled
              mixedContentMode="always"
              setSupportMultipleWindows={false}
              startInLoadingState={!busy}
              injectedJavaScript={CAPTURE_JS}
              injectedJavaScriptBeforeContentLoaded={CAPTURE_JS}
              onShouldStartLoadWithRequest={(req) => !captureUrl(req.url)}
              onLoadStart={(e) => {
                captureUrl(e.nativeEvent.url);
              }}
              onLoadProgress={(e) => {
                captureUrl(e.nativeEvent.url);
              }}
              onNavigationStateChange={(nav) => {
                captureUrl(nav.url);
              }}
              onLoadEnd={(e) => {
                captureUrl(e.nativeEvent.url);
              }}
              onError={(e) => {
                captureUrl(e.nativeEvent.url);
              }}
              onHttpError={(e) => {
                captureUrl(e.nativeEvent.url);
              }}
              onOpenWindow={(e) => {
                captureUrl(e.nativeEvent.targetUrl);
              }}
              onMessage={onMessage}
              style={[styles.web, busy ? styles.webHidden : null]}
            />
          ) : null}
          {(!authUrl || busy) && (
            <View style={styles.overlay}>
              <ActivityIndicator size="large" color="#22C55E" />
              <Text style={[styles.hint, { marginTop: 12 }]}>
                {busy ? 'Finishing sign-in…' : 'Opening Google…'}
              </Text>
            </View>
          )}
        </View>
      )}
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  wrap: { flex: 1 },
  bar: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 8,
    paddingVertical: 10,
    borderBottomWidth: StyleSheet.hairlineWidth,
  },
  close: { width: 40, height: 40, alignItems: 'center', justifyContent: 'center' },
  title: { fontSize: 16, fontWeight: '800' },
  webWrap: { flex: 1 },
  web: { flex: 1, backgroundColor: 'transparent' },
  webHidden: { opacity: 0 },
  overlay: {
    ...StyleSheet.absoluteFillObject,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#FFFFFF',
    padding: 28,
  },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 28 },
  err: { color: '#DC2626', fontSize: 15, fontWeight: '700', textAlign: 'center' },
  hint: { color: '#6B7280', fontSize: 13, textAlign: 'center', marginTop: 8 },
});
