<?php

namespace Modules\Centro\Models;

use App\Models\User;
use App\Traits\Versionable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Centro\Enums\ViaInscripcion;
use Modules\Usuarios\Models\Profesional;

/**
 * Sesión concreta de una actividad.
 *
 * Los aforos de sesión sobreescriben los de la actividad cuando se informan.
 *
 * estado: programada | celebrada | cancelada
 *
 * @property int $id
 * @property int $actividad_id
 * @property Carbon $fecha
 * @property string $hora_inicio
 * @property string|null $hora_fin
 * @property int|null $aforo_total
 * @property int|null $aforo_prescripcion
 * @property string $estado
 * @property int|null $sala_id
 * @property string|null $motivo_cancelacion
 * @property Carbon|null $fecha_cancelacion
 * @property int|null $cancelada_por_id
 * @property-read Actividad $actividad
 * @property-read Collection<int, Profesional> $profesionales
 */
class SesionActividad extends Model
{
    use SoftDeletes;
    use Versionable;

    protected $table = 'sesiones_actividad';

    protected $fillable = [
        'actividad_id',
        'fecha',
        'hora_inicio',
        'hora_fin',
        'aforo_total',
        'aforo_prescripcion',
        'estado',
        'sala_id',
        'notas',
        'motivo_cancelacion',
        'fecha_cancelacion',
        'cancelada_por_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'aforo_total' => 'integer',
        'aforo_prescripcion' => 'integer',
        'fecha_cancelacion' => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Actividad a la que pertenece esta sesión.
     *
     * @return BelongsTo<Actividad, $this>
     */
    public function actividad(): BelongsTo
    {
        return $this->belongsTo(Actividad::class, 'actividad_id');
    }

    /**
     * Sala donde se celebra la sesión. Nullable: una sesión puede no tener sala asignada
     * (actividad exterior, itinerante u online). La disponibilidad de la sala no se
     * valida en este módulo; corresponde al módulo de Agenda.
     *
     * @return BelongsTo<Sala, $this>
     */
    public function sala(): BelongsTo
    {
        return $this->belongsTo(Sala::class);
    }

    /**
     * Profesionales que dirigen esta sesión concreta.
     * Permite rotación de profesionales sesión a sesión independientemente de la actividad.
     *
     * @return BelongsToMany<Profesional, $this>
     */
    public function profesionales(): BelongsToMany
    {
        return $this->belongsToMany(Profesional::class, 'sesion_actividad_profesional')
            ->withTimestamps();
    }

    /**
     * Prescripciones dirigidas a esta sesión de actividad.
     *
     * @return HasMany<Prescripcion, $this>
     */
    public function prescripciones(): HasMany
    {
        return $this->hasMany(Prescripcion::class, 'destino_id')
            ->where('tipo_destino', 'sesion_actividad');
    }

    /**
     * Inscripciones de personas en esta sesión (vigentes y dadas de baja).
     *
     * @return HasMany<InscripcionSesion, $this>
     */
    public function inscripciones(): HasMany
    {
        return $this->hasMany(InscripcionSesion::class, 'sesion_actividad_id');
    }

    /**
     * Usuario que canceló la sesión.
     *
     * @return BelongsTo<User, $this>
     */
    public function canceladaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelada_por_id');
    }

    // -------------------------------------------------------------------------
    // Aforo (fase 6, paso 1)
    // -------------------------------------------------------------------------

    /**
     * Aforo total de la sesión: el suyo o, si no lo tiene, el de la actividad. Null = sin límite.
     *
     * @return int|null
     */
    public function aforoEfectivo(): ?int
    {
        return $this->aforo_total ?? $this->actividad->aforoEfectivo();
    }

