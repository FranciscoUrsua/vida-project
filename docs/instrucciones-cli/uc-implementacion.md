# Instrucciones CLI — Unidad de Convivencia (Módulo Ciudadanía)

## Contexto

La Unidad de Convivencia (UC) vive en `Modules/Ciudadania`. Las tablas
`unidades_convivencia` y `unidad_convivencia_miembros` están pendientes de crear
(referenciadas en BACKLOG como `unidades_convivencia (tabla pendiente)`).

La conversación de diseño ha fijado las siguientes decisiones que deben
reflejarse tanto en código como en `docs/modulo-ciudadania.md`:

1. Los miembros de la UC son ciudadanos plenos: pasan por el flujo de alta
   completo (deduplicación, padrón, cifrado). No existe un "miembro simplificado".
2. `verificado` en `unidad_convivencia_miembros` indica verificación de residencia
   en el municipio. Sin verificación, el ciudadano no puede ser perceptor de
   prestaciones. El TSR puede verificar manualmente en casos tasados (VVG, PSH).
3. El Plan de Intervención puede pertenecer a una UC (además de a un ciudadano
   individual). Se añade `unidad_convivencia_id` nullable a `planes_intervencion`,
   con la restricción de que exactamente uno de `ciudadano_id` o
   `unidad_convivencia_id` debe estar presente.
4. La UC no tiene titular. Los planes y prestaciones se asignan a personas
   concretas o a la UC como entidad, nunca implícitamente al "cabeza de familia".

---

## Paso 1 — Actualizar `docs/modulo-ciudadania.md`

Localiza la sección `### 3.4 Unidad de convivencia` y reemplaza su contenido
completo por el siguiente:

```markdown
### 3.4 Unidad de convivencia

La unidad de convivencia tiene identidad propia porque es la unidad de referencia
para el cálculo de prestaciones económicas y para la intervención familiar. No es
simplemente un grupo de relaciones — es una entidad con domicilio, fechas de
vigencia y composición propia.

**Ubicación en el código:** `Modules\Ciudadania`. La UC no tiene módulo propio;
su ciclo de vida siempre se origina desde un ciudadano.

```

unidades_convivencia
- id
- domicilio (text encriptado)
- latitud (decimal nullable)
- longitud (decimal nullable)
- fecha_constitucion (date)
- fecha_disolucion (date nullable)
- observaciones (text nullable)
- timestamps
- softDeletes

unidad_convivencia_miembros
- id
- unidad_convivencia_id (FK)
- ciudadano_id (FK)
- fecha_inicio (date)
- fecha_fin (date nullable)
- fuente (enum: manual / padron / importacion)
- verificado (boolean default false)
- verificado_por (FK a users, nullable)
- verificado_en (timestamp nullable)
- timestamps

```

**Sobre cuándo crear una unidad de convivencia:** un ciudadano se da de alta
siempre sin unidad de convivencia. La unidad se crea únicamente cuando es
relevante modelar la convivencia:

- Al dar de alta a un conviviente para vincularlo al caso de otro ciudadano.
- Al tramitar una prestación económica que requiere conocer la composición e
  ingresos del hogar.
- Cuando la intervención es de carácter familiar, no individual.

**Sobre los miembros de la UC:** todo miembro es un ciudadano de pleno derecho
en el sistema. Cuando el TSR añade un conviviente durante el flujo de intervención,
ese alta pasa por el mismo motor de deduplicación y la misma consulta al padrón
que cualquier otro alta. El contexto de alta puede preseleccionar el domicilio de
la UC y la relación con el ciudadano de referencia para agilizar el formulario,
pero no omite ninguna garantía de calidad de datos.

Un miembro de la UC puede no tener Historia Social ni Plan de Intervención propio.
Su presencia en la unidad puede ser relevante únicamente para el cálculo de
ingresos o la valoración de la situación familiar del ciudadano titular del caso.

**Sobre el campo `verificado`:** indica si se ha verificado la residencia del
ciudadano en el municipio, necesaria para ser perceptor de prestaciones municipales.
La verificación se produce normalmente durante el alta mediante consulta al padrón.
En casos tasados (VVG, PSH sin documentación), el TSR puede marcar la verificación
manualmente; en ese caso se registra `verificado_por` y `verificado_en` para
trazabilidad. Un ciudadano sin `verificado = true` en su membresía activa no puede
ser titular de prestaciones económicas municipales — esta restricción se evalúa en
código, no en configuración.

**Sobre el rol dentro de la unidad:** la unidad de convivencia no registra el rol
de cada miembro. Quién es hijo de quién, quién es tutor de quién, se lee de
`ciudadano_relaciones`. Cuando se añade un miembro a una unidad de convivencia, el
profesional debe asegurarse de que la relación entre ese ciudadano y los demás
miembros existe en la tabla de relaciones; si no existe, la crea en ese momento.

**Sobre la titularidad y los planes:** no existe un "titular de la unidad". Los
Planes de Intervención pueden asignarse a una persona concreta (ciudadano individual)
o a la UC como entidad (intervención familiar). Las prestaciones económicas se
asignan siempre a personas concretas. Ver `docs/modulo-intervencion.md`, sección 5,
para el modelo de `PlanDeIntervencion` y la restricción de que exactamente uno de
`ciudadano_id` o `unidad_convivencia_id` debe estar presente.

Un ciudadano puede pertenecer a más de una unidad de convivencia a lo largo del
tiempo, y excepcionalmente a más de una simultáneamente (menores con custodia
compartida en dos domicilios).

Los miembros importados desde el padrón se marcan con `fuente: padron` y
`verificado: false` hasta confirmación del profesional.
```

