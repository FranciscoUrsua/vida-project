# SESSION — VIDA 360

_Actualizado: 2026-06-08_

## Tarea completada

Navegación UI Intervención: enlaces ciudadano, modal nuevo mensaje, menú usuario en topbar (TF-LW-NAV-01 a TF-LW-NAV-13).

## Tarea anterior

Rediseño visual UI operativa: tokens design system aplicados a todas las vistas Livewire de Intervención, iconos migrados a Lucide (stroke-width 1.75), CSS operativo extraído a `app-operativo.css` importando `colors_and_type.css`.

## Estado actual

### Tests — 0 fallos
- Suite previa: 488 tests, 0 fallos.
- NavegacionTest: 12 pasan, 1 `markTestIncomplete` (TF-LW-NAV-03 requiere datos de plan activo).
- Tests relacionados (AgendaPage, MisCasosPage, BuscarCiudadanoPage, BuzonPage, CiudadanoPage): 60 pasan, 0 fallos.

### UI Intervención
- **Navegación — completa**: enlaces ciudadano en agenda, tabla casos clicable, búsqueda ciudadano con links reales, modal nuevo mensaje, menú usuario en topbar.
- **Entrega 3 — completa**: CiudadanoPage con timeline HS, 7 herramientas, 92 tests en verde.
- **Entrega 2 — completa** (MisCasosPage, BuscarCiudadanoPage, BuzonPage).
- **Entrega 1 — completa** (AgendaPage, Sidebar, layout operativo).
- **Autenticación — completa**.

### Cambios aplicados
- `Ciudadano`: accessor `nombre_completo` añadido.
- `AgendaPage`: fixture incluye `historia_id` para cada cita; citas con HS → `<a wire:navigate>`, sin HS → `<div>`.
- `MisCasosPage`: propiedad computada `ciudadanosDelPage()` para evitar N+1; columna HS visible; filas clicables.
- `BuzonPage`: modal "Nuevo mensaje" completo con búsqueda de destinatario, asunto, cuerpo y envío.
- `BuscarCiudadanoPage`: `registrarAccesoNivel2()` redirige con `redirectRoute()`; nombre nivel 1 → enlace.
- `operativo.blade.php`: topbar Alpine.js con menú de usuario (nombre, rol, cerrar sesión).
- `sidebar.blade.php`: bloque usuario inferior eliminado (ahora en topbar).
- `app-operativo.css`: clases `.op-topbar` y `.topbar__user*` añadidas.

### Tooling de calidad — operativo
- `composer analyse` → PHPStan nivel 6, baseline 772 errores heredados.
- `composer format` → Pint.
- `.github/workflows/quality.yml` → CI ejecuta Pint + PHPStan.

## Pendientes conocidos

| Componente | Pendiente |
|---|---|
| `CiudadanoPage` | "Ver PISO" → Entrega 4 |
| `crearDerivacion()` | Tabla `derivaciones` no existe — solo crea Apunte |
| UC | Tabla `unidades_convivencia` no existe — stub visible |
| Herramienta Informes | Stub "en construcción" — integración Documentos pendiente |
| `nunomaduro/larastan` | Abandonado upstream — migrar a `larastan/larastan` |
| TF-LW-NAV-03 | Requiere fixture con PlanDeIntervencion activo para mostrar cabeceras de tabla |
| Alta ciudadano | Botón deshabilitado — ver BACKLOG |
| Citas en agenda | historia_id solo se carga si el usuario tiene profesional_id y hay historias en BD |

## Siguiente paso recomendado

**UI Intervención — Entrega 4**: implementar "Ver PISO" en CiudadanoPage (pantalla del plan general de intervención social), o reducción progresiva del baseline de PHPStan (actualmente 772 errores heredados).

## Contexto relevante para retomar

- `ciudadanosDelPage()` en MisCasosPage usa `withoutGlobalScope(AmbitoUoScope::class)` — el control de acceso está en la query de casos.
- El modal de nuevo mensaje en BuzonPage usa `mount()` para abrir con asunto pre-rellenado desde URL (`?asunto=...`).
- `RolParticipante::RemitenteInicial` y `::Participante` son los valores del enum para crear participantes.
- Topbar Alpine.js usa `x-data="{ abierto: false }"` — Livewire 4 incluye Alpine automáticamente, no añadir CDN.
