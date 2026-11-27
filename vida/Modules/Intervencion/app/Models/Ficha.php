<?php

namespace Modules\Intervencion\Models;

use App\Models\CatalogoSistema;
use App\Models\HistoriaSocial;
use App\Support\Fichas\ValorCampo;
use App\Traits\Auditable;
use App\Traits\Versionable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
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
 * @property int|null $derivacion_id Encargo profesional desde el que se registra (EA-5).
 * @property Carbon $created_at
 * @property Carbon $updated_at
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
        'derivacion_id',
    ];

    protected $casts = [
        'datos' => 'array',
        'schema_snapshot' => 'array',
        'completada' => 'boolean',
    ];

    /**
     * Encargo profesional desde el que se registró la ficha (EA-5).
     *
     * @return BelongsTo<Derivacion, $this>
     */
    public function derivacion(): BelongsTo
    {
        return $this->belongsTo(Derivacion::class, 'derivacion_id');
    }

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
     * Indica si la ficha es de la historia social dada, para no mostrar por id una ficha ajena.
     *
     * La ficha guarda su historia en historia_id (flujo de RegistrarValoracionPage).
     * Solo si historia_id es nulo se toma la historia de su valoración formal; si
     * tampoco hay valoración, la ficha no pertenece a ninguna historia.
     *
     * @param int $historiaId Identificador de la historia social.
     *
     * @return bool
     */
    public function perteneceAHistoria(int $historiaId): bool
    {
        if ($this->historia_id !== null) {
            return (int) $this->historia_id === $historiaId;
        }

        if ($this->valoracion_id === null) {
            return false;
        }

        // Sin scopes: la historia de la página ya se ha autorizado; aquí solo se compara el id
        $historiaDeValoracion = Valoracion::withoutGlobalScopes()
            ->whereKey($this->valoracion_id)
            ->value('historia_id');

        return $historiaDeValoracion !== null && (int) $historiaDeValoracion === $historiaId;
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
     * Genera el array de datos pre-rellenado para una nueva ficha.
     *
     * Reglas (ver `prerellenarConAvisos()`):
     * - Campo en schema actual Y en datos anteriores → se copia.
     * - Campo en schema actual pero no en datos anteriores → null (campo nuevo).
     * - Campo en datos anteriores pero no en schema actual → se descarta (retirado).
     * - Campo de catálogo cuya clave ya no está vigente → null (SD-8).
     *
     * @param self $fichaAnterior Ficha de referencia.
     * @param TipoFicha $tipoFicha TipoFicha con el schema actual.
     *
     * @return array<string, mixed>
     */
    public static function prerellenarDesde(self $fichaAnterior, TipoFicha $tipoFicha): array
    {
        return self::prerellenarConAvisos($fichaAnterior, $tipoFicha)['datos'];
    }

    /**
     * Pre-relleno con la lista de campos de catálogo que se han dejado vacíos porque su clave
     * ya no está activa o vigente, para avisar al profesional (SD-8).
     *
     * @param self $fichaAnterior Ficha de referencia.
     * @param TipoFicha $tipoFicha TipoFicha con el schema actual.
     *
     * @return array{datos: array<string, mixed>, avisos: list<array{campo_id: string, etiqueta: string, valor_anterior: string}>}
     */
    public static function prerellenarConAvisos(self $fichaAnterior, TipoFicha $tipoFicha): array
    {
        $camposSchema = $tipoFicha->schema['campos'] ?? [];
        $datosAnteriores = is_array($fichaAnterior->datos) ? $fichaAnterior->datos : [];
        $datos = [];
        $avisos = [];

        foreach (is_array($camposSchema) ? $camposSchema : [] as $campo) {
            $campoId = is_array($campo) ? ($campo['id'] ?? null) : null;
            if (! is_string($campoId) || $campoId === '') {
                continue;
            }

            $valor = $datosAnteriores[$campoId] ?? null;

            // Una clave que ya no se puede elegir no se arrastra: se deja vacía y se avisa
            if (($campo['tipo'] ?? null) === 'catalogo' && ValorCampo::tieneValor($valor)
                && ! CatalogoSistema::where('grupo', (string) ($campo['catalogo'] ?? ''))->where('clave', (string) $valor)->vigentes()->exists()) {
                $avisos[] = [
                    'campo_id' => $campoId,
                    'etiqueta' => (string) ($campo['etiqueta'] ?? $campoId),
                    'valor_anterior' => ValorCampo::mostrar($campo, $valor),
                ];
                $valor = null;
            }

            $datos[$campoId] = $valor;
        }

        return ['datos' => $datos, 'avisos' => $avisos];
    }
}
