import * as FileSystem from 'expo-file-system/legacy';
import { API_BASE_URL } from '../config';

// Fail fast instead of hanging forever when the backend is down/unreachable.
const REQUEST_TIMEOUT_MS = 12000;

// ── Types matching the PHP API payloads (app/Controllers/ApiController.php) ──

export type ApiUser = {
  id: number;
  name: string;
  email: string;
  role: string;
  avatar: string;
  bio: string;
};

export type ChatStatus = 'sent' | 'delivered' | 'read';

export type ApiMessage = {
  id: number;
  body: string;
  mine: boolean;
  created_at: string;
  status: ChatStatus;
  // Present when the message was auto-translated into the reader's language.
  translated_body?: string;
  source_lang?: string | null;
  translated?: boolean;
};

export type ApiConversation = {
  partner_id: number;
  partner_name: string;
  partner_avatar: string;
  last_body: string;
  last_at: string;
  unread: number;
  is_pinned: boolean;
};

export type ApiChatPartner = {
  id: number;
  name: string;
  avatar: string;
  bio: string;
  online: boolean;
  // Populated by the guides directory endpoint (optional elsewhere).
  rating?: number;
  review_count?: number;
  completed_tours?: number;
  available?: boolean;
};

export type ApiCategory = {
  id: number;
  name: string;
  slug: string;
  icon: string;
  listing_count: number;
};

export type ApiListing = {
  id: number;
  title: string;
  slug: string;
  summary: string;
  area: string;
  price: number;
  price_unit: string;
  currency: string;
  rating: number;
  review_count: number;
  category: string;
  category_slug: string;
  image: string;
  featured: boolean;
  duration: string;
  owner_name: string;
  owner_role?: string;
  description?: string;
  included?: string;
  not_included?: string;
  owner_id?: number;
  favorited?: boolean;
  is_new?: boolean;
  nearest_hotel?: NearestHotel | null;
};

export type NearestHotel = {
  id: number;
  title: string;
  slug: string;
  area: string;
  address: string;
  image: string;
  price: number;
  price_unit: string;
  distance_km: number;
  latitude: number;
  longitude: number;
  directions_url: string;
};

export type ApiArea = { slug: string; name: string; tagline: string };

export type ApiBooking = {
  id: number;
  listing_id: number;
  listing_title: string;
  listing_slug: string;
  image: string;
  booking_date: string;
  booking_time: string;
  guests: number;
  total_amount: number;
  status: string;
  status_label?: string;
  area: string;
  reviewed?: boolean;
  can_review?: boolean;
  can_report?: boolean;
  dispute_status?: string;
};

// Problem types for reporting a guide to the admin (mirrors Dispute::TYPES).
export const DISPUTE_TYPES: { value: string; label: string }[] = [
  { value: 'extra_payment', label: 'Guide asked for extra payment' },
  { value: 'no_show', label: 'Guide did not show up' },
  { value: 'service_mismatch', label: 'Service was very different from listing' },
  { value: 'unsafe', label: 'Unsafe or unprofessional behavior' },
  { value: 'other', label: 'Other problem' },
];

export type ApiDispute = {
  id: number;
  booking_id: number;
  problem_type: string;
  problem_label: string;
  amount_requested: number | null;
  description: string;
  status: string;
  status_label: string;
  admin_note: string;
  created_at: string;
};

export type HomePayload = {
  categories: ApiCategory[];
  featured: ApiListing[];
  recommended: ApiListing[];
  areas: ApiArea[];
  // Count of published (approved) places; used to detect newly added tours.
  listings_total?: number;
};

// Origin (scheme + host) of the configured API, e.g. "http://10.0.4.99".
const API_ORIGIN = API_BASE_URL.replace(/^(https?:\/\/[^/]+).*$/, '$1');

// The backend may return image URLs pointing at "localhost" (its own machine).
// A phone can't reach that, so rewrite those to the API address instead.
// External URLs (e.g. picsum placeholders) are left untouched.
export function resolveImage(url?: string | null): string {
  if (!url) {
    return '';
  }
  return url.replace(/^https?:\/\/(localhost|127\.0\.0\.1)(:\d+)?/i, API_ORIGIN);
}

// ── Token handling ──

let authToken: string | null = null;

export function setAuthToken(token: string | null) {
  authToken = token;
}

