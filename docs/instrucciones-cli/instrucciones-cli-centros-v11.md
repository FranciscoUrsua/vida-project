# Instrucciones — Módulo Centros v1.1: UO, ámbito territorial y tests

## Contexto

Trabajas en el proyecto **VIDA 360**. El código vive en `vida/`. Todas las instrucciones se ejecutan desde ahí.

Estos cambios implementan las novedades de la **versión 1.1** del documento funcional `docs/modulo-centros.md`. Léelo antes de empezar. Es la fuente de verdad.

Lee también `CLAUDE.md` para recordar las convenciones del proyecto: módulos en `Modules/`, migraciones numeradas en `database/migrations/`, traits `Auditable` y `Versionable`.

Los tests usan **PostgreSQL**. No uses SQLite ni bases de datos en memoria. El entorno de test debe estar configurado en `.env.testing` con una base de datos PostgreSQL separada. Verifica que existe antes de ejecutar los tests.

---

## Resumen de cambios

1. **Migración de alteración**: añadir `unidad_organizativa_id` a `centros` si no existe; retirar `distrito_id` sustituyéndolo por `AmbitoTerritorial`.
2. **Nueva migración**: tabla `ambitos_territoriales`.
3. **Nuevo modelo**: `AmbitoTerritorial` con sus validaciones.
4. **Actualizar modelo `Centro`**: relación con UO, relación con `AmbitoTerritorial`, retirar relación con `Distrito` si existe.
5. **Actualizar `CentroResource` en Filament**: reflejar los nuevos campos.
6. **Tests funcionales**: implementar los 25 tests de la sección 9 del documento.

---

## Paso 0 — Exploración previa obligatoria

Antes de escribir cualquier código, ejecuta estas inspecciones y toma nota de los resultados. Lo que encuentres aquí determina exactamente qué hay que hacer en los pasos siguientes.

```bash
# ¿Qué columnas tiene la tabla centros actualmente?
grep -rn "centros" database/migrations/ | grep "create_centros\|table.*centros" | head -5

# ¿Existe ya unidad_organizativa_id en centros?
grep -rn "unidad_organizativa_id" database/migrations/

# ¿Existe distrito_id en centros?
grep -rn "distrito_id" database/migrations/

# ¿Existe ya la tabla ambitos_territoriales?
grep -rn "ambitos_territoriales" database/migrations/

# ¿Cuál es el número de migración más alto?
ls database/migrations/ | sort | tail -5

# ¿Qué tiene actualmente el modelo Centro?
cat Modules/Centro/app/Models/Centro.php

# ¿Qué tiene actualmente CentroResource?
find . -name "CentroResource.php" | head -3

# ¿Existe una base de datos de test configurada?
cat .env.testing 2>/dev/null || echo "No existe .env.testing"

# ¿Existe ya algún test de centros?
find tests/ -name "*Centro*" -o -name "*centro*" 2>/dev/null
```

Con esta información en mano, procede a los pasos siguientes.

---

## Paso 1 — Migración de alteración de `centros`

Crea una nueva migración de alteración. Número correlativo al más alto encontrado en el paso 0.

**Nombre sugerido**: `XXXX_add_unidad_organizativa_remove_distrito_to_centros.php`

```php
public function up(): void
{
    Schema::table('centros', function (Blueprint $table) {
        // Añadir unidad_organizativa_id solo si no existe (comprueba en paso 0)
        if (!Schema::hasColumn('centros', 'unidad_organizativa_id')) {
            $table->unsignedBigInteger('unidad_organizativa_id')
                ->nullable()
                ->after('tipo_gestion');
            $table->foreign('unidad_organizativa_id')
                ->references('id')
                ->on('unidades_organizativas')
                ->nullOnDelete();
        }

        // Retirar distrito_id solo si existe (comprueba en paso 0)
        if (Schema::hasColumn('centros', 'distrito_id')) {
            $table->dropForeign(['distrito_id']);
            $table->dropColumn('distrito_id');
        }
    });
}

public function down(): void
{
    Schema::table('centros', function (Blueprint $table) {
        if (Schema::hasColumn('centros', 'unidad_organizativa_id')) {
            $table->dropForeign(['unidad_organizativa_id']);
            $table->dropColumn('unidad_organizativa_id');
        }
        if (!Schema::hasColumn('centros', 'distrito_id')) {
            $table->unsignedBigInteger('distrito_id')->nullable();
        }
    });
}
```

> **Importante**: el nombre exacto de la tabla `unidades_organizativas` puede variar. Compruébalo en las migraciones existentes antes de escribir la FK.

---

