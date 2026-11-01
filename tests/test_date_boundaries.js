function getNextCalendarDay(dateStr) {
  const parts = dateStr.split('-');
  if (parts.length === 3) {
    const y = parseInt(parts[0], 10);
    const m = parseInt(parts[1], 10) - 1;
    const d = parseInt(parts[2], 10);
    const utcDate = new Date(Date.UTC(y, m, d + 1));
    const yyyy = utcDate.getUTCFullYear();
    const mm = String(utcDate.getUTCMonth() + 1).padStart(2, '0');
    const dd = String(utcDate.getUTCDate()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd}`;
  }
  return dateStr;
}

const tests = [
  ['2026-11-01', '2026-11-02'],
  ['2026-12-15', '2026-12-16'],
  ['2026-01-31', '2026-02-01'],
  ['2026-02-28', '2026-03-01'],
  ['2028-02-28', '2028-02-29'],
  ['2028-02-29', '2028-03-01'],
  ['2026-03-31', '2026-04-01'],
  ['2026-04-30', '2026-05-01'],
  ['2026-12-31', '2027-01-01'],
];

let allPassed = true;
for (const [input, expected] of tests) {
  const actual = getNextCalendarDay(input);
  const ok = actual === expected;
  console.log(`${input} -> ${actual} [${ok ? 'PASS' : 'FAIL'}]`);
  if (!ok) allPassed = false;
}

if (!allPassed) process.exit(1);
console.log('All date boundary tests passed!');
