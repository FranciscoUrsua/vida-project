# SESSION — Estado actual del proyecto VIDA 360

**Última actualización:** 2026-06-24

---

## Tarea completada

`Módulo Intervención` — Simplificación de objetivos en PlanPage: badge de estado eliminado de las tarjetas; modal de edición reducido a texto + botón Eliminar; el avance del objetivo queda determinado únicamente por los indicadores.

- **Nueva tabla `asignaciones_profesional`**: registra qué profesional es el responsable de cada Historia Social durante cada período. Campos: `historia_id`, `profesional_id`, `fecha_inicio`, `fecha_fin` (nullable). El historial de cambios se conserva cerrando la asignación vigente y creando una nueva.
- **Nuevo modelo `AsignacionProfesional`** (`Modules\Intervencion\Models\AsignacionProfesional`): scope `vigente()`, relaciones `historia()` y `profesional()`.
- **`HistoriaSocial`**: añadidas relaciones `asignaciones()` (hasMany) y `asignacionVigente()` (hasOne whereNull fecha_fin).
- **`FichaCiudadanoPage::abrirHistoriaSocial()`**: tras crear la Historia Social crea automáticamente la primera asignación vigente con el profesional autenticado.
- **`MisCasosPage::casos()`**: reescrita desde `asignaciones_profesional` (vigentes) como tabla base. Los planes generales ASP pasan a ser LEFT JOIN informativo; los ciudadanos sin plan aparecen en "Mis casos" desde el momento en que se les abre la historia. El filtro `filtroPiso` queda implementado correctamente por primera vez.
- **Vista `mis-casos-page.blade.php`**: columna PISO muestra el estado real del plan (Activo / En revisión / Borrador / Sin plan).
- **Tests**: 10 tests en `MisCasosPageTest` (TF-LW-CAS-01 a TF-LW-CAS-10), todos en verde. Añadidos TF-LW-CAS-08 (ciudadano sin plan aparece si tiene asignación vigente), TF-LW-CAS-09 (asignación cerrada no aparece), TF-LW-CAS-10 (filtro PISO 'sin' funciona).
- **`docs/modulo-intervencion.md`**: añadida sección 1.1 documentando la entidad `AsignacionProfesional`, su modelo de historial y el origen de la asignación inicial.

---

## Estado exacto del proyecto

- **Tests módulo Intervencion**: 236 passed (10 nuevos) / 1 incomplete (pre-existente) / 0 failed.
- **Fallo pre-existente en Ciudadania**: TF-LW-FIC-11 busca "Ver historia social" (texto renombrado a "Ir a HS" en la sesión 2026-06-23). No relacionado con los cambios de hoy.
- **`asignaciones_profesional`**: migración ejecutada en desarrollo.
- **`MisCasosPage`**: ahora muestra todos los ciudadanos con asignación vigente, tengan o no plan activo. El filtro `filtroPiso` = 'sin' muestra los casos sin plan no cerrado.

---

## Siguiente paso concreto recomendado

1. Corregir TF-LW-FIC-11 en `FichaCiudadanoPageTest`: cambiar `assertSee('Ver historia social')` por `assertSee('Ir a HS')` (texto renombrado en sesión anterior).
2. Añadir tests para `eliminarObjetivo()` en PlanPageTest (caso happy path y verificación de que solo se puede eliminar un objetivo del propio plan).
3. Implementar reasignación de profesional de referencia (cambiar asignación vigente → cerrar la actual, crear nueva).
4. Suite completa antes del siguiente merge a main.

---

## Contexto para retomar sin fricción

- La tabla `asignaciones_profesional` vive en `Modules/Intervencion/database/migrations/2026_06_24_000001_create_asignaciones_profesional_table.php`.
- El modelo es `Modules\Intervencion\Models\AsignacionProfesional` (namespace sin `app/`).
- `MisCasosPage` ya no filtra por `planes_intervencion.profesional_responsable_id`. El origen de "Mis casos" es siempre la asignación vigente.
- Los tests existentes (TF-LW-CAS-01 a TF-LW-CAS-07) se mantienen usando el helper `crearPlan()` actualizado, que ahora crea también la asignación.
