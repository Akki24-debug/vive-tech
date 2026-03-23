import { PropsWithChildren } from "react";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { SafeAreaProvider } from "react-native-safe-area-context";

import { ReservationPreviewProvider } from "./reservation-preview-provider";

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 30_000,
      retry: 1
    }
  }
});

export function AppProviders({ children }: PropsWithChildren) {
  return (
    <SafeAreaProvider>
      <QueryClientProvider client={queryClient}>
        <ReservationPreviewProvider>{children}</ReservationPreviewProvider>
      </QueryClientProvider>
    </SafeAreaProvider>
  );
}
