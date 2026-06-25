# Instrucciones CLI — Objetivos con indicadores, cierre del plan y UI

## Contexto

Las tablas `objetivos_catalogo` y `plan_objetivos` ya existen de la sesión
anterior (`plan-intervencion-completo.md`). Esta tarea las amplía con:

1. FK `tipo_ficha_id` en `objetivos_catalogo` (área temática)
2. Tabla nueva `indicadores_catalogo`
3. Tabla nueva `plan_objetivo_indicadores`
4. Columna `tipo_ficha_id` en `plan_objetivos`
5. Enum `motivo_cierre` actualizado en `planes_intervencion`
6. Backoffice Filament: indicadores en la página de gestión de objetivos
7. UI en `PlanPage`: valoración de indicadores y cierre del plan
8. Tests funcionales

---

## Migración 1 — Ampliar `objetivos_catalogo` con `tipo_ficha_id`

Crea `Modules/Intervencion/database/migrations/2026_06_16_000016_add_tipo_ficha_to_objetivos_catalogo.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('objetivos_catalogo', function (Blueprint $table) {
            $table->foreignId('tipo_ficha_id')
                  ->nullable()
                  ->after('tipo_plan_id')
                  ->constrained('tipo_fichas')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('objetivos_catalogo', function (Blueprint $table) {
            $table->dropForeign(['tipo_ficha_id']);
            $table->dropColumn('tipo_ficha_id');
        });
    }
};
```

> Verifica que la tabla se llama `tipo_fichas` (plural) consultando
> `CHANGELOG.md` sección `TipoFichaResource`. Si el nombre difiere, ajusta.

---

## Migración 2 — Ampliar `plan_objetivos` con `tipo_ficha_id`

Crea `Modules/Intervencion/database/migrations/2026_06_16_000017_add_tipo_ficha_to_plan_objetivos.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_objetivos', function (Blueprint $table) {
            $table->foreignId('tipo_ficha_id')
                  ->nullable()
                  ->after('nivel')
                  ->constrained('tipo_fichas')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('plan_objetivos', function (Blueprint $table) {
            $table->dropForeign(['tipo_ficha_id']);
            $table->dropColumn('tipo_ficha_id');
        });
    }
};
```

---

## Migración 3 — Crear `indicadores_catalogo`

Crea `Modules/Intervencion/database/migrations/2026_06_16_000018_create_indicadores_catalogo_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indicadores_catalogo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('objetivo_catalogo_id')
                  ->unique() // uno por objetivo
                  ->constrained('objetivos_catalogo')
                  ->cascadeOnDelete();
            $table->text('descripcion');
            $table->enum('tipo_valoracion', [
                'conseguido_proceso_no',
                'favorable_mantiene_desfavorable',
                'si_no',
            ])->default('conseguido_proceso_no');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indicadores_catalogo');
    }
};
```

---

## Migración 4 — Crear `plan_objetivo_indicadores`

Crea `Modules/Intervencion/database/migrations/2026_06_16_000019_create_plan_objetivo_indicadores_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_objetivo_indicadores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_objetivo_id')
                  ->constrained('plan_objetivos')
                  ->cascadeOnDelete();
            $table->foreignId('indicador_catalogo_id')
                  ->nullable()
                  ->constrained('indicadores_catalogo')
                  ->nullOnDelete();
            $table->text('descripcion');
            $table->enum('tipo_valoracion', [
                'conseguido_proceso_no',
                'favorable_mantiene_desfavorable',
                'si_no',
            ])->default('conseguido_proceso_no');
            $table->string('valoracion_actual')->nullable();
            $table->date('fecha_valoracion')->nullable();
            $table->foreignId('seguimiento_id')
                  ->nullable()
                  ->constrained('seguimientos_plan')
                  ->nullOnDelete();
            $table->timestamps();

            $table->index('plan_objetivo_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_objetivo_indicadores');
    }
};
```

---

## Migración 5 — Actualizar enum `motivo_cierre` en `planes_intervencion`

Crea `Modules/Intervencion/database/migrations/2026_06_16_000020_update_motivo_cierre_planes.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Valores correctos según diseño
    private const VALORES = [
        'negativa_firma',
        'consecucion_objetivos',
        'cambio_residencia',
        'imposibilidad_localizacion',
        'fallecimiento',
        'fin_intervencion',
    ];

    public function up(): void
    {
        // En SQLite (tests) el enum es un string — sin acción necesaria.
        // En PostgreSQL hay que rehacer el constraint.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE planes_intervencion DROP CONSTRAINT IF EXISTS planes_intervencion_motivo_cierre_check');
            $valores = implode("','", self::VALORES);
            DB::statement("ALTER TABLE planes_intervencion ADD CONSTRAINT planes_intervencion_motivo_cierre_check CHECK (motivo_cierre IN ('{$valores}'))");
        }

        // En MySQL: regenerar la columna enum
        if (DB::getDriverName() === 'mysql') {
            $valores = "'" . implode("','", self::VALORES) . "'";
            DB::statement("ALTER TABLE planes_intervencion MODIFY COLUMN motivo_cierre ENUM({$valores}) NULL");
        }
    }

    public function down(): void
    {
        // Revertir si fuera necesario — omitido por simplicidad
    }
};
```

