import path from "node:path";

import { ConversationMessage, ConversationRecord } from "@vlv-ai/shared";

import { paths } from "../config/paths";
import { createId } from "../shared/ids";
import { readJsonFile, writeJsonFile } from "../shared/json-file";
import { nowIso } from "../shared/time";

export class ConversationStore {
  private readonly maxMessages = 12;

  async getConversation(conversationId: string): Promise<ConversationRecord | null> {
    const record = await readJsonFile<ConversationRecord | null>(this.getFilePath(conversationId), null);
    return record ? this.normalizeRecord(record) : null;
  }

  async listRecent(limit = 20): Promise<ConversationRecord[]> {
    try {
      const fs = await import("node:fs/promises");
      const entries = await fs.readdir(paths.conversationsDirectory);
      const records = await Promise.all(
        entries
          .filter((entry) => entry.endsWith(".json"))
          .map((entry) =>
            readJsonFile<ConversationRecord | null>(
              path.join(paths.conversationsDirectory, entry),
              null
            )
          )
      );

      return records
        .filter(Boolean)
        .map((record) => this.normalizeRecord(record!))
        .sort((left: ConversationRecord | null, right: ConversationRecord | null) =>
          right!.updatedAt.localeCompare(left!.updatedAt)
        )
        .slice(0, limit) as ConversationRecord[];
    } catch {
      return [];
    }
  }

  async getOrCreate(
    tenantId: string,
    target: ConversationRecord["target"],
    conversationId: string,
    channel: ConversationRecord["channel"],
    userId: string
  ): Promise<ConversationRecord> {
    const filePath = this.getFilePath(conversationId);
    const existing = await readJsonFile<ConversationRecord | null>(filePath, null);

    if (existing) {
      return this.normalizeRecord(existing);
    }

    const record: ConversationRecord = {
      id: conversationId,
      tenantId,
      target,
      channel,
      userId,
      summary: "",
      messages: [],
      context: {},
      updatedAt: nowIso()
    };

    await writeJsonFile(filePath, record);
    return record;
  }

  async appendMessage(
    tenantId: string,
    target: ConversationRecord["target"],
    conversationId: string,
    channel: ConversationRecord["channel"],
    userId: string,
    role: ConversationMessage["role"],
    content: string
  ): Promise<ConversationRecord> {
    const record = await this.getOrCreate(tenantId, target, conversationId, channel, userId);
    const updatedMessages = [
      ...record.messages,
      {
        id: createId("msg"),
        role,
        content,
        createdAt: nowIso()
      }
    ];

    const trimmed = updatedMessages.slice(-this.maxMessages);
    const summary = updatedMessages.length > this.maxMessages ? this.summarize(trimmed) : record.summary;

    const nextRecord: ConversationRecord = {
      ...record,
      target,
      messages: trimmed,
      summary,
      updatedAt: nowIso()
    };

    await writeJsonFile(this.getFilePath(conversationId), nextRecord);
    return nextRecord;
  }

  private summarize(messages: ConversationMessage[]): string {
    return messages
      .slice(0, 4)
      .map((message) => `${message.role}: ${message.content}`)
      .join(" | ");
  }

  private getFilePath(conversationId: string): string {
    return path.join(paths.conversationsDirectory, `${conversationId}.json`);
  }

  private normalizeRecord(record: ConversationRecord): ConversationRecord {
    return {
      ...record,
      target: record.target ?? "pms"
    };
  }
}
