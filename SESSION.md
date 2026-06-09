# SESSION — VIDA 360

_Actualizado: 2026-06-09_

## Tarea completada

Módulo Ciudadanía: alta de ciudadano completa — `NormalizadorCiudadano`, `MotorMatching` (Jaro-Winkler), `AltaCiudadano` Livewire 4 fases, 19 tests TF-LW-ALT-01..19 en verde.

## Tarea anterior

Navegación UI Intervención: enlaces ciudadano, modal nuevo mensaje, menú usuario en topbar (TF-LW-NAV-01 a TF-LW-NAV-13).

## Estado actual

### Tests — 0 fallos
- Suite previa: 488 tests (antes de esta sesión), 0 fallos.
- AltaCiudadanoTest: 19 pasan.
- Tests relacionados previos (Intervencion, Centro, etc.) no regresionados.

### Módulo Ciudadanía — nuevo
- **Infraestructura completa**: module.json, CiudadaniaServiceProvider, rutas, autoload composer, provider en bootstrap.
- **Servicios**: NormalizadorCiudadano, MotorMatching, FuenteIdentidadInterface (contrato) + MockFuenteIdentidad (activo por defecto).
- **Modelo**: CiudadanoIdentificador con auto-hash en boot.
- **Livewire**: AltaCiudadano (4 fases) + vista alta-ciudadano.blade.php.
- **Migraciones**: primera_demanda + hashes (telefono_hash, email_hash, fecha_nacimiento_hash) en ciudadanos; fecha_nacimiento nullable; tabla ciudadano_identificadores.
- **Botón alta**: habilitado en buscar-ciudadano-page.blade.php con wire:navigate.

### UI Intervención (estado previo — sin cambios)
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

**Ficha ciudadano (Ciudadanía Entrega 1)**: implementar `CiudadanoPage` en el módulo Ciudadanía (actualmente existe en Intervencion acoplada a HistoriaSocial). La ruta `ciudadania.ciudadano.ficha` ya está definida como stub.

O alternativamente: **UI Intervención Entrega 4** ("Ver PISO" en CiudadanoPage — plan general de intervención social).

## Contexto relevante para retomar

- `CiudadanoIdentificador.boot()` auto-calcula `valor_hash = SHA-256(strtolower(valor))` en creating/updating.
- `confirmarAlta()` usa `withoutGlobalScope(AmbitoUoScope::class)` porque el ciudadano recién creado no tiene historia social (AmbitoUoScope lo filtraría).
- VVG: `consultarPadron()` retorna inmediatamente sin invocar `FuenteIdentidadInterface`. Condición evaluada ANTES de cualquier llamada HTTP.
- `ciudadania.buscar` y `intervencion.buscar.index` apuntan al mismo componente `BuscarCiudadanoPage` con distintos middleware (ciudadania: todos los roles operativos; intervencion: solo rol intervencion).
- `NormalizadorCiudadano::telefono()` añade prefijo +34 solo si el número empieza por 6/7/8/9 sin prefijo.
