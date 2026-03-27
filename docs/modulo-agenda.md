# Módulo Agenda

**Módulo Laravel:** `Modules\Agenda`  
**Estado:** Diseño funcional — pendiente de implementación  
**Última revisión:** marzo 2026  
**Dependencias:** Módulo Centro, Módulo Usuarios/Permisos, Módulo Mensajes, Módulo Intervención (consumidor), Módulo Integraciones (canal API externo)

---

## Nota de diseño: adopción progresiva

Los servicios sociales municipales presentan una heterogeneidad real en tamaño, composición de equipo y complejidad operativa. Un centro de atención primaria con veinte profesionales, múltiples tipos de atención y gestión intensa de urgencias necesita herramientas que su equivalente de cinco profesionales con un horario estable no necesita — y que le añadirían fricción innecesaria.

VIDA 360 resuelve esto con un **modelo de complejidad progresiva**: el mismo modelo de datos subyace en todos los casos, pero cada centro opera al nivel que corresponde a su realidad. La complejidad es opcional y se activa cuando el centro está preparado para ella, sin necesidad de migración técnica.

El nivel de agenda de un centro se configura en `HorarioCentro.modo_agenda` y determina qué funcionalidades se activan y qué interfaz se presenta. Un centro puede subir de nivel en cualquier momento; nunca necesita empezar desde cero.

---

## 1. Introducción funcional

El módulo Agenda gestiona la disponibilidad horaria de los profesionales de cada centro, la generación de cuadrantes mensuales y la reserva de citas con ciudadanos. Es el punto de encuentro entre la organización del centro y la atención directa a la persona.

El módulo distingue dos tipos de ocupación del tiempo de un profesional:

- **Citas:** reservas de tiempo con un ciudadano concreto para un tipo de atención determinado. Tienen ciclo de vida propio, pueden venir de canales internos o externos vía API, y generan trazabilidad en la Historia Social.
- **Eventos:** bloqueos de tiempo sin ciudadano asociado (reuniones de equipo, formaciones internas, mesas de coordinación). No generan historia social. Pueden reservar un espacio físico del centro.

La disponibilidad efectiva de un profesional es siempre la intersección de tres elementos: el horario del centro, el perfil horario del profesional en ese centro y las excepciones vigentes para ese período.

### Modos de agenda

Cada centro opera en uno de tres modos, configurado en `HorarioCentro.modo_agenda`. El modelo de datos es idéntico en los tres; lo que varía es qué funcionalidades se activan y qué interfaz se presenta.

**Modo `basico`**
Para centros pequeños o con gestión sencilla. El cuadrante se genera y publica automáticamente al inicio de cada mes sin intervención del supervisor. No hay tipos de slot configurables (se usa un tipo genérico "Cita" creado por defecto). Las ausencias de profesionales se registran con un formulario mínimo (fechas + motivo de lista corta). Los bloqueos de franja horaria (reuniones, etc.) se crean directamente como eventos simplificados sin gestión de espacios ni convocatoria formal. No hay slots de urgencia ni propuesta IA.

**Modo `estandar`**
El caso normal bien gestionado. El supervisor genera el cuadrante mensual, lo revisa y lo publica. Hay tipos de slot configurables, gestión de excepciones con detalle, slots de urgencia y gestión asistida de reasignaciones por ausencia. Los eventos pueden reservar espacios físicos y convocar profesionales.

**Modo `avanzado`**
Todo lo anterior más propuesta IA del cuadrante, soporte completo para profesionales itinerantes con perfiles en varios centros, múltiples tipos de slot con reglas diferenciadas y estadísticas de uso de urgencias.

Un centro puede subir de nivel en cualquier momento. No hay migración de datos: el modelo subyacente es el mismo desde el inicio.

### Flujo general (modo estándar y avanzado)

```
Configuración del centro (HorarioCentro + TipoSlot)
        ↓
Perfil horario de cada profesional (PerfilHorarioProfesional)
        ↓
Generación del cuadrante mensual (CuadranteMes) — propuesta IA en modo avanzado
        ↓  aprobación supervisor
Materialización de slots (Slot) al publicar el cuadrante
        ↓
Reserva de citas (Cita) — canal interno o API externa
        ↓
Gestión operativa: no-shows, reasignaciones, urgencias, eventos
```