---

## Modelo `IndicadorCatalogo`

Crea `Modules/Intervencion/app/Models/IndicadorCatalogo.php`:

```php
<?php

namespace Modules\Intervencion\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IndicadorCatalogo extends Model
{
    protected $table = 'indicadores_catalogo';

    protected $fillable = [
        'objetivo_catalogo_id',
        'descripcion',
        'tipo_valoracion',
    ];

    public function objetivoCatalogo(): BelongsTo
    {
        return $this->belongsTo(ObjetivoCatalogo::class, 'objetivo_catalogo_id');
    }

    public function indicadoresPlan(): HasMany
    {
        return $this->hasMany(PlanObjetivoIndicador::class, 'indicador_catalogo_id');
    }

    /**
     * Valores posibles según el tipo de valoración.
     */
    public function valoresPosibles(): array
    {
        return match ($this->tipo_valoracion) {
            'conseguido_proceso_no'          => ['conseguido', 'en_proceso', 'no_conseguido'],
            'favorable_mantiene_desfavorable' => ['favorable', 'se_mantiene', 'desfavorable'],
            'si_no'                           => ['si', 'no'],
            default                           => [],
        };
    }

    /**
     * Etiquetas visibles para cada valor.
     */
    public static function etiquetasValoración(string $tipo): array
    {
        return match ($tipo) {
            'conseguido_proceso_no' => [
                'conseguido'    => 'Conseguido',
                'en_proceso'    => 'En proceso',
                'no_conseguido' => 'No conseguido',
            ],
            'favorable_mantiene_desfavorable' => [
                'favorable'    => 'Favorable',
                'se_mantiene'  => 'Se mantiene',
                'desfavorable' => 'Desfavorable',
            ],
            'si_no' => [
                'si' => 'Sí',
                'no' => 'No',
            ],
            default => [],
        };
    }
}
```

---

## Modelo `PlanObjetivoIndicador`

Crea `Modules/Intervencion/app/Models/PlanObjetivoIndicador.php`:

```php
<?php

namespace Modules\Intervencion\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanObjetivoIndicador extends Model
{
    protected $table = 'plan_objetivo_indicadores';

    protected $fillable = [
        'plan_objetivo_id',
        'indicador_catalogo_id',
        'descripcion',
        'tipo_valoracion',
        'valoracion_actual',
        'fecha_valoracion',
        'seguimiento_id',
    ];

    protected $casts = [
        'fecha_valoracion' => 'date',
    ];

    public function planObjetivo(): BelongsTo
    {
        return $this->belongsTo(PlanObjetivo::class, 'plan_objetivo_id');
    }

    public function indicadorCatalogo(): BelongsTo
    {
        return $this->belongsTo(IndicadorCatalogo::class, 'indicador_catalogo_id');
    }

    public function seguimiento(): BelongsTo
    {
        return $this->belongsTo(SeguimientoPlan::class, 'seguimiento_id');
    }

    /**
     * Valores posibles para este indicador.
     */
    public function valoresPosibles(): array
    {
        return IndicadorCatalogo::etiquetasValoración($this->tipo_valoracion);
    }

    /**
     * ¿Tiene valoración registrada?
     */
    public function estaValorado(): bool
    {
        return $this->valoracion_actual !== null;
    }

    /**
     * Registra una nueva valoración.
     */
    public function registrarValoracion(
        string $valor,
        ?int $seguimientoId = null
    ): void {
        $posibles = array_keys($this->valoresPosibles());
        if (! in_array($valor, $posibles)) {
            throw new \InvalidArgumentException(
                "Valor '{$valor}' no válido para tipo '{$this->tipo_valoracion}'."
            );
        }

        $this->update([
            'valoracion_actual' => $valor,
            'fecha_valoracion'  => now()->toDateString(),
            'seguimiento_id'    => $seguimientoId,
        ]);
    }
}
```

---

## Actualizar `ObjetivoCatalogo` y `PlanObjetivo`

### En `ObjetivoCatalogo`

Añade la relación con `TipoFicha` y con `IndicadorCatalogo`:

```php
// FK a tipo de ficha (área temática) — solo en objetivos específicos
public function tipoFicha(): \Illuminate\Database\Eloquent\Relations\BelongsTo
{
    return $this->belongsTo(\Modules\Intervencion\Models\TipoFicha::class, 'tipo_ficha_id');
}

// Indicador del catálogo (uno por objetivo)
public function indicador(): \Illuminate\Database\Eloquent\Relations\HasOne
{
    return $this->hasOne(IndicadorCatalogo::class, 'objetivo_catalogo_id');
}
```

Añade scope para filtrar por área temática:

```php
public function scopeDeArea(Builder $query, int $tipoFichaId): Builder
{
    return $query->where('tipo_ficha_id', $tipoFichaId);
}
```

### En `PlanObjetivo`

Añade la relación con `TipoFicha` y con `PlanObjetivoIndicador`:

