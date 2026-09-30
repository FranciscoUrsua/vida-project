<?php

namespace Modules\Agenda\Models;

use App\Models\Ciudadano;
use App\Models\Scopes\AmbitoUoScope;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;
use Modules\Agenda\Database\Factories\CitaFactory;
use Modules\Agenda\Enums\EstadoCita;
use Modules\Agenda\Enums\EstadoSlot;
use Modules\Agenda\Enums\ModalidadCita;
use Modules\Agenda\Enums\ModoAsignacionCita;
use Modules\Agenda\Enums\OrigenCita;
use Modules\Agenda\Enums\PedidoPor;
use Modules\Atencion\Models\RegistroAtencion;
use Modules\Centro\Models\Centro;
use Modules\Intervencion\Models\Apunte;

/**
 * Reserva de un slot con un ciudadano concreto.
 *
 * Una cita vincula un slot con un ciudadano y tiene un ciclo de vida propio.
 * Puede originarse en el sistema interno o vía API externa. Al crearse,
 * el slot asociado pasa a estado 'reservado'.
 *
 * Solo la crean, mueven y cierran los servicios de Agenda (CitacionService,
 * AtencionCitaService, GestionAusenciaService), que escriben su evento en
 * `cita_eventos` en la misma transacción (docs/modulo-citas.md). Reprogramar
 * crea una cita nueva enlazada por `cita_anterior_id`; el sistema nunca cierra
 * una cita por su cuenta. Solo una cita externa pendiente de identificar puede
 * no tener ciudadano.
 *
 * @property int $id
 * @property int $slot_id
 * @property int|null $ciudadano_id Null solo en citas externas pendientes de identificar.
 * @property int $profesional_id
 * @property int $tipo_slot_id
 * @property int $centro_id
 * @property \Illuminate\Support\Carbon $fecha
 * @property string $hora_inicio
 * @property string $hora_fin
 * @property EstadoCita $estado
 * @property string|null $motivo
 * @property OrigenCita $origen
 * @property string|null $referencia_externa
 * @property int|null $creado_por_id
 * @property int|null $cancelado_por_id
 * @property string|null $motivo_cancelacion
 * @property \Illuminate\Support\Carbon|null $completada_en
 * @property string|null $notas_profesional
 * @property int|null $solicitud_cita_id
 * @property int $tipo_cita_id
 * @property ModalidadCita $modalidad
 * @property ModoAsignacionCita $modo_asignacion
 * @property int|null $cita_anterior_id
 * @property array<string, mixed>|null $datos_identificacion_externos Cifrado.
 * @property bool $pendiente_cierre
 * @property \Illuminate\Support\Carbon|null $pendiente_cierre_desde
 * @property \Illuminate\Support\Carbon|null $aviso_cierre_supervisor_en
 * @property PedidoPor|null $pedido_por_cancelacion
 * @property-read SolicitudCita|null $solicitud
 * @property-read TipoCita $tipoCita
 * @property-read Cita|null $citaAnterior
 * @property-read Cita|null $reprogramacion
 */
class Cita extends Model
{
    /** Motivo con el que GestionAusenciaService cancela las citas de un profesional ausente. */
    public const MOTIVO_CANCELACION_AUSENCIA = 'Ausencia del profesional';

    /** Motivo que deja el supervisor al descartar la reasignación de una de esas citas. */
    public const MOTIVO_AUSENCIA_DESCARTADA = 'Ausencia del profesional — descartada por supervisor';

    /** @use HasFactory<CitaFactory> */
    use HasFactory;

    use SoftDeletes;

    protected static function newFactory(): CitaFactory
    {
        return CitaFactory::new();
    }

    protected $table = 'citas';

    protected $guarded = [];

    protected $casts = [
        'fecha' => 'date',
        'estado' => EstadoCita::class,
        'origen' => OrigenCita::class,
        'completada_en' => 'datetime',
        'modalidad' => ModalidadCita::class,
        'modo_asignacion' => ModoAsignacionCita::class,
        'datos_identificacion_externos' => 'encrypted:array',
        'pendiente_cierre' => 'boolean',
        'pendiente_cierre_desde' => 'datetime',
        'aviso_cierre_supervisor_en' => 'datetime',
        'pedido_por_cancelacion' => PedidoPor::class,
    ];

    /**
     * Solo una cita externa puede crearse sin ciudadano (pendiente de identificar).
     * Sin tipo de cita, toma el genérico, como las citas anteriores a los tipos.
     *
     * @return void
     *
     * @throws LogicException
     */
    protected static function booted(): void
    {
        static::creating(function (self $cita): void {
            $cita->tipo_cita_id ??= TipoCita::generico()->id;
        });
        static::saving(fn (self $cita) => $cita->exigirCiudadano());
    }