### Flujo simplificado (modo básico)

```
Configuración mínima del centro (HorarioCentro con modo_agenda = basico)
        ↓
Generación y publicación automática del cuadrante al inicio de mes
        ↓
Bloqueos puntuales: días completos (ExcepcionProfesional) o franjas (EventoAgenda simplificado)
        ↓
Reserva de citas (Cita) — canal interno o API externa
```

---

## 2. Entidades

### 2.1 HorarioCentro

**Tabla:** `horarios_centro`  
**Descripción:** Define el marco horario operativo de un centro: días y horas de apertura, horario de atención al público y reglas globales que aplican a todos los profesionales del centro. Es configurado por el supervisor del centro desde Filament.

Un centro puede tener varios registros de horario a lo largo del tiempo (p. ej., horario de verano vs. invierno), pero solo uno puede estar vigente en cada momento.

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | int PK | |
| `centro_id` | int FK | Centro al que pertenece |
| `nombre` | string | Ej: "Horario general", "Horario verano 2025" |
| `dias_laborables` | json | Array de días de la semana activos (ej: `[1,2,3,4,5]` para L-V) |
| `hora_apertura` | time | Hora de apertura del centro |
| `hora_cierre` | time | Hora de cierre del centro |
| `hora_inicio_atencion` | time | Inicio del horario de atención al público |
| `hora_fin_atencion` | time | Fin del horario de atención al público |
| `buffer_inicio_minutos` | int | Minutos sin citas al inicio del turno (revisión pendientes, etc.) |
| `buffer_fin_minutos` | int | Minutos sin citas al final del turno |
| `vigente_desde` | date | Fecha de inicio de vigencia |
| `vigente_hasta` | date nullable | Fecha de fin de vigencia (null = indefinido) |
| `modo_agenda` | enum | `basico` / `estandar` / `avanzado` — determina las funcionalidades activas del centro |
| `activo` | boolean | |
| `notas` | text nullable | Observaciones del supervisor |

**Relaciones:**

| Método | Tipo | Descripción |
|---|---|---|
| `centro()` | `BelongsTo<Centro>` | Centro al que pertenece |
| `tiposSlot()` | `HasMany<TipoSlot>` | Tipos de slot definidos para este horario |

**Filament:** `HorarioCentroResource` (grupo *Agenda — Configuración*)

---

### 2.2 TipoSlot

**Tabla:** `tipos_slot`  
**Descripción:** Define los tipos de atención que pueden reservarse como cita en un centro: entrevista con TSR, primera atención SIA, reunión de coordinación con familia, sesión grupal, etc. Cada tipo tiene una duración por defecto y reglas de uso.

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | int PK | |
| `horario_centro_id` | int FK | Horario de centro al que pertenece |
| `nombre` | string | Ej: "Entrevista TSR", "Primera atención SIA" |
| `descripcion` | text nullable | |
| `duracion_minutos` | int | Duración por defecto del slot |
| `requiere_espacio` | boolean | Si `true`, la cita debe reservar un espacio físico |
| `porcentaje_urgencias` | int | % de slots de este tipo reservados para urgencias (0-100) |
| `origen_permitido` | enum | `interno` / `api_externa` / `ambos` |
| `genera_apunte_automatico` | boolean | Si la cita genera un apunte en Historia Social al cerrarse |
| `activo` | boolean | |

**Nota sobre `porcentaje_urgencias`:** El sistema calcula cuántos slots de urgencia deben existir por día en función de este porcentaje sobre el total de slots generados de ese tipo. Los slots de urgencia son visibles internamente pero no se exponen al canal externo (API).

**Nota sobre modo básico:** En centros con `modo_agenda = basico`, no es necesario configurar tipos de slot. El sistema crea automáticamente un tipo genérico "Cita" al activar la agenda del centro. Los campos `porcentaje_urgencias`, `requiere_espacio` y `genera_apunte_automatico` quedan en sus valores por defecto (0, false, false respectivamente).

**Relaciones:**

| Método | Tipo | Descripción |
|---|---|---|
| `horarioCentro()` | `BelongsTo<HorarioCentro>` | Horario al que pertenece |
| `slots()` | `HasMany<Slot>` | Slots generados de este tipo |