// ── Core request helper ──

type Options = {
  method?: string;
  body?: unknown;
  headers?: Record<string, string>;
};

export async function api<T = any>(path: string, options: Options = {}): Promise<T> {
  const headers: Record<string, string> = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    ...(options.headers ?? {}),
  };
  if (authToken) {
    headers.Authorization = `Bearer ${authToken}`;
  }

  let response: Response;
  // Abort the request if the server doesn't answer in time so the UI never
  // hangs on an endless spinner when XAMPP is down or unreachable.
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), REQUEST_TIMEOUT_MS);
  try {
    response = await fetch(`${API_BASE_URL}${path}`, {
      method: options.method ?? 'GET',
      headers,
      body: options.body ? JSON.stringify(options.body) : undefined,
      signal: controller.signal,
    });
  } catch (e) {
    if (e instanceof Error && e.name === 'AbortError') {
      throw new Error('The server took too long to respond. Make sure XAMPP/Apache is running and your phone is on the same Wi-Fi.');
    }
    throw new Error('Cannot reach the server. Check that XAMPP is running and the API address is correct.');
  } finally {
    clearTimeout(timeout);
  }

  const data = await response.json().catch(() => ({}));
  if (!response.ok) {
    const message = (data && (data.error || data.message)) || 'Request failed. Please try again.';
    const err = new Error(message) as Error & { code?: string; status?: number };
    err.status = response.status;
    // 401/403 means the saved token is missing/expired — callers can treat this
    // as "logged out" and prompt the user to sign in again.
    if (response.status === 401 || response.status === 403) {
      err.code = 'UNAUTHORIZED';
    }
    throw err;
  }
  return data as T;
}

// True when an error came from a 401/403 response (expired or missing session).
export function isUnauthorized(e: unknown): boolean {
  return e instanceof Error && (e as { code?: string }).code === 'UNAUTHORIZED';
}

// ── Endpoints ──

export const getHome = () => api<HomePayload>('/api/home');

export const getListings = (params: Record<string, string> = {}) => {
  const query = new URLSearchParams(params).toString();
  return api<{ listings: ApiListing[] }>(`/api/listings${query ? `?${query}` : ''}`);
};

export const getListing = (slug: string) =>
  api<{
    listing: ApiListing;
    gallery: { id: number; url: string }[];
    reviews: { id: number; rating: number; comment: string; user_name: string; created_at: string }[];
    summary: { average: number; total: number } | any;
    favorited: boolean;
    already_booked: boolean;
    has_reviewed: boolean;
    can_review: boolean;
    booked_slots: { date: string; time: string }[];
  }>(`/api/listings/${slug}`);

export const apiLogin = (email: string, password: string) =>
  api<{ token: string; user: ApiUser }>('/api/auth/login', { method: 'POST', body: { email, password } });

export const apiRegister = (name: string, email: string, password: string) =>
  api<{ token: string; user: ApiUser }>('/api/auth/register', {
    method: 'POST',
    body: { name, email, password },
  });

// Sign in with Google/Facebook. In demo mode we send an anonymous per-device
// `subject`; in live mode we send the verified provider `token`.
export const apiSocialLogin = (payload: {
  provider: 'google' | 'facebook';
  subject?: string;
  token?: string;
}) => api<{ token: string; user: ApiUser }>('/api/auth/social', { method: 'POST', body: payload });

export const apiForgotPassword = (email: string) =>
  api<{ message: string; dev_reset_url?: string }>('/api/auth/forgot-password', {
    method: 'POST',
    body: { email },
  });

export const apiMe = () => api<{ user: ApiUser }>('/api/auth/me');

export const apiLogout = () => api<{ success: boolean }>('/api/auth/logout', { method: 'POST' });

export const apiChangePassword = (currentPassword: string, newPassword: string) =>
  api<{ success: boolean }>('/api/auth/change-password', {
    method: 'POST',
    body: { current_password: currentPassword, new_password: newPassword },
  });

export const apiDeleteAccount = (password: string) =>
  api<{ success: boolean }>('/api/auth/delete-account', { method: 'POST', body: { password } });

export const getBookings = () => api<{ bookings: ApiBooking[] }>('/api/bookings');

