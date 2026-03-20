import { z } from "zod";

export const runtimeConfigInputSchema = z.object({
  tenantId: z.string().min(1),
  docsDirectory: z.string().optional(),
  assistant: z.object({
    companyCode: z.string().min(1),
    defaultLocale: z.string().min(2).optional(),
    defaultPropertyCode: z.string().optional(),
    defaultActorUserId: z.number().int().positive(),
    whatsappActorUserId: z.number().int().positive().optional(),
    whatsappRolesCsv: z.string().optional(),
    whatsappPermissionsCsv: z.string().optional()
  }),
  database: z.object({
    host: z.string().min(1),
    port: z.number().int().positive(),
    user: z.string().min(1),
    password: z.string().optional(),
    database: z.string().min(1),
    connectionLimit: z.number().int().positive().optional(),
    ssl: z.boolean().optional()
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
  })
});

export type RuntimeConfigInputSchema = z.infer<typeof runtimeConfigInputSchema>;
