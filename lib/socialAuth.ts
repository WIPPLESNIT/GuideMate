import AsyncStorage from '@react-native-async-storage/async-storage';
import * as AuthSession from 'expo-auth-session';
import * as Crypto from 'expo-crypto';
import * as WebBrowser from 'expo-web-browser';
import { router } from 'expo-router';
import { API_BASE_URL, SOCIAL_AUTH } from '../config';

WebBrowser.maybeCompleteAuthSession();

export type SocialProvider = 'google' | 'facebook';

export type SocialCredential =
  | { provider: SocialProvider; subject: string }
  | { provider: SocialProvider; token: string };

async function demoSubject(provider: SocialProvider): Promise<string> {
  const key = `guidemate_demo_${provider}_id`;
  let id = await AsyncStorage.getItem(key);
  if (!id) {
    id = `${provider}-${Crypto.randomUUID()}`;
    await AsyncStorage.setItem(key, id);
  }
  return id;
}

const PROVIDER_LABEL: Record<SocialProvider, string> = {
  google: 'Google',
  facebook: 'Facebook',
};

function tokenFromUrl(url: string): { token?: string; error?: string } {
  const err = /[?&]error=([^&#]+)/.exec(url);
  if (err) return { error: decodeURIComponent(err[1]) };
  const match = /[?&]token=([^&#]+)/.exec(url);
  if (match) return { token: decodeURIComponent(match[1]) };
  return {};
}

type GoogleWaiter = { resolve: (token: string) => void; reject: (error: Error) => void };
let googleWaiter: GoogleWaiter | null = null;

export function settleGoogleSignIn(token: string): void {
  if (!googleWaiter) return;
  const waiter = googleWaiter;
  googleWaiter = null;
  waiter.resolve(token);
}

export function failGoogleSignIn(error: Error): void {
  if (!googleWaiter) return;
  const waiter = googleWaiter;
  googleWaiter = null;
  waiter.reject(error);
}

/** Ask the PHP API (on the PC LAN) for Google's authorize URL. Never uses ngrok. */
export async function prepareGoogleAuthUrl(): Promise<{
  authUrl: string;
  sessionId: string;
  redirectUri: string;
}> {
  const lanBase = API_BASE_URL.replace(/\/+$/, '');
  const returnUrl = AuthSession.makeRedirectUri({ path: 'auth/google' });
  const qs = `return=${encodeURIComponent(returnUrl)}&format=json`;
  const res = await fetch(`${lanBase}/api/auth/google/start?${qs}`);
  const data = (await res.json()) as {
    auth_url?: string;
    session_id?: string;
    redirect_uri?: string;
    error?: string;
  };
  if (!res.ok || !data.auth_url) {
    throw new Error(
      data.error || 'Could not reach GuideMate on this PC. Start XAMPP Apache, then try Google sign-in again.'
    );
  }
  return {
    authUrl: data.auth_url,
    sessionId: data.session_id ?? '',
    redirectUri: data.redirect_uri ?? 'http://localhost/GuideMate/public/api/auth/google/callback',
  };
}

/**
 * Google sign-in runs in an in-app WebView and intercepts the redirect so the
 * dead ngrok page (err_ngrok_3200) is never loaded. Facebook still uses the
 * public tunnel when configured.
 */
export async function backendOAuthSignIn(provider: SocialProvider): Promise<string> {
  const label = PROVIDER_LABEL[provider];

  if (provider === 'google') {
    return new Promise((resolve, reject) => {
      if (googleWaiter) {
        googleWaiter.reject(new Error('Google sign-in is already in progress.'));
      }
      googleWaiter = { resolve, reject };
      router.push('/auth/google-signin');
    });
  }

  const returnUrl = AuthSession.makeRedirectUri({ path: `auth/${provider}` });
  const publicBase = (SOCIAL_AUTH.backendPublicUrl || API_BASE_URL).replace(/\/+$/, '');
  const startUrl = `${publicBase}/api/auth/${provider}/start?return=${encodeURIComponent(returnUrl)}`;
  const result = await WebBrowser.openAuthSessionAsync(startUrl, returnUrl);
  if (result.type !== 'success' || !result.url) {
    throw new Error(`${label} sign-in was cancelled.`);
  }
  const parsed = tokenFromUrl(result.url);
  if (parsed.error) throw new Error(parsed.error);
  if (!parsed.token) throw new Error(`${label} sign-in did not complete. Please try again.`);
  return parsed.token;
}

export function backendGoogleSignIn(): Promise<string> {
  return backendOAuthSignIn('google');
}

export function isProviderConfigured(provider: SocialProvider): boolean {
  if (provider === 'facebook') {
    return SOCIAL_AUTH.facebookAppId.trim() !== '';
  }
  const g = SOCIAL_AUTH.googleClientIds;
  return [g.expo, g.ios, g.android, g.web].some((id) => (id ?? '').trim() !== '');
}

export async function resolveSocialCredential(provider: SocialProvider): Promise<SocialCredential> {
  return { provider, subject: await demoSubject(provider) };
}
