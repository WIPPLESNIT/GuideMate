import { useLocalSearchParams, useRouter } from 'expo-router';
import { useEffect, useRef } from 'react';
import { ActivityIndicator, StyleSheet, Text, View, useColorScheme } from 'react-native';
import * as WebBrowser from 'expo-web-browser';
import { completeBackendOAuth } from '../../lib/authStore';

/**
 * Catches the Google/Facebook backend OAuth redirect. The PHP backend deep-links
 * to `.../auth/<provider>?token=...` (or `?error=...`). We finish the sign-in
 * here so the redirect never lands on an "Unmatched Route".
 */
export default function OAuthReturnScreen() {
  const router = useRouter();
  const scheme = useColorScheme();
  const isDark = scheme === 'dark';
  const { provider, token, error } = useLocalSearchParams<{
    provider?: string;
    token?: string | string[];
    error?: string | string[];
  }>();
  const handled = useRef(false);

  const label = provider === 'facebook' ? 'Facebook' : 'Google';

  useEffect(() => {
    if (handled.current) return;
    handled.current = true;

    const first = (v?: string | string[]) => (Array.isArray(v) ? v[0] : v);
    const tokenValue = first(token);
    const errorValue = first(error);

    // Close the in-app browser tab if it's still open. dismissBrowser() may
    // return void (not a Promise) depending on platform/SDK, so guard it.
    try {
      void Promise.resolve(WebBrowser.dismissBrowser()).catch(() => {});
    } catch {
      // no browser session to dismiss
    }

    const finish = async () => {
      if (errorValue) {
        router.replace({ pathname: '/(auth)/login', params: { social_error: errorValue } });
        return;
      }
      if (!tokenValue) {
        router.replace({
          pathname: '/(auth)/login',
          params: { social_error: `${label} sign-in did not complete. Please try again.` },
        });
        return;
      }
      try {
        await completeBackendOAuth(tokenValue);
        router.replace('/(tabs)');
      } catch {
        router.replace({
          pathname: '/(auth)/login',
          params: { social_error: `Could not finish your ${label} sign-in. Please try again.` },
        });
      }
    };

    finish();
  }, [error, label, router, token]);

  return (
    <View style={[styles.container, { backgroundColor: isDark ? '#0B0D12' : '#FFFFFF' }]}>
      <ActivityIndicator size="large" color="#2F6FED" />
      <Text style={[styles.text, { color: isDark ? '#E5E7EB' : '#111827' }]}>
        Finishing your {label} sign-in…
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: 24,
  },
  text: {
    marginTop: 16,
    fontSize: 15,
    fontWeight: '600',
    textAlign: 'center',
  },
});
