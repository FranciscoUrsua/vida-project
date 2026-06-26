<?php

namespace App\Models;

use App\Contracts\AuditableModel;
use App\Models\Scopes\AmbitoUoScope;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Centro\Models\Prescripcion;
use Modules\Escalas\Models\PaseEscala;
use Modules\Intervencion\Models\AsignacionProfesional;

/**
 * @use HasFactory<\Database\Factories\HistoriaSocialFactory>
 *
 * Modelo stub de Historia Social.
 *
 * Stub mínimo para que las Policies y los tests puedan referenciar
 * la entidad. La implementación completa corresponde al módulo
 * Intervencion (docs/glosario.md § Historia Social).
 *
 * La Historia Social es el instrumento central de intervención:
 * recoge la demanda del ciudadano, el diagnóstico, el plan y el
 * seguimiento. Se abre cuando existe una demanda que requiere
 * valoración, plan o seguimiento municipal (principio 3.2).
 *
 * Referencia definitiva: Modules\Intervencion\Models\Historia
 *
 * @property int $id
 * @property int $ciudadano_id
 * @property int $unidad_organizativa_id
 * @property bool $ciudadano_protegido
 * @property string $estado
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 *
 * @todo Mover a Modules\Intervencion\Models\Historia y completar
 *       con todos los atributos, relaciones y lógica de dominio.
 */
class HistoriaSocial extends Model implements AuditableModel
{
    use Auditable;
    use HasFactory;
    use SoftDeletes;

    /**
     * Columna de FK a unidades_organizativas usada por AmbitoUoScope.
     */
    public string $ambitoUoColumn = 'unidad_organizativa_id';

    /** @var string Tabla de base de datos (provisional) */
    protected $table = 'historias_sociales';

    /** @var list<string> Campos asignables en masa */
    protected $fillable = [
        'ciudadano_id',
        'unidad_organizativa_id',
        'ciudadano_protegido',
        'estado',
    ];

    /** @var array<string, string> Conversiones de tipo */
    protected $casts = [
        'ciudadano_protegido' => 'boolean',
    ];

    /**
     * Registra el Global Scope de ámbito de UO para filtrado automático.
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new AmbitoUoScope);
    }

    /**
     * {@inheritDoc}
     */
    public function getCiudadanoId(): ?int
    {
        return $this->ciudadano_id;
    }

    /**
     * Ciudadano titular de la Historia Social.
     * Sin FK en la migración (TODO: módulo Ciudadanía), por eso sin withoutGlobalScope.
     *
     * @return BelongsTo<Ciudadano, $this>
     */
    public function ciudadano(): BelongsTo
    {
        return $this->belongsTo(Ciudadano::class, 'ciudadano_id');
    }

    /**
     * UO responsable de la Historia Social.
     *
     * @return BelongsTo<UnidadOrganizativa, $this>
     */
    public function unidadOrganizativa(): BelongsTo
    {
        return $this->belongsTo(UnidadOrganizativa::class, 'unidad_organizativa_id');
    }

    /**
     * Pases de escala registrados en esta historia social.
     * Filtrar por tipo_escala_id para obtener la serie temporal de un instrumento concreto.
     *
     * @return HasMany<PaseEscala, $this>
     */
    public function pasesEscala(): HasMany
    {
        return $this->hasMany(PaseEscala::class, 'historia_id');
    }

    /**
     * Historial completo de asignaciones de profesional de referencia.
     *
     * @return HasMany<AsignacionProfesional, $this>
     */
    public function asignaciones(): HasMany
    {
        return $this->hasMany(AsignacionProfesional::class, 'historia_id');
    }

    /**
     * Asignación de profesional de referencia actualmente vigente (fecha_fin null).
     *
     * @return HasOne<AsignacionProfesional, $this>
     */
    public function asignacionVigente(): HasOne
    {
        return $this->hasOne(AsignacionProfesional::class, 'historia_id')->whereNull('fecha_fin');
    }

    /**
     * Prescripciones de recursos asociadas a esta Historia Social.
     *
     * @return HasMany<Prescripcion, $this>
     */
    public function prescripciones(): HasMany
    {
        return $this->hasMany(Prescripcion::class, 'ciudadano_id', 'ciudadano_id');
    }

    /**
     * Cierra la Historia Social estableciendo estado = 'cerrada'.
     *
     * Restricción de dominio: no se puede cerrar si existen prescripciones
     * en estado pendiente, en_lista_espera, asignada o activa.
     *
     * @throws \DomainException Si hay prescripciones activas pendientes de resolver.
     */
    public function cerrar(): void
    {
        if ($this->prescripciones()->enCurso()->exists()) {
            throw new \DomainException(
                'No se puede cerrar la historia social con prescripciones activas.'
            );
        }

        $this->update(['estado' => 'cerrada']);
    }
}
