# UI Plazas y recursos — Especificación funcional

> **Referencia:** `docs/front/ui-plazas-recursos.md`
> **Módulos afectados:** `Modules/Intervencion` · `Modules/Supervision` · `Modules/Centros`
> **Documentos relacionados:** `docs/front/ui-intervencion.md` · `docs/front/ui-supervision.md` · `docs/modulo-centros.md`

---

## 1. Visión general del flujo

El flujo de asignación de recursos (plazas, sesiones de actividad) involucra tres actores con responsabilidades separadas y momentos distintos:

| Actor | Rol | Responsabilidad |
|---|---|---|
| TSR de primaria | `intervencion` (centro ASP) | Prescribe el recurso adecuado para el ciudadano. No gestiona disponibilidad ni plazas concretas. |
| TS del centro receptor | `intervencion` (centro con plazas) | Gestiona la lista de espera y asigna la plaza concreta cuando decide que es el momento. |
| Supervisor del centro | `supervision` (centro con plazas) | Gestiona el inventario de plazas: altas, bajas, disponibilidad. |

El modelo de datos subyacente es uniforme para todos los tipos de recurso: `Prescripcion` con `tipo_destino = coleccion_plazas` o `sesion_actividad`. Un ciudadano esperando plaza de pernocta y otro esperando sesión de terapia pasan por el mismo circuito con la misma pantalla.

**Principio de ocultación de complejidad:** el TSR de primaria nunca ve plazas individuales, estados de ocupación ni detalles del inventario. Ve tipos de recurso, centro o red de destino, y estado de su prescripción. La complejidad operativa es responsabilidad del TS del centro receptor.

**Principio de acceso unificado:** cuando el TS del centro receptor necesita información del ciudadano para tomar decisiones en la lista de espera, accede a la ficha del ciudadano con las reglas estándar de acceso de Nivel 2. El acceso queda registrado en la auditoría. El TSR de referencia lo verá en su lista de accesos y el contexto (profesional del centro receptor) lo hace autoexplicativo. No se crea ningún mecanismo de acceso especial ni se duplica información.

---

## 2. Heurística de búsqueda de destino

Cuando el TSR de primaria inicia una prescripción, el sistema determina dónde buscar según estas reglas, en orden:

1. Si existe una **red de centros** que ofrezca el tipo de recurso requerido y sea accesible para este ciudadano → buscar en la red (disponibilidad agregada de todos sus centros miembros).
2. Si no hay red → buscar en **centros individuales** que ofrezcan el tipo de recurso.

Dentro del paso 2, y solo si la colección de plazas o la red tiene `criterio_territorial = true`:

- El sistema ordena los resultados por proximidad al domicilio del ciudadano (distancia desde `ciudadano.direccion`).
- Si el ciudadano no tiene domicilio registrado (PSH), el criterio territorial no aplica aunque el recurso lo tenga: se presenta la lista sin orden geográfico.

El TSR no ve esta lógica. Ve una lista de opciones —con disponibilidad visible— ordenada de la forma más útil para él. En muchos casos habrá una sola opción y simplemente confirma.

---

## 3. UI — TSR de primaria: prescripción de recurso

### 3.1 Punto de entrada

Nueva herramienta en la barra de herramientas de `CiudadanoPage` (módulo Intervención), junto a Entrevista, Anotación, Derivación y Coordinación. Nombre: **«Prescribir recurso»**. Icono: `heroicon-o-building-storefront` o similar.

La herramienta es visible si el ciudadano tiene una Historia Social abierta. No se requiere plan de intervención activo: hay ciudadanos (personas mayores con deterioro progresivo, por ejemplo) para quienes se prescriben recursos sin que exista un plan de intervención en curso. La Historia Social no puede estar cerrada mientras exista una prescripción activa, lo que el sistema garantiza impidiendo el cierre si hay prescripciones en estados `pendiente`, `en_lista_espera`, `asignada` o `activa`.

### 3.2 Modal: flujo en tres pasos

El modal tiene tres pasos con navegación hacia atrás. El avance requiere completar el paso actual.

---

**Paso 1 — Tipo de recurso**

