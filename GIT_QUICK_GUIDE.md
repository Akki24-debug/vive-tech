# Git Quick Guide

## Idea simple

Todo Vive La Vibe vive en **un solo repo**: `Proyecto VLV/`.

No vamos a usar muchos repos ni ramas permanentes.

La regla sera esta:

- `main` = lo aprobado y estable
- `task/...` = trabajo temporal por tarea

## Como trabajaremos

Cuando me pidas algo:

1. yo reviso el estado del repo;
2. si la tarea amerita rama, te pregunto si abro una temporal;
3. hago los cambios;
4. cuando una parte ya quede bien, te pregunto si la guardamos en commit;
5. al final te pregunto si cerramos la tarea;
6. si me dices que si, hago merge a `main` y borro la rama temporal.

## Que si se conserva

Aunque borremos la rama temporal:

- los commits quedan guardados;
- el historial no se pierde;
- siempre se puede revisar que se hizo en cada paso.

## Que me va a preguntar la IA

Solo estas cosas:

1. si quieres abrir una rama temporal nueva;
2. si una subseccion ya se puede guardar;
3. si quieres cerrar la tarea y mandarla a `main`.

## Ejemplo real

Tarea:

- mejorar huespedes

Rama:

- `task/guest-improvements`

Commits posibles:

- `guests: improve full-name search`
- `guests: fix guest form layout`
- `guests: normalize phone validation`

Cuando ya todo quede bien:

- merge a `main`
- borrar `task/guest-improvements`

## Regla practica

Si el cambio es chico pero toca logica real, mejor usar rama temporal.

Si es solo un cambio minimo de texto, podria hacerse en `main`, pero por defecto prefiero rama.

## Objetivo

Que tu no tengas que pensar en comandos Git.

La IA debe encargarse de:

- revisar estado
- crear ramas
- hacer commits
- hacer merge
- borrar ramas temporales

Tu solo validas:

- si se abre la tarea
- si una parte ya quedo bien
- si se cierra la tarea
