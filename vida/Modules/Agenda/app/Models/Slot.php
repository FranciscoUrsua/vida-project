<?php

namespace Modules\Agenda\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Modules\Agenda\Database\Factories\SlotFactory;
use Modules\Agenda\Enums\EstadoSlot;
use Modules\Centro\Models\Centro;
use Modules\Centro\Models\Sala;

/**
 * Hueco concreto disponible para reserva.
 *
 * Se genera al publicar un CuadranteMes. Corresponde a un profesional,
 * un día, una franja horaria y un tipo de slot. Los slots no se versionan;
 * la trazabilidad se obtiene del ciclo de vida de la Cita.
 *
 * @property int $id
 * @property int $linea_cuadrante_id
 * @property int $usuario_id
 * @property int $centro_id
 * @property int $tipo_slot_id
 * @property Carbon $fecha
 * @property string $hora_inicio
 * @property string $hora_fin
 * @property EstadoSlot $estado
 * @property int|null $espacio_id
 */
class Slot extends Model
{
    /** @use HasFactory<SlotFactory> */
    use HasFactory;

    protected static function newFactory(): SlotFactory
    {
        return SlotFactory::new();
    }

    protected $table = 'slots';

    protected $guarded = [];

    protected $casts = [
        'fecha' => 'date',
        'estado' => EstadoSlot::class,
    ];

    /**
     * Línea de cuadrante a la que pertenece el slot.
     *
     * @return BelongsTo<LineaCuadrante, $this>
     */
    public function lineaCuadrante(): BelongsTo
    {
        return $this->belongsTo(LineaCuadrante::class, 'linea_cuadrante_id');
    }

    /**
     * Profesional al que pertenece el slot.
     *
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * Centro al que pertenece el slot.
     *
     * @return BelongsTo<Centro, $this>
     */
    public function centro(): BelongsTo
    {
        return $this->belongsTo(Centro::class);
    }

    /**
     * Tipo de slot del hueco.
     *
     * @return BelongsTo<TipoSlot, $this>
     */
    public function tipoSlot(): BelongsTo
    {
        return $this->belongsTo(TipoSlot::class, 'tipo_slot_id');
    }

    /**
     * Sala del centro reservada por el slot, si existe.
     *
     * @return BelongsTo<Sala, $this>
     */
    public function espacio(): BelongsTo
    {
        return $this->belongsTo(Sala::class, 'espacio_id');
    }

    /**
     * Cita asociada al slot, si existe.
     *
     * @return HasOne<Cita, $this>
     */
    public function cita(): HasOne
    {
        return $this->hasOne(Cita::class, 'slot_id');
    }

    /**
     * Filtra slots disponibles.
     *
     * @param Builder<Slot> $query
     *
     * @return Builder<Slot>
     */
    public function scopeDisponibles(Builder $query): Builder
    {
        return $query->where('estado', EstadoSlot::Disponible->value);
    }

    /**
     * Filtra slots bloqueados para urgencias.
     *
     * @param Builder<Slot> $query
     *
     * @return Builder<Slot>
     */
    public function scopeUrgencias(Builder $query): Builder
    {
        return $query->where('estado', EstadoSlot::BloqueadoUrgencia->value);
    }

    /**
     * Filtra slots anulados.
     *
     * @param Builder<Slot> $query
     *
     * @return Builder<Slot>
     */
    public function scopeAnulados(Builder $query): Builder
    {
        return $query->where('estado', EstadoSlot::Anulado);
    }

    /**
     * Filtra slots de una fecha concreta.
     *
     * @param Builder<Slot> $query
     * @param Carbon|string $fecha
     *
     * @return Builder<Slot>
     */
    public function scopeDelDia(Builder $query, Carbon|string $fecha): Builder
    {
        return $query->where('fecha', $fecha);
    }

    /**
     * Filtra slots de un profesional.
     *
     * @param Builder<Slot> $query
     *
     * @return Builder<Slot>
     */
    public function scopeDelProfesional(Builder $query, int $usuarioId): Builder
    {
        return $query->where('usuario_id', $usuarioId);
    }

    /**
     * Filtra slots de un centro.
     *
     * @param Builder<Slot> $query
     *
     * @return Builder<Slot>
     */
    public function scopeDelCentro(Builder $query, int $centroId): Builder
    {
        return $query->where('centro_id', $centroId);
    }

    /**
     * Filtra slots por estado.
     *
     * @param Builder<Slot> $query
     *
     * @return Builder<Slot>
     */
    public function scopeDeEstado(Builder $query, EstadoSlot $estado): Builder
    {
        return $query->where('estado', $estado->value);
    }
}