```php
public function tipoFicha(): \Illuminate\Database\Eloquent\Relations\BelongsTo
{
    return $this->belongsTo(\Modules\Intervencion\Models\TipoFicha::class, 'tipo_ficha_id');
}

public function indicador(): \Illuminate\Database\Eloquent\Relations\HasOne
{
    return $this->hasOne(PlanObjetivoIndicador::class, 'plan_objetivo_id');
}
```

Añade método para instanciar el indicador desde el catálogo:

```php
/**
 * Crea el indicador de este objetivo a partir del catálogo.
 * Si el objetivo es ex-novo, usa descripción y tipo pasados como parámetro.
 */
public function instanciarIndicador(
    ?string $descripcionExnovo = null,
    string $tipoValoración = 'conseguido_proceso_no'
): PlanObjetivoIndicador {
    $indicadorCatalogo = $this->objetivoCatalogo?->indicador;

    return PlanObjetivoIndicador::create([
        'plan_objetivo_id'     => $this->id,
        'indicador_catalogo_id' => $indicadorCatalogo?->id,
        'descripcion'          => $indicadorCatalogo?->descripcion ?? $descripcionExnovo ?? '',
        'tipo_valoracion'      => $indicadorCatalogo?->tipo_valoracion ?? $tipoValoración,
        'valoracion_actual'    => null,
    ]);
}
```

---

## Actualizar backoffice Filament — `GestionarObjetivos`

En `Modules/Intervencion/app/Filament/Resources/TipoPlanResource/Pages/GestionarObjetivos.php`,
actualiza las columnas de la tabla para mostrar el área temática y el indicador:

```php
protected function getTableColumns(): array
{
    return [
        Tables\Columns\BadgeColumn::make('nivel')
            ->colors(['primary' => 'general', 'gray' => 'especifico']),
        Tables\Columns\TextColumn::make('tipoFicha.nombre')
            ->label('Área temática')
            ->placeholder('—')
            ->description(fn ($record) => $record->nivel === 'especifico'
                ? 'Área de la ficha'
                : 'Sin área (objetivo general)'
            ),
        Tables\Columns\TextColumn::make('texto')->limit(60)->searchable(),
        Tables\Columns\TextColumn::make('indicador.descripcion')
            ->label('Indicador')
            ->limit(40)
            ->placeholder('Sin indicador'),
        Tables\Columns\BadgeColumn::make('indicador.tipo_valoracion')
            ->label('Tipo valoración')
            ->formatStateUsing(fn ($state) => match($state) {
                'conseguido_proceso_no'           => 'C/P/N',
                'favorable_mantiene_desfavorable' => 'F/M/D',
                'si_no'                           => 'Sí/No',
                default                           => '—',
            })
            ->placeholder('—'),
        Tables\Columns\IconColumn::make('activo')->boolean(),
    ];
}
```

Actualiza el formulario de creación para incluir el indicador:

```php
protected function getTableHeaderActions(): array
{
    return [
        Tables\Actions\CreateAction::make()
            ->model(ObjetivoCatalogo::class)
            ->form([
                Forms\Components\Select::make('nivel')
                    ->options(['general' => 'General', 'especifico' => 'Específico'])
                    ->required()
                    ->live(),

                Forms\Components\Select::make('tipo_ficha_id')
                    ->label('Área temática (tipo de ficha)')
                    ->options(fn () => \Modules\Intervencion\Models\TipoFicha::pluck('nombre', 'id'))
                    ->searchable()
                    ->nullable()
                    ->visible(fn (Forms\Get $get) => $get('nivel') === 'especifico')
                    ->helperText('El área determina qué fichas activan la propuesta de este objetivo.'),

                Forms\Components\Select::make('objetivo_general_id')
                    ->label('Objetivo general al que pertenece')
                    ->options(fn () => ObjetivoCatalogo::where('tipo_plan_id', $this->record->id)
                        ->where('nivel', 'general')
                        ->pluck('texto', 'id'))
                    ->nullable()
                    ->visible(fn (Forms\Get $get) => $get('nivel') === 'especifico'),

                Forms\Components\Textarea::make('texto')
                    ->label('Texto del objetivo')
                    ->required()
                    ->rows(2),

                Forms\Components\TextInput::make('orden')->numeric()->default(0),
                Forms\Components\Toggle::make('activo')->default(true),

                Forms\Components\Section::make('Indicador asociado')
                    ->schema([
                        Forms\Components\Textarea::make('indicador_descripcion')
                            ->label('Qué se mide / indicador')
                            ->required()
                            ->rows(2),
                        Forms\Components\Select::make('indicador_tipo_valoracion')
                            ->label('Tipo de valoración')
                            ->options([
                                'conseguido_proceso_no'           => 'Conseguido / En proceso / No conseguido',
                                'favorable_mantiene_desfavorable' => 'Favorable / Se mantiene / Desfavorable',
                                'si_no'                           => 'Sí / No',
                            ])
                            ->default('conseguido_proceso_no')
                            ->required(),
                    ])
                    ->columns(1),
            ])
            ->using(function (array $data) {
                // Extraer datos del indicador antes de crear el objetivo
                $indicadorDesc = $data['indicador_descripcion'];
                $indicadorTipo = $data['indicador_tipo_valoracion'];
                unset($data['indicador_descripcion'], $data['indicador_tipo_valoracion']);

                $objetivo = ObjetivoCatalogo::create(array_merge(
                    $data,
                    ['tipo_plan_id' => $this->record->id]
                ));

                // Crear el indicador asociado
                IndicadorCatalogo::create([
                    'objetivo_catalogo_id' => $objetivo->id,
                    'descripcion'          => $indicadorDesc,
                    'tipo_valoracion'      => $indicadorTipo,
                ]);

                return $objetivo;
            }),
    ];
}
```

