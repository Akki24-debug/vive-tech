import { Router } from "express";
import { z } from "zod";

import { ConnectionTestRequest } from "@vlv-ai/shared";

import { runtimeConfigInputSchema } from "../../config/config-schema";
import { asyncRoute } from "../../shared/async-route";
import { ApplicationServices } from "../../shared/service-container";

const connectionTestRequestSchema = z.object({
  target: z.enum(["database", "openai", "whatsapp"]),
  domainTarget: z.enum(["business_brain", "pms"]).optional(),
  candidateConfig: z.any().optional()
});

export function createConfigRoutes(services: ApplicationServices): Router {
  const router = Router();

  router.get(
    "/config",
    asyncRoute(async (_request, response) => {
      const config = await services.runtimeConfigService.getSanitizedConfig();
      response.json({
        config
      });
    })
  );

  router.post(
    "/config",
    asyncRoute(async (request, response) => {
      const parsed = runtimeConfigInputSchema.parse(request.body);
      const config = await services.runtimeConfigService.saveConfig(parsed);
      await services.activityLogService.info("config.saved", "Saved runtime configuration.", {
        tenantId: config.tenantId
      });
      response.status(201).json({
        config
      });
    })
  );

  router.post(
    "/config/test",
    asyncRoute(async (request, response) => {
      const parsed = connectionTestRequestSchema.parse(request.body) as ConnectionTestRequest;
      const result = await services.connectionTestService.testConnection(parsed);
      response.json({
        result
      });
    })
  );

  return router;
}
