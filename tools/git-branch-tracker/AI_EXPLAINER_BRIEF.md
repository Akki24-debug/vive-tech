# AI Explainer Brief: Git Branch Tracker

## Proposito de este archivo

Este documento existe para que otra IA, especialmente una que responda por voz, pueda explicar la herramienta sin tener que inferir el funcionamiento a partir del codigo.

La intencion no es describir Git en general, sino explicar con claridad:

- que hace la app
- como esta organizada
- que puede hacer el usuario
- que no debe prometer la app
- como guiar a una persona paso por paso segun lo que quiera lograr

## Descripcion corta

`Git Branch Tracker` es una app local de Windows hecha con `Tkinter` para facilitar el trabajo diario con Git sobre el repo `Proyecto VLV`.

La app sirve para:

- crear ramas
- cambiar entre ramas
- ver ramas locales y remotas
- revisar cambios
- hacer stage y unstage
- ver diff
- hacer commits
- hacer pull y push
- visualizar el historial en un arbol de commits

No es un reemplazo completo de Git ni un cliente profesional como GitKraken, Sourcetree o Fork.

## Mensaje principal que la IA debe transmitir

La idea central es esta:

> La app reduce friccion y errores en el flujo diario de Git, especialmente para trabajar por ramas y evitar commits directos en `main`.

## Lo que la IA debe explicar primero

Cuando el usuario pregunte “como se usa” o “que hace”, la respuesta ideal debe arrancar con estas tres ideas:

1. la app esta pensada para trabajo diario, no para operaciones avanzadas de Git
2. el flujo recomendado es: crear rama, revisar cambios, stagear, commit, push
3. la app protege `main`, `master` y `develop` para evitar errores comunes

## Mapa mental de la interfaz

La IA debe explicar la ventana como cuatro zonas:

### 1. Encabezado superior

Sirve para:

- ver el repo activo
- recargar la informacion
- abrir la carpeta del repo
- ver rama actual, upstream, estado y cantidad de staged

### 2. Panel izquierdo

Sirve para:

- trabajar con ramas
- ver ramas locales
- ver ramas remotas
- hacer pull, push y copiar checklist PR
- explorar el arbol de commits

### 3. Panel derecho

Sirve para:

- ver archivos modificados
- stagear o unstagear
- revisar diff
- escribir el mensaje de commit
- lanzar el commit

### 4. Panel inferior

Sirve para:

- ver el detalle del commit seleccionado
- leer errores o resultados de los comandos

## Glosario minimo que la IA debe manejar

### Rama

Linea de trabajo separada dentro del repo.

### Rama protegida

En esta app, `main`, `master` y `develop`.

La app evita commits directos ahi y tambien evita borrar esas ramas.

### Stage

Lista de cambios preparados para el siguiente commit.

### Unstage

Quitar un archivo de esa lista sin borrar sus cambios del disco.

### Diff

Comparacion exacta de cambios en un archivo.

### Upstream

Rama remota asociada a la rama local actual.

### Merge

Integracion de una rama dentro de otra.

## Flujos por intencion del usuario

La IA debe preferir explicar por objetivo, no por boton aislado.

### Si el usuario quiere empezar una tarea nueva

Explicacion recomendada:

1. abre la app
2. confirma que estas en `main`
3. usa `Rama automatica`
4. escribe un slug corto
5. la app crea una rama tipo `codex/<slug>` y te cambia a ella

### Si el usuario quiere revisar antes de guardar cambios

Explicacion recomendada:

1. ve la lista de archivos
2. selecciona un archivo
3. revisa el diff
4. stagea solo lo correcto

### Si el usuario quiere hacer un commit

Explicacion recomendada:

1. confirma que no estas en `main`
2. deja staged solo lo correcto
3. escribe un mensaje claro
4. usa `Commit staged`

### Si el usuario quiere subir su trabajo

Explicacion recomendada:

1. confirma tu rama actual
2. usa `Push actual`

### Si el usuario quiere traer una rama de GitHub

Explicacion recomendada:

1. selecciona una rama remota
2. usa `Desde remota`
3. confirma el nombre local

### Si el usuario quiere integrar una rama

