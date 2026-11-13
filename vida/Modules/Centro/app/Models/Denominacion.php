<?php

namespace Modules\Centro\Models;

use App\Models\User;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Nombre de un tipo de centro o de un centro durante un periodo (RN-02, RN-12).
 *
 * Las vigencias de un mismo titular no se solapan, y una denominación ya en
 * vigor o pasada no cambia de nombre: un renombrado cierra la vigente y crea
 * otra (`TieneDenominaciones::programarDenominacion()`). Así siempre se puede
 * responder cómo se llamaba a una fecha.
 *
 * @property int $id
 * @property string $denominable_type
 * @property int $denominable_id
 * @property string $nombre
 * @property string $nombre_corto
 * @property Carbon $vigente_desde
 * @property Carbon|null $vigente_hasta
 * @property int|null $creado_por_id
 * @property-read Model $denominable
 */
class Denominacion extends Model
{
    protected $table = 'denominaciones';

    protected $fillable = [
        'denominable_type',
        'denominable_id',
        'nombre',
        'nombre_corto',
        'vigente_desde',
        'vigente_hasta',
        'creado_por_id',
    ];

    protected $casts = [
        'vigente_desde' => 'date',
        'vigente_hasta' => 'date',
    ];

    /**
     * Valida el solape de vigencias y la inmutabilidad del nombre al guardar.
     *
     *
     * @return void
     *
     * @throws DomainException
     */
    protected static function booted(): void
    {
        static::saving(function (self $denominacion): void {
            $denominacion->validarCoherencia();
        });
    }

    /**
     * Tipo de centro o centro al que pertenece la denominación.
     *
     * @return MorphTo<Model, $this>
     */
    public function denominable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Usuario que registró la denominación.
     *
     * @return BelongsTo<User, $this>
     */
    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por_id');
    }

    /**
     * Denominaciones vigentes en una fecha.
     *
     * @param Builder<self> $query
     * @param Carbon $fecha Fecha de referencia.
     *
     * @return Builder<self>
     */
    public function scopeVigentesEn(Builder $query, Carbon $fecha): Builder
    {
        $dia = $fecha->toDateString();

        return $query->whereDate('vigente_desde', '<=', $dia)
            ->where(fn (Builder $q) => $q->whereNull('vigente_hasta')->orWhereDate('vigente_hasta', '>=', $dia));
    }

    /**
     * Rechaza vigencias invertidas, solapes con otra denominación del mismo
     * titular y cambios de nombre en una denominación que ya ha entrado en vigor.
     *
     *
     * @return void
     *
     * @throws DomainException
     */
    private function validarCoherencia(): void
    {
        if ($this->vigente_hasta !== null && $this->vigente_hasta->lt($this->vigente_desde)) {
            throw new DomainException('La vigencia de la denominación termina antes de empezar.');
        }

        // El nombre de una denominación que ya está en vigor (o pasada) no se cambia:
        // se cierra y se crea otra, para conservar cómo se llamaba en cada fecha.
        if ($this->exists && $this->isDirty(['nombre', 'nombre_corto'])
            && Carbon::parse($this->getOriginal('vigente_desde'))->lte(today())) {
            throw new DomainException('No se cambia el nombre de una denominación en vigor: programa una nueva.');
        }

        $hasta = $this->vigente_hasta?->toDateString();
        $solapa = self::query()
            ->where('denominable_type', $this->denominable_type)
            ->where('denominable_id', $this->denominable_id)
            ->when($this->exists, fn (Builder $q) => $q->whereKeyNot($this->getKey()))
            ->where(fn (Builder $q) => $q->whereNull('vigente_hasta')->orWhereDate('vigente_hasta', '>=', $this->vigente_desde->toDateString()))
            ->when($hasta !== null, fn (Builder $q) => $q->whereDate('vigente_desde', '<=', $hasta))
            ->exists();

        if ($solapa) {
            throw new DomainException('La vigencia se solapa con otra denominación: usa programarDenominacion().');
        }
    }
}
