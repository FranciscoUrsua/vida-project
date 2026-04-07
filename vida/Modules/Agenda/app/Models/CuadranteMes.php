<?php

namespace Modules\Agenda\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Modules\Agenda\Enums\EstadoCuadrante;
use Modules\Centro\Models\Centro;

/**
 * Cuadrante mensual de un centro.
 *
 * Representa la planificación de disponibilidad para todos los profesionales
 * de un centro en un mes concreto. Ciclo de vida: borrador → revisión → publicado.
 * Al publicarse, el SlotMaterializadorService genera los registros Slot.
 *
 * @property int $id
 * @property int $centro_id
 * @property int $anyo
 * @property int $mes
 * @property EstadoCuadrante $estado
 * @property bool $generado_con_ia
 * @property bool $generado_automaticamente
 * @property \Illuminate\Support\Carbon|null $publicado_en
 * @property int|null $publicado_por_id
 * @property string|null $notas
 */
class CuadranteMes extends Model
{
    protected $table = 'cuadrantes_mes';

    protected $guarded = [];

    protected $casts = [
        'anyo'                     => 'integer',
        'mes'                      => 'integer',
        'estado'                   => EstadoCuadrante::class,
        'generado_con_ia'          => 'boolean',
        'generado_automaticamente' => 'boolean',
        'publicado_en'             => 'datetime',
    ];

    public function centro(): BelongsTo
    {
        return $this->belongsTo(Centro::class);
    }

    public function publicadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'publicado_por_id');
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(LineaCuadrante::class, 'cuadrante_mes_id');
    }

    public function slots(): HasManyThrough
    {
        return $this->hasManyThrough(Slot::class, LineaCuadrante::class, 'cuadrante_mes_id', 'linea_cuadrante_id');
    }

    public function scopePublicados(Builder $query): Builder
    {
        return $query->where('estado', EstadoCuadrante::Publicado->value);
    }

    public function scopeBorradores(Builder $query): Builder
    {
        return $query->where('estado', EstadoCuadrante::Borrador->value);
    }

    public function scopeDelMes(Builder $query, int $anyo, int $mes): Builder
    {
        return $query->where('anyo', $anyo)->where('mes', $mes);
    }

    public function scopeDelCentro(Builder $query, int $centroId): Builder
    {
        return $query->where('centro_id', $centroId);
    }
}