## Paso 2 — Migración de `ambitos_territoriales`

Crea una nueva migración. Número correlativo inmediatamente posterior al del paso 1.

**Nombre sugerido**: `XXXX_create_ambitos_territoriales_table.php`

```php
public function up(): void
{
    Schema::create('ambitos_territoriales', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('centro_id');
        $table->foreign('centro_id')
            ->references('id')
            ->on('centros')
            ->cascadeOnDelete();

        // Enum como string — patrón consistente con el resto del proyecto
        $table->string('tipo'); // ciudad_completa | demarcacion_oficial | barrios | secciones_censales | poligono_gis

        $table->string('descripcion');
            // Nombre legible. Ej: "Distrito de Vallecas", "Barrio de Lavapiés"

        $table->unsignedBigInteger('referencia_id')->nullable();
            // ID de la entidad referenciada (distrito, barrio...) según tipo

        $table->string('referencia_tipo')->nullable();
            // Nombre del modelo referenciado. Ej: 'Distrito', 'Barrio'

        $table->json('geojson')->nullable();
            // Solo para tipo poligono_gis

        $table->timestamps();

        $table->index('centro_id');
        $table->index(['centro_id', 'tipo']);
    });
}

public function down(): void
{
    Schema::dropIfExists('ambitos_territoriales');
}
```

---

## Paso 3 — Modelo `AmbitoTerritorial`

Crea el modelo en `Modules/Centro/app/Models/AmbitoTerritorial.php`.

```php
<?php

namespace Modules\Centro\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ámbito territorial de atención de un centro.
 *
 * Define la población geográfica a la que atiende el centro.
 * Un centro puede tener varios registros combinando tipos distintos.
 * Si existe un registro de tipo 'ciudad_completa', no puede coexistir
 * con ningún otro ámbito para ese mismo centro.
 */
class AmbitoTerritorial extends Model
{
    public const TIPOS = [
        'ciudad_completa',
        'demarcacion_oficial',
        'barrios',
        'secciones_censales',
        'poligono_gis',
    ];

    protected $table = 'ambitos_territoriales';

    protected $fillable = [
        'centro_id',
        'tipo',
        'descripcion',
        'referencia_id',
        'referencia_tipo',
        'geojson',
    ];

    protected $casts = [
        'geojson' => 'array',
    ];

    // ── Relaciones ─────────────────────────────────────────────────────

    public function centro(): BelongsTo
    {
        return $this->belongsTo(Centro::class);
    }

    // ── Validaciones de modelo ──────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (AmbitoTerritorial $ambito) {
            $ambito->validarCoherencia();
        });

        static::updating(function (AmbitoTerritorial $ambito) {
            $ambito->validarCoherencia();
        });
    }

    /**
     * Valida las reglas de coherencia del ámbito:
     * - ciudad_completa no puede coexistir con otros ámbitos del mismo centro.
     * - poligono_gis requiere geojson.
     *
     * @throws \InvalidArgumentException
     */
    protected function validarCoherencia(): void
    {
        // poligono_gis requiere geojson
        if ($this->tipo === 'poligono_gis' && empty($this->geojson)) {
            throw new \InvalidArgumentException(
                'Un ámbito de tipo poligono_gis requiere el campo geojson.'
            );
        }

        // ciudad_completa no puede coexistir con otros ámbitos
        $query = static::where('centro_id', $this->centro_id)
            ->when($this->exists, fn($q) => $q->where('id', '!=', $this->id));

        if ($this->tipo === 'ciudad_completa' && $query->exists()) {
            throw new \InvalidArgumentException(
                'Un centro con ámbito ciudad_completa no puede tener otros ámbitos.'
            );
        }

        if ($this->tipo !== 'ciudad_completa' && $query->where('tipo', 'ciudad_completa')->exists()) {
            throw new \InvalidArgumentException(
                'No se pueden añadir ámbitos adicionales a un centro con ámbito ciudad_completa.'
            );
        }
    }
}
```

---

## Paso 4 — Actualizar modelo `Centro`

Abre `Modules/Centro/app/Models/Centro.php` y aplica los siguientes cambios. No reescribas el archivo desde cero; edita quirúrgicamente lo que ya existe.

**Añadir import** (si no está):
```php
use Modules\Centro\Models\AmbitoTerritorial;
// Y el modelo de UO — comprueba su namespace exacto en el paso 0
use Modules\Organizacion\Models\UnidadOrganizativa; // ajusta el namespace si difiere
```

**Añadir `unidad_organizativa_id` a `$fillable`** si no está.

**Retirar `distrito_id` de `$fillable`** si está.