---

## Actualizar `PlanPage` — valoración de indicadores y cierre del plan

### Nuevas propiedades en `PlanPage.php`

```php
// --- Cierre del plan ---
public bool $modalCierreAbierto = false;
public string $motivoCierre = '';
public string $notasCierre = '';

// --- Valoración de indicadores ---
// Array: [plan_objetivo_indicador_id => valor]
public array $valoracionesIndicadores = [];
```

### Nuevos computeds

```php
#[Computed]
public function motivosCierre(): array
{
    return [
        'negativa_firma'           => 'Cerrado por negativa a la firma / falta de colaboración',
        'consecucion_objetivos'    => 'Cerrado por consecución de objetivos',
        'cambio_residencia'        => 'Cerrado por cambio de residencia',
        'imposibilidad_localizacion' => 'Cerrado por imposibilidad de localizar a la familia',
        'fallecimiento'            => 'Cerrado por fallecimiento',
        'fin_intervencion'         => 'Cerrado por finalización de la intervención',
    ];
}

#[Computed]
public function objetivosConIndicadores(): \Illuminate\Support\Collection
{
    if (! $this->plan) return collect();

    return $this->plan->objetivosGenerales()
        ->with(['objetivosEspecificos.indicador', 'indicador'])
        ->get();
}
```

### Nuevas acciones

```php
public function abrirModalCierre(): void
{
    $this->modalCierreAbierto = true;
    $this->motivoCierre = '';
    $this->notasCierre = '';
}

public function cerrarModalCierre(): void
{
    $this->modalCierreAbierto = false;
}

public function confirmarCierrePlan(): void
{
    if (! $this->plan || empty($this->motivoCierre)) return;
    $this->authorize('update', $this->plan);

    // Registrar el cierre como cambio en el historial
    $this->plan->registrarCambio(
        auth()->id(),
        "Cierre del plan: {$this->motivosCierre[$this->motivoCierre]}"
            . ($this->notasCierre ? ". {$this->notasCierre}" : ''),
        'discrecional'
    );

    $this->plan->update([
        'estado'         => 'cerrado',
        'fecha_cierre'   => now()->toDateString(),
        'motivo_cierre'  => $this->motivoCierre,
    ]);

    // Si hay notas, crear apunte en la historia social
    if ($this->notasCierre && $this->plan->historia_id) {
        \Modules\Intervencion\Models\Apunte::create([
            'historia_id'    => $this->plan->historia_id,
            'profesional_id' => auth()->id(),
            'tipo'           => 'nota',
            'contenido'      => "Cierre del plan: {$this->notasCierre}",
        ]);
    }

    $this->modalCierreAbierto = false;
    $this->mensajeExito = 'Plan cerrado correctamente.';
    unset($this->plan);
}

public function guardarValoracionIndicador(int $indicadorId, string $valor): void
{
    if (! $this->plan) return;

    $indicador = \Modules\Intervencion\Models\PlanObjetivoIndicador::findOrFail($indicadorId);

    // Verificar que pertenece al plan
    if ($indicador->planObjetivo->plan_id !== $this->plan->id) {
        return;
    }

    if ($this->planFirmado) {
        // En plan activo, la valoración de indicadores no requiere motivo
        // (es el seguimiento natural del plan, no un cambio estructural)
        $indicador->registrarValoracion($valor);
    } else {
        $indicador->registrarValoracion($valor);
    }

    $this->valoracionesIndicadores[$indicadorId] = $valor;
    $this->mensajeExito = 'Valoración guardada.';
}
```

### Cambios en la vista Blade

Localiza la sección de objetivos en `plan-page.blade.php` y actualiza el bloque
de cada objetivo general para mostrar su indicador y los de sus específicos:

