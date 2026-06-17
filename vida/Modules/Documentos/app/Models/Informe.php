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
use Illuminate\Support\Carbon;
use Modules\Documentos\Enums\EstadoInforme;
use Modules\Documentos\Enums\MetodoFirma;

/**
 * Instancia concreta de un informe profesional.
 *
 * Ciclo de vida: borrador → firmado → (anulado).
 * Un informe firmado es inmutable: no puede editarse ni eliminarse.
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
 * @property array $contenido
 * @property int|null $documento_id
 * @property Carbon|null $firmado_en
 * @property MetodoFirma|null $metodo_firma
 * @property string|null $numero_colegiado_firmante
 * @property string|null $motivo_anulacion
 * @property Carbon|null $anulado_en
 */
class Informe extends Model
{
    use Auditable;
    use Versionable;

    protected $table = 'informes';

    protected $guarded = [];

    protected $casts = [
        'estado' => EstadoInforme::class,
        'contenido' => 'array',
        'firmado_en' => 'datetime',
        'metodo_firma' => MetodoFirma::class,
        'anulado_en' => 'datetime',
    ];

    public function plantilla(): BelongsTo
    {
        return $this->belongsTo(PlantillaInforme::class, 'plantilla_id');
    }

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

    public function ciudadano(): BelongsTo
    {
        return $this->belongsTo(Ciudadano::class, 'ciudadano_id');
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autor_id');
    }

    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class, 'documento_id');
    }

    public function scopeBorradores(Builder $query): Builder
    {
        return $query->where('estado', EstadoInforme::Borrador->value);
    }

    public function scopeFirmados(Builder $query): Builder
    {
        return $query->where('estado', EstadoInforme::Firmado->value);
    }

    public function scopeDeAutor(Builder $query, int $userId): Builder
    {
        return $query->where('autor_id', $userId);
    }

    public function estaFirmado(): bool
    {
        return $this->estado === EstadoInforme::Firmado;
    }

    public function estaAnulado(): bool
    {
        return $this->estado === EstadoInforme::Anulado;
    }
}
