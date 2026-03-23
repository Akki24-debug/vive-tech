import { Request, Router } from "express";
import { z } from "zod";

import { MobilePmsRequestContext } from "@vlv-ai/shared";

import { asyncRoute } from "../../shared/async-route";
import { ApplicationServices } from "../../shared/service-container";

const dateString = z.string().regex(/^\d{4}-\d{2}-\d{2}$/);

const contextSchema = z.object({
  tenantId: z.string().min(1),
  companyCode: z.string().min(1),
  userId: z.string().min(1),
  actorUserId: z.coerce.number().int().positive(),
  locale: z.string().optional()
});

const availabilitySearchSchema = z.object({
  propertyCode: z.string().trim().min(1).nullable().optional(),
  dateStart: dateString,
  dateEnd: dateString.nullable().optional(),
  nights: z.coerce.number().int().positive().max(30).nullable().optional(),
  people: z.coerce.number().int().positive().max(20).nullable().optional(),
  visibleWindowDays: z.coerce.number().int().positive().max(30).optional()
});

function parseContext(request: Request): MobilePmsRequestContext {
  return contextSchema.parse({
    tenantId: request.header("x-mobile-tenant-id"),
    companyCode: request.header("x-mobile-company-code"),
    userId: request.header("x-mobile-user-id"),
    actorUserId: request.header("x-mobile-actor-user-id"),
    locale: request.header("x-mobile-locale") ?? undefined
  });
}

export function createMobilePmsRoutes(services: ApplicationServices): Router {
  const router = Router();

  router.get(
    "/mobile/pms/bootstrap",
    asyncRoute(async (request, response) => {
      const context = parseContext(request);
      const result = await services.pmsMobileService.getBootstrap(context);
      response.json(result);
    })
  );

  router.post(
    "/mobile/pms/availability/search",
    asyncRoute(async (request, response) => {
      const context = parseContext(request);
      const payload = availabilitySearchSchema.parse(request.body);
      const result = await services.pmsMobileService.searchAvailability(context, payload);
      response.json(result);
    })
  );

  return router;
}