**Filament:** `TipoSlotResource` (grupo *Agenda — Configuración*)

---

### 2.3 PerfilHorarioProfesional

**Tabla:** `perfiles_horario_profesional`  
**Descripción:** Define el horario habitual de un profesional en un centro concreto. Un profesional puede tener perfiles en varios centros (profesionales itinerantes). Solo un perfil por profesional y centro puede estar activo en cada momento.

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | int PK | |
| `usuario_id` | int FK | Profesional |
| `centro_id` | int FK | Centro al que aplica este perfil |
| `jornada_semanal_horas` | decimal(4,2) | Jornada semanal en horas (p. ej., 35, 17.5) |
| `horario_habitual` | json | Franjas habituales por día de semana. Estructura: `{"1": [{"inicio": "09:00", "fin": "14:00"}], ...}` |
| `vigente_desde` | date | |
| `vigente_hasta` | date nullable | null = vigente indefinidamente |
| `activo` | boolean | |
| `notas` | text nullable | Ej: "Reducción por conciliación familiar" |

**Relaciones:**

| Método | Tipo | Descripción |
|---|---|---|
| `usuario()` | `BelongsTo<Usuario>` | Profesional |
| `centro()` | `BelongsTo<Centro>` | Centro al que pertenece este perfil |
| `lineasCuadrante()` | `HasMany<LineaCuadrante>` | Líneas de cuadrante generadas para este perfil |

**Filament:** `PerfilHorarioProfesionalResource` (grupo *Agenda — Configuración*)

---

### 2.4 ExcepcionProfesional

**Tabla:** `excepciones_profesional`  
**Descripción:** Registra ausencias, reducciones o modificaciones puntuales del horario de un profesional. VIDA 360 no gestiona la solicitud ni la autorización (eso corresponde al sistema de RRHH); el supervisor introduce el resultado en el sistema. En el futuro, este módulo puede recibir excepciones vía API desde RRHH.

Los tipos de excepción con consecuencias distintas en lógica de negocio se modelan como enum. El campo `afecta_disponibilidad` determina si los slots del período deben cancelarse o simplemente bloquearse.

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | int PK | |
| `usuario_id` | int FK | Profesional afectado |
| `centro_id` | int FK | Centro al que aplica la excepción |
| `tipo` | enum | `baja_medica` / `vacaciones` / `dia_libre` / `formacion` / `reduccion_jornada` / `guardia` / `otros` |
| `fecha_inicio` | date | |
| `fecha_fin` | date | Puede coincidir con `fecha_inicio` para excepciones de un día |
| `afecta_disponibilidad` | boolean | `true` = cancela slots y citas existentes; `false` = bloquea pero mantiene citas |
| `franja_afectada` | json nullable | Si la excepción es parcial (solo mañana, solo una franja). Null = día completo |
| `origen` | enum | `manual` / `api_rrhh` |
| `creado_por_id` | int FK | Usuario supervisor que registró la excepción |
| `notas` | text nullable | |

**Relaciones:**

| Método | Tipo | Descripción |
|---|---|---|
| `usuario()` | `BelongsTo<Usuario>` | Profesional |
| `centro()` | `BelongsTo<Centro>` | Centro |
| `creadoPor()` | `BelongsTo<Usuario>` | Supervisor que introdujo la excepción |

**Scopes:** `scopeVigentes()`, `scopeQueAfectanDisponibilidad()`

**Nota sobre modo básico:** En centros con `modo_agenda = basico`, el formulario de excepción solo solicita fechas y un motivo de lista corta. Los campos `franja_afectada`, `afecta_disponibilidad` y `origen` se fijan a sus valores por defecto (`null`, `true`, `manual`). El modelo no cambia; solo se simplifica el formulario.

**Filament:** `ExcepcionProfesionalResource` con historial por profesional (grupo *Agenda — Supervisión*)

---

### 2.5 CuadranteMes

**Tabla:** `cuadrantes_mes`  
**Descripción:** Cuadrante mensual de un centro. Representa la planificación de disponibilidad para todos los profesionales del centro en un mes concreto. El cuadrante pasa por un ciclo de estados antes de tener efecto operativo.

