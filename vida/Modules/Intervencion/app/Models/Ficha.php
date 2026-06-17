<?php

namespace Modules\Intervencion\Models;

use App\Traits\Auditable;
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
 * @property int $id
 * @property int|null $historia_id
 * @property int|null $valoracion_id
 * @property int $tipo_ficha_id
 * @property array|null $datos
 * @property string|null $notas
 * @property bool $completada
 */
class Ficha extends Model
{
    use Auditable;
    use HasFactory;

    protected static function newFactory(): FichaFactory
    {
        return FichaFactory::new();
    }

    protected $table = 'fichas';

    protected $fillable = [
        'historia_id',
        'valoracion_id',
        'tipo_ficha_id',
        'datos',
        'notas',
        'completada',
    ];

    protected $casts = [
        'datos' => 'array',
        'completada' => 'boolean',
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
}
