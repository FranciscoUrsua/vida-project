# UI — Rol Intervención y navegación operativa
## `docs/front/ui-intervencion.md`

> Documento de diseño de interfaz para el rol `intervencion` de VIDA 360.
> Cubre todas las pantallas del flujo de trabajo del Trabajador Social de Referencia (TSR),
> el mapa de navegación entre pantallas y la ficha del ciudadano accesible para todos los
> roles operativos.
>
> **Documentos relacionados:** `docs/modulo-intervencion.md`, `docs/modulo-mensajes.md`,
> `docs/modulo-agenda.md`, `docs/modulo-usuarios-permisos.md`,
> `docs/front/ui-ficha-ciudadano.md`, `docs/front/alta-ciudadano-funcional.md`
>
> **Versión inicial:** mayo 2026  
> **Actualizado:** junio 2026 — navegación entre pantallas, ficha del ciudadano,
> comportamiento de widgets condicionales, mapa de enlaces por rol

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
| Agenda | `/intervencion/agenda` | — |
| Mis casos | `/intervencion/casos` | Número de ciudadanos asignados |
| Alertas y mensajes | `/intervencion/mensajes` | Total de alertas + mensajes no leídos (coral si hay alertas sin reconocer) |
| Buscar ciudadano | `/intervencion/buscar` | — |
| Alta de ciudadano | `/ciudadania/alta` | — |

La parte inferior del sidebar muestra el avatar con iniciales, nombre completo y
"Intervención · [nombre del CSS]".

El ítem "Alta de ciudadano" es compartido con otros roles operativos (`tramitacion`,
`consulta_basica`). El acceso a la ruta está protegido por middleware `role_or_permission`.

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
| Entrevista / valoración | `var(--color-primary-subtle)` / borde `var(--color-primary)` |
| Seguimiento | `var(--color-success-subtle)` / borde `var(--color-success)` |
| Urgencia | `var(--color-danger-subtle)` / borde `var(--color-danger)` + chip "Urgencia" |
| Evento interno / coordinación | `var(--color-background-secondary)` |

Los slots libres se muestran con borde punteado y etiqueta "Slot libre" — solo en la
vista de día. Las vistas de semana y mes no muestran slots libres.

**Vista de semana**

Cuadrícula hora × día (lunes a viernes, 08:00–17:00). Las citas son bloques coloreados
en su franja horaria. Los sábados y domingos no se muestran (horario laboral).

**Vista de mes**

Calendario clásico. Cada día muestra contadores por tipo con píldoras de color, ordenados
por prioridad (urgencias primero). Los fines de semana tienen fondo atenuado. El día actual
tiene borde usando `var(--color-primary)`. No se muestran nombres de ciudadanos en la vista
de mes. Clic en un día navega a la vista de día de esa fecha.

### 3.4 Enlaces desde la agenda

El enlace del nombre del ciudadano en una cita varía según el rol y el estado de la cita:

| Condición | Destino |
|---|---|
| Rol `intervencion` + cita con `historia_id` | `intervencion.ciudadano.show` (pantalla de trabajo clínico) |
| Rol `intervencion` + cita sin `historia_id` | No clicable — TODO: enlazar a `ciudadania.ciudadano.ficha` cuando la cita incluya `ciudadano_id` |
| Rol `tramitacion` / `consulta_basica` | `ciudadania.ciudadano.ficha` (ficha del ciudadano) |
| Cita de tipo `evento` (sin ciudadano) | No clicable en ningún rol |

La distinción por rol responde a propósitos de uso diferentes: el TSR accede desde la agenda
para atender a la persona; tramitación y consulta básica acceden para gestión administrativa.

### 3.5 Anotaciones en citas