Selector de tipo de recurso. Las opciones visibles se determinan dinámicamente según los recursos disponibles configurados en el sistema para el ámbito territorial del ciudadano. No se muestran tipos sin oferta activa.

Ejemplos de tipos (del catálogo `tipos_actividad` y tipos de plaza):
- Plaza de pernocta
- Plaza de día
- Plaza residencial
- Sesión de actividad grupal (taller, grupo de apoyo, etc.)

El TSR selecciona uno. No ve nada más de este paso.

---

**Paso 2 — Selección de destino**

El sistema aplica la heurística de §2 y presenta las opciones disponibles.

**Vista sin criterio territorial:**
Lista de centros o redes con el tipo de recurso, con columnas: nombre, plazas disponibles ahora, en lista de espera. Sin orden geográfico.

**Vista con criterio territorial:**
Igual, pero ordenada por proximidad al domicilio del ciudadano. Cada fila muestra la distancia aproximada («a 1,2 km»). Si el ciudadano no tiene domicilio, se muestra un aviso discreto y la lista sin orden geográfico.

El TSR selecciona el destino. Si hay una sola opción, aparece preseleccionada pero el TSR debe confirmar explícitamente.

---

**Paso 3 — Confirmación**

Resumen de la prescripción:
- Ciudadano
- Tipo de recurso
- Destino (centro o red)
- Disponibilidad actual (plaza disponible / entrará en lista de espera)
- Campo de notas para el TS del centro receptor (opcional, texto libre)

**Vinculación con el plan** *(condicional):* si el ciudadano tiene un plan de intervención en estado `activo` o `borrador` con compromisos del Ayuntamiento que referencian una prestación compatible con el tipo de recurso prescrito, el sistema muestra un selector opcional: «Vincular a un compromiso del plan». El TSR puede elegir uno de esos compromisos o dejar la prescripción sin vincular.

La vinculación es un enlace de referencia, no una sincronización de estado. El compromiso del plan no cambia de estado cuando la prescripción avanza: el plan es diseño de intervención, no gestión operativa. El enlace permite al TSR navegar desde el plan a la prescripción para consultar su estado real en cualquier momento.

Si no hay plan activo con compromisos compatibles, este selector no aparece.

El TSR confirma. El sistema:

1. Crea la `Prescripcion` con estado `asignada` si hay plaza disponible, o `en_lista_espera` si no.
2. Si `en_lista_espera`: crea el registro `ListaEspera` y genera una alerta de tipo `aviso` al TSR cuando se produzca movimiento en la lista.
3. Crea un `Apunte` en la Historia Social de tipo `derivacion` con referencia a la prescripción. El apunte es inmutable una vez creado.
4. Cierra el modal y actualiza la banda de recursos activos en `CiudadanoPage`.

### 3.3 Banda de recursos activos en CiudadanoPage

Junto a la banda del PISO activo, se añade una sección «Recursos prescritos» que lista las prescripciones activas del ciudadano con: tipo de recurso, destino, estado (`en_lista_espera` / `asignada` / `activa`), fecha. Solo lectura desde aquí.

El TSR puede cancelar una prescripción desde esta sección (con motivo obligatorio). La cancelación libera la plaza si estaba asignada.

---

## 4. UI — TS del centro receptor: gestión de lista de espera y asignación

### 4.1 Punto de entrada

Nuevo ítem en el sidebar del módulo Intervención: **«Recursos»** o **«Plazas»**. 

**Condicional:** solo visible para profesionales cuyo centro tiene `tiene_plazas = true` o tiene actividades con `modo_acceso = prescripcion`. La condición se evalúa sobre el centro de adscripción del profesional autenticado, no sobre el ciudadano.

Posición en el sidebar: después de «Mis casos» y antes de «Buscar ciudadano».

### 4.2 Pantalla principal: RecursosPage

La pantalla tiene dos pestañas.

---

**Pestaña «Pendientes»**

Lista de prescripciones que han llegado al centro o red y están en estado `pendiente` o `en_lista_espera`. Ordenación por defecto: fecha de entrada (más antigua primero), pero reordenable manualmente.

Columnas:
- Ciudadano (nombre, enlace a su ficha — acceso Nivel 2, queda registrado)
- Tipo de recurso solicitado
- Fecha de prescripción
- Posición en lista de espera (si aplica)
- Notas del TSR prescriptor (si las dejó)
- Acciones: «Asignar» · «Mover en lista» · «Cancelar»

