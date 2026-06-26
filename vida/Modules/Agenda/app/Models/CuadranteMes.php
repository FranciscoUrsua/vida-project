<?php

namespace Modules\Agenda\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;
use Modules\Agenda\Database\Factories\CuadranteMesFactory;
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
 * @property Carbon|null $publicado_en
 * @property int|null $publicado_por_id
 * @property string|null $notas
 */
class CuadranteMes extends Model
{
    /** @use HasFactory<CuadranteMesFactory> */
    use HasFactory;

    protected static function newFactory(): CuadranteMesFactory
    {
        return CuadranteMesFactory::new();
    }

    protected $table = 'cuadrantes_mes';

    protected $guarded = [];

    protected $casts = [
        'anyo' => 'integer',
        'mes' => 'integer',
        'estado' => EstadoCuadrante::class,
        'generado_con_ia' => 'boolean',
        'generado_automaticamente' => 'boolean',
        'publicado_en' => 'datetime',
    ];

    /**
     * Centro al que pertenece el cuadrante mensual.
     *
     * @return BelongsTo<Centro, $this>
     */
    public function centro(): BelongsTo
    {
        return $this->belongsTo(Centro::class);
    }

    /**
     * Usuario que publicó el cuadrante.
     *
     * @return BelongsTo<User, $this>
     */
    public function publicadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'publicado_por_id');
    }

    /**
     * Líneas de disponibilidad planificadas para el mes.
     *
     * @return HasMany<LineaCuadrante, $this>
     */
    public function lineas(): HasMany
    {
        return $this->hasMany(LineaCuadrante::class, 'cuadrante_mes_id');
    }

    /**
     * Slots materializados a partir de las líneas del cuadrante.
     *
     * @return HasManyThrough<Slot, LineaCuadrante, $this>
     */
    public function slots(): HasManyThrough
    {
        return $this->hasManyThrough(Slot::class, LineaCuadrante::class, 'cuadrante_mes_id', 'linea_cuadrante_id');
    }

    /**
     * Filtra cuadrantes publicados.
     *
     * @param Builder<CuadranteMes> $query
     *
     * @return Builder<CuadranteMes>
     */
    public function scopePublicados(Builder $query): Builder
    {
        return $query->where('estado', EstadoCuadrante::Publicado->value);
    }

    /**
     * Filtra cuadrantes en borrador.
     *
     * @param Builder<CuadranteMes> $query
     *
     * @return Builder<CuadranteMes>
     */
    public function scopeBorradores(Builder $query): Builder
    {
        return $query->where('estado', EstadoCuadrante::Borrador->value);
    }

    /**
     * Filtra cuadrantes por año y mes.
     *
     * @param Builder<CuadranteMes> $query
     *
     * @return Builder<CuadranteMes>
     */
    public function scopeDelMes(Builder $query, int $anyo, int $mes): Builder
    {
        return $query->where('anyo', $anyo)->where('mes', $mes);
    }

    /**
     * Filtra cuadrantes de un centro.
     *
     * @param Builder<CuadranteMes> $query
     *
     * @return Builder<CuadranteMes>
     */
    public function scopeDelCentro(Builder $query, int $centroId): Builder
    {
        return $query->where('centro_id', $centroId);
    }
}
