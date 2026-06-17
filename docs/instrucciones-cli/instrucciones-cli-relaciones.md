# Instrucciones para Claude CLI
# Tarea: Relaciones entre ciudadanos + UC solo lectura en FichaCiudadanoPage
# Módulo principal: Modules/Ciudadania

---

## Contexto

Se añade a `FichaCiudadanoPage` (módulo Ciudadanía) la gestión completa de relaciones
entre ciudadanos, y se convierte el panel de unidad de convivencia de stub a solo lectura.

La decisión de ubicar las relaciones en la ficha (no en intervención) está documentada
en `docs/modulo-ciudadania.md` §3.3. El modelo de datos `CiudadanoRelacion` ya está
especificado; revisar si la migración y el modelo existen antes de crearlos.

---

## Paso 1 — Revisión previa del código existente

Antes de escribir código, revisar:

1. `Modules/Ciudadania/app/Models/CiudadanoRelacion.php` — ¿existe? ¿tiene el trait
   `TieneRelacionesReciprocas`?
2. La migración de `ciudadano_relaciones` — ¿existe y está ejecutada?
3. `Modules/Ciudadania/app/Http/Livewire/FichaCiudadanoPage.php` — cómo está implementado
   actualmente `ucVigente()` (es un stub con TODO; ver CHANGELOG 2026-06-10 y 2026-06-11).
4. Cómo lee la UC la pantalla de intervención (`CiudadanoPage.php`) — copiar la lógica de
   lectura para el panel solo lectura de la ficha.
5. El catálogo `tipos_relacion` — ¿existe en `catalogos_sistema` o como tabla propia?

---

## Paso 2 — Modelo y migración (si no existen)

### CiudadanoRelacion

```php
// Modules/Ciudadania/app/Models/CiudadanoRelacion.php
// Tabla: ciudadano_relaciones
// Campos: id, ciudadano_id (FK), ciudadano_relacionado_id (FK),
//         tipo_relacion (string), fecha_inicio (date),
//         fecha_fin (date nullable), observaciones (text nullable), timestamps

// Relaciones:
// - ciudadano(): BelongsTo<Ciudadano>
// - ciudadanoRelacionado(): BelongsTo<Ciudadano>

// Trait TieneRelacionesReciprocas:
// - Al crear: busca el tipo_reciproco en el catálogo y crea el registro inverso
//   en la misma transacción
// - Al actualizar fecha_fin: aplica el mismo fecha_fin al registro recíproco
// - Al eliminar: elimina también el recíproco
// - Las relaciones simétricas (tipo_reciproco == tipo_relacion) siguen la misma lógica

// Scope vigentes(): where fecha_fin is null OR fecha_fin > today
// Scope cerradas(): where fecha_fin <= today
```

Si el trait `TieneRelacionesReciprocas` no existe, crearlo en
`Modules/Ciudadania/app/Traits/TieneRelacionesReciprocas.php`.

El catálogo de tipos de relación vive en `catalogos_sistema` (clave grupo:
`ciudadano.tipo_relacion`). Cada entrada necesita un campo extra `tipo_reciproco` con la
clave del tipo recíproco. Si el catálogo actual no soporta campos extra por entrada,
valorar una tabla propia `tipos_relacion_ciudadano` con columnas `clave`, `etiqueta`,
`tipo_reciproco`. Documentar la decisión tomada.

---

## Paso 3 — Actualizar FichaCiudadanoPage

### Propiedades nuevas (Livewire)

```php
// Propiedades para el modal de relaciones
public bool $modalRelacionAbierto = false;
public ?int $relacionId = null;           // null = nueva, int = editar existente
public string $relacionTipo = '';
public ?int $relacionCiudadanoId = null;
public string $relacionFechaInicio = '';
public string $relacionFechaFin = '';
public string $relacionObservaciones = '';
public string $busquedaCiudadano = '';    // para el buscador inline del modal
```

### Computeds nuevos

```php
// Relaciones vigentes del ciudadano, ordenadas por fecha_inicio desc
public function relaciones(): Collection { ... }

// Relaciones cerradas (historial)
public function relacionesHistorial(): Collection { ... }

// Sugerencias para el buscador del modal (mín. 3 chars, máx. 8 resultados)
// Excluir al propio ciudadano
public function sugerenciasCiudadano(): Collection { ... }

// Catálogo de tipos de relación para el select
public function tiposRelacion(): array { ... }
```

### Métodos nuevos

```php
public function abrirModalNuevaRelacion(): void
public function abrirModalEditarRelacion(int $relacionId): void
public function cerrarModalRelacion(): void
public function guardarRelacion(): void   // crea o actualiza; autorización: ciudadano.editar
public function cerrarRelacion(int $relacionId): void  // fecha_fin = today; autorización: ciudadano.editar
```

### Autorización

Todos los métodos de escritura deben verificar `$this->authorize('ciudadano.editar')` o
equivalente con la Policy existente. Consultar cómo lo hace el método `guardar()` actual
de la ficha para mantener coherencia.

### UC solo lectura