**Pestaña «Activas»**

Prescripciones en estado `asignada` o `activa` (ciudadano ya usando el recurso). Permite registrar la fecha de inicio efectiva, marcar como finalizada o cancelar.

---

### 4.3 Sub-flujo: asignación de plaza concreta

Cuando el TS del centro pulsa «Asignar» en una prescripción, se abre un modal con:

**Información del ciudadano y la prescripción:**
- Nombre, edad, datos de contacto básicos
- Tipo de plaza solicitada
- Notas del TSR prescriptor
- Enlace «Ver ficha completa» → abre `ciudadania.ciudadano.ficha` en nueva pestaña (acceso Nivel 2, registrado)

**Inventario disponible:**

Lista de plazas del tipo requerido, con información suficiente para decidir:

| Plaza | Espacio | Tipo | Estado | Ocupación prevista hasta |
|---|---|---|---|---|
| 204-A | Hab. individual | Pernocta | Disponible | — |
| 204-B | Hab. compartida | Pernocta | Disponible | — |
| 205 | Matrimonial | Pernocta | Ocupada | 15 jul (estimado) |

El TS puede asignar una plaza de tipo diferente al solicitado si lo considera apropiado (por ejemplo, habitación de matrimonio para una persona individual). En ese caso el sistema muestra un aviso: «El tipo de espacio seleccionado difiere del solicitado. Puede añadir una nota justificando la decisión.» La nota es opcional pero recomendada.

Al confirmar:
1. `Prescripcion.estado` → `asignada`, `plaza_id` se rellena.
2. `Plaza.estado` → `ocupada`.
3. El registro `ListaEspera` pasa a `estado = asignada`.
4. Se genera una alerta de tipo `aviso` al TSR de referencia del ciudadano informando de la asignación.
5. El TS puede añadir una nota interna que queda en la prescripción (no en la Historia Social).

### 4.4 Sub-flujo: gestión de la lista de espera

El TS del centro puede reordenar la lista de espera manualmente. El sistema no impone criterio FIFO ni ningún otro criterio automático más allá de la fecha de entrada como orden inicial. La responsabilidad de la gestión justa de la lista es del profesional.

Para reordenar: drag-and-drop en la lista o campos de posición editables. Cada cambio de posición queda registrado con fecha, profesional que lo realizó y posición anterior (trazabilidad).

El TS también puede consultar la ficha de cualquier ciudadano en lista antes de decidir el orden, usando el enlace de acceso Nivel 2.

**Previsión de liberaciones:**

En la parte superior de la pestaña «Pendientes», una sección compacta muestra las plazas del tipo más demandado que tienen fecha estimada de liberación próxima (basada en `Prescripcion.fecha_fin` estimada de las prescripciones activas). Esto permite al TS anticipar disponibilidad y contactar con los ciudadanos en lista de espera antes de que la plaza quede oficialmente libre.

---

## 5. UI — Supervisor: gestión del inventario de plazas

Especificado en `docs/front/ui-supervision.md §6`. Se añaden aquí los elementos que complementan esa especificación una vez definido el flujo completo.

### 5.1 Vista de ocupación y previsión

En la pantalla `PlazasPage` del supervisor, además del estado actual de cada plaza, se muestra:

- **Fecha estimada de liberación** para las plazas en estado `ocupada`: extraída de `Prescripcion.fecha_fin` de la prescripción activa. Puede ser nula si la prescripción no tiene fecha de fin definida.
- **Plazas en mantenimiento** (estado `no_disponible`): con el motivo y la fecha desde la que están en ese estado. Si llevan más de N días en mantenimiento (N configurable), se resaltan para llamar la atención del supervisor.

### 5.2 Altas y bajas de plaza

**Alta:** además de los campos ya especificados (nombre, tipo de espacio, notas), el supervisor puede indicar si la plaza está disponible inmediatamente o entra en estado `no_disponible` desde el alta (por ejemplo, una plaza en obras).