export type TripPin = {
  id: number;
  title: string;
  area: string;
  address: string;
  googleQuery: string;
  date: string;
  dateLabel: string;
  lat: number;
  lng: number;
  upcoming: boolean;
  approximate: boolean;
};

export const getTripMap = () =>
  api<{ bookings: TripPin[]; mapbox_token: string; mapillary_token: string }>('/api/trip-map');

export const getMapsConfig = () => api<{ mapbox_token: string }>('/api/maps');

export type ApiRentalVehicle = {
  id: string;
  name: string;
  type: string;
  price_per_day: number;
  specs: string[];
  shop: string;
  location: string;
  rating: number;
  image: string;
};

export const getRentalVehicles = () => api<{ vehicles: ApiRentalVehicle[] }>('/api/rentals');

export type PaymentMethod = 'gcash' | 'instapay' | 'card';

export type ApiRental = {
  id: number;
  vehicle_name: string;
  vehicle_type: string;
  shop_name: string;
  location: string;
  pickup_date: string;
  rental_days: number;
  total_amount: number;
  status: string;
  status_label: string;
  payment_status: 'unpaid' | 'paid' | 'refunded';
  payment_method: string;
  notes: string;
  report_status: 'none' | 'open' | 'refunded' | 'rejected';
  report_type: string;
  report_label: string;
  report_message: string;
  owner_report_note: string;
  can_report: boolean;
  created_at: string;
};

// Problem types a tourist can report against a reserved unit.
export const RENTAL_REPORT_TYPES: { value: string; label: string }[] = [
  { value: 'vehicle_defect', label: 'Vehicle problem / breakdown' },
  { value: 'not_delivered', label: 'Unit was not delivered' },
  { value: 'not_as_described', label: 'Not as described / wrong unit' },
  { value: 'overcharged', label: 'Overcharged / extra fees' },
  { value: 'safety', label: 'Unsafe or unfit to drive' },
  { value: 'other', label: 'Other problem' },
];

/**
 * Reserve a vehicle with FULL up-front payment. Sent as multipart/form-data so
 * the tourist can attach a photo of the valid ID that the owner will hold.
 */
export function createRentalRequest(payload: {
  vehicle_id: string;
  vehicle_name: string;
  vehicle_type: string;
  shop_name: string;
  location?: string;
  pickup_date: string;
  rental_days: number;
  price_per_day: number;
  customer_phone: string;
  notes?: string;
  id_type: string;
  id_number?: string;
  id_document_uri: string;
  payment_method: PaymentMethod;
  payment_reference?: string;
  card_last4?: string;
}): Promise<{ request: ApiRental }> {
  const parameters: Record<string, string> = {
    vehicle_id: payload.vehicle_id,
    vehicle_name: payload.vehicle_name,
    vehicle_type: payload.vehicle_type,
    shop_name: payload.shop_name,
    pickup_date: payload.pickup_date,
    rental_days: String(payload.rental_days),
    price_per_day: String(payload.price_per_day),
    customer_phone: payload.customer_phone,
    id_type: payload.id_type,
    payment_method: payload.payment_method,
  };
  if (payload.location) parameters.location = payload.location;
  if (payload.notes) parameters.notes = payload.notes;
  if (payload.id_number) parameters.id_number = payload.id_number;
  if (payload.payment_reference) parameters.payment_reference = payload.payment_reference;
  if (payload.card_last4) parameters.card_last4 = payload.card_last4;

  return uploadFileMultipart<{ request: ApiRental }>(
    '/api/rentals',
    payload.id_document_uri,
    'id_document',
    parameters,
    {
      fallbackName: `id_${Date.now()}.jpg`,
      connectionError: 'Could not complete your reservation. Check your connection and try again.',
    }
  );
}

export const getMyRentals = () => api<{ rentals: ApiRental[] }>('/api/rentals/mine');

export const reportRental = (payload: {
  rental_id: number;
  problem_type: string;
  description: string;
}) => api<{ request: ApiRental | null }>('/api/rentals/report', { method: 'POST', body: payload });

export const createBooking = (payload: {
  listing_id: number;
  booking_date: string;
  booking_time?: string;
  guests: number;
  notes?: string;
  promo_code?: string;
  payment_method?: PaymentMethod;
  payment_reference?: string;
  card_last4?: string;
}) => api<{ booking: ApiBooking | null }>('/api/bookings', { method: 'POST', body: payload });

