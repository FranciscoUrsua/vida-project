# Instrucciones CLI — Catálogo de tipos de relación entre ciudadanos

## Contexto

`ciudadano_relaciones` ya existe con un campo `tipo_relacion` (string) que
la documentación dice "configurable desde backoffice", pero ese backoffice
no existe. Esta tarea lo implementa.

La conversación de diseño ha fijado:

1. Cada tipo de relación tiene un **slug** fijo (contrato interno del código)
   y una **etiqueta** editable por el usuario (lo que ve el TSR).
2. Los tipos con implicaciones funcionales (`representante`, `tutor_legal`,
   `cuidador_principal`) se identifican por un campo `implicacion_funcional`,
   no por el slug. El código referencia siempre `implicacion_funcional`.
3. Cada tipo define su recíproco. Las relaciones simétricas (cónyuge) tienen
   `simetrica = true`. El trait `TieneRelacionesReciprocas` ya gestiona la
   creación automática del registro inverso.
4. Los tipos del seeder tienen `eliminable = false`. El backoffice puede crear
   tipos adicionales con `eliminable = true`.
5. El recurso Filament va en el grupo **Catálogos**.

---

## Paso 1 — Actualizar `docs/modulo-ciudadania.md`

Localiza la sección `### 3.3 Relaciones entre ciudadanos` y reemplaza el
párrafo que empieza por "El catálogo de `tipos_relacion` es configurable
desde el backoffice..." hasta el final de esa sección por:

```markdown
El catálogo de tipos de relación se gestiona en la tabla `tipos_relacion`
(ver sección 3.3.1). Cada tipo define su etiqueta visible, su recíproco y,
si procede, su implicación funcional. El trait `TieneRelacionesReciprocas`
aplicado al modelo `CiudadanoRelacion` crea automáticamente el registro
inverso al crear una relación, y propaga los cambios de fecha_fin y las
eliminaciones al recíproco.

### 3.3.1 Catálogo de tipos de relación

```

tipos_relacion
- id
- slug              (string único, no editable — contrato interno del código)
- etiqueta          (string editable — lo que ve el TSR)
- etiqueta_reciproca (string editable — etiqueta del tipo inverso)
- slug_reciproco    (string nullable — FK lógica al slug del tipo recíproco;
                    null para tipos simétricos porque el recíproco es sí mismo)
- simetrica         (boolean — true para cónyuge, pareja_de_hecho, hermano/a)
- implicacion_funcional (string nullable — identificador semántico que el código
                    usa para lógica de negocio; independiente del slug y la etiqueta.
                    Valores iniciales: representante / tutor_legal / cuidador_principal)
- eliminable        (boolean — false para tipos del seeder; true para tipos creados
                    desde backoffice)
- activo            (boolean default true)
- timestamps

```

**Sobre `implicacion_funcional`:** el código nunca evalúa slugs ni etiquetas
para tomar decisiones. Evalúa `implicacion_funcional`. Esto permite que el
backoffice renombre etiquetas libremente, e incluso que un municipio cree un
segundo tipo con `implicacion_funcional = representante` (representante de
facto vs. representante legal) sin tocar código.

**Tipos iniciales del seeder:**

| slug | etiqueta | etiqueta_recíproca | simetrica | implicacion_funcional |
|---|---|---|---|---|
| padre | Padre/Madre | Hijo/a | false | — |
| hijo | Hijo/a | Padre/Madre | false | — |
| conyuge | Cónyuge | Cónyuge | true | — |
| pareja_de_hecho | Pareja de hecho | Pareja de hecho | true | — |
| hermano | Hermano/a | Hermano/a | true | — |
| abuelo | Abuelo/a | Nieto/a | false | — |
| nieto | Nieto/a | Abuelo/a | false | — |
| tutor_legal | Tutor/a legal | Tutelado/a | false | tutor_legal |
| tutelado | Tutelado/a | Tutor/a legal | false | — |
| representante | Representante | Representado/a | false | representante |
| representado | Representado/a | Representante | false | — |
| cuidador_principal | Cuidador/a principal | Persona cuidada | false | cuidador_principal |
| persona_cuidada | Persona cuidada | Cuidador/a principal | false | — |
| acogedor | Acogedor/a | Acogido/a | false | — |
| acogido | Acogido/a | Acogedor/a | false | — |

El campo `slug_reciproco` apunta al slug del tipo inverso (p. ej. `padre` →
`slug_reciproco = 'hijo'`). Para tipos simétricos es null porque el recíproco
es el mismo tipo.
```

