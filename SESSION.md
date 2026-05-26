# SESSION — VIDA 360

_Actualizado: 2026-05-26_

## Tarea completada

Módulo Escalas fase 1: modelos, migraciones, seeder (Barthel + Pfeiffer + Lawton-Brody),
`TipoEscalaResource` en Filament y 18 tests funcionales pasando (TF-ESC-A01…C04).

## Estado actual

- **Módulo Escalas fase 1 — completo:**
  - Tablas `tipo_escalas` y `pases_escala` migradas en dev y test.
  - `TipoEscala` con validación de schema/rangos, inmutabilidad de código e ítems con pases.
  - `PaseEscala` con cálculo de scores, asignación de interpretación y cierre (`completar()`).
  - `EscalaSeeder` idempotente con los 3 instrumentos de libre uso.
  - `TipoEscalaResource` en Filament (grupo Catálogos), formulario en 3 pestañas.
  - Relación `pasesEscala()` añadida a `HistoriaSocial`.

- **Acceso supervision al backoffice — completo** (sesión anterior).
- **Seguridad en profundidad para datos de ciudadanos — completa** (sesión anterior).
- **Suite completa:** ~393 tests pasan (18 nuevos de Escalas + los anteriores).

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

- `EscalaSeeder` NO está incluido en `DatabaseSeeder` aún. Ejecutar manualmente o añadirlo
  cuando se decida el orden de seeders del módulo Escalas respecto a Intervencion.
- `TipoEscalaResource` usa grupo de navegación 'Catálogos' (el grupo 'Configuración' de las
  instrucciones no existe en el proyecto). Si se crea ese grupo en el futuro, moverlo.
- Los 9 fallos de `PrestacionFilamentResourceTest`: "Invalid Livewire snapshot structure" —
  problema pre-existente de setup de tests, no del código de producción.
- La corrección del FK `Modules\Centro\Models\Prestacion` → `Modules\Prestaciones\Models\Prestacion`
  en `Centro.php` se aplicó al inicio de esta sesión (commit `1027dbc`).
