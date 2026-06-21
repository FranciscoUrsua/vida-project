# Saneamiento de `vida/resources/css/app-operativo.css`

## Motivo

`vida/resources/scss/app-operativo.scss` importa Bootstrap y, a continuación, reinyecta `vida/resources/css/app-operativo.css`. Eso deja a Bootstrap como una base incompleta: sus componentes existen, pero quedan sobrescritos o duplicados por un archivo heredado de 2238 líneas.

El resultado práctico es conocido:

- botones equivalentes con APIs distintas;
- títulos y jerarquías tipográficas dibujados a mano;
- formularios y tablas que no comparten comportamiento base;
- modales paralelos para dominios distintos;
- demasiado tiempo de revisión visual pantalla a pantalla.

Este documento propone cortar ese patrón de trabajo.

## Diagnóstico resumido

En `app-operativo.css` conviven varios sistemas que resuelven lo mismo que Bootstrap ya cubre:

- botones: `ficha-btn`, `uc-btn`, `plan-btn`, botones específicos de mensajes y otros `*-btn-*`;
- modales: `hs-modal`, `uc-modal`, `plan-modal`, `mensajes-buzon__modal`;
- tablas: `agenda-page__table`, `mis-casos-page__table`, `plan-table`;
- tipografía de navegación/topbar dibujada con tamaños manuales;
- layouts de pantalla mezclando grid propio con utilidades parciales de Bootstrap.

Mientras ese archivo siga siendo el lugar donde se decide la apariencia de cada pantalla, la consistencia va a depender de inspección manual, no de un sistema.

## Decisión recomendada

### Regla 1

Bootstrap debe ser la capa base real para:

- botones;
- formularios;
- tipografía de interfaz;
- tablas;
- dropdowns;
- modales;
- spacing estructural.

### Regla 2

`app-operativo.css` no debe seguir creciendo con nuevos componentes visuales.

Desde este punto:

- si un problema lo resuelve Bootstrap, se usa Bootstrap;
- si hace falta una variante VIDA, se monta encima de Bootstrap en SCSS modular;
- no se añaden nuevas familias `*-btn`, `*-modal`, `*-table`, `*-input` en el CSS heredado.

### Regla 3

Las clases propias deben quedarse para:

- composición de pantallas operativas;
- layout de dominio;
- piezas que Bootstrap no modela por defecto;
- semántica de producto (`record-screen`, `cases-screen`, etc.).

No para reinventar primitives.

## Plan por bloques

### Fase 1. Congelación del CSS heredado

Objetivo: impedir que siga creciendo la deuda.

Acciones:

1. Declarar `app-operativo.css` como archivo en retirada.
2. No aceptar nuevas reglas de componentes base en ese archivo.
3. Añadir nuevas reglas sólo en SCSS modular (`_op-components.scss`, `_op-layout.scss`, utilidades nuevas si hacen falta).

Criterio de éxito:

- ninguna PR nueva añade `*-btn`, `*-modal`, `*-input`, `*-table` al CSS heredado.

### Fase 2. Unificación de primitives

Objetivo: reducir las familias paralelas.

Acciones:

1. Sustituir `ficha-btn`, `uc-btn`, `plan-btn` y botones equivalentes por:
   - `btn`;
   - `btn-primary`;
   - `btn-outline-secondary`;
   - `btn-sm`;
   - utilidades Bootstrap.
2. Sustituir inputs/selects/textareas propios por:
   - `form-control`;
   - `form-select`;
   - `form-check`;
   - `input-group` cuando toque.
3. Sustituir tablas específicas por:
   - `table`;
   - `table-sm`;
   - `table-hover`;
   - wrappers de scroll cuando haga falta.

Criterio de éxito:

- una acción primaria se reconoce siempre por las mismas clases;
- un campo de formulario no depende de una clase de pantalla para verse correctamente.

### Fase 3. Unificación de overlays

Objetivo: dejar de mantener varios sistemas de modal con la misma responsabilidad.

Acciones:

1. Elegir una base para overlays operativos:
   - `modal` de Bootstrap para diálogos convencionales;
   - `offcanvas` para paneles laterales o detalle largo.
2. Migrar primero:
   - `hs-modal`;
   - `uc-modal`;
   - `plan-modal`;
   - `mensajes-buzon__modal`.
3. Mantener sólo las clases de contenido de dominio dentro del contenedor Bootstrap.

Criterio de éxito:

- backdrop, cierre, cabecera, footer, spacing y acciones comparten implementación.

### Fase 4. Limpieza estructural de layout operativo

Objetivo: separar layout de apariencia.

Acciones:

1. Dejar en `app-operativo.css` únicamente lo que aún no haya sido migrado.
2. Mover topbar, sidebar y layout shell a SCSS modular.
3. Consolidar en `_op-layout.scss` y `_op-components.scss` lo que siga siendo necesario.
4. Borrar bloques muertos tras cada migración.

Criterio de éxito:

- `app-operativo.css` deja de ser el centro del sistema y pasa a ser un remanente transitorio.

## Orden recomendado de trabajo

Para no volver a inspeccionar toda la app a mano, el trabajo debe hacerse por superficies completas:

1. shell operativo compartido: topbar, sidebar, dropdown de usuario;
2. botones y formularios compartidos;
3. modales compartidos;
4. tablas/listados compartidos;
5. pantallas operativas principales:
   - agenda;
   - mis casos;
   - expediente/intervención;
   - ficha del ciudadano;
   - búsqueda;
   - mensajes.

La regla es simple: cuando se entra en una superficie, se sale de ella con primitives unificadas, no con otro parche local.

## Qué haría a continuación

Si seguimos con esta línea, el siguiente lote debería ser este:

1. refactor del shell operativo compartido para que topbar/sidebar/usermenu usen Bootstrap como base;
2. sustitución de las familias `ficha-btn`, `uc-btn` y `plan-btn`;
3. inventario de modales operativos y elección de patrón único.

Ese lote ya no sería un retoque visual. Sería el punto donde la deuda empieza a bajar de verdad.
