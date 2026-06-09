# SESSION — VIDA 360

_Actualizado: 2026-06-09_

## Tarea completada

Navegación UI — conexión de entradas operativas: ítem "Alta de ciudadano/a" en sidebar (user-plus), enlace habilitado en BuscarCiudadanoPage, tests TF-LW-NAV-14 y TF-LW-NAV-15 en verde.

## Tarea anterior

Módulo Ciudadanía: alta de ciudadano completa — `NormalizadorCiudadano`, `MotorMatching` (Jaro-Winkler), `AltaCiudadano` Livewire 4 fases, 19 tests TF-LW-ALT-01..19 en verde.

## Estado actual

### Tests — 0 fallos
- Suite previa: 488 tests (antes de esta sesión), 0 fallos.
- AltaCiudadanoTest: 19 pasan.
- NavegacionTest: 14 pasan, 1 incomplete (TF-LW-NAV-03 pendiente fixture PlanDeIntervencion).

### Módulo Ciudadanía — completo (Entrega 1)
- **Infraestructura**: module.json, CiudadaniaServiceProvider, rutas, autoload composer, provider en bootstrap.
- **Servicios**: NormalizadorCiudadano, MotorMatching, FuenteIdentidadInterface + MockFuenteIdentidad.
- **Modelo**: CiudadanoIdentificador con auto-hash en boot.
- **Livewire**: AltaCiudadano (4 fases) + vista alta-ciudadano.blade.php.
- **Migraciones**: primera_demanda + hashes en ciudadanos; fecha_nacimiento nullable; tabla ciudadano_identificadores.

### UI Intervención — entradas ciudadano conectadas
- **Sidebar**: 5 ítems — Agenda, Mis casos, Alertas y mensajes, Buscar ciudadano/a, **Alta de ciudadano/a** (user-plus → ciudadania.alta).
- **BuscarCiudadanoPage**: botón "Dar de alta" habilitado con wire:navigate → ciudadania.alta.
- **Tests**: TF-LW-NAV-01..15 (14 pasan, 1 incomplete).

### UI Intervención (entregas previas — sin cambios)
- **Entrega 3 — completa**: CiudadanoPage con timeline HS, 7 herramientas, 92 tests en verde.
- **Entrega 2 — completa** (MisCasosPage, BuscarCiudadanoPage, BuzonPage).
- **Entrega 1 — completa** (AgendaPage, Sidebar, layout operativo).
- **Autenticación — completa**.

## Pendientes conocidos

| Componente | Pendiente |
|---|---|
| `ciudadania.ciudadano.ficha` | Ruta stub — pendiente implementar ficha ciudadano en Ciudadanía |
| `ciudadania.ciudadano.nueva-cita` | Ruta stub — pendiente cuando Agenda exponga API simplificada |
| `BuscarCiudadanoPage` | TODO de búsqueda por documento → ahora funcional vía ciudadano_identificadores |
| `CiudadanoPage` | "Ver PISO" → Entrega 4 |
| Herramienta Informes | Stub "en construcción" — integración Documentos pendiente |
| `nunomaduro/larastan` | Abandonado upstream — migrar a `larastan/larastan` |
| TF-LW-NAV-03 | Requiere fixture con PlanDeIntervencion activo |
| Umbrales matching | Calibrar con datos reales (configurables en backoffice) |

## Siguiente paso recomendado

**Ficha ciudadano (Ciudadanía Entrega 2)**: implementar `CiudadanoFichaPage` en el módulo Ciudadanía. La ruta `ciudadania.ciudadano.ficha` ya está definida como stub. Incluye: datos identificativos, historial de documentos (`ciudadano_identificadores`), primera demanda, y enlace a la HistoriaSocial si existe en la UO del profesional.

O alternativamente: **UI Intervención Entrega 4** ("Ver PISO" en CiudadanoPage — plan general de intervención social).

## Contexto relevante para retomar

- `CiudadanoIdentificador.boot()` auto-calcula `valor_hash = SHA-256(strtolower(valor))` en creating/updating.
- `confirmarAlta()` usa `withoutGlobalScope(AmbitoUoScope::class)` porque el ciudadano recién creado no tiene historia social.
- VVG: `consultarPadron()` retorna inmediatamente sin invocar `FuenteIdentidadInterface`. Condición evaluada ANTES de cualquier llamada HTTP.
- `ciudadania.buscar` y `intervencion.buscar.index` apuntan al mismo componente `BuscarCiudadanoPage` con distintos middleware.
- `NormalizadorCiudadano::telefono()` añade prefijo +34 solo si el número empieza por 6/7/8/9 sin prefijo internacional.
- El sidebar usa `wire:poll.300s` — el nuevo ítem Alta de ciudadano usa `request()->routeIs('ciudadania.alta')` (sin wildcard, no hay subrutas).
