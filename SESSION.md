# SESSION — Estado actual del proyecto VIDA 360

**Última actualización:** 2026-06-26

---

## Tarea completada

`Plazas y Recursos` — Implementación completa del módulo de prescripción y gestión de plazas:

- **3 migraciones ejecutadas**: `compromiso_id` en `prescripciones`, `criterio_territorial` en `colecciones_plazas`, tabla `lista_espera_movimientos`.
- **Modelos actualizados**: `Prescripcion` (relación `compromiso()`, scope `enCurso()`), `ColeccionPlazas` (`criterio_territorial`), `HistoriaSocial` (relación `prescripciones()`, método `cerrar()` con guard).
- **Modelo nuevo**: `ListaEsperaMovimiento` — auditoría de reordenación de lista de espera.
- **3 componentes Livewire nuevos**: `PrescribirRecursoModal`, `RecursosPage`, `AsignarPlazaModal`.
- **`CiudadanoPage`** actualizado: banda de prescripciones activas, botón "Prescribir recurso", `cancelarPrescripcion()`.
- **`Sidebar`** actualizado: ítem "Recursos" condicional a `ConfiguracionService::get('tiene_plazas')`.
- **Ruta nueva**: `GET /intervencion/recursos` → `RecursosPage`.
- **`IntervencionServiceProvider`**: registra los 3 nuevos componentes.
- **4 Blade views nuevas/actualizadas**: `prescribir-recurso-modal`, `recursos-page`, `asignar-plaza-modal`, `ciudadano-page`.
- **30 tests nuevos** en `PlazasRecursosTest` — todos en verde (30/30).

Bugs corregidos en el proceso:
- `Prescripcion.profesional_id` referencia `profesionales.id` (no `users.id`); `Apunte.autor_id` referencia `users.id`.
- SQL ambigüedad en `prescripcionesPendientes()` por JOIN con `lista_espera` (calificadas las columnas).
- Vistas Livewire necesitan root element `<div>` incluso cuando `$abierto = false`.
- Componentes Livewire referenciados con punto, no con doble dos puntos.

---

## Tarea anterior completada

`Demo worlds` — Soporte de actividades grupales y salas en el sistema YAML de mundos.

---

## Estado exacto del proyecto

- **Tests módulo Intervencion (PlazasRecursos)**: 30 passed / 0 failed.
- **Tests módulo Centro**: 54 passed / 0 failed.
- **Tests módulo Supervision**: 36 passed (no ejecutados en esta sesión).
- **Fallo pre-existente en Ciudadania**: TF-LW-FIC-11 busca "Ver historia social" (texto renombrado a "Ir a HS"). No relacionado con los cambios recientes.

---

## Siguiente paso concreto recomendado

1. Ejecutar suite completa del módulo Intervencion: `php artisan test Modules/Intervencion/tests/` para verificar que no hay regresiones.
2. Probar en navegador el flujo completo: prescribir → lista espera → asignar plaza.
3. Configurar `tiene_plazas = true` en `ConfiguracionService` para un centro de prueba para que el sidebar muestre el ítem "Recursos".
4. Corregir TF-LW-FIC-11 en `FichaCiudadanoPageTest`: cambiar `assertSee('Ver historia social')` por `assertSee('Ir a HS')`.
5. Suite completa antes del siguiente merge a main.

---

## Contexto para retomar sin fricción

- El flujo de prescripción tiene dos actores: el TSR del CSS prescribe vía `PrescribirRecursoModal` (estado 'asignada' o 'en_lista_espera'); el TS del centro asigna una plaza concreta vía `AsignarPlazaModal` (establece `plaza_id`).
- `PrescribirRecursoModal::confirmar()` usa `Auth::user()->profesional_id` para `Prescripcion.profesional_id` y `Auth::id()` para `Apunte.autor_id`.
- `RecursosPage::destinoIdsDelCentro()` filtra por `profesional.unidad_organizativa_id` → `Centro` → `ColeccionPlazas`.
- Los modales Livewire deben tener un `<div>` raíz siempre (aunque el modal esté cerrado), ya que Livewire 4 requiere root element.
- `op-page` y `op-empty` son las únicas clases `op-*` con CSS propio. Todo lo demás es Bootstrap puro.
- `SalaFactory` requiere `Centro::factory()` — el modelo `Centro` NO tiene `HasFactory`. Usar `Centro::create()` en tests.
