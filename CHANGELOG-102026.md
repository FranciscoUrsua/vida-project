# CHANGELOG — VIDA 360 — Octubre 2026

> Entradas de octubre 2026. Para meses anteriores, ver `CHANGELOG-092026.md`.

---

## 2026-10-01 — Asignación: profesionales dados de baja con casos vigentes

Error 500 en staging en `supervision/asignaciones/actividad` («Undefined array key 26»): el usuario `ts1.ciam@demo.es` se borró desde Filament (soft delete) conservando 58 referencias vigentes, y `ActividadCasosService::resumen()` no cargaba usuarios borrados.

### Cambios
- `ActividadCasosService::resumen()` carga los profesionales con `withTrashed()`: sus casos siguen vigentes hasta que se repartan (RN-08) y el supervisor necesita verlos para iniciar el reparto.
- `RepartoCasos::profesionalOrigen()` y `AsignacionProfesional::profesional()` incluyen usuarios borrados: el reparto y el historial de referencias siguen mostrando quién era el profesional.
- Pestaña «Actividad del equipo»: badge «Dado de baja» junto al nombre.
- Tests: `ActividadCasosTest::el_profesional_dado_de_baja_con_casos_sigue_en_el_resumen` y `RepartoCasosTest::se_reparten_los_casos_de_un_profesional_dado_de_baja` (fallaban antes del arreglo).

### Decisiones
- No se bloquea borrar un usuario con casos vigentes: se deja para decidir (BACKLOG). Mientras tanto, la pantalla de actividad lo muestra para que el supervisor reparta sus casos.
