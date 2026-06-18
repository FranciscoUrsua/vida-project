# Instrucciones CLI — Plan de Intervención: modelo completo, backoffice y PDF

## Contexto

`planes_intervencion`, `firmas_plan`, `revisiones_plan` y `seguimientos_plan`
ya existen con sus modelos y tests (grupos TF-INT-A a D). Esta tarea añade
todo lo que falta para el plan completo:

1. Tabla `tipos_plan` — catálogo configurable desde backoffice
2. Tablas de contenido del plan: objetivos, actuaciones (Ayuntamiento y ciudadano),
   participantes, compromisos ciudadano
3. Columnas adicionales en `planes_intervencion` (diagnóstico, UC, tipo_plan_id,
   periodicidad seguimiento)
4. Backoffice Filament: `TipoPlanResource` + `ObjetivoCatalogoResource`
5. Generación de PDF para impresión y firma
6. Actualización de `docs/modulo-intervencion.md` secciones 5, 9
7. Tests funcionales completos

---

## Parte 1 — Actualizar `docs/modulo-intervencion.md`

### 1a — Sección 5: Plan de Intervención

Reemplaza el contenido de la sección `### 5.2 Atributos` con:

```markdown
### 5.2 Atributos del plan

```

planes_intervencion (ampliada)
- id
- historia_id               (FK — Historia Social del ciudadano titular)
- unidad_convivencia_id     (FK nullable — si el plan es familiar, no individual)
- tipo_plan_id              (FK a tipos_plan — define estructura y catálogos disponibles)
- profesional_responsable_id (FK a users)
- plan_asp_id               (FK nullable, self-ref — para planes especializados)
- estado                    (enum: borrador / activo / en_revision / cerrado)
- diagnostico_social        (text nullable — resumen del diagnóstico en el momento del plan)
- periodicidad_seguimiento  (enum: mensual / bimestral / trimestral / semestral / anual)
- fecha_inicio              (date)
- fecha_firma               (date nullable)
- fecha_cierre              (date nullable)
- motivo_cierre             (enum nullable: objetivos_cumplidos / abandono / derivacion / fallecimiento / otros)
- version                   (integer default 1)
- created_at, updated_at

```

### 5.3 Tipos de plan (backoffice)

```

tipos_plan
- id
- slug                      (string único no editable — identificador del código)
- nombre                    (string — nombre visible en UI)
- ambito                    (enum: asp / especializado)
- descripcion               (text nullable)
- activo                    (boolean default true)
- eliminable                (boolean — false para tipos del seeder)
- timestamps

```

El catálogo de objetivos disponibles, el catálogo de actuaciones sugeridas y la
plantilla de PDF están vinculados al `tipo_plan`. Ver secciones 5.4 y 5.5.

### 5.4 Objetivos del plan

Los objetivos tienen dos niveles: generales (propósito amplio de la intervención)
y específicos (resultados concretos esperados). Ambos niveles tienen un catálogo
configurable en backoffice por tipo de plan, pero el TSR puede redactarlos
libremente en texto adicional.

```

objetivos_catalogo                         ← backoffice, vinculado a tipo_plan
- id
- tipo_plan_id              (FK)
- nivel                     (enum: general / especifico)
- objetivo_general_id       (FK nullable a sí mismo — para específicos, indica su general)
- texto                     (string — texto del objetivo)
- activo                    (boolean)
- orden                     (integer)
- timestamps

plan_objetivos                             ← datos reales del plan
- id
- plan_id                   (FK)
- objetivo_catalogo_id      (FK nullable — si viene del catálogo)
- nivel                     (enum: general / especifico)
- objetivo_general_id       (FK nullable a plan_objetivos — para específicos)
- texto                     (text — texto final, puede diferir del catálogo)
- estado                    (enum: pendiente / en_proceso / conseguido / abandonado)
- orden                     (integer)
- timestamps

```

### 5.5 Actuaciones

**Actuaciones del Ayuntamiento:** siempre vinculadas a una prestación del catálogo.
Sin prestación, no existe la actuación. Esta es una regla de negocio en código.

**Actuaciones del ciudadano:** texto libre como campo primario. Pueden vincularse
opcionalmente a una prestación cuando la actuación corresponde a participación en
un recurso del catálogo (asistencia a talleres, etc.).

```

plan_actuaciones_ayuntamiento
- id
- plan_id                   (FK)
- prestacion_id             (FK obligatoria — regla de negocio en código)
- descripcion_especifica    (text nullable — "asistirá a 4 sesiones del taller de...")
- responsable_id            (FK nullable a users — profesional responsable de esta actuación)
- estado                    (enum: pendiente / en_curso / completada / cancelada)
- fecha_inicio_prevista     (date nullable)
- fecha_fin_prevista        (date nullable)
- fecha_fin_real            (date nullable)
- orden                     (integer)
- timestamps

plan_actuaciones_ciudadano
- id
- plan_id                   (FK)
- descripcion               (text — texto libre, campo primario)
- prestacion_id             (FK nullable — si corresponde a participación en prestación)
- estado                    (enum: pendiente / en_curso / completada / cancelada)
- fecha_inicio_prevista     (date nullable)
- fecha_fin_prevista        (date nullable)
- fecha_fin_real            (date nullable)
- orden                     (integer)
- timestamps

```

### 5.6 Participantes del plan

Además del profesional responsable (campo en `planes_intervencion`), otros
profesionales pueden participar formalmente en el plan.

```

plan_participantes
- id
- plan_id                   (FK)
- user_id                   (FK)
- rol_en_plan               (string — "Educador social", "Psicólogo/a", "TSR ASE"...)
- servicio_id               (FK nullable a servicios — si viene de atención especializada)
- fecha_inicio              (date)
- fecha_fin                 (date nullable)
- timestamps

```

### 5.7 Historial de cambios en el plan

Todo cambio al contenido de un plan activo queda registrado con fecha,
autor y motivo. Los cambios discrecionales del TSR y los cambios derivados
de un seguimiento usan la misma tabla pero se distinguen por `origen`.

```

plan_cambios
- id
- plan_id                   (FK)
- version                   (integer — versión del plan en el momento del cambio)
- profesional_id            (FK)
- origen                    (enum: discrecional / seguimiento)
- seguimiento_id            (FK nullable a seguimientos_plan)
- motivo                    (text)
- snapshot                  (JSON — estado completo del plan antes del cambio,
                             para reconstrucción histórica)
- created_at

```

> **Nota sobre `RevisionPlan`:** la tabla `revisiones_plan` ya existente cubre
> los cambios de versión formales (con nueva firma). `plan_cambios` es el
> historial completo de cualquier modificación, incluyendo las menores que no
> requieren nueva firma (añadir/quitar un participante, ajustar una actuación).

### 5.8 Firma y PDF

El ciclo de firma es: el TSR genera el PDF desde el sistema → lo imprime → ambas
partes firman en papel → el TSR escanea el documento firmado y lo adjunta a la
Historia Social usando el módulo de documentos.

```

firmas_plan (ya existe — añadir columna)
- documento_firmado_id (FK nullable a documentos — el PDF escaneado con firma)

