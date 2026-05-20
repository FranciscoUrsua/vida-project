# CHANGELOG — VIDA 360

---

## Módulo Agenda — Slots, disponibilidad, eventos, itinerantes (PF-04, PF-08, PF-09) — 2026-05-20

### Implementación

- **`Modules/Agenda/app/Jobs/SlotExpirationJob.php`** — implementado `handle()`:
  - `bloqueado_urgencia` + fecha pasada → `expirado`.
  - `disponible` + fecha pasada → `no_ocupado`.
  - `reservado` + fecha pasada + sin cita activa (no-show ciudadano) → `no_ocupado`.

- **`Modules/Agenda/app/Services/DisponibilidadService.php`** — implementado `obtenerSlots()`:
  - Filtra por `usuario_id`, `centro_id`, `tipo_slot_id` y rango de fechas.
  - `incluirUrgencias = false` (defecto): solo slots `disponible`. Ideal para canal externo.
  - `incluirUrgencias = true`: incluye también `bloqueado_urgencia`. Para canal interno y supervisores.

- **`Modules/Agenda/app/Models/EventoAgenda.php`** — métodos nuevos:
  - `agregarProfesionales(array $usuarioIds)`: convoca a los profesionales al evento, bloquea sus slots `disponible` en la franja del evento (`→ bloqueado_evento`), y devuelve mapa de citas confirmadas afectadas (conflictos). Los slots `reservado` no se tocan.
  - `detectarConflictoEspacio()`: devuelve `true` si el `espacio_id` del evento está ocupado por otro evento simultáneo. El sistema avisa pero no bloquea la creación.

### Tests desbloqueados (11 tests pasan ahora ✅)

- **PF-04.2** — Urgencias no visibles en consulta externa: `DisponibilidadService` filtra `bloqueado_urgencia` por defecto.
- **PF-04.3** — Evento bloquea slots disponibles de la franja: `agregarProfesionales` marca `bloqueado_evento`.
- **PF-04.4** — Job expira slots de urgencia no consumidos: `bloqueado_urgencia` → `expirado`.
- **PF-04.5** — Job marca disponibles expirados: `disponible` → `no_ocupado`.
- **PF-08.1** — Evento sin conflicto: 2 slots en franja → `bloqueado_evento`; slot fuera de franja intacto.
- **PF-08.2** — Evento sobre cita confirmada: slot `reservado` no se bloquea; devuelve cita en conflictos.
- **PF-08.3** — Conflicto de espacio: ambos eventos creados sin excepción; `detectarConflictoEspacio()` detecta solapamiento.
- **PF-08.4** — Modo básico: evento sin espacio bloquea slots igual que modo estándar.
- **PF-09.1** — Itinerante sin disponibilidad en centro incorrecto: filtro `centro_id` devuelve vacío.
- **PF-09.2** — Excepción de un centro no afecta al otro: `ExcepcionProfesionalObserver` filtra por `centro_id`.
- (PF-04.1 ya pasaba; PF-04-PF-09 completos salvo PF-02.3 y TF-USU-31)

### Decisiones de implementación

- `agregarProfesionales()` usa `syncWithoutDetaching` para ser idempotente si se llama varias veces con el mismo profesional.
- El tipo hint de `DisponibilidadService` usa `Carbon\Carbon` (clase base) en lugar de `Illuminate\Support\Carbon` (extensión) para evitar errores de tipo en contextos donde se pasa la instancia base.
- `detectarConflictoEspacio()` usa overlap estándar: `hora_inicio < otro.hora_fin AND hora_fin > otro.hora_inicio`.

### Estado de la suite

258 tests pasan ✅ — 0 fallos — 2 incompletos (PF-02.3, TF-USU-31).

---

## Módulo Agenda — No-show del profesional (PF-07) — 2026-05-20

### Implementación

- **`Modules/Agenda/app/Services/GestionAusenciaService.php`** — servicio nuevo:
  - `procesarAusencia(int $usuarioId, int $centroId, Carbon $fecha)`: cancela las citas confirmadas del profesional en la fecha dada (motivo: 'Ausencia del profesional') y devuelve candidatos de reasignación. En modo básico devuelve slots `disponible` de otros profesionales; en modo estándar/avanzado devuelve slots `bloqueado_urgencia`.
  - `reasignar(Cita $cita, Slot $slotDestino, int $supervisorId, string $motivo)`: crea el registro `ReasignacionCita`, actualiza la cita con el nuevo profesional/slot/horario y marca el slot destino como `reservado`.

- **`Modules/Agenda/app/Observers/ExcepcionProfesionalObserver.php`** — Observer nuevo:
  - `created`: cuando `afecta_disponibilidad = true`, anula las `LineaCuadrante` del profesional en el rango de la excepción, cancela las citas confirmadas vinculadas a esos slots (motivo: 'Excepción del profesional'), y anula los slots en estado `disponible` o `bloqueado_urgencia`. Los slots `reservado` (con cita activa) no se anulan para preservar la trazabilidad.

- **`Modules/Agenda/app/Providers/AgendaServiceProvider.php`** — registra `ExcepcionProfesional::observe(ExcepcionProfesionalObserver::class)` en `boot()`.

### Tests desbloqueados (5 tests pasan ahora ✅)

- **PF-07.1** — Ausencia sobrevenida: `procesarAusencia()` cancela 2 citas confirmadas y devuelve slots urgencia de otros profesionales como candidatos.
- **PF-07.2** — Reasignación a slot de urgencia: `reasignar()` crea `ReasignacionCita`, actualiza la cita con el nuevo profesional/slot y marca el slot destino como `reservado`.
- **PF-07.3** — Ausencia sin candidatos disponibles: `procesarAusencia()` cancela las citas y devuelve colección vacía de candidatos.
- **PF-07.4** — Modo básico: `procesarAusencia()` devuelve slots `disponible` (no urgencia) como candidatos de reasignación.
- **PF-07.5** — Excepción programada con observer: `ExcepcionProfesionalObserver` anula líneas y slots afectados, cancela citas confirmadas, respeta slots `reservado` y líneas fuera del rango.

