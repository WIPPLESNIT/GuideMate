import { Ionicons } from '@expo/vector-icons';
import React, { useMemo, useState } from 'react';
import { StyleSheet, Text, TouchableOpacity, View } from 'react-native';

// Lightweight month calendar with no native dependencies (works in Expo Go).
// Past dates and booked dates are disabled. Emits the selected date as a YYYY-MM-DD string.

type Theme = {
  textMain: string;
  textSub: string;
  accent: string;
  border: string;
  card: string;
};

type Props = {
  value?: string; // YYYY-MM-DD or ''
  onChange?: (date: string) => void;
  values?: string[]; // YYYY-MM-DD[] for multiSelect
  onMultiChange?: (dates: string[]) => void;
  multiSelect?: boolean;
  theme: Theme;
  disabledDates?: string[]; // YYYY-MM-DD dates that are fully booked / unavailable
  minDate?: string; // Optional minimum selectable date (YYYY-MM-DD)
  readOnly?: boolean; // When true, calendar displays selected date(s) without allowing clicks
};

const WEEKDAYS = ['S', 'M', 'T', 'W', 'T', 'F', 'S'];
const MONTHS = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December',
];

function pad(n: number): string {
  return n < 10 ? `0${n}` : String(n);
}

function toKey(year: number, month: number, day: number): string {
  return `${year}-${pad(month + 1)}-${pad(day)}`;
}

function parseYMD(str?: string): { year: number; month: number; day: number } | null {
  if (!str) return null;
  const parts = str.split('-');
  if (parts.length !== 3) return null;
  const year = parseInt(parts[0], 10);
  const month = parseInt(parts[1], 10) - 1;
  const day = parseInt(parts[2], 10);
  if (Number.isNaN(year) || Number.isNaN(month) || Number.isNaN(day)) return null;
  return { year, month, day };
}

