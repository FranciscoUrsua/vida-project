<?php

namespace Modules\Documentos\Services;

use App\Models\UnidadOrganizativa;
use Illuminate\Support\Facades\Cache;
use Modules\Documentos\Models\EstiloInforme;

/**
 * Resuelve el estilo de informe efectivo para una UO dada.
 *
 * Recorre la cadena de ancestros de la UO (incluida ella misma) de más cercana
 * a más lejana. Para cada campo visual (logo, nombre, dirección, teléfono, pie),
 * usa el primer valor no nulo encontrado en la cadena. Si ningún ancestro define
 * un campo, aplica el valor por defecto de config('documentos.estilo_defecto').
 *
 * El resultado se cachea por UO con TTL configurable. La caché se invalida en
 * EstiloInformeObserver al guardar o eliminar un EstiloInforme.
 */
class ResolverEstiloInforme
{
    /** Campos visuales que se resuelven por herencia. */
    private const CAMPOS = [
        'logo_cabecera',
        'nombre_unidad_cabecera',
        'direccion_cabecera',
        'telefono_cabecera',
        'html_pie',
    ];

    public function resolver(int $uoId): array
    {
        return Cache::remember(
            "estilo_informe_uo_{$uoId}",
            config('documentos.cache_ttl_segundos', 3600),
            fn () => $this->resolverSinCache($uoId)
        );
    }

    public function resolverSinCache(int $uoId): array
    {
        $uo = UnidadOrganizativa::find($uoId);

        if (! $uo) {
            return config('documentos.estilo_defecto', []);
        }

        // De más cercana (la propia UO) a más lejana (raíz)
        $ancestros = $uo->ancestorsAndSelf()->get();

        $estilosPorUo = EstiloInforme::whereIn('unidad_organizativa_id', $ancestros->pluck('id'))
            ->get()
            ->keyBy('unidad_organizativa_id');

        $resultado = [];
        $defecto = config('documentos.estilo_defecto', []);

        foreach (self::CAMPOS as $campo) {
            $valor = null;

            foreach ($ancestros as $nodo) {
                $estilo = $estilosPorUo->get($nodo->id);

                if ($estilo && $estilo->$campo !== null) {
                    $valor = $estilo->$campo;
                    break;
                }
            }

            $resultado[$campo] = $valor ?? ($defecto[$campo] ?? null);
        }

        return $resultado;
    }

    /**
     * Invalida la caché de una UO y todas sus descendientes.
     *
     * @param int $uoId ID de la unidad organizativa.
     *
     * @return void
     */
    public function invalidarCacheUo(int $uoId): void
    {
        $uo = UnidadOrganizativa::find($uoId);

        if (! $uo) {
            return;
        }

        $ids = $uo->descendantsAndSelf()->pluck('id');

        foreach ($ids as $id) {
            Cache::forget("estilo_informe_uo_{$id}");
        }
    }
}
