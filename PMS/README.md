# Vive Tech PMS

Repositorio principal del PMS de Vive La Vibe.

## Contiene

- `public_html/`
- `bd pms/`
- `migrations/`
- playbooks y mapas operativos del PMS
- automatizaciones ligadas directamente al PMS como `paste_calendar_values.ahk`

## No contiene

- servidor Node del asistente de IA
- prompts y GPT configs como proyecto principal
- sitio web publico

## Relacion con otros repos

- `vive-tech-ai` consume contexto del PMS y llama SPs del PMS
- `vive-tech-website` consume datos de la misma BD y endpoints ligados al PMS
- `vive-tech-tools` contiene utilidades generales de trabajo

## Nota

Que otros sistemas lean la misma BD no significa que deban vivir en el mismo repo. Este repo existe para concentrar la logica, SQL, PHP y documentacion del PMS.

## Levantar local

Lee:

- [LOCAL_SETUP.md](C:\Users\ragnarok\Documents\repos\vive-tech-pms\LOCAL_SETUP.md)
