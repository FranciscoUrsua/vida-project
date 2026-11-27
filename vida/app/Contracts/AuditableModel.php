<?php

namespace App\Contracts;

use App\Enums\AccionAuditEnum;

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

    /**
     * Resumen legible de una escritura para «Últimos accesos» (U3). Se calcula al escribir la
     * auditoría y se guarda en `contexto['resumen']`: no se recalcula al leer (U3.1).
     *
     * @param AccionAuditEnum $accion Crear, editar o eliminar.
     * @param array<string, mixed> $antes Valores anteriores de los campos auditados.
     * @param array<string, mixed> $despues Valores nuevos de los campos auditados.
     *
     * @return string|null
     */
    public function resumenAuditoria(AccionAuditEnum $accion, array $antes, array $despues): ?string;
}
