<?php

namespace Modules\Centro\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Usuarios\Models\Profesional;

/**
 * Responsable de un servicio en un periodo dado.
 *
 * El cargo es un atributo del servicio, no del profesional: el nombre del
 * cargo se lee de $this->servicio->cargo_nombre. El profesional asume ese
 * cargo al ser nombrado y lo deja al cesar.
 *
 * A diferencia de DirectorCentro, no existe la figura de responsable externo:
 * el responsable de un servicio es siempre un profesional con cuenta en VIDA 360.
 *
 * El registro activo es el que tiene fecha_fin = null. Solo puede haber un
 * responsable activo por servicio.
 *
 * @property int $id
 * @property int $servicio_id
 * @property int $profesional_id
 * @property \Illuminate\Support\Carbon $fecha_inicio
 * @property \Illuminate\Support\Carbon|null $fecha_fin
 * @property string|null $notas
 */
class ResponsableServicio extends Model
{
    protected $table = 'responsables_servicio';

    protected $fillable = [
        'servicio_id',
        'profesional_id',
        'fecha_inicio',
        'fecha_fin',
        'notas',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin'    => 'date',
    ];

    // -------------------------------------------------------------------------
    // Validación en modelo
    // -------------------------------------------------------------------------

    /**
     * Garantiza que el responsable es siempre un profesional de VIDA 360.
     *
     * No existe la figura de responsable externo en un servicio (a diferencia
     * de DirectorCentro, que sí admite personas de contacto externas).
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::saving(function (self $responsable) {
            if (empty($responsable->profesional_id)) {
                throw new \InvalidArgumentException(
                    'El responsable de un servicio debe ser siempre un profesional de VIDA 360 (profesional_id es obligatorio).'
                );
            }
        });
    }

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Servicio al que pertenece este registro.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Modules\Centro\Models\Servicio, self>
     */
    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class, 'servicio_id');
    }

    /**
     * Profesional que ejerce como responsable en este período.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Modules\Usuarios\Models\Profesional, self>
     */
    public function profesional(): BelongsTo
    {
        return $this->belongsTo(Profesional::class, 'profesional_id');
    }

    // -------------------------------------------------------------------------
    // Accesores
    // -------------------------------------------------------------------------

    /**
     * Nombre del cargo del responsable, tomado del servicio.
     *
     * El cargo pertenece al servicio, no al profesional que lo ocupa.
     *
     * @return string
     */
    public function getCargoNombreAttribute(): string
    {
        return $this->servicio->cargo_nombre;
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Filtra el responsable actualmente en activo (sin fecha de fin).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeActivo(Builder $query): Builder
    {
        return $query->whereNull('fecha_fin');
    }
}
