# actividades-salas-implementacion.md

Implementación de los cambios del módulo Centro acordados en sesión de diseño:

1. Añadir `slug` y `activo` al modelo `TipoActividad`
2. Crear la entidad `Sala` vinculada a `Centro`
3. Añadir `sala_id` a `SesionActividad`
4. Actualizar relaciones, factories, seeders y recurso Filament
5. Tests funcionales nuevos

Leer `docs/modulo-centros.md` íntegramente antes de empezar.

---

## Paso 1 — Migración: añadir `slug` y `activo` a `tipo_actividades`

Crear la migración:

```bash
php artisan make:migration add_slug_activo_to_tipo_actividades_table --path=Modules/Centro/database/migrations
```

Contenido:

```php
public function up(): void
{
    Schema::table('tipo_actividades', function (Blueprint $table) {
        $table->string('slug', 100)->unique()->after('nombre');
        $table->boolean('activo')->default(true)->after('descripcion');
    });
}

public function down(): void
{
    Schema::table('tipo_actividades', function (Blueprint $table) {
        $table->dropUnique(['slug']);
        $table->dropColumn(['slug', 'activo']);
    });
}
```

Ejecutar:

```bash
php artisan migrate
```

---

## Paso 2 — Modelo `TipoActividad`

Actualizar `Modules/Centro/app/Models/TipoActividad.php`:

- Añadir `slug` y `activo` al `$fillable`.
- Añadir scope `scopeActivos` para filtrar en selects del backoffice.
- PHPDoc completo: `@property string $slug`, `@property bool $activo`.

```php
/**
 * Tipo de actividad del catálogo backoffice.
 *
 * Catálogo configurable. El slug permite referencias estables desde código
 * sin depender del id numérico.
 *
 * @property int $id
 * @property string $nombre
 * @property string $slug  Identificador estable único, ej: 'taller', 'grupo-apoyo'
 * @property string|null $descripcion
 * @property bool $activo
 */
class TipoActividad extends Model
{
    protected $fillable = ['nombre', 'slug', 'descripcion', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    /**
     * Filtra solo los tipos activos. Usar en selects de backoffice y formularios.
     *
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    /**
     * Actividades que usan este tipo.
     *
     * @return HasMany<Actividad>
     */
    public function actividades(): HasMany
    {
        return $this->hasMany(Actividad::class);
    }
}
```

---

## Paso 3 — Migración: crear tabla `salas`

```bash
php artisan make:migration create_salas_table --path=Modules/Centro/database/migrations
```

Contenido:

```php
public function up(): void
{
    Schema::create('salas', function (Blueprint $table) {
        $table->id();
        $table->foreignId('centro_id')->constrained('centros')->cascadeOnDelete();
        $table->string('nombre', 100);
        $table->text('descripcion')->nullable();
        $table->integer('capacidad')->nullable();
        $table->boolean('accesible')->default(false);
        $table->boolean('activa')->default(true);
        $table->text('notas')->nullable();
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('salas');
}
```

Ejecutar:

```bash
php artisan migrate
```

---

## Paso 4 — Modelo `Sala`

Crear `Modules/Centro/app/Models/Sala.php`:

```php
<?php

namespace Modules\Centro\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Espacio funcional de un centro (aula, sala de reuniones, despacho...).
 *
 * Entidad distinta de Espacio, que pertenece a la jerarquía de alojamiento
 * (ColeccionPlazas → Espacio → Plaza). Las salas no tienen plazas asignables;
 * se referencian desde SesionActividad como dato informativo de ubicación.
 *
 * @property int $id
 * @property int $centro_id
 * @property string $nombre
 * @property string|null $descripcion
 * @property int|null $capacidad  Personas, no plazas de alojamiento
 * @property bool $accesible
 * @property bool $activa
 * @property string|null $notas
 */
class Sala extends Model
{
    protected $fillable = [
        'centro_id',
        'nombre',
        'descripcion',
        'capacidad',
        'accesible',
        'activa',
        'notas',
    ];

    protected $casts = [
        'accesible' => 'boolean',
        'activa'    => 'boolean',
    ];

    /**
     * Centro al que pertenece esta sala.
     *
     * @return BelongsTo<Centro, self>
     */
    public function centro(): BelongsTo
    {
        return $this->belongsTo(Centro::class);
    }

    /**
     * Sesiones de actividad que se celebran en esta sala.
     *
     * @return HasMany<SesionActividad>
     */
    public function sesiones(): HasMany
    {
        return $this->hasMany(SesionActividad::class);
    }

    /**
     * Filtra solo las salas activas.
     *
     * @param \Illuminate\Database\Eloquent\Builder<self> $query
     * @return \Illuminate\Database\Eloquent\Builder<self>
     */
    public function scopeActivas($query)
    {
        return $query->where('activa', true);
    }
}
```

---

## Paso 5 — Migración: añadir `sala_id` a `sesion_actividades`

```bash
php artisan make:migration add_sala_id_to_sesion_actividades_table --path=Modules/Centro/database/migrations
```

Contenido:

```php
public function up(): void
{
    Schema::table('sesion_actividades', function (Blueprint $table) {
        $table->foreignId('sala_id')
              ->nullable()
              ->after('estado')
              ->constrained('salas')
              ->nullOnDelete();
    });
}

public function down(): void
{
    Schema::table('sesion_actividades', function (Blueprint $table) {
        $table->dropConstrainedForeignId('sala_id');
    });
}
```

Ejecutar:

```bash
php artisan migrate
```

---

## Paso 6 — Actualizar modelo `SesionActividad`

En `Modules/Centro/app/Models/SesionActividad.php`:

- Añadir `sala_id` al `$fillable`.
- Añadir propiedad `@property int|null $sala_id` al PHPDoc.
- Añadir relación `sala()`.

```php
/**
 * Sala donde se celebra la sesión. Nullable: una sesión puede no tener sala asignada
 * (actividad exterior, itinerante u online). La disponibilidad de la sala no se
 * valida en este módulo; corresponde al módulo de Agenda.
 *
 * @return BelongsTo<Sala, self>
 */
public function sala(): BelongsTo
{
    return $this->belongsTo(Sala::class);
}
```

---

## Paso 7 — Actualizar modelo `Centro`

En `Modules/Centro/app/Models/Centro.php`:

- Añadir relación `salas()`.
- Añadir `@property-read \Illuminate\Database\Eloquent\Collection<int, Sala> $salas` al PHPDoc de clase.

```php
/**
 * Salas funcionales del centro (aulas, salas de reuniones, despachos...).
 *
 * @return HasMany<Sala>
 */
public function salas(): HasMany
{
    return $this->hasMany(Sala::class);
}
```

---

## Paso 8 — Factories

**`TipoActividadFactory`** — añadir `slug` y `activo`:

```php
public function definition(): array
{
    return [
        'nombre'      => fake()->words(2, true),
        'slug'        => fake()->unique()->slug(2),
        'descripcion' => fake()->optional()->sentence(),
        'activo'      => true,
    ];
}

/** Estado inactivo para tests de filtrado. */
public function inactivo(): static
{
    return $this->state(['activo' => false]);
}
```

**Crear `SalaFactory`** en `Modules/Centro/database/factories/SalaFactory.php`:

```php
<?php

namespace Modules\Centro\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Centro\Models\Sala;

/**
 * Factory para la entidad Sala.
 */
class SalaFactory extends Factory
{
    protected $model = Sala::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'centro_id'   => \Modules\Centro\Models\Centro::factory(),
            'nombre'      => fake()->randomElement(['Aula A', 'Sala de reuniones', 'Despacho 1', 'Sala polivalente', 'Sala multiusos']),
            'descripcion' => fake()->optional()->sentence(),
            'capacidad'   => fake()->optional()->numberBetween(5, 50),
            'accesible'   => fake()->boolean(70),
            'activa'      => true,
            'notas'       => null,
        ];
    }

    /** Estado sala inactiva. */
    public function inactiva(): static
    {
        return $this->state(['activa' => false]);
    }
}
```

---

## Paso 9 — Seeder de `TipoActividad`

Localizar el seeder existente de tipos de actividad (probablemente `TipoActividadSeeder` o dentro de `CentroSeeder`). Añadir el campo `slug` a cada entrada usando `updateOrCreate` con `slug` como clave estable. Ejemplo de entradas:

```php
$tipos = [
    ['nombre' => 'Taller',           'slug' => 'taller',          'descripcion' => null],
    ['nombre' => 'Charla',           'slug' => 'charla',          'descripcion' => null],
    ['nombre' => 'Seminario',        'slug' => 'seminario',       'descripcion' => null],
    ['nombre' => 'Grupo de apoyo',   'slug' => 'grupo-apoyo',     'descripcion' => null],
    ['nombre' => 'Curso',            'slug' => 'curso',           'descripcion' => null],
    ['nombre' => 'Actividad lúdica', 'slug' => 'actividad-ludica','descripcion' => null],
];

foreach ($tipos as $tipo) {
    TipoActividad::updateOrCreate(
        ['slug' => $tipo['slug']],
        ['nombre' => $tipo['nombre'], 'descripcion' => $tipo['descripcion'], 'activo' => true]
    );
}
```

Si no existe seeder previo, crear `Modules/Centro/database/seeders/TipoActividadSeeder.php` e incluirlo en `DatabaseSeeder`.

---

## Paso 10 — Recurso Filament: `SalaResource`

Crear `app/Filament/Resources/SalaResource.php` siguiendo el patrón de otros recursos del módulo Centro (mismo grupo de navegación: *Centros y Servicios*).

**Listado:** columnas `centro.nombre`, `nombre`, `capacidad`, `accesible`, `activa`. Filtro por `centro_id` y por `activa`.

**Formulario:**

```php
Forms\Components\Select::make('centro_id')
    ->relationship('centro', 'nombre')
    ->searchable()
    ->required(),

Forms\Components\TextInput::make('nombre')
    ->required()
    ->maxLength(100),

Forms\Components\Textarea::make('descripcion')
    ->nullable(),

Forms\Components\TextInput::make('capacidad')
    ->numeric()
    ->nullable()
    ->minValue(1),

Forms\Components\Toggle::make('accesible'),

Forms\Components\Toggle::make('activa')
    ->default(true),

Forms\Components\Textarea::make('notas')
    ->nullable(),
```

