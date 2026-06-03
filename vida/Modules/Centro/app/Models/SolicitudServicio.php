<?php

namespace Modules\Centro\Models;

use App\Models\Ciudadano;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Prestaciones\Models\Prestacion;
use Modules\Usuarios\Models\Profesional;

/**
 * Solicitud de tramitación de una prestación a un servicio.
 *
 * Se genera cuando un TSR prescribe desde un plan de intervención una
 * prestación asociada a un servicio. El responsable del servicio ve la
 * solicitud en su bandeja y gestiona la tramitación.
 *
 * Las anotaciones de seguimiento posteriores pertenecen al módulo de
 * Intervención como hechos de la historia social del ciudadano, no a
 * esta entidad.
 *
 * estado: pendiente | en_tramite | resuelta | denegada | derivada_externa
 *
 * Al pasar a estado 'resuelta', fecha_resolucion se registra automáticamente.
 *
 * @property int $id
 * @property int $servicio_id
 * @property int $ciudadano_id
 * @property int $profesional_id
 * @property int|null $plan_intervencion_id
 * @property int $prestacion_id
 * @property string $estado
 * @property Carbon $fecha_solicitud
 * @property Carbon|null $fecha_resolucion
 * @property string|null $notas
 */
class SolicitudServicio extends Model
{
    protected $table = 'solicitudes_servicio';

    protected $fillable = [
        'servicio_id',
        'ciudadano_id',
        'profesional_id',
        'plan_intervencion_id',
        'prestacion_id',
        'estado',
        'fecha_solicitud',
        'fecha_resolucion',
        'notas',
    ];

    protected $casts = [
        'fecha_solicitud' => 'date',
        'fecha_resolucion' => 'date',
    ];

    // -------------------------------------------------------------------------
    // Lógica de dominio en modelo
    // -------------------------------------------------------------------------

    /**
     * Al pasar al estado 'resuelta', registra automáticamente la fecha de resolución.
     */
    protected static function booted(): void
    {
        static::saving(function (self $solicitud) {
            if ($solicitud->isDirty('estado') && $solicitud->estado === 'resuelta') {
                $solicitud->fecha_resolucion ??= today();
            }
        });
    }

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Servicio destinatario de la solicitud.
     *
     * @return BelongsTo<Servicio, self>
     */
    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class, 'servicio_id');
    }

    /**
     * Ciudadano beneficiario de la tramitación.
     *
     * @return BelongsTo<Ciudadano, self>
     */
    public function ciudadano(): BelongsTo
    {
        return $this->belongsTo(Ciudadano::class, 'ciudadano_id');
    }

    /**
     * TSR que generó la solicitud.
     *
     * @return BelongsTo<Profesional, self>
     */
    public function profesional(): BelongsTo
    {
        return $this->belongsTo(Profesional::class, 'profesional_id');
    }

    /**
     * Plan de intervención en cuyo contexto se generó la solicitud.
     *
     * @return BelongsTo<PlanDeIntervencion, self>
     */
    public function planIntervencion(): BelongsTo
    {
        return $this->belongsTo(PlanDeIntervencion::class, 'plan_intervencion_id');
    }

    /**
     * Prestación solicitada.
     *
     * @return BelongsTo<Prestacion, self>
     */
    public function prestacion(): BelongsTo
    {
        return $this->belongsTo(Prestacion::class, 'prestacion_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Filtra solicitudes pendientes de tramitar.
     *
     * @param Builder<static> $query
     *
     * @return Builder<static>
     */
    public function scopePendientes(Builder $query): Builder
    {
        return $query->where('estado', 'pendiente');
    }
}
