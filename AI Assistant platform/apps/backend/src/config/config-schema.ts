import { z } from "zod";

const targetAssistantSchema = z.object({
  companyCode: z.string().min(1),
  defaultLocale: z.string().min(2).optional(),
  defaultPropertyCode: z.string().optional(),
  defaultActorUserId: z.number().int().nonnegative(),
  whatsappActorUserId: z.number().int().nonnegative().optional(),
  whatsappRolesCsv: z.string().optional(),
  whatsappPermissionsCsv: z.string().optional()
});

const targetDatabaseSchema = z.object({
  host: z.string().min(1),
  port: z.number().int().positive(),
  user: z.string().min(1),
  password: z.string().optional(),
  database: z.string().min(1),
  connectionLimit: z.number().int().positive().optional(),
  ssl: z.boolean().optional()
});

const targetRuntimeSchema = z.object({
  enabled: z.boolean(),
  docsDirectory: z.string().optional(),
  assistant: targetAssistantSchema,
  database: targetDatabaseSchema
});

const optimizationSchema = z.object({
  cheapModeEnabled: z.boolean().optional(),
  debugModelOverride: z.string().min(1).optional(),
  disableBroadBrainSnapshots: z.boolean().optional(),
  skipFinalLlmForSimpleReads: z.boolean().optional(),
  logEstimatedCost: z.boolean().optional(),
  maxRecentConversationMessages: z.number().int().positive().optional(),
  maxDocs: z.number().int().positive().optional(),
  maxDocsBundleBytes: z.number().int().positive().optional()
});

export const runtimeConfigInputSchema = z.object({
  tenantId: z.string().min(1),
  defaultTarget: z.enum(["business_brain", "pms"]).optional(),
  domains: z.object({
    business_brain: targetRuntimeSchema,
    pms: targetRuntimeSchema
  }),
  openai: z.object({
    apiKey: z.string().optional(),
    model: z.string().min(1),
    baseUrl: z.string().url().optional(),
    timeoutMs: z.number().int().positive().optional()
  }),
  whatsapp: z.object({
    provider: z.literal("meta-cloud"),
    baseUrl: z.string().url(),
    phoneNumberId: z.string().min(1),
    businessAccountId: z.string().optional(),
    apiToken: z.string().optional(),
    appSecret: z.string().optional(),
    webhookVerifyToken: z.string().optional()
  }),
  execution: z.object({
    mode: z.enum(["auto", "manual", "hybrid"]),
    enableWrites: z.boolean()
  }),
  optimization: optimizationSchema.optional()
});

export type RuntimeConfigInputSchema = z.infer<typeof runtimeConfigInputSchema>;
