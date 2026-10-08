import AsyncStorage from '@react-native-async-storage/async-storage';

const KEY = 'guidemate.search.history';
const MAX_ITEMS = 12;

// Recent search terms, most-recent first, persisted on-device (like Facebook).
export async function getSearchHistory(): Promise<string[]> {
  try {
    const raw = await AsyncStorage.getItem(KEY);
    if (!raw) return [];
    const parsed = JSON.parse(raw);
    return Array.isArray(parsed) ? parsed.filter((x) => typeof x === 'string') : [];
  } catch {
    return [];
  }
}

export async function addSearchHistory(term: string): Promise<string[]> {
  const clean = term.trim();
  if (!clean) return getSearchHistory();
  try {
    const current = await getSearchHistory();
    // De-dupe case-insensitively, keep the newest spelling at the top.
    const withoutDupe = current.filter((t) => t.toLowerCase() !== clean.toLowerCase());
    const next = [clean, ...withoutDupe].slice(0, MAX_ITEMS);
    await AsyncStorage.setItem(KEY, JSON.stringify(next));
    return next;
  } catch {
    return getSearchHistory();
  }
}

export async function removeSearchHistory(term: string): Promise<string[]> {
  try {
    const current = await getSearchHistory();
    const next = current.filter((t) => t !== term);
    await AsyncStorage.setItem(KEY, JSON.stringify(next));
    return next;
  } catch {
    return getSearchHistory();
  }
}

export async function clearSearchHistory(): Promise<void> {
  try {
    await AsyncStorage.removeItem(KEY);
  } catch {
    // ignore
  }
}
