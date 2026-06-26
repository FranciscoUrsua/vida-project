<?php

namespace Modules\Centro\Models;

use App\Traits\Versionable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Usuarios\Models\Profesional;

/**
 * Sesión concreta de una actividad.
 *
 * Los aforos de sesión sobreescriben los de la actividad cuando se informan.
 *
 * estado: programada | celebrada | cancelada
 *
 * @property int $id
 * @property int $actividad_id
 * @property Carbon $fecha
 * @property string $hora_inicio
 * @property string|null $hora_fin
 * @property int|null $aforo_total
 * @property int|null $aforo_prescripcion
 * @property string $estado
 * @property int|null $sala_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Profesional> $profesionales
 */
class SesionActividad extends Model
{
    use SoftDeletes;
    use Versionable;

    protected $table = 'sesiones_actividad';

    protected $fillable = [
        'actividad_id',
        'fecha',
        'hora_inicio',
        'hora_fin',
        'aforo_total',
        'aforo_prescripcion',
        'estado',
        'sala_id',
        'notas',
    ];

    protected $casts = [
        'fecha' => 'date',
        'aforo_total' => 'integer',
        'aforo_prescripcion' => 'integer',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Actividad a la que pertenece esta sesión.
     *
     * @return BelongsTo<Actividad, $this>
     */
    public function actividad(): BelongsTo
    {
        return $this->belongsTo(Actividad::class, 'actividad_id');
    }

    /**
     * Sala donde se celebra la sesión. Nullable: una sesión puede no tener sala asignada
     * (actividad exterior, itinerante u online). La disponibilidad de la sala no se
     * valida en este módulo; corresponde al módulo de Agenda.
     *
     * @return BelongsTo<Sala, $this>
     */
    public function sala(): BelongsTo
    {
        return $this->belongsTo(Sala::class);
    }

    /**
     * Profesionales que dirigen esta sesión concreta.
     * Permite rotación de profesionales sesión a sesión independientemente de la actividad.
     *
     * @return BelongsToMany<Profesional, $this>
     */
    public function profesionales(): BelongsToMany
    {
        return $this->belongsToMany(Profesional::class, 'sesion_actividad_profesional')
            ->withTimestamps();
    }

    /**
     * Prescripciones dirigidas a esta sesión de actividad.
     *
     * @return HasMany<Prescripcion, $this>
     */
    public function prescripciones(): HasMany
    {
        return $this->hasMany(Prescripcion::class, 'destino_id')
            ->where('tipo_destino', 'sesion_actividad');
    }

    // -------------------------------------------------------------------------
    // Accesores
    // -------------------------------------------------------------------------

    /**
     * Plazas disponibles: aforo efectivo menos prescripciones activas o asignadas.
     * Usa el aforo de la sesión si está definido, o el de la actividad como fallback.
     */
    public function getAforoDisponibleAttribute(): int
    {
        $aforo = $this->aforo_total ?? $this->actividad?->aforo_total;

        if ($aforo === null) {
            return PHP_INT_MAX; // sin límite
        }

        $ocupadas = $this->prescripciones()
            ->whereIn('estado', ['activa', 'asignada', 'pendiente'])
            ->count();

        return max(0, $aforo - $ocupadas);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Filtra sesiones con estado programada.
     *
     * @param Builder<static> $query
     *
     * @return Builder<static>
     */
    public function scopeProgramadas(Builder $query): Builder
    {
        return $query->where('estado', 'programada');
    }
}