```

La generación del PDF se realiza con la librería existente en el proyecto.
El PDF se genera en el momento en que el TSR lo solicita desde la pantalla del
plan; no se genera automáticamente.
```

### 1b — Sección 9: Decisiones pendientes

Reemplaza el ítem "Modelo de objetivos del plan — evolución futura" por:

```markdown
- **Pool de actuaciones del ciudadano**: actualmente texto libre. En el futuro
  se prevé un catálogo configurable de actuaciones estandarizadas, similar al
  catálogo de objetivos, para facilitar la comparación estadística entre planes.
  Pendiente hasta tener suficientes planes reales que justifiquen la estandarización.
- **Firma electrónica y dispositivos de firma**: diferido a versiones futuras de
  VIDA360. El flujo actual es: PDF impreso → firma manuscrita → escaneo.
```

---

## Parte 2 — Migraciones

### Migración 1: Crear `tipos_plan`

Crea `Modules/Intervencion/database/migrations/2026_06_16_000010_create_tipos_plan_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_plan', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('nombre');
            $table->enum('ambito', ['asp', 'especializado'])->default('asp');
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->boolean('eliminable')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_plan');
    }
};
```

### Migración 2: Ampliar `planes_intervencion`

Crea `Modules/Intervencion/database/migrations/2026_06_16_000011_expand_planes_intervencion_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planes_intervencion', function (Blueprint $table) {
            // tipo_plan_id — nullable para no romper datos existentes
            $table->foreignId('tipo_plan_id')
                  ->nullable()
                  ->after('historia_id')
                  ->constrained('tipos_plan')
                  ->nullOnDelete();

            // unidad_convivencia_id — ya definido en sesión anterior pero
            // puede no haberse creado; usar addColumnIfNotExists pattern
            if (! Schema::hasColumn('planes_intervencion', 'unidad_convivencia_id')) {
                $table->foreignId('unidad_convivencia_id')
                      ->nullable()
                      ->after('tipo_plan_id')
                      ->constrained('unidades_convivencia')
                      ->nullOnDelete();
            }

            $table->text('diagnostico_social')->nullable()->after('unidad_convivencia_id');
            $table->enum('periodicidad_seguimiento', [
                'mensual', 'bimestral', 'trimestral', 'semestral', 'anual'
            ])->default('trimestral')->after('diagnostico_social');
        });
    }

    public function down(): void
    {
        Schema::table('planes_intervencion', function (Blueprint $table) {
            $table->dropForeign(['tipo_plan_id']);
            $table->dropColumn(['tipo_plan_id', 'diagnostico_social', 'periodicidad_seguimiento']);
            if (Schema::hasColumn('planes_intervencion', 'unidad_convivencia_id')) {
                $table->dropForeign(['unidad_convivencia_id']);
                $table->dropColumn('unidad_convivencia_id');
            }
        });
    }
};
```

### Migración 3: Tablas de contenido del plan

Crea `Modules/Intervencion/database/migrations/2026_06_16_000012_create_plan_content_tables.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Catálogo de objetivos (backoffice)
        Schema::create('objetivos_catalogo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tipo_plan_id')->constrained('tipos_plan')->cascadeOnDelete();
            $table->enum('nivel', ['general', 'especifico']);
            $table->foreignId('objetivo_general_id')
                  ->nullable()
                  ->constrained('objetivos_catalogo')
                  ->nullOnDelete();
            $table->text('texto');
            $table->boolean('activo')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();

            $table->index(['tipo_plan_id', 'nivel', 'activo']);
        });

        // Objetivos reales del plan
        Schema::create('plan_objetivos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('planes_intervencion')->cascadeOnDelete();
            $table->foreignId('objetivo_catalogo_id')
                  ->nullable()
                  ->constrained('objetivos_catalogo')
                  ->nullOnDelete();
            $table->enum('nivel', ['general', 'especifico']);
            $table->foreignId('objetivo_general_id')
                  ->nullable()
                  ->constrained('plan_objetivos')
                  ->nullOnDelete();
            $table->text('texto');
            $table->enum('estado', ['pendiente', 'en_proceso', 'conseguido', 'abandonado'])
                  ->default('pendiente');
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();

            $table->index(['plan_id', 'nivel']);
        });

        // Actuaciones del Ayuntamiento
        Schema::create('plan_actuaciones_ayuntamiento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('planes_intervencion')->cascadeOnDelete();
            $table->foreignId('prestacion_id')->constrained('prestaciones'); // FK obligatoria
            $table->text('descripcion_especifica')->nullable();
            $table->foreignId('responsable_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->enum('estado', ['pendiente', 'en_curso', 'completada', 'cancelada'])
                  ->default('pendiente');
            $table->date('fecha_inicio_prevista')->nullable();
            $table->date('fecha_fin_prevista')->nullable();
            $table->date('fecha_fin_real')->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();

            $table->index('plan_id');
        });

        // Actuaciones del ciudadano
        Schema::create('plan_actuaciones_ciudadano', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('planes_intervencion')->cascadeOnDelete();
            $table->text('descripcion');
            $table->foreignId('prestacion_id')
                  ->nullable()
                  ->constrained('prestaciones')
                  ->nullOnDelete();
            $table->enum('estado', ['pendiente', 'en_curso', 'completada', 'cancelada'])
                  ->default('pendiente');
            $table->date('fecha_inicio_prevista')->nullable();
            $table->date('fecha_fin_prevista')->nullable();
            $table->date('fecha_fin_real')->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();

            $table->index('plan_id');
        });

        // Participantes del plan
        Schema::create('plan_participantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('planes_intervencion')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->string('rol_en_plan');
            $table->foreignId('servicio_id')
                  ->nullable()
                  ->constrained('servicios')
                  ->nullOnDelete();
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->timestamps();

            $table->index(['plan_id', 'user_id']);
        });

        // Historial de cambios
        Schema::create('plan_cambios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('planes_intervencion')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->foreignId('profesional_id')->constrained('users');
            $table->enum('origen', ['discrecional', 'seguimiento']);
            $table->foreignId('seguimiento_id')
                  ->nullable()
                  ->constrained('seguimientos_plan')
                  ->nullOnDelete();
            $table->text('motivo');
            $table->json('snapshot');
            $table->timestamp('created_at');

            $table->index(['plan_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_cambios');
        Schema::dropIfExists('plan_participantes');
        Schema::dropIfExists('plan_actuaciones_ciudadano');
        Schema::dropIfExists('plan_actuaciones_ayuntamiento');
        Schema::dropIfExists('plan_objetivos');
        Schema::dropIfExists('objetivos_catalogo');
    }
};
```

### Migración 4: Añadir `documento_firmado_id` a `firmas_plan`

Crea `Modules/Intervencion/database/migrations/2026_06_16_000013_add_documento_to_firmas_plan.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('firmas_plan', function (Blueprint $table) {
            $table->foreignId('documento_firmado_id')
                  ->nullable()
                  ->after('fecha_firma')
                  ->constrained('documentos')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('firmas_plan', function (Blueprint $table) {
            $table->dropForeign(['documento_firmado_id']);
            $table->dropColumn('documento_firmado_id');
        });
    }
};
```

