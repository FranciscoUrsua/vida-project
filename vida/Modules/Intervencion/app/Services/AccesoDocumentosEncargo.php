<?php

namespace Modules\Intervencion\Services;

use App\Models\Ciudadano;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Documentos\Contracts\AccesoDocumentoAdicional;
use Modules\Documentos\Models\Documento;
use Modules\Intervencion\Models\Derivacion;

/**
 * El profesional de encargo ve los documentos de sus encargos (EP-8 y bloque D de
 * docs/instrucciones-cli/2026-11-encargo-profesional.md), aunque no ve la carpeta de la persona.
 *
 * Dos casos:
 * - documentos vinculados a un encargo suyo (aceptado o cerrado): los que adjunta el TSR
 *   y los que sube él desde el encargo;
 * - documentos que ha subido él vinculados a una persona de sus encargos, sin límite de día.
 *
 * Restricción de dominio (EP-7, RN-17 ampliada): en los dos casos tiene que seguir viendo a
 * la persona (EncargosDeUsuario). De una persona protegida con el encargo ya cerrado no ve
 * nada, porque el documento la identifica, salvo con acceso protegido aprobado (RN-18).
 */
class AccesoDocumentosEncargo implements AccesoDocumentoAdicional
{
    /**
     * Inyecta el ámbito del profesional de encargo.
     *
     * @param EncargosDeUsuario $encargos
     */
    public function __construct(private readonly EncargosDeUsuario $encargos) {}

    /**
     * Indica si el documento es de un encargo del usuario, o lo subió él sobre una persona de sus encargos.
     *
     * @param User $usuario Usuario.
     * @param Documento $documento Documento disponible.
     *
     * @return bool
     */
    public function permite(User $usuario, Documento $documento): bool
    {
        if (! $usuario->can(EncargoService::PERMISO_PROFESIONAL)) {
            return false;
        }

        $vinculos = $documento->vinculosActivos()->get(['vinculable_type', 'vinculable_id']);
        $visibles = $this->encargos->personaIds($usuario);

        if ($visibles === []) {
            return false;
        }

        // Vinculado a un encargo suyo cuya persona sigue viendo
        $encargoIds = $vinculos->where('vinculable_type', (new Derivacion)->getMorphClass())->pluck('vinculable_id');
        if ($encargoIds->isNotEmpty()) {
            $historiaIds = Derivacion::query()->deProfesional($usuario)->whereKey($encargoIds)->pluck('historia_id');
            // Sin los scopes de ámbito de las historias: el profesional de encargo no tiene ámbito de UO
            $personasDeEncargos = DB::table('historias_sociales')->whereIn('id', $historiaIds)
                ->pluck('ciudadano_id')
                ->map(fn ($id) => (int) $id);

            if ($personasDeEncargos->intersect($visibles)->isNotEmpty()) {
                return true;
            }
        }

        // Subido por él sobre una persona de sus encargos
        if ((int) $documento->created_by !== $usuario->id) {
            return false;
        }

        return $vinculos->where('vinculable_type', (new Ciudadano)->getMorphClass())
            ->pluck('vinculable_id')
            ->map(fn ($id) => (int) $id)
            ->intersect($visibles)
            ->isNotEmpty();
    }
}
