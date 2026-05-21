# SESSION — VIDA 360

_Actualizado: 2026-05-21_

## Tarea completada

Entidad `Servicio` implementada completa (Fase 2 del módulo Centro): 5 migraciones, 3 modelos, 14 tests funcionales — todos pasan.

## Estado actual

- **Módulo Centro — Servicio:** implementado al 100% según §3, §5 y §11.8–11.10 del documento funcional v1.2.
- **Suite completa:** 272 tests pasan ✅ — 0 fallos — 1 incompleto (TF-USU-31).

## Qué se implementó en esta sesión

**Limpieza:**
- Eliminado `Modules/Centro/app/Models/Ciudadano.php` (artefacto temporal). Corregidos
  5 archivos en Centro, Agenda y Documentos para usar `App\Models\Ciudadano` directamente.

**Migraciones** (en `database/migrations/`):
- `servicios`, `servicio_prestacion`, `responsables_servicio`, `profesional_servicio`, `solicitudes_servicio`.

**Modelos** (en `Modules/Centro/app/Models/`):
- `Servicio` — con `Versionable`, `SoftDeletes`, validación UO obligatoria, `nombrarResponsable()`.
- `ResponsableServicio` — sin figura de responsable externo, accesor `cargo_nombre` desde el servicio.
- `SolicitudServicio` — `fecha_resolucion` automática al pasar a `resuelta`.

**Tests** (en `Modules/Centro/tests/Feature/`):
- `ServicioTest`, `ResponsableServicioTest`, `SolicitudServicioTest` — 14 tests, todos pasan.

## Test incompleto restante (1)

| Test | Módulo | Requiere |
|---|---|---|
| TF-USU-31 | Usuarios | Policy/Service que impide a `administrador_usuarios` adscribir usuarios a UO fuera de su ámbito jerárquico |

## Siguiente paso recomendado

**TF-USU-31** — El único test pendiente en toda la suite. Requiere implementar la validación en
`UsuarioUoPolicy` (o un Service dedicado) que compruebe que el usuario que intenta adscribir otro
a una UO tiene autoridad jerárquica sobre esa UO.

Leer `Modules/Usuarios/tests/Feature/UnidadOrganizativaTest.php` para ver el contexto exacto del test antes de actuar.
