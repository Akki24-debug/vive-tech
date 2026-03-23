import { ActionProposal, AssistantRequest } from "@vlv-ai/shared";

import { ResolvedCostControl } from "./cost-control-service";

const BROAD_SNAPSHOT_PATTERNS = [
  /whole picture/i,
  /resumen completo/i,
  /snapshot general/i,
  /panorama completo/i,
  /todo el contexto/i,
  /whole context/i
];

function isExplicitBroadSnapshot(message: string): boolean {
  return BROAD_SNAPSHOT_PATTERNS.some((pattern) => pattern.test(message));
}

function buildLookupProposal(
  action: string,
  intent: string,
  summary: string
): ActionProposal {
  return {
    intent,
    confidence: 0.82,
    action,
    arguments: {
      onlyActive: true,
      limit: 25
    },
    summary,
    needsHumanApproval: false
  };
}

export interface CheapRouteDecision {
  proposal: ActionProposal;
  reason: string;
}

export function routeBrainCheapIntent(
  request: AssistantRequest,
  control: ResolvedCostControl,
  isBroadReadBlocked: boolean
): CheapRouteDecision | null {
  if (request.target !== "business_brain") {
    return null;
  }

  const message = request.message.trim();
  const normalized = message.toLowerCase();
  const explicitBroadSnapshot = isExplicitBroadSnapshot(message);

  if (explicitBroadSnapshot) {
    if (control.disableBroadBrainSnapshots || isBroadReadBlocked) {
      return {
        reason: control.disableBroadBrainSnapshots
          ? "cheap_mode_blocked_broad_snapshot"
          : "domain_health_blocked_broad_snapshot",
        proposal: {
          intent: "Solicitar panorama amplio del Business Brain",
          confidence: 0.92,
          action: "conversation.clarify",
          arguments: {
            question:
              "El snapshot completo esta bloqueado temporalmente en modo barato o durante inestabilidad. Pideme una vista especifica: empresa, vision, areas, lineas, prioridades, objetivos, sistemas o documentos."
          },
          summary:
            "Bloqueado el snapshot amplio; se pidio redirigir a una lectura mas especifica.",
          needsHumanApproval: false
        }
      };
    }

    return {
      reason: "explicit_broad_snapshot_allowed",
      proposal: {
        intent: "Obtener panorama amplio del Business Brain",
        confidence: 0.9,
        action: "brain.current_context",
        arguments: {
          onlyActive: true,
          limit: 50
        },
        summary: "Se solicito un panorama amplio del Business Brain.",
        needsHumanApproval: false
      }
    };
  }

  if (/proyectos|tasks?|tareas|meetings?|reuniones|reservations?|crm/i.test(normalized)) {
    return {
      reason: "domain_limitation_projects_not_seeded",
      proposal: {
        intent: "Aclarar limite del Business Brain actual",
        confidence: 0.9,
        action: "conversation.clarify",
        arguments: {
          question:
            "El Business Brain actual todavia no es un sistema completo de proyectos o tareas. Puedo mostrarte empresa, vision, areas, lineas, prioridades, objetivos, sistemas y documentos base. Que vista de esas necesitas?"
        },
        summary:
          "Se detecto una pregunta fuera del alcance sembrado del Business Brain y se devolvio una aclaracion guiada.",
        needsHumanApproval: false
      }
    };
  }

  if (/edita|editar|editala|actualiza|actualizar|cambia|cambiar|modifica|modificar|quiero que diga|quiero que diga:|quiero cambiar/i.test(normalized)) {
    if (/vision|mision|descripcion|empresa|organizacion|vive la vibe/i.test(normalized)) {
      return {
        reason: "write_intent_requires_explicit_update_flow",
        proposal: {
          intent: "Detectar una solicitud de actualizacion sobre el perfil de la organizacion",
          confidence: 0.94,
          action: "conversation.clarify",
          arguments: {
            question:
              "Detecte una solicitud de edicion del perfil de Vive la Vibe. Todavia no estoy ejecutando cambios parciales de organizacion de forma automatica desde este flujo barato. Indica exactamente que campo quieres cambiar: vision, descripcion o ambos; o haz el cambio desde Brain Control."
          },
          summary:
            "Se detecto una intencion de edicion y se bloqueo el fallback a lectura para evitar responder como si fuera solo consulta.",
          needsHumanApproval: false
        }
      };
    }
  }

  if (
    /vision|vision_summary|mision|proposito|proposito estrategico|razon de ser|vision de la organizacion|vision de la empresa/i.test(
      normalized
    )
  ) {
    return {
      reason: "narrow_route_organization_vision",
      proposal: buildLookupProposal(
        "brain.organization.lookup",
        "Obtener la vision registrada de Vive la Vibe",
        "Se selecciono la lectura de organizacion para responder sobre la vision."
      )
    };
  }

  if (/estado actual|estatus actual|status actual|etapa actual|fase actual/i.test(normalized)) {
    return {
      reason: "narrow_route_organization_status",
      proposal: buildLookupProposal(
        "brain.organization.lookup",
        "Obtener el estado actual de Vive la Vibe",
        "Se selecciono la lectura de organizacion para responder sobre estado y etapa."
      )
    };
  }

  if (/estructura|reparto de trabajo|areas?|departamentos?|equipos?/i.test(normalized)) {
    return {
      reason: "narrow_route_business_areas",
      proposal: buildLookupProposal(
        "brain.business_area.lookup",
        "Obtener la estructura por areas del Business Brain",
        "Se selecciono la lectura de areas de negocio."
      )
    };
  }

  if (/lineas?|frentes|oferta|ofertas|business lines?/i.test(normalized)) {
    return {
      reason: "narrow_route_business_lines",
      proposal: buildLookupProposal(
        "brain.business_line.lookup",
        "Obtener lineas de negocio activas",
        "Se selecciono la lectura de lineas de negocio."
      )
    };
  }

  if (/prioridades?|foco actual|enfoque|focus/i.test(normalized)) {
    return {
      reason: "narrow_route_priorities",
      proposal: buildLookupProposal(
        "brain.business_priority.lookup",
        "Obtener prioridades activas",
        "Se selecciono la lectura de prioridades."
      )
    };
  }

  if (/objetivos?|metas?|targets?|estrategic/i.test(normalized)) {
    return {
      reason: "narrow_route_objectives",
      proposal: buildLookupProposal(
        "brain.objective.lookup",
        "Obtener objetivos estrategicos",
        "Se selecciono la lectura de objetivos."
      )
    };
  }

  if (/sistemas?|herramientas?|tools?|integraciones?|pms/i.test(normalized)) {
    return {
      reason: "narrow_route_external_systems",
      proposal: buildLookupProposal(
        "brain.external_system.lookup",
        "Obtener sistemas externos registrados",
        "Se selecciono la lectura de sistemas externos."
      )
    };
  }

  if (/documentos?|docs?|fuentes?|memoria|knowledge|schema|sp|stored procedures?/i.test(normalized)) {
    return {
      reason: "narrow_route_foundational_docs",
      proposal: buildLookupProposal(
        "brain.knowledge_document.lookup",
        "Obtener documentos base del Business Brain",
        "Se selecciono la lectura de documentos de conocimiento."
      )
    };
  }

  if (/empresa|compania|vive la vibe|organizacion|que es|sobre la empresa|about the company/i.test(normalized)) {
    return {
      reason: "narrow_route_company_summary",
      proposal: buildLookupProposal(
        "brain.organization.lookup",
        "Obtener el perfil base de Vive la Vibe",
        "Se selecciono la lectura de organizacion para responder sobre la empresa."
      )
    };
  }

  return null;
}
