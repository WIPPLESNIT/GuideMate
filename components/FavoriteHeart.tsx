import { Ionicons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import React, { useState } from 'react';
import { ActivityIndicator, Alert, StyleSheet, TouchableOpacity, ViewStyle } from 'react-native';
import { restoreSession } from '../lib/authStore';
import { useTheme } from '../lib/theme';
import { isUnauthorized, toggleFavorite } from '../services/api';

type Props = {
  listingId: number;
  /** Initial favorited state (from the listing payload). */
  favorited?: boolean;
  /** Diameter of the round button. */
  size?: number;
  style?: ViewStyle | ViewStyle[];
  /** Fired after a successful toggle, e.g. to drop an item from the wishlist. */
  onChange?: (favorited: boolean) => void;
};

/**
 * A round heart button overlaid on listing cards / hero images. Manages its
 * own optimistic state, prompts guests to sign in, and reverts on failure.
 */
export default function FavoriteHeart({ listingId, favorited = false, size = 34, style, onChange }: Props) {
  const router = useRouter();
  const { colors, isDark } = useTheme();
  const [fav, setFav] = useState(favorited);
  const [busy, setBusy] = useState(false);

  // Keep in sync if the parent list re-renders with fresh data.
  React.useEffect(() => setFav(favorited), [favorited]);

  const onPress = async () => {
    if (busy) return;
    const session = await restoreSession();
    if (!session) {
      Alert.alert('Sign in required', 'Log in to save places to your wishlist.', [
        { text: 'Cancel', style: 'cancel' },
        { text: 'Sign in', onPress: () => router.push('/(auth)/login') },
      ]);
      return;
    }

    const next = !fav;
    setFav(next); // optimistic
    setBusy(true);
    try {
      const res = await toggleFavorite(listingId);
      setFav(res.favorited);
      onChange?.(res.favorited);
    } catch (e) {
      setFav(!next); // revert
      if (isUnauthorized(e)) {
        Alert.alert('Session expired', 'Please sign in again to save favorites.');
      } else {
        Alert.alert('Could not update', e instanceof Error ? e.message : 'Please try again.');
      }
    } finally {
      setBusy(false);
    }
  };

  const dim = { width: size, height: size, borderRadius: size / 2 };

  return (
    <TouchableOpacity
      accessibilityRole="button"
      accessibilityLabel={fav ? 'Remove from wishlist' : 'Add to wishlist'}
      activeOpacity={0.85}
      onPress={onPress}
      hitSlop={{ top: 6, bottom: 6, left: 6, right: 6 }}
      style={[styles.btn, dim, { backgroundColor: isDark ? 'rgba(0,0,0,0.55)' : 'rgba(255,255,255,0.92)' }, style]}
    >
      {busy ? (
        <ActivityIndicator size="small" color="#EF4444" />
      ) : (
        <Ionicons
          name={fav ? 'heart' : 'heart-outline'}
          size={size * 0.55}
          color={fav ? '#EF4444' : colors.textSub}
        />
      )}
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  btn: {
    alignItems: 'center',
    justifyContent: 'center',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.15,
    shadowRadius: 3,
    elevation: 2,
  },
});
