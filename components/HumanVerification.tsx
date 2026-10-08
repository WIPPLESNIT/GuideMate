import { Ionicons } from '@expo/vector-icons';
import React, { useState } from 'react';
import { ActivityIndicator, StyleSheet, Text, TouchableOpacity, View } from 'react-native';

type Status = 'idle' | 'verifying' | 'verified';

type Props = {
  isDark: boolean;
  onVerifiedChange: (verified: boolean) => void;
  label?: string;
  verifyingLabel?: string;
  successLabel?: string;
};

// Cloudflare Turnstile–style "Verify you are human" widget.
// Tapping the checkbox shows a short "Verifying..." spinner, then a success state.
export default function HumanVerification({
  isDark,
  onVerifiedChange,
  label = 'Verify you are human',
  verifyingLabel = 'Verifying...',
  successLabel = 'Success!',
}: Props) {
  const [status, setStatus] = useState<Status>('idle');

  const theme = {
    boxBg: isDark ? '#1E2029' : '#FAFAFA',
    border: isDark ? '#2A2D38' : '#D1D5DB',
    text: isDark ? '#E5E7EB' : '#374151',
    sub: isDark ? '#6B7280' : '#9CA3AF',
    accent: '#22C55E',
  };

  const handlePress = () => {
    if (status !== 'idle') {
      return;
    }
    setStatus('verifying');
    // Simulate the verification challenge running.
    setTimeout(() => {
      setStatus('verified');
      onVerifiedChange(true);
    }, 1600);
  };

  return (
    <View style={[styles.container, { backgroundColor: theme.boxBg, borderColor: theme.border }]}>
      <View style={styles.left}>
        <TouchableOpacity
          style={[styles.checkbox, { borderColor: status === 'verified' ? theme.accent : theme.sub }]}
          onPress={handlePress}
          activeOpacity={0.8}
          disabled={status !== 'idle'}
        >
          {status === 'verifying' && <ActivityIndicator size="small" color={theme.accent} />}
          {status === 'verified' && <Ionicons name="checkmark" size={18} color={theme.accent} />}
        </TouchableOpacity>

        <Text style={[styles.label, { color: theme.text }]} numberOfLines={2}>
          {status === 'verifying' ? verifyingLabel : status === 'verified' ? successLabel : label}
        </Text>
      </View>

      <View style={styles.right}>
        <Ionicons name="shield-checkmark" size={20} color={theme.accent} />
        <Text style={[styles.brand, { color: theme.sub }]}>GuideMate{'\n'}Security</Text>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    borderWidth: 1,
    borderRadius: 10,
    paddingVertical: 12,
    paddingHorizontal: 14,
    marginBottom: 14,
  },
  left: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
  },
  checkbox: {
    width: 26,
    height: 26,
    borderRadius: 5,
    borderWidth: 2,
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 12,
  },
  label: {
    flex: 1,
    fontSize: 14,
    fontWeight: '600',
  },
  right: {
    flexShrink: 0,
    flexDirection: 'row',
    alignItems: 'center',
    marginLeft: 10,
  },
  brand: {
    fontSize: 9,
    fontWeight: '700',
    marginLeft: 6,
    lineHeight: 11,
  },
});
