import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import { LinearGradient } from 'expo-linear-gradient';
import React from 'react';
import {
  ActivityIndicator,
  Animated,
  StyleSheet,
  Text,
  TextStyle,
  TouchableOpacity,
  View,
  ViewStyle,
} from 'react-native';
import { useTheme } from '../lib/theme';

// ── Button ────────────────────────────────────────────────────────────────────
type ButtonVariant = 'primary' | 'outline' | 'ghost' | 'accent';

export function AppButton({
  label,
  onPress,
  variant = 'primary',
  icon,
  loading,
  disabled,
  fullWidth = true,
  style,
}: {
  label: string;
  onPress?: () => void;
  variant?: ButtonVariant;
  icon?: keyof typeof Ionicons.glyphMap;
  loading?: boolean;
  disabled?: boolean;
  fullWidth?: boolean;
  style?: ViewStyle;
}) {
  const { colors, radius, gradients } = useTheme();
  const isGradient = variant === 'primary' || variant === 'accent';

  const handlePress = () => {
    if (disabled || loading) return;
    Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light).catch(() => {});
    onPress?.();
  };

  const content = (
    <>
      {loading ? (
        <ActivityIndicator color={variant === 'outline' || variant === 'ghost' ? colors.primary : '#FFFFFF'} />
      ) : (
        <>
          {icon ? (
            <Ionicons
              name={icon}
              size={18}
              color={variant === 'outline' || variant === 'ghost' ? colors.primary : '#FFFFFF'}
              style={{ marginRight: 8 }}
            />
          ) : null}
          <Text
            style={[
              styles.btnLabel,
              { color: variant === 'outline' || variant === 'ghost' ? colors.primary : '#FFFFFF' },
            ]}
          >
            {label}
          </Text>
        </>
      )}
    </>
  );

  const base: ViewStyle = {
    height: 52,
    borderRadius: radius.md,
    alignItems: 'center',
    justifyContent: 'center',
    flexDirection: 'row',
    opacity: disabled ? 0.5 : 1,
    alignSelf: fullWidth ? 'stretch' : 'flex-start',
    paddingHorizontal: fullWidth ? 0 : 24,
  };

  if (isGradient) {
    return (
      <TouchableOpacity activeOpacity={0.85} onPress={handlePress} disabled={disabled || loading} style={style}>
        <LinearGradient
          colors={variant === 'accent' ? gradients.sunset : gradients.brand}
          start={{ x: 0, y: 0 }}
          end={{ x: 1, y: 1 }}
          style={base}
        >
          {content}
        </LinearGradient>
      </TouchableOpacity>
    );
  }

  return (
    <TouchableOpacity
      activeOpacity={0.85}
      onPress={handlePress}
      disabled={disabled || loading}
      style={[
        base,
        variant === 'outline'
          ? { borderWidth: 1.5, borderColor: colors.primary, backgroundColor: 'transparent' }
          : { backgroundColor: colors.chipBg },
        style,
      ]}
    >
      {content}
    </TouchableOpacity>
  );
}

// ── Card ──────────────────────────────────────────────────────────────────────
export function Card({
  children,
  style,
  padded = true,
}: {
  children: React.ReactNode;
  style?: ViewStyle;
  padded?: boolean;
}) {
  const { colors, radius, shadow } = useTheme();
  return (
    <View
      style={[
        {
          backgroundColor: colors.card,
          borderRadius: radius.lg,
          padding: padded ? 16 : 0,
          borderWidth: 1,
          borderColor: colors.border,
        },
        shadow.sm,
        style,
      ]}
    >
      {children}
    </View>
  );
}

// ── Section header ─────────────────────────────────────────────────────────────
export function SectionHeader({
  title,
  actionLabel,
  onAction,
}: {
  title: string;
  actionLabel?: string;
  onAction?: () => void;
}) {
  const { colors, fontSize } = useTheme();
  return (
    <View style={styles.sectionHeader}>
      <Text style={[styles.sectionTitle, { color: colors.text, fontSize: fontSize.lg }]}>{title}</Text>
      {actionLabel ? (
        <TouchableOpacity onPress={onAction} hitSlop={8} activeOpacity={0.7}>
          <Text style={[styles.sectionAction, { color: colors.primary }]}>{actionLabel}</Text>
        </TouchableOpacity>
      ) : null}
    </View>
  );
}

