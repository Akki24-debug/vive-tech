import { Router } from "express";
import { z } from "zod";

import { BrainAdminDeletePayload, BrainAdminResourceKey, BrainAdminSavePayload } from "@vlv-ai/shared";

import { asyncRoute } from "../../shared/async-route";
import { ApplicationServices } from "../../shared/service-container";

const resourceSchema = z.enum([
  "organization",
  "user_account",
  "role",
  "user_role",
  "user_area_assignment",
  "user_capacity_profile",
  "business_area",
  "business_line",
  "business_priority",
  "objective_record",
  "external_system",
  "knowledge_document"
]);

const savePayloadSchema = z.object({
  values: z.record(z.string(), z.unknown())
});

const deletePayloadSchema = z.object({
  reason: z.string().optional()
});

function resolveActorUserId(headerValue: unknown): number | null {
  if (typeof headerValue !== "string") {
    return null;
  }

  const trimmed = headerValue.trim();

  if (!trimmed) {
    return null;
  }

  const numeric = Number(trimmed);
  return Number.isInteger(numeric) && numeric >= 0 ? numeric : null;
}

export function createBrainAdminRoutes(services: ApplicationServices): Router {
  const router = Router();

  router.get(
    "/brain-admin/bootstrap",
    asyncRoute(async (request, response) => {
      const actorUserId = resolveActorUserId(request.header("x-brain-actor-user-id"));
      const bootstrap = await services.brainAdminService.getBootstrap(actorUserId);
      response.json(bootstrap);
    })
  );

  router.get(
    "/brain-admin/summary",
    asyncRoute(async (request, response) => {
      const actorUserId = resolveActorUserId(request.header("x-brain-actor-user-id"));
      const summary = await services.brainAdminService.getSummary(actorUserId);
      response.json(summary);
    })
  );

  router.get(
    "/brain-admin/options",
    asyncRoute(async (request, response) => {
      const actorUserId = resolveActorUserId(request.header("x-brain-actor-user-id"));
      const options = await services.brainAdminService.getOptions(actorUserId);
      response.json(options);
    })
  );

  router.get(
    "/brain-admin/users/:id/context",
    asyncRoute(async (request, response) => {
      const actorUserId = resolveActorUserId(request.header("x-brain-actor-user-id"));
      const id = z.coerce.number().int().positive().parse(request.params.id);
      const detail = await services.brainAdminService.getUserContext(id, actorUserId);
      response.json(detail);
    })
  );

  router.post(
    "/brain-admin/users/:id/roles/sync",
    asyncRoute(async (request, response) => {
      const actorUserId = resolveActorUserId(request.header("x-brain-actor-user-id"));
      const id = z.coerce.number().int().positive().parse(request.params.id);
      const roleIds = z.array(z.number().int().positive()).parse(request.body?.roleIds ?? []);
      const detail = await services.brainAdminService.syncUserRoles(id, roleIds, actorUserId);
      response.json(detail);
    })
  );

  router.post(
    "/brain-admin/knowledge-documents/:id/publish",
    asyncRoute(async (request, response) => {
      const actorUserId = resolveActorUserId(request.header("x-brain-actor-user-id"));
      const id = z.coerce.number().int().positive().parse(request.params.id);
      const detail = await services.brainAdminService.publishKnowledgeDocument(id, actorUserId);
      response.json(detail);
    })
  );

  router.get(
    "/brain-admin/:resource",
    asyncRoute(async (request, response) => {
      const actorUserId = resolveActorUserId(request.header("x-brain-actor-user-id"));
      const resource = resourceSchema.parse(request.params.resource) as BrainAdminResourceKey;
      const result = await services.brainAdminService.listResource(
        resource,
        request.query as Record<string, unknown>,
        actorUserId
      );
      response.json(result);
    })
  );

  router.get(
    "/brain-admin/:resource/:id",
    asyncRoute(async (request, response) => {
      const actorUserId = resolveActorUserId(request.header("x-brain-actor-user-id"));
      const resource = resourceSchema.parse(request.params.resource) as BrainAdminResourceKey;
      const id = z.coerce.number().int().positive().parse(request.params.id);
      const result = await services.brainAdminService.getResourceDetail(resource, id, actorUserId);
      response.json(result);
    })
  );

  router.post(
    "/brain-admin/:resource",
    asyncRoute(async (request, response) => {
      const actorUserId = resolveActorUserId(request.header("x-brain-actor-user-id"));
      const resource = resourceSchema.parse(request.params.resource) as BrainAdminResourceKey;
      const payload = savePayloadSchema.parse(request.body) as BrainAdminSavePayload;
      const result = await services.brainAdminService.saveResource(
        resource,
        null,
        payload,
        actorUserId
      );
      response.status(201).json(result);
    })
  );

  router.put(
    "/brain-admin/:resource/:id",
    asyncRoute(async (request, response) => {
      const actorUserId = resolveActorUserId(request.header("x-brain-actor-user-id"));
      const resource = resourceSchema.parse(request.params.resource) as BrainAdminResourceKey;
      const id = z.coerce.number().int().positive().parse(request.params.id);
      const payload = savePayloadSchema.parse(request.body) as BrainAdminSavePayload;
      const result = await services.brainAdminService.saveResource(resource, id, payload, actorUserId);
      response.json(result);
    })
  );

  router.delete(
    "/brain-admin/:resource/:id",
    asyncRoute(async (request, response) => {
      const actorUserId = resolveActorUserId(request.header("x-brain-actor-user-id"));
      const resource = resourceSchema.parse(request.params.resource) as BrainAdminResourceKey;
      const id = z.coerce.number().int().positive().parse(request.params.id);
      const payload = deletePayloadSchema.parse(request.body ?? {}) as BrainAdminDeletePayload;
      const result = await services.brainAdminService.deleteResource(resource, id, payload, actorUserId);
      response.json(result);
    })
  );

  return router;
}
