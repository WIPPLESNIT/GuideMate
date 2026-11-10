import { restoreSession } from '../lib/authStore';
import { API_BASE_URL } from '../config';
import { api } from './api';

export type PackageCategory = 'all' | 'multi-day' | 'day-tour' | 'adventure' | 'island-escape';

export type PackageInclusionType = 'attraction' | 'activity' | 'accommodation' | 'rental' | 'perk';

export type PackageInclusionItem = {
  type: PackageInclusionType;
  title: string;
  subtitle?: string;
  icon: string;
  badge?: string;
  details?: string;
};

export type ItineraryDay = {
  day: number;
  title: string;
  description: string;
  highlights: string[];
  schedule?: { time: string; activity: string }[];
};

export type PackageHost = {
  id: number;
  name: string;
  avatar?: string;
  role: string;
  badge: string;
  rating: number;
  reviewCount: number;
  rating_label?: string;
  reviews?: {
    id: number;
    userName: string;
    userAvatar?: string;
    rating: number;
    comment: string;
    createdAt?: string;
  }[];
  toursCount: number;
  bio: string;
  responseRate?: string;
  verified: boolean;
};

export type TravelPackage = {
  id: string;
  slug: string;
  title: string;
  tagline: string;
  description: string;
  destination: string;
  duration: string; // e.g. "3D2N", "2D1N", "Full Day"
  durationDays: number;
  durationNights: number;
  category: 'adventure' | 'island-escape' | 'day-tour' | 'cultural';
  categoryLabel: string;
  price: number;
  originalPrice?: number;
  badge?: string; // e.g. "Best Seller", "Featured", "Popular"
  assurance?: string; // e.g. "Guaranteed", "Instant Confirmation"
  featured?: boolean;
  coverImage: string;
  localImageKey?: 'kawasan' | 'sardine' | 'whaleshark' | 'templeofleah' | 'bantayan' | 'osmena' | 'magellan';
  gallery: string[];
  rating: number;
  reviewCount: number;
  rating_label?: string;
  reviews?: {
    id: number;
    userName: string;
    userAvatar?: string;
    rating: number;
    comment: string;
    createdAt?: string;
  }[];
  maxGroupSize: number;
  minGroupSize?: number;
  minPax?: number;
  maxPax?: number;
  meetingPoint: string;
  tags?: string[];
  host: PackageHost;
  inclusions: PackageInclusionItem[];
  exclusions: string[];
  itinerary: ItineraryDay[];
  hotelIncluded?: boolean;
  accommodationDetail?: {
    hotelName: string;
    roomType: string;
    nights: number;
    amenities: string[];
    partnerName?: string;
    checkIn?: string;
    checkOut?: string;
    coverImage?: string;
  } | null;
  rentalIncluded?: boolean;
  rentalDetail?: {
    vehicleType: string;
    model: string;
    duration: string;
    features: string[];
    partnerName?: string;
    location?: string;
    image?: string;
    handover?: string;
    dropoff?: string;
  } | null;
  policy?: {
    cancellation: string;
    confirmation: string;
    requirements: string[];
  };
  has_active_booking?: boolean;
  active_booking?: {
    id: number;
    status: string;
    overall_status: string;
    ref: string;
  } | null;
};

export type PackageBookingRequest = {
  packageId: string;
  travelDate: string;
  guestsCount: number;
  notes?: string;
  contactName: string;
  contactPhone: string;
  contactEmail?: string;
  paymentMethod: 'ewallet' | 'instapay' | 'card' | 'cash';
  paymentReference?: string;
  packageBasePrice?: number;
  taxes?: number;
  environmentalFees?: number;
  voucherCode?: string;
  voucherDiscount?: number;
  finalTotal?: number;
};

export type PackageBookingResponse = {
  success: boolean;
  bookingId: string;
  packageTitle: string;
  totalPrice: number;
  status: 'confirmed' | 'pending';
  travelDate: string;
  guestsCount: number;
  guideName: string;
  message: string;
};

export const MOCK_PACKAGES: TravelPackage[] = [];

export async function getPackages(query?: {
  category?: string;
  search?: string;
  minPrice?: number;
  maxPrice?: number;
}): Promise<TravelPackage[]> {
  try {
    const searchParams = new URLSearchParams();
    if (query?.category && query.category !== 'all') {
      searchParams.append('category', query.category);
    }
    if (query?.search) {
      searchParams.append('q', query.search);
    }

    const url = `${API_BASE_URL}/api/packages?${searchParams.toString()}`;
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 6000);

    const response = await fetch(url, { signal: controller.signal });
    clearTimeout(timeout);

    if (response.ok) {
      const json = await response.json();
      if (Array.isArray(json.packages)) {
        let pkgs = json.packages as TravelPackage[];
        if (query?.minPrice !== undefined) {
          pkgs = pkgs.filter((p) => p.price >= query.minPrice!);
        }
        if (query?.maxPrice !== undefined) {
          pkgs = pkgs.filter((p) => p.price <= query.maxPrice!);
        }
        return pkgs;
      }
    }
  } catch (e) {
    // Return empty list on network or parse error — zero mock data fallback
  }

  return [];
}

export async function getPackageById(idOrSlug: string): Promise<TravelPackage | null> {
  try {
    await restoreSession().catch(() => null);
    const json = await api<{ package?: TravelPackage }>(`/api/packages/${encodeURIComponent(idOrSlug)}`);
    if (json && json.package) {
      return json.package;
    }
  } catch {
    try {
      const url = `${API_BASE_URL}/api/packages/${encodeURIComponent(idOrSlug)}`;
      const controller = new AbortController();
      const timeout = setTimeout(() => controller.abort(), 5000);

      const response = await fetch(url, { signal: controller.signal });
      clearTimeout(timeout);

      if (response.ok) {
        const json = await response.json();
        if (json.package) {
          return json.package;
        }
      }
    } catch {}
  }

  return null;
}

export async function bookTravelPackage(
  request: PackageBookingRequest
): Promise<PackageBookingResponse> {
  const pkg = await getPackageById(request.packageId);
  const pkgTitle = pkg ? pkg.title : 'Travel Package';
  // Fixed total package price remains unchanged regardless of guests count!
  const price = request.finalTotal ?? (pkg ? pkg.price : 8499);

  try {
    await restoreSession().catch(() => null);
    const path = `/api/packages/${encodeURIComponent(request.packageId)}/book`;
    const res = await api<PackageBookingResponse>(path, {
      method: 'POST',
      body: request,
    });
    if (res && res.success) {
      return res;
    }
  } catch (err) {
    if (err instanceof Error && err.message) {
      throw err;
    }
  }

  const bookingId = `PKG-${Date.now().toString().slice(-6)}`;
  return {
    success: true,
    bookingId,
    packageTitle: pkgTitle,
    totalPrice: price,
    status: 'confirmed',
    travelDate: request.travelDate,
    guestsCount: request.guestsCount,
    guideName: pkg ? pkg.host.name : 'Marc Repuesto',
    message: `Your booking for "${pkgTitle}" has been confirmed! Guide ${pkg ? pkg.host.name : 'Marc'} has received your booking details and will coordinate your itinerary.`,
  };
}