Reemplazar el stub `ucVigente()` por una implementación real que lea de
`unidades_convivencia` / `unidad_convivencia_miembros`. Revisar el código de
`CiudadanoPage.php` para reutilizar la misma lógica de lectura.

El computed debe devolver una colección de ciudadanos convivientes vigentes (miembros con
`fecha_fin` null o futura), enriquecidos con el tipo de relación si existe en
`ciudadano_relaciones`. Si no hay UC activa, devolver colección vacía.

---

## Paso 4 — Vista Blade (ficha-ciudadano-page.blade.php)

### Panel Relaciones

Ubicación: columna principal, después del bloque de documentos de identidad y antes del
bloque de UC.

```
┌─────────────────────────────────────┐
│ Relaciones          [+ Añadir] (*)  │
├─────────────────────────────────────┤
│ Hijo/a · Juan García López    →     │
│ Cónyuge · Ana Martín Ruiz     →     │
│                                     │
│ [Ver historial (2)]                 │
└─────────────────────────────────────┘
(*) Solo visible con ciudadano.editar
```

- Cada fila: tipo de relación (badge o texto muted) + nombre del relacionado (enlace a
  su ficha vía `wire:navigate` a `ciudadania.ciudadano.ficha`)
- Fila clicable para editar solo si el usuario tiene `ciudadano.editar`
- Estado vacío: texto informativo "Sin relaciones registradas"
- Historial colapsado por defecto (Alpine `x-show` / `x-collapse`)

### Modal de relación

Modal Alpine (mismo patrón que otros modales de la ficha, z-index 500):

```
Título: "Nueva relación" / "Editar relación"

[Tipo de relación *]  ← select con catálogo tipos_relacion
[Ciudadano *]         ← buscador: input text + lista desplegable de sugerencias
                         (wire:model.live="busquedaCiudadano", mín 3 chars)
[Fecha inicio *]      ← date
[Fecha fin]           ← date (opcional)
[Observaciones]       ← textarea (opcional)

[Cancelar]  [Guardar]
            [Cerrar relación] ← solo en modo edición, botón secundario/danger
```

### Panel UC — solo lectura

Reemplazar el bloque actual de UC (que tiene TODO y botón "Ver ficha" stub) por:

```
┌─────────────────────────────────────┐
│ Convivientes                        │
├─────────────────────────────────────┤
│ Ana Martín Ruiz · Cónyuge           │
│ Luis García López · Hijo/a          │
└─────────────────────────────────────┘
```

- Sin botones de acción
- Sin enlace "Ver ficha" (la gestión de UC está en la pantalla de intervención)
- Si no hay UC: no renderizar el panel (o mostrar estado vacío sin CTA)
- El tipo de relación se muestra si existe en `ciudadano_relaciones`; si no, solo el nombre

---

## Paso 5 — Tests

Implementar los tests definidos en `tests-relaciones-uc.md` (TF-LW-REL-01 a REL-20,
TF-LW-UC-01 a UC-04) en:

```
Modules/Ciudadania/tests/Feature/Livewire/RelacionesCiudadanoTest.php
```

Convenciones del proyecto:
- Tests de Livewire usan `Livewire::test(FichaCiudadanoPage::class, ['ciudadanoId' => $c->id])`
- El ciudadano se crea con factory; los datos cifrados se asignan directamente
- Autorización: usar `actingAs($user)` con el usuario apropiado
- Nomenclatura: seguir el patrón `TF-LW-REL-NN` en el docblock del test

---

## Paso 6 — Actualizar documentación

Una vez implementado:

1. Añadir entrada en `CHANGELOG.md` con la estructura habitual del proyecto
2. Actualizar `SESSION.md` si hay restricciones técnicas nuevas de Livewire 4
   descubiertas durante la implementación
3. Si se ha creado tabla/modelo nuevos, actualizar la tabla de entidades en
   `docs/documentacion-proyecto.md` §8

---

## Restricciones técnicas a tener en cuenta

- **Livewire 4:** `wire:model` es diferido por defecto. Usar `wire:model.live` para el
  buscador de ciudadano (necesita re-render inmediato al escribir).
- **Ciudadanos cifrados:** la búsqueda por nombre carga un subconjunto y filtra en PHP
  (mismo patrón que `BuscarCiudadanoPage`). Ver CHANGELOG 2026-06-08 para el límite
  de 500 registros y el TODO de índice hash.
- **Alpine + Livewire:** el modal debe usar Alpine para el estado de apertura/cierre
  (mismo patrón que modales existentes en la ficha). Lucide icons requieren
  `Livewire.hook('morphed', ...)` para reinicializarse tras re-renders.
- **AmbitoUoScope:** `FichaCiudadanoPage` ya usa `withoutGlobalScope(AmbitoUoScope::class)`
  para acceder al ciudadano. Las queries sobre `ciudadano_relaciones` no necesitan este
  scope (no tienen AmbitoUo aplicado).
- **Transacciones:** la creación de la relación recíproca debe ocurrir dentro de una
  transacción DB para garantizar atomicidad.