> Si la tabla `documentos` no existe aún en el módulo Documentos, declara la FK
> sin constraint: `$table->unsignedBigInteger('documento_firmado_id')->nullable()`
> y añade un comentario `// TODO: añadir constraint cuando exista la tabla documentos`.

---

## Parte 3 — Seeder de tipos de plan

Crea `Modules/Intervencion/database/seeders/TipoPlanSeeder.php`:

```php
<?php

namespace Modules\Intervencion\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Intervencion\Models\TipoPlan;

class TipoPlanSeeder extends Seeder
{
    public function run(): void
    {
        $tipos = [
            [
                'slug'        => 'asp_general',
                'nombre'      => 'Plan de Intervención Social (ASP)',
                'ambito'      => 'asp',
                'descripcion' => 'Plan de intervención integral gestionado por el TSR de Atención Social Primaria.',
                'activo'      => true,
                'eliminable'  => false,
            ],
            [
                'slug'        => 'esp_familia_infancia',
                'nombre'      => 'Plan de Atención a Familia e Infancia',
                'ambito'      => 'especializado',
                'descripcion' => 'Plan especializado para intervención con familias y menores.',
                'activo'      => true,
                'eliminable'  => false,
            ],
            [
                'slug'        => 'esp_violencia_genero',
                'nombre'      => 'Plan de Atención a Víctimas de Violencia de Género',
                'ambito'      => 'especializado',
                'descripcion' => 'Plan especializado para intervención con víctimas de violencia de género.',
                'activo'      => true,
                'eliminable'  => false,
            ],
            [
                'slug'        => 'esp_mayores',
                'nombre'      => 'Plan de Atención a Personas Mayores',
                'ambito'      => 'especializado',
                'descripcion' => 'Plan especializado para atención a personas mayores en situación de vulnerabilidad.',
                'activo'      => true,
                'eliminable'  => false,
            ],
            [
                'slug'        => 'esp_inclusion',
                'nombre'      => 'Plan de Inclusión Social',
                'ambito'      => 'especializado',
                'descripcion' => 'Plan especializado para intervención en situaciones de exclusión social.',
                'activo'      => true,
                'eliminable'  => false,
            ],
        ];

        foreach ($tipos as $datos) {
            TipoPlan::updateOrCreate(['slug' => $datos['slug']], $datos);
        }
    }
}
```

---

## Parte 4 — Modelos

### Modelo `TipoPlan`

Crea `Modules/Intervencion/app/Models/TipoPlan.php`:

```php
<?php

namespace Modules\Intervencion\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class TipoPlan extends Model
{
    protected $table = 'tipos_plan';

    protected $fillable = [
        'slug', 'nombre', 'ambito', 'descripcion', 'activo', 'eliminable',
    ];

    protected $casts = [
        'activo'     => 'boolean',
        'eliminable' => 'boolean',
    ];

    // --- Relaciones ---

    public function planes(): HasMany
    {
        return $this->hasMany(PlanDeIntervencion::class, 'tipo_plan_id');
    }

    public function objetivosCatalogo(): HasMany
    {
        return $this->hasMany(ObjetivoCatalogo::class, 'tipo_plan_id');
    }

    public function objetivosGenerales(): HasMany
    {
        return $this->objetivosCatalogo()
            ->where('nivel', 'general')
            ->where('activo', true)
            ->orderBy('orden');
    }

    // --- Scopes ---

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    public function scopeAsp(Builder $query): Builder
    {
        return $query->where('ambito', 'asp');
    }

    public function scopeEspecializados(Builder $query): Builder
    {
        return $query->where('ambito', 'especializado');
    }

    // --- Negocio ---

    protected static function booted(): void
    {
        static::deleting(function (self $tipo) {
            if (! $tipo->eliminable) {
                throw new \LogicException(
                    "El tipo de plan '{$tipo->slug}' es del sistema y no puede eliminarse."
                );
            }
        });
    }

    public static function opcionesParaSelect(): array
    {
        return static::activos()->orderBy('nombre')->pluck('nombre', 'id')->toArray();
    }
}
```

### Modelo `ObjetivoCatalogo`

Crea `Modules/Intervencion/app/Models/ObjetivoCatalogo.php`:

```php
<?php

namespace Modules\Intervencion\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ObjetivoCatalogo extends Model
{
    protected $table = 'objetivos_catalogo';

    protected $fillable = [
        'tipo_plan_id', 'nivel', 'objetivo_general_id', 'texto', 'activo', 'orden',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'orden'  => 'integer',
    ];

    public function tipoPlan(): BelongsTo
    {
        return $this->belongsTo(TipoPlan::class, 'tipo_plan_id');
    }

    public function objetivoGeneral(): BelongsTo
    {
        return $this->belongsTo(self::class, 'objetivo_general_id');
    }

    public function objetivosEspecificos(): HasMany
    {
        return $this->hasMany(self::class, 'objetivo_general_id')
            ->where('activo', true)
            ->orderBy('orden');
    }
}
```

### Modelo `PlanObjetivo`

Crea `Modules/Intervencion/app/Models/PlanObjetivo.php`:

```php
<?php

namespace Modules\Intervencion\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanObjetivo extends Model
{
    protected $table = 'plan_objetivos';

    protected $fillable = [
        'plan_id', 'objetivo_catalogo_id', 'nivel', 'objetivo_general_id',
        'texto', 'estado', 'orden',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlanDeIntervencion::class, 'plan_id');
    }

    public function objetivoCatalogo(): BelongsTo
    {
        return $this->belongsTo(ObjetivoCatalogo::class, 'objetivo_catalogo_id');
    }

    public function objetivoGeneral(): BelongsTo
    {
        return $this->belongsTo(self::class, 'objetivo_general_id');
    }

    public function objetivosEspecificos(): HasMany
    {
        return $this->hasMany(self::class, 'objetivo_general_id')->orderBy('orden');
    }
}
```

### Modelo `PlanActuacionAyuntamiento`

Crea `Modules/Intervencion/app/Models/PlanActuacionAyuntamiento.php`:

```php
<?php

namespace Modules\Intervencion\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Prestaciones\Models\Prestacion;
use App\Models\User;

class PlanActuacionAyuntamiento extends Model
{
    protected $table = 'plan_actuaciones_ayuntamiento';

    protected $fillable = [
        'plan_id', 'prestacion_id', 'descripcion_especifica', 'responsable_id',
        'estado', 'fecha_inicio_prevista', 'fecha_fin_prevista', 'fecha_fin_real', 'orden',
    ];

    protected $casts = [
        'fecha_inicio_prevista' => 'date',
        'fecha_fin_prevista'    => 'date',
        'fecha_fin_real'        => 'date',
    ];

    protected static function booted(): void
    {
        // Regla de negocio: toda actuación del Ayuntamiento debe tener prestación
        static::saving(function (self $actuacion) {
            if (empty($actuacion->prestacion_id)) {
                throw new \LogicException(
                    'Las actuaciones del Ayuntamiento deben estar vinculadas a una prestación del catálogo.'
                );
            }
        });
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlanDeIntervencion::class, 'plan_id');
    }

    public function prestacion(): BelongsTo
    {
        return $this->belongsTo(Prestacion::class, 'prestacion_id');
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }
}
```

