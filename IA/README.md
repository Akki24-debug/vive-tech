# Vive Tech AI

Repositorio del ecosistema de IA de Vive La Vibe.

## Contiene

- `ai-assistant-platform/`
- `GPTS/`
- `docs/pms-context/`

## Objetivo

Este repo concentra:

- servidor Node del asistente
- integracion OpenAI y WhatsApp
- prompts y reglas operativas
- contexto documental minimo del PMS que la IA necesita para operar

## Relacion con el PMS

La IA no pierde contexto por estar en otro repo.

El contexto se conserva mediante:

- documentacion copiada o sincronizada en `docs/pms-context/`
- contratos claros de acciones y SPs
- docs del backend en `ai-assistant-platform/docs/`

## No contiene

- el PMS PHP completo
- el sitio web publico

## Regla practica

Cuando cambies SPs o reglas del PMS que afecten a la IA, actualiza tambien:

- `docs/pms-context/`
- `ai-assistant-platform/docs/`
