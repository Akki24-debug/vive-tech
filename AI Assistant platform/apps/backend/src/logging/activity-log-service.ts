import { AssistantTarget, LogEvent } from "@vlv-ai/shared";

import { paths } from "../config/paths";
import { createId } from "../shared/ids";
import { appendJsonLine } from "../shared/json-file";
import { nowIso } from "../shared/time";

export class ActivityLogService {
  async log(
    level: "info" | "warn" | "error",
    type: string,
    message: string,
    payload?: unknown,
    target?: AssistantTarget | "shared"
  ): Promise<void> {
    const event: LogEvent = {
      id: createId("log"),
      level,
      type,
      message,
      target,
      payload,
      timestamp: nowIso()
    };

    await appendJsonLine(paths.logsFile, event);

    const writer = level === "error" ? console.error : level === "warn" ? console.warn : console.log;
    writer(`[${event.timestamp}] ${level.toUpperCase()} ${type}: ${message}`);
  }

  async info(
    type: string,
    message: string,
    payload?: unknown,
    target?: AssistantTarget | "shared"
  ): Promise<void> {
    await this.log("info", type, message, payload, target);
  }

  async warn(
    type: string,
    message: string,
    payload?: unknown,
    target?: AssistantTarget | "shared"
  ): Promise<void> {
    await this.log("warn", type, message, payload, target);
  }

  async error(
    type: string,
    message: string,
    payload?: unknown,
    target?: AssistantTarget | "shared"
  ): Promise<void> {
    await this.log("error", type, message, payload, target);
  }

  async listRecent(limit = 100): Promise<LogEvent[]> {
    try {
      const fs = await import("node:fs/promises");
      const content = await fs.readFile(paths.logsFile, "utf8");
      return content
        .trim()
        .split("\n")
        .filter(Boolean)
        .slice(-limit)
        .map((line) => JSON.parse(line) as LogEvent)
        .reverse();
    } catch {
      return [];
    }
  }
}