// ── Rating pill ────────────────────────────────────────────────────────────────
export function RatingPill({
  rating,
  count,
  compact,
}: {
  rating: number;
  count?: number;
  compact?: boolean;
}) {
  const { brand } = useTheme();
  if (!rating || rating <= 0) return null;
  return (
    <View style={[styles.ratingPill, compact && { paddingVertical: 2, paddingHorizontal: 6 }]}>
      <Ionicons name="star" size={compact ? 10 : 12} color={brand.gold} />
      <Text style={[styles.ratingText, compact && { fontSize: 10 }]}>
        {rating.toFixed(1)}
        {count ? ` (${count})` : ''}
      </Text>
    </View>
  );
}

// ── Selectable chip ────────────────────────────────────────────────────────────
export function Chip({
  label,
  active,
  onPress,
}: {
  label: string;
  active?: boolean;
  onPress?: () => void;
}) {
  const { colors, radius } = useTheme();
  return (
    <TouchableOpacity
      activeOpacity={0.8}
      onPress={() => {
        Haptics.selectionAsync().catch(() => {});
        onPress?.();
      }}
      style={{
        paddingVertical: 9,
        paddingHorizontal: 16,
        borderRadius: radius.pill,
        marginRight: 10,
        borderWidth: 1.5,
        backgroundColor: active ? colors.primary : colors.card,
        borderColor: active ? colors.primary : colors.border,
      }}
    >
      <Text style={{ fontSize: 13, fontWeight: '700', color: active ? '#FFFFFF' : colors.textSub }}>{label}</Text>
    </TouchableOpacity>
  );
}

// ── Empty / error state ─────────────────────────────────────────────────────────
export function EmptyState({
  icon,
  title,
  subtitle,
  actionLabel,
  onAction,
}: {
  icon: keyof typeof Ionicons.glyphMap;
  title: string;
  subtitle?: string;
  actionLabel?: string;
  onAction?: () => void;
}) {
  const { colors } = useTheme();
  return (
    <View style={styles.emptyWrap}>
      <View style={[styles.emptyIconWrap, { backgroundColor: colors.chipBg }]}>
        <Ionicons name={icon} size={34} color={colors.textMute} />
      </View>
      <Text style={[styles.emptyTitle, { color: colors.text }]}>{title}</Text>
      {subtitle ? <Text style={[styles.emptySub, { color: colors.textSub }]}>{subtitle}</Text> : null}
      {actionLabel ? (
        <AppButton label={actionLabel} onPress={onAction} fullWidth={false} style={{ marginTop: 18 }} />
      ) : null}
    </View>
  );
}

// ── Large screen title ──────────────────────────────────────────────────────────
export function ScreenTitle({ title, subtitle, right }: { title: string; subtitle?: string; right?: React.ReactNode }) {
  const { colors, fontSize } = useTheme();
  return (
    <View style={styles.screenTitleRow}>
      <View style={{ flex: 1 }}>
        <Text style={[styles.screenTitle, { color: colors.text, fontSize: fontSize.xxl }]}>{title}</Text>
        {subtitle ? <Text style={[styles.screenSub, { color: colors.textSub }]}>{subtitle}</Text> : null}
      </View>
      {right}
    </View>
  );
}

