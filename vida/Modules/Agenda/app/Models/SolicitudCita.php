<?php

namespace Modules\Agenda\Models;

use App\Models\Ciudadano;
use App\Models\Scopes\AmbitoUoScope;
use App\Models\User;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use LogicException;
use Modules\Agenda\Enums\CanalSolicitudCita;
use Modules\Agenda\Enums\DestinoCita;
use Modules\Agenda\Enums\EstadoSolicitudCita;
use Modules\Agenda\Enums\UrgenciaCita;
use Modules\Centro\Models\Centro;

/**
 * Solicitud de cita: la necesidad de citar a una persona (docs/modulo-citas.md §2.2).
 *
 * Toda cita del canal interno nace de una, aunque se cree y se resuelva en el
 * mismo momento en ventanilla: así la demanda y la demora se miden igual. Solo
 * la escriben los servicios de Agenda (SolicitudCitaService, CitacionService).
 *
 * Las transiciones de estado se validan aquí: citada, desistida y anulada son finales.
 * El motivo profesional solo lo ven los roles con acceso a la Historia Social
 * (motivoVisiblePara()); quien cita ve las observaciones, sin contenido sensible.
 *
 * @property int $id
 * @property int $ciudadano_id
 * @property int $centro_id
 * @property int $solicitante_id
 * @property CanalSolicitudCita $canal
 * @property int $tipo_cita_id
 * @property UrgenciaCita $urgencia
 * @property DestinoCita $destino
 * @property int|null $profesional_destino_id
 * @property string|null $servicio_destino Slug del cargo.
 * @property Carbon|null $no_antes_de
 * @property Carbon $no_despues_de
 * @property string|null $motivo Cifrado.
 * @property string|null $observaciones_citacion Cifrado.
 * @property string|null $contexto_type
 * @property int|null $contexto_id
 * @property int|null $solicitud_anterior_id
 * @property EstadoSolicitudCita $estado
 * @property int|null $gestionada_por_id
 * @property Carbon|null $resuelta_en
 * @property string|null $motivo_cierre Cifrado.
 * @property Carbon|null $aviso_fuera_plazo_en
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Ciudadano $ciudadano
 * @property-read Centro $centro
 * @property-read User $solicitante
 * @property-read TipoCita $tipoCita
 * @property-read User|null $profesionalDestino
 * @property-read User|null $gestionadaPor
 */
class SolicitudCita extends Model
{
    use Auditable;
    use SoftDeletes;

    /** @var string */
    protected $table = 'solicitudes_cita';

    /** @var list<string> */
    protected $fillable = [
        'ciudadano_id',
        'centro_id',
        'solicitante_id',
        'canal',
        'tipo_cita_id',
        'urgencia',
        'destino',
        'profesional_destino_id',
        'servicio_destino',
        'no_antes_de',
        'no_despues_de',
        'motivo',
        'observaciones_citacion',
        'contexto_type',
        'contexto_id',
        'solicitud_anterior_id',
        'estado',
        'gestionada_por_id',
        'resuelta_en',
        'motivo_cierre',
        'aviso_fuera_plazo_en',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'canal' => CanalSolicitudCita::class,
        'urgencia' => UrgenciaCita::class,
        'destino' => DestinoCita::class,
        'estado' => EstadoSolicitudCita::class,
        'no_antes_de' => 'date',
        'no_despues_de' => 'date',
        'motivo' => 'encrypted',
        'observaciones_citacion' => 'encrypted',
        'motivo_cierre' => 'encrypted',
        'resuelta_en' => 'datetime',
        'aviso_fuera_plazo_en' => 'datetime',
    ];

    /**
     * Valida la transición de estado antes de guardar.
     *
     * @return void
     *
     * @throws LogicException
     */
    protected static function booted(): void
    {
        static::updating(fn (self $solicitud) => $solicitud->validarTransicion());
    }

    /**
     * Persona a cuyo nombre se pide.
     *
     * @return BelongsTo<Ciudadano, $this>
     */
    public function ciudadano(): BelongsTo
    {
        // Quien cita suele no tener historia de la persona en su UO
        return $this->belongsTo(Ciudadano::class, 'ciudadano_id')->withoutGlobalScope(AmbitoUoScope::class);
    }

    /**
     * Centro en cuya bandeja cae.
     *
     * @return BelongsTo<Centro, $this>
     */
    public function centro(): BelongsTo
    {
        return $this->belongsTo(Centro::class, 'centro_id');
    }

    /**
     * Quién la registró.
     *
     * @return BelongsTo<User, $this>
     */
    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitante_id');
    }

    /**
     * Tipo de cita pedido.
     *
     * @return BelongsTo<TipoCita, $this>
     */
    public function tipoCita(): BelongsTo
    {
        return $this->belongsTo(TipoCita::class, 'tipo_cita_id');
    }

    /**
     * Profesional pedido (destino profesional concreto).
     *
     * @return BelongsTo<User, $this>
     */
    public function profesionalDestino(): BelongsTo
    {
        return $this->belongsTo(User::class, 'profesional_destino_id');
    }

    /**
     * Quién la tiene en gestión o la resolvió.
     *
     * @return BelongsTo<User, $this>
     */
    public function gestionadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gestionada_por_id');
    }

    /**
     * Elemento del que nace: plan, seguimiento o registro de atención.
     *
     * @return MorphTo<Model, $this>
     */
    public function contexto(): MorphTo
    {
        return $this->morphTo('contexto');
    }

    /**
     * Citas dadas para esta solicitud (la vigente y sus reprogramaciones).
     *
     * @return HasMany<Cita, $this>
     */
    public function citas(): HasMany
    {
        return $this->hasMany(Cita::class, 'solicitud_cita_id');
    }

    /**
     * Historial de la solicitud.
     *
     * @return HasMany<CitaEvento, $this>
     */
    public function eventos(): HasMany
    {
        return $this->hasMany(CitaEvento::class, 'solicitud_cita_id')->orderBy('id');
    }

    /**
     * Solicitud de la que nace al cancelar una cita.
     *
     * @return BelongsTo<SolicitudCita, $this>
     */
    public function solicitudAnterior(): BelongsTo
    {
        return $this->belongsTo(self::class, 'solicitud_anterior_id');
    }

    /**
     * Solicitudes abiertas (pendientes o en gestión).
     *
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeAbiertas(Builder $query): Builder
    {
        return $query->whereIn('estado', [EstadoSolicitudCita::Pendiente, EstadoSolicitudCita::EnGestion]);
    }

    /**
     * Orden de la bandeja: urgencia y fecha límite.
     *
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeOrdenBandeja(Builder $query): Builder
    {
        return $query
            ->orderByRaw("CASE urgencia WHEN 'urgente' THEN 0 WHEN 'preferente' THEN 1 ELSE 2 END")
            ->orderBy('no_despues_de')
            ->orderBy('created_at');
    }

    /**
     * Motivo profesional si el usuario tiene acceso a la Historia Social; si no, null.
     * Única comprobación: las vistas no deciden.
     *
     * @param User|null $usuario
     * @return string|null
     */
    public function motivoVisiblePara(?User $usuario): ?string
    {
        return $usuario?->can('historia.leer') ? $this->motivo : null;
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
     * @throws LogicException Si la transición no es válida.
     */
    private function validarTransicion(): void
    {
        if (! $this->isDirty('estado')) {
            return;
        }

        $antes = EstadoSolicitudCita::from($this->getRawOriginal('estado'));

        if (! $antes->puedePasarA($this->estado)) {
            throw new LogicException("La solicitud no puede pasar de «{$antes->label()}» a «{$this->estado->label()}».");
        }
    }
}
