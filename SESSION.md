# SESSION — VIDA 360

_Actualizado: 2026-05-21_

## Tarea completada

Sistema de geocodificación con modelo canónico de dirección implementado completo:
trait `TieneDireccion`, servicio, mock adaptador, observer, job y 18 tests — todos pasan.

## Estado actual

- **Geocodificación:** implementada al 100% según `docs/geocodificacion.md`.
  Aplicada a `Ciudadano` y `Centro`. Mock activo por defecto. 2 migraciones.
- **Suite completa:** 290 tests pasan ✅ — 0 fallos — 1 incompleto (TF-USU-31).

## Qué se implementó en esta sesión

**Enums:**
- `App\Enums\OrigenDireccion` — profesional, padron, geocodificacion.
- `App\Enums\TipoNumeracion` — numero, sin_numero, km.

**Trait:**
- `App\Traits\TieneDireccion` — casts, `direccionFormateada()`, `scopeSinNormalizar()`.

**Servicio:**
- `GeocodificadorInterface`, `ResultadoGeocodificacion`, `GeocodificadorService`, `MockGeocodificador`.

**Observer y job:**
- `DireccionObserver` — geocodifica en `creating`/`updating` para origen profesional.
- `NormalizarDireccionJob` — reintento asíncrono en cola `low`.

**Provider:**
- `GeocodificacionServiceProvider` — binding + observer en Ciudadano y Centro.
- Registrado en `bootstrap/providers.php` + `app/helpers.php` en autoload.

**Migraciones:**
- `add_direccion_canonica_to_ciudadanos_table` — renombra `domicilio`, elimina lat/lng genéricas, añade 14 campos.
- `add_direccion_canonica_to_centros_table` — añade los mismos 14 campos a centros.

**Modelos actualizados:**
- `App\Models\Ciudadano` y `Modules\Centro\Models\Centro` — `TieneDireccion` + `$fillable` actualizado.

**Tests (18, todos pasan):**
- `DireccionObserverTest` (5), `MockGeocodificadorParserTest` (11).

## Test incompleto restante (1)

| Test | Módulo | Requiere |
|---|---|---|
| TF-USU-31 | Usuarios | Policy/Service que impide a `administrador_usuarios` adscribir usuarios a UO fuera de su ámbito jerárquico |

## Siguiente paso recomendado

**TF-USU-31** — El único test pendiente en toda la suite. Requiere implementar la validación en
`UsuarioUoPolicy` (o un Service dedicado) que compruebe que el usuario que intenta adscribir otro
a una UO tiene autoridad jerárquica sobre esa UO.

Leer `Modules/Usuarios/tests/Feature/UnidadOrganizativaTest.php` para ver el contexto exacto del test antes de actuar.
