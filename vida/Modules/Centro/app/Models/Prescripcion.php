<?php

namespace Modules\Centro\Models;

use App\Traits\Versionable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Usuarios\Models\Profesional;

/**
 * Prescripción de un recurso (plaza o sesión de actividad) para un ciudadano.
 *
 * tipo_destino + destino_id implementan una relación polimórfica manual:
 *   - 'coleccion_plazas' → destino_id apunta a colecciones_plazas.id
 *   - 'sesion_actividad'  → destino_id apunta a sesiones_actividad.id
 *
 * estado: pendiente | en_lista_espera | asignada | activa | finalizada | cancelada
 *
 * @property int $id
 * @property int $profesional_id
 * @property int $ciudadano_id
 * @property int|null $plan_intervencion_id
 * @property string $tipo_destino
 * @property int $destino_id
 * @property int|null $plaza_id
 * @property string $estado
 * @property \Illuminate\Support\Carbon $fecha_prescripcion
 * @property \Illuminate\Support\Carbon|null $fecha_inicio
 * @property \Illuminate\Support\Carbon|null $fecha_fin
 */
class Prescripcion extends Model
{
    use SoftDeletes;
    use Versionable;

    protected $table = 'prescripciones';

    protected $fillable = [
        'profesional_id',
        'ciudadano_id',
        'plan_intervencion_id',
        'tipo_destino',
        'destino_id',
        'plaza_id',
        'estado',
        'fecha_prescripcion',
        'fecha_asignacion',
        'fecha_inicio',
        'fecha_fin',
        'motivo_cancelacion',
        'notas',
    ];

    protected $casts = [
        'fecha_prescripcion' => 'date',
        'fecha_asignacion'   => 'date',
        'fecha_inicio'       => 'date',
        'fecha_fin'          => 'date',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Profesional que emite la prescripción.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Modules\Usuarios\Models\Profesional, self>
     */
    public function profesional(): BelongsTo
    {
        return $this->belongsTo(Profesional::class, 'profesional_id');
    }

    /**
     * Ciudadano beneficiario de la prescripción.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Modules\Centro\Models\Ciudadano, self>
     */
    public function ciudadano(): BelongsTo
    {
        return $this->belongsTo(Ciudadano::class, 'ciudadano_id');
    }

    /**
     * Plaza concreta asignada (nullable hasta la asignación efectiva).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Modules\Centro\Models\Plaza, self>
     */
    public function plaza(): BelongsTo
    {
        return $this->belongsTo(Plaza::class, 'plaza_id');
    }

    /**
     * Registro de lista de espera generado por esta prescripción.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne<\Modules\Centro\Models\ListaEspera, self>
     */
    public function listaEspera(): HasOne
    {
        return $this->hasOne(ListaEspera::class, 'prescripcion_id');
    }

    /**
     * Resuelve el destino de la prescripción según tipo_destino.
     * Devuelve la ColeccionPlazas o la SesionActividad correspondiente.
     *
     * @return \Modules\Centro\Models\ColeccionPlazas|\Modules\Centro\Models\SesionActividad|null
     */
    public function destino(): ColeccionPlazas|SesionActividad|null
    {
        return match ($this->tipo_destino) {
            'coleccion_plazas' => ColeccionPlazas::find($this->destino_id),
            'sesion_actividad' => SesionActividad::find($this->destino_id),
            default            => null,
        };
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Filtra prescripciones con estado activa.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('estado', 'activa');
    }

    /**
     * Filtra prescripciones con estado pendiente.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopePendientes(Builder $query): Builder
    {
        return $query->where('estado', 'pendiente');
    }
}
