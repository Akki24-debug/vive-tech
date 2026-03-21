# AI Error Repair Studio

Herramienta local de Windows para arrancar una base de analisis y reparacion de errores asistida por IA.

En esta primera fase no conecta todavia a un modelo externo. La idea es dejar listo:

- un GUI para capturar y seguir casos
- una base de conocimiento reutilizable
- un prompt estructurado para futura integracion con IA
- un caso documentado de referencia para entrenar el criterio inicial

## Incluye

- editor de caso actual
- base de conocimiento con casos guardados
- prompt generado automaticamente para otra IA
- blueprint del sistema para saber como debe comportarse
- ejemplo inicial basado en el caso ASUS AI Suite / BSOD durante reinicio

## Como abrirlo

Desde Windows:

```bat
launch_ai_error_repair_studio.bat
```

O desde PowerShell:

```powershell
python .\ai_error_repair_studio.py
```

## Requisitos

- Python en PATH

## Estructura

- `ai_error_repair_studio.py`: GUI principal
- `data/knowledge_base.json`: base de conocimiento inicial y blueprint del sistema
- `saved_cases/`: casos exportados desde la GUI
- `README.txt`: resumen humano rapido para abrir, entender y continuar
- `GUIDE.md`: guia operativa detallada
- `AI_EXPLAINER_BRIEF.md`: documento para otra IA explicadora

## Objetivo practico

La app debe servir para este flujo:

1. registrar un caso real
2. documentar sintomas, pruebas, resultados y aprendizajes
3. reutilizar lo aprendido en casos similares
4. generar el contexto correcto para una IA que proponga analisis o reparaciones

## Notas

- El conocimiento actual no reemplaza diagnostico tecnico real.
- El sistema esta pensado para crecer despues con deteccion automatica, mas conectores y una IA conectada al flujo.

## Siguientes pasos sugeridos

Si en el futuro se retoma esta herramienta, el orden recomendado es este:

1. integrar una IA real que consuma el prompt generado
2. agregar importacion automatica de eventos, logs o minidumps
3. anadir un motor simple de similitud entre casos
4. mostrar hipotesis priorizadas y acciones sugeridas dentro de la GUI
5. registrar historial de cambios por caso para no perder trazabilidad

## Estado actual

La herramienta queda lista como base documental y GUI local.

Eso significa:

- ya sirve para capturar y guardar casos
- ya sirve para conservar conocimiento tecnico reusable
- ya sirve para preparar contexto para una IA externa
- todavia no automatiza diagnostico ni reparacion