    /**
     * Cupo de prescripción de la sesión: el suyo o el de la actividad. Solo tiene sentido
     * en las mixtas; null en una mixta = sin cupo reservado (bolsa común, F6-8).
     *
     * @return int|null
     */
    public function aforoPrescripcionEfectivo(): ?int
    {
        return $this->aforo_prescripcion ?? $this->actividad->aforoPrescripcionEfectivo();
    }

    /**
     * Plazas de una vía, sin descontar las ocupadas. Null = sin límite; 0 si la actividad
     * no admite la vía.
     *
     * - Libre o prescripción puras: todo el aforo es de esa vía.
     * - Mixta con cupo de prescripción: prescripción = cupo; libre = aforo total − cupo.
     * - Mixta sin cupo de prescripción (F6-8): las dos vías comparten el aforo total.
     *
     * @param ViaInscripcion $via
     *
     * @return int|null
     */
    public function capacidad(ViaInscripcion $via): ?int
    {
        if (! $via->admitidaEn($this->actividad->modo_acceso)) {
            return 0;
        }

        $total = $this->aforoEfectivo();

        if ($this->aforoCompartido()) {
            return $total;
        }

        $cupo = (int) $this->aforoPrescripcionEfectivo();

        if ($via === ViaInscripcion::Prescripcion) {
            return $cupo;
        }

        return $total === null ? null : max(0, $total - $cupo);
    }

    /**
     * Si las vías comparten el aforo: siempre que no haya un cupo de prescripción
     * reservado, es decir, salvo en una mixta con cupo (F6-8).
     *
     * @return bool
     */
    public function aforoCompartido(): bool
    {
        return $this->actividad->modo_acceso !== 'mixta' || $this->aforoPrescripcionEfectivo() === null;
    }

    /**
     * Inscripciones que cuentan contra la capacidad de una vía: las de la vía, o todas si
     * el aforo es compartido.
     *
     * @param ViaInscripcion $via
     *
     * @return int
     */
    public function ocupadasContra(ViaInscripcion $via): int
    {
        return $this->ocupadas($this->aforoCompartido() ? null : $via);
    }

    /**
     * Plazas libres para una vía. Null = sin límite; 0 si la actividad no admite la vía.
     *
     * Para decidir una inscripción hay que llamarlo dentro de la transacción que
     * bloquea la sesión (InscripcionActividadService), no antes.
     *
     * @param ViaInscripcion $via
     *
     * @return int|null
     */
    public function plazasLibres(ViaInscripcion $via): ?int
    {
        $capacidad = $this->capacidad($via);

        return $capacidad === null ? null : max(0, $capacidad - $this->ocupadasContra($via));
    }

    /**
     * Inscripciones vigentes, de una vía o de todas.
     *
     * @param ViaInscripcion|null $via
     *
     * @return int
     */
    public function ocupadas(?ViaInscripcion $via = null): int
    {
        return $this->inscripciones()
            ->vigentes()
            ->when($via !== null, fn ($q) => $q->where('via', $via->value))
            ->count();
    }

    /**
     * Si la sesión está programada y aún no ha empezado: solo entonces admite inscripciones.
     *
     * @return bool
     */
    public function admiteInscripciones(): bool
    {
        return $this->estado === 'programada' && $this->inicio()->isFuture();
    }

    /**
     * Fecha y hora de inicio de la sesión.
     *
     * @return Carbon
     */
    public function inicio(): Carbon
    {
        return $this->fecha->copy()->setTimeFromTimeString((string) $this->hora_inicio);
    }

    /**
     * Si tiene inscripciones o prescripciones (de cualquier estado): entonces no se borra,
     * se cancela (F6-5).
     *
     * @return bool
     */
    public function tieneParticipantes(): bool
    {
        return $this->inscripciones()->exists() || $this->prescripciones()->exists();
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Filtra sesiones con estado programada.
     *
     * @param Builder<static> $query
     *
     * @return Builder<static>
     */
    public function scopeProgramadas(Builder $query): Builder
    {
        return $query->where('estado', 'programada');
    }
}