---

## Paso 2 — Actualizar `docs/modulo-intervencion.md`

Localiza la sección `### 5.2 Atributos` de `PlanDeIntervencion` y añade
`unidad_convivencia_id` como segundo campo tras `historia_id`:

```
- `unidad_convivencia_id` (FK nullable — para planes de intervención familiar asignados a la UC. Exactamente uno de `ciudadano_id` (en historia_id→ciudadano) o `unidad_convivencia_id` debe determinar el destinatario del plan)
```

Y en la sección sobre titularidad (donde actualmente dice "los planes se asignan
siempre a personas concretas, nunca a la unidad"), actualizar a:

```
Los Planes de Intervención pueden asignarse a una persona concreta o a una Unidad
de Convivencia. En el segundo caso, `unidad_convivencia_id` está presente y la
Historia Social del ciudadano interlocutor (el TSR de referencia del caso) actúa
como contenedor del historial de apuntes del plan familiar.
```

---

## Paso 3 — Migración: crear tablas de UC

Crea el archivo `Modules/Ciudadania/database/migrations/2026_06_16_000001_create_unidades_convivencia_tables.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unidades_convivencia', function (Blueprint $table) {
            $table->id();
            $table->text('domicilio')->nullable();          // encriptado en aplicación
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();
            $table->date('fecha_constitucion');
            $table->date('fecha_disolucion')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('unidad_convivencia_miembros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unidad_convivencia_id')
                  ->constrained('unidades_convivencia')
                  ->cascadeOnDelete();
            $table->foreignId('ciudadano_id')
                  ->constrained('ciudadanos')
                  ->cascadeOnDelete();
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->enum('fuente', ['manual', 'padron', 'importacion'])
                  ->default('manual');
            $table->boolean('verificado')->default(false);
            $table->foreignId('verificado_por')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->timestamp('verificado_en')->nullable();
            $table->timestamps();

            // Un ciudadano no puede estar dos veces activo en la misma UC
            // (fecha_fin null = activo). No se puede expresar como unique constraint
            // directamente por los nulls; se valida en el modelo.
            $table->index(['unidad_convivencia_id', 'ciudadano_id']);
            $table->index(['ciudadano_id', 'fecha_fin']); // consultas "membresías activas"
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unidad_convivencia_miembros');
        Schema::dropIfExists('unidades_convivencia');
    }
};
```

---

## Paso 4 — Migración: añadir `unidad_convivencia_id` a `planes_intervencion`

Crea `Modules/Intervencion/database/migrations/2026_06_16_000002_add_unidad_convivencia_to_planes_intervencion.php`:

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
            $table->foreignId('unidad_convivencia_id')
                  ->nullable()
                  ->after('historia_id')
                  ->constrained('unidades_convivencia')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('planes_intervencion', function (Blueprint $table) {
            $table->dropForeignIdFor(\Modules\Ciudadania\Models\UnidadConvivencia::class);
            $table->dropColumn('unidad_convivencia_id');
        });
    }
};
```

> **Nota:** si la tabla `planes_intervencion` aún no existe (el módulo Intervención
> está en stub), omite esta migración y añade el campo directamente en la migración
> de creación de `planes_intervencion` cuando se implemente. Documenta esto en
> BACKLOG si es el caso.

---

## Paso 5 — Modelo `UnidadConvivencia`

Crea `Modules/Ciudadania/app/Models/UnidadConvivencia.php`:

```php
<?php

