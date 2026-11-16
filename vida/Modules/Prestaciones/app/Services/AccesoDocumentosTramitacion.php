<?php

namespace Modules\Prestaciones\Services;

use App\Models\Ciudadano;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Modules\Documentos\Contracts\AccesoDocumentoAdicional;
use Modules\Documentos\Models\Documento;
use Modules\Prestaciones\Models\SolicitudPrestacion;

/**
 * Tramitación ve los documentos de las personas cuyas solicitudes tramita.
 *
 * RN-10 de docs/modulo-solicitudes-expedientes.md: tramitación necesita los documentos
 * para preparar la solicitud, aunque no ve la carpeta completa de la persona
 * (fase 4). Decisión del desarrollador (15 nov 2026): en la solicitud sigue viendo la
 * carpeta de la persona para vincular documentos, y puede abrirlos. La vía cubre los
 * documentos vinculados a una solicitud que el usuario tramita (SolicitudPrestacionPolicy::tramitar)
 * y los de la carpeta de la persona de esa solicitud.
 */
class AccesoDocumentosTramitacion implements AccesoDocumentoAdicional
{
    /**
     * Indica si el usuario tramita alguna solicitud vinculada al documento o de una de sus personas.
     *
     * @param User $usuario Usuario.
     * @param Documento $documento Documento disponible.
     *
     * @return bool
     */
    public function permite(User $usuario, Documento $documento): bool
    {
        if (! $usuario->can('solicitud.tramitar')) {
            return false;
        }

        $vinculos = $documento->vinculosActivos()->get(['vinculable_type', 'vinculable_id']);
        $personas = $vinculos->where('vinculable_type', (new Ciudadano)->getMorphClass())->pluck('vinculable_id');
        $solicitudes = $vinculos->where('vinculable_type', (new SolicitudPrestacion)->getMorphClass())->pluck('vinculable_id');

        if ($personas->isEmpty() && $solicitudes->isEmpty()) {
            return false;
        }

        return SolicitudPrestacion::query()
            ->where(fn ($q) => $q->whereIn('ciudadano_id', $personas)->orWhereIn('id', $solicitudes))
            ->get()
            ->contains(fn (SolicitudPrestacion $solicitud): bool => Gate::forUser($usuario)->allows('tramitar', $solicitud));
    }
}