### Modelo `PlanActuacionCiudadano`

Crea `Modules/Intervencion/app/Models/PlanActuacionCiudadano.php`:

```php
<?php

namespace Modules\Intervencion\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Prestaciones\Models\Prestacion;

class PlanActuacionCiudadano extends Model
{
    protected $table = 'plan_actuaciones_ciudadano';

    protected $fillable = [
        'plan_id', 'descripcion', 'prestacion_id', 'estado',
        'fecha_inicio_prevista', 'fecha_fin_prevista', 'fecha_fin_real', 'orden',
    ];

    protected $casts = [
        'fecha_inicio_prevista' => 'date',
        'fecha_fin_prevista'    => 'date',
        'fecha_fin_real'        => 'date',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlanDeIntervencion::class, 'plan_id');
    }

    public function prestacion(): BelongsTo
    {
        return $this->belongsTo(Prestacion::class, 'prestacion_id');
    }
}
```

### Modelo `PlanParticipante`

Crea `Modules/Intervencion/app/Models/PlanParticipante.php`:

```php
<?php

namespace Modules\Intervencion\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;
use Modules\Centro\Models\Servicio;

class PlanParticipante extends Model
{
    protected $table = 'plan_participantes';

    protected $fillable = [
        'plan_id', 'user_id', 'rol_en_plan', 'servicio_id', 'fecha_inicio', 'fecha_fin',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin'    => 'date',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlanDeIntervencion::class, 'plan_id');
    }

    public function profesional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class, 'servicio_id');
    }

    public function estaActivo(): bool
    {
        return $this->fecha_fin === null || $this->fecha_fin->isFuture();
    }
}
```

### Modelo `PlanCambio`

Crea `Modules/Intervencion/app/Models/PlanCambio.php`:

```php
<?php

namespace Modules\Intervencion\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class PlanCambio extends Model
{
    public $timestamps = false;

    protected $table = 'plan_cambios';

    protected $fillable = [
        'plan_id', 'version', 'profesional_id', 'origen',
        'seguimiento_id', 'motivo', 'snapshot', 'created_at',
    ];

    protected $casts = [
        'snapshot'   => 'array',
        'created_at' => 'datetime',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlanDeIntervencion::class, 'plan_id');
    }

    public function profesional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'profesional_id');
    }

    public function seguimiento(): BelongsTo
    {
        return $this->belongsTo(SeguimientoPlan::class, 'seguimiento_id');
    }
}
```

### Actualizar `PlanDeIntervencion` con nuevas relaciones y método `registrarCambio`

En `Modules/Intervencion/app/Models/PlanDeIntervencion.php`, añade:

```php
// Nuevas relaciones
public function tipoPlan(): BelongsTo
{
    return $this->belongsTo(TipoPlan::class, 'tipo_plan_id');
}

public function unidadConvivencia(): BelongsTo
{
    return $this->belongsTo(
        \Modules\Ciudadania\Models\UnidadConvivencia::class,
        'unidad_convivencia_id'
    );
}

public function objetivos(): HasMany
{
    return $this->hasMany(PlanObjetivo::class, 'plan_id')->orderBy('orden');
}

public function objetivosGenerales(): HasMany
{
    return $this->objetivos()->where('nivel', 'general');
}

public function actuacionesAyuntamiento(): HasMany
{
    return $this->hasMany(PlanActuacionAyuntamiento::class, 'plan_id')->orderBy('orden');
}

public function actuacionesCiudadano(): HasMany
{
    return $this->hasMany(PlanActuacionCiudadano::class, 'plan_id')->orderBy('orden');
}

public function participantes(): HasMany
{
    return $this->hasMany(PlanParticipante::class, 'plan_id');
}

public function participantesActivos(): HasMany
{
    return $this->participantes()->whereNull('fecha_fin');
}

public function cambios(): HasMany
{
    return $this->hasMany(PlanCambio::class, 'plan_id')->orderByDesc('created_at');
}

/**
 * Registra un cambio en el historial del plan con snapshot del estado actual.
 * Debe llamarse ANTES de aplicar los cambios para que el snapshot sea el estado previo.
 */
public function registrarCambio(
    int $profesionalId,
    string $motivo,
    string $origen = 'discrecional',
    ?int $seguimientoId = null
): PlanCambio {
    $snapshot = [
        'diagnostico_social'       => $this->diagnostico_social,
        'periodicidad_seguimiento' => $this->periodicidad_seguimiento,
        'objetivos'                => $this->objetivos()->with('objetivosEspecificos')->get()->toArray(),
        'actuaciones_ayuntamiento' => $this->actuacionesAyuntamiento()->with('prestacion')->get()->toArray(),
        'actuaciones_ciudadano'    => $this->actuacionesCiudadano()->get()->toArray(),
        'participantes'            => $this->participantesActivos()->with('profesional')->get()->toArray(),
    ];

    return $this->cambios()->create([
        'version'         => $this->version,
        'profesional_id'  => $profesionalId,
        'origen'          => $origen,
        'seguimiento_id'  => $seguimientoId,
        'motivo'          => $motivo,
        'snapshot'        => $snapshot,
        'created_at'      => now(),
    ]);
}
```

---

## Parte 5 — Backoffice Filament

### `TipoPlanResource`

Crea `Modules/Intervencion/app/Filament/Resources/TipoPlanResource.php`:

```php
<?php

namespace Modules\Intervencion\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Intervencion\Models\TipoPlan;

class TipoPlanResource extends Resource
{
    protected static ?string $model = TipoPlan::class;
    protected static ?string $navigationGroup = 'Catálogos';
    protected static ?string $navigationLabel = 'Tipos de plan';
    protected static ?string $modelLabel = 'Tipo de plan';
    protected static ?string $pluralModelLabel = 'Tipos de plan';
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identificación')->schema([
                Forms\Components\TextInput::make('slug')
                    ->label('Slug')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->alphaDash()
                    ->maxLength(60)
                    ->disabled(fn ($record) => $record !== null),

                Forms\Components\TextInput::make('nombre')
                    ->label('Nombre del plan')
                    ->required()
                    ->maxLength(120),

                Forms\Components\Select::make('ambito')
                    ->label('Ámbito')
                    ->options(['asp' => 'Atención Social Primaria', 'especializado' => 'Atención especializada'])
                    ->required(),

                Forms\Components\Textarea::make('descripcion')
                    ->label('Descripción')
                    ->rows(2)
                    ->nullable(),

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
                Tables\Columns\TextColumn::make('slug')->fontFamily('mono')->searchable(),
                Tables\Columns\TextColumn::make('nombre')->searchable(),
                Tables\Columns\BadgeColumn::make('ambito')
                    ->formatStateUsing(fn ($state) => $state === 'asp' ? 'ASP' : 'Especializado')
                    ->color(fn ($state) => $state === 'asp' ? 'primary' : 'warning'),
                Tables\Columns\IconColumn::make('activo')->boolean(),
                Tables\Columns\IconColumn::make('eliminable')
                    ->label('Del sistema')
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-pencil')
                    ->trueColor('gray')->falseColor('success'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('objetivos')
                    ->label('Objetivos')
                    ->icon('heroicon-o-list-bullet')
                    ->url(fn ($record) => static::getUrl('objetivos', ['record' => $record])),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn ($record) => $record->eliminable),
            ]);
    }

    public static function canDelete($record): bool
    {
        return $record->eliminable && (auth()->user()?->hasRole('adm_sistema') ?? false);
    }

    public static function getPages(): array
    {
        return [
            'index'    => \Filament\Resources\Pages\ListRecords::route('/'),
            'create'   => \Filament\Resources\Pages\CreateRecord::route('/create'),
            'edit'     => \Filament\Resources\Pages\EditRecord::route('/{record}/edit'),
            'objetivos' => TipoPlanResource\Pages\GestionarObjetivos::route('/{record}/objetivos'),
        ];
    }
}
```