namespace Modules\Ciudadania\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Crypt;

class UnidadConvivencia extends Model
{
    use SoftDeletes;

    protected $table = 'unidades_convivencia';

    protected $fillable = [
        'domicilio',
        'latitud',
        'longitud',
        'fecha_constitucion',
        'fecha_disolucion',
        'observaciones',
    ];

    protected $casts = [
        'fecha_constitucion' => 'date',
        'fecha_disolucion'   => 'date',
        'latitud'            => 'decimal:7',
        'longitud'           => 'decimal:7',
    ];

    // --- Cifrado en aplicación para domicilio ---

    protected function domicilio(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? Crypt::decryptString($value) : null,
            set: fn (?string $value) => $value ? Crypt::encryptString($value) : null,
        );
    }

    // --- Relaciones ---

    public function miembros(): HasMany
    {
        return $this->hasMany(UnidadConvivenciaMiembro::class, 'unidad_convivencia_id');
    }

    public function miembrosActivos(): HasMany
    {
        return $this->miembros()->whereNull('fecha_fin');
    }

    public function ciudadanos(): BelongsToMany
    {
        return $this->belongsToMany(
            Ciudadano::class,
            'unidad_convivencia_miembros',
            'unidad_convivencia_id',
            'ciudadano_id'
        )->withPivot(['fecha_inicio', 'fecha_fin', 'fuente', 'verificado',
                      'verificado_por', 'verificado_en'])
         ->withTimestamps();
    }

    // --- Métodos de negocio ---

    /**
     * Ciudadanos activos (sin fecha_fin) que tienen verificada su residencia.
     */
    public function miembrosVerificados(): HasMany
    {
        return $this->miembrosActivos()->where('verificado', true);
    }

    /**
     * ¿Está disuelta la unidad?
     */
    public function estaDisuelta(): bool
    {
        return $this->fecha_disolucion !== null
            && $this->fecha_disolucion->isPast();
    }

    /**
     * Añade un ciudadano como miembro activo.
     * Lanza excepción si el ciudadano ya es miembro activo.
     */
    public function agregarMiembro(
        int $ciudadanoId,
        string $fuente = 'manual',
        ?\DateTimeInterface $fechaInicio = null
    ): UnidadConvivenciaMiembro {
        $yaActivo = $this->miembrosActivos()
            ->where('ciudadano_id', $ciudadanoId)
            ->exists();

        if ($yaActivo) {
            throw new \LogicException(
                "El ciudadano #{$ciudadanoId} ya es miembro activo de esta unidad de convivencia."
            );
        }

        return $this->miembros()->create([
            'ciudadano_id'  => $ciudadanoId,
            'fecha_inicio'  => $fechaInicio ?? now()->toDateString(),
            'fuente'        => $fuente,
            'verificado'    => false,
        ]);
    }

    /**
     * Da de baja a un miembro (fecha_fin = hoy).
     * Lanza excepción si el ciudadano no es miembro activo.
     */
    public function darDeBajaMiembro(
        int $ciudadanoId,
        ?\DateTimeInterface $fechaFin = null
    ): void {
        $miembro = $this->miembrosActivos()
            ->where('ciudadano_id', $ciudadanoId)
            ->first();

        if (! $miembro) {
            throw new \LogicException(
                "El ciudadano #{$ciudadanoId} no es miembro activo de esta unidad de convivencia."
            );
        }

        $miembro->update(['fecha_fin' => $fechaFin ?? now()->toDateString()]);
    }
}
```

---

## Paso 6 — Modelo `UnidadConvivenciaMiembro`

Crea `Modules/Ciudadania/app/Models/UnidadConvivenciaMiembro.php`:

```php
<?php

namespace Modules\Ciudadania\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use App\Models\User;

class UnidadConvivenciaMiembro extends Model
{
    protected $table = 'unidad_convivencia_miembros';

    protected $fillable = [
        'unidad_convivencia_id',
        'ciudadano_id',
        'fecha_inicio',
        'fecha_fin',
        'fuente',
        'verificado',
        'verificado_por',
        'verificado_en',
    ];

    protected $casts = [
        'fecha_inicio'  => 'date',
        'fecha_fin'     => 'date',
        'verificado'    => 'boolean',
        'verificado_en' => 'datetime',
    ];

