# SESSION — VIDA 360

_Actualizado: 2026-06-12_

## Tarea completada

Corrección de dos bugs en `MisCasosPage` — ningún enlace navegaba:
- **Bug 1 — clic de fila roto**: `wire:click="$dispatch('navigate', ...)"` despachaba un evento de navegador sin ningún listener. Corregido a `@click="window.location.href='...'"`.
- **Bug 2 — `wire:navigate` bloqueaba `href`**: `wire:navigate` llama `preventDefault()` en el click; si la navegación SPA falla, el `href` nativo también queda bloqueado. Eliminado `wire:navigate` de ambos enlaces; se usan `href` nativos.
- Los 30 tests de NavegacionTest + MisCasosPageTest siguen en verde.

## Tarea anterior

Ficha del ciudadano (`FichaCiudadanoPage`) — Ciudadanía Entrega 2: Capa 1 editable, documentos, banner HS, prestaciones aggregation, 16 tests.

## Estado actual

### Tests — 0 fallos
- Suite previa: 488 tests (antes de esta sesión), 0 fallos.
- AltaCiudadanoTest: 19 pasan.
- NavegacionTest: **24 tests (TF-LW-NAV-01..24)**, 1 incomplete (TF-LW-NAV-03), 23 pasan.
- FichaCiudadanoPageTest: 16 pasan.

### Módulo Ciudadanía — completo (Entregas 1 y 2)
- **Alta** (`AltaCiudadano`): 4 fases, motor de matching Jaro-Winkler, 19 tests.
- **Ficha** (`FichaCiudadanoPage`): Capa 1 editable, documentos con historial, banner HS, prestaciones aggregation, 16 tests.
- **Rutas activas**: `ciudadania.buscar`, `ciudadania.alta`, `ciudadania.ciudadano.ficha`.
- **Ruta pendiente (stub)**: `ciudadania.ciudadano.nueva-cita`.

### UI Intervención — mapa de navegación completo
- **Entrega 3**: CiudadanoPage con timeline HS, 7 herramientas, 92 tests.
- **Entrega 2**: MisCasosPage (columna nombre enlaza a ficha), BuscarCiudadanoPage, BuzonPage.
- **Entrega 1**: AgendaPage (bifurcación por rol), Sidebar (5 ítems), layout operativo.
- **Autenticación**: completa.
- **Navegación (§8)**: completa — todos los enlaces entre pantallas implementados y testeados.

## Pendientes conocidos

| Componente | Pendiente |
|---|---|
| `ciudadania.ciudadano.nueva-cita` | Ruta stub — pendiente cuando Agenda exponga API simplificada |
| `CiudadanoPage` Intervencion | "Ver PISO" → Entrega 4 |
| Herramienta Informes | Stub "en construcción" — integración Documentos pendiente |
| `create_ciudadano_prestaciones_resumen_table` | Migración pendiente de ejecutar en producción |
| `nunomaduro/larastan` | Abandonado upstream — migrar a `larastan/larastan` |
| TF-LW-NAV-03 | Requiere fixture con PlanDeIntervencion activo |
| Citas sin `ciudadano_id` en agenda | Depende del módulo Agenda real — fixture solo tiene datos cuando user tiene `profesional_id` |
| Umbrales matching | Calibrar con datos reales (configurables en backoffice) |
| `UNIQUE(ciudadano_id)` en `historias_sociales` | Garantizar en BD unicidad de historia por ciudadano |
| Vista expandida prestaciones | Pantalla completa desde "Ver todo" — pendiente de diseño |
| Vista historial UC | Modal/pantalla de versiones UC — pendiente de diseño |
| `UnidadConvivencia` module | `ucVigente()` en FichaCiudadanoPage devuelve null hasta que exista |
| `ciudadanos_auditoria` tabla | `actividadReciente()` devuelve colección vacía hasta que exista |
| CI/CD | `php artisan migrate` no está en el pipeline — añadir `php artisan migrate --force` al deploy |

## Siguiente paso recomendado

**UI Intervención Entrega 4** — "Ver PISO" en CiudadanoPage.

O: **CI/CD** — añadir `php artisan migrate --force` al workflow `.github/workflows/ci.yml`.

O: **Módulo UnidadConvivencia** — `ucVigente()` ya está definida como stub en FichaCiudadanoPage.

## Contexto relevante para retomar

- Los tests TF-LW-NAV-16/17 (agenda) usan fechaAncla='2026-06-12' fija + setup Cargo/Profesional/Historia. Deterministas y estables en el tiempo.
- `citasFixture()` en AgendaPage solo incluye `historia_id`/`ciudadano_id` reales cuando `Auth::user()->profesional_id` está establecido. Sin profesional_id, todas las citas tienen estos campos a null.
- `ciudadanoId` (int) en FichaCiudadanoPage, NO `public Ciudadano $ciudadano`. Razón: Livewire rehidrata modelos con global scopes, y `AmbitoUoScope` filtraría ciudadanos sin HistoriaSocial.
- `actividadReciente()` devuelve `collect()` directamente: en PostgreSQL una query fallida (tabla inexistente) aborta la transacción aunque se capture la excepción con try/catch.
- `ciudadano_prestaciones_resumen` es una tabla de agregación: los módulos origen (Centros, Teleasistencia...) deben alimentarla via observers. FichaCiudadanoPage nunca consulta tablas de módulos origen directamente.
- El pipeline CI/CD no corre `php artisan migrate` — hay que ejecutarlo manualmente en producción tras cada deploy con migraciones.
