import { Stack } from 'expo-router';
import * as SplashScreen from 'expo-splash-screen';
import { StatusBar } from 'expo-status-bar';
import * as SystemUI from 'expo-system-ui';
import { NavigationBar } from 'expo-navigation-bar';
import React, { useEffect, useRef, useState } from 'react';
import {
  Animated,
  Image,
  StyleSheet,
  useColorScheme,
  View,
} from 'react-native';
import { SafeAreaProvider, initialWindowMetrics } from 'react-native-safe-area-context';
import { restoreSession } from '../lib/authStore';
import { PreferencesProvider } from '../lib/preferences';

// Instruct native splash screen to remain visible while app initializes
SplashScreen.preventAutoHideAsync().catch(() => {});

export default function RootLayout() {
  const scheme = useColorScheme();
  const isDark = scheme === 'dark';

  const [appReady, setAppReady] = useState(false);
  const [showSplash, setShowSplash] = useState(true);
  const splashOpacity = useRef(new Animated.Value(1)).current;

  // Startup initialization: restore saved login token & session
  useEffect(() => {
    let active = true;
    async function initApp() {
      try {
        await restoreSession();
      } catch (err) {
        // Continue even if session restore encounters an issue
      } finally {
        if (active) {
          setAppReady(true);
        }
      }
    }
    initApp();
    return () => {
      active = false;
    };
  }, []);

  useEffect(() => {
    const backgroundColor = isDark ? '#0B0D12' : '#FFFFFF';
    SystemUI.setBackgroundColorAsync(backgroundColor).catch(() => {});
  }, [isDark]);

  // Smoothly transition from the launch splash screen to the main app interface
  useEffect(() => {
    if (!appReady) return;

    // Small delay ensures the first frame of the app is rendered under the splash
    const timer = setTimeout(() => {
      // Hide the native splash screen so the seamless animated overlay handles the fade
      SplashScreen.hideAsync().catch(() => {});

      Animated.timing(splashOpacity, {
        toValue: 0,
        duration: 350,
        useNativeDriver: true,
      }).start(() => {
        setShowSplash(false);
      });
    }, 700);

    return () => clearTimeout(timer);
  }, [appReady, splashOpacity]);

  const splashBg = isDark ? '#0B0D12' : '#FFFFFF';

  return (
    <SafeAreaProvider initialMetrics={initialWindowMetrics}>
      <PreferencesProvider>
        {/* Immersive full screen: hide the Android on-screen navigation bar
            (back/home/recents). A swipe from the edge reveals it briefly, then
            it auto-hides again (behavior set in the app.json config plugin).
            No-op on iOS/web. */}
        <NavigationBar hidden />

        {/* Global status bar: keeps the phone's clock/battery icons visible on
            every screen (auto-picks dark icons in light mode, light in dark). */}
        <StatusBar style="auto" />

        <Stack screenOptions={{ headerShown: false }}>
          {/* 1. Point to the individual landing screen entry point */}
          <Stack.Screen name="(auth)/login" />

          {/* 2. Map the other specific auth routes individually so the Stack registry detects them */}
          <Stack.Screen name="(auth)/register" />
          <Stack.Screen name="(auth)/forgot-password" />

          {/* Catches the Google/Facebook backend OAuth redirect (deep link) */}
          <Stack.Screen name="auth/[provider]" />
          <Stack.Screen name="auth/google-signin" options={{ presentation: 'modal', animation: 'slide_from_bottom' }} />

          {/* 3. Map the main dashboard tab group entry point layout */}
          <Stack.Screen name="(tabs)" />

          {/* 4. Things to do in Cebu list screen */}
          <Stack.Screen name="things-to-do" />

          {/* Search with recent history */}
          <Stack.Screen name="search" />

          {/* 5. Single listing detail screen */}
          <Stack.Screen name="listing/[slug]" />

          {/* 6. App settings (language, currency, etc.) */}
          <Stack.Screen name="settings" />
          <Stack.Screen name="login-methods" />
          <Stack.Screen name="notification-settings" />
          <Stack.Screen name="feedback" />
          <Stack.Screen name="my-feedback" />
          <Stack.Screen name="about" />

          {/* 7. Security & biometric screens */}
          <Stack.Screen name="account-security" />
          <Stack.Screen name="change-password" />
          <Stack.Screen name="fingerprint" />

          {/* 8. Messaging + in-app calls */}
          <Stack.Screen name="messages" />
          <Stack.Screen name="chat/[id]" />
          <Stack.Screen name="call/[id]" options={{ presentation: 'fullScreenModal' }} />

          {/* 9. Travel services: vehicle rentals, weather, live traffic */}
          <Stack.Screen name="packages" />
          <Stack.Screen name="package/create" />
          <Stack.Screen name="package/[id]" />
          <Stack.Screen name="car-rentals" />
          <Stack.Screen name="rental/[id]" />
          <Stack.Screen name="weather" />
          <Stack.Screen name="traffic" />

          {/* 10. Emergency SOS */}
          <Stack.Screen name="sos" />

          {/* 11. Trip navigation map (post-booking) */}
          <Stack.Screen name="trip-map" />
        </Stack>

        {/* Launch Splash Screen: displayed only during app startup in both Expo Go and production builds */}
        {showSplash && (
          <Animated.View
            pointerEvents={appReady ? 'none' : 'auto'}
            style={[
              StyleSheet.absoluteFill,
              styles.splashContainer,
              { backgroundColor: splashBg, opacity: splashOpacity },
            ]}
          >
            <Image
              source={require('../assets/images/splash.png')}
              style={styles.splashLogo}
              resizeMode="contain"
              accessibilityLabel="GuideMate launch logo"
            />
          </Animated.View>
        )}
      </PreferencesProvider>
    </SafeAreaProvider>
  );
}

const styles = StyleSheet.create({
  splashContainer: {
    justifyContent: 'center',
    alignItems: 'center',
    zIndex: 999999,
    elevation: 999999,
  },
  splashLogo: {
    width: 160,
    height: 160,
  },
});
