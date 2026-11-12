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
 * Con `hereda_horario_tipo` el horario semanal es el del tipo de centro y los
 * campos de horario de este registro van a null; los parámetros de agenda
 * (buffers, tipos de slot, modo, plazos) son siempre de este registro. El horario
 * de un día se lee por ConfiguracionEfectivaCentro::dia() (docs/modulo-tipo-centro.md §5).
 *
 * @property int $id
 * @property int $centro_id
 * @property string $nombre
 * @property bool $hereda_horario_tipo Si el horario semanal es el del tipo de centro.
 * @property array|null $dias_laborables Null si hereda.
 * @property string|null $hora_apertura Null si hereda.
 * @property string|null $hora_cierre Null si hereda.
 * @property string|null $hora_inicio_atencion Null si hereda.
 * @property string|null $hora_fin_atencion Null si hereda.
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
        'hereda_horario_tipo' => 'boolean',
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

    /** Campos de horario semanal que se heredan del tipo. */
    public const CAMPOS_HORARIO = ['dias_laborables', 'hora_apertura', 'hora_cierre', 'hora_inicio_atencion', 'hora_fin_atencion'];

    /**
     * Herencia por defecto y coherencia de los campos de horario.
     *
     * Un horario nuevo hereda el del tipo salvo que traiga su propio horario
     * (compatibilidad con las altas que ya fijan días y horas). Con herencia, los
     * campos de horario quedan a null y no se leen.
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::creating(function (self $horario): void {
            if (! array_key_exists('hereda_horario_tipo', $horario->getAttributes())) {
                $horario->hereda_horario_tipo = $horario->getAttribute('hora_apertura') === null;
            }
        });

        static::saving(function (self $horario): void {
            if ($horario->hereda_horario_tipo) {
                foreach (self::CAMPOS_HORARIO as $campo) {
                    $horario->{$campo} = null;
                }
            }
        });
    }

    /**
     * Vuelve al horario del tipo: herencia activada y campos de horario vacíos;
     * el resto de parámetros de agenda se conservan (§4, «Volver al valor del tipo»).
     *
     * @return void
     */
    public function volverAlTipo(): void
    {
        $this->update(['hereda_horario_tipo' => true]);
    }

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
     * Si el centro abre en una fecha: horario efectivo con los cierres de calendario.
     *
     * @param Carbon $fecha
     * @return bool
     */
    public function esLaborable(Carbon $fecha): bool
    {
        // Horario sin centro guardado (p. ej. el de reserva de SolicitudCitaService): solo sus días
        if ($this->centro_id === null || $this->centro === null) {
            return in_array($fecha->isoWeekday(), array_map('intval', $this->dias_laborables ?? [1, 2, 3, 4, 5]), true);
        }

        return app(\Modules\Centro\Services\ConfiguracionEfectivaCentro::class)->dia($this->centro, $fecha)->abierto;
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
