# Instrucciones CLI — Actualizar `docs/modulo-intervencion.md` (firma y seguimiento)

## Cambio 1 — Sección 5.3: reemplazar ciclo de firma

Localiza el bloque `### 5.3 Firma del plan` completo (desde esa línea hasta el
inicio de `### 5.4`) y reemplázalo por:

```markdown
### 5.3 Firma del plan y condiciones de seguimiento

La sección final del plan agrupa las condiciones de seguimiento y el registro
de firmas. Son las últimas dos subsecciones antes de que el plan pueda activarse.

**Condiciones de seguimiento**

El plan define la periodicidad de revisión acordada entre el TSR y el ciudadano.

| Campo | Tipo | Descripción |
|---|---|---|
| `periodicidad_seguimiento` | enum | `bimensual` / `trimestral` / `cuatrimestral` / `semestral` |
| `observaciones_seguimiento` | text nullable | Acuerdos o matices sobre el seguimiento (ej: "se revisará antes si hay cambio de empleo") |

**Firma**

La firma es presencial y manuscrita. El flujo en la versión actual es:

1. El TSR genera el PDF desde la página del plan y lo imprime.
2. Ambas partes firman en papel durante la entrevista.
3. El TSR marca en el sistema que cada parte ha firmado, registrando la fecha.
4. Cuando ambas firmas están marcadas, el plan puede pasar a estado `activo`.

No se requiere subir el documento escaneado para activar el plan. La gestión
de documentos adjuntos (escaneados, certificados, nóminas, justificantes) es
funcionalidad del módulo de Documentos, pendiente de implementar, y no bloquea
el flujo del plan.

**Tabla `firmas_plan`**:

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | PK | |
| `plan_id` | FK | |
| `version` | integer | Versión del plan firmada |
| `profesional_firmado` | boolean | El TSR ha firmado en papel |
| `profesional_firmado_en` | timestamp nullable | Fecha en que el TSR marcó su firma |
| `ciudadano_firmado` | boolean | El ciudadano ha firmado en papel |
| `ciudadano_firmado_en` | timestamp nullable | Fecha en que el TSR marcó la firma del ciudadano |
| `metodo_firma` | enum | `manuscrita` (único método activo; `digital_certificada` previsto) |
| `fecha_firma` | date nullable | Fecha de la firma presencial (la registra el TSR) |
| `documento_firmado_id` | FK nullable | Reservado para cuando exista el módulo de Documentos |

El plan no pasa a estado `activo` sin un registro `FirmaPlan` con
`profesional_firmado = true` AND `ciudadano_firmado = true` para la versión
actual (verificado por `PlanDeIntervencion::estaFirmado()`).

El método `estaFirmado()` existente debe actualizarse para evaluar estos dos
booleanos en lugar de los campos anteriores `firma_ciudadano` y
`firma_profesional` (blobs). Ver sección de referencias de código.
```

---

## Cambio 2 — Tabla `firmas_plan` en migración

Este cambio implica también actualizar la migración `firmas_plan` existente.
Localiza en el CHANGELOG o en las migraciones la definición de `firmas_plan`
y verifica que tiene los campos `profesional_firmado`, `profesional_firmado_en`,
`ciudadano_firmado`, `ciudadano_firmado_en`. Si la tabla tiene los campos blob
`firma_ciudadano` y `firma_profesional` del diseño anterior, crea una migración
de alteración:

```
Modules/Intervencion/database/migrations/2026_06_16_000014_update_firmas_plan_fields.php
```

