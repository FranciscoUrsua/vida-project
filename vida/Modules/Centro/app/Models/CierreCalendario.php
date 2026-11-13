<?php

namespace Modules\Centro\Models;

use App\Models\User;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Modules\Centro\Enums\TipoCierre;

/**
 * Cierre o horario reducido de un tipo de centro o de un centro en unas fechas (§5.2).
 *
 * Los cierres del tipo y del centro se acumulan; en el mismo día manda el del
 * centro (ConfiguracionEfectivaCentro::dia()). Un cierre no cancela citas ni slots.
 *
 * @property int $id
 * @property string $titular_type
 * @property int $titular_id
 * @property Carbon $fecha_desde
 * @property Carbon $fecha_hasta
 * @property TipoCierre $tipo
 * @property string|null $hora_inicio Obligatoria en horario reducido.
 * @property string|null $hora_fin Obligatoria en horario reducido.
 * @property string $motivo
 * @property int|null $creado_por_id
 */
class CierreCalendario extends Model
{
    protected $table = 'cierres_calendario';

    protected $fillable = [
        'titular_type',
        'titular_id',
        'fecha_desde',
        'fecha_hasta',
        'tipo',
        'hora_inicio',
        'hora_fin',
        'motivo',
        'creado_por_id',
    ];

    protected $casts = [
        'fecha_desde' => 'date',
        'fecha_hasta' => 'date',
        'tipo' => TipoCierre::class,
    ];

    /**
     * Valida fechas y horas al guardar.
     *
     *
     * @return void
     *
     * @throws DomainException
     */
    protected static function booted(): void
    {
        static::saving(function (self $cierre): void {
            if ($cierre->fecha_hasta->lt($cierre->fecha_desde)) {
                throw new DomainException('El cierre termina antes de empezar.');
            }
            if ($cierre->tipo === TipoCierre::HorarioReducido
                && ($cierre->hora_inicio === null || $cierre->hora_fin === null || $cierre->hora_fin <= $cierre->hora_inicio)) {
                throw new DomainException('El horario reducido necesita hora de inicio y de fin.');
            }
        });
    }

    /**
     * Tipo de centro o centro al que se aplica.
     *
     * @return MorphTo<Model, $this>
     */
    public function titular(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Usuario que lo registró.
     *
     * @return BelongsTo<User, $this>
     */
    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por_id');
    }

    /**
     * Cierres que cubren una fecha.
     *
     * @param Builder<self> $query
     * @param Carbon $fecha Fecha.
     *
     * @return Builder<self>
     */
    public function scopeEnFecha(Builder $query, Carbon $fecha): Builder
    {
        $dia = $fecha->toDateString();

        return $query->whereDate('fecha_desde', '<=', $dia)->whereDate('fecha_hasta', '>=', $dia);
    }
}
