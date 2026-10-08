import { useColorScheme } from 'react-native';

/**
 * GuideMate design system.
 *
 * A single source of truth for colors, spacing, radius, typography and shadows
 * so every screen looks cohesive. The brand is a tropical emerald→teal that
 * evokes Cebu's islands, with a warm coral accent for prices and deals.
 */

// ── Raw brand palette ────────────────────────────────────────────────────────
export const brand = {
  primary: '#0FA968',
  primaryDark: '#0B8A54',
  primaryLight: '#34D399',
  teal: '#0EA5A5',
  accent: '#FF6A3D', // warm coral — used for prices, deals, sale
  accentDark: '#E8512A',
  gold: '#FBBF24', // rating stars
  info: '#3B82F6',
  danger: '#EF4444',
  success: '#22C55E',
};

export const gradients = {
  brand: ['#13C56B', '#0AA17E'] as const, // green → teal
  sunset: ['#FF8A4C', '#FF5A1F'] as const,
  ocean: ['#0EA5A5', '#0F766E'] as const,
  hero: ['rgba(0,0,0,0)', 'rgba(0,0,0,0.75)'] as const, // image overlay
};

// ── Light / dark color tokens ────────────────────────────────────────────────
const light = {
  bg: '#FFFFFF',
  bgAlt: '#F4F6F8',
  surface: '#FFFFFF',
  card: '#FFFFFF',
  cardAlt: '#F6F8FA',
  border: '#EBEEF2',
  text: '#0F172A',
  textSub: '#5B6573',
  textMute: '#94A3B8',
  primary: brand.primary,
  primaryDark: brand.primaryDark,
  accent: brand.accent,
  gold: brand.gold,
  danger: brand.danger,
  info: brand.info,
  chipBg: '#F1F4F7',
  overlay: 'rgba(0,0,0,0.45)',
  skeleton: '#E9EDF1',
};

const dark = {
  bg: '#0B0D12',
  bgAlt: '#0F1219',
  surface: '#14171F',
  card: '#171B24',
  cardAlt: '#1E2330',
  border: '#262C39',
  text: '#F8FAFC',
  textSub: '#A6B0BF',
  textMute: '#6B7687',
  primary: brand.primaryLight,
  primaryDark: brand.primary,
  accent: brand.accent,
  gold: brand.gold,
  danger: brand.danger,
  info: brand.info,
  chipBg: '#1E2330',
  overlay: 'rgba(0,0,0,0.55)',
  skeleton: '#1E2330',
};

export type ThemeColors = typeof light;

// ── Scales ───────────────────────────────────────────────────────────────────
export const radius = { xs: 8, sm: 12, md: 16, lg: 22, xl: 28, pill: 999 };

export const spacing = { xs: 4, sm: 8, md: 12, lg: 16, xl: 20, xxl: 28, xxxl: 40 };

export const fontSize = {
  xs: 11,
  sm: 13,
  md: 15,
  lg: 18,
  xl: 22,
  xxl: 28,
  display: 34,
};

// ── Shadows ──────────────────────────────────────────────────────────────────
export const shadow = {
  sm: {
    shadowColor: '#0F172A',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.06,
    shadowRadius: 6,
    elevation: 2,
  },
  md: {
    shadowColor: '#0F172A',
    shadowOffset: { width: 0, height: 6 },
    shadowOpacity: 0.1,
    shadowRadius: 14,
    elevation: 5,
  },
  lg: {
    shadowColor: '#0F172A',
    shadowOffset: { width: 0, height: 12 },
    shadowOpacity: 0.16,
    shadowRadius: 24,
    elevation: 10,
  },
};

export type Theme = {
  colors: ThemeColors;
  isDark: boolean;
  radius: typeof radius;
  spacing: typeof spacing;
  fontSize: typeof fontSize;
  shadow: typeof shadow;
  gradients: typeof gradients;
  brand: typeof brand;
};

/** Active theme based on the system color scheme. */
export function useTheme(): Theme {
  const scheme = useColorScheme();
  const isDark = scheme === 'dark';
  return {
    colors: isDark ? dark : light,
    isDark,
    radius,
    spacing,
    fontSize,
    shadow,
    gradients,
    brand,
  };
}
