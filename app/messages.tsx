import { Ionicons } from '@expo/vector-icons';
import { useFocusEffect, useRouter } from 'expo-router';
import React, { useCallback, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  FlatList,
  Image,
  Modal,
  Pressable,
  RefreshControl,
  StatusBar,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import { useTheme } from '../lib/theme';
import {
  ApiChatPartner,
  ApiConversation,
  archiveConversation,
  deleteConversation,
  getConversations,
  getGuides,
  isUnauthorized,
  resolveImage,
  unarchiveConversation,
} from '../services/api';

function timeLabel(value: string): string {
  if (!value) return '';
  const d = new Date(value.replace(' ', 'T'));
  if (isNaN(d.getTime())) return '';
  const now = new Date();
  const sameDay = d.toDateString() === now.toDateString();
  if (sameDay) {
    return d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
  }
  return d.toLocaleDateString([], { month: 'short', day: 'numeric' });
}

export default function MessagesScreen() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { colors, isDark } = useTheme();

  const [tab, setTab] = useState<'inbox' | 'archived'>('inbox');
  const [items, setItems] = useState<ApiConversation[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [loggedIn, setLoggedIn] = useState(true);

  const [guidesOpen, setGuidesOpen] = useState(false);
  const [guides, setGuides] = useState<ApiChatPartner[]>([]);
  const [guidesLoading, setGuidesLoading] = useState(false);

  const load = useCallback(
    async (silent = false) => {
      if (!silent) setLoading(true);
      try {
        const data = await getConversations(tab === 'archived');
        setItems(data.conversations);
        setLoggedIn(true);
      } catch (e) {
        if (isUnauthorized(e)) setLoggedIn(false);
      } finally {
        setLoading(false);
        setRefreshing(false);
      }
    },
    [tab]
  );

  useFocusEffect(
    useCallback(() => {
      load();
      const timer = setInterval(() => load(true), 7000);
      return () => clearInterval(timer);
    }, [load])
  );

  const openGuides = async () => {
    setGuidesOpen(true);
    setGuidesLoading(true);
    try {
      const data = await getGuides();
      setGuides(data.guides);
    } catch {
      // ignore
    } finally {
      setGuidesLoading(false);
    }
  };

  const rowActions = (conv: ApiConversation) => {
    const archived = tab === 'archived';
    Alert.alert(conv.partner_name, undefined, [
      {
        text: archived ? 'Unarchive' : 'Archive',
        onPress: async () => {
          try {
            archived ? await unarchiveConversation(conv.partner_id) : await archiveConversation(conv.partner_id);
            load(true);
          } catch {
            Alert.alert('Action failed', 'Please try again.');
          }
        },
      },
      {
        text: 'Delete conversation',
        style: 'destructive',
        onPress: () =>
          Alert.alert('Delete conversation?', 'This permanently removes all messages.', [
            { text: 'Cancel', style: 'cancel' },
            {
              text: 'Delete',
              style: 'destructive',
              onPress: async () => {
                try {
                  await deleteConversation(conv.partner_id);
                  load(true);
                } catch {
                  Alert.alert('Delete failed', 'Please try again.');
                }
              },
            },
          ]),
      },
      { text: 'Cancel', style: 'cancel' },
    ]);
  };

  const renderItem = ({ item }: { item: ApiConversation }) => {
    const avatar = resolveImage(item.partner_avatar);
    return (
      <Pressable
        style={({ pressed }) => [styles.row, { backgroundColor: pressed ? colors.bgAlt : colors.card, borderColor: colors.border }]}
        onPress={() => router.push(`/chat/${item.partner_id}`)}
        onLongPress={() => rowActions(item)}
      >
        {avatar ? (
          <Image source={{ uri: avatar }} style={styles.avatar} />
        ) : (
          <View style={[styles.avatar, styles.avatarFallback, { backgroundColor: colors.chipBg }]}>
            <Text style={{ color: colors.primary, fontWeight: '800', fontSize: 18 }}>
              {item.partner_name.charAt(0).toUpperCase()}
            </Text>
          </View>
        )}
        <View style={{ flex: 1, marginLeft: 12 }}>
          <View style={styles.rowTop}>
            <Text style={[styles.name, { color: colors.text }]} numberOfLines={1}>
              {item.is_pinned ? '📌 ' : ''}{item.partner_name}
            </Text>
            <Text style={[styles.time, { color: colors.textMute }]}>{timeLabel(item.last_at)}</Text>
          </View>
          <View style={styles.rowTop}>
            <Text
              style={[styles.preview, { color: item.unread > 0 ? colors.text : colors.textSub, fontWeight: item.unread > 0 ? '700' : '500' }]}
              numberOfLines={1}
            >
              {item.last_body}
            </Text>
            {item.unread > 0 ? (
              <View style={[styles.badge, { backgroundColor: colors.primary }]}>
                <Text style={styles.badgeText}>{item.unread > 99 ? '99+' : item.unread}</Text>
              </View>
            ) : null}
          </View>
        </View>
      </Pressable>
    );
  };

  return (
    <SafeAreaView style={[styles.container, { backgroundColor: colors.bgAlt }]} edges={['left', 'right']}>
      <StatusBar barStyle={isDark ? 'light-content' : 'dark-content'} />

      <View style={[styles.header, { backgroundColor: colors.card, borderBottomColor: colors.border, paddingTop: insets.top + 10 }]}>
        <TouchableOpacity onPress={() => router.back()} style={styles.headerBtn} activeOpacity={0.7}>
          <Ionicons name="chevron-back" size={26} color={colors.text} />
        </TouchableOpacity>
        <Text style={[styles.headerTitle, { color: colors.text }]}>Messages</Text>
        <TouchableOpacity onPress={openGuides} style={styles.headerBtn} activeOpacity={0.7}>
          <Ionicons name="create-outline" size={24} color={colors.primary} />
        </TouchableOpacity>
      </View>

      {/* Tabs */}
      <View style={[styles.tabs, { backgroundColor: colors.card, borderBottomColor: colors.border }]}>
        {(['inbox', 'archived'] as const).map((key) => (
          <TouchableOpacity key={key} style={styles.tab} onPress={() => setTab(key)} activeOpacity={0.7}>
            <Text style={[styles.tabText, { color: tab === key ? colors.primary : colors.textSub }]}>
              {key === 'inbox' ? 'Inbox' : 'Archived'}
            </Text>
            {tab === key ? <View style={[styles.tabUnderline, { backgroundColor: colors.primary }]} /> : null}
          </TouchableOpacity>
        ))}
      </View>

      {!loggedIn ? (
        <View style={styles.center}>
          <Ionicons name="chatbubbles-outline" size={54} color={colors.textMute} />
          <Text style={[styles.emptyTitle, { color: colors.text }]}>Sign in to view messages</Text>
          <TouchableOpacity style={[styles.signInBtn, { backgroundColor: colors.primary }]} onPress={() => router.replace('/(auth)/login')}>
            <Text style={styles.signInText}>Sign in</Text>
          </TouchableOpacity>
        </View>
      ) : loading ? (
        <View style={styles.center}>
          <ActivityIndicator size="large" color={colors.primary} />
        </View>
      ) : (
        <FlatList
          data={items}
          keyExtractor={(c) => String(c.partner_id)}
          renderItem={renderItem}
          contentContainerStyle={{ padding: 14, paddingBottom: 30 }}
          ItemSeparatorComponent={() => <View style={{ height: 10 }} />}
          refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); load(); }} tintColor={colors.primary} />}
          ListEmptyComponent={
            <View style={styles.center}>
              <Ionicons name="chatbubble-ellipses-outline" size={54} color={colors.textMute} />
              <Text style={[styles.emptyTitle, { color: colors.text }]}>
                {tab === 'archived' ? 'No archived chats' : 'No messages yet'}
              </Text>
              <Text style={[styles.emptySub, { color: colors.textSub }]}>
                {tab === 'archived' ? 'Archived conversations show up here.' : 'Tap the pencil to message a tour guide.'}
              </Text>
            </View>
          }
        />
      )}

      {/* New chat — pick a guide */}
      <Modal visible={guidesOpen} transparent animationType="slide" onRequestClose={() => setGuidesOpen(false)}>
        <View style={styles.modalBackdrop}>
          <View style={[styles.sheet, { backgroundColor: colors.card }]}>
            <View style={styles.sheetHeader}>
              <Text style={[styles.sheetTitle, { color: colors.text }]}>Message a tour guide</Text>
              <TouchableOpacity onPress={() => setGuidesOpen(false)}>
                <Ionicons name="close" size={24} color={colors.textSub} />
              </TouchableOpacity>
            </View>
            {guidesLoading ? (
              <ActivityIndicator color={colors.primary} style={{ marginVertical: 30 }} />
            ) : (
              <FlatList
                data={guides}
                keyExtractor={(g) => String(g.id)}
                style={{ maxHeight: 380 }}
                ItemSeparatorComponent={() => <View style={{ height: 1, backgroundColor: colors.border }} />}
                renderItem={({ item }) => (
                  <TouchableOpacity
                    style={styles.guideRow}
                    activeOpacity={0.7}
                    onPress={() => {
                      setGuidesOpen(false);
                      router.push(`/chat/${item.id}`);
                    }}
                  >
                    {item.avatar ? (
                      <Image source={{ uri: resolveImage(item.avatar) }} style={styles.guideAvatar} />
                    ) : (
                      <View style={[styles.guideAvatar, styles.avatarFallback, { backgroundColor: colors.chipBg }]}>
                        <Text style={{ color: colors.primary, fontWeight: '800' }}>{item.name.charAt(0).toUpperCase()}</Text>
                      </View>
                    )}
                    <View style={{ flex: 1, marginLeft: 12 }}>
                      <Text style={[styles.name, { color: colors.text }]} numberOfLines={1}>{item.name}</Text>
                      {item.bio ? <Text style={[styles.preview, { color: colors.textSub }]} numberOfLines={1}>{item.bio}</Text> : null}
                    </View>
                    {item.online ? <View style={styles.onlineDot} /> : null}
                  </TouchableOpacity>
                )}
                ListEmptyComponent={
                  <Text style={[styles.emptySub, { color: colors.textSub, textAlign: 'center', padding: 24 }]}>
                    No tour guides available right now.
                  </Text>
                }
              />
            )}
          </View>
        </View>
      </Modal>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1 },
  header: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: 8, paddingBottom: 12, borderBottomWidth: StyleSheet.hairlineWidth },
  headerBtn: { width: 44, height: 36, alignItems: 'center', justifyContent: 'center' },
  headerTitle: { fontSize: 18, fontWeight: '800' },
  tabs: { flexDirection: 'row', borderBottomWidth: StyleSheet.hairlineWidth },
  tab: { flex: 1, alignItems: 'center', paddingVertical: 12 },
  tabText: { fontSize: 14, fontWeight: '700' },
  tabUnderline: { height: 2, width: 40, borderRadius: 2, marginTop: 6 },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 40 },
  emptyTitle: { fontSize: 17, fontWeight: '800', marginTop: 14 },
  emptySub: { fontSize: 13, marginTop: 6, textAlign: 'center', lineHeight: 19 },
  signInBtn: { marginTop: 18, paddingVertical: 12, paddingHorizontal: 28, borderRadius: 999 },
  signInText: { color: '#FFFFFF', fontWeight: '800' },
  row: { flexDirection: 'row', alignItems: 'center', padding: 12, borderRadius: 16, borderWidth: 1 },
  avatar: { width: 52, height: 52, borderRadius: 26 },
  avatarFallback: { alignItems: 'center', justifyContent: 'center' },
  rowTop: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  name: { fontSize: 15, fontWeight: '800', flex: 1, marginRight: 8 },
  time: { fontSize: 11, fontWeight: '600' },
  preview: { fontSize: 13, flex: 1, marginRight: 8, marginTop: 3 },
  badge: { minWidth: 20, height: 20, borderRadius: 10, paddingHorizontal: 6, alignItems: 'center', justifyContent: 'center' },
  badgeText: { color: '#FFFFFF', fontSize: 11, fontWeight: '800' },
  modalBackdrop: { flex: 1, backgroundColor: 'rgba(0,0,0,0.5)', justifyContent: 'flex-end' },
  sheet: { borderTopLeftRadius: 22, borderTopRightRadius: 22, padding: 18, paddingBottom: 30 },
  sheetHeader: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginBottom: 12 },
  sheetTitle: { fontSize: 17, fontWeight: '800' },
  guideRow: { flexDirection: 'row', alignItems: 'center', paddingVertical: 12 },
  guideAvatar: { width: 44, height: 44, borderRadius: 22 },
  onlineDot: { width: 11, height: 11, borderRadius: 6, backgroundColor: '#22C55E' },
});
