<?php

namespace Modules\Prestaciones\Services;

use App\Models\User;
use App\Models\Version;
use Illuminate\Support\Facades\DB;
use Modules\Prestaciones\Models\Prestacion;

/**
 * Cambia los tipos de centro que ofrecen una prestación (catálogo del tipo, §6.1
 * de docs/modulo-tipo-centro.md) dejando versión del estado anterior.
 *
 * La versión guarda los atributos de la prestación y sus tipos de centro (id y
 * código), para poder responder qué tipos la ofrecían a una fecha. Mover una
 * prestación de tipo no toca prescripciones ni asignaciones existentes (RN-07).
 */
class TiposCentroPrestacionService
{
    /**
     * Deja la prestación exactamente con esos tipos de centro.
     *
     * @param Prestacion $prestacion Prestación.
     * @param list<int> $tipoCentroIds Tipos de centro que la ofrecen.
     * @param User|null $actor Usuario que hace el cambio.
     * @param bool $versionar False al dar de alta la prestación (no hay estado anterior).
     */
    public function sincronizar(Prestacion $prestacion, array $tipoCentroIds, ?User $actor, bool $versionar = true): void
    {
        $nuevos = collect($tipoCentroIds)->map(fn ($id) => (int) $id)->unique()->sort()->values();
        $actuales = $prestacion->tiposCentro()->pluck('tipo_centro_id')->map(fn ($id) => (int) $id)->sort()->values();

        if ($nuevos->all() === $actuales->all()) {
            return;
        }

        DB::transaction(function () use ($prestacion, $nuevos, $actor, $versionar): void {
            if ($versionar) {
                Version::create([
                    'versionable_type' => $prestacion->getMorphClass(),
                    'versionable_id' => $prestacion->getKey(),
                    'datos' => array_merge($prestacion->getAttributes(), [
                        'tipos_centro' => $prestacion->tiposCentro()->with('tipoCentro:id,codigo')->get()
                            ->map(fn ($fila) => ['id' => $fila->tipo_centro_id, 'codigo' => $fila->tipoCentro?->codigo])
                            ->values()->all(),
                    ]),
                    'usuario_id' => $actor?->id,
                ]);
            }

            $prestacion->tiposCentro()->delete();
            foreach ($nuevos as $tipoId) {
                $prestacion->tiposCentro()->create(['tipo_centro_id' => $tipoId]);
            }
        });
    }
}