### Página de gestión de objetivos del catálogo

Crea `Modules/Intervencion/app/Filament/Resources/TipoPlanResource/Pages/GestionarObjetivos.php`:

```php
<?php

namespace Modules\Intervencion\Filament\Resources\TipoPlanResource\Pages;

use Filament\Forms;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Modules\Intervencion\Filament\Resources\TipoPlanResource;
use Modules\Intervencion\Models\ObjetivoCatalogo;
use Modules\Intervencion\Models\TipoPlan;

class GestionarObjetivos extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = TipoPlanResource::class;
    protected static string $view = 'intervencion::filament.tipo-plan.gestionar-objetivos';

    public TipoPlan $record;

    public function getTitle(): string
    {
        return "Objetivos: {$this->record->nombre}";
    }

    protected function getTableQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return ObjetivoCatalogo::where('tipo_plan_id', $this->record->id)
            ->orderBy('nivel')
            ->orderBy('orden');
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\BadgeColumn::make('nivel')
                ->colors(['primary' => 'general', 'gray' => 'especifico']),
            Tables\Columns\TextColumn::make('objetivoGeneral.texto')
                ->label('Objetivo general')
                ->placeholder('—')
                ->limit(40),
            Tables\Columns\TextColumn::make('texto')->limit(60)->searchable(),
            Tables\Columns\TextColumn::make('orden')->sortable(),
            Tables\Columns\IconColumn::make('activo')->boolean(),
        ];
    }

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
                    Forms\Components\Select::make('objetivo_general_id')
                        ->label('Objetivo general al que pertenece')
                        ->options(fn () => ObjetivoCatalogo::where('tipo_plan_id', $this->record->id)
                            ->where('nivel', 'general')
                            ->pluck('texto', 'id'))
                        ->nullable()
                        ->visible(fn (Forms\Get $get) => $get('nivel') === 'especifico'),
                    Forms\Components\Textarea::make('texto')->required()->rows(2),
                    Forms\Components\TextInput::make('orden')->numeric()->default(0),
                    Forms\Components\Toggle::make('activo')->default(true),
                ])
                ->mutateFormDataUsing(fn ($data) => array_merge(
                    $data, ['tipo_plan_id' => $this->record->id]
                )),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            Tables\Actions\EditAction::make()
                ->form([
                    Forms\Components\Textarea::make('texto')->required()->rows(2),
                    Forms\Components\TextInput::make('orden')->numeric(),
                    Forms\Components\Toggle::make('activo'),
                ]),
            Tables\Actions\DeleteAction::make(),
        ];
    }
}
```

Crea la vista Blade asociada en
`Modules/Intervencion/resources/views/filament/tipo-plan/gestionar-objetivos.blade.php`:

```blade
<x-filament-panels::page>
    <div class="mb-4 text-sm text-gray-500">
        Gestiona los objetivos disponibles para este tipo de plan.
        Los objetivos generales agrupan objetivos específicos.
    </div>
    {{ $this->table }}
</x-filament-panels::page>
```

---

## Parte 6 — Generación de PDF

### Servicio `PlanPdfService`

Crea `Modules/Intervencion/app/Services/PlanPdfService.php`:

```php
<?php

namespace Modules\Intervencion\Services;

use Modules\Intervencion\Models\PlanDeIntervencion;

class PlanPdfService
{
    /**
     * Genera el PDF del plan listo para impresión y firma.
     * Devuelve el contenido del PDF como string binario.
     *
     * Usa la librería de PDF del proyecto (verificar cuál está instalada:
     * barryvdh/laravel-dompdf o knplabs/knp-snappy). Si ninguna está
     * disponible, instalar barryvdh/laravel-dompdf y documentarlo en
     * composer.json y SESSION.md.
     */
    public function generar(PlanDeIntervencion $plan): string
    {
        $plan->load([
            'tipoPlan',
            'historia.ciudadano',
            'unidadConvivencia.miembrosActivos.ciudadano',
            'objetivosGenerales.objetivosEspecificos',
            'actuacionesAyuntamiento.prestacion',
            'actuacionesAyuntamiento.responsable',
            'actuacionesCiudadano.prestacion',
            'participantesActivos.profesional',
            'profesionalResponsable',
        ]);

        $html = view('intervencion::pdf.plan', ['plan' => $plan])->render();

        // Adaptar según librería disponible:
        // Con dompdf:
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'defaultFont' => 'sans-serif',
                'isRemoteEnabled' => false,
            ]);
        return $pdf->output();

        // Con snappy:
        // return \Knp\Snappy\Pdf::getOutputFromHtml($html);
    }

    public function nombre(PlanDeIntervencion $plan): string
    {
        $ciudadanoId = $plan->historia->ciudadano_id ?? 'sin-id';
        return "plan_{$ciudadanoId}_v{$plan->version}_" . now()->format('Ymd') . '.pdf';
    }
}
```

### Vista Blade del PDF

Crea `Modules/Intervencion/resources/views/pdf/plan.blade.php`:

```blade
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: sans-serif; font-size: 10pt; color: #1a1a1a; line-height: 1.4; }
    .cabecera { border-bottom: 2px solid #2A5B8A; padding-bottom: 8px; margin-bottom: 16px; }
    .cabecera__titulo { font-size: 14pt; font-weight: bold; color: #2A5B8A; }
    .cabecera__subtitulo { font-size: 10pt; color: #555; margin-top: 2px; }
    .seccion { margin-bottom: 14px; }
    .seccion__titulo { font-size: 10pt; font-weight: bold; text-transform: uppercase;
                       letter-spacing: .05em; color: #2A5B8A; border-bottom: 1px solid #ccc;
                       padding-bottom: 3px; margin-bottom: 6px; }
    .seccion__contenido { font-size: 9.5pt; }
    table { width: 100%; border-collapse: collapse; font-size: 9pt; margin-top: 4px; }
    th { background: #f0f4f8; text-align: left; padding: 4px 6px; font-weight: 600; border: 1px solid #ddd; }
    td { padding: 4px 6px; border: 1px solid #ddd; vertical-align: top; }
    .firmas { margin-top: 32px; display: flex; gap: 40px; }
    .firma-bloque { flex: 1; border-top: 1px solid #333; padding-top: 6px; text-align: center; }
    .firma-bloque__nombre { font-size: 9pt; }
    .firma-bloque__fecha { font-size: 8pt; color: #666; margin-top: 2px; }
    .dato-fila { display: flex; gap: 8px; margin-bottom: 3px; }
    .dato-label { font-weight: 600; min-width: 140px; }
    .pie { margin-top: 20px; font-size: 8pt; color: #888; border-top: 1px solid #eee; padding-top: 6px; }
</style>
</head>
<body>

{{-- Cabecera --}}
<div class="cabecera">
    <div class="cabecera__titulo">{{ $plan->tipoPlan?->nombre ?? 'Plan de Intervención Social' }}</div>
    <div class="cabecera__subtitulo">
        Versión {{ $plan->version }} ·
        Fecha: {{ $plan->fecha_inicio?->format('d/m/Y') ?? now()->format('d/m/Y') }}
    </div>
</div>

{{-- Datos del ciudadano --}}
<div class="seccion">
    <div class="seccion__titulo">Datos de la persona</div>
    <div class="seccion__contenido">
        @php $ciudadano = $plan->historia->ciudadano @endphp
        <div class="dato-fila">
            <span class="dato-label">Nombre y apellidos:</span>
            <span>{{ $ciudadano->nombre_completo }}</span>
        </div>
        <div class="dato-fila">
            <span class="dato-label">Fecha de nacimiento:</span>
            <span>{{ $ciudadano->fecha_nacimiento?->format('d/m/Y') }}</span>
        </div>
        <div class="dato-fila">
            <span class="dato-label">Domicilio:</span>
            <span>{{ $ciudadano->domicilio }}</span>
        </div>
        @if($plan->unidadConvivencia)
        <div class="dato-fila">
            <span class="dato-label">Unidad de convivencia:</span>
            <span>
                {{ $plan->unidadConvivencia->miembrosActivos->map(fn ($m) =>
                    $m->ciudadano->nombre_completo)->implode(', ') }}
            </span>
        </div>
        @endif
    </div>
</div>

{{-- Diagnóstico social --}}
@if($plan->diagnostico_social)
<div class="seccion">
    <div class="seccion__titulo">Diagnóstico social</div>
    <div class="seccion__contenido">{!! nl2br(e($plan->diagnostico_social)) !!}</div>
</div>
@endif

{{-- Objetivos --}}
@if($plan->objetivosGenerales->isNotEmpty())
<div class="seccion">
    <div class="seccion__titulo">Objetivos</div>
    @foreach($plan->objetivosGenerales as $og)
    <div class="seccion__contenido">
        <strong>{{ $loop->iteration }}. {{ $og->texto }}</strong>
        @if($og->objetivosEspecificos->isNotEmpty())
        <ul style="margin: 3px 0 6px 16px;">
            @foreach($og->objetivosEspecificos as $oe)
            <li>{{ $oe->texto }}</li>
            @endforeach
        </ul>
        @endif
    </div>
    @endforeach
</div>
@endif

{{-- Actuaciones del Ayuntamiento --}}
@if($plan->actuacionesAyuntamiento->isNotEmpty())
<div class="seccion">
    <div class="seccion__titulo">Compromisos del Ayuntamiento</div>
    <table>
        <thead>
            <tr>
                <th style="width:35%">Prestación</th>
                <th style="width:40%">Concreción</th>
                <th style="width:15%">Responsable</th>
                <th style="width:10%">Inicio previsto</th>
            </tr>
        </thead>
        <tbody>
            @foreach($plan->actuacionesAyuntamiento as $act)
            <tr>
                <td>{{ $act->prestacion->nombre }}</td>
                <td>{{ $act->descripcion_especifica ?? '—' }}</td>
                <td>{{ $act->responsable?->name ?? '—' }}</td>
                <td>{{ $act->fecha_inicio_prevista?->format('d/m/Y') ?? '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- Compromisos del ciudadano --}}
@if($plan->actuacionesCiudadano->isNotEmpty())
<div class="seccion">
    <div class="seccion__titulo">Compromisos de la persona</div>
    <table>
        <thead>
            <tr>
                <th style="width:60%">Compromiso</th>
                <th style="width:30%">Recurso relacionado</th>
                <th style="width:10%">Inicio previsto</th>
            </tr>
        </thead>
        <tbody>
            @foreach($plan->actuacionesCiudadano as $act)
            <tr>
                <td>{{ $act->descripcion }}</td>
                <td>{{ $act->prestacion?->nombre ?? '—' }}</td>
                <td>{{ $act->fecha_inicio_prevista?->format('d/m/Y') ?? '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- Participantes --}}
@if($plan->participantesActivos->isNotEmpty())
<div class="seccion">
    <div class="seccion__titulo">Profesionales participantes</div>
    <div class="seccion__contenido">
        @foreach($plan->participantesActivos as $p)
        {{ $p->profesional->name }} ({{ $p->rol_en_plan }})@if(! $loop->last), @endif
        @endforeach
    </div>
</div>
@endif

{{-- Seguimiento --}}
<div class="seccion">
    <div class="seccion__titulo">Periodicidad de seguimiento</div>
    <div class="seccion__contenido">
        {{ ucfirst($plan->periodicidad_seguimiento ?? 'trimestral') }}
    </div>
</div>

{{-- Firmas --}}
<div class="firmas">
    <div class="firma-bloque">
        <div style="height: 40px;"></div>
        <div class="firma-bloque__nombre">
            {{ $plan->profesionalResponsable?->name ?? 'Profesional responsable' }}
        </div>
        <div class="firma-bloque__fecha">Trabajador/a Social de referencia</div>
        <div class="firma-bloque__fecha">Fecha: ___________</div>
    </div>
    <div class="firma-bloque">
        <div style="height: 40px;"></div>
        <div class="firma-bloque__nombre">
            {{ $plan->historia->ciudadano->nombre_completo }}
        </div>
        <div class="firma-bloque__fecha">Persona interesada</div>
        <div class="firma-bloque__fecha">Fecha: ___________</div>
    </div>
</div>

<div class="pie">
    Documento generado por VIDA360 · {{ now()->format('d/m/Y H:i') }} ·
    Historia Social #{{ $plan->historia_id }} · Versión {{ $plan->version }}
</div>

</body>
</html>
```

### Acción en `CiudadanoPage` para generar el PDF

En `CiudadanoPage.php`, añade el método:

```php
public function generarPdfPlan(int $planId): \Symfony\Component\HttpFoundation\StreamedResponse
{
    $plan = \Modules\Intervencion\Models\PlanDeIntervencion::findOrFail($planId);

    // La policy ya verificó acceso al plan en el mount; verificar de nuevo por seguridad
    $this->authorize('view', $plan);

    $service = app(\Modules\Intervencion\Services\PlanPdfService::class);

    return response()->streamDownload(
        fn () => print($service->generar($plan)),
        $service->nombre($plan),
        ['Content-Type' => 'application/pdf']
    );
}
```

---