Las anotaciones o comentarios vinculados a una cita (p. ej. "esta entrevista puede ser
larga") se envían como mensajes a través del módulo de Mensajes, con el contexto de la
cita precargado en el asunto. No se almacenan en la agenda.

### 3.6 No-show

El no-show del ciudadano no se marca en la agenda sino en el seguimiento del caso
(Historia Social). La agenda solo refleja el estado `no_show_ciudadano` como información.

---

## 4. Mis casos

### 4.1 Descripción

Listado de los ciudadanos de los que el TSR es responsable como profesional de referencia.
Paginado, ordenable y filtrable. Pantalla exclusiva del rol `intervencion`.

### 4.2 Columnas

| Columna | Descripción | Enlace |
|---|---|---|
| Ciudadano | Nombre completo | `ciudadania.ciudadano.ficha` |
| Historia Social | Identificador formato `HS-XXXXXX` | `intervencion.ciudadano.show` |
| Próximo seguimiento | Semáforo: vencido (coral) / próximo en 7 días (ámbar) / programado (verde) / sin programar (texto mudo) | — |
| PISO | Estado del plan de intervención general: Activo / En revisión / Sin PISO. Etiqueta configurable desde Filament | — |
| Planes especializados | Número de planes de especializada activos. Guión si no hay ninguno | — |
| Alertas | Icono coral si hay alertas sin reconocer vinculadas al ciudadano | — |

El clic en el nombre del ciudadano va a la ficha (datos de Capa 1). El clic en el identificador
de historia social va a la pantalla de intervención clínica. El clic en el resto de la fila
también va a la pantalla de intervención (`wire:click` con `@click.stop` en nombre e HS para
evitar propagación).

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

**Lista:** ordenada por urgencia (menor tiempo restante primero). Cada item muestra:
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

**Detalle de hilo:** mensajes en orden cronológico, los propios alineados a la derecha,
los del interlocutor a la izquierda. Campo de respuesta al pie.

**Restricción:** los mensajes no pueden incluir contenido copiado de expedientes. Solo se
permiten enlaces a la Historia Social, con control de permisos en el momento de acceso.

---

## 6. Buscar ciudadano

### 6.1 Descripción

Búsqueda de ciudadanos con control de acceso por nivel. Accesible también desde el sidebar
de otros roles operativos.

### 6.2 Campos de búsqueda

| Campo | Fuente |
|---|---|
| Nombre (por defecto) | `nombre` + `apellido1` + `apellido2` |
| NIF/NIE/Pasaporte | `ciudadano_identificadores.valor` |
| NI-HSU-CM | `ciudadano_identificadores.valor` donde `tipo = 'ni_hsu_cm'` |
| Alias PSH | `ciudadano.alias` |

### 6.3 Resultados y enlaces

Cada resultado muestra: avatar con iniciales, nombre completo, fecha de nacimiento,
NIF, CSS responsable, domicilio (si no es colectivo protegido), NI-HSU-CM (solo si tiene
Historia Social abierta), estado y TSR de la Historia Social (si existe).

**Tres niveles de acceso según el modelo de permisos:**

**Nivel 1 — Propia UO:** acceso directo.
- Nombre del ciudadano → `ciudadania.ciudadano.ficha`
- Botón "Ir a Historia Social" (si existe) → `intervencion.ciudadano.show`
- Botón "Abrir Historia Social" (si no existe)

**Nivel 2 — Otra UO, sin colectivo protegido:** acceso libre con registro de auditoría.
- Nombre → span no clicable hasta que se registre el acceso
- Botón "Ver Historia Social (acceso registrado)" → registra `AccesoProtegido` y redirige a `intervencion.ciudadano.show`

**Nivel 3 — Colectivo especialmente protegido fuera de la UO:**
- Domicilio enmascarado con icono de candado
- Badge "Colectivo protegido" con icono de escudo
- Botón "Ver Historia Social" desactivado
- Única acción: "Solicitar acceso" — modal con aviso de contexto (VVG, menores) y justificación obligatoria. El acceso se habilita tras aprobación del supervisor competente.

### 6.4 Ciudadano sin Historia Social

Si el ciudadano existe en VIDA pero no tiene Historia Social, el bloque correspondiente
muestra: "Registrado en VIDA · Sin Historia Social abierta". El NI-HSU-CM no aparece.
El botón principal es "Abrir Historia Social".

### 6.5 Ciudadano no registrado en VIDA

Al final del listado de resultados:

> ¿No está la persona que buscas?
> [Dar de alta nuevo ciudadano] → `ciudadania.alta`

---

## 7. Pantalla del ciudadano — Historia Social (`intervencion/ciudadano/{historia}`)

### 7.1 Descripción

Pantalla de trabajo principal del TSR con un ciudadano. Exclusiva del rol `intervencion`.
Pivota sobre `HistoriaSocial` (parámetro de ruta `{historia}`). Layout de dos columnas:
izquierda con contexto del ciudadano e historia, derecha con las herramientas de trabajo.

Esta pantalla es distinta de `ciudadania/ciudadano/{ciudadano}` (ficha del ciudadano),
que pivota sobre `Ciudadano` y es accesible para todos los roles operativos.
Ver sección 9 y `docs/front/ui-ficha-ciudadano.md`.

### 7.2 Barra superior

Breadcrumb: `← Mis casos · [Nombre del ciudadano]` + badge de estado de la Historia Social.
Acciones: "Ficha ciudadano" → `ciudadania.ciudadano.ficha` y menú de acciones secundarias.

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

La Historia Social es única por ciudadano y permanente — nunca se cierra. Lo que tiene
ciclo de vida son los planes (PISO) y las prestaciones dentro de ella.

Código de color de los puntos por tipo:

| Tipo | Color del punto |
|---|---|
| PISO / plan | Ámbar (`var(--color-warning)`) |
| Entrevista | Morado (`var(--color-primary)`) |
| Valoración (ficha) | Verde teal (`var(--color-success)`) |
| Escala | Morado claro (`var(--color-primary-subtle-foreground)`) |
| Derivación | Verde (`var(--color-success-dark)`) |
| Anotación | Gris (`var(--color-text-tertiary)`) |

**PISO activo**

Banda fija entre la barra superior y el área de trabajo. Muestra:
nombre del plan, estado, versión y fecha de próxima revisión. Botón "Ver PISO".

### 7.4 Columna derecha — herramientas

**Estado neutro (selector de herramientas)**

Cuadrícula de 4 columnas con las herramientas disponibles. Cada herramienta muestra
un icono con fondo de color semántico y etiqueta.

| Herramienta | Icono | Comportamiento |
|---|---|---|
| Entrevista | `user-check` (morado) | Formulario inline. Botón: "Guardar entrevista" |
| Anotación | `note` (gris) | Formulario inline. Botón: "Guardar anotación" |
| Valoración | `clipboard-check` (verde teal) | Pantalla completa |
| Escala | `chart-bar` (morado) | Pantalla completa |
| Derivación | `send` (azul) | Formulario inline. Botón: "Crear derivación" |
| Gestión / coordinación | `arrows-exchange` (ámbar) | Formulario inline. Botón: "Guardar gestión" |
| Informes | `file-text` (coral) | Pantalla completa |

Todos los iconos son Heroicons renderizados con `blade-ui-kit/blade-heroicons`.

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

## 8. Mapa de navegación entre pantallas

La regla que articula todos los enlaces:

> **El nombre del ciudadano lleva siempre a sus datos (quién es). La referencia al proceso
> clínico —historia social, cita— lleva al contexto de trabajo (qué está pasando con él).**

La excepción es la agenda para el rol `intervencion`: el TSR accede desde la agenda para
atender a la persona, por lo que ir directamente a la pantalla de intervención es más útil.

### 8.1 Tabla completa de enlaces

| Contexto | Elemento clicable | Destino `intervencion` | Destino `tramitacion` / `consulta_basica` |
|---|---|---|---|
| Búsqueda — nivel 1 | Nombre del ciudadano | `ciudadania.ciudadano.ficha` | `ciudadania.ciudadano.ficha` |
| Búsqueda — nivel 1 | Botón "Ir a Historia Social" | `intervencion.ciudadano.show` | `intervencion.ciudadano.show` |
| Agenda | Nombre en cita con `historia_id` | `intervencion.ciudadano.show` | `ciudadania.ciudadano.ficha` |
| Agenda | Nombre en cita sin `historia_id` | No clicable (TODO) | `ciudadania.ciudadano.ficha` |
| Mis casos | Nombre del ciudadano | `ciudadania.ciudadano.ficha` | — (pantalla exclusiva `intervencion`) |
| Mis casos | Identificador HS (`HS-XXXXXX`) | `intervencion.ciudadano.show` | — |
| Mis casos | Resto de la fila | `intervencion.ciudadano.show` | — |
| UC en ficha ciudadano | Botón "Ver ficha" del miembro | `ciudadania.ciudadano.ficha` | `ciudadania.ciudadano.ficha` |
| Ficha ciudadano — banner HS | Enlace "Ir a HS" | `intervencion.ciudadano.show` (clicable) | No clicable (visible, tooltip) |
| Pantalla intervención — barra superior | "Ficha ciudadano" | `ciudadania.ciudadano.ficha` | — |

### 8.2 Estado de implementación

| Comportamiento | Estado |
|---|---|
| Búsqueda nivel 1: nombre → ficha | ✅ Implementado |
| Búsqueda nivel 1: botón "Ir a HS" | ✅ Implementado |
| Agenda `intervencion`: nombre con HS → intervención | ✅ Implementado |
| Agenda `intervencion`: nombre sin HS → no clicable | ✅ Implementado (TODO pendiente) |
| Agenda `tramitacion`/`consulta_basica`: nombre → ficha | ⏳ Pendiente |
| Mis casos: nombre → ficha ciudadano | ⏳ Pendiente |
| Mis casos: HS → intervención | ✅ Implementado |
| Mis casos: fila → intervención | ✅ Implementado |
| UC en ficha: "Ver ficha" → ficha del miembro | ⏳ Pendiente |
| Ficha banner HS: clicable para `intervencion` | ⏳ Pendiente |
| Ficha banner HS: no clicable para otros roles | ⏳ Pendiente |

---

## 9. Ficha del ciudadano (`ciudadania/ciudadano/{ciudadano}`)

Pantalla accesible para todos los roles operativos. Pivota sobre `Ciudadano` (Capa 1).
Documentación completa en `docs/front/ui-ficha-ciudadano.md`.

### 9.1 Resumen de acceso por rol

| Rol | Acceso | Edición Capa 1 y UC |
|---|---|---|
| `intervencion` | Sí | Sí |
| `tramitacion` | Sí | Sí |
| `consulta_basica` | Sí | Sí |
| `supervision` | Sí | No |

### 9.2 Contenido principal

Columna principal: identificación y contacto (Capa 1), historial de documentos de
identidad, unidad de convivencia con miembros y enlaces a sus fichas.

Columna lateral — widgets condicionales (solo aparecen cuando tienen datos):

| Widget | Condición de aparición |
|---|---|
| Banner de historia social | Solo si existe `HistoriaSocial` para el ciudadano |
| Otras prestaciones | Solo si `ciudadano_prestaciones_resumen` tiene registros |
| Centro de referencia y TSR | Solo si existe `HistoriaSocial` |
| Actividad reciente | Siempre (al menos el alta genera un evento) |

El widget de permisos del rol activo ha sido eliminado por generar confusión.

Un ciudadano recién dado de alta muestra solo actividad reciente en el lateral.
La pantalla crece orgánicamente conforme el ciudadano acumula relaciones con los servicios.

### 9.3 Banner de historia social

Visible para todos los roles cuando existe historia social. El enlace "Ir a HS" es
navegable solo para `intervencion`; para otros roles aparece visible pero no clicable,
con tooltip "Requiere rol de intervención".

La historia social es única por ciudadano y permanente (ver sección 7.3).

### 9.4 Otras prestaciones

Widget alimentado desde `ciudadano_prestaciones_resumen` — tabla de agregación del módulo
Ciudadanía. Muestra prestaciones y actividades que no requieren historia social: talleres
de centro, teleasistencia, ayuda a domicilio básica, etc.

Visible para todos los roles con acceso a la ficha, sin restricciones adicionales.
Los módulos origen (Centros, Teleasistencia...) mantienen sus filas en esta tabla mediante
observers. La pantalla nunca consulta directamente las tablas de los módulos origen.

Estados con badge de color: activo (verde) / en trámite (ámbar) / finalizado (gris) /
denegado o baja (rojo).

---

## 10. Decisiones de diseño transversales

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
| Ciudadano no en VIDA | Botón "Dar de alta nuevo ciudadano" al final de los resultados → `ciudadania.alta`. |
| Herramientas complejas | Valoración, Escala e Informes se abren en pantalla completa. La historia queda colapsada para dar contexto mínimo. |
| Mensajes con información de ciudadanos | Solo se permiten enlaces a la Historia Social (con control de permisos en el momento de acceso). No se copia contenido del expediente en el mensaje. |
| Nombre del ciudadano en UI | Siempre enlaza a la ficha del ciudadano (`ciudadania.ciudadano.ficha`), excepto en la agenda para `intervencion` donde enlaza a la pantalla de intervención. |
| Historia social: permanencia | La historia social nunca se cierra. Tienen ciclo de vida los planes y las prestaciones, no el contenedor. |
| Historia social: unicidad | Cada ciudadano tiene como máximo una historia social en el sistema. Garantía en BD: índice `UNIQUE(ciudadano_id)` en `historias_sociales` (pendiente de aplicar). |
| Widgets vacíos en ficha | No se muestran. La ausencia del widget comunica que no hay datos, sin ruido visual. |
| Widget de permisos | Eliminado. Información redundante con la propia experiencia de la pantalla. |
| Iconos | Heroicons en todas las vistas. Bootstrap Icons y Tabler Icons eliminados. |
| Colores | Tokens CSS del design system (`var(--color-*)`) en todos los componentes. Colores hardcodeados eliminados. |
