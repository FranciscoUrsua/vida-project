<?php

namespace Modules\Centro\Services\TipoCentro;

use App\Models\User;
use DomainException;
use Modules\Centro\Enums\ElementoCatalogo;
use Modules\Centro\Models\Centro;
use Modules\Centro\Models\ExclusionCatalogo;

/**
 * Exclusiones del catálogo del tipo en un centro (RN-08, §6.3).
 *
 * Solo se puede excluir si el tipo permite la restricción en centro y el elemento
 * está en el catálogo del tipo. Ningún cambio de catálogo reasigna nada (RN-07).
 */
class ExclusionCatalogoService
{
    /**
     * Excluye un elemento del catálogo del tipo en el centro.
     *
     * @param Centro $centro Centro.
     * @param ElementoCatalogo $tipo Clase de elemento.
     * @param int $elementoId Id de la prestación, tipo de plan o ficha.
     * @param User|null $actor Usuario que la crea.
     *
     * @throws DomainException Si el tipo no permite restricción o el elemento no es del tipo.
     *
     * @return ExclusionCatalogo
     */
    public function excluir(Centro $centro, ElementoCatalogo $tipo, int $elementoId, ?User $actor): ExclusionCatalogo
    {
        $tipoCentro = $centro->tipoCentro;

        if (! $tipoCentro->permite_restriccion_en_centro) {
            throw new DomainException('El tipo de centro no permite restringir su catálogo en un centro (RN-08).');
        }

        $relacion = match ($tipo) {
            ElementoCatalogo::Prestacion => $tipoCentro->prestaciones(),
            ElementoCatalogo::TipoPlan => $tipoCentro->tiposPlan(),
            ElementoCatalogo::TipoFicha => $tipoCentro->tiposFicha(),
        };

        // Sin fichas en el tipo, todas las activas forman parte de su catálogo
        $enCatalogo = $tipo === ElementoCatalogo::TipoFicha && ! $tipoCentro->tiposFicha()->exists()
            ? \Modules\Intervencion\Models\TipoFicha::whereKey($elementoId)->where('activo', true)->exists()
            : $relacion->whereKey($elementoId)->exists();

        if (! $enCatalogo) {
            throw new DomainException('Solo se puede excluir un elemento del catálogo del tipo de centro.');
        }

        return ExclusionCatalogo::firstOrCreate(
            ['centro_id' => $centro->id, 'elemento_tipo' => $tipo, 'elemento_id' => $elementoId],
            ['creado_por_id' => $actor?->id],
        );
    }

    /**
     * Quita una exclusión.
     *
     * @param Centro $centro Centro.
     * @param ElementoCatalogo $tipo Clase de elemento.
     * @param int $elementoId Id del elemento.
     *
     * @return void
     */
    public function quitar(Centro $centro, ElementoCatalogo $tipo, int $elementoId): void
    {
        ExclusionCatalogo::where('centro_id', $centro->id)->where('elemento_tipo', $tipo)->where('elemento_id', $elementoId)->delete();
    }
}
