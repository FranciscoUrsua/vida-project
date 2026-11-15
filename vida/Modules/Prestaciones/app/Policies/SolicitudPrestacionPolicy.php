<?php

namespace Modules\Prestaciones\Policies;

use App\Models\HistoriaSocial;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Modules\Prestaciones\Enums\EstadoSolicitudPrestacion;
use Modules\Prestaciones\Models\SolicitudPrestacion;

/**
 * Quién crea, ve, tramita y registra a mano una solicitud de prestación (§5 del diseño).
 *
 * Dos accesos distintos:
 * - **Del lado del trabajador social** (`solicitud.ver`): quien la creó o quien puede ver la
 *   Historia Social de la persona (con la regla de personas protegidas, RN-17 ampliada).
 * - **De tramitación** (`solicitud.tramitar`): solo si la unidad tramitadora está en sus
 *   adscripciones (o sus descendientes orgánicas). Ve la solicitud, nunca la Historia Social
 *   (RN-10): esta policy no da acceso a la historia; eso sigue en HistoriaSocialPolicy.
 */
class SolicitudPrestacionPolicy
{
    /**
     * Crear solicitudes: trabajador social y supervisión.
     *
     * @param User $usuario
     *
     * @return bool
     */
    public function create(User $usuario): bool
    {
        return $usuario->can('solicitud.crear');
    }

    /**
     * Ver la solicitud y su historial.
     *
     * @param User $usuario
     * @param SolicitudPrestacion $solicitud
     *
     * @return bool
     */
    public function view(User $usuario, SolicitudPrestacion $solicitud): bool
    {
        return $this->delLadoDelTs($usuario, $solicitud) || $this->tramitar($usuario, $solicitud);
    }

    /**
     * Tramitar: está en la bandeja de una unidad del usuario.
     *
     * @param User $usuario
     * @param SolicitudPrestacion $solicitud
     *
     * @return bool
     */
    public function tramitar(User $usuario, SolicitudPrestacion $solicitud): bool
    {
        return $usuario->can('solicitud.tramitar')
            && $solicitud->unidad_tramitadora_id !== null
            && in_array($solicitud->unidad_tramitadora_id, $usuario->uoSubtreeIds(), true);
    }

    /**
     * Editar el borrador: quien lo creó, o quien puede crear solicitudes y ve la historia.
     *
     * @param User $usuario
     * @param SolicitudPrestacion $solicitud
     *
     * @return bool
     */
    public function editarBorrador(User $usuario, SolicitudPrestacion $solicitud): bool
    {
        return $solicitud->estado === EstadoSolicitudPrestacion::Borrador
            && $usuario->can('solicitud.crear')
            && $this->delLadoDelTs($usuario, $solicitud);
    }

    /**
     * Registrar a mano estado, incidencia o resolución (RN-07.4).
     *
     * @param User $usuario
     * @param SolicitudPrestacion $solicitud
     *
     * @return bool
     */
    public function registrarManual(User $usuario, SolicitudPrestacion $solicitud): bool
    {
        return $usuario->can('solicitud.registrar_manual') && $this->tramitar($usuario, $solicitud);
    }

    /**
     * Acceso del lado del trabajador social: la creó o ve la Historia Social de la persona.
     *
     * @param User $usuario
     * @param SolicitudPrestacion $solicitud
     *
     * @return bool
     */
    private function delLadoDelTs(User $usuario, SolicitudPrestacion $solicitud): bool
    {
        if (! $usuario->can('solicitud.ver')) {
            return false;
        }

        if ($solicitud->creada_por_id === $usuario->id) {
            return true;
        }

        $historia = HistoriaSocial::withoutGlobalScopes()->find($solicitud->historia_id);

        return $historia !== null && Gate::forUser($usuario)->allows('view', $historia);
    }
}