Actualiza también la sección `## 7.4 Referencias de código` para añadir:

```markdown
- `Modules\Ciudadania\Models\TipoRelacion`
- `Modules\Ciudadania\Enums\ImplicacionFuncional`
- `Modules\Ciudadania\Filament\Resources\TipoRelacionResource`
- `Modules\Ciudadania\Database\Seeders\TipoRelacionSeeder`
```

---

## Paso 2 — Enum `ImplicacionFuncional`

Crea `Modules/Ciudadania/app/Enums/ImplicacionFuncional.php`:

```php
<?php

namespace Modules\Ciudadania\Enums;

enum ImplicacionFuncional: string
{
    case Representante       = 'representante';
    case TutorLegal          = 'tutor_legal';
    case CuidadorPrincipal   = 'cuidador_principal';

    public function etiqueta(): string
    {
        return match($this) {
            self::Representante     => 'Representante',
            self::TutorLegal        => 'Tutor legal',
            self::CuidadorPrincipal => 'Cuidador principal',
        };
    }
}
```

---

## Paso 3 — Migración

Crea `Modules/Ciudadania/database/migrations/2026_06_16_000003_create_tipos_relacion_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_relacion', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('etiqueta');
            $table->string('etiqueta_reciproca');
            $table->string('slug_reciproco')->nullable(); // FK lógica, no constraint
            $table->boolean('simetrica')->default(false);
            $table->string('implicacion_funcional')->nullable();
            $table->boolean('eliminable')->default(true);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index('implicacion_funcional');
            $table->index('activo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_relacion');
    }
};
```

> **Nota sobre `slug_reciproco`:** se define como FK lógica (string) en lugar
> de FK real a `id` para simplificar el seeder (los tipos se referencian por
> slug antes de tener ID asignado) y porque el slug es inmutable, por lo que
> la integridad referencial está garantizada por la regla de no edición del slug.

---

## Paso 4 — Modelo `TipoRelacion`

Crea `Modules/Ciudadania/app/Models/TipoRelacion.php`:

```php
<?php

namespace Modules\Ciudadania\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Modules\Ciudadania\Enums\ImplicacionFuncional;

class TipoRelacion extends Model
{
    protected $table = 'tipos_relacion';

    protected $fillable = [
        'slug',
        'etiqueta',
        'etiqueta_reciproca',
        'slug_reciproco',
        'simetrica',
        'implicacion_funcional',
        'eliminable',
        'activo',
    ];

    protected $casts = [
        'simetrica'  => 'boolean',
        'eliminable' => 'boolean',
        'activo'     => 'boolean',
    ];

    // --- Scopes ---

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    public function scopeConImplicacion(Builder $query, ImplicacionFuncional $implicacion): Builder
    {
        return $query->where('implicacion_funcional', $implicacion->value);
    }

    // --- Relación con el tipo recíproco ---

    public function tipoRecíproco(): ?self
    {
        if ($this->simetrica || ! $this->slug_reciproco) {
            return $this;
        }

        return static::where('slug', $this->slug_reciproco)->first();
    }

    // --- Métodos de negocio ---

    /**
     * Devuelve los tipos con una implicación funcional concreta.
     * El código debe usar este método, nunca comparar slugs directamente.
     */
    public static function conImplicacionFuncional(ImplicacionFuncional $implicacion): \Illuminate\Support\Collection
    {
        return static::where('implicacion_funcional', $implicacion->value)
            ->where('activo', true)
            ->get();
    }

    /**
     * ¿Existe al menos un tipo activo con esta implicación funcional?
     */
    public static function existeImplicacion(ImplicacionFuncional $implicacion): bool
    {
        return static::where('implicacion_funcional', $implicacion->value)
            ->where('activo', true)
            ->exists();
    }

    /**
     * Impide eliminar tipos no eliminables.
     */
    protected static function booted(): void
    {
        static::deleting(function (self $tipo) {
            if (! $tipo->eliminable) {
                throw new \LogicException(
                    "El tipo de relación '{$tipo->slug}' es del sistema y no puede eliminarse."
                );
            }
        });
    }

    // --- Para Filament: opciones de select ---

    public static function opcionesParaSelect(): array
    {
        return static::activos()
            ->orderBy('etiqueta')
            ->pluck('etiqueta', 'slug')
            ->toArray();
    }
}
```

