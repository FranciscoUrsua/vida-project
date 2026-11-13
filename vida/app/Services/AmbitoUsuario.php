<?php

namespace App\Services;

use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\Scopes\AmbitoUoScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
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
 *
 * Personas protegidas (RN-17): el nivel 1 sobre una persona de colectivo
 * especialmente protegido se reduce a quien está adscrito directamente a la UO
 * del centro de su historia, más (salvedad del 12 nov 2026) quien tiene rol
 * `intervencion` y el centro en su ámbito. Para el resto está fuera de ámbito:
 * la ve como «Persona con protección especial» y necesita un acceso aprobado
 * (RN-18). Restricción de dominio crítica (CLAUDE.md §3): las policies, el
 * AmbitoUoScope y el scope de apuntes la toman de aquí.
 */
class AmbitoUsuario
{
    /** @var array<int, list<int>> UO del ámbito por id de usuario */
    private array $cache = [];

    /** @var array<int, list<int>> UO con nivel 1 sobre personas protegidas, por id de usuario */
    private array $cacheProtegidos = [];

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
        $this->cacheProtegidos = [];
    }

    /**
     * UO en las que el usuario gestiona personas protegidas (RN-17): sus
     * adscripciones directas vigentes y, con rol `intervencion`, todo su ámbito.
     *
     * @param User $usuario
     *
     * @return list<int>
     */
    public function uoIdsProtegidos(User $usuario): array
    {
        return $this->cacheProtegidos[$usuario->id] ??= $usuario->hasRole('intervencion')
            ? $this->uoIds($usuario)
            : array_values(array_map('intval', $usuario->adscripcionesVigentes()->pluck('unidad_organizativa_id')->all()));
    }

    /**
     * Si el usuario tiene nivel 1 (gestión) sobre una persona de la historia (RN-13 y RN-17).
     *
     * @param User $usuario
     * @param HistoriaSocial $historia
     *
     * @return bool
     */
    public function gestionaHistoria(User $usuario, HistoriaSocial $historia): bool
    {
        $uoId = $historia->unidad_organizativa_id;
        if ($uoId === null || ! in_array($uoId, $this->uoIds($usuario), true)) {
            return false;
        }

        return ! $this->historiaProtegida($historia) || in_array($uoId, $this->uoIdsProtegidos($usuario), true);
    }

    /**
     * Si el usuario gestiona a la persona protegida de esta historia o ciudadano
     * (RN-17): la UO de la historia está entre sus adscripciones directas o, con
     * rol `intervencion`, en su ámbito. Sin historia, nadie la gestiona por ámbito.
     *
     * @param User $usuario
     * @param Ciudadano|HistoriaSocial $objetivo
     *
     * @return bool
     */
    public function gestionaProtegido(User $usuario, Ciudadano|HistoriaSocial $objetivo): bool
    {
        $historia = $objetivo instanceof HistoriaSocial ? $objetivo : $this->historiaAbiertaDe($objetivo->id);

        return $historia !== null
            && $historia->unidad_organizativa_id !== null
            && in_array($historia->unidad_organizativa_id, $this->uoIdsProtegidos($usuario), true);
    }

    /**
     * Si la persona de la historia es de colectivo especialmente protegido. Manda
     * el indicador de la persona; el de la historia se suma por si lo tuviera.
     *
     * @param HistoriaSocial $historia
     *
     * @return bool
     */
    public function historiaProtegida(HistoriaSocial $historia): bool
    {
        return (bool) $historia->ciudadano_protegido
            || (bool) Ciudadano::withoutGlobalScope(AmbitoUoScope::class)->whereKey($historia->ciudadano_id)->value('colectivo_extra_protegido');
    }

    /**
     * Limita una subconsulta sobre `historias_sociales` a las que el usuario
     * gestiona (nivel 1): las de su ámbito no protegidas, más las de las UO
     * donde gestiona protegidas (RN-17). La usan AmbitoUoScope y el scope de apuntes.
     *
     * @param QueryBuilder $historias Subconsulta sobre la tabla historias_sociales.
     * @param User $usuario
     *
     * @return QueryBuilder
     */
    public function restringirHistoriasGestionables(QueryBuilder $historias, User $usuario): QueryBuilder
    {
        $uoIds = $this->uoIds($usuario);
        $protegidas = $this->uoIdsProtegidos($usuario);

        return $historias->where(function (QueryBuilder $q) use ($uoIds, $protegidas): void {
            $q->whereIn('historias_sociales.unidad_organizativa_id', $protegidas)
                ->orWhere(function (QueryBuilder $libres) use ($uoIds): void {
                    $libres->whereIn('historias_sociales.unidad_organizativa_id', $uoIds)
                        ->where('historias_sociales.ciudadano_protegido', false)
                        ->whereNotIn('historias_sociales.ciudadano_id', function (QueryBuilder $p): void {
                            $p->select('id')->from('ciudadanos')->where('colectivo_extra_protegido', true);
                        });
                });
        });
    }

    /**
     * Historia abierta más reciente de una persona, sin scope.
     *
     * @param int $ciudadanoId
     *
     * @return HistoriaSocial|null
     */
    private function historiaAbiertaDe(int $ciudadanoId): ?HistoriaSocial
    {
        return HistoriaSocial::withoutGlobalScope(AmbitoUoScope::class)
            ->where('ciudadano_id', $ciudadanoId)
            ->whereIn('estado', ['abierta', 'en_seguimiento'])
            ->latest()
            ->first();
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
