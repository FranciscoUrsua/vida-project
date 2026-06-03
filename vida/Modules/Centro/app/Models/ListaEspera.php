<?php

namespace Modules\Centro\Models;

use App\Traits\Versionable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Usuarios\Models\Profesional;

/**
 * Registro de lista de espera vinculado a una prescripción.
 *
 * El ámbito puede ser local (coleccion_plazas_id) o de red (red_id).
 * Ambos campos son mutuamente excluyentes — se valida en booted().
 *
 * estado: activa | asignada | cancelada
 *
 * @property int $id
 * @property int $prescripcion_id
 * @property int|null $coleccion_plazas_id
 * @property int|null $red_id
 * @property int $profesional_alerta_id
 * @property string $estado
 * @property int $posicion
 * @property Carbon $fecha_entrada
 * @property Carbon|null $fecha_alerta
 */
class ListaEspera extends Model
{
    use Versionable;

    protected $table = 'lista_espera';

    protected $fillable = [
        'prescripcion_id',
        'coleccion_plazas_id',
        'red_id',
        'profesional_alerta_id',
        'estado',
        'posicion',
        'fecha_entrada',
        'fecha_alerta',
        'notas',
    ];

    protected $casts = [
        'fecha_entrada' => 'datetime',
        'fecha_alerta' => 'datetime',
        'posicion' => 'integer',
    ];

    // -------------------------------------------------------------------------
    // Validación en modelo
    // -------------------------------------------------------------------------

    /**
     * Registra el hook de validación que impide mezclar ámbito local y de red.
     */
    protected static function booted(): void
    {
        static::saving(function (self $registro) {
            $tieneColeccion = ! empty($registro->coleccion_plazas_id);
            $tieneRed = ! empty($registro->red_id);

            if ($tieneColeccion && $tieneRed) {
                throw new \InvalidArgumentException(
                    'Una lista de espera no puede tener coleccion_plazas_id y red_id al mismo tiempo.'
                );
            }

            if (! $tieneColeccion && ! $tieneRed) {
                throw new \InvalidArgumentException(
                    'Una lista de espera debe tener ámbito: coleccion_plazas_id o red_id.'
                );
            }
        });
    }

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Prescripción que originó la entrada en lista de espera.
     *
     * @return BelongsTo<Prescripcion, self>
     */
    public function prescripcion(): BelongsTo
    {
        return $this->belongsTo(Prescripcion::class, 'prescripcion_id');
    }

    /**
     * Colección de plazas de ámbito local (mutuamente excluyente con red).
     *
     * @return BelongsTo<ColeccionPlazas, self>
     */
    public function coleccionPlazas(): BelongsTo
    {
        return $this->belongsTo(ColeccionPlazas::class, 'coleccion_plazas_id');
    }

    /**
     * Red de centros de ámbito compartido (mutuamente excluyente con coleccionPlazas).
     *
     * @return BelongsTo<Red, self>
     */
    public function red(): BelongsTo
    {
        return $this->belongsTo(Red::class, 'red_id');
    }

    /**
     * Profesional al que se notificará cuando haya plaza disponible.
     *
     * @return BelongsTo<Profesional, self>
     */
    public function profesionalAlerta(): BelongsTo
    {
        return $this->belongsTo(Profesional::class, 'profesional_alerta_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Filtra registros de lista de espera con estado activa.
     *
     * @param Builder<static> $query
     *
     * @return Builder<static>
     */
    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('estado', 'activa');
    }
}
