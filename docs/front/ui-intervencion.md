# UI — Rol Intervención
## `docs/front/ui-intervencion.md`

> Documento de diseño de interfaz para el rol `intervencion` de VIDA 360.
> Cubre todas las pantallas del flujo de trabajo del Trabajador Social de Referencia (TSR).
>
> **Documento relacionado:** `docs/modulo-intervencion.md`, `docs/modulo-mensajes.md`,
> `docs/modulo-agenda.md`, `docs/modulo-usuarios-permisos.md`
> **Versión inicial:** mayo 2026

---

## 1. Principios generales de este rol

El rol `intervencion` corresponde al Trabajador Social de Referencia (TSR). Su flujo de trabajo
es fundamentalmente **orientado a la jornada**: llega cada mañana y su primera pregunta es
"¿a quién atiendo hoy?". El diseño de la interfaz prioriza esta orientación.

El TSR **consulta pero no gestiona** su disponibilidad en VIDA. Las ausencias y cambios de
agenda los introduce el supervisor a través del módulo de Agenda. El TSR usa las vistas
de semana y mes para orientarse antes de solicitar días libres por los canales de RRHH,
no para modificar su propia agenda.

El término **"apunte"** es un concepto interno del modelo de datos (la entidad base que
unifica todos los eventos de la Historia Social). No debe aparecer en la interfaz. Los
botones de acción usan siempre verbos naturales: "Guardar entrevista", "Crear derivación", etc.

---

## 2. Navegación principal

La barra lateral izquierda es persistente en todas las pantallas del rol. Contiene:

| Ítem | Ruta | Badge |
|---|---|---|
| Agenda | `/agenda` | — |
| Mis casos | `/casos` | Número de ciudadanos asignados |
| Alertas y mensajes | `/mensajes` | Total de alertas + mensajes no leídos (coral si hay alertas sin reconocer) |
| Buscar ciudadano | `/buscar` | — |

La parte inferior del sidebar muestra el avatar con iniciales, nombre completo y
"Intervención · [nombre del CSS]".

---

## 3. Agenda

### 3.1 Descripción

Pantalla de entrada tras el login. No se usa un dashboard intermediario: la agenda
con franja de KPIs contextual actúa como punto de partida de la jornada.

### 3.2 Franja de KPIs

Cuatro métricas fijas en la parte superior, siempre visibles:

| KPI | Descripción | Color de alerta |
|---|---|---|
| Alertas sin reconocer | Alertas del módulo Mensajes pendientes de reconocimiento | Coral si > 0 |
| Seguimientos vencidos | Ciudadanos con fecha de próximo seguimiento superada | Ámbar si > 0 |
| Citas (hoy / semana / mes) | Se actualiza según la vista activa | — |
| Mensajes sin leer | Mensajes del módulo Mensajes no leídos | — |

### 3.3 Selector de vistas

Tres vistas en un segmentado en la barra superior: **Día · Semana · Mes**.
Los botones de navegación anterior/siguiente y "Hoy" funcionan en las tres vistas.

**Vista de día (por defecto al hacer login)**

Cuatro columnas: ayer (atenuado para revisión), hoy (destacado), mañana, pasado mañana.
Cada cita muestra: franja horaria, nombre del ciudadano, tipo de cita.
Código de color por tipo:

| Tipo | Color |
|---|---|
| Entrevista / valoración | Morado (`#EEEDFE` / borde `#534AB7`) |
| Seguimiento | Verde teal (`#E1F5EE` / borde `#0F6E56`) |
| Urgencia | Coral (`#FAECE7` / borde `#993C1D`) + chip "Urgencia" |
| Evento interno / coordinación | Gris (`var(--color-background-secondary)`) |

Los slots libres se muestran con borde punteado y etiqueta "Slot libre" — solo en la
vista de día. Las vistas de semana y mes no muestran slots libres.

**Vista de semana**

Cuadrícula hora × día (lunes a viernes, 08:00–17:00). Las citas son bloques coloreados
en su franja horaria. Los sábados y domingos no se muestran (horario laboral).

**Vista de mes**

Calendario clásico. Cada día muestra contadores por tipo con píldoras de color, ordenados
por prioridad (urgencias primero). Los fines de semana tienen fondo atenuado. El día actual
tiene borde morado (`#534AB7`). No se muestran nombres de ciudadanos en la vista de mes.
Clic en un día navega a la vista de día de esa fecha.

### 3.4 Anotaciones en citas

