# Scripts locales del PMS

## Objetivo

Estos scripts existen para ayudarte a levantar y probar el PMS fuera de produccion.

## Scripts

### `activate-local-db-tools.ps1`

Agrega `MariaDB\bin` al `PATH` de la sesion actual.

Uso:

```powershell
.\scripts\activate-local-db-tools.ps1
```

### `create-local-db.ps1`

Crea una base local vacia con `utf8mb4`.

Uso:

```powershell
.\scripts\create-local-db.ps1
```

Ejemplo:

```powershell
.\scripts\create-local-db.ps1 -Database vlv_pms_local -User root
```

### `import-local-dump.ps1`

Importa un dump SQL dentro de la base local.

Uso:

```powershell
.\scripts\import-local-dump.ps1 -DumpPath .\local-dumps\produccion.sql
```

### `check-local-prereqs.ps1`

Valida prerrequisitos de entorno local.

### `start-local-pms.ps1`

Levanta el PMS local con el servidor embebido de PHP.