    // --- Relaciones ---

    public function unidadConvivencia(): BelongsTo
    {
        return $this->belongsTo(UnidadConvivencia::class, 'unidad_convivencia_id');
    }

    public function ciudadano(): BelongsTo
    {
        return $this->belongsTo(Ciudadano::class, 'ciudadano_id');
    }

    public function verificadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verificado_por');
    }

    // --- Métodos de negocio ---

    /**
     * Marca la membresía como verificada por el usuario dado.
     * No lanza excepción si ya estaba verificada (operación idempotente).
     */
    public function verificar(User $profesional): void
    {
        $this->update([
            'verificado'    => true,
            'verificado_por' => $profesional->id,
            'verificado_en'  => now(),
        ]);
    }

    /**
     * ¿Es esta membresía actualmente activa?
     */
    public function estaActiva(): bool
    {
        return $this->fecha_fin === null;
    }

    /**
     * ¿Puede este miembro ser titular de prestaciones municipales?
     * Requiere membresía activa Y verificación de residencia.
     */
    public function puedeSerPerceptorPrestaciones(): bool
    {
        return $this->estaActiva() && $this->verificado;
    }
}
```

---

## Paso 7 — Añadir relaciones al modelo `Ciudadano`

En `Modules/Ciudadania/app/Models/Ciudadano.php`, añade los siguientes métodos
de relación (si ya existen, verifica que son correctos):

```php
use Modules\Ciudadania\Models\UnidadConvivencia;
use Modules\Ciudadania\Models\UnidadConvivenciaMiembro;

// Todas las membresías (históricas y activas)
public function membresiasUC(): HasMany
{
    return $this->hasMany(UnidadConvivenciaMiembro::class, 'ciudadano_id');
}

// Unidades de convivencia a las que ha pertenecido
public function unidadesConvivencia(): BelongsToMany
{
    return $this->belongsToMany(
        UnidadConvivencia::class,
        'unidad_convivencia_miembros',
        'ciudadano_id',
        'unidad_convivencia_id'
    )->withPivot(['fecha_inicio', 'fecha_fin', 'fuente', 'verificado',
                  'verificado_por', 'verificado_en'])
     ->withTimestamps();
}

// Unidades activas (puede ser más de una: custodia compartida)
public function unidadesConvivenciaActivas(): BelongsToMany
{
    return $this->unidadesConvivencia()
        ->wherePivotNull('fecha_fin');
}

/**
 * ¿Tiene el ciudadano verificada su residencia en alguna UC activa?
 * Determina si puede ser perceptor de prestaciones municipales.
 */
public function tieneResidenciaVerificada(): bool
{
    return $this->membresiasUC()
        ->whereNull('fecha_fin')
        ->where('verificado', true)
        ->exists();
}
```

---

## Paso 8 — Factory `UnidadConvivenciaFactory`

Crea `Modules/Ciudadania/database/factories/UnidadConvivenciaFactory.php`:

```php
<?php

namespace Modules\Ciudadania\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Ciudadania\Models\UnidadConvivencia;

class UnidadConvivenciaFactory extends Factory
{
    protected $model = UnidadConvivencia::class;

    public function definition(): array
    {
        return [
            'domicilio'          => $this->faker->streetAddress(),
            'latitud'            => $this->faker->latitude(40.30, 40.65),
            'longitud'           => $this->faker->longitude(-3.83, -3.52),
            'fecha_constitucion' => $this->faker->dateTimeBetween('-5 years', 'now')->format('Y-m-d'),
            'fecha_disolucion'   => null,
            'observaciones'      => null,
        ];
    }

    public function disuelta(): static
    {
        return $this->state(fn () => [
            'fecha_disolucion' => $this->faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
        ]);
    }
}
```

---

## Paso 9 — Tests funcionales

Crea `Modules/Ciudadania/tests/Feature/UnidadConvivenciaTest.php`:

```php
<?php

namespace Modules\Ciudadania\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Ciudadania\Models\Ciudadano;
use Modules\Ciudadania\Models\UnidadConvivencia;
use Modules\Ciudadania\Models\UnidadConvivenciaMiembro;
use App\Models\User;
use Tests\TestCase;

/**
 * Tests funcionales de Unidad de Convivencia.
 * Nomenclatura: TF-UC-XX
 */
class UnidadConvivenciaTest extends TestCase
{
    use RefreshDatabase;

