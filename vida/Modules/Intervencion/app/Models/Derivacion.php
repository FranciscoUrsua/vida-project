<?php

namespace Modules\Intervencion\Models;

use App\Models\HistoriaSocial;
use App\Models\User;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Centro\Models\Centro;
use Modules\Intervencion\Enums\EstadoDerivacion;
use Modules\Intervencion\Enums\ModalidadDerivacion;
use Modules\Prestaciones\Models\Prestacion;

/**
 * Derivación de una persona desde su plan principal a un centro de atención especializada
 * (fase 3, F3-5 y F3-6; docs/modulo-intervencion.md, «Derivación»).
 *
 * Es una prestación con vía de acceso `derivacion`: al aceptarla, el centro de destino
 * abre un plan especializado del tipo de la prestación, vinculado al plan principal
 * (`plan_asp_id`). Solo la escribe DerivacionService, que deja cada cambio de estado en
 * `derivacion_eventos` (historial inmutable). El historial sustituye al versionado.
 *
 * Sin scope de ámbito: la bandeja del centro de destino debe ver derivaciones de personas
 * cuya historia es de otro centro. Quién ve qué lo deciden el servicio y la bandeja
 * (identidad de personas protegidas, RN-19 de la addenda).
 *
 * @property int $id
 * @property int $historia_id
 * @property ModalidadDerivacion $modalidad
 * @property int|null $plan_origen_id Null en un encargo creado desde la Historia Social.
 * @property int|null $plan_objetivo_id
 * @property int|null $prestacion_id Null en un encargo.
 * @property int|null $tipo_plan_id Copia del tipo de plan de la prestación al derivar; null en un encargo.
 * @property int $centro_destino_id
 * @property string $motivo Cifrado.
 * @property EstadoDerivacion $estado
 * @property int $derivada_por_id
 * @property int|null $resuelta_por_id
 * @property int|null $profesional_responsable_id
 * @property int|null $plan_especializado_id
 * @property string|null $motivo_rechazo Cifrado.
 * @property string|null $motivo_anulacion Cifrado.
 * @property Carbon|null $resuelta_en
 * @property string|null $resultado Resultado del encargo, cifrado.
 * @property Carbon|null $cerrada_en
 * @property int|null $cerrada_por_id
 * @property Carbon $created_at
 * @property-read HistoriaSocial $historia
 * @property-read PlanDeIntervencion|null $planOrigen
 * @property-read PlanObjetivo|null $objetivo
 * @property-read Prestacion|null $prestacion
 * @property-read TipoPlan|null $tipoPlan
 * @property-read Centro $centroDestino
 * @property-read User $derivadaPor
 * @property-read User|null $profesionalResponsable
 * @property-read PlanDeIntervencion|null $planEspecializado
 * @property-read User|null $cerradaPor
 */
class Derivacion extends Model
{
    use Auditable;
    use SoftDeletes;

    /** @var string */
    protected $table = 'derivaciones';

    /** @var list<string> */
    protected $fillable = [
        'historia_id', 'plan_origen_id', 'plan_objetivo_id', 'prestacion_id', 'tipo_plan_id',
        'centro_destino_id', 'motivo', 'estado', 'derivada_por_id', 'resuelta_por_id',
        'profesional_responsable_id', 'plan_especializado_id', 'motivo_rechazo', 'motivo_anulacion', 'resuelta_en',
        'modalidad', 'resultado', 'cerrada_en', 'cerrada_por_id',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'modalidad' => 'plan_especializado',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'estado' => EstadoDerivacion::class,
        'modalidad' => ModalidadDerivacion::class,
        'resultado' => 'encrypted',
        'cerrada_en' => 'datetime',
        'motivo' => 'encrypted',
        'motivo_rechazo' => 'encrypted',
        'motivo_anulacion' => 'encrypted',
        'resuelta_en' => 'datetime',
    ];

    /**
     * Ciudadano de la derivación, para la auditoría.
     *
     * @return int|null
     */
    public function getCiudadanoId(): ?int
    {
        return HistoriaSocial::withoutGlobalScopes()->whereKey($this->historia_id)->value('ciudadano_id');
    }