export default function CalendarPicker({
  value = '',
  onChange,
  values = [],
  onMultiChange,
  multiSelect = false,
  theme,
  disabledDates = [],
  minDate,
  readOnly = false,
}: Props) {
  const today = new Date();
  today.setHours(0, 0, 0, 0);

  const blocked = useMemo(() => new Set(disabledDates), [disabledDates]);

  const initialDateStr = multiSelect ? (values[0] || value) : value;
  const initialParsed = parseYMD(initialDateStr);
  const [previousDate, setPreviousDate] = useState(initialDateStr);
  const [viewYear, setViewYear] = useState(initialParsed ? initialParsed.year : today.getFullYear());
  const [viewMonth, setViewMonth] = useState(initialParsed ? initialParsed.month : today.getMonth());

  if (initialDateStr !== previousDate) {
    setPreviousDate(initialDateStr);
    if (initialDateStr) {
      const target = parseYMD(initialDateStr);
      if (target) {
        setViewYear(target.year);
        setViewMonth(target.month);
      }
    }
  }

  const todayKey = toKey(today.getFullYear(), today.getMonth(), today.getDate());

  const selectedSet = useMemo(() => {
    if (multiSelect) {
      return new Set(values);
    }
    return new Set(value ? [value] : []);
  }, [multiSelect, values, value]);

  // Group days into explicit 7-column rows to prevent Saturday from wrapping to next line
  const rows = useMemo(() => {
    const firstDay = new Date(viewYear, viewMonth, 1).getDay();
    const daysInMonth = new Date(viewYear, viewMonth + 1, 0).getDate();
    const list: (number | null)[] = [];
    for (let i = 0; i < firstDay; i += 1) list.push(null);
    for (let d = 1; d <= daysInMonth; d += 1) list.push(d);

    const r: (number | null)[][] = [];
    for (let i = 0; i < list.length; i += 7) {
      const slice = list.slice(i, i + 7);
      while (slice.length < 7) {
        slice.push(null);
      }
      r.push(slice);
    }
    return r;
  }, [viewYear, viewMonth]);

  const goPrev = () => {
    if (viewMonth === 0) {
      setViewMonth(11);
      setViewYear((y) => y - 1);
    } else {
      setViewMonth((m) => m - 1);
    }
  };

  const goNext = () => {
    if (viewMonth === 11) {
      setViewMonth(0);
      setViewYear((y) => y + 1);
    } else {
      setViewMonth((m) => m + 1);
    }
  };

  const handlePress = (key: string) => {
    if (readOnly) return;
    if (multiSelect) {
      let updated: string[];
      if (selectedSet.has(key)) {
        updated = values.filter((d) => d !== key);
      } else {
        updated = [...values, key].sort();
      }
      onMultiChange?.(updated);
      onChange?.(updated[0] ?? '');
    } else {
      onChange?.(key);
    }
  };

  return (
    <View style={[styles.wrap, { borderColor: theme.border, backgroundColor: theme.card }]}>
      <View style={styles.header}>
        <TouchableOpacity onPress={goPrev} style={styles.navBtn} hitSlop={10} accessibilityRole="button" accessibilityLabel="Previous month">
          <Ionicons name="chevron-back" size={20} color={theme.textMain} />
        </TouchableOpacity>
        <Text style={[styles.monthTitle, { color: theme.textMain }]}>
          {MONTHS[viewMonth]} {viewYear}
        </Text>
        <TouchableOpacity onPress={goNext} style={styles.navBtn} hitSlop={10} accessibilityRole="button" accessibilityLabel="Next month">
          <Ionicons name="chevron-forward" size={20} color={theme.textMain} />
        </TouchableOpacity>
      </View>

      <View style={styles.weekRow}>
        {WEEKDAYS.map((w, i) => (
          <Text key={`${w}-${i}`} style={[styles.weekday, { color: theme.textSub }]}>
            {w}
          </Text>
        ))}
      </View>

      <View style={styles.grid}>
        {rows.map((row, rIdx) => (
          <View key={`row-${rIdx}`} style={styles.gridRow}>
            {row.map((day, cIdx) => {
              if (day === null) {
                return <View key={`empty-${rIdx}-${cIdx}`} style={styles.cell} />;
              }
              const key = toKey(viewYear, viewMonth, day);
              const isPast = minDate ? key < minDate : key < todayKey;
              const isFull = blocked.has(key);
              const isDisabled = isPast || isFull;
              const isSelected = selectedSet.has(key);
              const isToday = key === todayKey;
              return (
                <TouchableOpacity
                  key={key}
                  style={styles.cell}
                  disabled={readOnly || isDisabled}
                  activeOpacity={0.7}
                  onPress={() => handlePress(key)}
                  accessibilityRole="button"
                  accessibilityLabel={`${MONTHS[viewMonth]} ${day}, ${viewYear}${isFull ? ' (Booked / Unavailable)' : isPast ? ' (Past)' : ''}`}
                >
                  <View
                    style={[
                      styles.dayInner,
                      isFull && styles.dayInnerFull,
                      isSelected && { backgroundColor: theme.accent },
                      isToday && !isSelected && !isFull && { borderWidth: 1, borderColor: theme.accent },
                    ]}
                  >
                    <Text
                      style={[
                        styles.dayText,
                        { color: isDisabled ? (isFull ? '#94A3B8' : theme.textSub) : theme.textMain },
                        isPast && { opacity: 0.35 },
                        isFull && styles.dayTextFull,
                        isSelected && { color: '#FFFFFF', fontWeight: '800' },
                      ]}
                    >
                      {day}
                    </Text>
                  </View>
                </TouchableOpacity>
              );
            })}
          </View>
        ))}
      </View>

      {multiSelect && values.length > 0 ? (
        <View style={styles.multiFooter}>
          <Text style={[styles.multiSummary, { color: theme.textSub }]}>
            {values.length} {values.length === 1 ? 'date' : 'dates'} selected
          </Text>
          <TouchableOpacity onPress={() => onMultiChange?.([])} hitSlop={8}>
            <Text style={[styles.clearBtn, { color: theme.accent }]}>Clear all</Text>
          </TouchableOpacity>
        </View>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { borderRadius: 16, borderWidth: 1, padding: 12 },
  header: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginBottom: 10 },
  navBtn: { width: 36, height: 36, alignItems: 'center', justifyContent: 'center' },
  monthTitle: { fontSize: 15, fontWeight: '800' },
  weekRow: { flexDirection: 'row', marginBottom: 4 },
  weekday: { flex: 1, textAlign: 'center', fontSize: 12, fontWeight: '700' },
  grid: { width: '100%' },
  gridRow: { flexDirection: 'row', width: '100%' },
  cell: { flex: 1, aspectRatio: 1, alignItems: 'center', justifyContent: 'center' },
  dayInner: { width: 34, height: 34, borderRadius: 17, alignItems: 'center', justifyContent: 'center' },
  dayInnerFull: {
    backgroundColor: 'rgba(148, 163, 184, 0.16)',
  },
  dayText: { fontSize: 14, fontWeight: '600' },
  dayTextFull: {
    color: '#94A3B8',
    fontWeight: '500',
  },
  multiFooter: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginTop: 10,
    paddingTop: 8,
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: '#00000015',
  },
  multiSummary: { fontSize: 12, fontWeight: '600' },
  clearBtn: { fontSize: 12, fontWeight: '700' },
});
