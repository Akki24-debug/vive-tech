import fs from "node:fs/promises";
import path from "node:path";

import { DocumentDescriptor, REQUIRED_DOCUMENTS, RequiredDocumentKey } from "@vlv-ai/shared";

import { paths } from "../config/paths";
import { RuntimeConfigService } from "../config/runtime-config-service";

interface DocumentCacheEntry {
  descriptor: DocumentDescriptor;
  loadedAt: number;
}

export class DocumentationService {
  private cache = new Map<RequiredDocumentKey, DocumentCacheEntry>();

  constructor(private readonly runtimeConfigService: RuntimeConfigService) {}

  async listDocuments(includeContent = false): Promise<DocumentDescriptor[]> {
    const docsDirectory = await this.getDocsDirectory();
    const entries = await Promise.all(
      (Object.keys(REQUIRED_DOCUMENTS) as RequiredDocumentKey[]).map(async (documentKey) => {
        const fileName = REQUIRED_DOCUMENTS[documentKey];
        const filePath = path.join(docsDirectory, fileName);

        try {
          const stat = await fs.stat(filePath);
          const content = includeContent ? await fs.readFile(filePath, "utf8") : undefined;
          const descriptor: DocumentDescriptor = {
            key: documentKey,
            title: this.toTitle(fileName),
            path: filePath,
            exists: true,
            size: stat.size,
            lastModifiedAt: stat.mtime.toISOString(),
            content
          };

          this.cache.set(documentKey, {
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
            size: 0
          } satisfies DocumentDescriptor;
        }
      })
    );

    return entries;
  }

  async buildPromptBundle(): Promise<string> {
    const documents = await this.listDocuments(true);
    const runtimeContext = await this.runtimeConfigService.getSanitizedConfig();

    const documentBundle = documents
      .filter((document) => document.exists && document.content)
      .map((document) => {
        return `## ${document.title}\n\n${document.content ?? ""}`;
      })
      .join("\n\n");

    const runtimeBundle = runtimeContext
      ? [
          "## Runtime Assistant Context",
          `Tenant ID: ${runtimeContext.tenantId}`,
          `Company code: ${runtimeContext.assistant.companyCode}`,
          `Default locale: ${runtimeContext.assistant.defaultLocale}`,
          `Default property scope: ${runtimeContext.assistant.defaultPropertyCode ?? "none"}`,
          `Default PMS actor user id: ${runtimeContext.assistant.defaultActorUserId}`,
          `Execution mode: ${runtimeContext.execution.mode}`,
          `Writes enabled: ${runtimeContext.execution.enableWrites ? "yes" : "no"}`
        ].join("\n")
      : "";

    return [documentBundle, runtimeBundle].filter(Boolean).join("\n\n");
  }

  private async getDocsDirectory(): Promise<string> {
    const config = await this.runtimeConfigService.getSanitizedConfig();
    return config?.docsDirectory ?? paths.docsDirectory;
  }

  private toTitle(fileName: string): string {
    return fileName
      .replace(".md", "")
      .split("_")
      .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
      .join(" ");
  }
}
