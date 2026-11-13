<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Centro\Models\Centro;

/**
 * Único punto de cálculo del ámbito de un usuario (RN-13 de docs/modulo-tipo-centro.md).
 *
 * El ámbito es la unión de las dos jerarquías: las UO de sus adscripciones
 * vigentes con sus descendientes orgánicas, más las UO de los centros activos
 * cuyo tipo tiene la UO funcional dentro de ese ámbito orgánico. Dentro de él el
 * usuario actúa según su rol. Se calcula con una sola consulta y se cachea por
 * usuario durante la petición (servicio con ámbito de petición).
 */
class AmbitoUsuario
{
    /** @var array<int, list<int>> UO del ámbito por id de usuario */
    private array $cache = [];

    /**
     * UO del ámbito del usuario (orgánica + funcional).
     *
     * @param User $usuario
     *
     * @return list<int>
     */
    public function uoIds(User $usuario): array
    {
        return $this->cache[$usuario->id] ??= $this->calcular($usuario);
    }

    /**
     * Si el centro está dentro del ámbito del usuario.
     *
     * @param User $usuario
     * @param Centro $centro
     *
     * @return bool
     */
    public function incluyeCentro(User $usuario, Centro $centro): bool
    {
        return $centro->unidad_organizativa_id !== null && in_array($centro->unidad_organizativa_id, $this->uoIds($usuario), true);
    }

    /**
     * Centros activos dentro del ámbito del usuario.
     *
     * @param User $usuario
     *
     * @return Collection<int, Centro>
     */
    public function centrosEnAmbito(User $usuario): Collection
    {
        return Centro::whereIn('unidad_organizativa_id', $this->uoIds($usuario))->where('activo', true)->orderBy('nombre')->get();
    }

    /**
     * Olvida lo calculado (tras cambiar adscripciones o la UO funcional de un tipo en la misma petición).
     *
     * @return void
     */
    public function olvidar(): void
    {
        $this->cache = [];
    }

    /**
     * Calcula el ámbito con una CTE recursiva (orgánica) unida a los centros por la funcional.
     *
     * @param User $usuario
     *
     * @return list<int>
     */
    private function calcular(User $usuario): array
    {
        $filas = DB::select(<<<'SQL'
            WITH RECURSIVE organicas AS (
                SELECT uo.id
                FROM unidades_organizativas uo
                JOIN usuario_uo a ON a.unidad_organizativa_id = uo.id
                WHERE a.usuario_id = :usuario
                  AND (a.fecha_fin IS NULL OR a.fecha_fin >= CURRENT_DATE)
                  AND uo.activa = true
                  AND uo.deleted_at IS NULL
                UNION
                SELECT hija.id
                FROM unidades_organizativas hija
                JOIN organicas o ON hija.parent_id = o.id
                WHERE hija.deleted_at IS NULL
            )
            SELECT id FROM organicas
            UNION
            SELECT c.unidad_organizativa_id
            FROM centros c
            JOIN tipos_centro t ON t.id = c.tipo_centro_id
            WHERE c.activo = true
              AND c.deleted_at IS NULL
              AND c.unidad_organizativa_id IS NOT NULL
              AND t.unidad_organizativa_id IN (SELECT id FROM organicas)
            SQL, ['usuario' => $usuario->id]);

        return array_values(array_map(fn ($fila) => (int) $fila->id, $filas));
    }
}
