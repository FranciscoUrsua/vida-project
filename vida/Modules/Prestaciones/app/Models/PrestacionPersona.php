<?php

namespace Modules\Prestaciones\Models;

use App\Models\Ciudadano;
use App\Models\User;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Prestaciones\Enums\EstadoPrestacionPersona;

/**
 * Prestación que una persona tiene o ha tenido (§2.6 del diseño): fuente del bloque
 * «Prestaciones» de la ficha.
 *
 * Nace concedida (resolución), activa (directa o plaza asignada); concedida no es activa
 * hasta registrar el inicio efectivo (RN-08). Cada cambio de estado deja un evento inmutable
 * en `prestacion_persona_eventos`. Solo la escribe PrestacionPersonaService.
 *
 * @property int $id
 * @property int $ciudadano_id
 * @property int $prestacion_id
 * @property string|null $origen_type ResolucionLinea, Prescripcion, InscripcionSesion (actividad, fase 6) o null (directa)
 * @property int|null $origen_id
 * @property int|null $plan_id
 * @property int|null $plan_objetivo_id
 * @property EstadoPrestacionPersona $estado
 * @property Carbon|null $fecha_concesion
 * @property Carbon|null $fecha_inicio
 * @property Carbon|null $fecha_fin
 * @property string|null $motivo_suspension Cifrado.
 * @property string|null $motivo_fin Cifrado.
 * @property array<string, mixed>|null $condiciones
 * @property int $registrada_por_id
 * @property-read Prestacion $prestacion
 */
class PrestacionPersona extends Model
{
    use Auditable;
    use SoftDeletes;

    /** @var string */
    protected $table = 'prestaciones_persona';

    /** @var list<string> */
    protected $fillable = [
        'ciudadano_id', 'prestacion_id', 'origen_type', 'origen_id', 'plan_id', 'plan_objetivo_id', 'estado',
        'fecha_concesion', 'fecha_inicio', 'fecha_fin', 'motivo_suspension', 'motivo_fin', 'condiciones', 'registrada_por_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'estado' => EstadoPrestacionPersona::class,
        'fecha_concesion' => 'date',
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'motivo_suspension' => 'encrypted',
        'motivo_fin' => 'encrypted',
        'condiciones' => 'array',
    ];

    /**
     * Ciudadano, para la auditoría.
     *
     * @return int|null
     */
    public function getCiudadanoId(): ?int
    {
        return $this->ciudadano_id;
    }

    /**
     * Prestación del catálogo.
     *
     * @return BelongsTo<Prestacion, $this>
     */
    public function prestacion(): BelongsTo
    {
        return $this->belongsTo(Prestacion::class, 'prestacion_id')->withTrashed();
    }

    /**
     * Persona (sin scope: el acceso lo decide la policy).
     *
     * @return BelongsTo<Ciudadano, $this>
     */
    public function ciudadano(): BelongsTo
    {
        return $this->belongsTo(Ciudadano::class, 'ciudadano_id')->withoutGlobalScopes();
    }

    /**
     * Plan al que se vincula, si lo hay.
     *
     * @return BelongsTo<PlanDeIntervencion, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlanDeIntervencion::class, 'plan_id')->withoutGlobalScopes();
    }

    /**
     * Origen: línea de resolución o prescripción (null en las directas).
     *
     * @return MorphTo<Model, $this>
     */
    public function origen(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Quien la registró.
     *
     * @return BelongsTo<User, $this>
     */
    public function registradaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrada_por_id')->withTrashed();
    }

    /**
     * Historial de estados.
     *
     * @return HasMany<PrestacionPersonaEvento, $this>
     */
    public function eventos(): HasMany
    {
        return $this->hasMany(PrestacionPersonaEvento::class, 'prestacion_persona_id')->orderBy('id');
    }

    /**
     * Solo las de un estado.
     *
     * @param Builder<self> $query
     * @param EstadoPrestacionPersona $estado
     *
     * @return Builder<self>
     */
    public function scopeEnEstado(Builder $query, EstadoPrestacionPersona $estado): Builder
    {
        return $query->where('estado', $estado->value);
    }
}