```blade
{{-- Dentro del foreach de objetivos generales --}}
@foreach($this->objetivosConIndicadores as $og)
<div class="plan-obj-general" wire:key="og-{{ $og->id }}">

    <div class="plan-obj-texto">{{ $og->texto }}</div>

    {{-- Indicador del objetivo general --}}
    @if($og->indicador)
    <div class="plan-indicador" wire:key="ind-og-{{ $og->indicador->id }}">
        <div class="plan-indicador-desc">{{ $og->indicador->descripcion }}</div>
        <div class="plan-indicador-select">
            @foreach($og->indicador->valoresPosibles() as $valor => $etiqueta)
            <label class="plan-indicador-opcion
                {{ $og->indicador->valoracion_actual === $valor ? 'plan-indicador-opcion--activa' : '' }}">
                <input
                    type="radio"
                    wire:click="guardarValoracionIndicador({{ $og->indicador->id }}, '{{ $valor }}')"
                    {{ $og->indicador->valoracion_actual === $valor ? 'checked' : '' }}
                    @if($this->plan?->estado === 'cerrado') disabled @endif
                >
                {{ $etiqueta }}
            </label>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Objetivos específicos con sus indicadores --}}
    @if($og->objetivosEspecificos->isNotEmpty())
    <ul class="plan-obj-especificos">
        @foreach($og->objetivosEspecificos as $oe)
        <li wire:key="oe-{{ $oe->id }}">
            <div class="plan-obj-esp-texto">{{ $oe->texto }}</div>
            @if($oe->tipoFicha)
            <span class="plan-obj-area">{{ $oe->tipoFicha->nombre }}</span>
            @endif
            @if($oe->indicador)
            <div class="plan-indicador plan-indicador--esp" wire:key="ind-oe-{{ $oe->indicador->id }}">
                <div class="plan-indicador-desc">{{ $oe->indicador->descripcion }}</div>
                <div class="plan-indicador-select">
                    @foreach($oe->indicador->valoresPosibles() as $valor => $etiqueta)
                    <label class="plan-indicador-opcion
                        {{ $oe->indicador->valoracion_actual === $valor ? 'plan-indicador-opcion--activa' : '' }}">
                        <input
                            type="radio"
                            wire:click="guardarValoracionIndicador({{ $oe->indicador->id }}, '{{ $valor }}')"
                            {{ $oe->indicador->valoracion_actual === $valor ? 'checked' : '' }}
                            @if($this->plan?->estado === 'cerrado') disabled @endif
                        >
                        {{ $etiqueta }}
                    </label>
                    @endforeach
                </div>
            </div>
            @endif
        </li>
        @endforeach
    </ul>
    @endif

    <div class="plan-obj-footer">
        <span class="plan-estado-badge plan-estado-{{ $og->estado }}">
            {{ ucfirst(str_replace('_', ' ', $og->estado)) }}
        </span>
        <button class="plan-tb-btn">
            <x-heroicon-o-pencil-square class="icon-13" aria-hidden="true"/>
        </button>
    </div>
</div>
@endforeach
```

Añade el modal de cierre del plan antes del cierre del componente:

```blade
{{-- Modal: cierre del plan --}}
@if($modalCierreAbierto)
<div
    class="plan-modal-overlay"
    x-data
    x-on:keydown.escape.window="$wire.cerrarModalCierre()"
>
    <div class="plan-modal plan-modal--cierre">
        <div class="plan-modal-title">Cerrar plan de intervención</div>
        <div class="plan-modal-sub">
            Una vez cerrado, el plan queda en modo solo lectura.
            Esta acción queda registrada en el historial.
        </div>

        <div class="plan-field">
            <label class="plan-label">Motivo de cierre</label>
            <select wire:model="motivoCierre" class="plan-select" style="width:100%">
                <option value="">— Selecciona un motivo —</option>
                @foreach($this->motivosCierre as $valor => $etiqueta)
                <option value="{{ $valor }}">{{ $etiqueta }}</option>
                @endforeach
            </select>
        </div>

        <div class="plan-field">
            <label class="plan-label">Observaciones (opcional)</label>
            <textarea
                wire:model="notasCierre"
                class="plan-textarea"
                rows="2"
                placeholder="Se añadirán como apunte en la historia social si se rellenan…"
            ></textarea>
        </div>

        @if(in_array($motivoCierre, ['negativa_firma', 'imposibilidad_localizacion']))
        <div class="plan-aviso-cierre">
            <x-heroicon-o-exclamation-triangle class="icon-14" aria-hidden="true"/>
            Este motivo de cierre requiere dejar constancia en el historial de apuntes.
            Usa el campo de observaciones para documentarlo.
        </div>
        @endif

        <div class="plan-modal-footer">
            <button wire:click="cerrarModalCierre" class="plan-btn">Cancelar</button>
            <button
                wire:click="confirmarCierrePlan"
                class="plan-btn plan-btn--danger"
                @if(empty($motivoCierre)) disabled @endif
            >
                <x-heroicon-o-x-circle class="icon-13" aria-hidden="true"/>
                Confirmar cierre
            </button>
        </div>
    </div>
</div>
@endif
```

Actualiza el botón de cierre en la banda de contexto para abrir el modal:

```blade
{{-- Reemplaza el botón "Cerrar plan" existente --}}
@if($this->plan?->estado === 'activo')
<button wire:click="abrirModalCierre" class="plan-btn">
    <x-heroicon-o-x-circle class="icon-13" aria-hidden="true"/>
    Cerrar plan
</button>
@endif
```

### CSS adicional en `app-operativo.css`