## Parte 7 — Tests funcionales

Crea `Modules/Intervencion/tests/Feature/PlanContenidoTest.php`:

```php
<?php

namespace Modules\Intervencion\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Intervencion\Models\TipoPlan;
use Modules\Intervencion\Models\ObjetivoCatalogo;
use Modules\Intervencion\Models\PlanObjetivo;
use Modules\Intervencion\Models\PlanActuacionAyuntamiento;
use Modules\Intervencion\Models\PlanActuacionCiudadano;
use Modules\Intervencion\Models\PlanParticipante;
use Modules\Prestaciones\Models\Prestacion;
use App\Models\User;
use Tests\TestCase;

/**
 * Tests funcionales del contenido del Plan de Intervención.
 * Nomenclatura: TF-PLAN-XX
 */
class PlanContenidoTest extends TestCase
{
    use RefreshDatabase;

    private function crearPlan(): PlanDeIntervencion
    {
        $tipoPlan = TipoPlan::factory()->create();
        return PlanDeIntervencion::factory()->create([
            'tipo_plan_id' => $tipoPlan->id,
        ]);
    }

    // --- TipoPlan ---

    // TF-PLAN-01: Seeder carga los 5 tipos iniciales
    public function test_seeder_tipos_plan(): void
    {
        $this->seed(\Modules\Intervencion\Database\Seeders\TipoPlanSeeder::class);
        $this->assertEquals(5, TipoPlan::count());
    }

    // TF-PLAN-02: Seeder es idempotente
    public function test_seeder_tipos_plan_idempotente(): void
    {
        $this->seed(\Modules\Intervencion\Database\Seeders\TipoPlanSeeder::class);
        $this->seed(\Modules\Intervencion\Database\Seeders\TipoPlanSeeder::class);
        $this->assertEquals(5, TipoPlan::count());
    }

    // TF-PLAN-03: No se puede eliminar tipo no eliminable
    public function test_no_elimina_tipo_plan_sistema(): void
    {
        $this->seed(\Modules\Intervencion\Database\Seeders\TipoPlanSeeder::class);
        $tipo = TipoPlan::where('eliminable', false)->first();

        $this->expectException(\LogicException::class);
        $tipo->delete();
    }

    // --- ObjetivoCatalogo ---

    // TF-PLAN-04: Los objetivos específicos se vinculan correctamente a su general
    public function test_objetivo_especifico_vinculado_a_general(): void
    {
        $tipo = TipoPlan::factory()->create();
        $general = ObjetivoCatalogo::create([
            'tipo_plan_id' => $tipo->id,
            'nivel'        => 'general',
            'texto'        => 'Favorecer la inclusión social',
            'orden'        => 1,
        ]);
        $especifico = ObjetivoCatalogo::create([
            'tipo_plan_id'        => $tipo->id,
            'nivel'               => 'especifico',
            'objetivo_general_id' => $general->id,
            'texto'               => 'Reducir el aislamiento social',
            'orden'               => 1,
        ]);

        $this->assertEquals($general->id, $especifico->objetivoGeneral->id);
        $this->assertEquals($especifico->id, $general->objetivosEspecificos->first()->id);
    }

    // --- PlanObjetivo ---

    // TF-PLAN-05: Se pueden añadir objetivos al plan
    public function test_anadir_objetivo_al_plan(): void
    {
        $plan = $this->crearPlan();
        $objetivo = PlanObjetivo::create([
            'plan_id' => $plan->id,
            'nivel'   => 'general',
            'texto'   => 'Mejorar las condiciones económicas del hogar',
            'estado'  => 'pendiente',
            'orden'   => 1,
        ]);

        $this->assertDatabaseHas('plan_objetivos', ['plan_id' => $plan->id]);
        $this->assertEquals(1, $plan->objetivos()->count());
    }

    // TF-PLAN-06: Los objetivos específicos se anidan bajo generales
    public function test_objetivos_anidados(): void
    {
        $plan = $this->crearPlan();
        $general = PlanObjetivo::create([
            'plan_id' => $plan->id, 'nivel' => 'general',
            'texto' => 'Objetivo general', 'estado' => 'pendiente', 'orden' => 1,
        ]);
        PlanObjetivo::create([
            'plan_id'             => $plan->id,
            'nivel'               => 'especifico',
            'objetivo_general_id' => $general->id,
            'texto'               => 'Objetivo específico',
            'estado'              => 'pendiente',
            'orden'               => 1,
        ]);

        $this->assertCount(1, $general->objetivosEspecificos);
    }

    // --- PlanActuacionAyuntamiento ---

    // TF-PLAN-07: Actuación del Ayuntamiento sin prestación lanza excepción
    public function test_actuacion_ayuntamiento_requiere_prestacion(): void
    {
        $plan = $this->crearPlan();

        $this->expectException(\LogicException::class);
        PlanActuacionAyuntamiento::create([
            'plan_id'     => $plan->id,
            'prestacion_id' => null, // sin prestación
            'estado'      => 'pendiente',
            'orden'       => 1,
        ]);
    }

    // TF-PLAN-08: Actuación del Ayuntamiento con prestación se guarda correctamente
    public function test_actuacion_ayuntamiento_con_prestacion(): void
    {
        $plan = $this->crearPlan();
        $prestacion = Prestacion::factory()->create();

        $actuacion = PlanActuacionAyuntamiento::create([
            'plan_id'               => $plan->id,
            'prestacion_id'         => $prestacion->id,
            'descripcion_especifica' => 'Asistirá a 4 sesiones del taller de empleo',
            'estado'                => 'pendiente',
            'orden'                 => 1,
        ]);

        $this->assertEquals($prestacion->id, $actuacion->prestacion->id);
        $this->assertEquals('Asistirá a 4 sesiones del taller de empleo', $actuacion->descripcion_especifica);
    }

    // TF-PLAN-09: Actuación ciudadano con texto libre se guarda sin prestación
    public function test_actuacion_ciudadano_sin_prestacion(): void
    {
        $plan = $this->crearPlan();

        $actuacion = PlanActuacionCiudadano::create([
            'plan_id'     => $plan->id,
            'descripcion' => 'Mantendrá a sus hijos escolarizados',
            'estado'      => 'pendiente',
            'orden'       => 1,
        ]);

        $this->assertNull($actuacion->prestacion_id);
        $this->assertDatabaseHas('plan_actuaciones_ciudadano', ['plan_id' => $plan->id]);
    }

    // TF-PLAN-10: Actuación ciudadano puede vincularse a prestación
    public function test_actuacion_ciudadano_con_prestacion(): void
    {
        $plan = $this->crearPlan();
        $prestacion = Prestacion::factory()->create();

        $actuacion = PlanActuacionCiudadano::create([
            'plan_id'      => $plan->id,
            'descripcion'  => 'Asistirá al taller de resolución de conflictos',
            'prestacion_id' => $prestacion->id,
            'estado'       => 'pendiente',
            'orden'        => 1,
        ]);

        $this->assertEquals($prestacion->id, $actuacion->prestacion_id);
    }

    // --- PlanParticipante ---

    // TF-PLAN-11: Se pueden añadir participantes al plan
    public function test_anadir_participante(): void
    {
        $plan = $this->crearPlan();
        $profesional = User::factory()->create();

        $participante = PlanParticipante::create([
            'plan_id'     => $plan->id,
            'user_id'     => $profesional->id,
            'rol_en_plan' => 'Educador/a social',
            'fecha_inicio' => now()->toDateString(),
        ]);

        $this->assertTrue($participante->estaActivo());
        $this->assertCount(1, $plan->participantesActivos);
    }

    // TF-PLAN-12: Participante con fecha_fin no aparece en activos
    public function test_participante_inactivo_excluido(): void
    {
        $plan = $this->crearPlan();
        $profesional = User::factory()->create();

        PlanParticipante::create([
            'plan_id'      => $plan->id,
            'user_id'      => $profesional->id,
            'rol_en_plan'  => 'Psicólogo/a',
            'fecha_inicio' => '2024-01-01',
            'fecha_fin'    => '2024-06-01',
        ]);

        $this->assertCount(0, $plan->participantesActivos);
    }

    // --- Historial de cambios ---

    // TF-PLAN-13: registrarCambio crea snapshot con el estado actual
    public function test_registrar_cambio_crea_snapshot(): void
    {
        $plan = $this->crearPlan();
        $plan->update(['diagnostico_social' => 'Situación de vulnerabilidad económica']);
        $profesional = User::factory()->create();

        $cambio = $plan->registrarCambio($profesional->id, 'Actualización de diagnóstico');

        $this->assertNotNull($cambio->snapshot);
        $this->assertEquals('Situación de vulnerabilidad económica',
            $cambio->snapshot['diagnostico_social']);
    }

    // TF-PLAN-14: cambios discrecional y de seguimiento se distinguen por origen
    public function test_origen_cambio(): void
    {
        $plan = $this->crearPlan();
        $profesional = User::factory()->create();

        $cambioDisc = $plan->registrarCambio($profesional->id, 'Ajuste menor', 'discrecional');
        $this->assertEquals('discrecional', $cambioDisc->origen);
    }

    // TF-PLAN-15: Los cambios se ordenan de más reciente a más antiguo
    public function test_cambios_orden_desc(): void
    {
        $plan = $this->crearPlan();
        $profesional = User::factory()->create();

        $plan->registrarCambio($profesional->id, 'Primer cambio');
        sleep(1); // garantizar diferencia de timestamp
        $plan->registrarCambio($profesional->id, 'Segundo cambio');

        $cambios = $plan->cambios()->get();
        $this->assertEquals('Segundo cambio', $cambios->first()->motivo);
        $this->assertEquals('Primer cambio', $cambios->last()->motivo);
    }

    // --- PDF ---

    // TF-PLAN-16: PlanPdfService existe y es instanciable
    public function test_plan_pdf_service_instanciable(): void
    {
        $service = app(\Modules\Intervencion\Services\PlanPdfService::class);
        $this->assertInstanceOf(\Modules\Intervencion\Services\PlanPdfService::class, $service);
    }

    // TF-PLAN-17: nombre() devuelve string con extensión .pdf
    public function test_pdf_nombre_correcto(): void
    {
        $plan = $this->crearPlan();
        $service = app(\Modules\Intervencion\Services\PlanPdfService::class);

        $nombre = $service->nombre($plan);

        $this->assertStringEndsWith('.pdf', $nombre);
        $this->assertStringContainsString("v{$plan->version}", $nombre);
    }
}
```

