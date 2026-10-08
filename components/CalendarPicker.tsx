import { Ionicons } from '@expo/vector-icons';
import React, { useMemo, useState } from 'react';
import { StyleSheet, Text, TouchableOpacity, View } from 'react-native';

// Lightweight month calendar with no native dependencies (works in Expo Go).
// Past dates are disabled. Emits the selected date as a YYYY-MM-DD string.

type Theme = {
  textMain: string;
  textSub: string;
  accent: string;
  border: string;
  card: string;
};

type Props = {
  value: string; // YYYY-MM-DD or ''
  onChange: (date: string) => void;
  theme: Theme;
  disabledDates?: string[]; // YYYY-MM-DD dates that are fully booked
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

export default function CalendarPicker({ value, onChange, theme, disabledDates = [] }: Props) {
  const today = new Date();
  today.setHours(0, 0, 0, 0);

  const blocked = useMemo(() => new Set(disabledDates), [disabledDates]);

  const initial = value ? new Date(value) : today;
  const [viewYear, setViewYear] = useState(initial.getFullYear());
  const [viewMonth, setViewMonth] = useState(initial.getMonth());

  const todayKey = toKey(today.getFullYear(), today.getMonth(), today.getDate());

  const cells = useMemo(() => {
    const firstDay = new Date(viewYear, viewMonth, 1).getDay();
    const daysInMonth = new Date(viewYear, viewMonth + 1, 0).getDate();
    const list: (number | null)[] = [];
    for (let i = 0; i < firstDay; i += 1) list.push(null);
    for (let d = 1; d <= daysInMonth; d += 1) list.push(d);
    return list;
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

  return (
    <View style={[styles.wrap, { borderColor: theme.border, backgroundColor: theme.card }]}>
      <View style={styles.header}>
        <TouchableOpacity onPress={goPrev} style={styles.navBtn} hitSlop={10}>
          <Ionicons name="chevron-back" size={20} color={theme.textMain} />
        </TouchableOpacity>
        <Text style={[styles.monthTitle, { color: theme.textMain }]}>
          {MONTHS[viewMonth]} {viewYear}
        </Text>
        <TouchableOpacity onPress={goNext} style={styles.navBtn} hitSlop={10}>
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
        {cells.map((day, idx) => {
          if (day === null) {
            return <View key={`empty-${idx}`} style={styles.cell} />;
          }
          const key = toKey(viewYear, viewMonth, day);
          const isPast = key < todayKey;
          const isFull = blocked.has(key);
          const isDisabled = isPast || isFull;
          const isSelected = key === value;
          const isToday = key === todayKey;
          return (
            <TouchableOpacity
              key={key}
              style={styles.cell}
              disabled={isDisabled}
              activeOpacity={0.7}
              onPress={() => onChange(key)}
            >
              <View
                style={[
                  styles.dayInner,
                  isSelected && { backgroundColor: theme.accent },
                  isToday && !isSelected && { borderWidth: 1, borderColor: theme.accent },
                ]}
              >
                <Text
                  style={[
                    styles.dayText,
                    { color: isDisabled ? theme.textSub : theme.textMain },
                    isPast && { opacity: 0.35 },
                    isFull && { opacity: 0.4, textDecorationLine: 'line-through' },
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
  grid: { flexDirection: 'row', flexWrap: 'wrap' },
  cell: { width: `${100 / 7}%`, aspectRatio: 1, alignItems: 'center', justifyContent: 'center' },
  dayInner: { width: 34, height: 34, borderRadius: 17, alignItems: 'center', justifyContent: 'center' },
  dayText: { fontSize: 14, fontWeight: '600' },
});