---

## Paso 5 — Actualizar `CiudadanoRelacion` para usar `TipoRelacion`

En `Modules/Ciudadania/app/Models/CiudadanoRelacion.php`, añade la relación
al catálogo y actualiza el trait de reciprocidad para que use `TipoRelacion`
al buscar el tipo inverso:

```php
// Añadir relación al modelo TipoRelacion
public function tipoRelacion(): \Illuminate\Database\Eloquent\Relations\BelongsTo
{
    return $this->belongsTo(TipoRelacion::class, 'tipo_relacion', 'slug');
}
```

Y en el trait `TieneRelacionesReciprocas`, actualiza la lógica de obtención
del tipo recíproco para usar `TipoRelacion::where('slug', $tipo->slug_reciproco)`:

```php
// En el método que crea el recíproco, reemplazar la lógica hardcodeada
// por una consulta al catálogo:
$tipoRelacion = TipoRelacion::where('slug', $this->tipo_relacion)->first();

if (! $tipoRelacion) {
    // Tipo no registrado en catálogo — no se crea recíproco
    return;
}

$slugRecíproco = $tipoRelacion->simetrica
    ? $tipoRelacion->slug
    : $tipoRelacion->slug_reciproco;

if (! $slugRecíproco) {
    return; // tipo sin recíproco definido
}
```

---

## Paso 6 — Seeder `TipoRelacionSeeder`

Crea `Modules/Ciudadania/database/seeders/TipoRelacionSeeder.php`:

```php
<?php

namespace Modules\Ciudadania\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Ciudadania\Models\TipoRelacion;

class TipoRelacionSeeder extends Seeder
{
    public function run(): void
    {
        $tipos = [
            [
                'slug'                  => 'padre',
                'etiqueta'              => 'Padre/Madre',
                'etiqueta_reciproca'    => 'Hijo/a',
                'slug_reciproco'        => 'hijo',
                'simetrica'             => false,
                'implicacion_funcional' => null,
                'eliminable'            => false,
            ],
            [
                'slug'                  => 'hijo',
                'etiqueta'              => 'Hijo/a',
                'etiqueta_reciproca'    => 'Padre/Madre',
                'slug_reciproco'        => 'padre',
                'simetrica'             => false,
                'implicacion_funcional' => null,
                'eliminable'            => false,
            ],
            [
                'slug'                  => 'conyuge',
                'etiqueta'              => 'Cónyuge',
                'etiqueta_reciproca'    => 'Cónyuge',
                'slug_reciproco'        => null,
                'simetrica'             => true,
                'implicacion_funcional' => null,
                'eliminable'            => false,
            ],
            [
                'slug'                  => 'pareja_de_hecho',
                'etiqueta'              => 'Pareja de hecho',
                'etiqueta_reciproca'    => 'Pareja de hecho',
                'slug_reciproco'        => null,
                'simetrica'             => true,
                'implicacion_funcional' => null,
                'eliminable'            => false,
            ],
            [
                'slug'                  => 'hermano',
                'etiqueta'              => 'Hermano/a',
                'etiqueta_reciproca'    => 'Hermano/a',
                'slug_reciproco'        => null,
                'simetrica'             => true,
                'implicacion_funcional' => null,
                'eliminable'            => false,
            ],
            [
                'slug'                  => 'abuelo',
                'etiqueta'              => 'Abuelo/a',
                'etiqueta_reciproca'    => 'Nieto/a',
                'slug_reciproco'        => 'nieto',
                'simetrica'             => false,
                'implicacion_funcional' => null,
                'eliminable'            => false,
            ],
            [
                'slug'                  => 'nieto',
                'etiqueta'              => 'Nieto/a',
                'etiqueta_reciproca'    => 'Abuelo/a',
                'slug_reciproco'        => 'abuelo',
                'simetrica'             => false,
                'implicacion_funcional' => null,
                'eliminable'            => false,
            ],
            [
                'slug'                  => 'tutor_legal',
                'etiqueta'              => 'Tutor/a legal',
                'etiqueta_reciproca'    => 'Tutelado/a',
                'slug_reciproco'        => 'tutelado',
                'simetrica'             => false,
                'implicacion_funcional' => 'tutor_legal',
                'eliminable'            => false,
            ],
            [
                'slug'                  => 'tutelado',
                'etiqueta'              => 'Tutelado/a',
                'etiqueta_reciproca'    => 'Tutor/a legal',
                'slug_reciproco'        => 'tutor_legal',
                'simetrica'             => false,
                'implicacion_funcional' => null,
                'eliminable'            => false,
            ],
            [
                'slug'                  => 'representante',
                'etiqueta'              => 'Representante',
                'etiqueta_reciproca'    => 'Representado/a',
                'slug_reciproco'        => 'representado',
                'simetrica'             => false,
                'implicacion_funcional' => 'representante',
                'eliminable'            => false,
            ],
            [
                'slug'                  => 'representado',
                'etiqueta'              => 'Representado/a',
                'etiqueta_reciproca'    => 'Representante',
                'slug_reciproco'        => 'representante',
                'simetrica'             => false,
                'implicacion_funcional' => null,
                'eliminable'            => false,
            ],
            [
                'slug'                  => 'cuidador_principal',
                'etiqueta'              => 'Cuidador/a principal',
                'etiqueta_reciproca'    => 'Persona cuidada',
                'slug_reciproco'        => 'persona_cuidada',
                'simetrica'             => false,
                'implicacion_funcional' => 'cuidador_principal',
                'eliminable'            => false,
            ],
            [
                'slug'                  => 'persona_cuidada',
                'etiqueta'              => 'Persona cuidada',
                'etiqueta_reciproca'    => 'Cuidador/a principal',
                'slug_reciproco'        => 'cuidador_principal',
                'simetrica'             => false,
                'implicacion_funcional' => null,
                'eliminable'            => false,
            ],
            [
                'slug'                  => 'acogedor',
                'etiqueta'              => 'Acogedor/a',
                'etiqueta_reciproca'    => 'Acogido/a',
                'slug_reciproco'        => 'acogido',
                'simetrica'             => false,
                'implicacion_funcional' => null,
                'eliminable'            => false,
            ],
            [
                'slug'                  => 'acogido',
                'etiqueta'              => 'Acogido/a',
                'etiqueta_reciproca'    => 'Acogedor/a',
                'slug_reciproco'        => 'acogedor',
                'simetrica'             => false,
                'implicacion_funcional' => null,
                'eliminable'            => false,
            ],
        ];

        foreach ($tipos as $datos) {
            TipoRelacion::updateOrCreate(
                ['slug' => $datos['slug']],
                $datos
            );
        }
    }
}
```

Registra el seeder en `Database/Seeders/DatabaseSeeder.php` (o el seeder
raíz del módulo Ciudadanía) añadiendo:

```php
$this->call(TipoRelacionSeeder::class);
```

---

## Paso 7 — Recurso Filament `TipoRelacionResource`

Crea `Modules/Ciudadania/app/Filament/Resources/TipoRelacionResource.php`:

