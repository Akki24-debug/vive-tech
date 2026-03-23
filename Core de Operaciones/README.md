# Core de Operaciones

Este bloque concentra los sistemas operativos centrales de Vive La Vibe.

## Contiene

- `PMS/`: aplicacion PHP, playbooks, setup local y codigo operativo.
- `bd/`: SQL runtime, schema, stored procedures y consultas auxiliares del PMS.
- `pagina de vive la vibe/`: sitio publico y endpoints web ligados a la operacion.

## Regla practica

Si un cambio toca UI o PHP del PMS, revisar tambien:

- `PMS/`
- `bd/`
- `pagina de vive la vibe/`
