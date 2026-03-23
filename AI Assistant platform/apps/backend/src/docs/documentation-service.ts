import fs from "node:fs/promises";
import crypto from "node:crypto";
import path from "node:path";

import {
  AssistantTarget,
  DocumentDescriptor,
  RequiredDocumentKey,
  SharedDocumentKey
} from "@vlv-ai/shared";

import { paths } from "../config/paths";
import { RuntimeConfigService } from "../config/runtime-config-service";

type DocumentKey = RequiredDocumentKey | SharedDocumentKey;

const REQUIRED_DOCUMENTS: Record<RequiredDocumentKey, string> = {
  business_rules: "business_rules.md",
  stored_procedures: "stored_procedures.md",
  assistant_behavior: "assistant_behavior.md",
  permissions: "permissions.md",
  company_context: "company_context.md"
};

const SHARED_DOCUMENTS: Record<SharedDocumentKey, string> = {
  platform_overview: "platform_overview.md",
  target_routing: "target_routing.md"
};

interface DocumentCacheEntry {
  descriptor: DocumentDescriptor;
  loadedAt: number;
}

export interface PromptBundle {
  text: string;
  documents: DocumentDescriptor[];
  totalChars: number;
  totalBytes: number;
}

export class DocumentationService {
  private cache = new Map<string, DocumentCacheEntry>();

  constructor(private readonly runtimeConfigService: RuntimeConfigService) {}

  async listDocuments(target: AssistantTarget, includeContent = false): Promise<DocumentDescriptor[]> {
    const sharedEntries = await Promise.all(
      (Object.keys(SHARED_DOCUMENTS) as SharedDocumentKey[]).map((documentKey) =>
        this.readDocument("shared", documentKey, SHARED_DOCUMENTS[documentKey], includeContent)
      )
    );
    const domainEntries = await Promise.all(
      (Object.keys(REQUIRED_DOCUMENTS) as RequiredDocumentKey[]).map((documentKey) =>
        this.readDocument(target, documentKey, REQUIRED_DOCUMENTS[documentKey], includeContent)
      )
    );

    return [...sharedEntries, ...domainEntries];
  }

  async buildPromptBundle(target: AssistantTarget): Promise<string> {
    const bundle = await this.buildBundle(target, "final_response");
    return bundle.text;
  }

  async buildBundle(
    target: AssistantTarget,
    purpose: "proposal" | "final_response",
    options?: {
      maxDocs?: number;
      maxBytes?: number;
    }
  ): Promise<PromptBundle> {
    const documents = await this.listDocuments(target, true);
    const runtimeContext = await this.runtimeConfigService.getSanitizedConfig();
    const targetConfig = runtimeContext?.domains[target];
    const preferredKeys =
      purpose === "proposal"
        ? ([
            "platform_overview",
            "target_routing",
            "assistant_behavior",
            "business_rules"
          ] as DocumentKey[])
        : ([
            "platform_overview",
            "target_routing",
            "assistant_behavior",
            "business_rules",
            "stored_procedures",
            "company_context"
          ] as DocumentKey[]);

    const selected = preferredKeys
      .map((key) => documents.find((document) => document.key === key))
      .filter((document): document is DocumentDescriptor => Boolean(document?.exists && document.content));

    const limitedByCount =
      typeof options?.maxDocs === "number" ? selected.slice(0, options.maxDocs) : selected;
    const limitedByBytes: DocumentDescriptor[] = [];
    let consumedBytes = 0;

    for (const document of limitedByCount) {
      const documentText = `## ${document.title}\n\n${document.content ?? ""}`;
      const documentBytes = Buffer.byteLength(documentText, "utf8");
      if (typeof options?.maxBytes === "number" && consumedBytes + documentBytes > options.maxBytes) {
        break;
      }

      limitedByBytes.push(document);
      consumedBytes += documentBytes;
    }

    const documentBundle = limitedByBytes
      .map((document) => `## ${document.title}\n\n${document.content ?? ""}`)
      .join("\n\n");
    const runtimeBundle = this.buildRuntimeBundle(runtimeContext, target, targetConfig, purpose);
    const text = [documentBundle, runtimeBundle].filter(Boolean).join("\n\n");

    return {
      text,
      documents: limitedByBytes,
      totalChars: text.length,
      totalBytes: Buffer.byteLength(text, "utf8")
    };
  }

  private async readDocument(
    target: AssistantTarget | "shared",
    documentKey: DocumentKey,
    fileName: string,
    includeContent: boolean
  ): Promise<DocumentDescriptor> {
    const directory = await this.getDocsDirectory(target);
    const filePath = path.join(directory, fileName);
    const cacheKey = `${target}:${documentKey}:${includeContent ? "content" : "meta"}`;

    try {
      const stat = await fs.stat(filePath);
      const content = includeContent ? await fs.readFile(filePath, "utf8") : undefined;
      const descriptor: DocumentDescriptor = {
        key: documentKey,
        title: this.toTitle(fileName),
        path: filePath,
        exists: true,
        size: stat.size,
        target,
        lastModifiedAt: stat.mtime.toISOString(),
        content,
        contentHash: content ? crypto.createHash("sha256").update(content).digest("hex") : undefined
      };

      this.cache.set(cacheKey, {
        descriptor,
        loadedAt: Date.now()
      });

      return descriptor;
    } catch {
      return {
        key: documentKey,
        title: this.toTitle(fileName),
        path: filePath,
        exists: false,
        size: 0,
        target
      };
    }
  }

  private async getDocsDirectory(target: AssistantTarget | "shared"): Promise<string> {
    if (target === "shared") {
      return paths.sharedDocsDirectory;
    }

    const config = await this.runtimeConfigService.getSanitizedConfig();
    return config?.domains[target].docsDirectory ?? path.join(paths.domainDocsDirectory, target);
  }

  private toTitle(fileName: string): string {
    return fileName
      .replace(".md", "")
      .split("_")
      .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
      .join(" ");
  }

  private buildRuntimeBundle(
    runtimeContext: Awaited<ReturnType<RuntimeConfigService["getSanitizedConfig"]>>,
    target: AssistantTarget,
    targetConfig: { enabled: boolean; assistant: { companyCode: string; defaultLocale: string; defaultPropertyCode?: string; defaultActorUserId: number } } | undefined,
    purpose: "proposal" | "final_response"
  ): string {
    if (!runtimeContext || !targetConfig) {
      return "";
    }

    return [
      "## Runtime Assistant Context",
      `Bundle purpose: ${purpose}`,
      `Tenant ID: ${runtimeContext.tenantId}`,
      `Target domain: ${target}`,
      `Default target: ${runtimeContext.defaultTarget}`,
      `Domain enabled: ${targetConfig.enabled ? "yes" : "no"}`,
      `Company code: ${targetConfig.assistant.companyCode}`,
      `Default locale: ${targetConfig.assistant.defaultLocale}`,
      `Default property scope: ${targetConfig.assistant.defaultPropertyCode ?? "none"}`,
      `Default actor user id: ${targetConfig.assistant.defaultActorUserId}`,
      `Execution mode: ${runtimeContext.execution.mode}`,
      `Writes enabled: ${runtimeContext.execution.enableWrites ? "yes" : "no"}`,
      `Cheap mode enabled: ${runtimeContext.optimization.cheapModeEnabled ? "yes" : "no"}`,
      `Broad Brain snapshots disabled: ${runtimeContext.optimization.disableBroadBrainSnapshots ? "yes" : "no"}`
    ].join("\n");
  }
}