El flujo es: el supervisor solicita la generación del cuadrante base (a partir de `HorarioCentro` + `PerfilHorarioProfesional`). El sistema genera una propuesta en estado `borrador`. El supervisor revisa y ajusta. Al publicar, el sistema materializa los slots del mes.

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | int PK | |
| `centro_id` | int FK | Centro al que pertenece |
| `anyo` | int | Año del cuadrante |
| `mes` | int | Mes del cuadrante (1-12) |
| `estado` | enum | `borrador` / `revision` / `publicado` |
| `generado_con_ia` | boolean | Si la propuesta inicial fue generada por IA |
| `generado_automaticamente` | boolean | `true` si lo generó el sistema sin intervención del supervisor (modo básico) |
| `publicado_en` | timestamp nullable | Momento de publicación |
| `publicado_por_id` | int FK nullable | Supervisor que publicó |
| `notas` | text nullable | |

**Relaciones:**

| Método | Tipo | Descripción |
|---|---|---|
| `centro()` | `BelongsTo<Centro>` | Centro |
| `publicadoPor()` | `BelongsTo<Usuario>` | Supervisor |
| `lineas()` | `HasMany<LineaCuadrante>` | Líneas del cuadrante (una por profesional y día) |
| `slots()` | `HasManyThrough<Slot>` | Slots generados al publicar |

**Scopes:** `scopePublicados()`, `scopeDelMes($anyo, $mes)`

**Filament:** `CuadranteMesResource` con `LineasCuadranteRelationManager` (grupo *Agenda — Supervisión*)

---

### 2.6 LineaCuadrante

**Tabla:** `lineas_cuadrante`  
**Descripción:** Una línea del cuadrante representa la asignación de un profesional en un día concreto dentro del cuadrante mensual. Define la franja horaria real de trabajo de ese profesional ese día, ya incorporando las particularidades de su perfil (reducción, horario especial). Las excepciones sobreescriben o anulan la línea correspondiente.

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | int PK | |
| `cuadrante_mes_id` | int FK | Cuadrante al que pertenece |
| `usuario_id` | int FK | Profesional |
| `centro_id` | int FK | Centro (desnormalizado para consultas directas) |
| `fecha` | date | Día concreto |
| `franjas` | json | Array de franjas de trabajo: `[{"inicio": "09:30", "fin": "14:00", "tipo": "atencion"}, ...]` |
| `anulada` | boolean | `true` si una excepción posterior anuló este día |
| `excepcion_id` | int FK nullable | Excepción que anuló esta línea |

**Relaciones:**

| Método | Tipo | Descripción |
|---|---|---|
| `cuadranteMes()` | `BelongsTo<CuadranteMes>` | Cuadrante |
| `usuario()` | `BelongsTo<Usuario>` | Profesional |
| `slots()` | `HasMany<Slot>` | Slots generados para esta línea |
| `excepcion()` | `BelongsTo<ExcepcionProfesional>` nullable | Excepción que la anuló |

**Scopes:** `scopeActivas()` — solo líneas no anuladas

---

### 2.7 Slot

**Tabla:** `slots`  
**Descripción:** Hueco concreto disponible para reserva. Se generan al publicar el cuadrante mensual. Un slot corresponde a un profesional, un día, una franja horaria y un tipo de slot. Su estado refleja si está disponible, ocupado por una cita, reservado para urgencias o expirado.

Los slots **no se versionan** (Principio 3.5 adaptado): no necesitamos saber que un slot estuvo libre antes de ser asignado. La trazabilidad se obtiene del ciclo de vida de la `Cita` y del estado final del slot.

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | int PK | |
| `linea_cuadrante_id` | int FK | Línea del cuadrante que lo generó |
| `usuario_id` | int FK | Profesional asignado |
| `centro_id` | int FK | Centro (desnormalizado) |
| `tipo_slot_id` | int FK | Tipo de atención |
| `fecha` | date | Día del slot |
| `hora_inicio` | time | Hora de inicio |
| `hora_fin` | time | Hora de fin |
| `estado` | enum | `disponible` / `reservado` / `bloqueado_urgencia` / `bloqueado_evento` / `expirado` / `no_ocupado` |
| `espacio_id` | int FK nullable | Espacio físico reservado (si aplica) |

