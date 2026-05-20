# SESSION — VIDA 360

_Actualizado: 2026-05-20_

## Tarea completada

Ciclo de vida de Cita implementado: 7 tests desbloqueados (PF-05.1, PF-05.2, PF-05.4, PF-05.5, PF-05.6, PF-05.7, PF-05.8).

## Estado actual

- **Módulo Agenda — tests funcionales:** 44 tests — 18 pasan ✅ — 26 pendientes ⏳ — 0 fallos.
- **Módulo Usuarios fase 2:** 23 tests pasan ✅, 1 pendiente (TF-USU-31).
- **Módulo Prestaciones:** 45 tests pasan ✅.
- **Módulo Mensajes:** 31 tests pasan ✅.
- **Módulo Intervención:** 35 tests pasan ✅.
- **Módulo Documentos:** 20 tests pasan ✅.
- **Módulo Centros:** 31 tests pasan ✅.
- **Suite completa:** 258 tests pasan ✅ — 0 fallos — 20 incompletos.

## Qué se implementó en esta sesión

**`CitaObserver`** — `Modules/Agenda/app/Observers/CitaObserver.php`:
- `creating`: rechaza reserva de slot urgencia desde canal externo (`LogicException`).
- `created`: actualiza slot a `reservado` al crear la cita.

**Cita model** — métodos añadidos:
- `completar()`: transiciona a `completada`, registra `completada_en`.
- `cancelar(User, string)`: ajusta el slot según si su hora ya pasó → `no_ocupado` (retroactiva) o `disponible` (futura).
- `apuntes()`: `MorphMany` hacia `Apunte` de Intervención via `apuntable`.

**AgendaServiceProvider**: registra `Cita::observe(CitaObserver::class)`.

## Notas técnicas relevantes

- El Observer usa `Slot::find($cita->slot_id)` en `creating` (sin lanzar si null, por si el slot no existe todavía en edge cases de factory). En `created` usa `Slot::where()->update()` (raw, sin disparar eventos de Slot).
- `cancelar()` usa `Slot::findOrFail($this->slot_id)` en lugar de `$this->slot` para garantizar estado fresco de DB.
- El test PF-05.3 ya pasaba antes (DB unique constraint en slot_id); los 7 desbloqueados son PF-05.1/2/4/5/6/7/8.
- PF-05.8 crea un `Apunte` via factory de Intervención (`PlanDeIntervencion::factory()`) con `apuntable_type = Cita::class`. Confirma que `$cita->apuntes` es accesible y que `cancelar()` no destruye los apuntes.

## Siguiente paso recomendado

Los 20 tests pendientes se distribuyen:
- **PF-01** (2): buffer de inicio, día no laborable → `SlotMaterializadorService` ya implementado; buffer en PF-01.1 está pendiente de verificar con test actualizado.
- **PF-02** (3): perfiles horarios, solapamiento de perfiles.
- **PF-04** (5): slots y disponibilidad → `DisponibilidadService`, `SlotExpirationJob`.
- **PF-06** (3): no-show del ciudadano.
- **PF-07** (5): no-show del profesional → `GestionAusenciaService`.
- **PF-08** (4): eventos de agenda → lógica bloqueo slots en `EventoAgenda`.
- **PF-09** (2): profesionales itinerantes.
- **PF-10** (1): slot mayor que franja → ya implementado en SlotMaterializadorService (pasa en PF-10.2).

Siguiente bloque natural: **PF-06 (no-show ciudadano)** — 3 tests, lógica de ciclo de vida en Cita que ya tiene los métodos base implementados. O bien **PF-04** para unbloquear SlotExpirationJob + DisponibilidadService.
