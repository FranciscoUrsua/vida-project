# SESSION — VIDA 360

_Actualizado: 2026-06-03_

## Tarea completada

Corrección de los 17 tests fallidos de la suite (Bloque Auth 7 + Bloque Prestaciones Filament 9 + Bloque CiudadanoPage 1). Suite pasa a 488 tests, 0 fallos.

## Estado actual

### Tests — 0 fallos
- Suite: 488 tests, 0 fallos, 6 pendientes (deuda documentada), 1 incompleto (centroActivo pendiente).
- Los tests de Filament (`PrestacionFilamentResourceTest`) requieren `adm_sistema` en el usuario y siembra de roles.
- El binding `historia` en `IntervencionServiceProvider` usa `withoutGlobalScopes()` para que la policy emita 403 en lugar de 404.

### Tooling de calidad — operativo
- `composer analyse` → PHPStan nivel 6, pasa sin errores (baseline de 772 errores heredados).
- `composer format` → Pint formatea; `composer format-check` verifica (para CI).
- `composer rector-dry` → Rector en modo seco para revisar propuestas de refactoring.
- `.github/workflows/quality.yml` → CI ejecuta Pint + PHPStan en cada push/PR.

### UI Intervención
- **Entrega 3 — completa**: CiudadanoPage con timeline HS, 7 herramientas, 92 tests en verde.
- **Entrega 2 — completa** (MisCasosPage, BuscarCiudadanoPage, BuzonPage).
- **Entrega 1 — completa** (AgendaPage, Sidebar, layout operativo).
- **Autenticación — completa**.

## Pendientes conocidos

| Componente | Pendiente |
|---|---|
| `CiudadanoPage` | "Ver PISO" → Entrega 4 |
| `crearDerivacion()` | Tabla `derivaciones` no existe — solo crea Apunte |
| UC | Tabla `unidades_convivencia` no existe — stub visible |
| Herramienta Informes | Stub "en construcción" — integración Documentos pendiente |
| `nunomaduro/larastan` | Abandonado upstream — migrar a `larastan/larastan` en próxima sesión de deps |
| PHPStan baseline | 772 errores heredados — reducir progresivamente, nunca añadir nuevos |

## Siguiente paso recomendado

**UI Intervención — Entrega 4** o reducción progresiva del baseline de PHPStan.
Si se toca código nuevo, ejecutar `composer format-check && composer analyse` antes de commitear.

## Contexto relevante para retomar

- `Apunte` usa `plan_id` (NOT NULL) → siempre requiere un plan activo para crear apuntes.
- `withoutGlobalScopes()` en `apuntesHS` y `pisoActivo` es deliberado: la policy ya verificó acceso.
- Los 9 fallos de `PrestacionFilamentResourceTest` son pre-existentes (no relacionados con este tooling).
