import { Router } from "express";

import { asyncRoute } from "../../shared/async-route";
import { ApplicationServices } from "../../shared/service-container";

export function createHealthRoutes(services: ApplicationServices): Router {
  const router = Router();

  router.get(
    "/health",
    asyncRoute(async (_request, response) => {
      const config = await services.runtimeConfigService.getSanitizedConfig();
      response.json({
        ok: true,
        configured: Boolean(config),
        tenantId: config?.tenantId ?? null,
        defaultTarget: config?.defaultTarget ?? null
      });
    })
  );

  return router;
}
