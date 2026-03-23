import { Stack } from "expo-router";
import { StatusBar } from "expo-status-bar";

import { AppProviders } from "../src/providers/app-providers";
import { colors } from "../src/theme/tokens";

export default function RootLayout() {
  return (
    <AppProviders>
      <StatusBar style="dark" />
      <Stack
        screenOptions={{
          headerStyle: { backgroundColor: colors.canvas },
          headerShadowVisible: false,
          headerTintColor: colors.ink,
          contentStyle: { backgroundColor: colors.canvas }
        }}
      >
        <Stack.Screen name="index" options={{ title: "Disponibilidad" }} />
        <Stack.Screen name="preview" options={{ title: "Preview de reservacion" }} />
      </Stack>
    </AppProviders>
  );
}
