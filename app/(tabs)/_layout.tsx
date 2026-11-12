import { Ionicons } from '@expo/vector-icons';
import { Tabs } from 'expo-router';
import * as Haptics from 'expo-haptics';
import React from 'react';
import { Platform, Pressable } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { usePreferences } from '../../lib/preferences';
import { useTheme } from '../../lib/theme';

export default function TabsLayout() {
  const insets = useSafeAreaInsets();
  const { t } = usePreferences();
  const { colors, shadow } = useTheme();

  const isAndroid = Platform.OS === 'android';

  // Symmetrical vertical padding gives icons breathing room from the top border
  // while keeping them comfortable to tap and clearing the navigation area.
  const bottomPad = isAndroid ? 8 : Math.max(insets.bottom, 6);
  const topPad = 8;
  const barHeight = 58 + bottomPad;

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
          backgroundColor: colors.card as string,
          borderTopWidth: 1,
          borderTopColor: colors.border as string,
          height: barHeight,
          paddingTop: topPad,
          paddingBottom: bottomPad,
          ...(Platform.OS === 'ios' ? shadow.md : { elevation: 12 }),
        },
        tabBarItemStyle: {
          justifyContent: 'center',
          alignItems: 'center',
          paddingVertical: 2,
        },
        // Custom pressable removes the circular Android ripple and web hover background artifacts
        tabBarButton: ({ ref, ...restProps }: any) => (
          <Pressable
            {...restProps}
            android_ripple={{ color: 'transparent' }}
            style={({ pressed }) => [
              restProps.style,
              {
                justifyContent: 'center',
                alignItems: 'center',
                backgroundColor: 'transparent',
              },
            ]}
          />
        ),
        headerShown: false,
      }}
    >
      <Tabs.Screen
        name="index"
        options={{
          title: t('tab_home'),
          tabBarIcon: ({ color, focused }) => (
            <Ionicons name={focused ? 'home' : 'home-outline'} size={23} color={typeof color === 'string' ? color : colors.primary} />
          ),
        }}
      />
      <Tabs.Screen
        name="wishlist"
        options={{
          title: t('tab_wishlist'),
          tabBarIcon: ({ color, focused }) => (
            <Ionicons name={focused ? 'heart' : 'heart-outline'} size={23} color={typeof color === 'string' ? color : colors.primary} />
          ),
        }}
      />
      <Tabs.Screen
        name="sale"
        options={{
          title: t('tab_sale'),
          tabBarIcon: ({ color, focused }) => (
            <Ionicons name={focused ? 'pricetag' : 'pricetag-outline'} size={23} color={typeof color === 'string' ? color : colors.primary} />
          ),
        }}
      />
      <Tabs.Screen
        name="trips"
        options={{
          title: t('tab_trips'),
          tabBarIcon: ({ color, focused }) => (
            <Ionicons name={focused ? 'briefcase' : 'briefcase-outline'} size={23} color={typeof color === 'string' ? color : colors.primary} />
          ),
        }}
      />
      <Tabs.Screen
        name="account"
        options={{
          title: t('tab_account'),
          tabBarIcon: ({ color, focused }) => (
            <Ionicons name={focused ? 'person' : 'person-outline'} size={23} color={typeof color === 'string' ? color : colors.primary} />
          ),
        }}
      />
    </Tabs>
  );
}
