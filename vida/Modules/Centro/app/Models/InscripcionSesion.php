<?php

namespace Modules\Centro\Models;

use App\Models\Ciudadano;
use App\Models\User;
use App\Traits\Versionable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Centro\Enums\EstadoInscripcionSesion;
use Modules\Centro\Enums\ViaInscripcion;

/**
 * Plaza que ocupa una persona en una sesión de actividad (fase 6, docs/modulo-centros.md §2.6).
 *
 * La inscripción apunta siempre a una sesión. Solo las `inscrita` ocupan aforo, y solo
 * hay una vigente por persona y sesión. Nada se borra (F6-5): la baja es lógica, con
 * motivo, y la base de datos rechaza el DELETE. Se escribe solo desde
 * InscripcionActividadService.
 *
 * @property int $id
 * @property int $sesion_actividad_id
 * @property int $ciudadano_id
 * @property ViaInscripcion $via
 * @property int|null $prescripcion_id
 * @property EstadoInscripcionSesion $estado
 * @property int $inscrita_por_id
 * @property Carbon|null $fecha_baja
 * @property string|null $motivo_baja
 * @property int|null $baja_por_id
 * @property Carbon $created_at
 * @property-read SesionActividad $sesion
 * @property-read Ciudadano $ciudadano
 * @property-read Prescripcion|null $prescripcion
 * @property-read User $inscritaPor
 * @property-read User|null $bajaPor
 */
class InscripcionSesion extends Model
{
    use Versionable;

    protected $table = 'inscripciones_sesion';

    protected $fillable = [
        'sesion_actividad_id',
        'ciudadano_id',
        'via',
        'prescripcion_id',
        'estado',
        'inscrita_por_id',
        'fecha_baja',
        'motivo_baja',
        'baja_por_id',
    ];

    protected $casts = [
        'via' => ViaInscripcion::class,
        'estado' => EstadoInscripcionSesion::class,
        'fecha_baja' => 'datetime',
    ];

    /**
     * Sesión en la que está inscrita la persona.
     *
     * @return BelongsTo<SesionActividad, $this>
     */
    public function sesion(): BelongsTo
    {
        return $this->belongsTo(SesionActividad::class, 'sesion_actividad_id')->withTrashed();
    }

    /**
     * Persona inscrita, sin el scope de ámbito: quien lista inscritos la presenta con
     * PresentacionPersona, que oculta la identidad de las protegidas que no puede ver (RN-17).
     *
     * @return BelongsTo<Ciudadano, $this>
     */
    public function ciudadano(): BelongsTo
    {
        return $this->belongsTo(Ciudadano::class, 'ciudadano_id')->withoutGlobalScopes();
    }

    /**
     * Prescripción de la que nace, si la vía es `prescripcion`.
     *
     * @return BelongsTo<Prescripcion, $this>
     */
    public function prescripcion(): BelongsTo
    {
        return $this->belongsTo(Prescripcion::class, 'prescripcion_id');
    }

    /**
     * Usuario que inscribió a la persona (o que la prescribió).
     *
     * @return BelongsTo<User, $this>
     */
    public function inscritaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inscrita_por_id');
    }

    /**
     * Usuario que dio de baja la inscripción.
     *
     * @return BelongsTo<User, $this>
     */
    public function bajaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'baja_por_id');
    }

    /**
     * Si la inscripción ocupa plaza.
     *
     * @return bool
     */
    public function vigente(): bool
    {
        return $this->estado === EstadoInscripcionSesion::Inscrita;
    }

    /**
     * Inscripciones que ocupan plaza.
     *
     * @param Builder<self> $query
     *
     * @return Builder<self>
     */
    public function scopeVigentes(Builder $query): Builder
    {
        return $query->where('estado', EstadoInscripcionSesion::Inscrita->value);
    }
}
