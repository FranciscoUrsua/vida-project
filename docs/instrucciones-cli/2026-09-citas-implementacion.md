# Instrucciones de implementación — Citas

**Módulo:** `Agenda` (`Modules/Agenda/`), subdominio Citas. Toca también `Intervencion`, `Atencion`, `Mensajes` y `Ciudadania`.
**Tipo de trabajo:** evolución de módulos existentes, no módulo nuevo.
**Diseño funcional:** `docs/modulo-citas.md`.
**Tests asociados:** `docs/instrucciones-cli/2026-09-citas-tests.md` (TF-CIT-01 a TF-CIT-43, y revisión de PF-05.1, PF-05.5 y PF-06.2).
**Requisito previo:** `docs/instrucciones-cli/2026-09-asignacion-implementacion.md` implementado (profesional de referencia y `dias_ausencia_prolongada`).

---

## Antes de empezar

Lee, en este orden:

1. `CLAUDE.md`, `SESSION.md` y `docs/principios-vida360.md`.
2. `docs/modulo-citas.md` completo. Es el **qué** y el **por qué**; este documento es el **cómo**.
3. `docs/modulo-agenda.md` completo: `Slot`, `Cita`, `ReasignacionCita`, `EventoAgenda`, `DisponibilidadService`, `GestionAusenciaService`, `SlotExpirationJob`, canal API.
4. `docs/modulo-intervencion.md` §3 (Entrevista) y §7 (Apunte); `docs/modulo-atencion.md` (tabla `registros_atencion`).
5. `docs/modulo-mensajes.md` (alertas y enlaces de contexto) y `docs/modulo-auditoria.md`.
6. `docs/design-system/SKILL.md` y `docs/instrucciones-cli/2026-09-bootstrap-unico.md` antes de cualquier Blade.
7. El documento de tests.

Si este documento contradice a `docs/modulo-agenda.md`, manda este. Al terminar, `modulo-agenda.md` debe quedar actualizado (paso 11).

**Regla de trabajo:** si encuentras un caso no cubierto aquí (un campo que falta, una relación ambigua, un conflicto con código existente), **detente y pregunta** antes de decidir. No improvises decisiones de diseño. Lo marcado como *fuera de alcance* o *pendiente* no se implementa.

---

## 1. Qué cambia

| Hoy | Después de esta fase |
|---|---|
| La cita la crea "el profesional o el supervisor" | La crean `consulta_basica` y supervisión, siempre a través de `CitacionService` |
| No existe la demanda como tal | `SolicitudCita` + bandeja de citación del centro |
| El profesional marca la cita como completada; `genera_apunte_automatico` crea un apunte | El apunte o el registro de atención completan la cita. Se retira `genera_apunte_automatico` |
| Cambiar la fecha no está definido | Reprogramación: nueva cita enlazada a la anterior, estado `reprogramada` |
| Rastro en campos sueltos | Tabla `cita_eventos`, solo inserción |
| `EventoAgenda` sin ciudadanos | Puede referenciar ciudadanos |
| Cita externa exige ciudadano | Puede llegar pendiente de identificar |

Principios que no se negocian:

- **Ningún camino crea, mueve o cierra citas fuera de los servicios.** Ni la UI, ni los observers de otros módulos escriben directamente en `citas`, `solicitudes_cita` o `cita_eventos`.
- **Todo cambio de estado escribe su evento en la misma transacción.**
- **`cita_eventos` es de solo inserción** y no entra en la purga de `audits`.
- **Datos personales cifrados** (cast `encrypted`): motivos, observaciones, nombres de acompañantes, datos de identificación externos.
- **El sistema propone huecos, la persona elige** (principio 3.10). Ningún proceso asigna una cita solo.
- **El sistema nunca cierra una cita por su cuenta.**

---

## 2. Fuera de alcance (no construir)

- Notificaciones al ciudadano (SMS, email, carpeta ciudadana).
- Contrato real con Cita Previa: mapeo de servicios, reconciliación. Solo se amplía el adaptador mock.
- Indicadores y cuadros de mando (demora, incomparecencias…). Solo se guardan los datos.
- Registro de intentos de contacto o límite de intentos.
- Avisos por incomparecencias reiteradas.
- Cambios en la generación de cuadrantes o en la materialización de slots.