**Notas sobre estados:**
- `disponible`: libre para reserva, visible en canal externo si `tipo_slot.origen_permitido` lo permite.
- `reservado`: tiene una `Cita` activa asociada.
- `bloqueado_urgencia`: reservado para urgencias; invisible en canal externo.
- `bloqueado_evento`: ocupado por un `EventoAgenda`.
- `expirado`: la hora de inicio ha pasado sin que se asignara (aplica a `bloqueado_urgencia` no consumido).
- `no_ocupado`: la hora ha pasado y el slot `disponible` no fue reservado.

**Relaciones:**

| Método | Tipo | Descripción |
|---|---|---|
| `lineaCuadrante()` | `BelongsTo<LineaCuadrante>` | Línea que lo generó |
| `usuario()` | `BelongsTo<Usuario>` | Profesional |
| `tipoSlot()` | `BelongsTo<TipoSlot>` | Tipo de atención |
| `espacio()` | `BelongsTo<Espacio>` nullable | Espacio físico |
| `cita()` | `HasOne<Cita>` nullable | Cita vinculada |

**Scopes:** `scopeDisponibles()`, `scopeUrgencias()`, `scopeDelDia($fecha)`, `scopeDelProfesional($usuarioId)`

---

### 2.8 Cita

**Tabla:** `citas`  
**Descripción:** Reserva de un slot con un ciudadano concreto. Una cita tiene un origen (quién la creó), un motivo y un ciclo de vida. Las citas pueden llegar desde el sistema interno o desde un canal externo vía API (Atención a la Ciudadanía u otros).

La cita se vincula a un slot, que cambia a estado `reservado` al crearse. Si la cita se cancela, el slot vuelve a `disponible` si aún no ha pasado la hora, o queda en `no_ocupado` si ya expiró.

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | int PK | |
| `slot_id` | int FK unique | Slot reservado |
| `ciudadano_id` | int FK | Ciudadano con quien se cita |
| `profesional_id` | int FK | Profesional asignado (desnormalizado del slot para consultas) |
| `tipo_slot_id` | int FK | Tipo de atención (desnormalizado) |
| `centro_id` | int FK | Centro (desnormalizado) |
| `fecha` | date | Fecha de la cita (desnormalizada) |
| `hora_inicio` | time | Hora de inicio (desnormalizada) |
| `hora_fin` | time | Hora de fin (desnormalizada) |
| `estado` | enum | `confirmada` / `cancelada` / `completada` / `no_show_ciudadano` / `no_show_profesional` / `reasignada` |
| `motivo` | text nullable | Motivo de la cita, introducido al crear |
| `origen` | enum | `interno` / `api_externa` |
| `referencia_externa` | string nullable | ID de la cita en el sistema externo (para sincronización) |
| `creado_por_id` | int FK nullable | Usuario que creó la cita (null si viene de API) |
| `cancelado_por_id` | int FK nullable | Usuario que canceló (si aplica) |
| `motivo_cancelacion` | text nullable | |
| `completada_en` | timestamp nullable | Momento en que el profesional marcó la cita como completada |
| `notas_profesional` | text nullable | Notas post-cita del profesional (no van a Historia Social automáticamente) |

**Nota sobre `no_show_ciudadano`:** El profesional marca manualmente el no-show al finalizar la franja o en cualquier momento posterior. No hay automatismo por tiempo transcurrido.

**Nota sobre `no_show_profesional`:** Lo registra el supervisor cuando gestiona una ausencia sobrevenida. Las citas del profesional ausente pasan a este estado, quedando disponibles para reasignación.

**Relaciones:**

| Método | Tipo | Descripción |
|---|---|---|
| `slot()` | `BelongsTo<Slot>` | Slot reservado |
| `ciudadano()` | `BelongsTo<Ciudadano>` | Ciudadano |
| `profesional()` | `BelongsTo<Usuario>` | Profesional |
| `tipoSlot()` | `BelongsTo<TipoSlot>` | Tipo de atención |
| `creadoPor()` | `BelongsTo<Usuario>` nullable | |
| `reasignacion()` | `HasOne<ReasignacionCita>` nullable | Si fue reasignada |

**Scopes:** `scopeConfirmadas()`, `scopeDelDia($fecha)`, `scopeDelProfesional($usuarioId)`, `scopePendientesReasignacion()`

