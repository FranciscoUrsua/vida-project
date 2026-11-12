<?php

namespace Modules\Centro\Models;

use App\Traits\Versionable;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Horario semanal por defecto de un tipo de centro, con vigencia (§5.1).
 *
 * Lo heredan los centros del tipo que no tienen horario propio. Las vigencias de
 * un mismo tipo no se solapan: un horario de verano cierra el de invierno.
 *
 * @property int $id
 * @property int $tipo_centro_id
 * @property string $nombre
 * @property list<int> $dias_laborables ISO: 1 = lunes … 7 = domingo.
 * @property string $hora_apertura
 * @property string $hora_cierre
 * @property string $hora_inicio_atencion
 * @property string $hora_fin_atencion
 * @property Carbon $vigente_desde
 * @property Carbon|null $vigente_hasta
 */
class HorarioTipoCentro extends Model
{
    use Versionable;

    protected $table = 'horarios_tipo_centro';

    protected $fillable = [
        'tipo_centro_id',
        'nombre',
        'dias_laborables',
        'hora_apertura',
        'hora_cierre',
        'hora_inicio_atencion',
        'hora_fin_atencion',
        'vigente_desde',
        'vigente_hasta',
    ];

    protected $casts = [
        'dias_laborables' => 'array',
        'vigente_desde' => 'date',
        'vigente_hasta' => 'date',
    ];

    /**
     * Rechaza solapes de vigencia dentro del mismo tipo.
     *
     * @throws DomainException
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::saving(function (self $horario): void {
            $hasta = $horario->vigente_hasta?->toDateString();
            $solapa = self::query()
                ->where('tipo_centro_id', $horario->tipo_centro_id)
                ->when($horario->exists, fn (Builder $q) => $q->whereKeyNot($horario->getKey()))
                ->where(fn (Builder $q) => $q->whereNull('vigente_hasta')->orWhereDate('vigente_hasta', '>=', $horario->vigente_desde->toDateString()))
                ->when($hasta !== null, fn (Builder $q) => $q->whereDate('vigente_desde', '<=', $hasta))
                ->exists();

            if ($solapa) {
                throw new DomainException('El horario se solapa con otro horario del tipo de centro.');
            }
        });
    }

    /**
     * Tipo de centro del horario.
     *
     * @return BelongsTo<TipoCentro, $this>
     */
    public function tipoCentro(): BelongsTo
    {
        return $this->belongsTo(TipoCentro::class, 'tipo_centro_id');
    }

    /**
     * Horario del tipo vigente en una fecha.
     *
     * @param Builder<self> $query
     * @param Carbon $fecha Fecha de referencia.
     *
     * @return Builder<self>
     */
    public function scopeVigenteEn(Builder $query, Carbon $fecha): Builder
    {
        $dia = $fecha->toDateString();

        return $query->whereDate('vigente_desde', '<=', $dia)
            ->where(fn (Builder $q) => $q->whereNull('vigente_hasta')->orWhereDate('vigente_hasta', '>=', $dia));
    }
}
