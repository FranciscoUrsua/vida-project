# UI — Ficha del ciudadano

**Proyecto:** VIDA 360  
**Módulo:** Ciudadanía  
**Versión:** 1.0  
**Fecha:** junio 2026  
**Estado:** Aprobado para implementación

---

## 1. Propósito

La ficha del ciudadano es la pantalla de referencia sobre los datos de Capa 1 de una persona registrada en VIDA 360. Muestra y permite editar los datos de identificación y contacto, el historial de documentos de identidad, la unidad de convivencia y un resumen de las prestaciones y actividades no vinculadas a historia social.

Esta pantalla es distinta de `intervencion/ciudadano/{historia}`, que pivota sobre la `HistoriaSocial` y está optimizada para el trabajo clínico del trabajador social. La ficha del ciudadano pivota sobre el propio `Ciudadano` y es accesible para un conjunto más amplio de roles.

---

## 2. Ruta y parámetro

```
/ciudadania/ciudadano/{ciudadano}
```

El parámetro de ruta es el `id` del ciudadano (tabla `ciudadanos`), no el id de una historia social. Un ciudadano puede no tener historia social y aun así tener ficha.

---

## 3. Roles y permisos

| Rol | Acceso | Edición Capa 1 | Edición UC |
|---|---|---|---|
| `intervencion` | Sí | Sí | Sí |
| `tramitacion` | Sí | Sí | Sí |
| `consulta_basica` | Sí | Sí | Sí |
| `supervision` | Sí | No | No |

El rol `supervision` ve todos los datos pero no puede modificarlos. El lápiz de edición inline no se renderiza para este rol.

La Capa 2 (situación social) y la Capa 3 (historia social) no se muestran en esta pantalla. Su acceso sigue las restricciones del módulo Intervención.

---

## 4. Layout

Dos columnas:

- **Columna principal** (flexible): identificación y contacto, documentos de identidad, unidad de convivencia.
- **Columna lateral** (300px fija): banner de historia social, otras prestaciones, centro de referencia y TSR, actividad reciente, permisos del rol activo.

---

## 5. Cabecera

Muestra nombre completo, documento de identidad activo, edad calculada y nivel de identificación (`identificado`, `probable`, `no_identificado`) como badge de color.

A la derecha: rol con el que opera el profesional, y botón "Editar datos" que activa el modo edición de todos los campos de Capa 1 simultáneamente. Para `supervision`, el botón no se renderiza.

---

## 6. Columna principal

### 6.1 Identificación y contacto

Campos de Capa 1 organizados en dos bloques: datos personales (nombre, apellidos, fecha de nacimiento, sexo, alias) y datos de contacto (domicilio, teléfono, email).

Cada campo muestra un lápiz de edición inline al hacer hover. Al hacer clic el campo se convierte en input editable y aparecen botones Guardar / Cancelar. Para `supervision` el lápiz no aparece en ningún campo.

El domicilio se introduce en texto libre. `DireccionObserver` lo normaliza a dirección canónica y lanza la geocodificación de forma transparente al guardar.

Al pie del bloque se muestra la primera demanda registrada en el alta, entrecomillada como texto del ciudadano, con metadatos de quién la registró, cuándo y desde qué centro. Es inmutable: no tiene lápiz de edición.

La línea de metadatos del bloque muestra la fecha y el profesional de la última modificación de cualquier campo del bloque.

### 6.2 Documentos de identidad

Lista completa del historial de documentos (`ciudadano_identificadores`), ordenados por fecha de inicio descendente. Para cada documento: tipo (DNI, NIE, pasaporte), valor, fecha de inicio, fecha de fin si existe, y estado (activo, caducado, sustituido).

El documento activo aparece con opacidad completa. Los documentos anteriores aparecen atenuados pero visibles, con su estado reflejado en un badge.

Botón "Añadir documento" disponible para roles con permiso de edición. Al añadir un documento nuevo, el anterior no se elimina: recibe `fecha_fin = today()` y estado `sustituido`. Esto garantiza que búsquedas por documentos anteriores sigan encontrando al ciudadano.

Nota informativa fija bajo la lista: los documentos anteriores no se eliminan; permiten localizar al ciudadano aunque haya cambiado de documento.

### 6.3 Unidad de convivencia

Muestra la UC vigente con sus miembros, la relación de cada miembro con el titular, su edad y documento si está registrado.

Cada miembro que es ciudadano registrado en VIDA tiene un enlace "Ver ficha" que navega a su propia ficha (`ciudadania/ciudadano/{id}`).

El pie del bloque muestra el número de miembros, los ingresos declarados si están registrados, y un enlace "Ver historial UC" que abre una vista del historial de versiones de la unidad de convivencia (la UC es versionada: cada cambio de composición genera una nueva versión con fecha de inicio, sin sobrescribir la anterior).

Botón "Añadir miembro" disponible para roles con permiso de edición.

Para `supervision`: todos los datos visibles, sin botones de edición ni añadir miembro.

---

## 7. Columna lateral

### 7.1 Banner de historia social

Visible para todos los roles si el ciudadano tiene historia social registrada. No aparece si no existe historia.

Muestra: indicador "Historia social activa", nombre del TSR asignado y centro de referencia.

El enlace "Ir a HS" navega a `intervencion/ciudadano/{historia}`. Este enlace solo es clicable para roles con permiso de acceso a la historia social (`intervencion`). Para el resto de roles el enlace está presente visualmente pero no es navegable, comunicando que existe una historia sin dar acceso a su contenido.

La historia social de un ciudadano es única y permanente. No se cierra: lo que tiene ciclo de vida son los planes (PISO) y las prestaciones dentro de ella. El banner siempre está visible mientras exista la historia, sin condición de estado.