```css
/* ============================================================
   INDICADORES DE OBJETIVOS
   ============================================================ */

.plan-indicador {
    margin-top: 8px;
    padding: 8px 10px;
    background: var(--color-surface-alt);
    border-radius: 6px;
    border-left: 2px solid var(--color-border-secondary);
}

.plan-indicador--esp {
    margin-left: 8px;
    border-left-color: var(--color-primary, #2563eb);
}

.plan-indicador-desc {
    font-size: 11px;
    color: var(--color-text-secondary);
    margin-bottom: 6px;
    font-style: italic;
}

.plan-indicador-select {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}

.plan-indicador-opcion {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 11px;
    padding: 3px 8px;
    border-radius: 99px;
    border: 1px solid var(--color-border);
    cursor: pointer;
    color: var(--color-text-secondary);
    background: var(--color-surface);
    transition: all .1s;
}

.plan-indicador-opcion input[type="radio"] {
    display: none;
}

.plan-indicador-opcion:hover {
    background: var(--color-surface-alt);
    color: var(--color-text-primary);
}

.plan-indicador-opcion--activa {
    background: var(--color-text-primary);
    color: var(--color-surface);
    border-color: var(--color-text-primary);
}

.plan-obj-area {
    font-size: 10px;
    padding: 1px 6px;
    border-radius: 3px;
    background: #E6F1FB;
    color: #0C447C;
    margin-left: 4px;
    font-family: var(--font-mono);
}

.plan-obj-esp-texto {
    font-size: 12px;
    color: var(--color-text-secondary);
}

/* ============================================================
   MODAL DE CIERRE
   ============================================================ */

.plan-modal--cierre {
    width: 440px;
}

.plan-btn--danger {
    background: var(--color-danger, #dc2626);
    color: #fff;
    border-color: var(--color-danger, #dc2626);
}

.plan-btn--danger:hover { opacity: .85; }
.plan-btn--danger:disabled { opacity: .4; }

.plan-aviso-cierre {
    display: flex;
    align-items: flex-start;
    gap: 6px;
    font-size: 12px;
    color: #92400e;
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-radius: 6px;
    padding: 8px 10px;
    line-height: 1.5;
}
```

---

## Tests funcionales

Crea `Modules/Intervencion/tests/Feature/ObjetivosIndicadoresTest.php`:

