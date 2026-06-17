<?php

namespace Modules\Documentos\Models;

use App\Models\UnidadOrganizativa;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Estilo formal de los informes generados por una UO.
 *
 * Los campos se heredan campo a campo por proximidad ascendente en la jerarquía
 * de UOs: para cada campo, se usa el valor definido en la UO más cercana al autor
 * que lo tenga establecido. La resolución la realiza ResolverEstiloInforme.
 *
 * Una UO tiene como máximo un EstiloInforme (unique sobre unidad_organizativa_id).
 *
 * @property int $id
 * @property int $unidad_organizativa_id
 * @property string|null $logo_cabecera
 * @property string|null $nombre_unidad_cabecera
 * @property string|null $direccion_cabecera
 * @property string|null $telefono_cabecera
 * @property string|null $html_pie
 * @property int $creado_por
 */
class EstiloInforme extends Model
{
    protected $table = 'estilos_informe';

    protected $guarded = [];

    /**
     * Unidad organizativa dueña del estilo.
     *
     * @return BelongsTo<UnidadOrganizativa, self>
     */
    public function unidadOrganizativa(): BelongsTo
    {
        return $this->belongsTo(UnidadOrganizativa::class, 'unidad_organizativa_id');
    }

    /**
     * Usuario que creó el estilo.
     *
     * @return BelongsTo<User, self>
     */
    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }
}
