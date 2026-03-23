# PMS Local Setup

## Objetivo

Dejar de probar directamente en produccion y poder correr el PMS localmente con una base clonada o restaurada aparte.

## Estrategia recomendada

Trabaja con tres entornos:

- `production`: sistema real
- `staging`: copia operativa remota para validar antes de publicar
- `local`: tu maquina para desarrollo rapido

## Base tecnica ya existente

La conexion del PMS ya soporta dos cosas importantes:

1. `config.local.php`
2. overrides por variables de entorno

Eso significa que no tienes que reescribir el PMS para separar entornos.

## Archivos agregados para local

- [config.local.example.php](C:\Users\ragnarok\Documents\repos\Proyecto%20VLV\Core%20de%20Operaciones\PMS\public_html\pms%20db%20connections\config.local.example.php)
- [activate-local-db-tools.ps1](C:\Users\ragnarok\Documents\repos\Proyecto%20VLV\Core%20de%20Operaciones\PMS\run%20local\activate-local-db-tools.ps1)
- [create-local-db.ps1](C:\Users\ragnarok\Documents\repos\Proyecto%20VLV\Core%20de%20Operaciones\PMS\run%20local\create-local-db.ps1)
- [import-local-dump.ps1](C:\Users\ragnarok\Documents\repos\Proyecto%20VLV\Core%20de%20Operaciones\PMS\run%20local\import-local-dump.ps1)
- [start-local-pms.ps1](C:\Users\ragnarok\Documents\repos\Proyecto%20VLV\Core%20de%20Operaciones\PMS\run%20local\start-local-pms.ps1)
- [check-local-prereqs.ps1](C:\Users\ragnarok\Documents\repos\Proyecto%20VLV\Core%20de%20Operaciones\PMS\run%20local\check-local-prereqs.ps1)

## Paso 1: crear base local

Opcion recomendada:

- instala MariaDB o MySQL localmente
- crea una base nueva, por ejemplo:
  - `vlv_pms_local`

Con los scripts nuevos:

```powershell
cd "C:\Users\ragnarok\Documents\repos\Proyecto VLV\Core de Operaciones\PMS"
.\run local\activate-local-db-tools.ps1
.\run local\create-local-db.ps1 -Database vlv_pms_local -User root
```

## Paso 2: restaurar una copia de produccion

Lo ideal es usar:

- dump reciente de produccion
- o una copia sanitizada si quieres proteger datos sensibles

Puedes restaurar desde:

- `mysql` CLI
- HeidiSQL
- DBeaver
- phpMyAdmin

Si usas CLI, el flujo tipico es:

```sql
CREATE DATABASE vlv_pms_local CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Luego importas el dump o restauras desde una copia SQL.

Con script:

```powershell
.\run local\import-local-dump.ps1 -DumpPath ".\run local\local-dumps\tu_dump.sql" -Database vlv_pms_local -User root
```

## Paso 3: crear config.local.php

En esta ruta:

`public_html/pms db connections/config.local.php`

Puedes partir de:

`config.local.example.php`

Ejemplo:

```php
<?php
return [
    'host' => '127.0.0.1',
    'port' => 3306,
    'database' => 'vlv_pms_local',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
];
```

Ese archivo ya esta ignorado por Git.

## Paso 4: validar prerrequisitos

Desde PowerShell:

```powershell
cd "C:\Users\ragnarok\Documents\repos\Proyecto VLV\Core de Operaciones\PMS"
.\run local\check-local-prereqs.ps1
```

Esto verifica:

- PHP disponible
- presencia de `config.local.php`
- `mysql` CLI si existe
- prueba de conexion por PHP

## Paso 5: levantar el PMS local

Desde PowerShell:

```powershell
cd "C:\Users\ragnarok\Documents\repos\Proyecto VLV\Core de Operaciones\PMS"
.\run local\start-local-pms.ps1
```

Por default abre en:

- `http://127.0.0.1:8080`

## Entorno visible

Cuando el PMS corre con `PMS_APP_ENV=local`, la interfaz muestra claramente una banda de entorno para que no confundas local, staging y produccion.

Valores soportados:

- `local`
- `staging`
- `production`

## Flujo recomendado de trabajo

1. haces cambio en Git en una rama
2. pruebas en local
3. validas en staging
4. despliegas a produccion

## Herramientas utiles

Ya disponibles o parcialmente resueltas:

- PHP portable o en PATH
- Composer portable
- ngrok portable

Pendiente segun tu entorno:

- MariaDB/MySQL local
- dump reciente de produccion

## Carpeta sugerida para dumps locales

Usa:

- `run local/local-dumps/`

Esa carpeta se usa para guardar dumps locales fuera del flujo principal del proyecto.

## Nota importante sobre produccion

No sigas usando `config.php` de produccion como base para pruebas locales.

El uso correcto ahora es:

- produccion -> `config.php`
- local -> `config.local.php`

## Siguiente mejora recomendada

Despues de levantar local, el siguiente paso correcto es crear un entorno `staging` remoto con:

- BD separada
- subdominio separado
- banner visual `STAGING`
