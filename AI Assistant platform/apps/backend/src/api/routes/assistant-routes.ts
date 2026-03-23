import { Router } from "express";
import { z } from "zod";

import { asyncRoute } from "../../shared/async-route";
import { ApplicationServices } from "../../shared/service-container";

const assistantRequestSchema = z.object({
  tenantId: z.string().min(1),
  target: z.enum(["business_brain", "pms"]),
  requestId: z.string().min(1).optional(),
  companyCode: z.string().min(1),
  conversationId: z.string().min(1),
  userId: z.string().min(1),
  actorUserId: z.number().int().nonnegative(),
  message: z.string().min(1),
  propertyCode: z.string().optional(),
  locale: z.string().optional(),
  channel: z.enum(["web", "whatsapp", "admin"]),
  roles: z.array(z.string()).default([]),
  permissions: z.array(z.string()).default([])
});

export function createAssistantRoutes(services: ApplicationServices): Router {
  const router = Router();

  router.get(
    "/actions",
    asyncRoute(async (request, response) => {
      const target = z
        .enum(["business_brain", "pms"])
        .parse(request.query.target ?? "business_brain");
      response.json({
        actions: services.listActionCatalog(target)
      });
    })
  );

  router.post(
    "/assistant/messages",
    asyncRoute(async (request, response) => {
      const parsed = assistantRequestSchema.parse(request.body);
      const result = await services.assistantOrchestrator.handleUserMessage(parsed);
      response.json(result);
    })
  );

  return router;
}
