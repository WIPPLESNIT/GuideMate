import { Redirect } from 'expo-router';

export default function RootIndex() {
  // Automatically routes the user to the login screen on application startup
  return <Redirect href="/(auth)/login" />;
}