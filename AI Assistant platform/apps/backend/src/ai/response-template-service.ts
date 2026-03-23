import { ActionExecutionResult } from "../actions/action-execution-service";

function asRows(result: ActionExecutionResult): Record<string, unknown>[] {
  const data = result.data as { rows?: Record<string, unknown>[] } | undefined;
  return Array.isArray(data?.rows) ? data.rows : [];
}

function summarizeRows(rows: Record<string, unknown>[], fields: string[]): string[] {
  return rows.slice(0, 8).map((row) =>
    fields
      .map((field) => row[field])
      .filter((value) => value !== null && value !== undefined && value !== "")
      .join(" | ")
  );
}

export class ResponseTemplateService {
  canTemplate(actionName: string): boolean {
    return [
      "brain.organization.lookup",
      "brain.business_area.lookup",
      "brain.business_line.lookup",
      "brain.business_priority.lookup",
      "brain.objective.lookup",
      "brain.external_system.lookup",
      "brain.knowledge_document.lookup"
    ].includes(actionName);
  }

  render(actionName: string, result: ActionExecutionResult, userMessage?: string): string {
    switch (actionName) {
      case "brain.organization.lookup":
        return this.renderOrganization(result, userMessage);
      case "brain.business_area.lookup":
        return this.renderList("Areas de negocio", result, ["name", "status", "priority_level"]);
      case "brain.business_line.lookup":
        return this.renderList("Lineas de negocio", result, ["name", "status", "owner_name"]);
      case "brain.business_priority.lookup":
        return this.renderList("Prioridades", result, ["title", "status", "scope_type"]);
      case "brain.objective.lookup":
        return this.renderList("Objetivos", result, ["title", "status", "objective_type"]);
      case "brain.external_system.lookup":
        return this.renderList("Sistemas externos", result, ["name", "system_type", "status"]);
      case "brain.knowledge_document.lookup":
        return this.renderList("Documentos base", result, ["title", "document_type", "status"]);
      default:
        return "No se genero una plantilla para esta respuesta.";
    }
  }

  private renderOrganization(result: ActionExecutionResult, userMessage?: string): string {
    const rows = asRows(result);
    const first = rows[0];
    const normalizedMessage = (userMessage ?? "").toLowerCase();

    if (!first) {
      return "No encontre un perfil de organizacion registrado en el Business Brain.";
    }

    if (/vision|vision_summary|mision|proposito/.test(normalizedMessage)) {
      return first.vision_summary
        ? `Vision registrada de Vive la Vibe:\n- ${String(first.vision_summary)}`
        : "No hay una vision registrada todavia en el Business Brain.";
    }

    if (/estado actual|estatus actual|status actual|etapa actual|fase actual/.test(normalizedMessage)) {
      return [
        "Estado actual de Vive la Vibe:",
        `- Estado: ${String(first.status ?? "sin estado")}`,
        `- Etapa: ${String(first.current_stage ?? "sin etapa")}`
      ].join("\n");
    }

    const lines = [
      "Perfil base de Vive la Vibe:",
      `- Nombre: ${String(first.name ?? "sin nombre")}`,
      `- Pais: ${String(first.country ?? "sin pais")}`,
      `- Estado: ${String(first.status ?? "sin estado")}`
    ];

    if (first.current_stage) {
      lines.push(`- Etapa actual: ${String(first.current_stage)}`);
    }

    if (first.vision_summary) {
      lines.push(`- Vision registrada: ${String(first.vision_summary)}`);
    }

    lines.push(
      "- Contexto disponible hoy: areas, lineas de negocio, prioridades, objetivo estrategico, sistemas externos y documentos base."
    );

    return lines.join("\n");
  }

  private renderList(title: string, result: ActionExecutionResult, fields: string[]): string {
    const rows = asRows(result);
    if (rows.length === 0) {
      return `No encontre registros para ${title.toLowerCase()} en el Business Brain.`;
    }

    const lines = [
      `${title}: ${rows.length} registro(s).`,
      ...summarizeRows(rows, fields).map((line) => `- ${line}`)
    ];

    if (rows.length > 8) {
      lines.push(`- Hay ${rows.length - 8} registros adicionales no mostrados en este resumen.`);
    }

    return lines.join("\n");
  }
}
