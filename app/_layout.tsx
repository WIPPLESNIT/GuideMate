import { Stack } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { useEffect } from 'react';
import { useColorScheme } from 'react-native';
import { SafeAreaProvider, initialWindowMetrics } from 'react-native-safe-area-context';
import * as SystemUI from 'expo-system-ui';
import { NavigationBar } from 'expo-navigation-bar';
import { restoreSession } from '../lib/authStore';
import { PreferencesProvider } from '../lib/preferences';

export default function RootLayout() {
  const scheme = useColorScheme();
  const isDark = scheme === 'dark';

  // Restore any saved login token at startup so authenticated API calls work.
  useEffect(() => {
    restoreSession();
  }, []);

  useEffect(() => {
    const backgroundColor = isDark ? '#0B0D12' : '#FFFFFF';
    SystemUI.setBackgroundColorAsync(backgroundColor);
  }, [isDark]);

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
        <StatusBar style="auto" translucent />
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
        <Stack.Screen name="car-rentals" />
        <Stack.Screen name="weather" />
        <Stack.Screen name="traffic" />

        {/* 10. Emergency SOS */}
        <Stack.Screen name="sos" />

        {/* 11. Trip navigation map (post-booking) */}
        <Stack.Screen name="trip-map" />
        </Stack>
      </PreferencesProvider>
    </SafeAreaProvider>
  );
}
