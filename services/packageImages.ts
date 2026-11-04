import { resolveImage } from './api';

export const PACKAGE_LOCAL_IMAGES: Record<string, any> = {
  kawasan: require('../assets/images/places/kawasan.jpg'),
  sardine: require('../assets/images/places/sardine.jpg'),
  whaleshark: require('../assets/images/places/whaleshark.jpg'),
  templeofleah: require('../assets/images/places/templeofleah.jpg'),
  bantayan: require('../assets/images/places/bantayan.jpg'),
  osmena: require('../assets/images/places/osmena.jpg'),
  magellan: require('../assets/images/places/magellan.jpg'),
};

/**
 * Resolve the package cover image source.
 * If a local asset key exists, use it.
 * Otherwise, resolve the remote or uploaded URL against the API backend.
 * Falls back safely to kawasan cover image if not found.
 */
export function getPackageCoverSource(localKey?: string, remoteUrl?: string): any {
  if (localKey && PACKAGE_LOCAL_IMAGES[localKey]) {
    return PACKAGE_LOCAL_IMAGES[localKey];
  }
  if (remoteUrl && typeof remoteUrl === 'string' && remoteUrl.trim() !== '') {
    const trimmed = remoteUrl.trim();
    const resolved = resolveImage(trimmed);
    if (resolved) {
      return { uri: resolved };
    }
  }
  return PACKAGE_LOCAL_IMAGES.kawasan;
}
