# SESSION — VIDA 360

_Actualizado: 2026-05-20_

## Tarea completada

No-show del ciudadano (PF-06) implementado: 3 tests desbloqueados.

## Estado actual

- **Módulo Agenda — tests funcionales:** 44 tests — 21 pasan ✅ — 23 pendientes ⏳ — 0 fallos.
- **Suite completa:** 258 tests pasan ✅ — 0 fallos — 17 incompletos.

## Qué se implementó en esta sesión

**`Cita::noShowCiudadano()`** — `Modules/Agenda/app/Models/Cita.php`:
- Transiciona `estado → no_show_ciudadano`.
- No modifica el slot; permanece `reservado` hasta que `SlotExpirationJob` lo procese.

PF-06.2 (cancelación anticipada del ciudadano) reutiliza `cancelar()` existente con slot en el futuro → slot `disponible`.

## Notas técnicas relevantes

- La distinción clave de PF-06: `noShowCiudadano()` no toca el slot (a diferencia de `cancelar()`). El slot se limpia mediante el job de expiración al final del día.
- PF-06.3 es comportamentalmente idéntico a PF-06.1 — ambos usan `noShowCiudadano()` y verifican que el slot permanece `reservado`.

## Distribución de los 17 tests pendientes restantes

| Área | Tests | Requiere |
|---|---|---|
| PF-01 (buffer, día no laborable) | 2 | `SlotMaterializadorService` (ya implementado) |
| PF-02 (perfiles horarios) | 3 | Validación solapamiento + `SlotMaterializadorService` |
| PF-04 (slots y disponibilidad) | 5 | `DisponibilidadService`, `SlotExpirationJob` |
| PF-07 (no-show profesional) | 5 | `GestionAusenciaService` |
| PF-08 (eventos de agenda) | 4 | Lógica bloqueo slots en `EventoAgenda` |
| PF-09 (itinerantes) | 2 | `DisponibilidadService` |

## Siguiente paso recomendado

**PF-07 (no-show del profesional)** — 5 tests, requiere `GestionAusenciaService`. Es el bloque más rico en lógica de negocio: gestión de ausencias sobrevenidas, reasignación a slots de urgencia y flujo en modo básico.

O bien **PF-04** para implementar `SlotExpirationJob` + `DisponibilidadService`, que desbloquea también los 2 tests de PF-09 (itinerantes).
