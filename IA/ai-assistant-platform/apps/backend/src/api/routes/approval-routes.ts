import { Router } from "express";
import { z } from "zod";

import { asyncRoute } from "../../shared/async-route";
import { ApplicationServices } from "../../shared/service-container";

const decisionSchema = z.object({
  approverId: z.string().min(1)
});

export function createApprovalRoutes(services: ApplicationServices): Router {
  const router = Router();

  router.get(
    "/approvals",
    asyncRoute(async (_request, response) => {
      const approvals = await services.approvalService.listApprovals();
      response.json({
        approvals
      });
    })
  );

  router.post(
    "/approvals/:approvalId/approve",
    asyncRoute(async (request, response) => {
      const parsed = decisionSchema.parse(request.body);
      const approvalId = String(request.params.approvalId);
      const pendingApproval = await services.approvalService.getApproval(approvalId);
      const result = await services.assistantOrchestrator.approveAndExecute(
        approvalId,
        parsed.approverId
      );
      if (pendingApproval.requestContext.channel === "whatsapp") {
        await services.whatsappService.sendTextMessage(pendingApproval.requestedBy, result.answer);
      }
      response.json(result);
    })
  );

  router.post(
    "/approvals/:approvalId/reject",
    asyncRoute(async (request, response) => {
      const parsed = decisionSchema.parse(request.body);
      const approvalId = String(request.params.approvalId);
      const pendingApproval = await services.approvalService.getApproval(approvalId);
      const result = await services.assistantOrchestrator.rejectApproval(
        approvalId,
        parsed.approverId
      );
      if (pendingApproval.requestContext.channel === "whatsapp") {
        await services.whatsappService.sendTextMessage(
          pendingApproval.requestedBy,
          "La solicitud fue revisada y rechazada por un operador."
        );
      }
      response.json({
        approval: result
      });
    })
  );

  return router;
}