```php
<?php

namespace Modules\Intervencion\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Intervencion\Models\ObjetivoCatalogo;
use Modules\Intervencion\Models\IndicadorCatalogo;
use Modules\Intervencion\Models\PlanObjetivo;
use Modules\Intervencion\Models\PlanObjetivoIndicador;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Intervencion\Models\TipoPlan;
use Modules\Intervencion\Models\TipoFicha;
use Tests\TestCase;

/**
 * Tests funcionales de objetivos con indicadores.
 * Nomenclatura: TF-OI-XX
 */
class ObjetivosIndicadoresTest extends TestCase
{
    use RefreshDatabase;

    private function crearPlan(): PlanDeIntervencion
    {
        $this->seed(\Modules\Intervencion\Database\Seeders\TipoPlanSeeder::class);
        return PlanDeIntervencion::factory()->create([
            'tipo_plan_id' => TipoPlan::first()->id,
        ]);
    }

    // TF-OI-01: Se puede crear un indicador de catálogo para un objetivo
    public function test_crear_indicador_catalogo(): void
    {
        $tipo = TipoPlan::factory()->create();
        $objetivo = ObjetivoCatalogo::create([
            'tipo_plan_id' => $tipo->id,
            'nivel'        => 'general',
            'texto'        => 'Mejorar la situación económica',
            'orden'        => 1,
        ]);

        $indicador = IndicadorCatalogo::create([
            'objetivo_catalogo_id' => $objetivo->id,
            'descripcion'          => 'El ciudadano ha accedido a prestaciones económicas',
            'tipo_valoracion'      => 'conseguido_proceso_no',
        ]);

        $this->assertEquals($objetivo->id, $indicador->objetivoCatalogo->id);
        $this->assertEquals(
            ['conseguido', 'en_proceso', 'no_conseguido'],
            $indicador->valoresPosibles()
        );
    }

    // TF-OI-02: Los valores posibles son correctos para cada tipo
    public function test_valores_posibles_por_tipo(): void
    {
        $this->assertEquals(
            ['conseguido', 'en_proceso', 'no_conseguido'],
            array_keys(IndicadorCatalogo::etiquetasValoración('conseguido_proceso_no'))
        );
        $this->assertEquals(
            ['favorable', 'se_mantiene', 'desfavorable'],
            array_keys(IndicadorCatalogo::etiquetasValoración('favorable_mantiene_desfavorable'))
        );
        $this->assertEquals(
            ['si', 'no'],
            array_keys(IndicadorCatalogo::etiquetasValoración('si_no'))
        );
    }

    // TF-OI-03: instanciarIndicador crea el indicador del plan desde el catálogo
    public function test_instanciar_indicador_desde_catalogo(): void
    {
        $plan = $this->crearPlan();
        $tipo = TipoPlan::first();

        $objCatalogo = ObjetivoCatalogo::create([
            'tipo_plan_id' => $tipo->id,
            'nivel'        => 'general',
            'texto'        => 'Objetivo de prueba',
            'orden'        => 1,
        ]);
        $indCatalogo = IndicadorCatalogo::create([
            'objetivo_catalogo_id' => $objCatalogo->id,
            'descripcion'          => 'Indicador de prueba',
            'tipo_valoracion'      => 'si_no',
        ]);

        $planObj = PlanObjetivo::create([
            'plan_id'              => $plan->id,
            'objetivo_catalogo_id' => $objCatalogo->id,
            'nivel'                => 'general',
            'texto'                => $objCatalogo->texto,
            'estado'               => 'pendiente',
            'orden'                => 1,
        ]);

        $indicador = $planObj->instanciarIndicador();

        $this->assertEquals($indCatalogo->id, $indicador->indicador_catalogo_id);
        $this->assertEquals('si_no', $indicador->tipo_valoracion);
        $this->assertNull($indicador->valoracion_actual);
    }

    // TF-OI-04: instanciarIndicador funciona para objetivos ex-novo
    public function test_instanciar_indicador_exnovo(): void
    {
        $plan = $this->crearPlan();

        $planObj = PlanObjetivo::create([
            'plan_id'  => $plan->id,
            'nivel'    => 'especifico',
            'texto'    => 'Objetivo ex-novo del TSR',
            'estado'   => 'pendiente',
            'orden'    => 1,
        ]);

        $indicador = $planObj->instanciarIndicador(
            'Indicador creado por el profesional',
            'favorable_mantiene_desfavorable'
        );

        $this->assertNull($indicador->indicador_catalogo_id);
        $this->assertEquals('Indicador creado por el profesional', $indicador->descripcion);
        $this->assertEquals('favorable_mantiene_desfavorable', $indicador->tipo_valoracion);
    }

    // TF-OI-05: registrarValoracion guarda el valor correcto
    public function test_registrar_valoracion(): void
    {
        $plan = $this->crearPlan();

        $planObj = PlanObjetivo::create([
            'plan_id' => $plan->id, 'nivel' => 'general',
            'texto' => 'Objetivo', 'estado' => 'pendiente', 'orden' => 1,
        ]);

        $indicador = PlanObjetivoIndicador::create([
            'plan_objetivo_id' => $planObj->id,
            'descripcion'      => 'Test',
            'tipo_valoracion'  => 'conseguido_proceso_no',
        ]);

        $indicador->registrarValoracion('en_proceso');

        $this->assertEquals('en_proceso', $indicador->fresh()->valoracion_actual);
        $this->assertNotNull($indicador->fresh()->fecha_valoracion);
    }

    // TF-OI-06: registrarValoracion rechaza valor inválido
    public function test_valoracion_invalida_lanza_excepcion(): void
    {
        $plan = $this->crearPlan();

        $planObj = PlanObjetivo::create([
            'plan_id' => $plan->id, 'nivel' => 'general',
            'texto' => 'Objetivo', 'estado' => 'pendiente', 'orden' => 1,
        ]);

        $indicador = PlanObjetivoIndicador::create([
            'plan_objetivo_id' => $planObj->id,
            'descripcion'      => 'Test',
            'tipo_valoracion'  => 'si_no',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $indicador->registrarValoracion('favorable'); // no es válido para si_no
    }

    // TF-OI-07: estaValorado devuelve false/true correctamente
    public function test_esta_valorado(): void
    {
        $plan = $this->crearPlan();

        $planObj = PlanObjetivo::create([
            'plan_id' => $plan->id, 'nivel' => 'general',
            'texto' => 'Objetivo', 'estado' => 'pendiente', 'orden' => 1,
        ]);

        $indicador = PlanObjetivoIndicador::create([
            'plan_objetivo_id' => $planObj->id,
            'descripcion'      => 'Test',
            'tipo_valoracion'  => 'si_no',
        ]);

        $this->assertFalse($indicador->estaValorado());
        $indicador->registrarValoracion('si');
        $this->assertTrue($indicador->fresh()->estaValorado());
    }

    // TF-OI-08: Un objetivo específico se puede vincular a un tipo de ficha
    public function test_objetivo_especifico_con_tipo_ficha(): void
    {
        $tipo = TipoPlan::factory()->create();
        $tipoFicha = TipoFicha::factory()->create(['nombre' => 'Situación de vivienda']);

        $objetivo = ObjetivoCatalogo::create([
            'tipo_plan_id' => $tipo->id,
            'tipo_ficha_id' => $tipoFicha->id,
            'nivel'         => 'especifico',
            'texto'         => 'Mejorar las condiciones de habitabilidad',
            'orden'         => 1,
        ]);

        $this->assertEquals('Situación de vivienda', $objetivo->tipoFicha->nombre);
    }
}
```

Crea `Modules/Intervencion/tests/Feature/Livewire/CierrePlanTest.php`:

```php
<?php

namespace Modules\Intervencion\Tests\Feature\Livewire;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Intervencion\Http\Livewire\PlanPage;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Intervencion\Models\TipoPlan;
use App\Models\User;
use Tests\TestCase;

/**
 * Tests funcionales del cierre del plan en PlanPage.
 * Nomenclatura: TF-CP-XX
 */
class CierrePlanTest extends TestCase
{
    use RefreshDatabase;

    private function montarPlanActivo(): array
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->seed(\Modules\Intervencion\Database\Seeders\TipoPlanSeeder::class);

        $plan = PlanDeIntervencion::factory()->create([
            'estado'                     => 'activo',
            'tipo_plan_id'               => TipoPlan::first()->id,
            'profesional_responsable_id' => $user->id,
        ]);

        return [$user, $plan];
    }

    // TF-CP-01: abrirModalCierre abre el modal
    public function test_abrir_modal_cierre(): void
    {
        [, $plan] = $this->montarPlanActivo();

        Livewire::test(PlanPage::class, ['plan' => $plan])
            ->call('abrirModalCierre')
            ->assertSet('modalCierreAbierto', true);
    }

    // TF-CP-02: cerrarModalCierre cierra el modal
    public function test_cerrar_modal_cierre(): void
    {
        [, $plan] = $this->montarPlanActivo();

        Livewire::test(PlanPage::class, ['plan' => $plan])
            ->call('abrirModalCierre')
            ->call('cerrarModalCierre')
            ->assertSet('modalCierreAbierto', false);
    }

    // TF-CP-03: confirmarCierrePlan sin motivo no cierra el plan
    public function test_cierre_sin_motivo_no_cierra(): void
    {
        [, $plan] = $this->montarPlanActivo();

        Livewire::test(PlanPage::class, ['plan' => $plan])
            ->call('abrirModalCierre')
            ->set('motivoCierre', '')
            ->call('confirmarCierrePlan');

        $this->assertEquals('activo', $plan->fresh()->estado);
    }

    // TF-CP-04: confirmarCierrePlan con motivo válido cierra el plan
    public function test_cierre_con_motivo_valido(): void
    {
        [, $plan] = $this->montarPlanActivo();

        Livewire::test(PlanPage::class, ['plan' => $plan])
            ->call('abrirModalCierre')
            ->set('motivoCierre', 'consecucion_objetivos')
            ->call('confirmarCierrePlan');

        $plan->refresh();
        $this->assertEquals('cerrado', $plan->estado);
        $this->assertEquals('consecucion_objetivos', $plan->motivo_cierre);
        $this->assertNotNull($plan->fecha_cierre);
    }

    // TF-CP-05: el cierre registra el cambio en plan_cambios
    public function test_cierre_registra_en_historial(): void
    {
        [, $plan] = $this->montarPlanActivo();

        Livewire::test(PlanPage::class, ['plan' => $plan])
            ->call('abrirModalCierre')
            ->set('motivoCierre', 'fallecimiento')
            ->call('confirmarCierrePlan');

        $this->assertDatabaseHas('plan_cambios', [
            'plan_id' => $plan->id,
        ]);
    }

    // TF-CP-06: motivosCierre contiene los 6 motivos
    public function test_motivos_cierre_completos(): void
    {
        [, $plan] = $this->montarPlanActivo();

        $componente = Livewire::test(PlanPage::class, ['plan' => $plan]);
        $motivos = $componente->get('motivosCierre');

        $this->assertCount(6, $motivos);
        $this->assertArrayHasKey('negativa_firma', $motivos);
        $this->assertArrayHasKey('fallecimiento', $motivos);
    }

    // TF-CP-07: plan cerrado no permite guardar diagnóstico
    public function test_plan_cerrado_readonly(): void
    {
        [, $plan] = $this->montarPlanActivo();
        $plan->update(['estado' => 'cerrado']);

        // El método de guardado debe hacer nada o lanzar error de autorización
        // dependiendo de la implementación; verificamos que el estado no cambia
        $textoOriginal = $plan->diagnostico_social;

        Livewire::test(PlanPage::class, ['plan' => $plan])
            ->set('diagnosticoTexto', 'Texto que no debe guardarse')
            ->call('guardarDiagnostico');

        $this->assertEquals($textoOriginal, $plan->fresh()->diagnostico_social);
    }
}
```

---

## Checklist de verificación

- [ ] `php artisan migrate` — 5 migraciones ejecutadas sin errores
- [ ] `php artisan test --filter=ObjetivosIndicadoresTest` — 8 tests en verde
- [ ] `php artisan test --filter=CierrePlanTest` — 7 tests en verde
- [ ] Tests anteriores TF-PLAN, TF-PP sin regresiones
- [ ] La página de gestión de objetivos en Filament muestra columna de área e indicador
- [ ] Al crear un objetivo en backoffice se crea también su indicador
- [ ] Los botones de valoración aparecen en la sección de objetivos de PlanPage
- [ ] Seleccionar un valor lo guarda inmediatamente (sin modal de motivo)
- [ ] El modal de cierre muestra los 6 motivos
- [ ] El cierre sin motivo deja el botón deshabilitado
- [ ] El aviso especial aparece para `negativa_firma` e `imposibilidad_localizacion`
- [ ] El plan cerrado muestra todo en modo solo lectura
- [ ] `docs/modulo-intervencion.md` actualizado (instrucciones en archivo separado)
- [ ] Commit: `feat(intervencion): objetivos con indicadores, valoración y cierre del plan`
