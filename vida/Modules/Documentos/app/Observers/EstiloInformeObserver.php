<?php

namespace Modules\Documentos\Observers;

use Modules\Documentos\Models\EstiloInforme;
use Modules\Documentos\Services\ResolverEstiloInforme;

/**
 * Observer de EstiloInforme.
 *
 * Invalida la caché de estilos de la UO afectada y todas sus descendientes
 * cada vez que un EstiloInforme se guarda o elimina.
 */
class EstiloInformeObserver
{
    /**
     * Crea el observer con el resolutor de estilos.
     */
    public function __construct(private ResolverEstiloInforme $resolver) {}

    /**
     * Invalida la caché cuando se guarda un estilo.
     */
    public function saved(EstiloInforme $estilo): void
    {
        $this->resolver->invalidarCacheUo($estilo->unidad_organizativa_id);
    }

    /**
     * Invalida la caché cuando se elimina un estilo.
     */
    public function deleted(EstiloInforme $estilo): void
    {
        $this->resolver->invalidarCacheUo($estilo->unidad_organizativa_id);
    }
}
