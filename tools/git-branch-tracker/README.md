# Git Branch Tracker

Utilidad local de Windows para manejar Git de forma mas amistosa sobre este repo.

## Incluye

- ramas locales y remotas
- creacion manual y automatica de ramas
- checkout local y desde remota
- borrado de ramas locales no protegidas
- merge de una rama seleccionada hacia la rama actual
- stage y unstage por archivo
- stage all y unstage all
- diff del archivo seleccionado
- commit seguro solo sobre staged
- pull y push de la rama actual
- arbol visual real de commits con seleccion de nodos
- detalle del commit seleccionado
- checklist rapido para PR o entrega

## Como abrirlo

Desde Windows:

```bat
launch_git_branch_tracker.bat
```

O desde PowerShell:

```powershell
python .\git_branch_tracker.py
```

## Requisitos

- Python en PATH
- Git en PATH

## Guia detallada

Lee la guia completa aqui:

- [GUIDE.md](C:\Users\ragnarok\Documents\repos\Proyecto VLV\tools\git-branch-tracker\GUIDE.md)

## Documento para otra IA explicadora

Si quieres pasar esta herramienta a otra IA para que la explique por texto o voz, usa primero:

- [AI_EXPLAINER_BRIEF.md](C:\Users\ragnarok\Documents\repos\Proyecto VLV\tools\git-branch-tracker\AI_EXPLAINER_BRIEF.md)

## Notas

- El programa usa `git` del sistema.
- Las ramas `main`, `master` y `develop` se tratan como protegidas para commit y borrado.
- El commit trabaja solo sobre lo que este en stage.
- El push usa `git push -u origin <rama-actual>`.
- Si tu sesion Git pide autenticacion, esa parte la seguira resolviendo Git con tus credenciales normales.
