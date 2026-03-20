# Tools locales

Este folder contiene herramientas instaladas en modo portable para no depender de una instalacion global de Windows.

## Activar en la sesion actual

```powershell
. .\activate-tools.ps1
```

## Tools incluidas

- `php`
- `composer`
- `ngrok`

## Notas

- Estas herramientas viven solo dentro del repo.
- Si quieres usarlas por nombre corto (`php`, `composer`, `ngrok`) en una terminal nueva, activa primero `activate-tools.ps1`.
- Esto no sustituye una instalacion global de MariaDB CLI, AutoHotkey o OpenSSL si luego los necesitas desde cualquier carpeta del sistema.
