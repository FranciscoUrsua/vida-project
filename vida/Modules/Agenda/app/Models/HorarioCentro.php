<?php

namespace Modules\Agenda\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
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
 * @property Carbon $vigente_desde
 * @property Carbon|null $vigente_hasta
 * @property ModoAgenda $modo_agenda
 * @property bool $activo
 * @property string|null $notas
 * @property array|null $semana_tipo
 * @property-read \Illuminate\Database\Eloquent\Collection<int, TipoSlot> $tiposSlot
 * @property int $dias_ausencia_prolongada Umbral a partir del cual una ausencia saca al profesional del sorteo de referencias.
 */
class HorarioCentro extends Model
{
    /** @use HasFactory<HorarioCentroFactory> */
    use HasFactory;
    use SoftDeletes;

    /**
     * Factoría del modelo (vive en el módulo, no en database/factories).
     *
     * @return HorarioCentroFactory
     */
    protected static function newFactory(): HorarioCentroFactory
    {
        return HorarioCentroFactory::new();
    }

    protected $table = 'horarios_centro';

    protected $guarded = [];

    protected $casts = [
        'dias_laborables' => 'array',
        'vigente_desde' => 'date',
        'vigente_hasta' => 'date',
        'modo_agenda' => ModoAgenda::class,
        'semana_tipo' => 'array',
        'activo' => 'boolean',
        'buffer_inicio_minutos' => 'integer',
        'buffer_fin_minutos' => 'integer',
        'dias_ausencia_prolongada' => 'integer',
    ];

    /**
     * Centro al que pertenece el horario.
     *
     * @return BelongsTo<Centro, $this>
     */
    public function centro(): BelongsTo
    {
        return $this->belongsTo(Centro::class);
    }

    /**
     * Tipos de slot del catálogo global que ofrece este horario: son los que se
     * materializan al publicar el cuadrante.
     *
     * @return BelongsToMany<TipoSlot, $this>
     */
    public function tiposSlot(): BelongsToMany
    {
        return $this->belongsToMany(TipoSlot::class, 'horario_centro_tipo_slot')->withTimestamps();
    }

    /**
     * Filtra horarios activos.
     *
     * @param Builder<HorarioCentro> $query
     *
     * @return Builder<HorarioCentro>
     */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    /**
     * Filtra horarios vigentes en la fecha actual.
     *
     * @param Builder<HorarioCentro> $query
     *
     * @return Builder<HorarioCentro>
     */
    public function scopeVigentes(Builder $query): Builder
    {
        $hoy = now()->toDateString();

        return $query->where('vigente_desde', '<=', $hoy)
            ->where(function (Builder $q) use ($hoy) {
                $q->whereNull('vigente_hasta')->orWhere('vigente_hasta', '>=', $hoy);
            });
    }

    /**
     * Filtra horarios de un centro.
     *
     * @param Builder<HorarioCentro> $query
     *
     * @return Builder<HorarioCentro>
     */
    public function scopeDelCentro(Builder $query, int $centroId): Builder
    {
        return $query->where('centro_id', $centroId);
    }

    /**
     * Indica si el horario usa modo basico.
     *
     * @return bool
     */
    public function esModoBasico(): bool
    {
        return $this->modo_agenda === ModoAgenda::Basico;
    }

    /**
     * Indica si el horario usa modo avanzado.
     *
     * @return bool
     */
    public function esModoAvanzado(): bool
    {
        return $this->modo_agenda === ModoAgenda::Avanzado;
    }
}
