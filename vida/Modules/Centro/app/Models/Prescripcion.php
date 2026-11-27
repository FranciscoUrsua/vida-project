<?php

namespace Modules\Centro\Models;

use App\Contracts\AuditableModel;
use App\Enums\AccionAuditEnum;
use App\Models\Ciudadano;
use App\Traits\Auditable;
use App\Traits\Versionable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Intervencion\Models\PlanActuacionAyuntamiento;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Prestaciones\Models\Prestacion;
use Modules\Usuarios\Models\Profesional;

/**
 * Prescripción de un recurso (plaza o sesión de actividad) para un ciudadano.
 *
 * tipo_destino + destino_id implementan una relación polimórfica manual:
 *   - 'coleccion_plazas' → destino_id apunta a colecciones_plazas.id
 *   - 'sesion_actividad'  → destino_id apunta a sesiones_actividad.id
 * No es un `morphTo`: los valores de tipo_destino no son clases y un `morphMap` global
 * cambiaría también el `auditable_type` de la auditoría. Para poder cargar el destino con
 * eager loading hay una relación por tipo (`sesionActividad()`, `coleccionPlazas()`) y
 * `destino()` devuelve la que corresponde a tipo_destino (usabilidad U2, 27 nov 2026).
 *
 * estado: pendiente | en_lista_espera | asignada | activa | finalizada | cancelada
 *
 * @property int $id
 * @property int $profesional_id
 * @property int $ciudadano_id
 * @property int|null $plan_intervencion_id
 * @property int|null $compromiso_id
 * @property string $tipo_destino
 * @property int $destino_id
 * @property int|null $plaza_id
 * @property int|null $prestacion_id
 * @property string $estado
 * @property Carbon|null $fecha_asignacion
 * @property string|null $motivo_cancelacion
 * @property string|null $notas
 * @property-read Prestacion|null $prestacion
 * @property-read PlanDeIntervencion|null $planIntervencion
 * @property-read SesionActividad|null $sesionActividad
 * @property-read ColeccionPlazas|null $coleccionPlazas
 * @property Carbon $fecha_prescripcion
 * @property Carbon|null $fecha_inicio
 * @property Carbon|null $fecha_fin
 */
class Prescripcion extends Model implements AuditableModel
{
    use Auditable;
    use SoftDeletes;
    use Versionable;

    protected $table = 'prescripciones';

    protected $fillable = [
        'profesional_id',
        'ciudadano_id',
        'prestacion_id',
        'plan_intervencion_id',
        'compromiso_id',
        'tipo_destino',
        'destino_id',
        'plaza_id',
        'estado',
        'fecha_prescripcion',
        'fecha_asignacion',
        'fecha_inicio',
        'fecha_fin',
        'motivo_cancelacion',
        'notas',
    ];