```php
<?php

namespace Modules\Ciudadania\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Ciudadania\Models\TipoRelacion;
use Modules\Ciudadania\Enums\ImplicacionFuncional;

class TipoRelacionResource extends Resource
{
    protected static ?string $model = TipoRelacion::class;
    protected static ?string $navigationGroup = 'Catálogos';
    protected static ?string $navigationLabel = 'Tipos de relación';
    protected static ?string $modelLabel = 'Tipo de relación';
    protected static ?string $pluralModelLabel = 'Tipos de relación';
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?int $navigationSort = 9;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identificación')
                ->schema([
                    Forms\Components\TextInput::make('slug')
                        ->label('Slug (identificador interno)')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->alphaDash()
                        ->maxLength(50)
                        ->helperText('Solo letras, números y guiones bajos. No editable tras la creación.')
                        ->disabled(fn ($record) => $record !== null), // no editable en edit

                    Forms\Components\TextInput::make('etiqueta')
                        ->label('Etiqueta (visible para el TSR)')
                        ->required()
                        ->maxLength(80),

                    Forms\Components\TextInput::make('etiqueta_reciproca')
                        ->label('Etiqueta del tipo recíproco')
                        ->required()
                        ->maxLength(80)
                        ->helperText('Cómo se etiqueta la relación inversa (p. ej. si este tipo es "Padre/Madre", el recíproco es "Hijo/a").'),
                ])->columns(3),

            Forms\Components\Section::make('Reciprocidad')
                ->schema([
                    Forms\Components\Toggle::make('simetrica')
                        ->label('Relación simétrica')
                        ->helperText('Marca si ambas partes tienen el mismo rol (cónyuge, hermano/a). En ese caso no hace falta definir slug recíproco.')
                        ->live(),

                    Forms\Components\Select::make('slug_reciproco')
                        ->label('Tipo recíproco')
                        ->options(fn () => TipoRelacion::orderBy('etiqueta')->pluck('etiqueta', 'slug'))
                        ->searchable()
                        ->nullable()
                        ->hidden(fn (Forms\Get $get) => $get('simetrica'))
                        ->helperText('Tipo de relación que se crea automáticamente en la otra parte.'),
                ])->columns(2),

            Forms\Components\Section::make('Implicación funcional')
                ->schema([
                    Forms\Components\Select::make('implicacion_funcional')
                        ->label('Implicación funcional')
                        ->options(
                            collect(ImplicacionFuncional::cases())
                                ->mapWithKeys(fn ($case) => [$case->value => $case->etiqueta()])
                                ->toArray()
                        )
                        ->nullable()
                        ->helperText('Si este tipo tiene relevancia en procesos del sistema (notificaciones, consentimientos...), selecciona la implicación correspondiente.'),

                    Forms\Components\Toggle::make('activo')
                        ->label('Activo')
                        ->default(true),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->fontFamily('mono')
                    ->width('140px'),

                Tables\Columns\TextColumn::make('etiqueta')
                    ->label('Etiqueta')
                    ->searchable(),

                Tables\Columns\TextColumn::make('etiqueta_reciproca')
                    ->label('Recíproco'),

                Tables\Columns\IconColumn::make('simetrica')
                    ->label('Simétrica')
                    ->boolean()
                    ->width('80px'),

                Tables\Columns\TextColumn::make('implicacion_funcional')
                    ->label('Implicación funcional')
                    ->badge()
                    ->color(fn ($state) => $state ? 'warning' : 'gray')
                    ->formatStateUsing(fn ($state) => $state
                        ? ImplicacionFuncional::from($state)->etiqueta()
                        : '—'
                    ),

                Tables\Columns\IconColumn::make('activo')
                    ->label('Activo')
                    ->boolean()
                    ->width('70px'),

                Tables\Columns\IconColumn::make('eliminable')
                    ->label('Del sistema')
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-pencil')
                    ->trueColor('gray')
                    ->falseColor('success')
                    ->width('90px'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('activo')->label('Estado'),
                Tables\Filters\TernaryFilter::make('simetrica')->label('Simétrica'),
                Tables\Filters\SelectFilter::make('implicacion_funcional')
                    ->label('Implicación funcional')
                    ->options(
                        collect(ImplicacionFuncional::cases())
                            ->mapWithKeys(fn ($case) => [$case->value => $case->etiqueta()])
                            ->toArray()
                    ),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn ($record) => true), // edición siempre disponible (etiqueta sí editable)
                Tables\Actions\DeleteAction::make()
                    ->visible(fn ($record) => $record->eliminable)
                    ->before(function ($record) {
                        if (! $record->eliminable) {
                            throw new \LogicException('Este tipo no puede eliminarse.');
                        }
                    }),
            ])
            ->defaultSort('etiqueta');
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasAnyRole(['adm_sistema']) ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->hasAnyRole(['adm_sistema']) ?? false;
    }

    public static function canDelete($record): bool
    {
        return (auth()->user()?->hasAnyRole(['adm_sistema']) ?? false)
            && $record->eliminable;
    }

    public static function getPages(): array
    {
        return [
            'index'  => \Filament\Resources\Pages\ListRecords::route('/'),
            'create' => \Filament\Resources\Pages\CreateRecord::route('/create'),
            'edit'   => \Filament\Resources\Pages\EditRecord::route('/{record}/edit'),
        ];
    }
}
```

