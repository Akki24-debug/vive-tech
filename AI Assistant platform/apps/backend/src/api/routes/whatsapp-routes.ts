import { Router } from "express";

import { asyncRoute } from "../../shared/async-route";
import { AuthorizationError } from "../../shared/errors";
import { ApplicationServices } from "../../shared/service-container";

export function createWhatsAppRoutes(services: ApplicationServices): Router {
  const router = Router();

  router.get(
    "/whatsapp/webhook",
    asyncRoute(async (request, response) => {
      const challenge = await services.whatsappService.verifyWebhook(
        request.query["hub.mode"] as string | undefined,
        request.query["hub.verify_token"] as string | undefined,
        request.query["hub.challenge"] as string | undefined
      );

      if (!challenge) {
        throw new AuthorizationError("WhatsApp webhook verification failed.");
      }

      response.status(200).send(challenge);
    })
  );

  router.post(
    "/whatsapp/webhook",
    asyncRoute(async (request, response) => {
      await services.whatsappService.processInboundWebhook(request.body);
      response.status(200).json({
        ok: true
      });
    })
  );

  return router;
}
