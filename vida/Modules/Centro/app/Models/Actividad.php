<?php

namespace Modules\Centro\Models;

use App\Traits\Versionable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Prestaciones\Models\Prestacion;
use Modules\Usuarios\Models\Profesional;

/**
 * Actividad programada en un centro.
 *
 * modo_acceso: libre | prescripcion | mixta
 *   mixta = hay cupo para prescripciones y cupo libre simultáneamente.
 *
 * @property int $id
 * @property int $centro_id
 * @property int $tipo_actividad_id
 * @property int|null $prestacion_id
 * @property string $nombre
 * @property string|null $descripcion
 * @property string $modo_acceso
 * @property int|null $aforo_total
 * @property int|null $aforo_prescripcion
 * @property bool $requiere_inscripcion_centro
 * @property bool $activa
 * @property Carbon $fecha_alta
 * @property Carbon|null $fecha_baja
 * @property string|null $motivo_baja
 * @property-read Centro $centro
 * @property-read Prestacion|null $prestacion
 * @property-read Collection<int, Profesional> $profesionales
 */
class Actividad extends Model
{
    use SoftDeletes;
    use Versionable;

    protected $table = 'actividades';

    protected $fillable = [
        'centro_id',
        'tipo_actividad_id',
        'prestacion_id',
        'nombre',
        'descripcion',
        'modo_acceso',
        'aforo_total',
        'aforo_prescripcion',
        'requiere_inscripcion_centro',
        'activa',
        'fecha_alta',
        'fecha_baja',
        'motivo_baja',
        'notas',
    ];

    protected $casts = [
        'aforo_total' => 'integer',
        'aforo_prescripcion' => 'integer',
        'requiere_inscripcion_centro' => 'boolean',
        'activa' => 'boolean',
        'fecha_alta' => 'date',
        'fecha_baja' => 'date',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Centro en el que se desarrolla la actividad.
     *
     * @return BelongsTo<Centro, $this>
     */
    public function centro(): BelongsTo
    {
        return $this->belongsTo(Centro::class, 'centro_id');
    }

    /**
     * Tipo de actividad que clasifica esta actividad.
     *
     * @return BelongsTo<TipoActividad, $this>
     */
    public function tipoActividad(): BelongsTo
    {
        return $this->belongsTo(TipoActividad::class, 'tipo_actividad_id');
    }

    /**
     * Prestación del catálogo vinculada: al inscribir a una persona se le crea la
     * prestación activa (fase 6, paso 2). Sin prestación, no se crea nada.
     *
     * @return BelongsTo<Prestacion, $this>
     */
    public function prestacion(): BelongsTo
    {
        return $this->belongsTo(Prestacion::class, 'prestacion_id');
    }

    /**
     * Sesiones concretas planificadas para esta actividad.
     *
     * @return HasMany<SesionActividad, $this>
     */
    public function sesiones(): HasMany
    {
        return $this->hasMany(SesionActividad::class, 'actividad_id');
    }

    /**
     * Profesionales responsables o coordinadores de la actividad.
     * La dirección de sesiones concretas se gestiona en sesion_actividad_profesional.
     *
     * @return BelongsToMany<Profesional, $this>
     */
    public function profesionales(): BelongsToMany
    {
        return $this->belongsToMany(Profesional::class, 'actividad_profesional')
            ->withTimestamps();
    }

    // -------------------------------------------------------------------------
    // Métodos de negocio
    // -------------------------------------------------------------------------

    /**
     * Aforo total de la actividad; las sesiones pueden sobrescribirlo. Null = sin límite.
     *
     * @return int|null
     */
    public function aforoEfectivo(): ?int
    {
        return $this->aforo_total;
    }

    /**
     * Cupo de prescripción; solo en las mixtas (en las demás, todo el aforo es de su vía).
     *
     * @return int|null
     */
    public function aforoPrescripcionEfectivo(): ?int
    {
        return $this->modo_acceso === 'mixta' ? $this->aforo_prescripcion : null;
    }

    /**
     * Si admite inscripciones: activa y sin baja (F6-5).
     *
     * @return bool
     */
    public function admiteInscripciones(): bool
    {
        return $this->activa && $this->fecha_baja === null;
    }

    /**
     * Si el ciudadano tiene inscripción activa en el centro de la actividad.
     *
     * @param int $ciudadanoId
     *
     * @return bool
     */
    public function ciudadanoInscritoEnCentro(int $ciudadanoId): bool
    {
        return InscripcionCentro::where('centro_id', $this->centro_id)
            ->where('ciudadano_id', $ciudadanoId)
            ->activas()
            ->exists();
    }

    /**
     * Verifica que el ciudadano tiene inscripción activa en este centro.
     * Solo aplica si requiere_inscripcion_centro = true.
     *
     * @param int $ciudadanoId ID del ciudadano a comprobar.
     *
     * @throws \InvalidArgumentException Si se requiere inscripción y el ciudadano no la tiene.
     */
    public function verificarInscripcionCentro(int $ciudadanoId): void
    {
        if (! $this->requiere_inscripcion_centro) {
            return;
        }

        if (! $this->ciudadanoInscritoEnCentro($ciudadanoId)) {
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
     * @param Builder<static> $query
     *
     * @return Builder<static>
     */
    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activa', true)->whereNull('fecha_baja');
    }
}
