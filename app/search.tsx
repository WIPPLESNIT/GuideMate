import { Ionicons } from '@expo/vector-icons';
import { useFocusEffect, useRouter } from 'expo-router';
import React, { useCallback, useState } from 'react';
import {
    FlatList,
    StatusBar,
    StyleSheet,
    Text,
    TextInput,
    TouchableOpacity,
    View,
} from 'react-native';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import { useTheme } from '../lib/theme';
import {
    addSearchHistory,
    clearSearchHistory,
    getSearchHistory,
    removeSearchHistory,
} from '../lib/searchHistory';

export default function SearchScreen() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { colors, isDark, shadow } = useTheme();

  const [query, setQuery] = useState('');
  const [history, setHistory] = useState<string[]>([]);

  useFocusEffect(
    useCallback(() => {
      getSearchHistory().then(setHistory);
    }, [])
  );

  const runSearch = async (term: string) => {
    const clean = term.trim();
    if (!clean) return;
    await addSearchHistory(clean);
    router.replace({ pathname: '/things-to-do', params: { q: clean } });
  };

  const removeOne = async (term: string) => {
    setHistory(await removeSearchHistory(term));
  };

  const clearAll = async () => {
    await clearSearchHistory();
    setHistory([]);
  };

  // While typing, surface matching past searches as suggestions.
  const shown = query.trim()
    ? history.filter((h) => h.toLowerCase().includes(query.trim().toLowerCase()))
    : history;

  return (
    <SafeAreaView style={[styles.container, { backgroundColor: colors.bgAlt }]} edges={['left', 'right']}>
      <StatusBar barStyle={isDark ? 'light-content' : 'dark-content'} />

      {/* Search header */}
      <View style={[styles.header, { paddingTop: insets.top + 8, backgroundColor: colors.card, borderBottomColor: colors.border }]}>
        <TouchableOpacity onPress={() => router.back()} style={styles.backBtn} activeOpacity={0.7}>
          <Ionicons name="chevron-back" size={26} color={colors.text} />
        </TouchableOpacity>
        <View style={[styles.searchBar, { backgroundColor: colors.bgAlt, borderColor: colors.border }]}>
          <Ionicons name="search" size={18} color={colors.textMute} />
          <TextInput
            style={[styles.input, { color: colors.text }]}
            placeholder="Search places in Cebu"
            placeholderTextColor={colors.textMute}
            value={query}
            onChangeText={setQuery}
            autoFocus
            returnKeyType="search"
            onSubmitEditing={() => runSearch(query)}
          />
          {query ? (
            <TouchableOpacity onPress={() => setQuery('')} hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }}>
              <Ionicons name="close-circle" size={18} color={colors.textMute} />
            </TouchableOpacity>
          ) : null}
        </View>
      </View>

      <FlatList
        data={shown}
        keyExtractor={(item) => item}
        keyboardShouldPersistTaps="handled"
        contentContainerStyle={{ paddingBottom: 40 }}
        ListHeaderComponent={
          shown.length > 0 ? (
            <View style={styles.listTop}>
              <Text style={[styles.sectionTitle, { color: colors.text }]}>
                {query.trim() ? 'Suggestions' : 'Recent searches'}
              </Text>
              {!query.trim() ? (
                <TouchableOpacity onPress={clearAll} hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }}>
                  <Text style={[styles.clearAll, { color: colors.primary }]}>Clear all</Text>
                </TouchableOpacity>
              ) : null}
            </View>
          ) : null
        }
        renderItem={({ item }) => (
          <TouchableOpacity
            style={[styles.row, { borderBottomColor: colors.border }]}
            activeOpacity={0.6}
            onPress={() => runSearch(item)}
          >
            <Ionicons name="time-outline" size={20} color={colors.textMute} style={styles.rowIcon} />
            <Text style={[styles.rowText, { color: colors.text }]} numberOfLines={1}>{item}</Text>
            <TouchableOpacity
              onPress={() => removeOne(item)}
              hitSlop={{ top: 10, bottom: 10, left: 10, right: 10 }}
            >
              <Ionicons name="close" size={18} color={colors.textMute} />
            </TouchableOpacity>
          </TouchableOpacity>
        )}
        ListEmptyComponent={
          <View style={[styles.empty, shadow.sm]}>
            <Ionicons name="search-outline" size={40} color={colors.textMute} />
            <Text style={[styles.emptyTitle, { color: colors.text }]}>
              {query.trim() ? `Search for "${query.trim()}"` : 'No recent searches'}
            </Text>
            <Text style={[styles.emptySub, { color: colors.textSub }]}>
              {query.trim()
                ? 'Press search on your keyboard to see results.'
                : 'Your recent searches will appear here.'}
            </Text>
          </View>
        }
      />
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1 },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 8,
    paddingBottom: 12,
    borderBottomWidth: StyleSheet.hairlineWidth,
  },
  backBtn: { width: 40, height: 44, alignItems: 'center', justifyContent: 'center' },
  searchBar: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    borderWidth: 1,
    borderRadius: 999,
    paddingHorizontal: 14,
    height: 44,
    gap: 8,
  },
  input: { flex: 1, fontSize: 15, padding: 0 },
  listTop: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 20,
    paddingTop: 18,
    paddingBottom: 6,
  },
  sectionTitle: { fontSize: 15, fontWeight: '700' },
  clearAll: { fontSize: 14, fontWeight: '700' },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 20,
    paddingVertical: 15,
    borderBottomWidth: StyleSheet.hairlineWidth,
  },
  rowIcon: { marginRight: 14 },
  rowText: { flex: 1, fontSize: 15.5 },
  empty: { alignItems: 'center', justifyContent: 'center', paddingHorizontal: 40, paddingTop: 70 },
  emptyTitle: { fontSize: 16, fontWeight: '700', marginTop: 14, textAlign: 'center' },
  emptySub: { fontSize: 13.5, marginTop: 6, textAlign: 'center', lineHeight: 19 },
});
