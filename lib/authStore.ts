import AsyncStorage from '@react-native-async-storage/async-storage';
import {
  apiChangePassword,
  apiDeleteAccount,
  apiLogin,
  apiLogout,
  apiMe,
  apiRegister,
  apiSocialLogin,
  ApiUser,
  setAuthToken,
} from '../services/api';
import { backendOAuthSignIn, resolveSocialCredential, SocialProvider } from './socialAuth';
import {
  canUseBiometrics,
  clearSavedCredentials,
  getSavedCredentials,
  getSavedSocialToken,
  isBiometricEnabled,
  promptBiometric,
  saveCredentials,
  saveSocialToken,
  setBiometricEnabled,
} from './biometric';

// Authentication backed by the GuideMate PHP API.
// The bearer token + the signed-in user are cached on the device so the
// session survives app restarts.

const TOKEN_KEY = 'guidemate_token';
const USER_KEY = 'guidemate_user';

export type SessionUser = {
  id: number;
  fullName: string;
  email: string;
  role: string;
  avatar: string;
};

function toSession(user: ApiUser): SessionUser {
  return {
    id: user.id,
    fullName: user.name,
    email: user.email,
    role: user.role,
    avatar: user.avatar,
  };
}

async function persist(token: string, user: ApiUser): Promise<SessionUser> {
  const session = toSession(user);
  setAuthToken(token);
  await AsyncStorage.setItem(TOKEN_KEY, token);
  await AsyncStorage.setItem(USER_KEY, JSON.stringify(session));
  return session;
}

// Restore a cached token at app startup so authenticated calls keep working.
export async function restoreSession(): Promise<SessionUser | null> {
  try {
    const token = await AsyncStorage.getItem(TOKEN_KEY);
    const rawUser = await AsyncStorage.getItem(USER_KEY);
    if (token && rawUser) {
      setAuthToken(token);
      return JSON.parse(rawUser) as SessionUser;
    }
  } catch {
    // ignore
  }
  return null;
}

export async function login(email: string, password: string): Promise<SessionUser> {
  const data = await apiLogin(email, password);
  const session = await persist(data.token, data.user);
  // Remember credentials so the user can sign back in with fingerprint only.
  await saveCredentials(email, password, session.fullName);
  return session;
}

export async function register(
  fullName: string,
  email: string,
  password: string
): Promise<SessionUser> {
  const data = await apiRegister(fullName, email, password);
  const session = await persist(data.token, data.user);
  await saveCredentials(email, password, session.fullName);
  return session;
}

// Persist a social session. Social sign-in has no password to replay, so we
// also wipe any fingerprint credentials left over from a previous password
// account (e.g. "jalel mauc") and turn biometric off — otherwise the saved
// fingerprint would keep logging into that old account instead of this one.
async function persistSocial(token: string, user: ApiUser): Promise<SessionUser> {
  const session = await persist(token, user);
  await setBiometricEnabled(false); // also clears saved credentials
  return session;
}

// Sign in with Google/Facebook. No password is involved, so nothing is saved
// for biometric replay — the account stays anonymous on the backend.
export async function loginWithSocial(provider: SocialProvider): Promise<SessionUser> {
  const credential = await resolveSocialCredential(provider);
  const data = await apiSocialLogin(credential);
  return persistSocial(data.token, data.user);
}

// Finish a real provider sign-in once we already hold a verified token (e.g.
// a Facebook access token). No password, so nothing is saved for biometrics.
export async function loginWithSocialToken(
  provider: SocialProvider,
  token: string
): Promise<SessionUser> {
  const data = await apiSocialLogin({ provider, token });
  return persistSocial(data.token, data.user);
}

// Real Google/Facebook sign-in for Expo Go: the backend performs the OAuth and
// hands us a ready GuideMate token, which we then use to load the account.
export async function loginWithBackendOAuth(provider: SocialProvider): Promise<SessionUser> {
  const token = await backendOAuthSignIn(provider);
  setAuthToken(token);
  const { user } = await apiMe();
  return persistSocial(token, user);
}

// Back-compat alias for the earlier Google-only entry point.
export function loginWithGoogleBackend(): Promise<SessionUser> {
  return loginWithBackendOAuth('google');
}