**Livewire:** Vista de agenda del profesional, vista de gestión de ausencias del supervisor.

---

### 2.9 ReasignacionCita

**Tabla:** `reasignaciones_cita`  
**Descripción:** Registra el historial de reasignaciones. Cuando una cita pasa de un profesional a otro (por no-show del profesional, baja sobrevenida u otras causas), se crea un registro de reasignación que vincula la cita original con el nuevo slot asignado.

La reasignación siempre la realiza un supervisor. El sistema asiste al supervisor mostrando los slots de urgencia disponibles en el centro para esa franja horaria; la selección final es siempre humana (Principio 3.9).

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | int PK | |
| `cita_id` | int FK | Cita original reasignada |
| `slot_original_id` | int FK | Slot original (profesional ausente) |
| `slot_nuevo_id` | int FK | Slot de urgencia asignado |
| `profesional_original_id` | int FK | Profesional que no pudo atender |
| `profesional_nuevo_id` | int FK | Profesional que asume la cita |
| `motivo` | enum | `no_show_profesional` / `baja_sobrevenida` / `redistribucion` / `otros` |
| `notas` | text nullable | |
| `realizada_por_id` | int FK | Supervisor que realizó la reasignación |

**Relaciones:**

| Método | Tipo | Descripción |
|---|---|---|
| `cita()` | `BelongsTo<Cita>` | |
| `slotOriginal()` | `BelongsTo<Slot>` | |
| `slotNuevo()` | `BelongsTo<Slot>` | |
| `realizadaPor()` | `BelongsTo<Usuario>` | |

---

### 2.10 EventoAgenda

**Tabla:** `eventos_agenda`  
**Descripción:** Bloqueo de tiempo en la agenda de uno o varios profesionales, sin ciudadano asociado. Representa reuniones internas, formaciones, mesas de coordinación, supervisiones de equipo, etc. Los eventos se muestran en el calendario junto a las citas pero no generan historia social. Pueden reservar un espacio físico del centro.

En modo `basico`, los eventos son el mecanismo principal para bloquear franjas horarias: el supervisor selecciona profesionales, marca el rango horario y elige un motivo de lista corta. No hay gestión de espacios ni convocatoria formal. El formulario completo (tipo configurable, espacio, confirmación de asistencia por profesional) solo está disponible en modos `estandar` y `avanzado`.

Cuando un evento se solapa con slots existentes marcados como `disponible`, estos pasan a `bloqueado_evento`. El sistema avisa si hay citas ya confirmadas afectadas por el evento, pero no bloquea su creación.

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | int PK | |
| `centro_id` | int FK | Centro donde se celebra |
| `tipo_evento_id` | int FK | Catálogo configurable (`catalogos_sistema`) |
| `titulo` | string | |
| `descripcion` | text nullable | |
| `fecha` | date | |
| `hora_inicio` | time | |
| `hora_fin` | time | |
| `espacio_id` | int FK nullable | Espacio físico reservado |
| `creado_por_id` | int FK | Usuario que creó el evento |
| `notas` | text nullable | |

**Relaciones:**

| Método | Tipo | Descripción |
|---|---|---|
| `centro()` | `BelongsTo<Centro>` | |
| `espacio()` | `BelongsTo<Espacio>` nullable | Espacio reservado |
| `profesionales()` | `BelongsToMany<Usuario>` via `evento_usuario` | Profesionales convocados |
| `creadoPor()` | `BelongsTo<Usuario>` | |

**Pivot `evento_usuario`:** `evento_agenda_id`, `usuario_id`, `confirmado` (boolean), `notas`

**Nota sobre conflictos de espacio:** Si el espacio ya está reservado por otro evento simultáneo, el sistema genera un aviso pero no bloquea la creación. No hay locking automático de espacios.

**Livewire:** Vista de calendario del centro (citas + eventos unificados).  
**Filament:** Catálogo de tipos de evento (`catalogos_sistema`).

---

## 3. Servicios de dominio

### 3.1 DisponibilidadService

Calcula los slots disponibles para un profesional, un tipo de slot y un rango de fechas. Cruza `LineaCuadrante` vigentes con `ExcepcionProfesional` activas. No genera slots: consulta los ya materializados filtrando por estado `disponible`.