export function AppToast({
  visible,
  title,
  subtitle,
  onHide,
  duration = 2400,
  top = 12,
}: {
  visible: boolean;
  title: string;
  subtitle?: string;
  onHide: () => void;
  duration?: number;
  top?: number;
}) {
  const { colors, radius, shadow, gradients, isDark } = useTheme();
  const opacity = React.useRef(new Animated.Value(0)).current;
  const translateY = React.useRef(new Animated.Value(-12)).current;
  const onHideRef = React.useRef(onHide);
  onHideRef.current = onHide;

  React.useEffect(() => {
    if (!visible) {
      opacity.setValue(0);
      translateY.setValue(-12);
      return;
    }
    Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success).catch(() => {});
    Animated.parallel([
      Animated.timing(opacity, { toValue: 1, duration: 220, useNativeDriver: true }),
      Animated.timing(translateY, { toValue: 0, duration: 220, useNativeDriver: true }),
    ]).start();
    const hide = setTimeout(() => {
      Animated.parallel([
        Animated.timing(opacity, { toValue: 0, duration: 180, useNativeDriver: true }),
        Animated.timing(translateY, { toValue: -8, duration: 180, useNativeDriver: true }),
      ]).start(({ finished }) => {
        if (finished) onHideRef.current();
      });
    }, duration);
    return () => clearTimeout(hide);
  }, [visible, duration, opacity, translateY]);

  if (!visible) return null;

  return (
    <Animated.View
      pointerEvents="none"
      style={[
        toastStyles.wrap,
        {
          top,
          opacity,
          transform: [{ translateY }],
        },
      ]}
    >
      <View
        style={[
          toastStyles.card,
          shadow.md,
          {
            backgroundColor: isDark ? '#1A2030' : '#FFFFFF',
            borderColor: isDark ? 'rgba(52,211,153,0.28)' : 'rgba(15,169,104,0.22)',
            borderRadius: radius.lg,
          },
        ]}
      >
        <LinearGradient colors={gradients.brand} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={toastStyles.icon}>
          <Ionicons name="checkmark" size={18} color="#FFFFFF" />
        </LinearGradient>
        <View style={{ flex: 1 }}>
          <Text style={[toastStyles.title, { color: colors.text }]}>{title}</Text>
          {subtitle ? <Text style={[toastStyles.sub, { color: colors.textSub }]}>{subtitle}</Text> : null}
        </View>
      </View>
    </Animated.View>
  );
}

const toastStyles = StyleSheet.create({
  wrap: {
    position: 'absolute',
    left: 20,
    right: 20,
    zIndex: 50,
  },
  card: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 14,
    paddingHorizontal: 14,
    borderWidth: 1,
  },
  icon: {
    width: 36,
    height: 36,
    borderRadius: 18,
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 12,
  },
  title: { fontSize: 15, fontWeight: '800', letterSpacing: -0.2 },
  sub: { fontSize: 12, fontWeight: '500', marginTop: 2, lineHeight: 16 },
});

const styles = StyleSheet.create({
  btnLabel: { fontSize: 15, fontWeight: '800', letterSpacing: 0.2 } as TextStyle,
  sectionHeader: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginBottom: 14 },
  sectionTitle: { fontWeight: '800', letterSpacing: -0.4 },
  sectionAction: { fontSize: 13, fontWeight: '700' },
  ratingPill: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: 'rgba(0,0,0,0.55)',
    paddingVertical: 3,
    paddingHorizontal: 8,
    borderRadius: 999,
    alignSelf: 'flex-start',
  },
  ratingText: { color: '#FFFFFF', fontSize: 11, fontWeight: '800', marginLeft: 3 },
  emptyWrap: { alignItems: 'center', justifyContent: 'center', paddingHorizontal: 36, paddingTop: 70 },
  emptyIconWrap: { width: 76, height: 76, borderRadius: 38, alignItems: 'center', justifyContent: 'center', marginBottom: 18 },
  emptyTitle: { fontSize: 17, fontWeight: '800', textAlign: 'center' },
  emptySub: { fontSize: 14, textAlign: 'center', marginTop: 8, lineHeight: 20 },
  screenTitleRow: { flexDirection: 'row', alignItems: 'center' },
  screenTitle: { fontWeight: '800', letterSpacing: -0.6 },
  screenSub: { fontSize: 13, marginTop: 3, fontWeight: '500' },
});