Las anotaciones o comentarios vinculados a una cita (p. ej. "esta entrevista puede ser
larga") se envían como mensajes a través del módulo de Mensajes, con el contexto de la
cita precargado en el asunto. No se almacenan en la agenda.

### 3.5 No-show

El no-show del ciudadano no se marca en la agenda sino en el seguimiento del caso
(Historia Social). La agenda solo refleja el estado `no_show_ciudadano` como información.

---

## 4. Mis casos

### 4.1 Descripción

Listado de los ciudadanos de los que el TSR es responsable como profesional de referencia.
Paginado, ordenable y filtrable.

### 4.2 Columnas

| Columna | Descripción |
|---|---|
| Ciudadano | Nombre completo, enlace a su Historia Social |
| Próximo seguimiento | Semáforo: vencido (coral) / próximo en 7 días (ámbar) / programado (verde) / sin programar (texto mudo) |
| PISO | Estado del plan de intervención general: Activo / En revisión / Sin PISO. La etiqueta "PISO" es configurable desde Filament (`nombre_plan_asp` en `catalogos_sistema`) |
| Planes especializados | Número de planes de especializada activos. Guión si no hay ninguno |
| Alertas | Icono coral si hay alertas sin reconocer vinculadas al ciudadano |

### 4.3 Filtros

- Búsqueda por nombre
- Estado del próximo seguimiento: todos / vencido / próximo (7 días) / programado / sin programar
- Estado del PISO: todos / activo / en revisión / sin PISO
- Planes especializados: todos / con planes activos / sin planes

### 4.4 Ordenación

Por defecto: próximo seguimiento (vencidos primero). Ordenable también por nombre.

### 4.5 Parámetro de configuración

El literal de la columna "PISO" y su equivalente en todo el interfaz se obtiene de un
parámetro configurable en Filament: `nombre_plan_asp` en `catalogos_sistema`.
Valor por defecto para Madrid: `PISO`. Otros valores posibles: `PI`, `DIS`,
`Plan de intervención`.

---

## 5. Alertas y mensajes

### 5.1 Descripción

Pantalla única con tres pestañas: **Alertas · Avisos · Mensajes**.
El badge del ítem en el sidebar suma los tres contadores.

Botón "Nuevo mensaje" en la barra superior abre el redactor con selector de destinatario
por nombre o por rol+UO.

### 5.2 Pestaña Alertas

Las alertas (`tipo = 'alerta'` en el modelo) requieren reconocimiento explícito en 4 horas
laborales. Si vencen sin reconocer, escalan al supervisor de la UO (un único nivel).

**Lista:** ordered por urgencia (menor tiempo restante primero). Cada item muestra:
punto de no leído, título, origen (módulo generador), tiempo transcurrido, tiempo que
queda para vencer.

**Detalle:** banner coral con cuenta atrás (`Requiere reconocimiento · Vence en Xh`).
Dos acciones: **Reconocer alerta** (botón primario) y **Ir al contexto** (enlace al
origen polimórfico: expediente, lista de espera, etc.).

### 5.3 Pestaña Avisos

Los avisos (`tipo = 'aviso'`) son informativos. No tienen plazo ni escalada.

**Detalle:** sin banner de urgencia. Única acción: "Marcar como leído". La píldora de
origen distingue entre: Módulo Agenda, Sistema, Organización.

### 5.4 Pestaña Mensajes

Mensajería uno a uno entre profesionales. Las conversaciones se organizan en hilos.

**Lista:** hilos ordenados por actividad reciente. Punto de no leído si hay mensajes nuevos.

**Detalle:** hilo de burbujas con diferenciación visual propios/ajenos. Área de composición
con dos acciones auxiliares diferenciadas:
- **Adjuntar documento** (clip): documento genérico.
- **Enlazar expediente** (icono expediente): enlace a la Historia Social de un ciudadano.
  El acceso al enlace respeta el sistema de permisos del módulo de Intervención en el
  momento de acceder. Un usuario sin permisos suficientes no verá el contenido.

El TSR responsable de un expediente puede incorporar un mensaje a la Historia Social
del ciudadano: acción explícita, con posibilidad de editar el contenido antes de
registrarlo. Lo que queda registrado es una copia editada (`comunicacion_interna`)
con visibilidad `profesionales` por defecto. Nunca `ciudadano`.

---

## 6. Buscar ciudadano

### 6.1 Descripción

Búsqueda global en todo el sistema (no solo en los casos propios). Necesaria cuando
llega una derivación del SIA o una urgencia.

### 6.2 Campos de búsqueda

Selector de tipo de búsqueda:

| Tipo | Campo buscado |
|---|---|
| Nombre (por defecto) | `nombre` + `apellido1` + `apellido2` |
| NIF/NIE/Pasaporte | `ciudadano_identificadores.valor` |
| NI-HSU-CM | `ciudadano_identificadores.valor` donde `tipo = 'ni_hsu_cm'` |
| Alias PSH | `ciudadano.alias` |

### 6.3 Resultados

Cada resultado muestra: avatar con iniciales, nombre completo, fecha de nacimiento,
NIF, CSS responsable, domicilio (si no es colectivo protegido), NI-HSU-CM (solo si tiene
Historia Social abierta), estado y TSR de la Historia Social (si existe).

**Tres niveles de acceso según el modelo de permisos:**

**Nivel 1 — Propia UO:** acceso directo. Botones: "Ir a Historia Social" (si existe) o
"Abrir Historia Social" (si no existe). + "Ver ficha".

**Nivel 2 — Otra UO, sin colectivo protegido:** acceso libre con registro de auditoría.
El botón "Ver Historia Social" incluye la nota "(acceso registrado)" en texto pequeño.
No bloquea.

**Nivel 3 — Colectivo especialmente protegido fuera de la UO:** el domicilio aparece
enmascarado con icono de candado. Badge "Colectivo protegido" con icono de escudo.
Botón "Ver Historia Social" desactivado. Única acción disponible: "Solicitar acceso",
que abre un modal con aviso de contexto (VVG, menores, etc.) y campo de justificación
obligatorio. El acceso se habilita tras aprobación del supervisor competente.

### 6.4 Ciudadano sin Historia Social

Si el ciudadano existe en VIDA pero no tiene Historia Social, el bloque correspondiente
muestra: "Registrado en VIDA · Sin Historia Social abierta". El NI-HSU-CM no aparece
(no existe hasta que hay Historia Social). El botón principal es "Abrir Historia Social".

### 6.5 Ciudadano no registrado en VIDA

Al final del listado de resultados, siempre aparece el pie:

> ¿No está la persona que buscas?
> [Dar de alta nuevo ciudadano] ← desactivado hasta implementación del módulo de Ciudadanía

---

## 7. Pantalla del ciudadano (Historia Social)

### 7.1 Descripción

Pantalla de trabajo principal del TSR con un ciudadano. Layout de dos columnas:
izquierda con contexto del ciudadano e historia, derecha con las herramientas de trabajo.

### 7.2 Barra superior

Breadcrumb: `← Mis casos · [Nombre del ciudadano]` + badge de estado de la Historia Social.
Acciones: "Ficha social" (abre la ficha social del ciudadano) y menú de acciones secundarias.

### 7.3 Columna izquierda

**Cabecera del ciudadano**

Datos identificativos: avatar con iniciales, nombre completo, edad, fecha de nacimiento,
NIF, NI-HSU-CM, domicilio, teléfono, fecha de apertura de la HS.

**Unidad de convivencia**

Colapsable. Estado cerrado: avatares apilados de los miembros. Estado abierto: lista de
miembros con nombre, rol en la UC y edad. Clic en un miembro navega a su Historia Social
dentro del mismo layout (la columna izquierda se actualiza; aparece breadcrumb de retorno).

**Historia Social**

Timeline cronológico inverso (más reciente primero). Filtros: Todos / PISO / Entrevistas.
Cada entrada muestra: punto de color por tipo, título, tipo y fecha. Clic expande el
contenido completo + autor.

Código de color de los puntos por tipo:

| Tipo | Color del punto |
|---|---|
| PISO / plan | Ámbar (`#854F0B`) |
| Entrevista | Morado (`#534AB7`) |
| Valoración (ficha) | Verde teal (`#1D9E75`) |
| Escala | Morado claro (`#7F77DD`) |
| Derivación | Verde (`#0F6E56`) |
| Anotación | Gris (`#888780`) |

**PISO activo**

Banda fija entre la barra superior y el área de trabajo (en la columna derecha). Muestra:
nombre del plan, estado, versión y fecha de próxima revisión. Botón "Ver PISO".

### 7.4 Columna derecha — herramientas

**Estado neutro (selector de herramientas)**

Cuadrícula de 4 columnas con las herramientas disponibles. Cada herramienta muestra
un icono con fondo de color semántico y etiqueta.

| Herramienta | Icono | Comportamiento |
|---|---|---|
| Entrevista | `ti-user-check` (morado) | Formulario inline. Botón: "Guardar entrevista" |
| Anotación | `ti-note` (gris) | Formulario inline. Botón: "Guardar anotación" |
| Valoración | `ti-clipboard-check` (verde teal) | Pantalla completa. Botón: "Abrir en pantalla completa" |
| Escala | `ti-chart-bar` (morado) | Pantalla completa. Botón: "Abrir en pantalla completa" |
| Derivación | `ti-send` (azul) | Formulario inline. Botón: "Crear derivación" |
| Gestión / coordinación | `ti-arrows-exchange` (ámbar) | Formulario inline. Botón: "Guardar gestión" |
| Informes | `ti-file-text` (coral) | Pantalla completa. Botón: "Abrir en pantalla completa" |

**Estado activo (herramienta seleccionada)**

La cuadrícula pasa a formato compacto de una sola fila (referencia visual). El formulario
de la herramienta aparece debajo.

**Herramientas inline**

*Entrevista:* selector de tipo (seguimiento / inicial / urgencia / informativa), modalidad
(presencial / telefónica / videollamada / domicilio), campo de notas, checkboxes opcionales
"Generar valoración asociada" y "Programar cita de seguimiento".

*Anotación:* campo de contenido libre, selector de visibilidad (Profesionales / Privada —
solo yo). Las anotaciones privadas no son accesibles para ningún otro usuario ni se
transfieren al cambiar de TSR responsable.

*Derivación:* selector de servicio receptor (del catálogo de prestaciones), selector de
urgencia (ordinaria / preferente / urgente), campo de motivo, checkbox "Adjuntar informe
de derivación".

*Gestión / coordinación:* selector de tipo de gestión, campo de recurso/interlocutor,
campo de descripción.

**Herramientas a pantalla completa**

*Valoración:* selector de ficha por ámbito (socioeconómica, familiar, vivienda, salud —
catálogo configurable desde Filament). Muestra la última ficha cumplimentada del mismo
tipo como referencia. Se abre en pantalla completa con la historia colapsada al mínimo.

*Escala:* selector de instrumento estandarizado (catálogo configurable desde Filament:
vulnerabilidad social, empleabilidad, Barthel, etc.). Muestra la última aplicación y
resultado. Se abre en pantalla completa.

*Informes:* selector de plantilla (catálogo configurable desde Filament). Los datos del
ciudadano se precargan automáticamente en las secciones de tipo `automatico` de la
plantilla. Se abre en pantalla completa con flujo wizard y firma con AutoFirma.

### 7.5 Distinción entre Valoración y Escala

| Concepto | Definición | Ejemplos |
|---|---|---|
| Ficha de valoración | Diagnóstico del profesional sobre un ámbito de la situación del ciudadano | Valoración socioeconómica, familiar, de vivienda |
| Escala | Instrumento estandarizado de medición con puntuación | Escala de vulnerabilidad social, Barthel, Zarit |

Ambas generan una entrada en la Historia Social con tipo diferente y punto de color
diferente en el timeline.

### 7.6 Visibilidad de los registros

Toda acción registrada en la Historia Social tiene un nivel de visibilidad:

| Nivel | Descripción |
|---|---|
| `privada` | Solo visible para el autor. No se transfiere. |
| `profesionales` | Visible para cualquier profesional con acceso a la historia. |
| `ciudadano` | Visible desde la carpeta ciudadana (cuando esté activa). |

La visibilidad `profesionales` no equivale a que el ciudadano nunca pueda acceder: tiene
derecho de acceso a su historia si lo solicita formalmente (RGPD). Eso es un proceso
administrativo separado, no una funcionalidad de la plataforma.

---

## 8. Decisiones de diseño transversales

| Decisión | Resolución |
|---|---|
| Dashboard vs agenda al hacer login | Acceso directo a la agenda. Los KPIs actúan como dashboard contextual en la misma pantalla. |
| Gestión de agenda (vacaciones, días libres) | Solo consulta. El TSR no modifica su propia agenda en VIDA. La gestión es del supervisor. |
| Anotaciones en citas de agenda | Se envían como mensajes del módulo Mensajería con contexto precargado. No se almacenan en la agenda. |
| No-show del ciudadano | Se registra en la Historia Social (seguimiento del caso), no en la agenda. |
| Término "apunte" | Interno al modelo de datos. No aparece en la UI. Se usan verbos y términos naturales. |
| Configurabilidad de "PISO" | Parámetro `nombre_plan_asp` en `catalogos_sistema`, gestionable desde Filament. |
| NI-HSU-CM en búsqueda | Solo visible en resultados que tienen Historia Social abierta. |
| Etiqueta de UO en búsqueda | Muestra directamente el nombre del CSS, sin prefijo "UO responsable". |
| Ciudadano no en VIDA | Botón "Dar de alta nuevo ciudadano" al final de los resultados. Sin funcionalidad hasta implementación del módulo de Ciudadanía. |
| Herramientas complejas | Valoración, Escala e Informes se abren en pantalla completa. La historia queda colapsada para dar contexto mínimo. |
| Mensajes con información de ciudadanos | Solo se permiten enlaces a la Historia Social (con control de permisos en el momento de acceso). No se copia contenido del expediente en el mensaje. |