---

## Paso 0 — Verificación del entorno

1. `git pull origin master`.
2. `php artisan test --filter=Agenda`. El BACKLOG registra ~60 tests de Agenda fallando por `tipos_slot.horario_centro_id` tras convertir `TipoSlot` en catálogo global (commit `3003283`). **Si siguen fallando, detente y avisa**: hay que arreglar factories y helpers antes de construir encima. No lo arregles por tu cuenta sin confirmación.
3. Comprueba el estado real del código frente a `modulo-agenda.md`: enum de estados de `Cita`, existencia de `Cita::completar()`, `cancelar()`, `noShowCiudadano()`, `CitaObserver`, relación `Cita::apuntes()` y dónde se lee `genera_apunte_automatico`. Informa de cualquier diferencia antes de seguir.
4. Comprueba que la fase de asignación está implementada. El **profesional de referencia** de un ciudadano es su `AsignacionProfesional` vigente (`fecha_fin` nula) en su Historia Social. Si la fase no está hecha, detente y avisa.
5. El **perfil** de un profesional para el destino "servicio" es su `cargo` (tabla `profesionales.cargo_id`). `servicio_destino` guarda el `cargos.slug`. Comprueba que el slug existe y es estable; si no, detente y pregunta.

---

## Paso 1 — Tipos de cita

### Tabla `tipos_cita`

| Campo | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `codigo` | varchar(100) unique | Inmutable si hay citas del tipo |
| `nombre` | varchar(200) | Nombre interno |
| `etiqueta_publica` | varchar(100) | Nombre neutro |
| `herramienta` | enum | `entrevista_inicial` / `entrevista_seguimiento` / `valoracion` / `plan` / `atencion` / `ninguna` |
| `modalidad_defecto` | enum | `presencial` / `telefonica` / `videollamada` / `domicilio`. Reutiliza el enum de `Entrevista.modalidad` si existe |
| `requiere_historia_social` | boolean | |
| `activo` | boolean | |
| timestamps, soft deletes | | |

Pivote `tipo_cita_tipo_slot` (`tipo_cita_id`, `tipo_slot_id`).

Enums PHP: `HerramientaCita`, `ModalidadCita` (o el existente). `herramienta` es enum porque el código decide con él.

### Filament

`TipoCitaResource` en el grupo *Agenda — Configuración*, junto a `TipoSlotResource`. Solo `adm_sistema`. Versionable (principio 3.5).

### Seeder

Tipo genérico `cita` ("Cita" / "Cita", `herramienta = ninguna`, compatible con el tipo de slot genérico). Idempotente.

---

## Paso 2 — Modelo de datos

### `solicitudes_cita`

| Campo | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `ciudadano_id` | FK | |
| `centro_id` | FK | |
| `solicitante_id` | FK users | |
| `canal` | enum `CanalSolicitudCita` | `presencial` / `telefonico` / `interno` / `seguimiento` |
| `tipo_cita_id` | FK | |
| `urgencia` | enum `UrgenciaCita` | `ordinaria` / `preferente` / `urgente` |
| `destino` | enum `DestinoCita` | `profesional_concreto` / `referencia` / `servicio` / `primer_libre` |
| `profesional_destino_id` | FK nullable | Obligatorio si `destino = profesional_concreto` |
| `servicio_destino` | varchar nullable | `cargos.slug` (paso 0.5) |
| `no_antes_de` | date nullable | |
| `no_despues_de` | date | Se calcula si no se informa |
| `motivo` | text nullable, `encrypted` | |
| `observaciones_citacion` | text nullable, `encrypted` | |
| `contexto_type` / `contexto_id` | morph nullable | Plan, seguimiento, registro de atención |
| `solicitud_anterior_id` | FK nullable | Solicitud abierta al cancelar una cita |
| `estado` | enum `EstadoSolicitudCita` | `pendiente` / `en_gestion` / `citada` / `desistida` / `anulada` |
| `gestionada_por_id` | FK nullable | |
| `resuelta_en` | timestamp nullable | |
| `motivo_cierre` | text nullable, `encrypted` | Obligatorio en `desistida` y `anulada` |
| timestamps, soft deletes | | |