export type ApiVoucher = {
  code: string;
  label: string;
  description: string;
  min_spend: number;
  discount_percent: number | null;
  discount_amount: number | null;
  expires: string;
  used: boolean;
};

export const getVouchers = () => api<{ vouchers: ApiVoucher[] }>('/api/vouchers');

export const validateVoucher = (code: string, subtotal: number) =>
  api<{ valid: boolean; discount: number; id: number | null; message: string }>('/api/vouchers/validate', {
    method: 'POST',
    body: { code, subtotal },
  });

export type FeedbackCategory = 'general' | 'bug' | 'feature' | 'praise';
export type FeedbackStatus = 'new' | 'reviewed' | 'archived';

export type MyFeedback = {
  id: number;
  rating: number;
  category: FeedbackCategory;
  message: string;
  status: FeedbackStatus;
  created_at: string;
};

export const submitFeedback = (payload: {
  message: string;
  rating?: number;
  category?: FeedbackCategory;
  name?: string;
  email?: string;
}) =>
  api<{ feedback: { id: number }; message: string }>('/api/feedback', {
    method: 'POST',
    body: payload,
  });

export const getMyFeedback = () => api<{ feedback: MyFeedback[] }>('/api/feedback');

export const getFavorites = () => api<{ listings: ApiListing[] }>('/api/favorites');

export const toggleFavorite = (listingId: number) =>
  api<{ favorited: boolean }>('/api/favorites/toggle', {
    method: 'POST',
    body: { listing_id: listingId },
  });

// ── Reviews ──

export const submitReview = (payload: {
  listing_id: number;
  rating: number;
  comment: string;
  title?: string;
}) =>
  api<{ success: boolean; summary: { avg: number; count: number } }>('/api/reviews', {
    method: 'POST',
    body: payload,
  });

// ── Problem reports (disputes) ──

export const getMyDispute = (bookingId: number) =>
  api<{ dispute: ApiDispute | null }>(`/api/disputes?booking_id=${bookingId}`);

// Report uses multipart/form-data so photo evidence can be attached.
export async function submitDispute(payload: {
  booking_id: number;
  problem_type: string;
  description: string;
  amount_requested?: string;
  photos?: string[];
}): Promise<{ success: boolean; dispute: ApiDispute | null }> {
  const form = new FormData();
  form.append('booking_id', String(payload.booking_id));
  form.append('problem_type', payload.problem_type);
  form.append('description', payload.description);
  if (payload.amount_requested) form.append('amount_requested', payload.amount_requested);

  (payload.photos ?? []).forEach((uri, i) => {
    const name = uri.split('/').pop() || `evidence_${Date.now()}_${i}.jpg`;
    const ext = (/\.(\w+)$/.exec(name)?.[1] ?? 'jpg').toLowerCase();
    const type = ext === 'png' ? 'image/png' : ext === 'webp' ? 'image/webp' : 'image/jpeg';
    // "evidence[]" so PHP receives an array when multiple photos are attached.
    form.append('evidence[]', { uri, name, type } as unknown as Blob);
  });

  const headers: Record<string, string> = { Accept: 'application/json' };
  if (authToken) headers.Authorization = `Bearer ${authToken}`;

  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), 30000);
  let response: Response;
  try {
    response = await fetch(`${API_BASE_URL}/api/disputes`, {
      method: 'POST',
      headers,
      body: form,
      signal: controller.signal,
    });
  } catch {
    throw new Error('Could not submit your report. Check your connection and try again.');
  } finally {
    clearTimeout(timeout);
  }

  const data = await response.json().catch(() => ({}));
  if (!response.ok) {
    const err = new Error((data && (data.error || data.message)) || 'Report failed.') as Error & { code?: string };
    if (response.status === 401 || response.status === 403) err.code = 'UNAUTHORIZED';
    throw err;
  }
  return data as { success: boolean; dispute: ApiDispute | null };
}

// ── Profile ──

export const apiUpdateProfile = (payload: { name?: string; bio?: string }) =>
  api<{ user: ApiUser }>('/api/profile', { method: 'POST', body: payload });

