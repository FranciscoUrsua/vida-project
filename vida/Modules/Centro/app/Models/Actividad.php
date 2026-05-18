<?php

namespace Modules\Centro\Models;

use App\Traits\Versionable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Actividad programada en un centro.
 *
 * modo_acceso: libre | prescripcion | mixta
 *   mixta = hay cupo para prescripciones y cupo libre simultáneamente.
 *
 * @property int $id
 * @property int $centro_id
 * @property int $tipo_actividad_id
 * @property string $nombre
 * @property string|null $descripcion
 * @property string $modo_acceso
 * @property int|null $aforo_total
 * @property int|null $aforo_prescripcion
 * @property bool $requiere_inscripcion_centro
 * @property bool $activa
 * @property \Illuminate\Support\Carbon $fecha_alta
 * @property \Illuminate\Support\Carbon|null $fecha_baja
 */
class Actividad extends Model
{
    use SoftDeletes;
    use Versionable;

    protected $table = 'actividades';

    protected $fillable = [
        'centro_id',
        'tipo_actividad_id',
        'nombre',
        'descripcion',
        'modo_acceso',
        'aforo_total',
        'aforo_prescripcion',
        'requiere_inscripcion_centro',
        'activa',
        'fecha_alta',
        'fecha_baja',
        'notas',
    ];

    protected $casts = [
        'aforo_total'                 => 'integer',
        'aforo_prescripcion'          => 'integer',
        'requiere_inscripcion_centro' => 'boolean',
        'activa'                      => 'boolean',
        'fecha_alta'                  => 'date',
        'fecha_baja'                  => 'date',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Centro en el que se desarrolla la actividad.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Modules\Centro\Models\Centro, self>
     */
    public function centro(): BelongsTo
    {
        return $this->belongsTo(Centro::class, 'centro_id');
    }

    /**
     * Tipo de actividad que clasifica esta actividad.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Modules\Centro\Models\TipoActividad, self>
     */
    public function tipoActividad(): BelongsTo
    {
        return $this->belongsTo(TipoActividad::class, 'tipo_actividad_id');
    }

    /**
     * Sesiones concretas planificadas para esta actividad.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\Modules\Centro\Models\SesionActividad, self>
     */
    public function sesiones(): HasMany
    {
        return $this->hasMany(SesionActividad::class, 'actividad_id');
    }

    // -------------------------------------------------------------------------
    // Métodos de negocio
    // -------------------------------------------------------------------------

    /**
     * Verifica que el ciudadano tiene inscripción activa en este centro.
     * Solo aplica si requiere_inscripcion_centro = true.
     *
     * @throws \InvalidArgumentException Si se requiere inscripción y el ciudadano no la tiene.
     */
    public function verificarInscripcionCentro(int $ciudadanoId): void
    {
        if (! $this->requiere_inscripcion_centro) {
            return;
        }

        $tieneInscripcion = InscripcionCentro::where('centro_id', $this->centro_id)
            ->where('ciudadano_id', $ciudadanoId)
            ->activas()
            ->exists();

        if (! $tieneInscripcion) {
            throw new \InvalidArgumentException(
                'El ciudadano no tiene inscripción activa en el centro. La actividad requiere inscripción previa.'
            );
        }
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Filtra actividades activas y sin fecha de baja.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activa', true)->whereNull('fecha_baja');
    }
}
