<?php

namespace Modules\Documentos\Contracts;

use App\Models\User;
use Modules\Documentos\Models\Documento;

/**
 * Vía de acceso a un documento que otro módulo aporta a DocumentoPolicy sin que Documentos lo conozca.
 *
 * Sirve a quien no ve la carpeta completa (`documento.ver_carpeta`) pero necesita un
 * documento por su trabajo: p. ej. tramitación, los documentos de las personas cuyas
 * solicitudes tramita (Prestaciones, RN-10 de docs/modulo-solicitudes-expedientes.md).
 * Las implementaciones se registran con la etiqueta DocumentoPolicy::ETIQUETA_ACCESOS.
 */
interface AccesoDocumentoAdicional
{
    /**
     * Indica si esta vía da acceso de lectura al documento.
     *
     * @param User $usuario Usuario.
     * @param Documento $documento Documento disponible (ni destruido ni retirado).
     *
     * @return bool
     */
    public function permite(User $usuario, Documento $documento): bool;
}
