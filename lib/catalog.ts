import { ApiListing } from '../services/api';

const HIDDEN_CATEGORY_SLUGS = new Set(['restaurants', 'restaurant']);

export function isHiddenCategorySlug(slug?: string | null): boolean {
  if (!slug) return false;
  return HIDDEN_CATEGORY_SLUGS.has(slug.toLowerCase());
}

export function isHiddenListing(item: Pick<ApiListing, 'category_slug' | 'category'>): boolean {
  if (isHiddenCategorySlug(item.category_slug)) return true;
  return (item.category ?? '').toLowerCase() === 'restaurants';
}