    /**
     * Historia Social de la persona derivada.
     *
     * @return BelongsTo<HistoriaSocial, $this>
     */
    public function historia(): BelongsTo
    {
        return $this->belongsTo(HistoriaSocial::class, 'historia_id')->withoutGlobalScopes();
    }

    /**
     * Plan principal desde el que se deriva.
     *
     * @return BelongsTo<PlanDeIntervencion, $this>
     */
    public function planOrigen(): BelongsTo
    {
        return $this->belongsTo(PlanDeIntervencion::class, 'plan_origen_id')->withoutGlobalScopes();
    }

    /**
     * Objetivo del plan principal desde el que se deriva, si lo hay.
     *
     * @return BelongsTo<PlanObjetivo, $this>
     */
    public function objetivo(): BelongsTo
    {
        return $this->belongsTo(PlanObjetivo::class, 'plan_objetivo_id');
    }

    /**
     * Prestación de derivación.
     *
     * @return BelongsTo<Prestacion, $this>
     */
    public function prestacion(): BelongsTo
    {
        return $this->belongsTo(Prestacion::class, 'prestacion_id')->withTrashed();
    }

    /**
     * Tipo de plan especializado que se abre al aceptar.
     *
     * @return BelongsTo<TipoPlan, $this>
     */
    public function tipoPlan(): BelongsTo
    {
        return $this->belongsTo(TipoPlan::class, 'tipo_plan_id');
    }

    /**
     * Centro de destino.
     *
     * @return BelongsTo<Centro, $this>
     */
    public function centroDestino(): BelongsTo
    {
        return $this->belongsTo(Centro::class, 'centro_destino_id');
    }

    /**
     * Profesional que derivó (el TSR).
     *
     * @return BelongsTo<User, $this>
     */
    public function derivadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'derivada_por_id')->withTrashed();
    }

    /**
     * Profesional responsable asignado al aceptar.
     *
     * @return BelongsTo<User, $this>
     */
    public function profesionalResponsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'profesional_responsable_id')->withTrashed();
    }

    /**
     * Plan especializado abierto al aceptar.
     *
     * @return BelongsTo<PlanDeIntervencion, $this>
     */
    public function planEspecializado(): BelongsTo
    {
        return $this->belongsTo(PlanDeIntervencion::class, 'plan_especializado_id')->withoutGlobalScopes();
    }

    /**
     * Historial de la derivación, del más antiguo al más reciente.
     *
     * @return HasMany<DerivacionEvento, $this>
     */
    public function eventos(): HasMany
    {
        return $this->hasMany(DerivacionEvento::class, 'derivacion_id')->orderBy('id');
    }

    /**
     * Solo derivaciones pendientes.
     *
     * @param Builder<self> $query
     *
     * @return Builder<self>
     */
    public function scopePendientes(Builder $query): Builder
    {
        return $query->where('estado', EstadoDerivacion::Pendiente->value);
    }

    /**
     * Si es un encargo profesional (EP-1) y no una derivación a atención especializada.
     *
     * @return bool
     */
    public function esEncargo(): bool
    {
        return $this->modalidad === ModalidadDerivacion::Encargo;
    }

    /**
     * Usuario que cerró el encargo.
     *
     * @return BelongsTo<User, $this>
     */
    public function cerradaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cerrada_por_id');
    }

    /**
     * Solo encargos profesionales.
     *
     * @param Builder<self> $query
     *
     * @return Builder<self>
     */
    public function scopeEncargos(Builder $query): Builder
    {
        return $query->where('modalidad', ModalidadDerivacion::Encargo->value);
    }

    /**
     * Encargos asignados al profesional, en curso o ya cerrados (sus «ciudadanos
     * asignados», EP-6): los pendientes, rechazados o anulados no son suyos.
     *
     * @param Builder<self> $query
     * @param User $profesional
     *
     * @return Builder<self>
     */
    public function scopeDeProfesional(Builder $query, User $profesional): Builder
    {
        return $query->encargos()
            ->where('profesional_responsable_id', $profesional->id)
            ->whereIn('estado', [EstadoDerivacion::Aceptada->value, EstadoDerivacion::Cerrada->value]);
    }
}
