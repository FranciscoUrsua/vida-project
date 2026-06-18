<?php

namespace Modules\Intervencion\Models;

use App\Traits\Auditable;
use App\Traits\Versionable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\HistoriaSocial;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Intervencion\Database\Factories\FichaFactory;

/**
 * Ficha de datos reales de una valoración.
 *
 * Cada ficha corresponde a un TipoFicha y almacena los valores
 * introducidos por el profesional. El campo datos es un JSON libre;
 * el campo notas permite texto sin estructura durante la entrevista.
 *
 * historia_id se usa cuando la ficha se crea desde RegistrarValoracionPage
 * antes de existir una Valoracion formal (valoracion_id nullable).
 * TODO: vincular siempre a Valoracion cuando ese flujo esté completo.
 *
 * @property int        $id
 * @property int|null   $historia_id
 * @property int|null   $valoracion_id
 * @property int        $tipo_ficha_id
 * @property array|null $schema_snapshot  Copia del schema del TipoFicha al crear la ficha
 * @property int|null   $profesional_id
 * @property array|null $datos
 * @property string|null $notas
 * @property bool       $completada
 */
class Ficha extends Model
{
    use Auditable;
    use HasFactory;
    use Versionable;

    protected static function newFactory(): FichaFactory
    {
        return FichaFactory::new();
    }

    protected $table = 'fichas';

    protected $fillable = [
        'historia_id',
        'valoracion_id',
        'tipo_ficha_id',
        'schema_snapshot',
        'profesional_id',
        'datos',
        'notas',
        'completada',
    ];

    protected $casts = [
        'datos'           => 'array',
        'schema_snapshot' => 'array',
        'completada'      => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Historia social a la que pertenece esta ficha (flujo directo, sin valoracion formal).
     *
     * @return BelongsTo<HistoriaSocial, Ficha>
     */
    public function historia(): BelongsTo
    {
        return $this->belongsTo(HistoriaSocial::class, 'historia_id');
    }

    /**
     * @return BelongsTo<Valoracion, Ficha>
     */
    public function valoracion(): BelongsTo
    {
        return $this->belongsTo(Valoracion::class, 'valoracion_id');
    }

    /**
     * @return BelongsTo<TipoFicha, Ficha>
     */
    public function tipoFicha(): BelongsTo
    {
        return $this->belongsTo(TipoFicha::class, 'tipo_ficha_id');
    }

    /**
     * Resuelve el ciudadano_id para el sistema de auditoría a través de la historia social.
     *
     * @return int|null
     */
    public function getCiudadanoId(): ?int
    {
        return $this->historia?->ciudadano_id;
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Fichas de un tipo concreto para una historia, ordenadas de más reciente a más antigua.
     *
     * @param Builder<self> $query
     */
    public function scopeHistorialPara(Builder $query, int $historiaId, int $tipoFichaId): Builder
    {
        return $query
            ->where('historia_id', $historiaId)
            ->where('tipo_ficha_id', $tipoFichaId)
            ->orderByDesc('created_at');
    }

    // -------------------------------------------------------------------------
    // Métodos estáticos de negocio
    // -------------------------------------------------------------------------

    /**
     * Genera el array de datos pre-rellenado para una nueva valoración.
     *
     * Reglas:
     * - Campo en schema actual Y en datos anteriores → se copia.
     * - Campo en schema actual pero no en datos anteriores → null (campo nuevo).
     * - Campo en datos anteriores pero no en schema actual → se descarta (retirado).
     *
     * @param self     $fichaAnterior Ficha de referencia.
     * @param TipoFicha $tipoFicha    TipoFicha con el schema actual.
     * @return array<string, mixed>
     */
    public static function prerellenarDesde(self $fichaAnterior, TipoFicha $tipoFicha): array
    {
        $camposActuales = collect($tipoFicha->schema['campos'] ?? [])->pluck('id')->all();
        $datosAnteriores = $fichaAnterior->datos ?? [];

        $resultado = [];
        foreach ($camposActuales as $campoId) {
            $resultado[$campoId] = $datosAnteriores[$campoId] ?? null;
        }

        return $resultado;
    }
}