```
DisponibilidadService::obtenerSlots(
    usuarioId: int,
    centroId: int,
    tipoSlotId: int,
    desde: Date,
    hasta: Date,
    incluirUrgencias: bool = false
): Collection<Slot>
```

### 3.2 CuadranteGeneratorService

Genera el borrador de `LineaCuadrante` para un mes a partir del `HorarioCentro` vigente y los `PerfilHorarioProfesional` activos. Aplica las `ExcepcionProfesional` ya conocidas para el período.

En modos `estandar` y `avanzado`, la propuesta es siempre un borrador que el supervisor debe revisar y publicar. Cuando `generado_con_ia = true` (solo modo `avanzado`), el servicio delega en un componente IA (Principio 3.9: la IA propone, el supervisor aprueba) que optimiza la distribución de franjas en función de la demanda histórica y restricciones conocidas.

En modo `basico`, el servicio genera y publica el cuadrante automáticamente al inicio de cada mes (`generado_automaticamente = true`), sin requerir intervención del supervisor. El cuadrante generado es siempre el horario estándar del centro aplicado a todos los profesionales. El supervisor puede anularlo manualmente si necesita ajustes.

### 3.3 SlotMaterializadorService

Al publicar un `CuadranteMes`, genera los registros `Slot` a partir de las `LineaCuadrante`. Aplica las reglas del `HorarioCentro` (buffers, horario de atención) y de cada `TipoSlot` (duración, porcentaje de urgencias). Los slots de urgencia se marcan directamente como `bloqueado_urgencia`.

### 3.4 GestionAusenciaService

Servicio operativo que gestiona el flujo cuando un profesional no se presenta. Localiza las citas del día del profesional afectado y las pasa a estado `no_show_profesional`.

En modos `estandar` y `avanzado`, devuelve la lista de slots de urgencia disponibles en el centro para esa franja; el supervisor selecciona el slot destino y el servicio ejecuta la `ReasignacionCita`.

En modo `basico`, no hay slots de urgencia. El servicio devuelve las citas afectadas para que el supervisor las redistribuya manualmente, una por una, eligiendo un slot disponible de otro profesional. El flujo es más manual pero igualmente operativo.

### 3.5 SlotExpirationJob

Job programado que se ejecuta al final de cada día (o al pasar la hora de inicio de cada slot). Actualiza a `expirado` los slots `bloqueado_urgencia` cuya hora ha pasado sin ser consumidos, y a `no_ocupado` los slots `disponible` no reservados. No realiza ninguna acción sobre los profesionales ni sobre las citas.

---

## 4. Integraciones

### 4.1 Canal API externo (citas entrantes)

Las citas pueden llegar desde sistemas externos (p. ej., el sistema centralizado de Cita Previa del Ayuntamiento de Madrid). El módulo expone un endpoint para recepción de citas que sigue el patrón adaptador (Principio 3.6).

```
POST /api/v1/agenda/citas
```

El adaptador valida que el slot existe, está disponible y que el tipo de slot admite `origen_permitido = api_externa` o `ambos`. Crea la `Cita` con `origen = api_externa` y guarda la `referencia_externa` para trazabilidad y sincronización.

Los slots de urgencia (`bloqueado_urgencia`) nunca se exponen al canal externo.

El mock adapter (activo por defecto en desarrollo) simula la recepción de citas externas sin conexión real.

### 4.2 Canal API externo (citas salientes)

Cuando se cancela o modifica una cita con `origen = api_externa`, el sistema notifica al sistema externo mediante el adaptador correspondiente. El mock adapter registra la notificación sin enviarla.

### 4.3 Integración con Módulo Mensajes

Las siguientes situaciones generan alertas (Módulo Mensajes):

- Slot de urgencia consumido: aviso al supervisor del centro.
- Cita reasignada: alerta al ciudadano afectado (si hay canal disponible) y al profesional nuevo.
- Conflicto de espacio al crear un evento: aviso al creador.
- Cuadrante publicado: aviso a todos los profesionales del centro.

### 4.4 Integración con Módulo Intervención

Al completarse una cita con `tipo_slot.genera_apunte_automatico = true`, el módulo Intervención crea automáticamente un apunte en la Historia Social del ciudadano con el tipo, fecha, duración y profesional. El profesional puede enriquecer el apunte posteriormente.

