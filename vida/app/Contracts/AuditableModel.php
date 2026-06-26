<?php

namespace App\Contracts;

/**
 * Contrato estático para modelos que exponen la API del trait Auditable.
 */
interface AuditableModel
{
    /**
     * @return list<string>
     */
    public function camposAuditables(): array;

    public function getCiudadanoId(): ?int;
}
