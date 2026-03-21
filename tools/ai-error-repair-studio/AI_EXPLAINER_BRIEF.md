# AI Explainer Brief: AI Error Repair Studio

## Proposito de este archivo

Este documento existe para que otra IA pueda explicar la herramienta sin tener que deducir su objetivo leyendo primero el codigo.

## Descripcion corta

`AI Error Repair Studio` es una app local de Windows hecha con `Tkinter` para construir una base de conocimiento de soporte tecnico y preparar una futura capa de IA para analisis y reparacion de errores.

Hoy no se conecta a una IA externa. Su trabajo actual es ordenar el caso, documentar aprendizaje y generar el contexto correcto para la siguiente fase.

## Mensaje principal que otra IA debe transmitir

La idea central es esta:

> La app convierte incidentes tecnicos reales en conocimiento estructurado y reusable, para que una IA futura reciba mejor contexto y no arranque desde cero en cada caso.

## Que debe explicar primero

Cuando alguien pregunte "que hace" o "como sirve", la respuesta ideal debe empezar con estas ideas:

1. no es todavia un reparador automatico
2. si es una base local de casos y conocimiento tecnico
3. esta pensada para que despues una IA pueda analizar mejor y proponer pasos utiles

## Mapa mental de la interfaz

Otra IA debe explicar la ventana como cuatro piezas:

### 1. Encabezado

Sirve para:

- titulo del caso
- sistema objetivo
- estado
- tags
- resultado
- guardado
- generacion y copia del prompt

### 2. Pestana `Caso actual`

Sirve para escribir el incidente de forma estructurada:

- problema
- sintomas
- patron
- entorno
- evidencia
- acciones
- resultados
- hipotesis
- siguientes pasos
- lecciones

### 3. Pestana `Conocimiento`

Sirve para revisar casos previos y reutilizarlos.

### 4. Pestana `Prompt IA`

Sirve para preparar un prompt grande y consistente para una IA futura.

### 5. Pestana `Sistema`

Sirve para explicar como debe funcionar el producto cuando se amplie.

## Lo que otra IA puede afirmar con seguridad

- hay una GUI local funcional
- se pueden guardar casos como conocimiento
- existe una base inicial con un ejemplo real
- la app genera un prompt para una IA futura
- el sistema ya define reglas de seguridad y flujo operativo

## Lo que otra IA no debe exagerar

No debe decir que la app ya:

- analiza minidumps sola
- arregla drivers automaticamente
- llama a una IA real
- ejecuta remediaciones del sistema
- aprende sola sin supervision

Porque eso todavia no existe.

## Caso semilla actual

La base incluye un caso real relacionado con:

- ASUS AI Suite
- errores de inicio por residuos de `AsIO.sys` y `aaHM.apiHmData2`
- BSOD al reiniciar una vez por ciclo

Otra IA puede usarlo como ejemplo de aprendizaje inicial, pero no como regla general para todos los casos de BSOD.

## Flujo por intencion del usuario

### Si el usuario quiere registrar un caso nuevo

Explicacion recomendada:

1. crea o limpia el formulario
2. captura sintomas, evidencia y pasos ya intentados
3. agrega tags
4. guarda el caso

### Si el usuario quiere reutilizar un caso viejo

Explicacion recomendada:

1. ve a `Conocimiento`
2. selecciona el caso
3. revisa el detalle
4. cargalo o usalo como base

### Si el usuario quiere pedir ayuda a una IA

Explicacion recomendada:

1. termina de estructurar el caso
2. genera el prompt
3. copialo
4. pegalo en la IA elegida

## Reglas de explicacion

Otra IA debe seguir estas reglas:

1. explicar primero el objetivo practico
2. separar lo actual de lo futuro
3. no vender automatizacion inexistente
4. tratar el caso ASUS como ejemplo semilla, no como plantilla universal
5. si pregunta por la IA, aclarar que hoy solo esta la base y el prompt

## Archivos que otra IA deberia leer

En este orden:

1. `README.md`
2. `GUIDE.md`
3. `data/knowledge_base.json`
4. `ai_error_repair_studio.py`

## Criterio final

Si otra IA solo puede leer un documento para explicar esta herramienta, este debe ser el primero.