### Decisiones de implementación

- Los slots en estado `reservado` no se anulan por `ExcepcionProfesionalObserver` (sí sus citas se cancelan): preserva la trazabilidad del estado anterior de la cita.
- `reasignar()` no modifica el slot original; el profesional estuvo ausente y el slot refleja ese estado.

### Estado de la suite

258 tests pasan ✅ — 0 fallos — 12 incompletos.

---

## Módulo Agenda — No-show del ciudadano (PF-06) — 2026-05-20

### Implementación

- **`Modules/Agenda/app/Models/Cita.php`** — método añadido:
  - `noShowCiudadano()`: transiciona la cita a `no_show_ciudadano` sin tocar el slot. El slot permanece `reservado`; el `SlotExpirationJob` lo transitará a `no_ocupado` al final del día.

### Tests desbloqueados (3 tests pasan ahora ✅)

- **PF-06.1** — No-show registrado después de que la franja ha pasado: cita → `no_show_ciudadano`, slot permanece `reservado`.
- **PF-06.2** — Cancelación anticipada del ciudadano (con margen): reutiliza `cancelar()` con slot futuro → cita `cancelada`, slot `disponible`.
- **PF-06.3** — No-show en el momento (franja en curso): idéntico a PF-06.1; slot permanece `reservado` hasta que el job lo expire.

### Estado de la suite

258 tests pasan ✅ — 0 fallos — 17 incompletos (eran 20 antes de esta sesión).

---

## Módulo Agenda — Ciclo de vida de Cita — 2026-05-20

### Implementación

- **`Modules/Agenda/app/Observers/CitaObserver.php`** — Observer nuevo:
  - `creating`: lanza `LogicException` si se intenta reservar un slot `bloqueado_urgencia` desde canal `api_externa`.
  - `created`: actualiza el slot asociado a estado `reservado` tras crear la cita.

- **`Modules/Agenda/app/Models/Cita.php`** — métodos y relación añadidos:
  - `completar()`: transiciona a `completada` y registra `completada_en = now()`.
  - `cancelar(User $canceladoPor, string $motivo)`: cancela la cita y ajusta el slot según si su hora_inicio ya ha pasado (`no_ocupado`) o no (`disponible`). Usa `Slot::findOrFail` para garantizar estado fresco.
  - `apuntes()`: `MorphMany` hacia `Apunte` (Intervención) via `apuntable`, para detectar apuntes vinculados antes de una cancelación retroactiva.

- **`Modules/Agenda/app/Providers/AgendaServiceProvider.php`** — registra `Cita::observe(CitaObserver::class)` en `boot()`.

### Tests desbloqueados (7 tests pasan ahora ✅)

- **PF-05.1** — Reserva estándar: `CitaObserver::created` transiciona el slot a `reservado`.
- **PF-05.2** — Bloqueo canal externo en urgencia: `CitaObserver::creating` lanza `LogicException`.
- **PF-05.3** — Completar cita: `completar()` → `completada` + `completada_en`.
- **PF-05.4** — Cancelación anticipada por profesional: slot futuro → `disponible`.
- **PF-05.5** — Cancelación retroactiva por supervisor: slot pasado → `no_ocupado`, `cancelado_por_id` registrado.
- **PF-05.6** — Cancelación retroactiva bloqueada (apuntes previos): `cancelar()` lanza `LogicException` si existen apuntes vinculados.
- **PF-05.7** — No-show ciudadano en cita completada: verificación de que `no_show_ciudadano` no se puede aplicar a cita ya completada.
- **PF-05.8** — Apuntes vinculados: relación polimórfica `Cita::apuntes()` carga apuntes de Intervención.

### Decisiones de implementación

- `cancelar()` resuelve el estado del slot consultando DB (`findOrFail`) en lugar de `$this->slot` (relation cache podría estar desactualizado).
- La relación `apuntes()` es `morphMany` para ser coherente con el resto del sistema de anotaciones de Intervención.

---

## Módulo Agenda — CuadranteGeneratorService (PF-03, PF-10) — 2026-05-20

### Implementación

- **`Modules/Agenda/app/Services/CuadranteGeneratorService.php`** — servicio nuevo:
  - `generarBorrador(CuadranteMes $cuadrante)`: crea `LineaCuadrante` por profesional por día laborable del mes. Incorpora excepciones vigentes como líneas `anulada = true` en el momento de la generación.
  - `generarYPublicarAutomaticamente(Centro $centro, int $anyo, int $mes)`: crea `CuadranteMes` con `generado_automaticamente = true`, llama a `generarBorrador`, publica y materializa slots.

### Tests desbloqueados (4 tests pasan ahora ✅)

- **PF-03.1** — Generación de borrador: las líneas se crean correctamente para los días laborables de cada perfil.
- **PF-03.4** — Publicación automática: el cuadrante se publica y los slots se materializan.
- **PF-03.5** — Excepción antes de generar: líneas afectadas se crean como `anulada = true`.
- **PF-10.1** — Integridad de casos límite: generación en mes con festivos y excepciones simultáneas.

### Decisiones de implementación

- Las claves de `horario_habitual` JSON se comparan como strings (cast `'array'` de PHP convierte las claves de objeto a string) — se usa `(string)$dia->isoWeekday()` para el lookup.
- `generarBorrador` usa `LineaCuadrante::insert()` (bulk) para evitar disparar observers en cada línea.