**Baja temporal** (`no_disponible`): requiere motivo. Si la plaza tiene una prescripción activa, el sistema bloquea la baja temporal y muestra un error: «Esta plaza tiene un ciudadano asignado. Gestiona el traslado antes de marcarla como no disponible.»

**Baja definitiva** (eliminación lógica): solo posible si la plaza está `disponible` y no tiene prescripciones activas. Requiere confirmación. Si tiene prescripciones históricas, la plaza pasa a `deleted_at` pero los registros históricos se conservan.

### 5.3 Relación con la lista de espera

El supervisor ve, para cada colección de plazas, el número de prescripciones en lista de espera. No gestiona la lista directamente (eso es responsabilidad del TS del centro), pero puede verla en modo lectura para tener visión del estado de demanda.

---

## 6. Mapa de rutas nuevas

### Módulo Intervención

```
/intervencion/recursos                → RecursosPage    [condicional: centro con plazas]
```

La herramienta «Prescribir recurso» en `CiudadanoPage` no tiene ruta propia: es un modal que se abre desde la pantalla existente `/intervencion/ciudadano/{historia}`.

### Módulo Supervision

No hay rutas nuevas. Los cambios son extensiones de `PlazasPage` ya especificada en `supervision.md`.

---

## 7. Componentes nuevos

### Módulo Intervención

| Componente | Tipo | Descripción |
|---|---|---|
| `RecursosPage` | Livewire page | Pantalla de gestión de lista de espera y asignación para TS del centro |
| `PrescribirRecursoModal` | Livewire component | Modal de 3 pasos para prescripción desde CiudadanoPage |

`PrescribirRecursoModal` se monta dentro de `CiudadanoPage` igual que los modales de herramientas existentes.

---

## 8. Interacciones entre módulos

| Origen | Destino | Qué |
|---|---|---|
| `PrescribirRecursoModal` | `Modules/Centros` | Lee disponibilidad de `ColeccionPlazas` y `Red` según tipo de recurso y criterio territorial |
| `PrescribirRecursoModal` | `Modules/Centros` | Crea `Prescripcion` y `ListaEspera` si aplica |
| `PrescribirRecursoModal` | `Modules/Intervencion` | Crea `Apunte` de tipo `derivacion` en la Historia Social |
| `RecursosPage` | `Modules/Centros` | Lee y actualiza `Prescripcion`, `ListaEspera`, `Plaza` |
| `RecursosPage` | `Modules/Mensajes` | Genera alerta `aviso` al TSR cuando se asigna una plaza |
| `PlazasPage` (Supervision) | `Modules/Centros` | CRUD de `Plaza`, lectura de `Prescripcion` activa para previsión |

---

## 9. Decisiones de diseño pendientes

- **Criterio de ordenación de la lista de espera en v1:** el sistema no implementa criterios automáticos de prioridad (baremo, vulnerabilidad, antigüedad). La lista se ordena por fecha de entrada y el TS la gestiona manualmente. Cuando se implemente el módulo de Baremos, la lista podrá mostrar una puntuación sugerida sin forzar el orden.
- **Notificación al ciudadano:** cuando se asigna una plaza, el sistema alerta al TSR de referencia. La notificación directa al ciudadano depende del módulo de comunicaciones ciudadanas, pendiente de diseño.
- **Prescripción y plan de intervención:** la prescripción no requiere plan activo, solo Historia Social abierta. Cuando existe un plan con compromisos del Ayuntamiento compatibles, el sistema ofrece vincular la prescripción a uno de esos compromisos. El vínculo se almacena en `Prescripcion.compromiso_id` (nullable FK a `compromisos_ayuntamiento`): la prescripción conoce al compromiso, el plan no se modifica. Desde la vista del plan, la prescripción asociada se obtiene por query inversa (`Prescripcion::where('compromiso_id', $id)`). El plan no sincroniza estado con la prescripción: es diseño de intervención, no gestión operativa. Añadir campo `compromiso_id` nullable a la tabla `prescripciones` si no existe.
- **Criterio territorial y PSH:** si el ciudadano es PSH y tiene coordenadas de pernocta (en lugar de domicilio), evaluar si esas coordenadas pueden usarse como punto de referencia para el orden geográfico en recursos con criterio territorial.

---

*Documento elaborado en fase de diseño. Versión inicial: junio 2026.*
