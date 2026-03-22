import fs from "node:fs/promises";
import path from "node:path";

import {
  AssistantTarget,
  DocumentDescriptor,
  REQUIRED_DOCUMENTS,
  RequiredDocumentKey,
  SHARED_DOCUMENTS,
  SharedDocumentKey
} from "@vlv-ai/shared";

import { paths } from "../config/paths";
import { RuntimeConfigService } from "../config/runtime-config-service";

type DocumentKey = RequiredDocumentKey | SharedDocumentKey;

interface DocumentCacheEntry {
  descriptor: DocumentDescriptor;
  loadedAt: number;
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
    const documents = await this.listDocuments(target, true);
    const runtimeContext = await this.runtimeConfigService.getSanitizedConfig();
    const targetConfig = runtimeContext?.domains[target];

    const documentBundle = documents
      .filter((document) => document.exists && document.content)
      .map((document) => `## ${document.title}\n\n${document.content ?? ""}`)
      .join("\n\n");

    const runtimeBundle =
      runtimeContext && targetConfig
        ? [
            "## Runtime Assistant Context",
            `Tenant ID: ${runtimeContext.tenantId}`,
            `Target domain: ${target}`,
            `Default target: ${runtimeContext.defaultTarget}`,
            `Domain enabled: ${targetConfig.enabled ? "yes" : "no"}`,
            `Company code: ${targetConfig.assistant.companyCode}`,
            `Default locale: ${targetConfig.assistant.defaultLocale}`,
            `Default property scope: ${targetConfig.assistant.defaultPropertyCode ?? "none"}`,
            `Default actor user id: ${targetConfig.assistant.defaultActorUserId}`,
            `Execution mode: ${runtimeContext.execution.mode}`,
            `Writes enabled: ${runtimeContext.execution.enableWrites ? "yes" : "no"}`
          ].join("\n")
        : "";

    return [documentBundle, runtimeBundle].filter(Boolean).join("\n\n");
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
        content
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
}
