<?php

namespace Modules\Agenda\Models;

use App\Models\Ciudadano;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Agenda\Database\Factories\CitaFactory;
use Modules\Agenda\Enums\EstadoCita;
use Modules\Agenda\Enums\EstadoSlot;
use Modules\Agenda\Enums\OrigenCita;
use Modules\Centro\Models\Centro;
use Modules\Intervencion\Models\Apunte;

/**
 * Reserva de un slot con un ciudadano concreto.
 *
 * Una cita vincula un slot con un ciudadano y tiene un ciclo de vida propio.
 * Puede originarse en el sistema interno o vía API externa. Al crearse,
 * el slot asociado pasa a estado 'reservado'.
 *
 * @property int $id
 * @property int $slot_id
 * @property int $ciudadano_id
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
 */
class Cita extends Model
{
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
    ];

    /**
     * Slot reservado por la cita.
     *
     * @return BelongsTo<Slot, self>
     */
    public function slot(): BelongsTo
    {
        return $this->belongsTo(Slot::class);
    }

    /**
     * Ciudadano atendido en la cita.
     *
     * @return BelongsTo<Ciudadano, self>
     */
    public function ciudadano(): BelongsTo
    {
        return $this->belongsTo(Ciudadano::class);
    }

    /**
     * Profesional asignado a la cita.
     *
     * @return BelongsTo<User, self>
     */
    public function profesional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'profesional_id');
    }

    /**
     * Tipo de slot reservado para la cita.
     *
     * @return BelongsTo<TipoSlot, self>
     */
    public function tipoSlot(): BelongsTo
    {
        return $this->belongsTo(TipoSlot::class, 'tipo_slot_id');
    }

    /**
     * Centro donde se presta la cita.
     *
     * @return BelongsTo<Centro, self>
     */
    public function centro(): BelongsTo
    {
        return $this->belongsTo(Centro::class);
    }

    /**
     * Usuario que creó la cita.
     *
     * @return BelongsTo<User, self>
     */
    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por_id');
    }

    /**
     * Usuario que canceló la cita.
     *
     * @return BelongsTo<User, self>
     */
    public function canceladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelado_por_id');
    }

    /**
     * Reasignación asociada a la cita, si existe.
     *
     * @return HasOne<ReasignacionCita>
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
     * @param mixed $fecha
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
     * @param int $usuarioId
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
     * @param int $ciudadanoId
     *
     * @return Builder<Cita>
     */
    public function scopeDelCiudadano(Builder $query, int $ciudadanoId): Builder
    {
        return $query->where('ciudadano_id', $ciudadanoId);
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
     * @return void
     */
    public function noShowCiudadano(): void
    {
        $this->update(['estado' => EstadoCita::NoShowCiudadano->value]);
    }

    /**
     * Marca la cita como completada y registra el momento exacto.
     *
     * @return void
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
     * @return void
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
     * Apuntes de Historia Social vinculados a esta cita (polimórficos).
     *
     * Permite detectar apuntes existentes antes de una cancelación retroactiva.
     *
     * @return MorphMany<Apunte>
     */
    public function apuntes(): MorphMany
    {
        return $this->morphMany(Apunte::class, 'apuntable');
    }
}
