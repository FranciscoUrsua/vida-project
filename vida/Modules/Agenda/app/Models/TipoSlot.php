<?php

namespace Modules\Agenda\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Agenda\Database\Factories\TipoSlotFactory;
use Modules\Agenda\Enums\OrigenPermitidoSlot;

/**
 * Tipo de atención que puede reservarse como cita en un centro.
 *
 * Define la duración, reglas de uso y el porcentaje de slots reservados
 * para urgencias. Pertenece a un HorarioCentro concreto.
 *
 * @property int $id
 * @property int $horario_centro_id
 * @property string $nombre
 * @property string|null $descripcion
 * @property int $duracion_minutos
 * @property bool $requiere_espacio
 * @property int $porcentaje_urgencias
 * @property OrigenPermitidoSlot $origen_permitido
 * @property bool $genera_apunte_automatico
 * @property bool $bloquea_todos_convocados
 * @property bool $activo
 */
class TipoSlot extends Model
{
    /** @use HasFactory<TipoSlotFactory> */
    use HasFactory;

    protected static function newFactory(): TipoSlotFactory
    {
        return TipoSlotFactory::new();
    }

    protected $table = 'tipos_slot';

    protected $guarded = [];

    protected $casts = [
        'duracion_minutos' => 'integer',
        'requiere_espacio' => 'boolean',
        'porcentaje_urgencias' => 'integer',
        'origen_permitido' => OrigenPermitidoSlot::class,
        'genera_apunte_automatico' => 'boolean',
        'bloquea_todos_convocados' => 'boolean',
        'activo' => 'boolean',
    ];

    /**
     * Horario centro al que pertenece el tipo de slot.
     *
     * @return BelongsTo<HorarioCentro, $this>
     */
    public function horarioCentro(): BelongsTo
    {
        return $this->belongsTo(HorarioCentro::class, 'horario_centro_id');
    }

    /**
     * Slots creados para este tipo de atención.
     *
     * @return HasMany<Slot, $this>
     */
    public function slots(): HasMany
    {
        return $this->hasMany(Slot::class, 'tipo_slot_id');
    }

    /**
     * Filtra tipos de slot activos.
     *
     * @param Builder<TipoSlot> $query
     *
     * @return Builder<TipoSlot>
     */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    /**
     * Filtra tipos de slot que admiten reserva desde API externa.
     *
     * @param Builder<TipoSlot> $query
     *
     * @return Builder<TipoSlot>
     */
    public function scopeQueAdmitenApiExterna(Builder $query): Builder
    {
        return $query->whereIn('origen_permitido', [
            OrigenPermitidoSlot::ApiExterna->value,
            OrigenPermitidoSlot::Ambos->value,
        ]);
    }
}
