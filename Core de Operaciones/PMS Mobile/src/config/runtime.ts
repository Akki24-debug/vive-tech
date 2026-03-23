const apiBaseUrl = process.env.EXPO_PUBLIC_PMS_MOBILE_API_URL ?? "http://localhost:3000/api";
const tenantId = process.env.EXPO_PUBLIC_PMS_MOBILE_TENANT_ID ?? "local-dev";
const companyCode = process.env.EXPO_PUBLIC_PMS_MOBILE_COMPANY_CODE ?? "VLV";
const userId = process.env.EXPO_PUBLIC_PMS_MOBILE_USER_ID ?? "mobile-internal";
const actorUserId = Number(process.env.EXPO_PUBLIC_PMS_MOBILE_ACTOR_USER_ID ?? "1");

export const runtimeConfig = {
  apiBaseUrl,
  tenantId,
  companyCode,
  userId,
  actorUserId: Number.isFinite(actorUserId) && actorUserId > 0 ? actorUserId : 1
};
