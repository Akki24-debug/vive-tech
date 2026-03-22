import { Router } from "express";
import { z } from "zod";

import { asyncRoute } from "../../shared/async-route";
import { ApplicationServices } from "../../shared/service-container";

export function createLogRoutes(services: ApplicationServices): Router {
  const router = Router();

  router.get(
    "/logs",
    asyncRoute(async (request, response) => {
      const limit = Number(request.query.limit ?? 100);
      const events = await services.activityLogService.listRecent(limit);
      const target =
        request.query.target !== undefined
          ? z.enum(["business_brain", "pms", "shared"]).parse(request.query.target)
          : undefined;
      response.json({
        events: target ? events.filter((event) => event.target === target) : events
      });
    })
  );

  return router;
}
