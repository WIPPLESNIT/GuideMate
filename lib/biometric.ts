import AsyncStorage from '@react-native-async-storage/async-storage';
import * as LocalAuthentication from 'expo-local-authentication';
import * as SecureStore from 'expo-secure-store';

// Stores whether the user opted into fingerprint/Face ID login, plus the
// credentials needed to sign back in after a successful biometric check.
// The password is kept in the OS secure keystore (Keychain / Keystore),
// never in plain AsyncStorage.

const BIO_KEY = 'guidemate_biometric_enabled';
const CRED_KEY = 'guidemate_saved_login';
const TOKEN_KEY = 'guidemate_saved_token';
const NAME_KEY = 'guidemate_saved_name';

export type SavedCredentials = { email: string; password: string };

export async function isBiometricEnabled(): Promise<boolean> {
  try {
    return (await AsyncStorage.getItem(BIO_KEY)) === '1';
  } catch {
    return false;
  }
}

export async function setBiometricEnabled(enabled: boolean): Promise<void> {
  try {
    await AsyncStorage.setItem(BIO_KEY, enabled ? '1' : '0');
    if (!enabled) {
      await clearSavedCredentials();
    }
  } catch {
    // ignore
  }
}

// Remember the last successful login so it can be replayed after a fingerprint
// check. Called automatically on every login/register.
export async function saveCredentials(email: string, password: string, name: string): Promise<void> {
  try {
    await SecureStore.setItemAsync(CRED_KEY, JSON.stringify({ email, password }));
    await AsyncStorage.setItem(NAME_KEY, name);
  } catch {
    // ignore
  }
}

export async function getSavedCredentials(): Promise<SavedCredentials | null> {
  try {
    const raw = await SecureStore.getItemAsync(CRED_KEY);
    return raw ? (JSON.parse(raw) as SavedCredentials) : null;
  } catch {
    return null;
  }
}

// Social accounts (Google/Facebook) have no password to replay, so we store
// the app session token instead. A fingerprint check then restores it.
export async function saveSocialToken(token: string, name: string): Promise<void> {
  try {
    await SecureStore.setItemAsync(TOKEN_KEY, token);
    await AsyncStorage.setItem(NAME_KEY, name);
  } catch {
    // ignore
  }
}

export async function getSavedSocialToken(): Promise<string | null> {
  try {
    return await SecureStore.getItemAsync(TOKEN_KEY);
  } catch {
    return null;
  }
}

export async function getSavedName(): Promise<string | null> {
  try {
    return await AsyncStorage.getItem(NAME_KEY);
  } catch {
    return null;
  }
}

export async function clearSavedCredentials(): Promise<void> {
  try {
    await SecureStore.deleteItemAsync(CRED_KEY);
    await SecureStore.deleteItemAsync(TOKEN_KEY);
    await AsyncStorage.removeItem(NAME_KEY);
  } catch {
    // ignore
  }
}

// Whether this device actually has a usable fingerprint/face sensor enrolled.
export async function canUseBiometrics(): Promise<boolean> {
  try {
    const hasHardware = await LocalAuthentication.hasHardwareAsync();
    const enrolled = await LocalAuthentication.isEnrolledAsync();
    return hasHardware && enrolled;
  } catch {
    return false;
  }
}

// Show the native fingerprint/Face ID prompt. Returns true on success.
export async function promptBiometric(message = 'Log in with fingerprint'): Promise<boolean> {
  try {
    const result = await LocalAuthentication.authenticateAsync({
      promptMessage: message,
      cancelLabel: 'Use password',
      disableDeviceFallback: false,
    });
    return result.success;
  } catch {
    return false;
  }
}
