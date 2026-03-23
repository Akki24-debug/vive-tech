import { RequiredDocumentKey, SharedDocumentKey } from "./types";

export const REQUIRED_DOCUMENTS: Record<RequiredDocumentKey, string> = {
  business_rules: "business_rules.md",
  stored_procedures: "stored_procedures.md",
  assistant_behavior: "assistant_behavior.md",
  permissions: "permissions.md",
  company_context: "company_context.md"
};

export const SHARED_DOCUMENTS: Record<SharedDocumentKey, string> = {
  platform_overview: "platform_overview.md",
  target_routing: "target_routing.md"
};
