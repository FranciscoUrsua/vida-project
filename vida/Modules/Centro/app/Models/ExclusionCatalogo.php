<?php

namespace Modules\Centro\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Centro\Enums\ElementoCatalogo;

/**
 * Elemento del catálogo del tipo que un centro no ofrece (RN-08).
 *
 * Se guarda aunque el tipo desactive la restricción: al reactivarla vuelve a
 * aplicarse. Se crea por ExclusionCatalogoService, que valida que el tipo lo
 * permite y que el elemento está en su catálogo.
 *
 * @property int $id
 * @property int $centro_id
 * @property ElementoCatalogo $elemento_tipo
 * @property int $elemento_id
 * @property int|null $creado_por_id
 */
class ExclusionCatalogo extends Model
{
    protected $table = 'centro_exclusiones_catalogo';

    protected $fillable = ['centro_id', 'elemento_tipo', 'elemento_id', 'creado_por_id'];

    protected $casts = ['elemento_tipo' => ElementoCatalogo::class];

    /**
     * Centro que excluye el elemento.
     *
     * @return BelongsTo<Centro, $this>
     */
    public function centro(): BelongsTo
    {
        return $this->belongsTo(Centro::class);
    }
}
