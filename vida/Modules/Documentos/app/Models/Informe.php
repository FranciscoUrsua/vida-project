<?php

namespace Modules\Documentos\Models;

use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\User;
use App\Traits\Auditable;
use App\Traits\Versionable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Documentos\Enums\EstadoInforme;
use Modules\Documentos\Enums\MetodoFirma;
use Modules\Intervencion\Models\Derivacion;

/**
 * Instancia concreta de un informe profesional.
 *
 * Ciclo de vida: borrador → firmado → (anulado).
 * Un informe firmado es inmutable: no puede editarse ni eliminarse. Un borrador sí
 * puede eliminarlo su autor (baja lógica; no es un documento custodiado).
 * Solo puede ser anulado por el autor, con motivo obligatorio.
 * El fichero PDF firmado permanece en el sistema tras la anulación.
 *
 * Las transiciones de estado se validan en ServicioFirmaInforme,
 * no en el modelo (capa de servicio, no de persistencia).
 *
 * @property int $id
 * @property int $plantilla_id
 * @property int|null $historia_social_id
 * @property int $ciudadano_id
 * @property int $autor_id
 * @property EstadoInforme $estado
 * @property array<string, mixed> $contenido
 * @property int|null $documento_id
 * @property int|null $derivacion_id Encargo profesional desde el que se redacta (EA-5).
 * @property Carbon|null $firmado_en
 * @property MetodoFirma|null $metodo_firma
 * @property string|null $firmante_certificado Titular del certificado de la firma (F5-2).
 * @property string|null $numero_colegiado_firmante
 * @property string|null $motivo_anulacion
 * @property Carbon|null $anulado_en
 */
class Informe extends Model
{
    use Auditable;
    use SoftDeletes;
    use Versionable;

    /** Prefijo del identificador impreso en el pie del PDF. */
    public const PREFIJO_IDENTIFICADOR = 'INF-';

    protected $table = 'informes';

    protected $guarded = [];

    protected $casts = [
        'estado' => EstadoInforme::class,
        'contenido' => 'array',
        'firmado_en' => 'datetime',
        'metodo_firma' => MetodoFirma::class,
        'anulado_en' => 'datetime',
    ];

    /**
     * Plantilla utilizada para construir el informe.
     *
     * @return BelongsTo<PlantillaInforme, $this>
     */
    public function plantilla(): BelongsTo
    {
        return $this->belongsTo(PlantillaInforme::class, 'plantilla_id');
    }

    /**
     * Historia social vinculada al informe, si existe.
     *
     * @return BelongsTo<HistoriaSocial, $this>
     */
    public function historiaSocial(): BelongsTo
    {
        return $this->belongsTo(HistoriaSocial::class, 'historia_social_id');
    }

    /**
     * ID del ciudadano asociado al informe.
     *
     * @return int|null
     */
    public function getCiudadanoId(): ?int
    {
        return $this->ciudadano_id;
    }

    /**
     * Ciudadano destinatario del informe.
     *
     * @return BelongsTo<Ciudadano, $this>
     */
    public function ciudadano(): BelongsTo
    {
        return $this->belongsTo(Ciudadano::class, 'ciudadano_id');
    }

    /**
     * Autor que creó el informe.
     *
     * @return BelongsTo<User, $this>
     */
    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autor_id');
    }

    /**
     * Encargo profesional desde el que se redacta el informe (EA-5).
     *
     * @return BelongsTo<Derivacion, $this>
     */
    public function derivacion(): BelongsTo
    {
        return $this->belongsTo(Derivacion::class, 'derivacion_id');
    }

    /**
     * Documento PDF asociado al informe, si existe.
     *
     * @return BelongsTo<Documento, $this>
     */
    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class, 'documento_id');
    }

    /**
     * Filtra los informes en estado borrador.
     *
     * @param Builder<self> $query Consulta base.
     *
     * @return Builder<self>
     */
    public function scopeBorradores(Builder $query): Builder
    {
        return $query->where('estado', EstadoInforme::Borrador->value);
    }

    /**
     * Filtra los informes en estado firmado.
     *
     * @param Builder<self> $query Consulta base.
     *
     * @return Builder<self>
     */
    public function scopeFirmados(Builder $query): Builder
    {
        return $query->where('estado', EstadoInforme::Firmado->value);
    }

    /**
     * Filtra los informes por identificador de autor.
     *
     * @param Builder<self> $query Consulta base.
     * @param int $userId ID del usuario autor.
     *
     * @return Builder<self>
     */
    public function scopeDeAutor(Builder $query, int $userId): Builder
    {
        return $query->where('autor_id', $userId);
    }

    /**
     * Identificador del informe que se imprime en el pie de cada página del PDF.
     *
     * Permite comprobar que un PDF firmado que se sube corresponde a este informe (F5-2).
     *
     * @return string
     */
    public function identificador(): string
    {
        return self::PREFIJO_IDENTIFICADOR.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Indica si el informe está en borrador.
     *
     * @return bool
     */
    public function esBorrador(): bool
    {
        return $this->estado === EstadoInforme::Borrador;
    }

    /**
     * Indica si el informe está firmado.
     *
     * @return bool
     */
    public function estaFirmado(): bool
    {
        return $this->estado === EstadoInforme::Firmado;
    }

    /**
     * Indica si el informe está anulado.
     *
     * @return bool
     */
    public function estaAnulado(): bool
    {
        return $this->estado === EstadoInforme::Anulado;
    }
}