Registra el resource en `CiudadaniaServiceProvider` si el módulo no usa
auto-discovery de Filament:

```php
// En el método boot() o register() del ServiceProvider:
Filament::registerResources([
    \Modules\Ciudadania\Filament\Resources\TipoRelacionResource::class,
]);
```

Si el módulo ya usa auto-discovery (comprueba si otros resources del módulo
se registran automáticamente), no es necesario este paso.

---

## Paso 8 — Factory `TipoRelacionFactory`

Crea `Modules/Ciudadania/database/factories/TipoRelacionFactory.php`:

```php
<?php

namespace Modules\Ciudadania\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Ciudadania\Models\TipoRelacion;

class TipoRelacionFactory extends Factory
{
    protected $model = TipoRelacion::class;

    public function definition(): array
    {
        $slug = $this->faker->unique()->word() . '_' . $this->faker->randomNumber(3);

        return [
            'slug'                  => $slug,
            'etiqueta'              => ucfirst($this->faker->word()),
            'etiqueta_reciproca'    => ucfirst($this->faker->word()),
            'slug_reciproco'        => null,
            'simetrica'             => false,
            'implicacion_funcional' => null,
            'eliminable'            => true,
            'activo'                => true,
        ];
    }

    public function simetrico(): static
    {
        return $this->state(fn () => ['simetrica' => true, 'slug_reciproco' => null]);
    }

    public function conImplicacion(string $implicacion): static
    {
        return $this->state(fn () => ['implicacion_funcional' => $implicacion]);
    }

    public function noEliminable(): static
    {
        return $this->state(fn () => ['eliminable' => false]);
    }
}
```

---

## Paso 9 — Tests funcionales

Crea `Modules/Ciudadania/tests/Feature/TipoRelacionTest.php`:

