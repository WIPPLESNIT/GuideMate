import { Ionicons } from '@expo/vector-icons';
import { useLocalSearchParams, useRouter } from 'expo-router';
import React, { useCallback, useEffect, useRef, useState } from 'react';
import {
  ActivityIndicator,
  FlatList,
  Image,
  KeyboardAvoidingView,
  Platform,
  StatusBar,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import { getSession } from '../../lib/authStore';
import { usePreferences } from '../../lib/preferences';
import { useTheme } from '../../lib/theme';
import {
  ApiChatPartner,
  ApiMessage,
  ChatStatus,
  getThread,
  pollThread,
  resolveImage,
  sendMessage,
} from '../../services/api';

function bubbleTime(value: string): string {
  if (!value) return '';
  const d = new Date(value.replace(' ', 'T'));
  if (isNaN(d.getTime())) return '';
  return d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
}

function StatusTick({ status, color }: { status: ChatStatus; color: string }) {
  if (status === 'sent') return <Ionicons name="checkmark" size={14} color={color} />;
  if (status === 'delivered') return <Ionicons name="checkmark-done" size={14} color={color} />;
  return <Ionicons name="checkmark-done" size={14} color="#5CC6FF" />; // read
}

export default function ChatScreen() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { colors, isDark } = useTheme();
  const { language, t } = usePreferences();
  const params = useLocalSearchParams<{ id: string }>();
  const partnerId = Number(params.id);

  const [partner, setPartner] = useState<ApiChatPartner | null>(null);
  const [messages, setMessages] = useState<ApiMessage[]>([]);
  const [online, setOnline] = useState(false);
  const [loading, setLoading] = useState(true);
  const [text, setText] = useState('');
  const [sending, setSending] = useState(false);
  const [isTourist, setIsTourist] = useState(true);

  // Auto-translate the guide's replies into the reader's app language.
  const [autoTranslate, setAutoTranslate] = useState(true);
  // Message ids the reader chose to view in the original language.
  const [showOriginal, setShowOriginal] = useState<Set<number>>(new Set());

  const translateLang = autoTranslate ? language : undefined;

  const listRef = useRef<FlatList<ApiMessage>>(null);
  const lastIdRef = useRef(0);

  const scrollToEnd = useCallback(() => {
    requestAnimationFrame(() => listRef.current?.scrollToEnd({ animated: true }));
  }, []);

  const loadInitial = useCallback(async () => {
    try {
      const data = await getThread(partnerId, translateLang);
      setPartner(data.partner);
      setOnline(data.partner.online);
      setMessages(data.messages);
      lastIdRef.current = data.messages.length ? data.messages[data.messages.length - 1].id : 0;
      scrollToEnd();
    } catch {
      // keep screen; user can go back
    } finally {
      setLoading(false);
    }
  }, [partnerId, translateLang, scrollToEnd]);

  const poll = useCallback(async () => {
    try {
      const data = await pollThread(partnerId, lastIdRef.current, translateLang);
      setOnline(data.partner_online);
      if (data.new.length) {
        setMessages((prev) => [...prev, ...data.new]);
        lastIdRef.current = data.new[data.new.length - 1].id;
        scrollToEnd();
      }
      if (data.statuses?.length) {
        const map = new Map(data.statuses.map((s) => [s.id, s.status]));
        setMessages((prev) => prev.map((m) => (map.has(m.id) ? { ...m, status: map.get(m.id)! } : m)));
      }
    } catch {
      // transient — try again next tick
    }
  }, [partnerId, translateLang, scrollToEnd]);

  useEffect(() => {
    getSession().then((session) => {
      setIsTourist((session?.role ?? 'tourist') === 'tourist');
    });
  }, []);

  useEffect(() => {
    loadInitial();
  }, [loadInitial]);

  useEffect(() => {
    const timer = setInterval(poll, 4000);
    return () => clearInterval(timer);
  }, [poll]);

  const handleSend = async () => {
    const body = text.trim();
    if (!body || sending) return;
    setSending(true);
    setText('');
    try {
      const res = await sendMessage(partnerId, body);
      if (res.message) {
        setMessages((prev) => [...prev, res.message!]);
        lastIdRef.current = Math.max(lastIdRef.current, res.message.id);
        scrollToEnd();
      }
    } catch {
      setText(body); // restore so the user doesn't lose their text
    } finally {
      setSending(false);
    }
  };

  const toggleOriginal = (id: number) => {
    setShowOriginal((prev) => {
      const next = new Set(prev);
      next.has(id) ? next.delete(id) : next.add(id);
      return next;
    });
  };

  const renderItem = ({ item }: { item: ApiMessage }) => {
    const hasTranslation = !!item.translated && !!item.translated_body;
    const viewingOriginal = showOriginal.has(item.id);
    const displayBody = hasTranslation && !viewingOriginal ? item.translated_body! : item.body;

    return (
      <View style={[styles.bubbleRow, { justifyContent: item.mine ? 'flex-end' : 'flex-start' }]}>
        <View
          style={[
            styles.bubble,
            item.mine
              ? { backgroundColor: colors.primary, borderBottomRightRadius: 4 }
              : { backgroundColor: colors.card, borderBottomLeftRadius: 4, borderWidth: 1, borderColor: colors.border },
          ]}
        >
          <Text style={[styles.bubbleText, { color: item.mine ? '#FFFFFF' : colors.text }]}>{displayBody}</Text>

          {hasTranslation ? (
            <TouchableOpacity
              onPress={() => toggleOriginal(item.id)}
              activeOpacity={0.7}
              style={styles.translateToggle}
            >
              <Ionicons name="language-outline" size={12} color={colors.primary} />
              <Text style={[styles.translateToggleText, { color: colors.primary }]}>
                {viewingOriginal ? t('show_translation') : t('show_original')}
              </Text>
            </TouchableOpacity>
          ) : null}

          <View style={styles.metaRow}>
            <Text style={[styles.metaTime, { color: item.mine ? 'rgba(255,255,255,0.75)' : colors.textMute }]}>
              {bubbleTime(item.created_at)}
            </Text>
            {item.mine ? <View style={{ marginLeft: 4 }}><StatusTick status={item.status} color="rgba(255,255,255,0.8)" /></View> : null}
          </View>
        </View>
      </View>
    );
  };

  return (
    <SafeAreaView style={[styles.container, { backgroundColor: colors.bgAlt }]} edges={['left', 'right']}>
      <StatusBar barStyle={isDark ? 'light-content' : 'dark-content'} />

      {/* Header */}
      <View style={[styles.header, { backgroundColor: colors.card, borderBottomColor: colors.border, paddingTop: insets.top + 8 }]}>
        <TouchableOpacity onPress={() => router.back()} style={styles.iconBtn} activeOpacity={0.7}>
          <Ionicons name="chevron-back" size={26} color={colors.text} />
        </TouchableOpacity>

        {partner?.avatar ? (
          <Image source={{ uri: resolveImage(partner.avatar) }} style={styles.headerAvatar} />
        ) : (
          <View style={[styles.headerAvatar, { backgroundColor: colors.chipBg, alignItems: 'center', justifyContent: 'center' }]}>
            <Text style={{ color: colors.primary, fontWeight: '800' }}>{partner?.name?.charAt(0).toUpperCase() ?? '?'}</Text>
          </View>
        )}
        <View style={{ flex: 1, marginLeft: 10 }}>
          <Text style={[styles.headerName, { color: colors.text }]} numberOfLines={1}>{partner?.name ?? 'Chat'}</Text>
          <Text style={[styles.headerStatus, { color: online ? '#22C55E' : colors.textMute }]}>
            {online ? 'Online' : 'Offline'}
          </Text>
        </View>

        <TouchableOpacity
          style={styles.iconBtn}
          activeOpacity={0.7}
          onPress={() => setAutoTranslate((v) => !v)}
        >
          <Ionicons name="language" size={21} color={autoTranslate ? colors.primary : colors.textMute} />
        </TouchableOpacity>
        {!isTourist ? (
          <>
            <TouchableOpacity style={styles.iconBtn} activeOpacity={0.7} onPress={() => router.push(`/call/${partnerId}?video=0&name=${encodeURIComponent(partner?.name ?? '')}`)}>
              <Ionicons name="call" size={21} color={colors.primary} />
            </TouchableOpacity>
            <TouchableOpacity style={styles.iconBtn} activeOpacity={0.7} onPress={() => router.push(`/call/${partnerId}?video=1&name=${encodeURIComponent(partner?.name ?? '')}`)}>
              <Ionicons name="videocam" size={23} color={colors.primary} />
            </TouchableOpacity>
          </>
        ) : null}
      </View>

      {loading ? (
        <View style={styles.center}>
          <ActivityIndicator size="large" color={colors.primary} />
        </View>
      ) : (
        <KeyboardAvoidingView
          style={{ flex: 1 }}
          behavior={Platform.OS === 'ios' ? 'padding' : undefined}
          keyboardVerticalOffset={Platform.OS === 'ios' ? 0 : 0}
        >
          <FlatList
            ref={listRef}
            data={messages}
            keyExtractor={(m) => String(m.id)}
            renderItem={renderItem}
            contentContainerStyle={{ padding: 14, paddingBottom: 16 }}
            onContentSizeChange={scrollToEnd}
            ListEmptyComponent={
              <View style={styles.center}>
                <Ionicons name="chatbubble-ellipses-outline" size={48} color={colors.textMute} />
                <Text style={[styles.emptyText, { color: colors.textSub }]}>Say hello to {partner?.name ?? 'your guide'} 👋</Text>
              </View>
            }
          />

          <View style={[styles.inputBar, { backgroundColor: colors.card, borderTopColor: colors.border, paddingBottom: insets.bottom + 8 }]}>
            <TextInput
              style={[styles.input, { backgroundColor: colors.bgAlt, color: colors.text }]}
              placeholder="Type a message..."
              placeholderTextColor={colors.textMute}
              value={text}
              onChangeText={setText}
              multiline
            />
            <TouchableOpacity
              style={[styles.sendBtn, { backgroundColor: text.trim() ? colors.primary : colors.border }]}
              onPress={handleSend}
              disabled={!text.trim() || sending}
              activeOpacity={0.8}
            >
              {sending ? <ActivityIndicator size="small" color="#FFFFFF" /> : <Ionicons name="send" size={18} color="#FFFFFF" />}
            </TouchableOpacity>
          </View>
        </KeyboardAvoidingView>
      )}
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1 },
  header: { flexDirection: 'row', alignItems: 'center', paddingHorizontal: 6, paddingBottom: 10, borderBottomWidth: StyleSheet.hairlineWidth },
  iconBtn: { width: 40, height: 38, alignItems: 'center', justifyContent: 'center' },
  headerAvatar: { width: 38, height: 38, borderRadius: 19 },
  headerName: { fontSize: 16, fontWeight: '800' },
  headerStatus: { fontSize: 11, fontWeight: '600', marginTop: 1 },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 40 },
  emptyText: { fontSize: 14, marginTop: 12, fontWeight: '600' },
  bubbleRow: { flexDirection: 'row', marginBottom: 8 },
  bubble: { maxWidth: '78%', borderRadius: 18, paddingHorizontal: 13, paddingVertical: 9 },
  bubbleText: { fontSize: 15, lineHeight: 20 },
  translateToggle: { flexDirection: 'row', alignItems: 'center', marginTop: 5 },
  translateToggleText: { fontSize: 11, fontWeight: '700', marginLeft: 4 },
  metaRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'flex-end', marginTop: 3 },
  metaTime: { fontSize: 10, fontWeight: '600' },
  inputBar: { flexDirection: 'row', alignItems: 'flex-end', paddingHorizontal: 12, paddingTop: 10, borderTopWidth: StyleSheet.hairlineWidth },
  input: { flex: 1, maxHeight: 120, minHeight: 44, borderRadius: 22, paddingHorizontal: 16, paddingTop: 11, paddingBottom: 11, fontSize: 15, marginRight: 10 },
  sendBtn: { width: 44, height: 44, borderRadius: 22, alignItems: 'center', justifyContent: 'center' },
});