Con el siguiente contenido:

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
            // Eliminar campos blob del diseño anterior si existen
            if (Schema::hasColumn('firmas_plan', 'firma_ciudadano')) {
                $table->dropColumn('firma_ciudadano');
            }
            if (Schema::hasColumn('firmas_plan', 'firma_profesional')) {
                $table->dropColumn('firma_profesional');
            }

            // Añadir campos del diseño actual si no existen
            if (! Schema::hasColumn('firmas_plan', 'profesional_firmado')) {
                $table->boolean('profesional_firmado')->default(false)->after('version');
            }
            if (! Schema::hasColumn('firmas_plan', 'profesional_firmado_en')) {
                $table->timestamp('profesional_firmado_en')->nullable()->after('profesional_firmado');
            }
            if (! Schema::hasColumn('firmas_plan', 'ciudadano_firmado')) {
                $table->boolean('ciudadano_firmado')->default(false)->after('profesional_firmado_en');
            }
            if (! Schema::hasColumn('firmas_plan', 'ciudadano_firmado_en')) {
                $table->timestamp('ciudadano_firmado_en')->nullable()->after('ciudadano_firmado');
            }
            if (! Schema::hasColumn('firmas_plan', 'observaciones_seguimiento')) {
                $table->text('observaciones_seguimiento')->nullable()->after('ciudadano_firmado_en');
            }
        });

        // Actualizar enum de periodicidad_seguimiento en planes_intervencion
        // para incluir 'cuatrimestral' si no está
        // Nota: en PostgreSQL ALTER COLUMN para enum requiere USING; verificar
        // si la columna ya tiene 'cuatrimestral' antes de modificar.
        // Si es SQLite (tests), el enum es un string, no hay problema.
    }

    public function down(): void
    {
        Schema::table('firmas_plan', function (Blueprint $table) {
            $table->dropColumn([
                'profesional_firmado', 'profesional_firmado_en',
                'ciudadano_firmado', 'ciudadano_firmado_en',
                'observaciones_seguimiento',
            ]);
        });
    }
};
```

---

## Cambio 3 — Actualizar `FirmaPlan` model

En `Modules/Intervencion/app/Models/FirmaPlan.php`, actualiza `$fillable`
y `$casts`, y añade el método `estaCompleta()`:

```php
protected $fillable = [
    'plan_id', 'version',
    'profesional_firmado', 'profesional_firmado_en',
    'ciudadano_firmado', 'ciudadano_firmado_en',
    'metodo_firma', 'fecha_firma', 'documento_firmado_id',
    'observaciones_seguimiento',
];

protected $casts = [
    'profesional_firmado'    => 'boolean',
    'ciudadano_firmado'      => 'boolean',
    'profesional_firmado_en' => 'datetime',
    'ciudadano_firmado_en'   => 'datetime',
    'fecha_firma'            => 'date',
];

/**
 * La firma está completa cuando ambas partes han firmado en papel.
 */
public function estaCompleta(): bool
{
    return $this->profesional_firmado && $this->ciudadano_firmado;
}
```

---

## Cambio 4 — Actualizar `estaFirmado()` en `PlanDeIntervencion`

En `Modules/Intervencion/app/Models/PlanDeIntervencion.php`, actualiza el
método `estaFirmado()` para usar los nuevos campos booleanos:

```php
public function estaFirmado(): bool
{
    return $this->firmas()
        ->where('version', $this->version)
        ->where('profesional_firmado', true)
        ->where('ciudadano_firmado', true)
        ->exists();
}
```

---

## Cambio 5 — Actualizar enum `periodicidad_seguimiento`

En `planes_intervencion`, el enum actual era:
`mensual / bimensual / trimestral / semestral / anual`

Según la conversación, las opciones acordadas son:
`bimensual / trimestral / cuatrimestral / semestral`

Crea una migración si la columna ya existe con el enum anterior:

```php
// En la migración de alteración, si es PostgreSQL:
// DB::statement("ALTER TABLE planes_intervencion
//   DROP CONSTRAINT IF EXISTS planes_intervencion_periodicidad_seguimiento_check");
// DB::statement("ALTER TABLE planes_intervencion
//   ADD CONSTRAINT planes_intervencion_periodicidad_seguimiento_check
//   CHECK (periodicidad_seguimiento IN
//   ('bimensual','trimestral','cuatrimestral','semestral'))");

// Si la tabla aún no existe (primer deploy), la migración de creación
// ya incluirá el enum correcto.
```

Actualiza también el modelo `PlanDeIntervencion` si tiene el enum en un cast
o validación explícita.

---

## Checklist

- [ ] `docs/modulo-intervencion.md` sección 5.3 actualizada
- [ ] Migración `update_firmas_plan_fields` creada y ejecutada sin errores
- [ ] `FirmaPlan::estaCompleta()` devuelve true solo con ambos booleanos en true
- [ ] `PlanDeIntervencion::estaFirmado()` actualizado y tests TF-INT-B04 a B08 siguen pasando
- [ ] Enum `periodicidad_seguimiento` refleja las 4 opciones acordadas
- [ ] Commit: `refactor(intervencion): firma simplificada a booleanos + condiciones seguimiento`
