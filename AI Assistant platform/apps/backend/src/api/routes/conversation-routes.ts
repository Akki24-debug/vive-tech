import { Router } from "express";
import { z } from "zod";

import { asyncRoute } from "../../shared/async-route";
import { ApplicationServices } from "../../shared/service-container";

export function createConversationRoutes(services: ApplicationServices): Router {
  const router = Router();

  router.get(
    "/conversations",
    asyncRoute(async (request, response) => {
      const limit = Number(request.query.limit ?? 20);
      const target =
        request.query.target !== undefined
          ? z.enum(["business_brain", "pms"]).parse(request.query.target)
          : undefined;
      const conversations = await services.conversationStore.listRecent(limit);
      response.json({
        conversations: target
          ? conversations.filter((conversation) => conversation.target === target)
          : conversations
      });
    })
  );

  router.get(
    "/conversations/:conversationId",
    asyncRoute(async (request, response) => {
      const conversation = await services.conversationStore.getConversation(
        String(request.params.conversationId)
      );
      response.json({
        conversation
      });
    })
  );

  return router;
}
