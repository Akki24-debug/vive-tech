import cors from "cors";
import express, { NextFunction, Request, Response } from "express";

import { createApprovalRoutes } from "./api/routes/approval-routes";
import { createAssistantRoutes } from "./api/routes/assistant-routes";
import { createConfigRoutes } from "./api/routes/config-routes";
import { createConversationRoutes } from "./api/routes/conversation-routes";
import { createDocsRoutes } from "./api/routes/docs-routes";
import { createHealthRoutes } from "./api/routes/health-routes";
import { createLogRoutes } from "./api/routes/log-routes";
import { createWhatsAppRoutes } from "./api/routes/whatsapp-routes";
import { AppError } from "./shared/errors";
import { ApplicationServices } from "./shared/service-container";

export function createApp(services: ApplicationServices) {
  const app = express();

  app.use(cors());
  app.use(express.json({ limit: "1mb" }));

  app.use("/api", createHealthRoutes(services));
  app.use("/api", createConfigRoutes(services));
  app.use("/api", createDocsRoutes(services));
  app.use("/api", createAssistantRoutes(services));
  app.use("/api", createConversationRoutes(services));
  app.use("/api", createApprovalRoutes(services));
  app.use("/api", createLogRoutes(services));
  app.use("/api", createWhatsAppRoutes(services));

  app.use((error: unknown, _request: Request, response: Response, _next: NextFunction) => {
    if (error instanceof AppError) {
      response.status(error.statusCode).json({
        error: {
          code: error.code,
          message: error.message,
          details: error.details
        }
      });
      return;
    }

    const typed = error instanceof Error ? error : new Error("Unknown error");
    void services.activityLogService.error(
      "app.unhandled_error",
      "Unhandled backend error.",
      {
        message: typed.message,
        stack: typed.stack
      },
      "shared"
    );
    response.status(500).json({
      error: {
        code: "UNHANDLED_ERROR",
        message: typed.message
      }
    });
  });

  return app;
}
