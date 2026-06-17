<?php

namespace Modules\Intervencion\Models;

use App\Models\HistoriaSocial;
use App\Models\User;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Intervencion\Database\Factories\ValoracionFactory;
use Modules\Intervencion\Enums\EstadoValoracion;

/**
 * Valoración diagnóstica estructurada del ciudadano.
 *
 * Tiene ciclo de vida propio: puede completarse en varias sesiones,
 * revisarse posteriormente y existir en múltiples versiones.
 * No toda entrevista genera valoración.
 *
 * @property int $id
 * @property int $historia_id
 * @property int|null $entrevista_id
 * @property int $profesional_id
 * @property int $tipo_valoracion_id
 * @property Carbon $fecha
 * @property EstadoValoracion $estado
 * @property string|null $resumen
 */
class Valoracion extends Model
{
    use Auditable;
    use HasFactory;

    protected static function newFactory(): ValoracionFactory
    {
        return ValoracionFactory::new();
    }

    protected $table = 'valoraciones';

    protected $fillable = [
        'historia_id',
        'entrevista_id',
        'profesional_id',
        'tipo_valoracion_id',
        'fecha',
        'estado',
        'resumen',
    ];

    protected $casts = [
        'fecha' => 'date',
        'estado' => EstadoValoracion::class,
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Devuelve el ciudadano asociado a la historia social de la valoración.
     *
     * @return int|null
     */
    public function getCiudadanoId(): ?int
    {
        return $this->historia()->withoutGlobalScopes()->value('ciudadano_id');
    }

    /**
     * @return BelongsTo<HistoriaSocial, Valoracion>
     */
    public function historia(): BelongsTo
    {
        return $this->belongsTo(HistoriaSocial::class, 'historia_id');
    }

    /**
     * @return BelongsTo<Entrevista, Valoracion>
     */
    public function entrevista(): BelongsTo
    {
        return $this->belongsTo(Entrevista::class, 'entrevista_id');
    }

    /**
     * @return BelongsTo<User, Valoracion>
     */
    public function profesional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'profesional_id');
    }

    /**
     * @return BelongsTo<TipoValoracion, Valoracion>
     */
    public function tipoValoracion(): BelongsTo
    {
        return $this->belongsTo(TipoValoracion::class, 'tipo_valoracion_id');
    }

    /**
     * @return HasMany<Ficha>
     */
    public function fichas(): HasMany
    {
        return $this->hasMany(Ficha::class, 'valoracion_id');
    }
}
