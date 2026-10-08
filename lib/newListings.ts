import AsyncStorage from '@react-native-async-storage/async-storage';

// Remembers how many published places the user has already seen, so we can tell
// when guides publish new tours and surface a "New places to explore!" banner.
const SEEN_KEY = 'guidemate_places_seen_total';

export async function getSeenPlacesTotal(): Promise<number> {
  try {
    const raw = await AsyncStorage.getItem(SEEN_KEY);
    const n = raw == null ? NaN : parseInt(raw, 10);
    return Number.isFinite(n) ? n : -1; // -1 = never seen before
  } catch {
    return -1;
  }
}

export async function setSeenPlacesTotal(total: number): Promise<void> {
  try {
    await AsyncStorage.setItem(SEEN_KEY, String(Math.max(0, Math.floor(total))));
  } catch {
    // ignore
  }
}

/**
 * How many brand-new places have appeared since the user last looked.
 * Returns 0 on the very first run (nothing to compare against yet) so we don't
 * greet a fresh install with a "new places" banner for the whole catalog.
 */
export function newPlacesCount(currentTotal: number, seenTotal: number): number {
  if (seenTotal < 0) {
    return 0;
  }
  return Math.max(0, currentTotal - seenTotal);
}
