# Instrucciones CLI — `Ficha` con `schema_snapshot` y versionado

> Fichero: `docs/instrucciones-cli/ficha-schema-snapshot.md`
> Módulo afectado: `Modules/Intervencion`
> Tests: `TF-INT-I01` a `TF-INT-I12` en `FichaVersionadoTest.php`
> Prerrequisito: instrucciones `tipo-ficha-implementacion.md` ya ejecutadas.

---

## Contexto y decisión de diseño

Las fichas de valoración se repiten a lo largo del tiempo: una valoración económica en
2024, otra en 2026 tras una revisión del Plan de Intervención. Entre medias, el
`TipoFicha` puede haber cambiado: campos añadidos, campos retirados, etiquetas
modificadas.

Sin `schema_snapshot`, la ficha de 2024 dependería del schema *actual* del tipo para ser
interpretada, lo que la haría incoherente o irreconstruible. Con `schema_snapshot`, cada
`Ficha` es **autocontenida**: lleva consigo la definición exacta de sus campos en el
momento de su creación.

Esto implica dos cambios respecto al diseño anterior:

1. La tabla `fichas` añade el campo `schema_snapshot` (jsonb).
2. La restricción «no se puede eliminar un campo de un `TipoFicha` con fichas asociadas»
   se invierte: **eliminar** campos pasa a estar permitido (la ficha antigua conserva su
   snapshot); **cambiar el tipo** de un campo sigue prohibido.

Leer `docs/modulo-intervencion.md`, secciones 4.5, 4.6 y 4.7, antes de implementar.

---

## Paso 0 — Inspección previa (obligatoria)

Leer los siguientes ficheros antes de tocar nada:

- `Modules/Intervencion/database/migrations/*_create_fichas_table.php` — confirmar
  columnas existentes en `fichas` (especialmente si ya existe `historia_id` como FK
  directa o solo como `valoracion_id`).
- `Modules/Intervencion/app/Models/Ficha.php` — estado actual del modelo.
- `Modules/Intervencion/tests/Feature/TipoFichaTest.php` — localizar el test H08
  («eliminar campo con datos → exc») que debe invertirse.

Documentar en CHANGELOG cualquier divergencia significativa entre lo encontrado y lo
descrito aquí.

---

## Paso 1 — Migración: añadir `schema_snapshot` a `fichas`

Crear una nueva migración (no modificar la original):

```php
// Nombre sugerido: add_schema_snapshot_to_fichas_table

Schema::table('fichas', function (Blueprint $table): void {
    $table->jsonb('schema_snapshot')->nullable()->after('tipo_ficha_id');
    // nullable para no romper fichas existentes en desarrollo;
    // en producción se ejecutará un backfill (ver Paso 5).
});
```

Si la tabla `fichas` aún no existe (el módulo todavía no se ha migrado en el entorno),
añadir la columna directamente a la migración original en lugar de crear una nueva.
Verificar con `php artisan migrate:status` antes de decidir.

---

## Paso 2 — Modelo `Ficha`: trait `Versionable`, `schema_snapshot` y método de pre-relleno

### 2.1 Añadir `Versionable`

```php
use App\Traits\Versionable;

class Ficha extends Model
{
    use Versionable;
    // ...
}
```

Verificar que el trait `Versionable` existe en `app/Traits/Versionable.php` y que
implementa el listener de `updating`. Si no existe, seguir el patrón documentado en
`docs/modulo-usuarios-permisos.md` (sección de versionado polimórfico).

### 2.2 Cast y `$fillable`

```php
protected $casts = [
    'datos'           => 'array',
    'schema_snapshot' => 'array',
    'completada'      => 'boolean',
];
```

Añadir `schema_snapshot` a `$fillable`.

### 2.3 Scope `historialPara`

```php
/**
 * Fichas de un tipo concreto para una historia, ordenadas de más reciente a más antigua.
 *
 * @param \Illuminate\Database\Eloquent\Builder $query
 * @param int $historiaId
 * @param int $tipoFichaId
 * @return \Illuminate\Database\Eloquent\Builder
 */
public function scopeHistorialPara(
    Builder $query,
    int $historiaId,
    int $tipoFichaId
): Builder {
    return $query
        ->where('historia_id', $historiaId)
        ->where('tipo_ficha_id', $tipoFichaId)
        ->orderByDesc('created_at');
}
```

### 2.4 Método estático `prerellenarDesde`