---

## 5. Interfaz: separación Filament / Livewire

Siguiendo el Principio 3.12. El `modo_agenda` del centro determina qué elementos de la interfaz se muestran activos.

**Filament (configuración):**
- `HorarioCentroResource` — gestión del horario del centro y configuración del modo de agenda
- `TipoSlotResource` — tipos de slot y sus reglas *(modos estandar y avanzado)*
- `PerfilHorarioProfesionalResource` — perfiles horarios de los profesionales
- `ExcepcionProfesionalResource` — introducción de excepciones por el supervisor (formulario adaptado al modo)
- `CuadranteMesResource` — generación, revisión y publicación del cuadrante *(modos estandar y avanzado; en básico solo visualización)*

**Livewire (operación):**
- Vista de agenda del profesional — calendario personal con citas y eventos (todos los modos)
- Vista de agenda del ciudadano — citas activas e historial (todos los modos)
- Vista de gestión de ausencias — pantalla simplificada en modo básico; asistida con slots de urgencia en modos estandar y avanzado
- Vista de cuadrante del centro — visión agregada del equipo para el supervisor *(modos estandar y avanzado)*

---

## 6. Paquetes de soporte

- **`spatie/laravel-period`** — manejo de períodos de tiempo con operaciones de intersección y diferencia. Clave para `DisponibilidadService` al cruzar franjas del cuadrante con excepciones.
- **`recurr/recurr`** — generación de secuencias de fechas a partir de reglas de recurrencia (RRULE). Útil en `CuadranteGeneratorService` para proyectar el horario habitual del profesional sobre el mes.

---

## 7. Decisiones pendientes

- **Generación IA de cuadrantes:** diseño del componente IA (modelo, inputs, outputs, criterios de calidad de la propuesta). Requiere definir qué métricas de demanda histórica se usan como input. Decisión diferida: pendiente de datos históricos suficientes en el sistema.

- **Notificación al ciudadano por cambio de cita:** depende del canal de comunicación disponible (carpeta ciudadana, SMS, email). Diferido a la definición del módulo de comunicaciones ciudadanas.

- **Integración RRHH vía API:** el campo `origen = api_rrhh` en `ExcepcionProfesional` está preparado para la integración futura. El adaptador mock está activo por defecto. La definición del contrato API se diferiere hasta que se identifique el sistema de RRHH del municipio que adopte VIDA 360.

- **Visibilidad del cuadrante para el profesional:** definir qué parte del cuadrante puede ver y editar el profesional sobre el suyo propio (solicitud de cambio de franja, visualización de compañeros...). Diferido al diseño de la interfaz Livewire.

- **Gestión de conflictos de espacio:** actualmente solo genera aviso. Evaluar en fases posteriores si se justifica un sistema de reserva de espacios con bloqueo efectivo.

- **Estrategia de adopción por modos:** definir el proceso de activación del módulo Agenda en un centro nuevo (qué configuración mínima es necesaria para cada modo, qué hace el sistema si un centro no tiene `HorarioCentro` configurado). Diferido al diseño de la interfaz de onboarding.

- **Migración entre modos:** cuando un centro sube de `basico` a `estandar`, los cuadrantes generados automáticamente y los eventos simplificados existentes son compatibles con el modelo completo sin transformación. Conviene documentar esto explícitamente en la guía de adopción para evitar resistencias.

---

## 8. Diagrama de entidades

```
HorarioCentro ──────────── Centro
    │
    └── TipoSlot

PerfilHorarioProfesional ── Usuario
                        └── Centro

ExcepcionProfesional ────── Usuario
                        └── Centro

CuadranteMes ───────────── Centro
    │
    └── LineaCuadrante ──── Usuario (profesional)
            │           └── ExcepcionProfesional (si anulada)
            │
            └── Slot ────── TipoSlot
                    │   └── Espacio (opcional)
                    │
                    └── Cita ──── Ciudadano
                            │ └── ReasignacionCita
                            └── [origen: interno | api_externa]

EventoAgenda ───────────── Centro
    │                   └── Espacio (opcional)
    └── [BelongsToMany] Usuario (profesionales convocados)
```