Índices: `(centro_id, estado, urgencia, no_despues_de)`, `(ciudadano_id)`.

Las transiciones válidas se validan en el modelo (`LogicException` si no lo son). `citada`, `desistida` y `anulada` son finales.

### `citas` (ampliación)

| Campo | Tipo | Notas |
|---|---|---|
| `solicitud_cita_id` | FK nullable | Null solo si `origen = api_externa` |
| `tipo_cita_id` | FK | Migración: las citas existentes toman el tipo genérico |
| `modalidad` | enum | |
| `modo_asignacion` | enum `ModoAsignacionCita` | `profesional_concreto` / `referencia` / `sustituto` / `primer_libre`. Existentes: `profesional_concreto` |
| `cita_anterior_id` | FK nullable unique | Una cita solo puede reprogramarse una vez |
| `ciudadano_id` | pasa a **nullable** | Solo con `origen = api_externa`; validar en el modelo |
| `datos_identificacion_externos` | jsonb nullable, `encrypted:array` | |
| `pendiente_cierre` | boolean default false | |
| `pedido_por_cancelacion` | enum nullable `PedidoPor` | `ciudadano` / `centro` / `profesional` / `sistema` |

Estado nuevo `reprogramada` en el enum de estados. Índice único `(origen, referencia_externa)` donde `referencia_externa` no es null.

### `cita_eventos`

| Campo | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `solicitud_cita_id` | FK nullable | |
| `cita_id` | FK nullable | Check: al menos uno informado |
| `accion` | enum `AccionCitaEvento` | Lista en `modulo-citas.md` §2.4 |
| `actor_id` | FK nullable | |
| `actor_tipo` | enum | `usuario` / `api_externa` / `sistema` |
| `canal` | enum nullable | `presencial` / `telefonico` / `interno` / `api` |
| `pedido_por` | enum nullable `PedidoPor` | |
| `estado_antes` / `estado_despues` | varchar nullable | |
| `motivo` | text nullable, `encrypted` | |
| `datos` | jsonb nullable | Slot origen y destino, profesional, fechas. **Sin datos personales en claro** |
| `created_at` | timestamptz | Sin `updated_at` |

Solo inserción: el modelo lanza `LogicException` en `updating` y `deleting`, y la migración crea un trigger de PostgreSQL que rechaza UPDATE y DELETE. Comprueba que el comando de purga de `audits` no toca esta tabla.

### `cita_acompanantes`

| Campo | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `cita_id` | FK | |
| `relacion` | varchar | `catalogos_sistema`, grupo `cita.relacion_acompanante` (seeder con los valores de `modulo-citas.md` §2.5) |
| `ciudadano_id` | FK nullable | |
| `nombre` | varchar nullable, `encrypted` | Check: `ciudadano_id` o `nombre` |
| `registrado_por_id` | FK | |
| timestamps | | |

### Otras tablas

- `evento_ciudadano` (`evento_agenda_id`, `ciudadano_id`, timestamps).
- `apuntes.cita_id` (FK nullable) y `apuntes.evento_agenda_id` (FK nullable).
- `registros_atencion.cita_id` (FK nullable). Distinto de `cita_generada_id`, que se mantiene.
- `horarios_centro`: `dias_ausencia_prolongada` (ya añadido en la fase de asignación; reutilízalo), `plazos_urgencia` (jsonb, default `{"ordinaria": 20, "preferente": 7, "urgente": 2}`), `dias_aviso_cierre_supervisor` (int, default 3). Editables en `HorarioCentroResource`.
- `tipos_slot.genera_apunte_automatico`: retirar la lectura en código. **Pregunta antes de eliminar la columna.**

### Relaciones

