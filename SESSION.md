# SESSION — VIDA 360

_Actualizado: 2026-06-10_

## Tarea completada

Ficha del ciudadano (`FichaCiudadanoPage`) — Ciudadanía Entrega 2: Capa 1 editable con permisos por rol, historial de documentos, banner de historia social, widget de prestaciones, 16 tests TF-LW-FIC-01..16 en verde.

## Tarea anterior

Navegación UI: ítem "Alta de ciudadano/a" en sidebar, TF-LW-NAV-14/15.

## Estado actual

### Tests — 0 fallos
- Suite previa: 488 tests (antes de esta sesión), 0 fallos.
- AltaCiudadanoTest: 19 pasan.
- NavegacionTest: 14 pasan, 1 incomplete (TF-LW-NAV-03).
- FichaCiudadanoPageTest: 16 pasan.

### Módulo Ciudadanía — completo (Entregas 1 y 2)
- **Alta** (`AltaCiudadano`): 4 fases, motor de matching Jaro-Winkler, 19 tests.
- **Ficha** (`FichaCiudadanoPage`): Capa 1 editable, documentos con historial, banner HS, prestaciones aggregation, 16 tests.
- **Rutas activas**: `ciudadania.buscar`, `ciudadania.alta`, `ciudadania.ciudadano.ficha`.
- **Ruta pendiente (stub)**: `ciudadania.ciudadano.nueva-cita`.
- **Migración aplicada en producción**: `create_ciudadano_prestaciones_resumen_table` (ejecutar `php artisan migrate` en prod).

### UI Intervención (sin cambios)
- **Entrega 3 — completa**: CiudadanoPage con timeline HS, 7 herramientas, 92 tests.
- **Entrega 2 — completa**: MisCasosPage, BuscarCiudadanoPage, BuzonPage.
- **Entrega 1 — completa**: AgendaPage, Sidebar (5 ítems), layout operativo.
- **Autenticación — completa**.

## Pendientes conocidos

| Componente | Pendiente |
|---|---|
| `ciudadania.ciudadano.nueva-cita` | Ruta stub — pendiente cuando Agenda exponga API simplificada |
| `CiudadanoPage` Intervencion | "Ver PISO" → Entrega 4 |
| Herramienta Informes | Stub "en construcción" — integración Documentos pendiente |
| `create_ciudadano_prestaciones_resumen_table` | Migración pendiente de ejecutar en producción |
| `nunomaduro/larastan` | Abandonado upstream — migrar a `larastan/larastan` |
| TF-LW-NAV-03 | Requiere fixture con PlanDeIntervencion activo |
| Umbrales matching | Calibrar con datos reales (configurables en backoffice) |
| `UNIQUE(ciudadano_id)` en `historias_sociales` | Garantizar en BD unicidad de historia por ciudadano |
| Vista expandida prestaciones | Pantalla completa desde "Ver todo" — pendiente de diseño |
| Vista historial UC | Modal/pantalla de versiones UC — pendiente de diseño |
| `UnidadConvivencia` module | `ucVigente()` en FichaCiudadanoPage devuelve null hasta que exista |
| `ciudadanos_auditoria` tabla | `actividadReciente()` devuelve colección vacía hasta que exista |
| CI/CD | `php artisan migrate` no está en el pipeline — añadir `php artisan migrate --force` al deploy |

## Siguiente paso recomendado

**CI/CD — añadir `php artisan migrate --force`** al workflow `.github/workflows/ci.yml` para que las migraciones se ejecuten automáticamente en cada deploy (actualmente hay que correrlas manualmente en producción).

O alternativamente: **UI Intervención Entrega 4** ("Ver PISO" en CiudadanoPage).

O: **Módulo UnidadConvivencia** (UC vigente en ficha ciudadano — `ucVigente()` ya está definida como stub).

## Contexto relevante para retomar

- `ciudadanoId` (int) en el componente, NO `public Ciudadano $ciudadano`. Razón: Livewire rehidrata modelos con global scopes, y `AmbitoUoScope` filtraría ciudadanos sin HistoriaSocial.
- `actividadReciente()` devuelve `collect()` directamente: en PostgreSQL una query fallida (tabla inexistente) aborta la transacción entera aunque se capture la excepción PHP con try/catch.
- `ciudadano_prestaciones_resumen` es una tabla de agregación: los módulos origen (Centros, Teleasistencia...) deben alimentarla via observers. La FichaCiudadanoPage nunca consulta tablas de módulos origen directamente.
- El pipeline CI/CD no corre `php artisan migrate` — hay que ejecutarlo manualmente en producción tras cada deploy que incluya migraciones.
