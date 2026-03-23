# Vive Tech PMS

Repositorio principal del PMS de Vive La Vibe.

## Contiene

- `public_html/`
- `../bd/`
- `migrations/`
- playbooks y mapas operativos del PMS
- automatizaciones ligadas directamente al PMS como `run local/paste_calendar_values.ahk`

## No contiene

- servidor Node del asistente de IA
- prompts y GPT configs como proyecto principal
- sitio web publico

## Relacion con otros repos

- `AI Assistant platform/` consume contexto del PMS y llama SPs del PMS
- `Core de Operaciones/pagina de vive la vibe/` consume datos de la misma BD y endpoints ligados al PMS
- `tools/` contiene utilidades generales de trabajo

## Nota

Que otros sistemas lean la misma BD no significa que deban vivir en el mismo repo. Este repo existe para concentrar la logica, SQL, PHP y documentacion del PMS.

## Levantar local

Lee:

- [LOCAL_SETUP.md](C:\Users\ragnarok\Documents\repos\Proyecto%20VLV\Core%20de%20Operaciones\PMS\LOCAL_SETUP.md)
