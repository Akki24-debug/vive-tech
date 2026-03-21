AI ERROR REPAIR STUDIO
======================

QUE ES
------
Esta carpeta contiene una herramienta local de Windows hecha en Python con Tkinter.
Su objetivo no es reparar equipos automaticamente todavia.

Su objetivo actual es:
- capturar casos tecnicos reales
- guardar conocimiento reutilizable
- documentar sintomas, pruebas y resultados
- dejar listo el contexto para conectar una IA despues


QUE INCLUYE
-----------
1. Una GUI local para editar casos.
2. Una base de conocimiento inicial.
3. Un generador de prompt para una IA futura.
4. Un caso semilla ya documentado:
   ASUS AI Suite con errores residuales y BSOD durante reinicio.


ARCHIVOS IMPORTANTES
--------------------
- ai_error_repair_studio.py
  Programa principal.

- launch_ai_error_repair_studio.bat
  Lanzador rapido para Windows.

- data\knowledge_base.json
  Base de conocimiento y blueprint del sistema.

- saved_cases\
  Carpeta donde se guardan casos exportados desde la GUI.

- GUIDE.md
  Guia detallada del funcionamiento.

- AI_EXPLAINER_BRIEF.md
  Documento pensado para otra IA que necesite explicar la herramienta.


COMO ABRIRLO
------------
Opcion 1:
Haz doble clic en:
launch_ai_error_repair_studio.bat

Opcion 2:
Desde PowerShell, dentro de esta carpeta:
python .\ai_error_repair_studio.py


QUE SE PUEDE HACER HOY
----------------------
- Crear un caso nuevo.
- Escribir resumen, sintomas, evidencia, acciones y resultados.
- Guardar el caso.
- Revisar casos previos.
- Reutilizar un caso como base.
- Generar un prompt estructurado para una futura IA.


QUE NO HACE TODAVIA
-------------------
- No llama a una IA real.
- No analiza minidumps automaticamente.
- No lee eventos de Windows por si sola.
- No ejecuta reparaciones automaticas.
- No decide sola la causa raiz.


CASO SEMILLA DOCUMENTADO
------------------------
La base ya trae un caso real de referencia:

- Errores de AI Suite al iniciar sesion por residuos:
  "Can't open AsIO.sys!! (2)"
  "Clase no registrada, ProgID: aaHM.apiHmData2"

- Punto importante del caso:
  el BSOD restante no era de arranque normal;
  ocurria al reiniciar, no al apagar y encender.

Ese caso sirve como ejemplo de entrenamiento inicial para el criterio del sistema.


SI ALGUIEN LO RETOMA DESPUES
----------------------------
Orden recomendado:

1. Conectar una IA real a partir del prompt generado.
2. Agregar importacion de logs y eventos.
3. Mejorar busqueda de casos similares.
4. Mostrar hipotesis y acciones sugeridas en la GUI.
5. Agregar historial de cambios por caso.


REGLAS PRACTICAS PARA NO ROMPERLO
---------------------------------
- Mantener la base en formato simple y legible.
- No mezclar hechos confirmados con hipotesis.
- No vender la herramienta como reparador automatico.
- Mantener separado lo que es documentacion para humanos y lo que es contexto para IA.


ESTADO EN EL QUE SE DEJA
------------------------
La herramienta queda como una base funcional y documentada.

Eso significa:
- la GUI ya existe
- la estructura del sistema ya esta definida
- el caso ASUS ya esta documentado
- hay sugerencias claras para ampliarla despues


REQUISITO
---------
Python debe estar en PATH.
