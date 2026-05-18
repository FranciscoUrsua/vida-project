<?php

namespace Modules\Agenda\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Agenda\Database\Factories\HorarioCentroFactory;
use Modules\Agenda\Enums\ModoAgenda;
use Modules\Centro\Models\Centro;

/**
 * Horario operativo de un centro.
 *
 * Define los días y horas de apertura, el horario de atención al público
 * y el modo de agenda (básico, estándar o avanzado). Un centro puede tener
 * múltiples horarios históricos pero solo uno vigente en cada momento.
 *
 * @property int $id
 * @property int $centro_id
 * @property string $nombre
 * @property array $dias_laborables
 * @property string $hora_apertura
 * @property string $hora_cierre
 * @property string $hora_inicio_atencion
 * @property string $hora_fin_atencion
 * @property int $buffer_inicio_minutos
 * @property int $buffer_fin_minutos
 * @property \Illuminate\Support\Carbon $vigente_desde
 * @property \Illuminate\Support\Carbon|null $vigente_hasta
 * @property ModoAgenda $modo_agenda
 * @property bool $activo
 * @property string|null $notas
 */
class HorarioCentro extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected static function newFactory(): HorarioCentroFactory
    {
        return HorarioCentroFactory::new();
    }

    protected $table = 'horarios_centro';

    protected $guarded = [];

    protected $casts = [
        'dias_laborables'        => 'array',
        'vigente_desde'          => 'date',
        'vigente_hasta'          => 'date',
        'modo_agenda'            => ModoAgenda::class,
        'activo'                 => 'boolean',
        'buffer_inicio_minutos'  => 'integer',
        'buffer_fin_minutos'     => 'integer',
    ];

    public function centro(): BelongsTo
    {
        return $this->belongsTo(Centro::class);
    }

    public function tiposSlot(): HasMany
    {
        return $this->hasMany(TipoSlot::class, 'horario_centro_id');
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    public function scopeVigentes(Builder $query): Builder
    {
        $hoy = now()->toDateString();

        return $query->where('vigente_desde', '<=', $hoy)
            ->where(function (Builder $q) use ($hoy) {
                $q->whereNull('vigente_hasta')->orWhere('vigente_hasta', '>=', $hoy);
            });
    }

    public function scopeDelCentro(Builder $query, int $centroId): Builder
    {
        return $query->where('centro_id', $centroId);
    }

    public function esModoBasico(): bool
    {
        return $this->modo_agenda === ModoAgenda::Basico;
    }

    public function esModoAvanzado(): bool
    {
        return $this->modo_agenda === ModoAgenda::Avanzado;
    }
}
