<?php

namespace App\Models;

use App\Models\Scopes\AmbitoUoScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Escalas\Models\PaseEscala;

/**
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
 * @property int $unidad_organizativa_id UO responsable de la Historia
 * @property bool $ciudadano_protegido Ciudadano pertenece a colectivo especialmente protegido
 * @property string $estado abierta | en_seguimiento | cerrada
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 *
 * @todo Mover a Modules\Intervencion\Models\Historia y completar
 *       con todos los atributos, relaciones y lógica de dominio.
 */
class HistoriaSocial extends Model
{
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

    // -------------------------------------------------------------------------
    // Ciclo de vida
    // -------------------------------------------------------------------------

    /**
     * Registra el Global Scope de ámbito de UO para filtrado automático.
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new AmbitoUoScope);
    }

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Ciudadano titular de la Historia Social.
     * Sin FK en la migración (TODO: módulo Ciudadanía), por eso sin withoutGlobalScope.
     *
     * @return BelongsTo<Ciudadano, HistoriaSocial>
     */
    public function ciudadano(): BelongsTo
    {
        return $this->belongsTo(Ciudadano::class, 'ciudadano_id');
    }

    /**
     * UO responsable de la Historia Social.
     *
     * @return BelongsTo<UnidadOrganizativa, HistoriaSocial>
     */
    public function unidadOrganizativa(): BelongsTo
    {
        return $this->belongsTo(UnidadOrganizativa::class, 'unidad_organizativa_id');
    }

    /**
     * Pases de escala registrados en esta historia social.
     * Filtrar por tipo_escala_id para obtener la serie temporal de un instrumento concreto.
     *
     * @return HasMany<PaseEscala, HistoriaSocial>
     */
    public function pasesEscala(): HasMany
    {
        return $this->hasMany(PaseEscala::class, 'historia_id');
    }
}
