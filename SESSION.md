# SESSION — VIDA 360

_Actualizado: 2026-06-01_

## Tarea completada

Implementación de la Entrega 1 del interfaz operativo de Intervención: layout base con
sidebar persistente y pantalla de Agenda (vistas día, semana, mes). 14/14 tests nuevos
en verde, 52 tests del módulo Intervención pasan, sin regresiones en la suite completa.

## Estado actual

- **UI Intervención Entrega 1 — completa:**
  - Middleware `role:intervencion` registrado en `bootstrap/app.php`.
  - Rutas protegidas bajo `auth + role:intervencion`: `/intervencion` y `/intervencion/agenda`.
  - Layout `resources/views/layouts/operativo.blade.php` con sidebar de 196px.
  - Livewire `Sidebar` con badges de notificaciones y casos activos (poll 300s).
  - Livewire `AgendaPage` con vistas día/semana/mes, navegación de fechas y 4 KPIs.
  - Fixture de citas determinista para desarrollo hasta conectar con módulo Agenda.
  - `IntervencionSidebarDataService` para contadores del sidebar.
  - Tests TF-LW-AGE-01 a TF-LW-AGE-14 pasando.

- **Autenticación — completa** (estado anterior, sin cambios):
  - Rutas: `/login`, `/logout`, `/bienvenida`, `/`.
  - `LoginController`, `OnboardingController`, middleware `PrimerAcceso`.
  - Componente `<x-avatar>`.

- **Suite completa:** 416 tests. 9 fallos pre-existentes en `PrestacionFilamentResourceTest`
  (problema conocido de setup de tests de Filament).

## Tests incompletos actuales

| Test | Clase | Motivo |
|---|---|---|
| TF-AUTH-04 | AutenticacionTest | Skipped: email case-sensitive por diseño |
| TF-AUTH-20 | AutenticacionTest | Incomplete: requiere `Profesional::centroActivo()` (módulo Centro) |
| 3.5, 3.6, 3.8 | KAnonimatoTest | Requieren modelo Extraccion + job asíncrono |
| 6.6 | PerfilesTest | Requiere modelo Extraccion + relación con PerfilAnonimizacion |
| TF-USU-31 | UnidadOrganizativaTest | Policy jerárquica de adscripción de usuarios a UO |

## KPIs pendientes de conectar en AgendaPage

Los 4 KPIs de la pantalla Agenda devuelven 0 y tienen comentario `// TODO:`:
- **Alertas sin reconocer**: conectar con módulo Mensajes
- **Seguimientos vencidos**: conectar con planes activos del módulo Intervención
- **Citas**: conectar con módulo Agenda (tabla `citas`, `profesional_id = Auth::id()`)
- **Mensajes sin leer**: conectar con módulo Mensajes

## Siguiente paso recomendado

**UI Intervención — Entrega 2**: pantallas de Mis casos (lista de ciudadanos asignados
con filtros y badge de seguimientos vencidos) y Alertas/mensajes (bandeja unificada).
Instrucciones en `docs/instrucciones-cli/ui-intervencion-entrega2.md`.

Alternativa: conectar los KPIs de AgendaPage con los módulos reales (Agenda, Mensajes).

## Contexto relevante para retomar

- El sidebar muestra `centroActivo()` del profesional; este método no existe aún en
  `Profesional`. En la vista Blade se usa `?->centroActivo()` — null-safe, no rompe.
- Los componentes Livewire del módulo se registran en `IntervencionServiceProvider::boot()`.
- El layout `operativo.blade.php` usa `{{ $slot }}` (Livewire v4) — no `@yield`.
- La fixture de citas es determinista: `crc32($fecha) % 3` citas por día; útil para tests.
- Los 9 fallos de `PrestacionFilamentResourceTest`: "Invalid Livewire snapshot structure" — pre-existente.
