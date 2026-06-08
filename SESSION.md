# SESSION — VIDA 360

_Actualizado: 2026-06-08_

## Tarea completada

Selector de prestaciones en `CentroResource`: sustituido el `CheckboxList` por un SlideOver con componente Livewire interactivo (`SelectorPrestacionesCentro`). Prestaciones agrupadas por objetivo general (etiqueta de `catalogos_sistema`), búsqueda por texto, panel de seleccionadas y guardado via `sync()` en `centro_prestacion`.

## Tarea anterior

`canViewAny()` de `ProfesionalResource` y `CentroResource` abierto a cualquier usuario autenticado (antes solo adm_sistema / adm_usuarios / supervision). Demo world crea entidades `Centro` y `Profesional` además de UOs y Users.

## Estado actual

### Tests — 0 fallos
- Suite base: 488 tests, 0 fallos (antes de esta sesión).
- Tests Demo: `tests/Feature/Demo/DemoWorldLoaderTest.php` — 7 activos pasan, 5 `markTestIncomplete`.
- PHPStan sobre los ficheros nuevos: 0 errores.

### Sistema de demo — operativo
- `php artisan demo:validate ci_minimo` → valida YAML sin tocar BD
- `php artisan demo:reset --world=ci_minimo` → resetea entorno en transacción
- Página Filament en grupo 'Sistema' → 'Entornos Demo' (visible en local/staging, oculta en producción)
- 5 mundos YAML: `ci_minimo`, `demo_formacion`, `pruebas_permisos`, `pruebas_agenda`, `demo_comercial`

### UI Intervención
- **Entrega 3 — completa**: CiudadanoPage con timeline HS, 7 herramientas, 92 tests en verde.
- **Entrega 2 — completa** (MisCasosPage, BuscarCiudadanoPage, BuzonPage).
- **Entrega 1 — completa** (AgendaPage, Sidebar, layout operativo).
- **Autenticación — completa**.

### Tooling de calidad — operativo
- `composer analyse` → PHPStan nivel 6, baseline 772 errores heredados (no añadir nuevos).
- `composer format` → Pint; `composer format-check` para CI.
- `.github/workflows/quality.yml` → CI ejecuta Pint + PHPStan en cada push/PR.

## Pendientes conocidos

| Componente | Pendiente |
|---|---|
| `SelectorPrestacionesCentro` | Filtro por segmento pendiente — ver BACKLOG |
| `CiudadanoPage` | "Ver PISO" → Entrega 4 |
| `crearDerivacion()` | Tabla `derivaciones` no existe — solo crea Apunte |
| UC | Tabla `unidades_convivencia` no existe — stub visible |
| Herramienta Informes | Stub "en construcción" — integración Documentos pendiente |
| `nunomaduro/larastan` | Abandonado upstream — migrar a `larastan/larastan` en próxima sesión de deps |
| PHPStan baseline | 772 errores heredados — reducir progresivamente, nunca añadir nuevos |
| TF-DEMO-08 a 12 | Tests integración pesados del sistema de demo — ver BACKLOG |

## Siguiente paso recomendado

**UI Intervención — Entrega 4** ("Ver PISO" en CiudadanoPage) o reducción progresiva del baseline de PHPStan.
Si se toca código nuevo: `composer format-check && vendor/bin/phpstan analyse --memory-limit=512M <ficheros>`.

## Contexto relevante para retomar

- `Apunte` usa `plan_id` (NOT NULL) → siempre requiere un plan activo para crear apuntes.
- `withoutGlobalScopes()` en seeders de demo es deliberado: no hay usuario autenticado en contexto de seeder.
- `PlanDeIntervencion` tiene guard de firma en `saving()` pero solo aplica al **actualizar** estado a activo (no al crear).
- La página Filament `DemoWorldsPage` usa `getResetAction($id)` dinámico en la vista, no `getHeaderActions()`.
- Citas excluidas del sistema de demo: requieren slot_id y maquinaria de agenda (ver BACKLOG).
- `SelectorPrestacionesCentro`: el filtro de segmento está en la UI pero no aplica restricción en la query (ver BACKLOG). El agrupamiento usa `CatalogoSistema::opcionesParaSelect('prestacion.objetivo_general')`.
