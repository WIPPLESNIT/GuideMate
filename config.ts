import Constants from 'expo-constants';

// Base URL of your GuideMate PHP web backend.
//
// In development we AUTO-DETECT your PC's LAN IP from the Expo dev server that
// Expo Go is already connected to. That means when your Wi-Fi IP changes
// (DHCP), the API address follows automatically — no manual edits needed.
//
// For a production build, set PROD_API_BASE_URL to your real hosted URL.

const PROD_API_BASE_URL = 'https://yourdomain.com/GuideMate/public';

// Fallback used only if the dev host can't be detected (e.g. tunnel mode).
const FALLBACK_DEV_IP = '10.0.4.99';

function detectDevHost(): string {
  // Expo exposes the dev server as "<ip>:<port>" across SDK versions.
  const hostUri =
    Constants.expoConfig?.hostUri ||
    (Constants as any).expoGoConfig?.debuggerHost ||
    (Constants as any).manifest2?.extra?.expoGo?.debuggerHost ||
    (Constants as any).manifest?.debuggerHost ||
    '';
  const host = String(hostUri).split(':')[0];
  return host || FALLBACK_DEV_IP;
}

export const API_BASE_URL = __DEV__
  ? `http://${detectDevHost()}/GuideMate/public`
  : PROD_API_BASE_URL;

// ─────────────────────────────────────────────────────────────────────────────
// Social sign-in (Google / Facebook)
//
// PLUG-AND-PLAY: as soon as you paste a real client ID / app ID below, that
// button opens the REAL Google/Facebook login (the user signs into their own
// account). Until then, `demo: true` lets the button work with an anonymous
// demo account so you can still show the flow. No flag to flip.
//
// The backend never receives or stores a real email or password either way.
//
//   • Google client ID:  https://console.cloud.google.com/apis/credentials
//       - For a dev build / store build: create an Android + iOS OAuth client.
//       - Use the app's redirect scheme "guidemate" (see app.json).
//   • Facebook app ID:   https://developers.facebook.com/apps
//
// Also mirror these on the PHP backend's .env (GOOGLE_OAUTH_CLIENT_IDS /
// FACEBOOK_APP_ID) so it can verify the audience of the real tokens.
// ─────────────────────────────────────────────────────────────────────────────
export const SOCIAL_AUTH = {
  demo: true, // fallback only — ignored for a provider once configured below
  googleClientIds: {
    expo: '', // Web-type client id (Expo Go / web)
    ios: '', // paste your iOS OAuth client id here for iOS dev/store builds
    android: '', // paste your Android OAuth client id here for Android dev/store builds
    web: '594984195783-95p4qciig7ghg2jtok9a709ov8k0embh.apps.googleusercontent.com',
  },
  // Paste your Facebook App ID here to switch Facebook from demo to the real
  // backend-mediated login (the actual OAuth uses the backend .env secret).
  facebookAppId: '',

  // Expo Go-compatible Google/Facebook login is mediated by your PHP backend.
  // Google now uses a localhost callback inside the in-app WebView (no ngrok).
  // Facebook still needs this public https origin when configured.
  backendPublicUrl: 'https://concinnous-unobliging-max.ngrok-free.dev/GuideMate/public',
};
