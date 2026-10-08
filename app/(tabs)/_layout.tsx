import { Ionicons } from '@expo/vector-icons';
import { Tabs } from 'expo-router';
import * as Haptics from 'expo-haptics';
import React from 'react';
import { Platform } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { usePreferences } from '../../lib/preferences';
import { useTheme } from '../../lib/theme';

export default function TabsLayout() {
  const insets = useSafeAreaInsets();
  const { t } = usePreferences();
  const { colors, shadow } = useTheme();

  const isAndroid = Platform.OS === 'android';

  // The Android on-screen navigation bar is hidden (immersive mode set in the
  // root layout), so we don't reserve its inset — that leaves a dark strip
  // under the icons. On Android we use a compact bar with no bottom padding so
  // the icons sit right at the bottom edge. On iOS we honour the home-indicator
  // inset so the icons clear the home bar.
  const bottomPad = isAndroid ? 0 : insets.bottom;
  const barHeight = isAndroid ? 56 : 58 + insets.bottom;

  const haptic = () => Haptics.selectionAsync().catch(() => {});

  return (
    <Tabs
      screenListeners={{ tabPress: haptic }}
      screenOptions={{
        tabBarActiveTintColor: colors.primary,
        tabBarInactiveTintColor: colors.textMute,
        // Icons only — labels hidden for a cleaner, self-explanatory tab bar.
        tabBarShowLabel: false,
        tabBarStyle: {
          backgroundColor: colors.card,
          borderTopWidth: 1,
          borderTopColor: colors.border,
          height: barHeight,
          paddingBottom: bottomPad,
          paddingTop: 0,
          ...(Platform.OS === 'ios' ? shadow.md : { elevation: 12 }),
        },
        tabBarItemStyle: {
          justifyContent: 'center',
          alignItems: 'center',
        },
        headerShown: false,
      }}
    >
      <Tabs.Screen
        name="index"
        options={{
          title: t('tab_home'),
          tabBarIcon: ({ color, focused }) => (
            <Ionicons name={focused ? 'home' : 'home-outline'} size={23} color={color} />
          ),
        }}
      />
      <Tabs.Screen
        name="wishlist"
        options={{
          title: t('tab_wishlist'),
          tabBarIcon: ({ color, focused }) => (
            <Ionicons name={focused ? 'heart' : 'heart-outline'} size={23} color={color} />
          ),
        }}
      />
      <Tabs.Screen
        name="sale"
        options={{
          title: t('tab_sale'),
          tabBarIcon: ({ color, focused }) => (
            <Ionicons name={focused ? 'pricetag' : 'pricetag-outline'} size={23} color={color} />
          ),
        }}
      />
      <Tabs.Screen
        name="trips"
        options={{
          title: t('tab_trips'),
          tabBarIcon: ({ color, focused }) => (
            <Ionicons name={focused ? 'briefcase' : 'briefcase-outline'} size={23} color={color} />
          ),
        }}
      />
      <Tabs.Screen
        name="account"
        options={{
          title: t('tab_account'),
          tabBarIcon: ({ color, focused }) => (
            <Ionicons name={focused ? 'person' : 'person-outline'} size={23} color={color} />
          ),
        }}
      />
    </Tabs>
  );
}
