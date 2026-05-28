# SESSION — VIDA 360

_Actualizado: 2026-05-28_

## Tarea completada

Bugfix: Repeater de secciones en PlantillaInforme falla al editar. Causa: `mutateFormDataBeforeFill` residual del antiguo Textarea JSON serializaba el array a string antes de pasárselo al Repeater. Eliminados `mutateFormDataBeforeFill` y `mutateFormDataBeforeSave` en `EditPlantillaInforme`.
27 Resources actualizados (solo `$navigationGroup`, `$navigationSort`, `$navigationLabel`).
`$navigationParentItem` eliminado de HorarioCentro y PerfilHorario para que sean items directos.

## Estado actual

- **Navegación Filament — completa:**
  - 6 grupos: Organización, Centros y Servicios, Catálogos, Informes y Plantillas, Usuarios y Profesionales, Sistema.
  - `ServicioResource` y `HistorialRolResource` no existen aún; se añadirán cuando se implementen sus módulos.
  - `RolResource` permanece en «Organización» sin cambios (no estaba en la tabla de cambios).
  - 376 tests pasan; 9 fallos pre-existentes en `PrestacionFilamentResourceTest` (problema de setup, no de código de producción).

- **Módulo Documentos — merge tags en plantillas — completo** (sesión anterior).
- **Módulo Escalas fase 1 + UX builder — completo** (sesión anterior).
- **Suite completa:** ~376 tests pasan.

## Tests incompletos actuales

| Test | Clase | Motivo |
|---|---|---|
| 3.5, 3.6, 3.8 | KAnonimatoTest | Requieren modelo Extraccion + job asíncrono |
| 6.6 | PerfilesTest | Requiere modelo Extraccion + relación con PerfilAnonimizacion |
| TF-USU-31 | UnidadOrganizativaTest | Policy jerárquica de adscripción de usuarios a UO |

## Siguiente paso recomendado

**Módulo Escalas fase 2** — componente Livewire de aplicación del pase desde la Historia Social:
selección de instrumento activo, presentación sección a sección, cierre con cálculo de scores,
y visualización del historial cronológico de pases por escala.
Ver `BACKLOG.md` para detalles y la nota sobre el punto de entrada desde la Historia Social.

Alternativa si se prefiere cerrar deuda técnica: **TF-USU-31** — validación jerárquica en
`UsuarioUoPolicy` (un profesional solo puede adscribirse a una UO descendiente de la del
usuario que opera).

## Contexto relevante para retomar

- `EscalaSeeder` NO está incluido en `DatabaseSeeder` aún.
- `TipoEscalaResource` usa grupo «Informes y Plantillas» (mayúscula en P tras esta sesión).
- Los 9 fallos de `PrestacionFilamentResourceTest`: "Invalid Livewire snapshot structure" — problema pre-existente de setup de tests.
- Grupos de navegación actuales: Organización, Centros y Servicios, Catálogos, Informes y Plantillas, Usuarios y Profesionales, Sistema.
