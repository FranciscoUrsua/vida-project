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
 * @property array<string, int>|null $plazos_urgencia Días laborables por urgencia.
 * @property int $dias_aviso_cierre_supervisor Días laborables pendientes de cierre antes de avisar al supervisor.
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
        'plazos_urgencia' => 'array',
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
     * Plazo máximo en días laborables para una urgencia (docs/modulo-citas.md §8).
     *
     * @param \Modules\Agenda\Enums\UrgenciaCita $urgencia
     * @return int
     */
    public function plazoUrgencia(\Modules\Agenda\Enums\UrgenciaCita $urgencia): int
    {
        $defecto = ['ordinaria' => 20, 'preferente' => 7, 'urgente' => 2];

        return (int) (($this->plazos_urgencia ?? [])[$urgencia->value] ?? $defecto[$urgencia->value]);
    }

    /**
     * Si una fecha es laborable según los días del horario.
     *
     * @param Carbon $fecha
     * @return bool
     */
    public function esLaborable(Carbon $fecha): bool
    {
        return in_array($fecha->isoWeekday(), array_map('intval', $this->dias_laborables ?? [1, 2, 3, 4, 5]), true);
    }

    /**
     * Fecha que resulta de sumar días laborables a otra.
     *
     * @param Carbon $desde
     * @param int $dias
     * @return Carbon
     */
    public function sumarDiasLaborables(Carbon $desde, int $dias): Carbon
    {
        $fecha = $desde->copy()->startOfDay();

        while ($dias > 0) {
            $fecha->addDay();
            if ($this->esLaborable($fecha)) {
                $dias--;
            }
        }

        return $fecha;
    }

    /**
     * Días laborables entre dos fechas (sin contar la inicial).
     *
     * @param Carbon $desde
     * @param Carbon $hasta
     * @return int
     */
    public function diasLaborablesEntre(Carbon $desde, Carbon $hasta): int
    {
        $dias = 0;
        $fecha = $desde->copy()->startOfDay();
        $fin = $hasta->copy()->startOfDay();

        while ($fecha->lt($fin)) {
            $fecha->addDay();
            if ($this->esLaborable($fecha)) {
                $dias++;
            }
        }

        return $dias;
    }

    /**
     * Horario vigente de un centro en una fecha.
     *
     * @param int $centroId
     * @param Carbon|null $fecha Por defecto, hoy.
     * @return self|null
     */
    public static function vigenteDelCentro(int $centroId, ?Carbon $fecha = null): ?self
    {
        $dia = ($fecha ?? now())->toDateString();

        return static::where('centro_id', $centroId)
            ->where('activo', true)
            ->whereDate('vigente_desde', '<=', $dia)
            ->where(fn ($q) => $q->whereNull('vigente_hasta')->orWhereDate('vigente_hasta', '>=', $dia))
            ->orderByDesc('vigente_desde')
            ->first();
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