Explicacion recomendada:

1. cambia primero a la rama destino
2. selecciona la rama origen en locales
3. usa `Merge hacia actual`

## Arbol visual de commits

Este punto debe explicarse con cuidado porque es una mejora nueva.

La IA debe decir:

- ya no es solo un log en texto
- ahora hay nodos visuales sobre un `Canvas`
- el usuario puede hacer clic sobre un nodo
- al hacer clic, se resalta el commit y abajo aparece su detalle

### Que si puede afirmar la IA

- hay una vista de arbol visual interpretable
- se puede seleccionar un commit
- se puede ver su detalle

### Que no debe exagerar

No debe decir que la app tiene:

- drag and drop de commits
- grafo estilo GitKraken
- rebase visual
- resolucion visual de conflictos

Porque eso no existe ahora.

## Capacidades reales actuales

La IA si puede afirmar que hoy la app permite:

- crear rama manual
- crear rama automatica
- cambiar de rama
- traer rama remota a local
- borrar rama local no protegida
- mergear una rama local hacia la rama actual
- hacer pull
- hacer push
- copiar checklist PR
- ver archivos modificados
- stagear y unstagear por archivo
- stage all
- unstage all
- ver diff
- hacer commit seguro solo sobre staged
- ver arbol de commits
- ver detalle de commit

## Limites reales actuales

La IA debe decir claramente que todavia no hace:

- rebase
- cherry-pick
- squash interactivo
- creacion real de pull request
- resolucion de conflictos
- diff visual lado a lado
- operaciones destructivas avanzadas

## Reglas de explicacion

La IA debe seguir estas reglas al explicarla:

1. primero explicar el objetivo practico
2. luego explicar el flujo mas comun
3. luego explicar botones concretos solo si el usuario los pide
4. evitar hablar como si fuera un cliente Git empresarial completo
5. si el usuario pregunta si “puede hacer todo”, responder con honestidad que cubre bien el flujo diario, pero no Git avanzado

## Respuestas modelo recomendadas

### Pregunta: “que hace esta app?”

Respuesta modelo:

> Es una interfaz de Windows para hacer mas facil el trabajo diario con Git en este repo. Te ayuda a crear ramas, cambiar entre ramas, revisar archivos modificados, hacer stage, ver diff, commitear, hacer push y ver el historial en un arbol de commits. La idea principal es trabajar por ramas y evitar errores en `main`.

### Pregunta: “como empiezo una tarea nueva?”

Respuesta modelo:

> Lo normal es abrir la app, confirmar que estas en `main` y usar `Rama automatica`. Eso te crea una rama de trabajo nueva y te cambia a ella. Desde ahi haces tus cambios, revisas el diff, stageas lo correcto y luego commiteas.

### Pregunta: “puedo hacer merge ahi?”

Respuesta modelo:

> Si, pero de forma basica. Cambias a la rama destino, seleccionas la rama origen y usas `Merge hacia actual`. La app hace un merge con `--no-ff`. No resuelve conflictos visualmente; si hay conflicto, Git te lo va a reportar.

### Pregunta: “puedo hacer todo desde aqui?”

Respuesta modelo:

> Para flujo diario, casi si: ramas, stage, diff, commit, push, pull y merge basico. Para Git avanzado, no: rebase, cherry-pick, conflictos complejos y PRs reales siguen fuera.

## Guion corto para explicacion por voz

Si la IA necesita dar una explicacion oral corta, puede seguir este orden:

1. “Esta app te ayuda a trabajar Git por ramas sin tocar tanto la terminal.”
2. “Arriba ves el repo y la rama actual.”
3. “A la izquierda manejas ramas y ves el arbol de commits.”
4. “A la derecha revisas archivos, stageas y haces commit.”
5. “Abajo ves el detalle del commit o los errores.”
6. “El flujo recomendado es: crear rama, hacer cambios, revisar diff, stagear, commitear y push.”

## Archivos que otra IA debe leer si necesita mas detalle

En este orden:

1. `README.md`
2. `GUIDE.md`
3. `git_branch_tracker.py`

## Criterio final

Si otra IA solo puede leer un documento para explicar esta herramienta, este debe ser el primero.
