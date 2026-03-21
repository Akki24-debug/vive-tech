# Guia detallada: Git Branch Tracker

## Objetivo

Esta herramienta existe para manejar el repo de `Proyecto VLV` de forma mas segura y mas amistosa desde Windows, sin depender de recordar todos los comandos de Git cada vez.

No reemplaza Git. Es una interfaz local encima de Git para las tareas de trabajo mas comunes del dia a dia.

Si otra IA necesita explicar esta herramienta por voz o texto a otra persona, primero debe leer:

- `AI_EXPLAINER_BRIEF.md`

## Que problema resuelve

El flujo deseado del proyecto es:

1. no trabajar directamente sobre `main`
2. crear ramas por tarea
3. revisar cambios antes de commitear
4. commitear solo lo correcto
5. subir la rama cuando este lista

La app concentra eso en una sola ventana y agrega protecciones para evitar errores comunes.

## Requisitos

- Windows
- `python` en `PATH`
- `git` en `PATH`

## Como abrirla

### Opcion 1: archivo BAT

Haz doble clic en:

`launch_git_branch_tracker.bat`

### Opcion 2: PowerShell

```powershell
python .\git_branch_tracker.py
```

## Vista general de la ventana

La app se divide en cuatro zonas principales:

### 1. Encabezado superior

Aqui puedes:

- ver el path del repositorio actual
- recargar toda la informacion
- abrir la carpeta del repo en Windows
- ver:
  - rama actual
  - upstream
  - estado general
  - cantidad de archivos staged

### 2. Panel izquierdo

Contiene:

- acciones de ramas
- lista de ramas locales
- lista de ramas remotas
- acciones rapidas
- arbol visual de commits

### 3. Panel derecho

Contiene:

- lista de archivos modificados
- botones de stage y unstage
- diff del archivo seleccionado
- caja para mensaje de commit

### 4. Panel inferior

Contiene:

- detalle del commit seleccionado
- salida textual del ultimo comando relevante

## Funcionamiento de ramas

### Nueva rama

Boton: `Nueva rama`

Pide un nombre y ejecuta:

```bash
git checkout -b <rama>
```

Usala cuando ya sabes exactamente el nombre de la rama que quieres.

### Rama automatica

Boton: `Rama automatica`

Pide un slug corto y crea una rama con formato:

```text
codex/<slug>
```

Comportamiento:

- si estas en `main`, `master` o `develop`, crea la rama y te cambia a ella
- si ya estas en una rama de trabajo, no crea otra por encima sin avisarte

Es la forma recomendada para empezar una tarea nueva.

### Checkout

Boton: `Checkout`

Hace checkout de la rama local seleccionada.

Tambien puedes hacer doble clic sobre una rama local.

### Desde remota

Boton: `Desde remota`

Toma la rama remota seleccionada y crea una rama local siguiendo esa rama remota.

Sirve para:

- bajar una rama existente de GitHub
- empezar a trabajar sobre una rama remota sin tener que escribir el comando

### Borrar local

Boton: `Borrar local`

Elimina una rama local seleccionada usando borrado forzado.

Protecciones:

- no permite borrar `main`, `master`, `develop`
- no permite borrar la rama actual

### Merge hacia actual

Boton: `Merge hacia actual`

Toma la rama local seleccionada y la mergea hacia la rama actual usando:

```bash
git merge --no-ff <rama>
```

Ejemplo:

- rama actual: `main`
- rama seleccionada: `fix/report-grid`
- resultado: merge de `fix/report-grid` dentro de `main`

## Acciones rapidas

### Pull actual

Hace:

```bash
git pull --ff-only origin <rama-actual>
```

### Push actual

Hace:

```bash
git push -u origin <rama-actual>
```

### Checklist PR

Copia al portapapeles un checklist corto para preparar PR o entrega.

## Arbol visual de commits

Esta es la mejora principal frente a la version anterior.

### Que muestra

La herramienta ejecuta internamente un `git log --graph` y lo dibuja en un `Canvas` como:

- lineas por carril
- nodos de commits
- texto del commit

No es un cliente Git comercial completo, pero ya es un arbol visual real e interactivo, no solo texto plano.

### Como usarlo

- haz clic sobre un nodo de commit
- el commit seleccionado se resalta
- abajo se llena el panel `Detalle de commit seleccionado`

### Alcance del arbol

Dropdown: `all` o `current`

- `all`: muestra toda la historia visible del repo
- `current`: muestra solo la historia alcanzable desde `HEAD`

### Que muestra el detalle del commit

Al seleccionar un commit, la app carga:

```bash
git show --stat --summary --format=fuller <hash>
```

Y enseña:

- hash
- autor
- fechas
- mensaje
- resumen de archivos tocados

## Archivos modificados

La tabla de archivos se alimenta desde:

```bash
git status --porcelain
```

Cada fila muestra:

- estado simplificado
- ruta del archivo

## Stage y unstage

### Stage/Unstage archivo

Funciona sobre el archivo seleccionado.

Regla:

- si ya esta staged, hace unstage
- si no esta staged, lo agrega

### Stage all

Hace:

```bash
git add -A
```

### Unstage all

Hace:

```bash
git restore --staged .
```

## Diff del archivo

Cuando seleccionas un archivo en la tabla:

- la app intenta mostrar el diff correspondiente

Si el archivo esta staged:

```bash
git diff --cached -- <archivo>
```

Si no esta staged:

```bash
git diff -- <archivo>
```

## Commit

### Caja de mensaje

Escribe ahi el mensaje del commit.

### Commit staged

Hace commit solo de lo que esta staged.

Protecciones:

- no permite commit directo en `main`
- no permite commit directo en `master`
- no permite commit directo en `develop`
- no permite commit si no hay staged
- no permite commit sin mensaje

## Lo que si hace bien

- manejo diario de ramas
- stage selectivo por archivo
- diff rapido
- commit seguro
- push y pull
- vista de arbol interpretable
- proteccion contra errores comunes en ramas protegidas

## Lo que todavia no hace

- resolver conflictos de merge
- rebase
- cherry-pick
- squash interactivo
- crear PR real en GitHub
- diff lado a lado
- render grafico avanzado estilo GitKraken

## Flujo recomendado

### Empezar tarea nueva

1. abre la app
2. confirma que estas en `main`
3. usa `Rama automatica`
4. trabaja sobre esa rama

### Preparar commit

1. selecciona archivos
2. revisa diff
3. stagea solo los correctos
4. escribe mensaje
5. usa `Commit staged`

### Subir rama

1. verifica rama actual
2. usa `Push actual`

### Integrar rama

1. cambia a rama destino
2. selecciona la rama origen
3. usa `Merge hacia actual`

## Si algo falla

### La app no abre

Revisa:

- `python --version`
- `git --version`

### No encuentra el repo

Revisa que el script este dentro de un arbol con `.git` o ajusta el path del repositorio arriba.

### Un comando Git falla

Lee el panel `Salida / Resultado`. Ahi se muestra el error real de Git.