// Guess a MIME type from a local file URI's extension.
function mimeFromUri(uri: string, fallbackName: string): string {
  const name = uri.split('/').pop() || fallbackName;
  const ext = (/\.(\w+)$/.exec(name)?.[1] ?? 'jpg').toLowerCase();
  if (ext === 'png') return 'image/png';
  if (ext === 'webp') return 'image/webp';
  if (ext === 'pdf') return 'application/pdf';
  return 'image/jpeg';
}

/**
 * Reliable multipart upload of a single local file (plus optional text fields)
 * using Expo FileSystem's NATIVE uploader.
 *
 * We use this instead of `fetch` + `FormData` because sending a file through
 * the JS `fetch`/`FormData` path is unreliable on React Native's New
 * Architecture (it was failing every photo upload). `uploadAsync` performs the
 * request with native networking (OkHttp / NSURLSession), which handles the
 * `file://` URI and multipart boundary correctly.
 */
async function uploadFileMultipart<T = any>(
  path: string,
  fileUri: string,
  fieldName: string,
  parameters: Record<string, string> = {},
  opts: { fallbackName?: string; connectionError?: string } = {}
): Promise<T> {
  const fallbackName = opts.fallbackName ?? `photo_${Date.now()}.jpg`;
  const mimeType = mimeFromUri(fileUri, fallbackName);

  const headers: Record<string, string> = { Accept: 'application/json' };
  if (authToken) headers.Authorization = `Bearer ${authToken}`;

  let result: Awaited<ReturnType<typeof FileSystem.uploadAsync>>;
  try {
    result = await FileSystem.uploadAsync(`${API_BASE_URL}${path}`, fileUri, {
      httpMethod: 'POST',
      uploadType: FileSystem.FileSystemUploadType.MULTIPART,
      fieldName,
      mimeType,
      parameters,
      headers,
    });
  } catch {
    throw new Error(
      opts.connectionError ?? 'Could not upload the photo. Check your connection and try again.'
    );
  }

  let data: any = {};
  try {
    data = result.body ? JSON.parse(result.body) : {};
  } catch {
    data = {};
  }

  if (result.status < 200 || result.status >= 300) {
    const err = new Error(
      (data && (data.error || data.message)) || 'Upload failed.'
    ) as Error & { code?: string };
    if (result.status === 401 || result.status === 403) err.code = 'UNAUTHORIZED';
    throw err;
  }
  return data as T;
}

// Avatar upload uses multipart/form-data via the native uploader.
export function apiUploadAvatar(uri: string): Promise<{ user: ApiUser }> {
  return uploadFileMultipart<{ user: ApiUser }>('/api/profile/avatar', uri, 'avatar', {}, {
    fallbackName: `avatar_${Date.now()}.jpg`,
  });
}

// ── Messaging ──

export const getConversations = (archived = false) =>
  api<{ conversations: ApiConversation[]; unread_total: number }>(
    `/api/messages${archived ? '?archived=1' : ''}`
  );

export const getThread = (partnerId: number, lang?: string) =>
  api<{ partner: ApiChatPartner; messages: ApiMessage[]; archived: boolean }>(
    `/api/messages/thread?partner=${partnerId}${lang ? `&lang=${encodeURIComponent(lang)}` : ''}`
  );

export const pollThread = (partnerId: number, since: number, lang?: string) =>
  api<{ new: ApiMessage[]; statuses: { id: number; status: ChatStatus }[]; partner_online: boolean }>(
    `/api/messages/poll?partner=${partnerId}&since=${since}${lang ? `&lang=${encodeURIComponent(lang)}` : ''}`
  );

export const sendMessage = (partnerId: number, body: string, listingId?: number) =>
  api<{ message: ApiMessage | null }>('/api/messages/send', {
    method: 'POST',
    body: { partner_id: partnerId, body, listing_id: listingId },
  });

export const archiveConversation = (partnerId: number) =>
  api<{ success: boolean }>('/api/messages/archive', { method: 'POST', body: { partner_id: partnerId } });

export const unarchiveConversation = (partnerId: number) =>
  api<{ success: boolean }>('/api/messages/unarchive', { method: 'POST', body: { partner_id: partnerId } });

export const deleteConversation = (partnerId: number) =>
  api<{ success: boolean }>('/api/messages/delete', { method: 'POST', body: { partner_id: partnerId } });

export const getGuides = () => api<{ guides: ApiChatPartner[] }>('/api/guides');
