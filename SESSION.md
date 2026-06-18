# SESSION — VIDA 360

_Actualizado: 2026-06-18_

## Tarea completada

Gestión de relaciones en FichaCiudadanoPage (crear, cerrar, ver historial).
Implementación completa del ciclo de relaciones entre ciudadanos.

## Estado actual

### Cambios aplicados en esta sesión

**Módulo Ciudadania — nuevos ficheros**
- `Modules/Ciudadania/database/migrations/2026_06_16_000004_create_ciudadano_relaciones_table.php`
  — tabla `ciudadano_relaciones` con FKs a ciudadanos, tipo_relacion (slug),
  fecha_inicio, fecha_fin nullable, observaciones.
- `Modules/Ciudadania/app/Models/CiudadanoRelacion.php`
  — modelo con reciprocidad automática en hooks booted(): al crear genera el
  registro inverso; al cerrar (fecha_fin) sincroniza el recíproco; al eliminar
  propaga la eliminación. Flag estático `$sincronizandoReciproca` evita recursión.
  Relaciones: ciudadano(), ciudadanoRelacionado(), tipoRelacion(). Scope activas().

**Módulo Intervencion — CiudadanoPage**
- 3 computeds nuevos: `representante()`, `relacionesAgrupadas()`, `relacionesMiembrosUc()`
- 2 propiedades nuevas: `modalRelacionesAbierto`, `modalRepresentanteAbierto`
- 4 métodos nuevos: `abrirModalRelaciones()`, `cerrarModalRelaciones()`,
  `abrirModalRepresentante()`, `cerrarModalRepresentante()`
- Blade: línea de representante antes del widget UC, etiqueta de tipo de relación
  en cada miembro de la UC, botón "Ver todas las relaciones" al pie del widget,
  modal de datos de contacto del representante, modal agrupado de todas las relaciones.
- CSS: `.hs-representante`, `.uc-widget-miembro__relacion`, `.uc-widget__ver-relaciones`,
  `.rel-modal__*`, `.uc-modal--sm` añadidos en `app-operativo.css`.
- `Modules/Intervencion/tests/Feature/Livewire/RelacionesUiTest.php`
  — 12 tests TF-LW-REL-01..12, todos en verde.

**Catálogos Filament**
- `TipoRelacionProfesionalResource`: renombrado a "Relaciones profesionales"
  (navigationLabel, modelLabel, icon, sort 10) para evitar colisión con el nuevo
  `TipoRelacionResource` (tipos de relación entre ciudadanos, sort 9).

## Siguiente paso recomendado

1. **Fichas sociales / Formulario de valoración** — bloquea el PISO completo.
2. **PISO/plan detail page** — Entrega 4.
3. **Genograma** — bloqueado hasta definir tipo_dinamica, fecha_fallecimiento
   y decisión sobre nodos ligeros (ver BACKLOG).

## Contexto técnico para retomar

### CiudadanoRelacion — reciprocidad automática
- `booted()` created: crea el registro inverso con el tipo recíproco del catálogo.
- Para tipos simétricos (cónyuge, hermano): `tipoRecíproco()` devuelve `$this`,
  comprueba existencia antes de crear para no duplicar.
- Para tipos asimétricos (padre → hijo): crea `hijo → padre` automáticamente.
- `$sincronizandoReciproca = true` durante la operación para evitar recursión infinita.

### Computeds de relaciones en CiudadanoPage
- `representante()`: busca por `implicacion_funcional = 'representante'` (nunca por slug).
- `relacionesAgrupadas()`: agrupa relaciones activas por tipo, excluye tipos inactivos.
- `relacionesMiembrosUc()`: indexado por `ciudadano_id` → etiqueta del tipo de relación.

### Campos Ciudadano (referencia)
- `nombre`, `apellido1`, `apellido2`: cast `encrypted` — no queryables con LIKE.
- `direccion_texto` (cifrado), `coordenadas_lat`, `coordenadas_lng`.