Todas con PHPDoc completo según `CLAUDE.md`:
- `SolicitudCita`: `ciudadano()`, `centro()`, `solicitante()`, `tipoCita()`, `profesionalDestino()`, `contexto()`, `citas()`, `eventos()`, `solicitudAnterior()`.
- `Cita`: `solicitud()`, `tipoCita()`, `citaAnterior()`, `reprogramacion()`, `eventos()`, `acompanantes()`, `apuntes()` (redefinida sobre `apuntes.cita_id`), `registrosAtencion()`.
- `EventoAgenda`: `ciudadanos()`.
- `Apunte`: `cita()`, `eventoAgenda()`. `RegistroAtencion`: `citaAtendida()`.

`getCiudadanoId()` en `SolicitudCita`, `Cita`, `CitaEvento` y `CitaAcompanante` para auditoría.

---

## Paso 3 — Servicios

En `Modules/Agenda/app/Services/`. Cada método público valida permisos con la Policy correspondiente, abre transacción y escribe su `CitaEvento` a través de un único `RegistroEventosCita::registrar(...)`.

### `SolicitudCitaService`

- `crear(array $datos, User $solicitante): SolicitudCita` — valida destino, calcula `no_despues_de` con `plazos_urgencia` del centro (días laborables según `HorarioCentro`) si falta, lanza alerta a `consulta_basica` del centro.
- `tomar(SolicitudCita, User)` — con bloqueo de fila (`lockForUpdate`) para que dos usuarios no la tomen a la vez.
- `liberar(SolicitudCita, User, ?string $nota)`.
- `desistir(SolicitudCita, User, string $motivo)`, `anular(SolicitudCita, User, string $motivo)`.

### `BusquedaHuecosService`

- `buscar(SolicitudCita, int $limite = 10): Collection<PropuestaHueco>` — reglas de `modulo-citas.md` §3.3. Se apoya en `DisponibilidadService::obtenerSlots()`; no consulta `slots` por su cuenta si ese servicio ya lo resuelve.
- `PropuestaHueco` es un DTO de solo lectura: slot, profesional, `modo_asignacion` resultante.
- La ausencia prolongada se calcula con las `ExcepcionProfesional` vigentes que afectan a disponibilidad dentro de la ventana, contra `dias_ausencia_prolongada`.

### `CitacionService`

- `citar(SolicitudCita, Slot, ModoAsignacionCita, User): Cita`.
- `citarDirecto(array $datos, Slot, ModoAsignacionCita, User): Cita` — solicitud y cita en una transacción.
- `reprogramar(Cita, Slot $nuevo, PedidoPor, string $motivo, User): Cita` — original a `reprogramada`, slot original liberado con las mismas reglas que `Cita::cancelar()`, nueva cita con `cita_anterior_id` y mismo `solicitud_cita_id`. Si la original es externa, notificación saliente por el adaptador.
- `cancelar(Cita, PedidoPor, string $motivo, User, bool $abrirSolicitud = false): ?SolicitudCita` — envuelve `Cita::cancelar()`. Si `$abrirSolicitud`, crea una solicitud nueva copiando tipo, urgencia y destino, con `solicitud_anterior_id`.

Los métodos del modelo `Cita` (`cancelar()`, etc.) siguen existiendo, pero **pasan a ser internos**: añade un comentario en su PHPDoc indicando que solo se llaman desde los servicios.

### `AtencionCitaService`

- `registrarNoShow(Cita, User)` — envuelve `Cita::noShowCiudadano()`.
- `completarPorApunte(Cita, Apunte)` / `completarPorAtencion(Cita, RegistroAtencion)` — idempotentes: si la cita ya está completada, no hacen nada ni escriben evento.
- `registrarAcompanantes(Cita, array $acompanantes, User)`.
- `identificarCiudadano(Cita, Ciudadano, User)` — para citas externas pendientes de identificar.
- `solicitarCambio(Cita|Slot, string $texto, User)` — mensaje uno a uno al supervisor del centro (Módulo Mensajes) con enlace de contexto a la cita o al slot, y evento `cambio_solicitado`.

### Ajustes en servicios existentes