    /**
     * Slot reservado por la cita.
     *
     * @return BelongsTo<Slot, $this>
     */
    public function slot(): BelongsTo
    {
        return $this->belongsTo(Slot::class);
    }

    /**
     * Ciudadano atendido en la cita.
     *
     * @return BelongsTo<Ciudadano, $this>
     */
    public function ciudadano(): BelongsTo
    {
        // Quien cita (consulta_basica) no suele tener la historia de la persona en su UO
        return $this->belongsTo(Ciudadano::class)->withoutGlobalScope(AmbitoUoScope::class);
    }

    /**
     * Profesional asignado a la cita.
     *
     * @return BelongsTo<User, $this>
     */
    public function profesional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'profesional_id');
    }

    /**
     * Tipo de slot reservado para la cita.
     *
     * @return BelongsTo<TipoSlot, $this>
     */
    public function tipoSlot(): BelongsTo
    {
        return $this->belongsTo(TipoSlot::class, 'tipo_slot_id');
    }

    /**
     * Centro donde se presta la cita.
     *
     * @return BelongsTo<Centro, $this>
     */
    public function centro(): BelongsTo
    {
        return $this->belongsTo(Centro::class);
    }

    /**
     * Usuario que creó la cita.
     *
     * @return BelongsTo<User, $this>
     */
    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por_id');
    }

    /**
     * Usuario que canceló la cita.
     *
     * @return BelongsTo<User, $this>
     */
    public function canceladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelado_por_id');
    }

    /**
     * Reasignación asociada a la cita, si existe.
     *
     * @return HasOne<ReasignacionCita, $this>
     */
    public function reasignacion(): HasOne
    {
        return $this->hasOne(ReasignacionCita::class, 'cita_id');
    }

    /**
     * Filtra citas confirmadas.
     *
     * @param Builder<Cita> $query
     *
     * @return Builder<Cita>
     */
    public function scopeConfirmadas(Builder $query): Builder
    {
        return $query->where('estado', EstadoCita::Confirmada->value);
    }

    /**
     * Filtra citas de una fecha concreta.
     *
     * @param Builder<Cita> $query
     * @param Carbon|string $fecha
     *
     * @return Builder<Cita>
     */
    public function scopeDelDia(Builder $query, $fecha): Builder
    {
        return $query->where('fecha', $fecha);
    }

    /**
     * Filtra citas asignadas a un profesional.
     *
     * @param Builder<Cita> $query
     *
     * @return Builder<Cita>
     */
    public function scopeDelProfesional(Builder $query, int $usuarioId): Builder
    {
        return $query->where('profesional_id', $usuarioId);
    }

    /**
     * Filtra citas de un ciudadano.
     *
     * @param Builder<Cita> $query
     *
     * @return Builder<Cita>
     */
    public function scopeDelCiudadano(Builder $query, int $ciudadanoId): Builder
    {
        return $query->where('ciudadano_id', $ciudadanoId);
    }

    /**
     * Citas de hoy del centro canceladas por ausencia del profesional que el
     * supervisor aún no ha reasignado ni descartado. Única definición para la
     * pantalla de ausencias y los contadores de los menús.
     *
     * @param Builder<Cita> $query
     * @param int $centroId
     * @return Builder<Cita>
     */
    public function scopeCanceladasPorAusenciaSinGestionar(Builder $query, int $centroId): Builder
    {
        return $query->where('centro_id', $centroId)
            ->where('fecha', now()->toDateString())
            ->where('estado', EstadoCita::Cancelada->value)
            // Igualdad exacta: el motivo de las descartadas empieza igual y no debe contar
            ->where('motivo_cancelacion', self::MOTIVO_CANCELACION_AUSENCIA)
            ->whereDoesntHave('reasignacion');
    }

    /**
     * Filtra citas pendientes de reasignación por no-show profesional.
     *
     * @param Builder<Cita> $query
     *
     * @return Builder<Cita>
     */
    public function scopePendientesReasignacion(Builder $query): Builder
    {
        return $query->where('estado', EstadoCita::NoShowProfesional->value);
    }

    // =========================================================================
    // Acciones de ciclo de vida
    // =========================================================================

    /**
     * Registra el no-show del ciudadano sin modificar el slot.
     *
     * El slot permanece en 'reservado'; el SlotExpirationJob lo transitará
     * a 'no_ocupado' cuando la franja haya expirado al final del día.
     *
     * Interno: solo lo llama AtencionCitaService, que escribe el evento.
     */
    public function noShowCiudadano(): void
    {
        $this->update(['estado' => EstadoCita::NoShowCiudadano->value]);
    }

    /**
     * Marca la cita como completada y registra el momento exacto.
     *
     * Interno: solo lo llama AtencionCitaService, que escribe el evento.
     */
    public function completar(): void
    {
        $this->update([
            'estado' => EstadoCita::Completada->value,
            'completada_en' => now(),
        ]);
    }

    /**
     * Cancela la cita y ajusta el estado del slot según si la franja ha pasado.
     *
     * Si la hora de inicio del slot ya ha transcurrido, el slot queda en
     * 'no_ocupado'; si aún no ha llegado, vuelve a 'disponible'.
     *
     * @param User $canceladoPor Usuario que ejecuta la cancelación
     * @param string $motivo Motivo de la cancelación
     *
     * Interno: solo lo llaman CitacionService y GestionAusenciaService, que escriben el evento.
     */
    public function cancelar(User $canceladoPor, string $motivo): void
    {
        $slot = Slot::findOrFail($this->slot_id);
        $horarioSlot = Carbon::parse($slot->fecha->toDateString().' '.$slot->hora_inicio);
        $slotPasado = now()->isAfter($horarioSlot);

        $slot->update([
            'estado' => $slotPasado
                ? EstadoSlot::NoOcupado->value
                : EstadoSlot::Disponible->value,
        ]);

        $this->update([
            'estado' => EstadoCita::Cancelada->value,
            'cancelado_por_id' => $canceladoPor->id,
            'motivo_cancelacion' => $motivo,
        ]);
    }

    // =========================================================================
    // Relaciones adicionales
    // =========================================================================

    /**
     * Apuntes de Historia Social que atienden esta cita (`plan_apuntes.cita_id`).
     * La cita se completa con el primero.
     *
     * @return HasMany<Apunte, $this>
     */
    public function apuntes(): HasMany
    {
        return $this->hasMany(Apunte::class, 'cita_id');
    }

    /**
     * Registros de atención que atienden esta cita (personas sin Historia Social).
     *
     * @return HasMany<RegistroAtencion, $this>
     */
    public function registrosAtencion(): HasMany
    {
        return $this->hasMany(RegistroAtencion::class, 'cita_id');
    }

    /**
     * Solicitud de la que nace (null en citas externas).
     *
     * @return BelongsTo<SolicitudCita, $this>
     */
    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudCita::class, 'solicitud_cita_id');
    }

    /**
     * Tipo de cita.
     *
     * @return BelongsTo<TipoCita, $this>
     */
    public function tipoCita(): BelongsTo
    {
        return $this->belongsTo(TipoCita::class, 'tipo_cita_id')->withTrashed();
    }

    /**
     * Cita que esta sustituye por reprogramación.
     *
     * @return BelongsTo<Cita, $this>
     */
    public function citaAnterior(): BelongsTo
    {
        return $this->belongsTo(self::class, 'cita_anterior_id');
    }

    /**
     * Cita que sustituye a esta si se reprogramó.
     *
     * @return HasOne<Cita, $this>
     */
    public function reprogramacion(): HasOne
    {
        return $this->hasOne(self::class, 'cita_anterior_id');
    }

    /**
     * Historial de la cita.
     *
     * @return HasMany<CitaEvento, $this>
     */
    public function eventos(): HasMany
    {
        return $this->hasMany(CitaEvento::class, 'cita_id')->orderBy('id');
    }

    /**
     * Acompañantes registrados al atenderla.
     *
     * @return HasMany<CitaAcompanante, $this>
     */
    public function acompanantes(): HasMany
    {
        return $this->hasMany(CitaAcompanante::class, 'cita_id');
    }

    /**
     * Cadena de reprogramaciones hasta esta cita, de la primera a esta.
     *
     * @return \Illuminate\Support\Collection<int, Cita>
     */
    public function cadenaReprogramaciones(): \Illuminate\Support\Collection
    {
        $cadena = collect([$this]);
        $actual = $this;

        while ($actual->cita_anterior_id !== null && ($anterior = self::withTrashed()->find($actual->cita_anterior_id)) !== null) {
            $cadena->prepend($anterior);
            $actual = $anterior;
        }

        return $cadena;
    }

    /**
     * Si es una cita externa aún sin persona identificada.
     *
     * @return bool
     */
    public function pendienteDeIdentificar(): bool
    {
        return $this->ciudadano_id === null;
    }

    /**
     * Ciudadano para auditoría.
     *
     * @return int|null
     */
    public function getCiudadanoId(): ?int
    {
        return $this->ciudadano_id;
    }

    /**
     * @return void
     *
     * @throws LogicException Si una cita no externa no tiene ciudadano.
     */
    private function exigirCiudadano(): void
    {
        if ($this->ciudadano_id === null && $this->origen !== OrigenCita::ApiExterna) {
            throw new LogicException('Solo una cita del canal externo puede quedar pendiente de identificar.');
        }
    }
}
