<?php

namespace Modules\Agenda\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Agenda\Database\Factories\SlotFactory;
use Modules\Agenda\Enums\EstadoSlot;
use Modules\Centro\Models\Centro;
use Modules\Centro\Models\Espacio;

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
 * @property \Illuminate\Support\Carbon $fecha
 * @property string $hora_inicio
 * @property string $hora_fin
 * @property EstadoSlot $estado
 * @property int|null $espacio_id
 */
class Slot extends Model
{
    use HasFactory;

    protected static function newFactory(): SlotFactory
    {
        return SlotFactory::new();
    }

    protected $table = 'slots';

    protected $guarded = [];

    protected $casts = [
        'fecha'  => 'date',
        'estado' => EstadoSlot::class,
    ];

    public function lineaCuadrante(): BelongsTo
    {
        return $this->belongsTo(LineaCuadrante::class, 'linea_cuadrante_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function centro(): BelongsTo
    {
        return $this->belongsTo(Centro::class);
    }

    public function tipoSlot(): BelongsTo
    {
        return $this->belongsTo(TipoSlot::class, 'tipo_slot_id');
    }

    public function espacio(): BelongsTo
    {
        return $this->belongsTo(Espacio::class);
    }

    public function cita(): HasOne
    {
        return $this->hasOne(Cita::class, 'slot_id');
    }

    public function scopeDisponibles(Builder $query): Builder
    {
        return $query->where('estado', EstadoSlot::Disponible->value);
    }

    public function scopeUrgencias(Builder $query): Builder
    {
        return $query->where('estado', EstadoSlot::BloqueadoUrgencia->value);
    }

    public function scopeAnulados(Builder $query): Builder
    {
        return $query->where('estado', EstadoSlot::Anulado);
    }

    public function scopeDelDia(Builder $query, $fecha): Builder
    {
        return $query->where('fecha', $fecha);
    }

    public function scopeDelProfesional(Builder $query, int $usuarioId): Builder
    {
        return $query->where('usuario_id', $usuarioId);
    }

    public function scopeDelCentro(Builder $query, int $centroId): Builder
    {
        return $query->where('centro_id', $centroId);
    }

    public function scopeDeEstado(Builder $query, EstadoSlot $estado): Builder
    {
        return $query->where('estado', $estado->value);
    }
}