- `GestionAusenciaService::procesarAusencia()` y `reasignar()`: escriben sus eventos (`cita_cancelada` con `pedido_por = centro`, `cita_reasignada`).
- `CitaObserver`: mantiene la transición del slot; no escribe eventos (los escriben los servicios).

---

## Paso 4 — Cierre implícito

- Observer en `Apunte` (`created`): si `cita_id` está informado, llama a `AtencionCitaService::completarPorApunte()`. Igual en `RegistroAtencion`.
- Validación al crear el apunte con `cita_id`: la cita debe ser del mismo ciudadano y estar `confirmada`, `completada` o `pendiente_cierre`. Si está en `no_show_ciudadano` o `cancelada`, se rechaza salvo usuario con `supervision` y motivo.
- `Entrevista`: al generar su apunte, copia `cita_id` al apunte.
- Retirar el camino actual "completar cita → apunte automático".

---

## Paso 5 — Jobs

### `CitaCierreJob` (nuevo)

Al final de cada día laboral, **antes** que `SlotExpirationJob` (encadénalos o ajusta el scheduler).
- Citas `confirmada` con franja pasada, sin apunte ni registro de atención vinculado → `pendiente_cierre = true`, evento `marcada_pendiente_cierre`, alerta al profesional.
- Citas que llevan más de `dias_aviso_cierre_supervisor` días laborables pendientes → alerta al supervisor (una sola vez por cita).
- Nunca cambia el estado de la cita.

### `SlotExpirationJob` (ajuste)

Un slot `reservado` cuya cita está `pendiente_cierre` no pasa a `no_ocupado`. Cuando la cita se cierre (completada o incomparecencia), el siguiente pase del job aplica la regla normal.

### Alerta de solicitudes fuera de plazo

En el mismo job diario o en uno propio: solicitudes `pendiente`/`en_gestion` con `no_despues_de` pasada → alerta al supervisor del centro, una vez por solicitud.

---

## Paso 6 — Canal externo

- Endpoint existente `POST /api/v1/agenda/citas`: pasa por `CitacionService` (añade un método `recibirExterna(...)` si hace falta) y escribe `cita_creada` con `actor_tipo = api_externa`.
- Idempotencia: si existe una cita con el mismo `(origen, referencia_externa)`, devuelve la existente con 200, sin crear ni escribir evento.
- Identificación: busca por `CiudadanoIdentificador` (tipo y número). Una coincidencia única → `ciudadano_id`. Ninguna o varias → `ciudadano_id = null` y `datos_identificacion_externos`.
- El tipo de cita de las citas externas es el genérico hasta que exista el contrato. Déjalo en un único punto del adaptador, marcado con `// TODO: mapeo de servicios de Cita Previa`.
- El mock adapter acepta ahora datos de identificación en el payload.

---

## Paso 7 — Permisos

Policies `SolicitudCitaPolicy` y `CitaPolicy` según la tabla de `modulo-citas.md` §7. Comprueba si existen permisos atómicos por rol (ver `modulo-atencion.md`, "Permisos atómicos nuevos") y sigue ese patrón: `citas.solicitar`, `citas.gestionar`, `citas.atender`, `citas.supervisar`.

Regla de "citas propias" (RN-05 bis): `reprogramar` y `cancelar` devuelven `false` si `cita.profesional_id` es el usuario autenticado, salvo que tenga `supervision`. `citar` sí se permite sobre slots propios.

La visibilidad de `motivo` y de `TipoCita.nombre` se resuelve en un único método (p. ej. `SolicitudCita::motivoVisiblePara(User)` y `TipoCita::nombreParaUsuario(User)`), no en las vistas.

---

## Paso 8 — Interfaz (Livewire, Bootstrap)

Antes de cualquier Blade: design system y `2026-09-bootstrap-unico.md`. Termina con `npm run build` y `php artisan ui:auditar` en verde.

