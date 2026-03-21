import { Router } from "express";

import { asyncRoute } from "../../shared/async-route";
import { ApplicationServices } from "../../shared/service-container";

export function createLogRoutes(services: ApplicationServices): Router {
  const router = Router();

  router.get(
    "/logs",
    asyncRoute(async (request, response) => {
      const limit = Number(request.query.limit ?? 100);
      const events = await services.activityLogService.listRecent(limit);
      response.json({
        events
      });
    })
  );

  return router;
}