    // TF-UC-01: Se puede crear una UC con domicilio encriptado
    public function test_crea_uc_con_domicilio_encriptado(): void
    {
        $uc = UnidadConvivencia::factory()->create([
            'domicilio' => 'Calle Mayor 1, Madrid',
        ]);

        // El valor en BD está encriptado (no es el texto plano)
        $raw = \DB::table('unidades_convivencia')
            ->where('id', $uc->id)
            ->value('domicilio');

        $this->assertNotEquals('Calle Mayor 1, Madrid', $raw);
        $this->assertEquals('Calle Mayor 1, Madrid', $uc->domicilio);
    }

    // TF-UC-02: Se puede añadir un ciudadano como miembro
    public function test_agrega_miembro_a_uc(): void
    {
        $uc = UnidadConvivencia::factory()->create();
        $ciudadano = Ciudadano::factory()->create();

        $miembro = $uc->agregarMiembro($ciudadano->id);

        $this->assertInstanceOf(UnidadConvivenciaMiembro::class, $miembro);
        $this->assertEquals($ciudadano->id, $miembro->ciudadano_id);
        $this->assertNull($miembro->fecha_fin);
        $this->assertFalse($miembro->verificado);
    }

    // TF-UC-03: No se puede añadir el mismo ciudadano dos veces como miembro activo
    public function test_no_permite_miembro_activo_duplicado(): void
    {
        $uc = UnidadConvivencia::factory()->create();
        $ciudadano = Ciudadano::factory()->create();

        $uc->agregarMiembro($ciudadano->id);

        $this->expectException(\LogicException::class);
        $uc->agregarMiembro($ciudadano->id);
    }

    // TF-UC-04: Se puede añadir el mismo ciudadano tras darle de baja (histórico)
    public function test_permite_miembro_tras_baja(): void
    {
        $uc = UnidadConvivencia::factory()->create();
        $ciudadano = Ciudadano::factory()->create();

        $uc->agregarMiembro($ciudadano->id);
        $uc->darDeBajaMiembro($ciudadano->id);

        // Puede volver a añadirse
        $miembro = $uc->agregarMiembro($ciudadano->id);
        $this->assertNull($miembro->fecha_fin);
    }

    // TF-UC-05: Dar de baja a un miembro no activo lanza excepción
    public function test_baja_miembro_no_activo_lanza_excepcion(): void
    {
        $uc = UnidadConvivencia::factory()->create();
        $ciudadano = Ciudadano::factory()->create();

        $this->expectException(\LogicException::class);
        $uc->darDeBajaMiembro($ciudadano->id);
    }

    // TF-UC-06: miembrosActivos() solo devuelve miembros sin fecha_fin
    public function test_miembros_activos_excluye_dados_de_baja(): void
    {
        $uc = UnidadConvivencia::factory()->create();
        $activo = Ciudadano::factory()->create();
        $baja   = Ciudadano::factory()->create();

        $uc->agregarMiembro($activo->id);
        $uc->agregarMiembro($baja->id);
        $uc->darDeBajaMiembro($baja->id);

        $activos = $uc->miembrosActivos()->get();
        $this->assertCount(1, $activos);
        $this->assertEquals($activo->id, $activos->first()->ciudadano_id);
    }

    // TF-UC-07: verificar() marca la membresía con el profesional y timestamp
    public function test_verificar_membresia(): void
    {
        $uc = UnidadConvivencia::factory()->create();
        $ciudadano = Ciudadano::factory()->create();
        $profesional = User::factory()->create();

        $miembro = $uc->agregarMiembro($ciudadano->id);
        $miembro->verificar($profesional);

        $miembro->refresh();
        $this->assertTrue($miembro->verificado);
        $this->assertEquals($profesional->id, $miembro->verificado_por);
        $this->assertNotNull($miembro->verificado_en);
    }

    // TF-UC-08: puedeSerPerceptorPrestaciones() requiere activo Y verificado
    public function test_perceptor_prestaciones_requiere_activo_y_verificado(): void
    {
        $uc = UnidadConvivencia::factory()->create();
        $ciudadano = Ciudadano::factory()->create();
        $profesional = User::factory()->create();

        $miembro = $uc->agregarMiembro($ciudadano->id);

        // Activo pero no verificado
        $this->assertFalse($miembro->puedeSerPerceptorPrestaciones());

        $miembro->verificar($profesional);
        $miembro->refresh();

        // Activo y verificado
        $this->assertTrue($miembro->puedeSerPerceptorPrestaciones());

        $uc->darDeBajaMiembro($ciudadano->id);
        $miembro->refresh();

        // Verificado pero no activo
        $this->assertFalse($miembro->puedeSerPerceptorPrestaciones());
    }