    protected $casts = [
        'fecha_prescripcion' => 'date',
        'fecha_asignacion' => 'date',
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Profesional que emite la prescripción.
     *
     * @return BelongsTo<Profesional, self>
     */
    public function profesional(): BelongsTo
    {
        return $this->belongsTo(Profesional::class, 'profesional_id');
    }

    /**
     * Ciudadano beneficiario de la prescripción.
     *
     * @return BelongsTo<Ciudadano, self>
     */
    public function ciudadano(): BelongsTo
    {
        return $this->belongsTo(Ciudadano::class, 'ciudadano_id');
    }

    /**
     * Plaza concreta asignada (nullable hasta la asignación efectiva).
     *
     * @return BelongsTo<Plaza, self>
     */
    public function plaza(): BelongsTo
    {
        return $this->belongsTo(Plaza::class, 'plaza_id');
    }

    /**
     * Compromiso del plan de intervención vinculado opcionalmente a esta prescripción.
     * El vínculo es de referencia: el estado del compromiso no cambia con la prescripción.
     *
     * @return BelongsTo<PlanActuacionAyuntamiento, $this>
     */
    public function compromiso(): BelongsTo
    {
        return $this->belongsTo(PlanActuacionAyuntamiento::class, 'compromiso_id');
    }

    /**
     * Registro de lista de espera generado por esta prescripción.
     *
     * @return HasOne<ListaEspera, self>
     */
    public function listaEspera(): HasOne
    {
        return $this->hasOne(ListaEspera::class, 'prescripcion_id');
    }

    /**
     * Prestación que se prescribe.
     *
     * @return BelongsTo<Prestacion, $this>
     */
    public function prestacion(): BelongsTo
    {
        return $this->belongsTo(Prestacion::class, 'prestacion_id');
    }

    /**
     * Plan de intervención desde el que se prescribe, si lo hay.
     *
     * @return BelongsTo<PlanDeIntervencion, $this>
     */
    public function planIntervencion(): BelongsTo
    {
        return $this->belongsTo(PlanDeIntervencion::class, 'plan_intervencion_id')->withoutGlobalScopes();
    }

    /**
     * Sesión de actividad de destino. Solo tiene sentido con tipo_destino = 'sesion_actividad':
     * usar `destino()`.
     *
     * @return BelongsTo<SesionActividad, $this>
     */
    public function sesionActividad(): BelongsTo
    {
        return $this->belongsTo(SesionActividad::class, 'destino_id');
    }

    /**
     * Colección de plazas de destino. Solo tiene sentido con tipo_destino = 'coleccion_plazas':
     * usar `destino()`.
     *
     * @return BelongsTo<ColeccionPlazas, $this>
     */
    public function coleccionPlazas(): BelongsTo
    {
        return $this->belongsTo(ColeccionPlazas::class, 'destino_id');
    }

    /**
     * Resuelve el destino de la prescripción según tipo_destino.
     * Devuelve la ColeccionPlazas o la SesionActividad correspondiente (cargada si se pidió con
     * eager loading).
     *
     * @return ColeccionPlazas|SesionActividad|null
     */
    public function destino(): ColeccionPlazas|SesionActividad|null
    {
        return match ($this->tipo_destino) {
            'coleccion_plazas' => $this->coleccionPlazas,
            'sesion_actividad' => $this->sesionActividad,
            default => null,
        };
    }

    /**
     * Nombre legible del recurso prescrito: la actividad o la colección de plazas y, si no
     * se puede resolver, la prestación.
     *
     * @return string
     */
    public function nombreRecurso(): string
    {
        $destino = $this->destino();

        $nombre = match (true) {
            $destino instanceof SesionActividad => $destino->actividad?->nombre,
            $destino instanceof ColeccionPlazas => $destino->nombre,
            default => null,
        };

        return $nombre ?? $this->prestacion?->nombre ?? 'Recurso';
    }

    /**
     * Centro del recurso de destino (el de la actividad o el de la colección de plazas).
     *
     * @return Centro|null
     */
    public function centroDestino(): ?Centro
    {
        $destino = $this->destino();

        return match (true) {
            $destino instanceof SesionActividad => $destino->actividad?->centro,
            $destino instanceof ColeccionPlazas => $destino->centro,
            default => null,
        };
    }

    /**
     * Etiqueta del estado de la prescripción para la interfaz.
     *
     * @return string
     */
    public function etiquetaEstado(): string
    {
        return self::ETIQUETAS_ESTADO[$this->estado] ?? ucfirst(str_replace('_', ' ', $this->estado));
    }

    /** Etiquetas de los estados (los valores los decide el código: ver la cabecera). */
    public const ETIQUETAS_ESTADO = [
        'pendiente' => 'Pendiente',
        'en_lista_espera' => 'En lista de espera',
        'asignada' => 'Asignada',
        'activa' => 'Activa',
        'finalizada' => 'Finalizada',
        'cancelada' => 'Cancelada',
    ];

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Filtra prescripciones con estado activa.
     *
     * @param Builder<static> $query
     *
     * @return Builder<static>
     */
    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('estado', 'activa');
    }

    /**
     * Filtra prescripciones con estado pendiente.
     *
     * @param Builder<static> $query
     *
     * @return Builder<static>
     */
    public function scopePendientes(Builder $query): Builder
    {
        return $query->where('estado', 'pendiente');
    }

    /**
     * Filtra prescripciones consideradas "activas" a efectos del guard de cierre de Historia Social.
     * Incluye: pendiente, en_lista_espera, asignada, activa.
     *
     * @param Builder<static> $query
     *
     * @return Builder<static>
     */
    public function scopeEnCurso(Builder $query): Builder
    {
        return $query->whereIn('estado', ['pendiente', 'en_lista_espera', 'asignada', 'activa']);
    }

    /**
     * Ciudadano al que pertenece la prescripción (auditoría).
     *
     * @return int|null
     */
    public function getCiudadanoId(): ?int
    {
        return $this->ciudadano_id;
    }

    /**
     * Resumen de auditoría (U3): «Prescribió “X”», «Canceló la prescripción de “X”» o
     * «Modificó la prescripción de “X”». El motivo de la cancelación va en `datos_despues`.
     *
     * @param AccionAuditEnum $accion
     * @param array<string, mixed> $antes
     * @param array<string, mixed> $despues
     *
     * @return string|null
     */
    public function resumenAuditoria(AccionAuditEnum $accion, array $antes, array $despues): ?string
    {
        $recurso = $this->nombreRecurso();

        return match (true) {
            $accion === AccionAuditEnum::Crear => "Prescribió “{$recurso}”",
            $accion === AccionAuditEnum::Editar && ($despues['estado'] ?? null) === 'cancelada' => "Canceló la prescripción de “{$recurso}”",
            $accion === AccionAuditEnum::Editar => "Modificó la prescripción de “{$recurso}”",
            $accion === AccionAuditEnum::Eliminar => "Eliminó la prescripción de “{$recurso}”",
            default => null,
        };
    }
}