---

## Paso 11 — Actualizar `TipoActividadResource` en Filament

Añadir los campos `slug` y `activo` al formulario y al listado:

**Formulario — añadir tras `nombre`:**

```php
Forms\Components\TextInput::make('slug')
    ->required()
    ->unique(ignoreRecord: true)
    ->maxLength(100)
    ->helperText('Identificador estable en minúsculas con guiones. Ej: grupo-apoyo'),

Forms\Components\Toggle::make('activo')
    ->default(true),
```

**Listado — añadir columna:**

```php
Tables\Columns\IconColumn::make('activo')->boolean(),
```

**Filtro:**

```php
Tables\Filters\TernaryFilter::make('activo'),
```

---

## Paso 12 — Actualizar formulario de `SesionActividad` en Filament

En el recurso o panel donde se crean/editan sesiones, añadir el selector de sala:

```php
Forms\Components\Select::make('sala_id')
    ->label('Sala')
    ->options(function (callable $get) {
        // Filtrar salas por el centro de la actividad padre
        $actividadId = $get('actividad_id');
        if (!$actividadId) {
            return [];
        }
        $centroId = \Modules\Centro\Models\Actividad::find($actividadId)?->centro_id;
        if (!$centroId) {
            return [];
        }
        return \Modules\Centro\Models\Sala::where('centro_id', $centroId)
            ->activas()
            ->pluck('nombre', 'id');
    })
    ->nullable()
    ->searchable()
    ->helperText('Opcional. La disponibilidad no se valida automáticamente.'),
```

---

## Paso 13 — Tests funcionales

Añadir al fichero de tests del módulo Centro (`Modules/Centro/tests/Feature/`).
Crear clase `SalaTest` si no existe.

### TF-CEN-SALA-01 — Se puede crear una sala con los campos mínimos

```
Dado un centro existente.
Cuando se crea una Sala con centro_id, nombre = 'Aula A'.
Entonces la sala existe en BD con accesible = false y activa = true.
```

### TF-CEN-SALA-02 — Una sala pertenece a un centro

```
Dado una sala creada con centro_id = $centro->id.
Cuando se accede a $sala->centro.
Entonces devuelve el centro correcto.
```

### TF-CEN-SALA-03 — Un centro puede tener múltiples salas

```
Dado un centro.
Cuando se crean tres salas con ese centro_id.
Entonces $centro->salas()->count() === 3.
```

### TF-CEN-SALA-04 — El scope activas excluye las inactivas

```
Dado un centro con dos salas activas y una inactiva.
Cuando se llama a Sala::activas()->where('centro_id', $centro->id)->get().
Entonces el resultado contiene exactamente 2 salas.
```

### TF-CEN-SALA-05 — Se puede asignar una sala a una sesión

```
Dado una sesión existente y una sala del mismo centro.
Cuando se actualiza $sesion->sala_id = $sala->id.
Entonces $sesion->fresh()->sala->id === $sala->id.
```

### TF-CEN-SALA-06 — sala_id en sesión es nullable

```
Dado una sesión existente con sala_id null.
Cuando se guarda sin modificar sala_id.
Entonces no hay error de validación y $sesion->sala_id === null.
```

### TF-CEN-SALA-07 — Eliminar una sala pone sala_id a null en sus sesiones (nullOnDelete)

```
Dado una sesión con sala_id = $sala->id.
Cuando se elimina la sala ($sala->delete()).
Entonces $sesion->fresh()->sala_id === null.
```

### TF-CEN-TIPO-01 — TipoActividad requiere slug único

```
Dado un TipoActividad con slug = 'taller'.
Cuando se intenta crear otro TipoActividad con slug = 'taller'.
Entonces se lanza excepción de constraint único.
```

### TF-CEN-TIPO-02 — TipoActividad inactivo no aparece en scopeActivos

```
Dado un TipoActividad con activo = false.
Cuando se llama a TipoActividad::activos()->get().
Entonces el resultado no incluye ese tipo.
```

---

## Paso 14 — Ejecutar tests

```bash
php artisan test --filter=SalaTest
php artisan test --filter=TipoActividadTest
# O el path completo del módulo:
php artisan test Modules/Centro/tests/Feature/
```

Verificar que todos los tests nuevos pasan y que los tests previos del módulo siguen en verde.

---

## Paso 15 — Cierre de sesión

Seguir el protocolo estándar de `CLAUDE.md §4`:

1. Añadir entrada a `CHANGELOG.md`.
2. Si quedan pendientes, añadir a `BACKLOG.md` (por ejemplo: validación aforo sala ≥ inscritos, disponibilidad de salas en módulo Agenda).
3. Actualizar `SESSION.md`.
4. `git add -A && git commit -m "feat(centro): Sala, slug/activo en TipoActividad, sala_id en SesionActividad" && git push origin master`