```php
<?php

namespace Modules\Ciudadania\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Ciudadania\Models\TipoRelacion;
use Modules\Ciudadania\Enums\ImplicacionFuncional;
use Tests\TestCase;

/**
 * Tests funcionales del catálogo de tipos de relación.
 * Nomenclatura: TF-TR-XX
 */
class TipoRelacionTest extends TestCase
{
    use RefreshDatabase;

    // --- Seeder ---

    // TF-TR-01: El seeder carga los 15 tipos iniciales
    public function test_seeder_carga_tipos_iniciales(): void
    {
        $this->seed(\Modules\Ciudadania\Database\Seeders\TipoRelacionSeeder::class);

        $this->assertEquals(15, TipoRelacion::count());
    }

    // TF-TR-02: El seeder es idempotente
    public function test_seeder_es_idempotente(): void
    {
        $this->seed(\Modules\Ciudadania\Database\Seeders\TipoRelacionSeeder::class);
        $this->seed(\Modules\Ciudadania\Database\Seeders\TipoRelacionSeeder::class);

        $this->assertEquals(15, TipoRelacion::count());
    }

    // TF-TR-03: Los tipos del seeder tienen eliminable = false
    public function test_tipos_seeder_no_son_eliminables(): void
    {
        $this->seed(\Modules\Ciudadania\Database\Seeders\TipoRelacionSeeder::class);

        $eliminables = TipoRelacion::where('eliminable', true)->count();
        $this->assertEquals(0, $eliminables);
    }

    // TF-TR-04: Las implicaciones funcionales previstas existen tras el seeder
    public function test_implicaciones_funcionales_existen(): void
    {
        $this->seed(\Modules\Ciudadania\Database\Seeders\TipoRelacionSeeder::class);

        foreach (ImplicacionFuncional::cases() as $implicacion) {
            $this->assertTrue(
                TipoRelacion::existeImplicacion($implicacion),
                "Implicación funcional '{$implicacion->value}' no encontrada tras el seeder."
            );
        }
    }

    // TF-TR-05: Los tipos simétricos no tienen slug_reciproco
    public function test_tipos_simetricos_sin_slug_reciproco(): void
    {
        $this->seed(\Modules\Ciudadania\Database\Seeders\TipoRelacionSeeder::class);

        $simetricosConRecíproco = TipoRelacion::where('simetrica', true)
            ->whereNotNull('slug_reciproco')
            ->count();

        $this->assertEquals(0, $simetricosConRecíproco);
    }

    // TF-TR-06: Los tipos asimétricos con recíproco apuntan a slugs existentes
    public function test_slug_reciproco_referencia_slug_existente(): void
    {
        $this->seed(\Modules\Ciudadania\Database\Seeders\TipoRelacionSeeder::class);

        $slugs = TipoRelacion::pluck('slug')->toArray();

        TipoRelacion::where('simetrica', false)
            ->whereNotNull('slug_reciproco')
            ->get()
            ->each(function ($tipo) use ($slugs) {
                $this->assertContains(
                    $tipo->slug_reciproco,
                    $slugs,
                    "El slug_reciproco '{$tipo->slug_reciproco}' de '{$tipo->slug}' no existe."
                );
            });
    }

    // --- Modelo ---

    // TF-TR-07: No se puede eliminar un tipo no eliminable
    public function test_no_elimina_tipo_no_eliminable(): void
    {
        $tipo = TipoRelacion::factory()->noEliminable()->create();

        $this->expectException(\LogicException::class);
        $tipo->delete();
    }

    // TF-TR-08: Se puede eliminar un tipo eliminable
    public function test_elimina_tipo_eliminable(): void
    {
        $tipo = TipoRelacion::factory()->create(); // eliminable = true por defecto

        $tipo->delete();

        $this->assertDatabaseMissing('tipos_relacion', ['id' => $tipo->id]);
    }

    // TF-TR-09: conImplicacionFuncional() devuelve solo tipos activos con esa implicación
    public function test_con_implicacion_funcional_solo_activos(): void
    {
        $this->seed(\Modules\Ciudadania\Database\Seeders\TipoRelacionSeeder::class);

        // Desactivar el representante
        TipoRelacion::where('implicacion_funcional', 'representante')
            ->update(['activo' => false]);

        $resultados = TipoRelacion::conImplicacionFuncional(ImplicacionFuncional::Representante);

        $this->assertCount(0, $resultados);
    }

    // TF-TR-10: existeImplicacion() devuelve false si todos los tipos están inactivos
    public function test_existe_implicacion_false_si_inactivos(): void
    {
        $this->seed(\Modules\Ciudadania\Database\Seeders\TipoRelacionSeeder::class);

        TipoRelacion::where('implicacion_funcional', 'cuidador_principal')
            ->update(['activo' => false]);

        $this->assertFalse(
            TipoRelacion::existeImplicacion(ImplicacionFuncional::CuidadorPrincipal)
        );
    }

    // TF-TR-11: tipoRecíproco() devuelve self para tipos simétricos
    public function test_tipo_reciproco_simetrico_devuelve_self(): void
    {
        $this->seed(\Modules\Ciudadania\Database\Seeders\TipoRelacionSeeder::class);

        $conyuge = TipoRelacion::where('slug', 'conyuge')->first();

        $this->assertEquals($conyuge->id, $conyuge->tipoRecíproco()->id);
    }

    // TF-TR-12: tipoRecíproco() devuelve el tipo correcto para asimétricos
    public function test_tipo_reciproco_asimetrico(): void
    {
        $this->seed(\Modules\Ciudadania\Database\Seeders\TipoRelacionSeeder::class);

        $padre = TipoRelacion::where('slug', 'padre')->first();
        $hijo  = TipoRelacion::where('slug', 'hijo')->first();

        $this->assertEquals($hijo->id, $padre->tipoRecíproco()->id);
    }

    // TF-TR-13: opcionesParaSelect() devuelve solo tipos activos
    public function test_opciones_para_select_solo_activos(): void
    {
        $this->seed(\Modules\Ciudadania\Database\Seeders\TipoRelacionSeeder::class);

        TipoRelacion::where('slug', 'acogido')->update(['activo' => false]);

        $opciones = TipoRelacion::opcionesParaSelect();

        $this->assertArrayNotHasKey('acogido', $opciones);
        $this->assertCount(14, $opciones);
    }

    // TF-TR-14: La reciprocidad es coherente (padre↔hijo, abuelo↔nieto...)
    public function test_reciprocidad_coherente_en_pares(): void
    {
        $this->seed(\Modules\Ciudadania\Database\Seeders\TipoRelacionSeeder::class);

        $pares = [
            ['padre', 'hijo'],
            ['abuelo', 'nieto'],
            ['tutor_legal', 'tutelado'],
            ['representante', 'representado'],
            ['cuidador_principal', 'persona_cuidada'],
            ['acogedor', 'acogido'],
        ];

        foreach ($pares as [$slugA, $slugB]) {
            $a = TipoRelacion::where('slug', $slugA)->first();
            $b = TipoRelacion::where('slug', $slugB)->first();

            $this->assertEquals($slugB, $a->slug_reciproco,
                "El recíproco de '$slugA' debería ser '$slugB'.");
            $this->assertEquals($slugA, $b->slug_reciproco,
                "El recíproco de '$slugB' debería ser '$slugA'.");
        }
    }

    // TF-TR-15: ImplicacionFuncional::from() lanza error para valor desconocido
    public function test_implicacion_funcional_enum_rechaza_valor_invalido(): void
    {
        $this->expectException(\ValueError::class);
        ImplicacionFuncional::from('valor_inexistente');
    }
}
```

