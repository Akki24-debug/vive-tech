import {
  AvailabilityFilters,
  MobilePmsAvailabilityResponse,
  MobilePmsBootstrapResponse
} from "@vlv-ai/shared";

import { runtimeConfig } from "../config/runtime";

function mobileHeaders(): HeadersInit {
  return {
    "Content-Type": "application/json",
    "x-mobile-tenant-id": runtimeConfig.tenantId,
    "x-mobile-company-code": runtimeConfig.companyCode,
    "x-mobile-user-id": runtimeConfig.userId,
    "x-mobile-actor-user-id": String(runtimeConfig.actorUserId)
  };
}

async function request<T>(path: string, init?: RequestInit): Promise<T> {
  const response = await fetch(`${runtimeConfig.apiBaseUrl}${path}`, {
    ...init,
    headers: {
      ...mobileHeaders(),
      ...(init?.headers ?? {})
    }
  });

  const payload = (await response.json().catch(() => null)) as
    | T
    | { error?: { message?: string } }
    | null;

  if (!response.ok) {
    const message =
      payload && typeof payload === "object" && "error" in payload && payload.error?.message
        ? payload.error.message
        : "No se pudo completar la solicitud.";
    throw new Error(message);
  }

  return payload as T;
}

export const mobileApi = {
  getBootstrap: () => request<MobilePmsBootstrapResponse>("/mobile/pms/bootstrap"),
  searchAvailability: (filters: AvailabilityFilters) =>
    request<MobilePmsAvailabilityResponse>("/mobile/pms/availability/search", {
      method: "POST",
      body: JSON.stringify(filters)
    })
};
