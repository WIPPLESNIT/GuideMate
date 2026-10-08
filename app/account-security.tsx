import { Ionicons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import React, { useState } from 'react';
import {
    Alert,
    Modal,
    StatusBar,
    StyleSheet,
    Text,
    TextInput,
    TouchableOpacity,
    useColorScheme,
    View,
} from 'react-native';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import { deleteAccount } from '../lib/authStore';

export default function AccountSecurityScreen() {
  const router = useRouter();
  const colorScheme = useColorScheme();
  const isDark = colorScheme === 'dark';
  const insets = useSafeAreaInsets();

  const [deleteOpen, setDeleteOpen] = useState(false);
  const [password, setPassword] = useState('');
  const [deleting, setDeleting] = useState(false);

  const theme = {
    bg: isDark ? '#0F1012' : '#F2F3F5',
    card: isDark ? '#1E2029' : '#FFFFFF',
    border: isDark ? '#2A2D38' : '#ECECEC',
    textMain: isDark ? '#FFFFFF' : '#1A1A1A',
    textSub: isDark ? '#9CA3AF' : '#8A8A8A',
    accent: '#22C55E',
    danger: '#EF4444',
    inputBg: isDark ? '#15161A' : '#F1F5F9',
  };

  const confirmDelete = async () => {
    if (!password.trim()) {
      Alert.alert('Password required', 'Please enter your password to confirm.');
      return;
    }
    setDeleting(true);
    try {
      await deleteAccount(password.trim());
      setDeleteOpen(false);
      Alert.alert('Account deleted', 'Your account has been permanently deleted.', [
        { text: 'OK', onPress: () => router.replace('/(auth)/login') },
      ]);
    } catch (e) {
      Alert.alert('Could not delete', e instanceof Error ? e.message : 'Please try again.');
    } finally {
      setDeleting(false);
    }
  };

  return (
    <SafeAreaView style={[styles.container, { backgroundColor: theme.bg }]}>
      <StatusBar barStyle={isDark ? 'light-content' : 'dark-content'} backgroundColor={theme.card} />

      <View style={[styles.header, { backgroundColor: theme.card, borderBottomColor: theme.border, paddingTop: insets.top + 12 }]}>
        <TouchableOpacity onPress={() => router.back()} style={styles.back} activeOpacity={0.7}>
          <Ionicons name="chevron-back" size={26} color={theme.textMain} />
        </TouchableOpacity>
        <Text style={[styles.headerTitle, { color: theme.textMain }]}>Account security</Text>
        <View style={styles.back} />
      </View>

      <View style={styles.body}>
        <View style={[styles.group, { backgroundColor: theme.card }]}>
          <TouchableOpacity style={styles.row} activeOpacity={0.6} onPress={() => router.push('/change-password')}>
            <View style={styles.rowLeft}>
              <Ionicons name="key-outline" size={22} color={theme.textMain} style={styles.rowIcon} />
              <Text style={[styles.rowLabel, { color: theme.textMain }]}>Change Password</Text>
            </View>
            <Ionicons name="chevron-forward" size={18} color={theme.textSub} />
          </TouchableOpacity>

          <TouchableOpacity
            style={[styles.row, { borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: theme.border }]}
            activeOpacity={0.6}
            onPress={() => setDeleteOpen(true)}
          >
            <View style={styles.rowLeft}>
              <Ionicons name="trash-outline" size={22} color={theme.danger} style={styles.rowIcon} />
              <Text style={[styles.rowLabel, { color: theme.danger }]}>Delete Account</Text>
            </View>
            <Ionicons name="chevron-forward" size={18} color={theme.textSub} />
          </TouchableOpacity>
        </View>

        <Text style={[styles.note, { color: theme.textSub }]}>
          Deleting your account permanently removes your profile and data. This cannot be undone.
        </Text>
      </View>

      {/* Delete confirmation modal */}
      <Modal visible={deleteOpen} transparent animationType="fade" onRequestClose={() => setDeleteOpen(false)}>
        <View style={styles.modalBackdrop}>
          <View style={[styles.modalCard, { backgroundColor: theme.card }]}>
            <Text style={[styles.modalTitle, { color: theme.textMain }]}>Delete account?</Text>
            <Text style={[styles.modalText, { color: theme.textSub }]}>
              This will permanently delete your account. Enter your password to confirm.
            </Text>
            <TextInput
              style={[styles.input, { backgroundColor: theme.inputBg, color: theme.textMain }]}
              placeholder="Password"
              placeholderTextColor={theme.textSub}
              secureTextEntry
              value={password}
              onChangeText={setPassword}
            />
            <View style={styles.modalActions}>
              <TouchableOpacity
                style={[styles.modalBtn, { backgroundColor: 'transparent' }]}
                onPress={() => {
                  setDeleteOpen(false);
                  setPassword('');
                }}
                disabled={deleting}
              >
                <Text style={[styles.modalBtnText, { color: theme.textSub }]}>Cancel</Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={[styles.modalBtn, { backgroundColor: theme.danger }, deleting && { opacity: 0.6 }]}
                onPress={confirmDelete}
                disabled={deleting}
              >
                <Text style={[styles.modalBtnText, { color: '#FFFFFF' }]}>{deleting ? 'Deleting...' : 'Delete'}</Text>
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1 },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 8,
    paddingVertical: 12,
    borderBottomWidth: StyleSheet.hairlineWidth,
  },
  back: { width: 40, height: 32, alignItems: 'center', justifyContent: 'center' },
  headerTitle: { fontSize: 18, fontWeight: '700' },
  body: { padding: 16 },
  group: { borderRadius: 14, overflow: 'hidden', marginTop: 8 },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 18,
    paddingVertical: 18,
  },
  rowLeft: { flexDirection: 'row', alignItems: 'center' },
  rowIcon: { marginRight: 14 },
  rowLabel: { fontSize: 16, fontWeight: '600' },
  note: { fontSize: 13, lineHeight: 19, marginTop: 16, paddingHorizontal: 4 },
  modalBackdrop: { flex: 1, backgroundColor: 'rgba(0,0,0,0.5)', alignItems: 'center', justifyContent: 'center', padding: 28 },
  modalCard: { width: '100%', borderRadius: 18, padding: 22 },
  modalTitle: { fontSize: 18, fontWeight: '800', marginBottom: 8 },
  modalText: { fontSize: 14, lineHeight: 20, marginBottom: 16 },
  input: { borderRadius: 12, paddingVertical: 13, paddingHorizontal: 16, fontSize: 15, marginBottom: 18 },
  modalActions: { flexDirection: 'row', justifyContent: 'flex-end', alignItems: 'center' },
  modalBtn: { paddingVertical: 11, paddingHorizontal: 22, borderRadius: 10, marginLeft: 10 },
  modalBtnText: { fontSize: 15, fontWeight: '700' },
});
