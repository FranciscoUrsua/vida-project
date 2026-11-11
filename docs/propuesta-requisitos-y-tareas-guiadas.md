# Requisitos de acción y Tareas guiadas — Punto de partida

Estado: notas de partida para diseñar dos módulos nuevos. No es diseño funcional. Fecha: noviembre 2026

## Contexto

VIDA360 ya tiene las piezas (permisos, versionado, documentos, planes, citas, mesas de trabajo), pero la ficha de la persona funciona como una caja de herramientas: da por hecho que el profesional sabe qué herramienta usar en cada momento. Con el perfil de los usuarios, hace falta una capa que guíe sin cambiar el modelo de datos.

Se identifican dos módulos transversales a todo VIDA360, distintos entre sí:

- Requisitos de acción: qué tiene que cumplirse para poder hacer algo.
- Tareas guiadas: en qué orden se usan las herramientas para conseguir un objetivo.

Relación: las tareas guiadas aplican los requisitos en cada paso, y los requisitos también se aplican fuera de las tareas, cuando se usa la caja de herramientas directamente.

Origen: análisis de CIAM y de los grupos multifuncionales (seguimiento coordinado de varios planes, cada uno con su responsable; el modelo de permisos ya lo soporta, lo que falta es interfaz).

## 1. Requisitos de acción

### Decidido

- Se aplica a todo VIDA360, no solo a CIAM.
- Un requisito se define como: acción + condición + requisito + nivel + ámbito.
- El nivel va en la configuración de cada requisito:
  - Absoluto: no se puede seguir.
  - Salvable con justificación: se puede seguir dejando una justificación, que queda auditada.
- El contexto importa. La misma condición puede tener efectos opuestos según el ámbito:
  - CIAM: inscribir a una menor en una actividad requiere la autorización firmada de padres o tutores.
  - Centros de Jóvenes: ser menor no impide participar; puede incluso ser requisito.
- Los requisitos son configuración (backoffice), no código.

### Ejemplos

| Acción | Condición | Requisito | Nivel | Ámbito |
|---|---|---|---|---|
| Tramitar una prestación | — | Historia social abierta | Absoluto | General |
| Inscribir en actividad | Persona menor de edad | Documento "Autorización de padres/tutores" | Por definir | Tipo de centro CIAM |
| Inscribir en actividad | — | Persona menor de edad | Por definir | Centros de Jóvenes (según actividad) |

### Ideas planteadas, por confirmar

- La aplicación puede comprobar que un documento existe, que es del tipo correcto, que está asociado a la persona y que está en vigor. Que sea "correcto" (firma, legibilidad) lo decide el profesional, que lo marca como revisado; queda registrado.
- Permisos y requisitos son distintos: los permisos dicen quién puede hacer algo; los requisitos, cuándo se puede.
- Se evalúan en el servidor, no solo en la interfaz.

### Por decidir

- Ámbitos posibles: general, tipo de centro, tipo de actividad, tipo de prestación, tipo de plan…
- Precedencia: cuando hay un requisito general y otro de ámbito más concreto, ¿el concreto sustituye al general o se suman?
- Qué tipo de centro aplica cuando el profesional está asignado a una UO superior a varios centros: probablemente el del centro donde se realiza la acción (p. ej. el de la actividad), no el de la UO del profesional.
- Catálogo de acciones a las que se pueden poner requisitos.
- Catálogo de condiciones (edad, historia social abierta, colectivo protegido, documento aportado, plan abierto…).
- Qué validaciones que hoy están en el código pasan a ser requisitos configurables.

## 2. Tareas guiadas

### Decidido

- Catálogo de tareas en la ficha de la persona, junto a la caja de herramientas (que se mantiene).
- Las tareas se definen en backoffice como un workflow de herramientas existentes. Ejemplo:
  - Dar de alta a la persona o identificarla si ya existe
  - Subir documentación
  - Entrevista de información y acogida
  - Si el caso lo requiere, abrir historia social
  - Crear cita con un profesional
- Cada paso tiene un título propio de la tarea, aunque use la misma herramienta: la "entrevista de información y acogida" y la "entrevista de seguimiento" se muestran distintas aunque sean la misma herramienta por debajo.
- Sustituye a los actuales "Procesos", que están programados en el código.

### Ideas planteadas, por confirmar

- Las tareas que se ofrecen dependen del rol del profesional, del tipo de centro y de la situación de la persona (menor, colectivo protegido, con o sin PISO…).
- La tarea no tiene lógica propia: usa las mismas acciones, validaciones, permisos y requisitos que la caja de herramientas.
- Las tareas se pueden dejar a medias y retomar.
- Para elegir las primeras tareas: las más frecuentes o con más errores. La auditoría puede mostrar las secuencias de acciones más repetidas.
- Grupos multifuncionales: tarea tipo "Preparar / registrar sesión del grupo", con una vista de todos los planes de la persona (responsable, estado, próximo seguimiento) y revisión plan por plan.

### Por decidir

- Pasos condicionales ("si el caso lo requiere"): ¿decide el profesional, o se evalúa una condición automáticamente?
- Pasos opcionales, repetibles o que se pueden saltar.
- Versionado de la definición de una tarea cuando hay personas a mitad de una tarea.
- Migración de los "Procesos" actuales a tareas configurables.
- Relación con el vocabulario por contexto (tipo de centro, tipo de mesa).

## 3. Señal visual del contexto de trabajo del profesional

Se refiere a dónde trabaja el profesional, no a dónde está vinculado el ciudadano.

### Decidido

- El profesional siempre está asignado a una unidad organizativa: un centro, o una UO superior si trabaja en varios centros.
- La barra superior muestra el tipo y el nombre del centro (p. ej. "CIAM · Puente de Vallecas"). Si el profesional está asignado a una UO superior, se muestra el nombre de esa UO.
- Colores por tipo de centro: aplazado. Se valorará más adelante (accesibilidad, choque con colores de alerta y del banner de protegido, distinción entre muchos tipos).

### Por decidir

Si un profesional con varios contextos necesita un selector en ese mismo elemento.

### Descartado o aparcado

- "Entornos" como concepto propio: se resuelve con la configuración del tipo de centro (catálogo de prestaciones, planes y fichas), el catálogo de tareas por contexto y, si hace falta, el vocabulario.
- Colores por tipo de centro: aplazado (§3).
- Importación de datos históricos de CIAM: proyecto de migración aparte.
