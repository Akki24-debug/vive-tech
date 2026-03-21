# Guia detallada: AI Error Repair Studio

## Objetivo

Esta herramienta existe para construir una base local de soporte tecnico asistido por IA, empezando por casos reales y documentados.

La app no intenta arreglar equipos automaticamente todavia.

Lo que hace hoy es:

- capturar un caso tecnico de forma estructurada
- guardar el conocimiento localmente
- permitir reutilizar casos previos
- generar un prompt robusto para una IA futura

## Problema que resuelve

En soporte tecnico casi siempre pasa esto:

1. se prueban muchas cosas
2. se pierde contexto entre sesiones
3. se recuerdan mal los sintomas o el orden de pruebas
4. cuando se quiere usar IA, se le da un prompt pobre

La app ataca justo eso. Ordena el caso, conserva el aprendizaje y prepara mejor la futura capa de IA.

## Requisitos

- Windows
- `python` en `PATH`

## Como abrirla

### Opcion 1: archivo BAT

Haz doble clic en:

`launch_ai_error_repair_studio.bat`

### Opcion 2: PowerShell

```powershell
python .\ai_error_repair_studio.py
```

## Vista general de la interfaz

La app se divide en cuatro zonas logicas:

### 1. Encabezado superior

Sirve para:

- definir titulo del caso
- indicar sistema objetivo
- marcar estado del caso
- guardar el caso
- definir tags y resultado
- generar o copiar el prompt para otra IA

### 2. Pestana `Caso actual`

Es el corazon de la app.

Aqui se capturan:

- resumen del problema
- sintomas observados
- patron de reproduccion
- notas del entorno
- evidencia
- acciones ya realizadas
- resultados
- hipotesis de trabajo
- siguientes pasos
- lecciones que deberian entrenar a la futura IA

### 3. Pestana `Conocimiento`

Muestra los casos conocidos dentro de la base local.

Sirve para:

- revisar casos anteriores
- cargarlos en el editor
- usarlos como plantilla para un caso nuevo similar

### 4. Pestana `Prompt IA`

Genera automaticamente un bloque de contexto listo para pegarse en una IA futura.

Ese prompt incluye:

- rol esperado del asistente
- flujo operativo
- reglas de seguridad
- caso actual estructurado
- conocimiento relevante encontrado en la base
- instrucciones de respuesta para la IA

### 5. Pestana `Sistema`

Documenta como debe funcionar la herramienta una vez ampliada.

Sirve como blueprint de producto y tambien como base de comportamiento para otra IA.

## Modelo del sistema

La arquitectura pensada desde ahora es esta:

1. ingesta del caso
2. normalizacion de sintomas, acciones y resultados
3. busqueda de casos parecidos en la base local
4. generacion de plan de diagnostico o reparacion
5. ejecucion manual o semiautomatica
6. captura del resultado real
7. aprendizaje hacia la base de conocimiento

## Reglas del sistema futuro

La futura IA no deberia:

- inventar hallazgos
- ocultar incertidumbre
- mezclar hechos con hipotesis
- sugerir pasos destructivos sin advertirlos
- prometer reparacion segura cuando no hay evidencia

La futura IA si deberia:

- resumir claro
- separar confirmado de probable
- priorizar pasos de bajo riesgo
- reutilizar casos similares
- dejar lecciones reutilizables

## Caso inicial documentado

La base ya trae un ejemplo real:

- ASUS AI Suite con errores residuales al iniciar sesion
- `Can't open AsIO.sys!! (2)`
- `Clase no registrada, ProgID: 'aaHM.apiHmData2'`
- BSOD que aparece al reiniciar, no en apagado frio

Ese caso queda como ejemplo inicial de entrenamiento de criterio, no como verdad universal.

## Flujo recomendado hoy

### Registrar un caso nuevo

1. usa `Nuevo caso`
2. escribe titulo, sistema y estado
3. rellena sintomas, evidencia, acciones y resultados
4. agrega tags utiles
5. guarda el caso

### Reutilizar conocimiento

1. abre la pestana `Conocimiento`
2. selecciona un caso parecido
3. revisa detalle
4. usa `Cargar en editor` o `Usar como base`

### Preparar una consulta para IA

1. confirma que el caso esta bien capturado
2. pulsa `Generar prompt IA`
3. revisa el texto
4. pulsa `Copiar prompt`
5. pegalo en la IA que luego conectaremos

## Lo que la app ya hace bien

- estructura el conocimiento tecnico
- evita perder contexto entre sesiones
- deja trazabilidad de lo que ya se intento
- prepara una capa de IA futura sin improvisacion

## Lo que todavia no hace

- leer eventos de Windows automaticamente
- parsear minidumps
- consultar WMI o servicios desde la GUI
- ejecutar remediaciones automaticamente
- llamar a un modelo de IA real
- aprender por embeddings o recuperacion semantica

## Siguientes ampliaciones naturales

- importador de logs
- catalogo de sintomas frecuentes
- puntuacion de hipotesis
- historial de cambios por caso
- integracion con una API de IA
- motor de recuperacion de casos mas parecido

## Siguientes pasos sugeridos

Si este proyecto se deja quieto por ahora y luego se retoma, conviene seguir este orden para no rehacer la base:

### Paso 1

Conectar una IA real al prompt ya generado.

Objetivo:

- aprovechar la estructura ya capturada sin tocar el modelo de datos

Entrega minima esperada:

- boton para enviar el prompt a una API
- area de respuesta dentro de la GUI
- guardado opcional de la respuesta en el caso

### Paso 2

Anadir fuentes de evidencia automáticas.

Objetivo:

- dejar de depender solo de captura manual

Entrega minima esperada:

- importacion de logs de texto
- lectura de eventos relevantes de Windows
- campo de evidencia enriquecido

### Paso 3

Mejorar la reutilizacion de conocimiento.

Objetivo:

- encontrar mas rapido casos parecidos

Entrega minima esperada:

- scoring por tags
- scoring por palabras clave
- lista de casos similares mas visible

### Paso 4

Convertir el conocimiento en ayuda operativa real.

Objetivo:

- pasar de archivo historico a asistente de trabajo

Entrega minima esperada:

- hipotesis priorizadas
- acciones sugeridas
- casillas para marcar pasos completados

### Paso 5

Agregar trazabilidad fuerte.

Objetivo:

- saber que cambio, cuando y con que resultado

Entrega minima esperada:

- historial por caso
- autor o fuente del cambio
- fecha y notas por iteracion

## Criterio para no romper la base actual

Si alguien amplia la herramienta despues, deberia respetar estas decisiones:

- mantener `knowledge_base.json` como fuente simple y legible
- no borrar el caso ASUS semilla; solo enriquecerlo o moverlo con migracion clara
- mantener separados los documentos para humanos y para otra IA
- no mezclar hechos confirmados con recomendaciones generadas
- no convertir el sistema en automatizacion agresiva sin controles de seguridad

## Si algo falla

### La app no abre

Revisa:

- `python --version`

### No guarda casos

Revisa permisos sobre la carpeta del repo y sobre `saved_cases`.

### El prompt sale vacio

Normalmente significa que el caso actual sigue casi vacio.