**Añadir relaciones** (si no existen):
```php
/**
 * UO a la que pertenece administrativamente el centro.
 * Nullable: centros externos o privados puros pueden no tener UO municipal.
 */
public function unidadOrganizativa(): BelongsTo
{
    return $this->belongsTo(UnidadOrganizativa::class);
}

/**
 * Ámbitos territoriales de atención del centro.
 * Un centro puede tener varios ámbitos combinando tipos distintos.
 */
public function ambitosTeritoriales(): HasMany
{
    return $this->hasMany(AmbitoTerritorial::class);
}
```

**Retirar la relación `distrito()`** si existe, o dejarla con un comentario `@deprecated` si hay código que todavía la usa (no la rompas sin saber quién la consume).

---

## Paso 5 — Actualizar `CentroResource` en Filament

Abre el `CentroResource` existente y aplica estos cambios en el formulario:

**Retirar** el campo `distrito_id` del formulario (o marcarlo como deprecated si hay datos existentes que requieren migración visual).

**Añadir** en la sección "Identificación" o "Organización":
```php
Select::make('unidad_organizativa_id')
    ->label('Unidad organizativa')
    ->relationship('unidadOrganizativa', 'nombre')
    ->searchable()
    ->preload()
    ->nullable()
    ->helperText('UO de la que depende administrativamente este centro. Dejar vacío para centros externos o privados.'),
```

**Añadir** un `RelationManager` para `AmbitoTerritorial` en el resource de Centro:

Crea `Modules/Centro/app/Filament/Resources/CentroResource/RelationManagers/AmbitosTerritorialesRelationManager.php`:

- Tabla: columnas `tipo`, `descripcion`.
- Formulario:
  - `Select::make('tipo')` con opciones de `AmbitoTerritorial::TIPOS`.
  - `TextInput::make('descripcion')->required()`.
  - `TextInput::make('referencia_id')->numeric()->nullable()`.
  - `TextInput::make('referencia_tipo')->nullable()`.
  - `Textarea::make('geojson')->nullable()->helperText('Solo para tipo poligono_gis. Formato GeoJSON.')`.
- Registra el RelationManager en el método `getRelations()` de `CentroResource`.

---

## Paso 6 — Ejecutar las migraciones

```bash
php artisan migrate
```

Si hay errores de FK o de columnas ya existentes, revisa los condicionales del paso 0. Nunca uses `--force` en producción.

---

## Paso 7 — Tests funcionales

Crea el directorio y los archivos de test:

```bash
mkdir -p tests/Feature/Modules/Centro
```

### Estructura de archivos

Crea un archivo por grupo de tests, siguiendo la sección 9 del documento `docs/modulo-centros.md`:

- `tests/Feature/Modules/Centro/CentroUoTest.php`
- `tests/Feature/Modules/Centro/AmbitoTerritorialTest.php`
- `tests/Feature/Modules/Centro/RedCentrosTest.php`
- `tests/Feature/Modules/Centro/ColeccionPlazasTest.php`
- `tests/Feature/Modules/Centro/PrescripcionTest.php`
- `tests/Feature/Modules/Centro/InscripcionCentroTest.php`
- `tests/Feature/Modules/Centro/DirectorCentroTest.php`

### Convenciones de los tests

- Extienden `Tests\TestCase`.
- Usan el trait `RefreshDatabase` para limpiar y remontar el esquema en cada test sobre la base de datos de test de PostgreSQL.
- Usan factories para crear datos. Si no existen factories para algún modelo del módulo Centro, créalas en `Modules/Centro/database/factories/` o en `database/factories/` según el patrón del proyecto.
- Los nombres de método siguen el patrón `snake_case` descriptivo de la sección 9. Ejemplo: `test_un_centro_puede_pertenecer_a_una_uo`.
- Añade el prefijo `test_` a cada método, o usa el atributo `#[Test]` de PHPUnit si es el patrón del proyecto. Comprueba cuál se usa en los tests existentes.
- No uses mocks para lógica de base de datos; los tests son de feature y trabajan contra PostgreSQL real.

### Factories necesarias

Antes de escribir los tests, comprueba qué factories existen:

```bash
find . -name "*Factory*" -path "*/Centro/*"
find database/factories -name "*.php" | head -20
```

Si faltan factories para `Centro`, `ColeccionPlazas`, `Espacio`, `Plaza`, `Red`, `AmbitoTerritorial`, `DirectorCentro`, `InscripcionCentro`, créalas con los campos mínimos para que los tests funcionen. Las factories deben producir datos válidos por defecto (estados coherentes, enums con valores correctos).

### Ejemplo de estructura de un test

