<?php

namespace Modules\Documentos\Models;

use App\Models\UnidadOrganizativa;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Documentos\Enums\TipoInforme;

/**
 * Plantilla configurable para la generación de informes profesionales.
 *
 * Define la estructura del informe (secciones automáticas y de texto libre).
 * El aspecto formal lo aporta EstiloInforme en el momento de la generación.
 *
 * Las plantillas tienen alcance jerárquico: una plantilla creada en una UO
 * está disponible para todos los profesionales de esa UO y sus descendientes.
 *
 * @property int $id
 * @property int $unidad_organizativa_id
 * @property string $nombre
 * @property string|null $descripcion
 * @property TipoInforme $tipo_informe
 * @property array $secciones
 * @property bool $activa
 * @property int $creada_por
 */
class PlantillaInforme extends Model
{
    protected $table = 'plantillas_informe';

    protected $guarded = [];

    protected $casts = [
        'secciones'    => 'array',
        'activa'       => 'boolean',
        'tipo_informe' => TipoInforme::class,
    ];

    public function unidadOrganizativa(): BelongsTo
    {
        return $this->belongsTo(UnidadOrganizativa::class, 'unidad_organizativa_id');
    }

    public function creadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creada_por');
    }

    public function informes(): HasMany
    {
        return $this->hasMany(Informe::class, 'plantilla_id');
    }

    /**
     * Plantillas activas visibles para una UO dada y todos sus ancestros.
     *
     * Una plantilla creada en una UO es visible para los profesionales de esa UO
     * y todos sus descendientes. Dado un $uoId (la UO del profesional), devuelve
     * las plantillas activas cuya UO es esa misma o cualquiera de sus ancestros.
     */
    public function scopeVisiblesParaUo(Builder $query, int $uoId): Builder
    {
        $uo = UnidadOrganizativa::find($uoId);

        if (! $uo) {
            return $query->whereRaw('1 = 0');
        }

        $uoIds = $uo->ancestorsAndSelf()->pluck('id')->toArray();

        return $query->where('activa', true)
            ->whereIn('unidad_organizativa_id', $uoIds);
    }
}
