import { Router } from "express";

import { z } from "zod";

import { asyncRoute } from "../../shared/async-route";
import { ApplicationServices } from "../../shared/service-container";

export function createDocsRoutes(services: ApplicationServices): Router {
  const router = Router();

  router.get(
    "/docs",
    asyncRoute(async (request, response) => {
      const includeContent = request.query.includeContent === "true";
      const target = z
        .enum(["business_brain", "pms"])
        .parse(request.query.target ?? "business_brain");
      const documents = await services.documentationService.listDocuments(target, includeContent);
      response.json({
        documents
      });
    })
  );

  return router;
}
