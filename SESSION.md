# SESSION — VIDA 360

_Actualizado: 2026-05-28_

## Tarea completada

Documentos — Merge tags en editor de plantillas de informe.
`PlantillaInformeResource` convertido a Repeater tipado. `RichEditor` con `mergeTags()` añadido a secciones `texto_libre`. `MergeTagsCatalogo` con 26 variables. `ResolverFuentesInforme::resolverMergeTags()` implementado. `TipoEscala::codigoId()` añadido. 21/21 tests pasando.

## Estado actual

- **Módulo Documentos — merge tags en plantillas — completo:**
  - `MergeTagsCatalogo`: 26 variables (ciudadano, expediente, valoración, plan, profesional, informe).
  - `PlantillaInformeResource`: Repeater tipado con bloques `automatico` / `texto_libre`; `RichEditor` con merge tags en `texto_libre`.
  - `ResolverFuentesInforme::resolverMergeTags()`: sustitución `{{ clave }}` en HTML; variables de Intervención devuelven `'—'` hasta que el módulo esté disponible.
  - `TipoEscala::codigoId()`: lookup código → id con caché de un día.
  - 21/21 tests del módulo Documentos pasan; 18/18 de Escalas pasan.

- **Módulo Escalas fase 1 + UX builder — completo:**
  - `TipoEscalaResource` pestaña «Estructura» usa `Builder` nativo con bloque `seccion`.
  - Transformación `afterStateHydrated` / `dehydrateStateUsing` entre formato modelo y formato Builder.
  - IDs de sección e ítem generados automáticamente en `dehydrateStateUsing`.
  - Inmutabilidad con closure `$record->pases()->exists()` en texto e opciones de ítems.
  - `Placeholder` de aviso cuando existen pases.
  - 18 tests funcionales pasando (TF-ESC-A01…C04).

- **Acceso supervision al backoffice — completo** (sesión anterior).
- **Seguridad en profundidad para datos de ciudadanos — completa** (sesión anterior).
- **Suite completa:** ~393 tests pasan.

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
- `TipoEscalaResource` usa grupo de navegación 'Informes y plantillas'.
- Los 9 fallos de `PrestacionFilamentResourceTest`: "Invalid Livewire snapshot structure" —
  problema pre-existente de setup de tests, no del código de producción.
