export type Voucher = {
  code: string;
  label: string;
  description: string;
  minSpend: number;
  discountPercent?: number;
  discountAmount?: number;
  expires: string;
};

export const VOUCHERS: Voucher[] = [
  { code: 'CEBU6', label: '6% OFF', description: 'Sitewide on Cebu tours', minSpend: 8000, discountPercent: 6, expires: 'Valid until Dec 31' },
  { code: 'ISLAND300', label: '₱300 OFF', description: 'Island hopping packages', minSpend: 3000, discountAmount: 300, expires: 'Valid until Sep 30' },
  { code: 'STAY10', label: '10% OFF', description: 'Hotels & resorts', minSpend: 5000, discountPercent: 10, expires: 'Valid until Nov 15' },
];

export function normalizeVoucherCode(code: string): string {
  return code.trim().toUpperCase().replace(/\s+/g, '');
}

export function findVoucher(code: string): Voucher | null {
  const normalized = normalizeVoucherCode(code);
  if (!normalized) {
    return null;
  }
  return VOUCHERS.find((voucher) => voucher.code === normalized) ?? null;
}

export function getVoucherDiscount(voucher: Voucher, subtotal: number): number {
  if (subtotal < voucher.minSpend) {
    return 0;
  }

  if (voucher.discountPercent !== undefined) {
    return Math.min(subtotal, Math.round(subtotal * (voucher.discountPercent / 100) * 100) / 100);
  }

  if (voucher.discountAmount !== undefined) {
    return Math.min(subtotal, voucher.discountAmount);
  }

  return 0;
}

export function formatVoucherMinimum(minSpend: number): string {
  return `Min. spend ₱${minSpend.toLocaleString('en-PH')}`;
}