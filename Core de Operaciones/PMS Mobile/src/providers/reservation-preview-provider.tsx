import { createContext, PropsWithChildren, useContext, useState } from "react";

import { ReservationDraftPreview } from "@vlv-ai/shared";

interface ReservationPreviewContextValue {
  draft: ReservationDraftPreview | null;
  setDraft: (draft: ReservationDraftPreview | null) => void;
}

const ReservationPreviewContext = createContext<ReservationPreviewContextValue | undefined>(
  undefined
);

export function ReservationPreviewProvider({ children }: PropsWithChildren) {
  const [draft, setDraft] = useState<ReservationDraftPreview | null>(null);

  return (
    <ReservationPreviewContext.Provider value={{ draft, setDraft }}>
      {children}
    </ReservationPreviewContext.Provider>
  );
}

export function useReservationPreview() {
  const value = useContext(ReservationPreviewContext);
  if (!value) {
    throw new Error("useReservationPreview must be used inside ReservationPreviewProvider.");
  }
  return value;
}
