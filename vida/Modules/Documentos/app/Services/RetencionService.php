<?php

namespace Modules\Documentos\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Documentos\Enums\MotivoRetencion;
use Modules\Documentos\Models\Documento;
use Modules\Documentos\Models\DocumentoRetencion;
use Modules\Documentos\Models\DocumentoVersion;

/**
 * Crea retenciones sobre documentos o versiones concretas.
 *
 * Ningún evento lo llama todavía: está pendiente decidir qué hito crea una retención
 * «intervención cerrada» (ver BACKLOG).
 */
class RetencionService
{
    /**
     * Retiene un documento entero o una de sus versiones.
     *
     * @param Documento $documento Documento retenido.
     * @param MotivoRetencion $motivo Motivo de la retención.
     * @param User $usuario Quien la crea.
     * @param DocumentoVersion|null $version Versión concreta; null = todo el documento.
     * @param Model|null $retenedor Entidad que causa la retención.
     * @param Carbon|null $hasta Fin; null = indefinida.
     * @param string|null $observaciones Observaciones libres.
     *
     * @throws \InvalidArgumentException si la versión no es de ese documento
     *
     * @return DocumentoRetencion
     */
    public function retener(
        Documento $documento,
        MotivoRetencion $motivo,
        User $usuario,
        ?DocumentoVersion $version = null,
        ?Model $retenedor = null,
        ?Carbon $hasta = null,
        ?string $observaciones = null,
    ): DocumentoRetencion {
        if ($version !== null && $version->documento_id !== $documento->id) {
            throw new \InvalidArgumentException('La versión no pertenece al documento.');
        }

        return $documento->retenciones()->create([
            'documento_version_id' => $version?->id,
            'motivo' => $motivo,
            'retenedor_type' => $retenedor?->getMorphClass(),
            'retenedor_id' => $retenedor?->getKey(),
            'desde' => now(),
            'hasta' => $hasta,
            'observaciones' => $observaciones,
            'creado_por' => $usuario->id,
        ]);
    }
}
