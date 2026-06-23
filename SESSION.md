# SESSION — Estado actual del proyecto VIDA 360

**Última actualización:** 2026-06-23

---

## Tarea completada

`Módulo Intervención` — Corrección arquitectónica: Apunte pertenece a la Historia Social, no al Plan:

- **Migración** `2026_06_23_000001_add_historia_id_to_plan_apuntes`: añade `historia_id` NOT NULL FK a `plan_apuntes`, hace `plan_id` nullable, popula `historia_id` desde el plan para registros existentes.
- **`Apunte` model**: docblock y lógica actualizados. `historia_id` es el FK primario. `plan_id` es optional. El Global Scope `ambito_uo` ahora filtra directamente por `historia_id → historias_sociales.unidad_organizativa_id` (sin join extra a `planes_intervencion`). `getCiudadanoId()` resuelve por `historia_id` directamente.
- **`ApunteFactory`**: `historia_id` como campo requerido, `plan_id` nullable por defecto.
- **`CiudadanoPage`**: `apuntesHS()` filtra por `historia_id` directamente. Los 6 métodos de acción (`guardarEntrevista`, `guardarAnotacion`, `crearDerivacion`, `guardarGestion`, `guardarValoracion`, `guardarEscala`) siempre crean el apunte con `historia_id`; `plan_id` se añade si hay plan en curso.
- **`PlanPage`**: el apunte del cierre del plan incluye `historia_id`, `autor_id`, `fecha` y `visibilidad` correctos.
- **`RegistrarValoracionPage`**: `registrarApunte()` siempre crea el apunte; busca plan en curso (borrador/activo/en_revision) para vincularlo opcionalmente.
- **Tests**: `VisibilidadApuntesTest` y `CiudadanoPageTest` actualizados para incluir `historia_id`.
- **`PlanPage::mount()`**: lee `historia` y `uc` del query string cuando no son parámetros de ruta, corrigiendo el 500 al acceder a `/intervencion/plan/crear?historia=X`.
- **Docs**: sección 7.1 de `docs/modulo-intervencion.md` corregida.

---

## Estado exacto del proyecto

- **Apuntes**: se crean siempre que hay una Historia Social abierta, independientemente de si existe un plan. El `plan_id` es un campo opcional de contexto.
- **Plan creation page**: el error 500 `UrlGenerationException` está corregido — `mount()` lee `historia` del query string.
- **Fallos pre-existentes en PlanPageTest**: 4 tests (relacionados con modal de motivo en plan activo) siguen siendo deuda técnica pendiente.

---

## Siguiente paso concreto recomendado

1. Investigar y corregir los 4 tests pre-existentes de `PlanPageTest` (modal de motivo).
2. Propuesta automática de objetivos al añadir ficha al diagnóstico.
3. Verificar que `dompdf` está instalado: `composer show barryvdh/laravel-dompdf`.

---

## Contexto para retomar sin fricción

- `Apunte` usa la tabla `plan_apuntes` (nombre histórico, no renombrada para no romper migraciones anteriores).
- El Global Scope `ambito_uo` en `Apunte` ahora hace un único nivel de join (`historias_sociales`), no dos.
- `PlanPage::mount()` lee `request()->query('historia')` porque Livewire 3 no inyecta query string params en `mount()` — solo parámetros de ruta.
- Tests de `CiudadanoPageTest` crean un plan activo en `setUp()` (`$this->piso`) que se vincula opcionalmente a los apuntes via `plan_id`. Los apuntes se consultan por `historia_id`, no por `plan_id`.