---

## Parte 8 — Factory `TipoPlan`

Crea `Modules/Intervencion/database/factories/TipoPlanFactory.php`:

```php
<?php

namespace Modules\Intervencion\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Intervencion\Models\TipoPlan;

class TipoPlanFactory extends Factory
{
    protected $model = TipoPlan::class;

    public function definition(): array
    {
        return [
            'slug'        => $this->faker->unique()->slug(2),
            'nombre'      => 'Plan ' . $this->faker->words(3, true),
            'ambito'      => $this->faker->randomElement(['asp', 'especializado']),
            'descripcion' => $this->faker->sentence(),
            'activo'      => true,
            'eliminable'  => true,
        ];
    }

    public function asp(): static
    {
        return $this->state(['ambito' => 'asp']);
    }

    public function especializado(): static
    {
        return $this->state(['ambito' => 'especializado']);
    }

    public function noEliminable(): static
    {
        return $this->state(['eliminable' => false]);
    }
}
```

---

## Parte 9 — Actualizar BACKLOG y SESSION

Elimina de `BACKLOG.md`:
- Cualquier entrada pendiente sobre "Plan de Intervención — modelo" o "PISO — estructura de datos"

Añade en `BACKLOG.md`:

```markdown
**UI del Plan de Intervención en CiudadanoPage** — 2026-06-16
`Módulo: Intervención`
El modelo completo está implementado. Pendiente: UI Livewire para crear/editar
planes en CiudadanoPage, incluyendo:
- Formulario de diagnóstico social
- Gestión de objetivos (selección de catálogo + texto libre)
- Gestión de actuaciones del Ayuntamiento (búsqueda en catálogo de prestaciones)
- Gestión de actuaciones del ciudadano (texto libre + prestación opcional)
- Gestión de participantes
- Botón generar PDF + upload del PDF firmado

**Instalar barryvdh/laravel-dompdf** — 2026-06-16
`Infraestructura`
El servicio PlanPdfService usa dompdf. Si no está instalado:
`composer require barryvdh/laravel-dompdf`
Documentar en SESSION.md tras instalación.

**Seguimiento del plan — UI** — 2026-06-16
`Módulo: Intervención`
El modelo SeguimientoPlan ya existe (tests TF-INT-C). Pendiente: integrar el
seguimiento en la UI de CiudadanoPage, con evaluación de objetivos por estado
y programación del siguiente seguimiento.
```

Actualiza `SESSION.md`:
- Tarea completada: modelo completo del Plan de Intervención (tipos, objetivos,
  actuaciones, participantes, historial de cambios, PDF)
- Siguiente paso recomendado: instalar dompdf + UI del plan en CiudadanoPage

---

## Checklist de verificación

- [ ] `php artisan migrate` sin errores (4 migraciones nuevas)
- [ ] `php artisan db:seed --class=Modules\\Intervencion\\Database\\Seeders\\TipoPlanSeeder` — 5 registros
- [ ] `php artisan test --filter=PlanContenidoTest` — 17 tests en verde
- [ ] Tests anteriores TF-INT-A a D siguen pasando (no regresiones)
- [ ] `TipoPlanResource` aparece en Filament bajo el grupo Catálogos
- [ ] La página de gestión de objetivos es accesible desde el recurso
- [ ] El slug no es editable al editar un tipo existente
- [ ] `PlanActuacionAyuntamiento::create()` sin `prestacion_id` lanza LogicException
- [ ] La vista PDF renderiza sin errores con datos reales de prueba
- [ ] BACKLOG y SESSION.md actualizados
- [ ] Commit: `feat(intervencion): modelo completo Plan de Intervención — tipos, objetivos, actuaciones, participantes, historial y PDF`
