<?php

namespace Modules\Intervencion\Models;

use App\Models\HistoriaSocial;
use App\Traits\Auditable;
use App\Traits\Versionable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Intervencion\Database\Factories\FichaFactory;

/**
 * @use HasFactory<FichaFactory>
 *
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
 * @property int $id
 * @property int|null $historia_id
 * @property int|null $valoracion_id
 * @property int $tipo_ficha_id
 * @property array<string, mixed>|null $schema_snapshot Copia del schema del TipoFicha al crear la ficha
 * @property int|null $profesional_id
 * @property array<string, mixed>|null $datos
 * @property string|null $notas
 * @property bool $completada
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
        'datos' => 'array',
        'schema_snapshot' => 'array',
        'completada' => 'boolean',
    ];

    /**
     * Historia social a la que pertenece esta ficha (flujo directo, sin valoracion formal).
     *
     * @return BelongsTo<HistoriaSocial, $this>
     */
    public function historia(): BelongsTo
    {
        return $this->belongsTo(HistoriaSocial::class, 'historia_id');
    }

    /**
     * @return BelongsTo<Valoracion, $this>
     */
    public function valoracion(): BelongsTo
    {
        return $this->belongsTo(Valoracion::class, 'valoracion_id');
    }

    /**
     * @return BelongsTo<TipoFicha, $this>
     */
    public function tipoFicha(): BelongsTo
    {
        return $this->belongsTo(TipoFicha::class, 'tipo_ficha_id');
    }

    /**
     * Resuelve el ciudadano_id para el sistema de auditoría a través de la historia social.
     */
    public function getCiudadanoId(): ?int
    {
        return $this->historia?->ciudadano_id;
    }

    /**
     * Fichas de un tipo concreto para una historia, ordenadas de más reciente a más antigua.
     *
     * @param Builder<self> $query Consulta base.
     * @param int $historiaId Identificador de la historia social.
     * @param int $tipoFichaId Identificador del tipo de ficha.
     *
     * @return Builder<self>
     */
    public function scopeHistorialPara(Builder $query, int $historiaId, int $tipoFichaId): Builder
    {
        return $query
            ->where('historia_id', $historiaId)
            ->where('tipo_ficha_id', $tipoFichaId)
            ->orderByDesc('created_at');
    }

    /**
     * Genera el array de datos pre-rellenado para una nueva valoración.
     *
     * Reglas:
     * - Campo en schema actual Y en datos anteriores → se copia.
     * - Campo en schema actual pero no en datos anteriores → null (campo nuevo).
     * - Campo en datos anteriores pero no en schema actual → se descarta (retirado).
     *
     * @param self $fichaAnterior Ficha de referencia.
     * @param TipoFicha $tipoFicha TipoFicha con el schema actual.
     *
     * @return array<string, mixed>
     */
    public static function prerellenarDesde(self $fichaAnterior, TipoFicha $tipoFicha): array
    {
        $camposSchema = $tipoFicha->schema['campos'] ?? [];
        $camposActuales = [];

        if (is_array($camposSchema)) {
            foreach ($camposSchema as $campo) {
                if (! is_array($campo)) {
                    continue;
                }

                $campoId = $campo['id'] ?? null;
                if (is_string($campoId) && $campoId !== '') {
                    $camposActuales[] = $campoId;
                }
            }
        }

        $datosAnteriores = is_array($fichaAnterior->datos) ? $fichaAnterior->datos : [];
        $resultado = [];

        foreach ($camposActuales as $campoId) {
            $resultado[$campoId] = $datosAnteriores[$campoId] ?? null;
        }

        return $resultado;
    }
}
