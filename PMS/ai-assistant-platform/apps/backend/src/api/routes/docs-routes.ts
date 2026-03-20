import { Router } from "express";

import { asyncRoute } from "../../shared/async-route";
import { ApplicationServices } from "../../shared/service-container";

export function createDocsRoutes(services: ApplicationServices): Router {
  const router = Router();

  router.get(
    "/docs",
    asyncRoute(async (request, response) => {
      const includeContent = request.query.includeContent === "true";
      const documents = await services.documentationService.listDocuments(includeContent);
      response.json({
        documents
      });
    })
  );

  return router;
}