```php
<?php

namespace Tests\Feature\Modules\Centro;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centro\Models\AmbitoTerritorial;
use Modules\Centro\Models\Centro;
use Tests\TestCase;

class AmbitoTerritorialTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_un_centro_puede_tener_ambito_ciudad_completa(): void
    {
        $centro = Centro::factory()->create();

        $ambito = AmbitoTerritorial::create([
            'centro_id'   => $centro->id,
            'tipo'        => 'ciudad_completa',
            'descripcion' => 'Todo el municipio de Madrid',
        ]);

        $this->assertEquals(1, $centro->ambitosTeritoriales()->count());
        $this->assertEquals('ciudad_completa', $ambito->tipo);
    }

    /** @test */
    public function test_ciudad_completa_no_puede_coexistir_con_otros_ambitos(): void
    {
        $centro = Centro::factory()->create();

        AmbitoTerritorial::create([
            'centro_id'   => $centro->id,
            'tipo'        => 'ciudad_completa',
            'descripcion' => 'Todo Madrid',
        ]);

        $this->expectException(\InvalidArgumentException::class);

        AmbitoTerritorial::create([
            'centro_id'    => $centro->id,
            'tipo'         => 'demarcacion_oficial',
            'descripcion'  => 'Distrito de Vallecas',
            'referencia_id' => 1,
        ]);
    }

    // ... resto de tests del grupo
}
```

### Tests que requieren atención especial

**`test_al_liberarse_una_plaza_se_genera_alerta_al_tsr_activo`** y **`test_la_asignacion_no_es_automatica_al_liberarse_plaza`**: estos tests validan comportamiento de negocio que puede no estar implementado todavía en el modelo o en un servicio. Si la lógica de liberación de plaza y generación de alerta no existe aún, crea el test marcándolo con `$this->markTestIncomplete('Pendiente: lógica de liberación de plaza no implementada')` y añade un comentario explicando qué debe implementarse. No inventes lógica de negocio para que el test pase.

**`test_al_nombrar_nuevo_director_el_anterior_recibe_fecha_fin`**: este test implica lógica de negocio en el modelo `DirectorCentro` o en un método de `Centro`. Si no existe, créala como parte de este paso: un método `Centro::nombrarDirector(array $datos): DirectorCentro` que cierra el director activo y abre el nuevo.

---

## Paso 8 — Ejecutar los tests

```bash
# Todos los tests del módulo Centro
composer run test -- --filter="Modules\\\\Centro"

# O por archivo
composer run test -- tests/Feature/Modules/Centro/AmbitoTerritorialTest.php
composer run test -- tests/Feature/Modules/Centro/RedCentrosTest.php
composer run test -- tests/Feature/Modules/Centro/CentroUoTest.php

# Todos a la vez
composer run test -- tests/Feature/Modules/Centro/
```

Todos los tests deben pasar en verde antes de dar el trabajo por terminado. Si algún test falla por lógica de negocio no implementada, márcalo con `markTestIncomplete` como se indica arriba — no lo comentes ni lo elimines.

---

## Paso 9 — Actualizar el CHANGELOG

Añade una entrada en `CHANGELOG.md` bajo el módulo Centro con la fecha de hoy:

```markdown
## Módulo Centro — v1.1 — YYYY-MM-DD

### Cambios
- Migración de alteración: añadido `unidad_organizativa_id` (FK nullable a `unidades_organizativas`) en `centros`.
- Migración de alteración: retirado `distrito_id` de `centros` (sustituido por `AmbitoTerritorial`).
- Nueva tabla `ambitos_territoriales`: tipos `ciudad_completa`, `demarcacion_oficial`, `barrios`, `secciones_censales`, `poligono_gis`.
- Nuevo modelo `AmbitoTerritorial` con validación de coherencia (ciudad_completa excluyente, poligono_gis requiere geojson).
- Modelo `Centro` actualizado: relación `unidadOrganizativa()`, relación `ambitosTeritoriales()`.
- `CentroResource` actualizado: campo UO, RelationManager de ámbitos territoriales.
- 25 tests funcionales en `tests/Feature/Modules/Centro/`.
```

---

## Qué no hacer

- No toques migraciones ya ejecutadas. Solo crea migraciones nuevas de alteración.
- No reescribas el modelo `Centro` desde cero; edita quirúrgicamente.
- No implementes lógica de consulta espacial GIS (diferida al módulo de Integraciones según `docs/modulo-centros.md` sección 7).
- No añadas lógica de prescripción ni lista de espera en este paso.
- No uses `php artisan migrate:fresh` en entornos con datos; usa `php artisan migrate`.