---

## Paso 10 — Actualizar BACKLOG y SESSION

Elimina de `BACKLOG.md` cualquier entrada relacionada con "catálogo de
tipos de relación pendiente" si existe.

Añade en `BACKLOG.md`:

```markdown
**Relaciones en UI de intervención** — 2026-06-16
`Módulo: Intervención`
El catálogo TipoRelacion está implementado. Pendiente:
- Widget UC en CiudadanoPage: mostrar tipo de relación de cada miembro
  respecto al titular (leer de ciudadano_relaciones filtrando por el par).
- Línea de representante entre cabecera ciudadano y widget UC.
- Modal "Ver todas las relaciones" accesible desde el widget UC.
- Gestión de relaciones (crear/editar/cerrar) en FichaCiudadanoPage.
```

Actualiza `SESSION.md`:
- Tarea completada: catálogo TipoRelacion con seeder, modelo, enum y recurso Filament
- Siguiente paso recomendado: enriquecer widget UC con relaciones y añadir
  línea de representante en CiudadanoPage

---

## Checklist de verificación

- [ ] `php artisan migrate` sin errores
- [ ] `php artisan db:seed --class=Modules\\Ciudadania\\Database\\Seeders\\TipoRelacionSeeder` — 15 registros creados
- [ ] `php artisan test --filter=TipoRelacionTest` — 15 tests en verde
- [ ] Suite completa Ciudadanía sin regresiones
- [ ] El recurso aparece en Filament bajo el grupo **Catálogos**
- [ ] El slug no es editable al editar un tipo existente
- [ ] El botón eliminar no aparece para tipos con `eliminable = false`
- [ ] BACKLOG y SESSION.md actualizados
- [ ] Commit: `feat(ciudadania): catálogo TipoRelacion con seeder, enum ImplicacionFuncional y recurso Filament`