// Finish sign-in when the backend deep-links back with a ready GuideMate token
// (used by the app/auth/[provider] route that catches the OAuth redirect).
export async function completeBackendOAuth(token: string): Promise<SessionUser> {
  setAuthToken(token);
  const { user } = await apiMe();
  return persistSocial(token, user);
}

// True when a fingerprint login is possible: the user opted in, the device has
// an enrolled sensor, and we have something to replay — either a saved
// password (email accounts) or a saved session token (Google/Facebook).
export async function hasBiometricLogin(): Promise<boolean> {
  try {
    if (!(await isBiometricEnabled())) return false;
    if (!(await canUseBiometrics())) return false;
    if ((await getSavedCredentials()) !== null) return true;
    return (await getSavedSocialToken()) !== null;
  } catch {
    return false;
  }
}

// Turn on fingerprint login for whoever is signed in right now. Password
// accounts replay their saved credentials; Google/Facebook accounts (which
// have no password) save the current session token instead — so neither needs
// a password to set up fingerprint.
export async function enableFingerprint(): Promise<void> {
  const creds = await getSavedCredentials();
  if (creds) {
    await setBiometricEnabled(true);
    return;
  }
  const token = await AsyncStorage.getItem(TOKEN_KEY);
  const session = await getSession();
  if (!token || !session) {
    throw new Error('Please log in first, then enable fingerprint.');
  }
  await saveSocialToken(token, session.fullName);
  await setBiometricEnabled(true);
}

// Prompt for fingerprint, then sign in using the saved account. No password
// needed — replays a saved password, or a saved social session token.
export async function loginWithBiometrics(): Promise<SessionUser> {
  const creds = await getSavedCredentials();
  const socialToken = creds ? null : await getSavedSocialToken();
  if (!creds && !socialToken) {
    throw new Error('No saved account on this device. Please log in first.');
  }
  const ok = await promptBiometric('Log in to GuideMate');
  if (!ok) {
    throw new Error('Fingerprint not recognized.');
  }
  if (creds) {
    return login(creds.email, creds.password);
  }
  // Social account: restore the saved session token and reload the account.
  setAuthToken(socialToken as string);
  try {
    const { user } = await apiMe();
    return persist(socialToken as string, user);
  } catch {
    await setBiometricEnabled(false);
    throw new Error('Your saved session expired. Please sign in with Google or Facebook again.');
  }
}

export async function getSession(): Promise<SessionUser | null> {
  try {
    const raw = await AsyncStorage.getItem(USER_KEY);
    return raw ? (JSON.parse(raw) as SessionUser) : null;
  } catch {
    return null;
  }
}

// Clear the cached token + user locally, without hitting the API. Used when the
// server reports the token is expired/invalid (a network logout would just fail).
export async function clearSession(): Promise<void> {
  setAuthToken(null);
  await AsyncStorage.multiRemove([TOKEN_KEY, USER_KEY]);
}

export async function logout(): Promise<void> {
  // A social account (Google/Facebook) can't silently re-authenticate, so if
  // the user set up fingerprint we must KEEP its saved token valid on the
  // server — otherwise the fingerprint login would fail after logout. In that
  // case we only clear the local session and leave the token un-revoked.
  let keepSocialToken = false;
  try {
    keepSocialToken =
      (await isBiometricEnabled()) &&
      (await getSavedCredentials()) === null &&
      (await getSavedSocialToken()) !== null;
  } catch {
    keepSocialToken = false;
  }

  if (!keepSocialToken) {
    try {
      await apiLogout();
    } catch {
      // ignore network errors on logout
    }
  }
  await clearSession();
}

export async function changePassword(currentPassword: string, newPassword: string): Promise<void> {
  await apiChangePassword(currentPassword, newPassword);
}

export async function deleteAccount(password: string): Promise<void> {
  await apiDeleteAccount(password);
  setAuthToken(null);
  await AsyncStorage.multiRemove([TOKEN_KEY, USER_KEY]);
  // Wipe fingerprint login for the deleted account.
  await setBiometricEnabled(false);
  await clearSavedCredentials();
}
