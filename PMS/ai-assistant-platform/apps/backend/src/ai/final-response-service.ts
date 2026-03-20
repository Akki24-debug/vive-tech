import { AssistantRequest } from "@vlv-ai/shared";

import { DocumentationService } from "../docs/documentation-service";
import { OpenAIClientFactory } from "./openai-client";

export class FinalResponseService {
  constructor(
    private readonly openAIClientFactory: OpenAIClientFactory,
    private readonly documentationService: DocumentationService
  ) {}

  async createFinalAnswer(
    request: AssistantRequest,
    actionName: string,
    backendResult: unknown
  ): Promise<string> {
    const client = await this.openAIClientFactory.createClient();
    const model = await this.openAIClientFactory.getModel();
    const docsBundle = await this.documentationService.buildPromptBundle();

    const response = await client.responses.create({
      model,
      input: [
        {
          role: "system",
          content: [
            {
              type: "input_text",
              text: [
                "You are the response layer for a property management assistant.",
                "Answer only with information supported by the backend result.",
                "Do not expose internal procedure names or technical implementation details unless asked.",
                "If the result is empty, say that no matching information was found.",
                "Respond in Spanish unless the request clearly asked for another language.",
                "If the backend result contains quote or pricing data, answer in a Markdown table and always include a total or estimated total.",
                "When describing prices, prefer the labels 'precio normal' and 'precio especial con descuento por pagar en mostrador'.",
                "When describing payments, use the explicit payments block from the backend result and do not confuse payments with sale items.",
                "",
                "## Documentation Context",
                docsBundle
              ].join("\n")
            }
          ]
        },
        {
          role: "user",
          content: [
            {
              type: "input_text",
              text: [
                `Latest user message: ${request.message}`,
                `Approved action: ${actionName}`,
                `Backend result: ${JSON.stringify(backendResult, null, 2)}`
              ].join("\n\n")
            }
          ]
        }
      ]
    });

    return response.output_text?.trim() || "No response was generated.";
  }
}
