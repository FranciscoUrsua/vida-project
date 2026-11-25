<?php

namespace Modules\Intervencion\Models;

use App\Traits\Auditable;
use App\Traits\Versionable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use LogicException;
use Modules\Documentos\Models\PlantillaInforme;
use Modules\Escalas\Models\TipoEscala;

/**
 * Área de un encargo profesional (jurídica, psicológica, formativa…) y su caja de
 * herramientas: las fichas de valoración, escalas y plantillas de informe que puede usar el
 * profesional del encargo (EA-1 y EA-2 de docs/instrucciones-cli/2026-11-encargo-actuaciones.md).
 *
 * Es una entidad y no un valor de `catalogos_sistema` porque el código decide con ella: filtra
 * las herramientas. El TSR la elige al encargar (EA-3). Se gestiona en el backoffice
 * (`AreaEncargoResource`). El código es estable: no cambia una vez creada. No se borra si
 * algún encargo la usa: se desactiva.
 *
 * Las plantillas pasan por el pivote AreaEncargoPlantillaInforme, que rechaza las que leen la
 * Historia Social (EA-9).
 *
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property string|null $descripcion
 * @property bool $activa
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
class AreaEncargo extends Model
{
    use Auditable;
    use SoftDeletes;
    use Versionable;

    /** @var string */
    protected $table = 'areas_encargo';

    /** @var list<string> */
    protected $fillable = ['codigo', 'nombre', 'descripcion', 'activa'];

    /** @var array<string, string> */
    protected $casts = [
        'activa' => 'boolean',
    ];

    /**
     * Registra las reglas del área: código estable y sin baja si hay encargos.
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::updating(fn (self $area) => $area->impedirCambioDeCodigo());
        static::deleting(fn (self $area) => $area->impedirBajaConEncargos());
    }

    /**
     * Fichas de valoración que ofrece el área.
     *
     * @return BelongsToMany<TipoFicha, $this>
     */
    public function tiposFicha(): BelongsToMany
    {
        return $this->belongsToMany(TipoFicha::class, 'area_encargo_tipo_ficha', 'area_encargo_id', 'tipo_ficha_id');
    }

    /**
     * Escalas que ofrece el área.
     *
     * @return BelongsToMany<TipoEscala, $this>
     */
    public function tiposEscala(): BelongsToMany
    {
        return $this->belongsToMany(TipoEscala::class, 'area_encargo_tipo_escala', 'area_encargo_id', 'tipo_escala_id');
    }

    /**
     * Plantillas de informe que ofrece el área (el pivote valida EA-9).
     *
     * @return BelongsToMany<PlantillaInforme, $this>
     */
    public function plantillasInforme(): BelongsToMany
    {
        return $this->belongsToMany(PlantillaInforme::class, 'area_encargo_plantilla_informe', 'area_encargo_id', 'plantilla_informe_id')
            ->using(AreaEncargoPlantillaInforme::class);
    }

    /**
     * Encargos hechos con esta área.
     *
     * @return HasMany<Derivacion, $this>
     */
    public function encargos(): HasMany
    {
        return $this->hasMany(Derivacion::class, 'area_encargo_id');
    }

    /**
     * Áreas que se pueden elegir al encargar.
     *
     * @param Builder<self> $query
     *
     * @return Builder<self>
     */
    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activa', true);
    }

    /**
     * El código identifica el área en seeders y configuración: no cambia.
     *
     * @return void
     *
     * @throws LogicException
     */
    private function impedirCambioDeCodigo(): void
    {
        if ($this->isDirty('codigo')) {
            throw new LogicException('El código de un área de encargo no se puede cambiar.');
        }
    }

    /**
     * Un área con encargos no se borra: los encargos tienen que seguir diciendo de qué eran.
     *
     * @return void
     *
     * @throws LogicException
     */
    private function impedirBajaConEncargos(): void
    {
        if (Derivacion::withTrashed()->where('area_encargo_id', $this->id)->exists()) {
            throw new LogicException("El área «{$this->nombre}» tiene encargos: desactívala en lugar de borrarla.");
        }
    }
}