### 7.2 Otras prestaciones

Muestra las prestaciones y actividades del ciudadano que no requieren historia social abierta: actividades de centro (talleres, cursos), teleasistencia, ayuda a domicilio básica, y cualquier otra prestación registrada en VIDA sin proceso de intervención asociado.

Los datos se leen desde la tabla `ciudadano_prestaciones_resumen` del módulo Ciudadanía. Esta tabla es alimentada por observers de cada módulo origen (Centros, Teleasistencia, Prestaciones...) cuando se crea o cambia el estado de una prestación. La pantalla nunca consulta directamente las tablas de los módulos origen.

Estructura de `ciudadano_prestaciones_resumen`:

| Campo | Tipo | Descripción |
|---|---|---|
| `ciudadano_id` | bigint FK | |
| `modulo_origen` | string | `centros`, `teleasistencia`, `prestaciones`... |
| `origen_id` | bigint | Id en la tabla origen, para el enlace de detalle |
| `tipo` | string | Clave de catálogo: `actividad_centro`, `teleasistencia`, `ayuda_domicilio`... |
| `descripcion` | string | Nombre legible, desnormalizado intencionalmente |
| `estado` | enum | `activo`, `en_tramite`, `finalizado`, `denegado`, `baja` |
| `fecha_inicio` | date | |
| `fecha_fin` | date nullable | |
| `updated_at` | timestamp | |

El widget muestra los 4 registros más recientes ordenados por estado (activos primero) y fecha. El enlace "Ver todo" lleva a una vista expandida. Si no hay registros, el widget no aparece.

Los estados se muestran con badges de color:
- `activo` → verde
- `en_tramite` → ámbar
- `finalizado` → gris
- `denegado` / `baja` → rojo

Visible para todos los roles con acceso a la ficha. No requiere permisos adicionales: que un ciudadano participe en un taller de acuarela o tenga teleasistencia no es información restringida.

### 7.3 Centro de referencia y TSR

Nombre del centro de referencia, dirección y UTS/distrito. TSR asignado con nombre, avatar y fecha de asignación.

Se lee desde `HistoriaSocial` si existe. Si no existe historia social, este widget no aparece.

### 7.4 Actividad reciente

Lista cronológica inversa de los últimos eventos registrados sobre el ciudadano: alta, primera demanda, apertura de historia social, cambios de estado de prestaciones. Máximo 5 eventos en el widget, con enlace "Ver auditoría completa" al final.

Visible para todos los roles con acceso a la ficha.

### 7.5 Permisos del rol activo

Widget informativo que muestra qué capas puede ver y editar el profesional que está consultando la ficha, según su rol. No es configurable ni interactivo.

Útil cuando un profesional de tramitación recibe preguntas sobre datos que no puede ver (situación social, historia) y necesita explicar al ciudadano por qué no puede responderlas.

---

## 8. Relación con otras pantallas

| Pantalla | Relación |
|---|---|
| `intervencion/ciudadano/{historia}` | Destino del enlace "Ir a HS" del banner. Pantalla de trabajo clínico del TSR, con herramientas de intervención. Solo accesible con rol `intervencion`. |
| `ciudadania/ciudadano/{ciudadano}` (otro ciudadano) | Destino de "Ver ficha" en miembros de la UC. |
| `ciudadania/buscar` | Origen habitual de navegación a esta pantalla. |
| `ciudadania/alta` | El alta crea el ciudadano; tras confirmar el alta con acción "Ir a ficha", el destino es esta pantalla. |

---

## 9. Decisiones de diseño

**Una pantalla por capa de acceso, no una pantalla con secciones ocultas.** La alternativa de una pantalla única que muestre u oculte secciones según el rol fue descartada: mezcla responsabilidades y genera confusión sobre qué ve cada profesional. Dos pantallas con propósitos claros es más mantenible.

**La primera demanda es inmutable.** Es el registro del momento del alta, no un campo editable del perfil. Si la situación del ciudadano cambia, eso se registra en la historia social, no modificando la demanda original.

**`ciudadano_prestaciones_resumen` como tabla de agregación.** Las prestaciones sin historia social provienen de múltiples módulos (Centros, Teleasistencia, Prestaciones...). Una query directa a cada módulo acoplaría esta pantalla a la implementación de cada uno. La tabla de agregación desacopla el widget de los módulos origen y hace la query trivial. Cada módulo es responsable de mantener sus filas en esta tabla mediante observers o eventos de dominio.

**Historia social única y permanente.** Cada ciudadano tiene como máximo una historia social, nunca se cierra. Lo que tiene ciclo de vida son los planes (PISO) y las prestaciones dentro de ella. Esta decisión refleja el modelo de la historia sanitaria y el marco normativo del Decreto 51/2023.

**`supervision` ve todo, edita nada.** El rol de supervisión necesita visibilidad completa para su función de control y calidad, pero no debe poder modificar datos operativos. La separación es limpia: un solo rol, dos comportamientos según la acción.

---

## 10. Pendientes de fases posteriores

- **Vista expandida de prestaciones**: pantalla completa accesible desde "Ver todo" en el widget de otras prestaciones. Pendiente de diseño.
- **Vista de historial UC**: pantalla o modal con el histórico de versiones de la unidad de convivencia. Pendiente de diseño.
- **Integración HSU-CM**: cuando esté disponible la integración con la Historia Social Única de la Comunidad de Madrid, revisar si prestaciones de otras administraciones aparecen en el widget de otras prestaciones o en una sección separada.
- **Índice único en `ciudadanos` para historia social**: añadir `UNIQUE(ciudadano_id)` en `historias_sociales` para garantizar en base de datos la unicidad de historia por ciudadano.