1. **Bandeja de citación** (nueva página en Agenda): lista según `modulo-citas.md` §6; acciones tomar, soltar, buscar huecos y citar, desistir, anular; bloque de citas externas pendientes de identificar.
2. **Formulario de cita directa** (ventanilla/teléfono): búsqueda de ciudadano, tipo, urgencia, destino, propuestas de hueco, confirmación. Reutilizable desde el registro de atención (activa `cita_generada_id`, ver BACKLOG).
3. **Solicitar cita** desde `CiudadanoPage` (acción del toolbox) y al programar el siguiente seguimiento en `SeguimientoPlan`.
4. **Agenda del profesional:** acciones sobre cada cita: *Atender* (abre `CiudadanoPage` con la herramienta de `TipoCita.herramienta`, `cita_id` y contexto), *Incomparecencia*, *Acompañantes*, *Pedir cambio*. Sin acciones de reprogramar ni cancelar para el propio profesional. Marca visual de `pendiente_cierre` y de "pendiente de identificar".
5. **Propuesta de vinculación** en las herramientas de `CiudadanoPage`: si hay cita del ciudadano con el usuario hoy o `pendiente_cierre`, casilla "Vincular a la cita de las HH:MM" marcada por defecto.
6. **Timeline:** sección *Cita* o *Coordinación* solo en la tarjeta desplegada (`modulo-citas.md` §5). El resumen no cambia. Los colores y tonos salen de `App\Support\Ui\Tono`.
7. **Gestión de citas** (reprogramar, cancelar con casilla "Abrir nueva solicitud") para `consulta_basica` y supervisión, desde la bandeja y desde la agenda del centro.
8. **Filament:** `TipoCitaResource`; campos nuevos en `HorarioCentroResource`; valores del catálogo `cita.relacion_acompanante`.

---

## Paso 9 — Mensajes

- Cita y slot como elementos enlazables en `mensajes` (enlaces de contexto).
- Alertas nuevas: solicitud creada (a `consulta_basica` del centro), solicitud fuera de plazo (supervisor), cita pendiente de cierre (profesional), pendiente de cierre prolongada (supervisor). Usa los niveles de gravedad existentes; si dudas del nivel, pregunta.

---

## Paso 10 — Datos de demo

Con `CitacionService::citarDirecto()` ya es posible crear citas desde los mundos demo. Añade citas a un escenario existente (sin romper `demo:reset`) y marca como resuelta la entrada "Citas en escenarios de demo" del BACKLOG. No uses `migrate:fresh`.

---

## Paso 11 — Tests, documentación y cierre

1. Implementa los tests de `docs/instrucciones-cli/2026-09-citas-tests.md` y revisa PF-05.1, PF-05.5 y PF-06.2.
2. `php artisan test --filter=Agenda`, después los filtros de `Intervencion`, `Atencion` y `Mensajes`, y después **la suite completa** (esta fase toca código transversal). No des la fase por terminada con fallos nuevos.
3. Actualiza `docs/modulo-agenda.md` según `modulo-citas.md` §9, `docs/modulo-intervencion.md` §7, `docs/modulo-atencion.md` y `docs/modulo-mensajes.md`.
4. Actualiza `docs/documentacion-proyecto.md` §10 (Módulo Agenda).
5. `CHANGELOG.md`: cambios, **decisiones de implementación que no estaban en estas instrucciones**, ficheros adaptados.
6. `BACKLOG.md`: notificación al ciudadano, contrato con Cita Previa, indicadores de citas (módulo de analítica), eliminación de la columna `genera_apunte_automatico` si no se hizo.
7. Añade este fichero y el de tests a la tabla de `CLAUDE.md` (sección 6).
8. Actualiza `SESSION.md`.

## Criterios de finalización

1. Tests de Agenda existentes pasando, con PF-05.1, PF-05.5 y PF-06.2 reescritos.
2. TF-CIT-01 a TF-CIT-43 implementados y pasando.
3. Suite completa sin fallos nuevos.
4. Ninguna escritura en `citas`, `solicitudes_cita` o `cita_eventos` fuera de los servicios de Agenda y sus modelos (verificable con `grep`; indica el resultado en el CHANGELOG).
5. `cita_eventos` rechaza UPDATE y DELETE también a nivel de base de datos.
6. `ui:auditar` en verde.
7. Documentación, CHANGELOG, BACKLOG, CLAUDE.md y SESSION.md actualizados.