    // TF-UC-09: tieneResidenciaVerificada() en Ciudadano funciona correctamente
    public function test_ciudadano_tiene_residencia_verificada(): void
    {
        $uc = UnidadConvivencia::factory()->create();
        $ciudadano = Ciudadano::factory()->create();
        $profesional = User::factory()->create();

        $miembro = $uc->agregarMiembro($ciudadano->id);

        $this->assertFalse($ciudadano->tieneResidenciaVerificada());

        $miembro->verificar($profesional);

        $this->assertTrue($ciudadano->fresh()->tieneResidenciaVerificada());
    }

    // TF-UC-10: Un ciudadano puede pertenecer a dos UC activas simultáneamente
    public function test_ciudadano_en_dos_uc_simultaneas(): void
    {
        $uc1 = UnidadConvivencia::factory()->create();
        $uc2 = UnidadConvivencia::factory()->create();
        $ciudadano = Ciudadano::factory()->create();

        $uc1->agregarMiembro($ciudadano->id);
        $uc2->agregarMiembro($ciudadano->id);

        $this->assertCount(2, $ciudadano->unidadesConvivenciaActivas()->get());
    }

    // TF-UC-11: estaDisuelta() detecta correctamente la disolución
    public function test_uc_disuelta(): void
    {
        $activa   = UnidadConvivencia::factory()->create();
        $disuelta = UnidadConvivencia::factory()->disuelta()->create();

        $this->assertFalse($activa->estaDisuelta());
        $this->assertTrue($disuelta->estaDisuelta());
    }

    // TF-UC-12: softDelete no elimina los registros de miembros
    public function test_soft_delete_uc_preserva_miembros(): void
    {
        $uc = UnidadConvivencia::factory()->create();
        $ciudadano = Ciudadano::factory()->create();
        $uc->agregarMiembro($ciudadano->id);

        $uc->delete();

        // La UC ya no aparece en consultas normales
        $this->assertNull(UnidadConvivencia::find($uc->id));

        // Pero los miembros siguen en la BD
        $this->assertDatabaseHas('unidad_convivencia_miembros', [
            'unidad_convivencia_id' => $uc->id,
            'ciudadano_id'          => $ciudadano->id,
        ]);
    }

    // TF-UC-13: fuente se guarda correctamente para miembros importados de padrón
    public function test_fuente_padron_en_miembro(): void
    {
        $uc = UnidadConvivencia::factory()->create();
        $ciudadano = Ciudadano::factory()->create();

        $miembro = $uc->agregarMiembro($ciudadano->id, fuente: 'padron');

        $this->assertEquals('padron', $miembro->fuente);
        $this->assertFalse($miembro->verificado);
    }
}
```

---

## Paso 10 — Actualizar BACKLOG y SESSION

Al finalizar, añade en `BACKLOG.md`:

```markdown
**UC en UI de intervención** — 2026-06-16
`Módulo: Ciudadanía / Intervención`
Los modelos y migraciones de UnidadConvivencia están implementados.
Pendiente: UI Livewire para gestión de UC dentro de la pantalla de intervención
del ciudadano (añadir/dar de baja miembros, verificar residencia, ver composición).
```

Y elimina de BACKLOG la entrada:
```
`unidades_convivencia` (tabla pendiente)
```

Actualiza `SESSION.md` con el estado tras esta implementación.

---

## Checklist de verificación

Antes de hacer commit, confirma que:

- [ ] `php artisan migrate` ejecuta sin errores
- [ ] `php artisan test --filter=UnidadConvivenciaTest` — 13 tests en verde
- [ ] La suite completa de Ciudadanía sigue pasando (sin regresiones)
- [ ] `docs/modulo-ciudadania.md` sección 3.4 actualizada
- [ ] `docs/modulo-intervencion.md` sección 5.2 actualizada
- [ ] BACKLOG actualizado (entrada UC tabla pendiente eliminada, nueva entrada UI añadida)
- [ ] SESSION.md actualizado
- [ ] Commit con mensaje: `feat(ciudadania): implementar UnidadConvivencia y UnidadConvivenciaMiembro`