```php
/**
 * Genera el array de datos pre-rellenado para una nueva valoración, basándose en
 * los datos de una ficha anterior y el schema actual del TipoFicha.
 *
 * Reglas:
 * - Campos presentes en el schema actual Y en los datos de la ficha anterior → se copian.
 * - Campos en el schema actual pero no en los datos anteriores → valor null (campo nuevo).
 * - Campos en los datos anteriores pero no en el schema actual → se descartan (campo retirado).
 *
 * @param self $fichaAnterior Ficha de referencia para el pre-relleno.
 * @param \Modules\Intervencion\Models\TipoFicha $tipoFicha TipoFicha con el schema actual.
 * @return array<string, mixed> Array [campo_id => valor] listo para una nueva Ficha.
 */
public static function prerellenarDesde(self $fichaAnterior, TipoFicha $tipoFicha): array
{
    $camposActuales = collect($tipoFicha->schema['campos'] ?? [])->pluck('id')->all();
    $datosAnteriores = $fichaAnterior->datos ?? [];

    $resultado = [];

    foreach ($camposActuales as $campoId) {
        $resultado[$campoId] = $datosAnteriores[$campoId] ?? null;
    }

    return $resultado;
}
```

---

## Paso 3 — Actualizar la validación de `TipoFicha`: invertir la restricción de eliminación

En `Modules/Intervencion/app/Models/TipoFicha.php`, dentro de `validarSchema()`,
localizar el bloque que impide eliminar campos cuando hay fichas asociadas y sustituirlo:

**Eliminar** este bloque (o el equivalente que exista):
```php
// ❌ ELIMINAR — ya no es válido con schema_snapshot
if ($campoActual === null) {
    throw ValidationException::withMessages([
        'schema' => "No se puede eliminar el campo '{$id}': ya existen fichas cumplimentadas.",
    ]);
}
```

**Mantener** únicamente la restricción de cambio de tipo:
```php
// ✅ MANTENER — cambiar tipo sigue siendo destructivo
if ($campoActual['tipo'] !== $tipo) {
    throw ValidationException::withMessages([
        'schema' => "No se puede cambiar el tipo del campo '{$id}': ya existen fichas "
            . "cumplimentadas. Los datos existentes ({$tipo}) serían ininterpretables "
            . "como {$campoActual['tipo']}.",
    ]);
}
```

### Actualizar el test H08

En `Modules/Intervencion/tests/Feature/TipoFichaTest.php`, el test `TF-INT-H08`
verificaba que eliminar un campo con fichas asociadas lanzaba excepción. Ese
comportamiento ya no es correcto. Actualizar el test para reflejar el nuevo
comportamiento: eliminar un campo con fichas asociadas **debe guardarse sin errores**.

```php
#[Test]
public function h08_eliminar_campo_de_ficha_con_datos_asociados_esta_permitido(): void
{
    // Dado: TipoFicha con campos A y B, y una Ficha asociada
    $tipoFicha = TipoFicha::factory()->create([
        'schema' => ['campos' => [
            ['id' => 'campo_a', 'tipo' => 'texto', 'etiqueta' => 'Campo A', 'obligatorio' => false, 'orden' => 1],
            ['id' => 'campo_b', 'tipo' => 'texto', 'etiqueta' => 'Campo B', 'obligatorio' => false, 'orden' => 2],
        ]],
    ]);
    Ficha::factory()->create(['tipo_ficha_id' => $tipoFicha->id]);

    // Cuando: se elimina el campo B del schema
    $tipoFicha->schema = ['campos' => [
        ['id' => 'campo_a', 'tipo' => 'texto', 'etiqueta' => 'Campo A', 'obligatorio' => false, 'orden' => 1],
    ]];
    $tipoFicha->save();  // No debe lanzar excepción

    // Entonces: el TipoFicha se actualiza correctamente
    $this->assertCount(1, $tipoFicha->fresh()->schema['campos']);
}
```

---

## Paso 4 — Actualizar `RegistrarValoracionPage`: poblar `schema_snapshot` al guardar

En `Modules/Intervencion/app/Http/Livewire/RegistrarValoracionPage.php`, dentro del
método `guardar()`, añadir `schema_snapshot` al `updateOrCreate`:

```php
\Modules\Intervencion\Models\Ficha::updateOrCreate(
    [
        'historia_id'   => $this->historiaId,
        'tipo_ficha_id' => $this->tipoFichaId,
        'completada'    => false,
    ],
    [
        'schema_snapshot' => $ficha->schema,   // ← AÑADIR esta línea
        'datos'           => $this->datos,
        'notas'           => $this->notas,
        'completada'      => false,
        'profesional_id'  => auth()->id(),
    ]
);
```

**Atención:** el `updateOrCreate` actualiza `schema_snapshot` también en el update. Eso
es correcto para correcciones (mismo acto profesional, mismo schema vigente). Para una
nueva valoración se crea una fila nueva, así que el snapshot también será el del momento
de creación. El comportamiento es coherente en ambos casos.

---

## Paso 5 — Backfill para fichas existentes en desarrollo

Si en el entorno de desarrollo ya existen filas en `fichas` con `schema_snapshot = null`
(creadas antes de esta migración), ejecutar el siguiente tinker one-liner para poblarlas:

```php
// php artisan tinker
\Modules\Intervencion\Models\Ficha::whereNull('schema_snapshot')
    ->with('tipoFicha')
    ->get()
    ->each(function ($ficha) {
        $ficha->updateQuietly(['schema_snapshot' => $ficha->tipoFicha?->schema ?? []]);
    });
```

`updateQuietly` evita disparar el evento `updating` y por tanto no crea una versión
`Versionable` para este backfill administrativo.

En producción, si llegara a haber datos reales, este mismo one-liner se ejecutaría antes
de desplegar la migración (o en el mismo deploy como un comando `php artisan app:backfill-schema-snapshot`).

---

## Paso 6 — Crear `FichaVersionadoTest.php` con tests TF-INT-I01 a I12

Crear el fichero `Modules/Intervencion/tests/Feature/FichaVersionadoTest.php`.

Los tests están descritos en `docs/modulo-intervencion.md`, sección «Tests funcionales —
Grupo I». Reproducirlos aquí como referencia de implementación.

### Fixtures compartidas

```php
protected TipoFicha $tipoFicha;
protected HistoriaSocial $historia;

protected function setUp(): void
{
    parent::setUp();

    $this->tipoFicha = TipoFicha::factory()->create([
        'schema' => ['campos' => [
            ['id' => 'campo_a', 'tipo' => 'numero', 'etiqueta' => 'Campo A',
             'obligatorio' => true, 'orden' => 1],
            ['id' => 'campo_b', 'tipo' => 'texto', 'etiqueta' => 'Campo B',
             'obligatorio' => false, 'orden' => 2],
        ]],
        'activo' => true,
    ]);

    // HistoriaSocial con ciudadano_id ficticio (sin FK real a ciudadanos en tests)
    $this->historia = HistoriaSocial::factory()->create();
}
```

### Implementación de cada test

Seguir el patrón Dado/Cuando/Entonces de la documentación. A continuación, los más
complejos con orientación de implementación:

**I03 (versión Versionable):** crear una `Ficha`, actualizar `datos`, luego verificar
`Versiones::where('versionable_type', Ficha::class)->where('versionable_id', $ficha->id)->exists()`.
El nombre exacto del modelo en `versionable_type` depende de cómo esté implementado el
trait — verificar con el trait existente antes de escribir la aserción.

**I07-I09 (pre-relleno):** llamar a `Ficha::prerellenarDesde($fichaAnterior, $tipoFicha)`.
No implica persistencia; es un método puro que devuelve un array.

**I11 (eliminar campo permitido):** este test verifica la inversión de la restricción
anterior. Si el entorno tiene el código antiguo (restricción activa), este test fallará
hasta que se aplique el Paso 3. Es el test que confirma que el Paso 3 se ejecutó correctamente.

---

## Verificación final

```bash
# Tests nuevos
php artisan test --filter=FichaVersionadoTest

# Tests existentes que deben seguir pasando
php artisan test --filter=TipoFichaTest
```

Todos los tests de `TipoFichaTest` deben pasar, incluido el H08 actualizado.
Los 12 tests de `FichaVersionadoTest` deben pasar todos.

Actualizar `CHANGELOG.md`, `SESSION.md` y hacer commit:

```
feat(intervencion): Ficha — schema_snapshot, Versionable y pre-relleno de nueva valoración
```

Registrar en `CLAUDE.md` sección 6:

```
| `ficha-schema-snapshot.md` | Ficha con schema_snapshot + Versionable + pre-relleno: migración, modelo, inversión restricción TipoFicha, 12 tests (TF-INT-I01 a I12) |
```
