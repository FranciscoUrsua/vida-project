<?php

namespace Modules\Agenda\Policies;

use App\Models\User;
use Modules\Agenda\Models\SolicitudCita;
use Modules\Centro\Models\Centro;

/**
 * Permisos sobre solicitudes de cita (docs/modulo-citas.md §7).
 *
 * Crean solicitudes `consulta_basica`, intervención y supervisión
 * (`citas.solicitar`). La bandeja de citación y la gestión de las solicitudes
 * (tomar, soltar, citar, desistir, anular) son de `citas.gestionar` en el
 * centro. Quien pidió la solicitud también puede anularla.
 */
class SolicitudCitaPolicy
{
    /**
     * Crear solicitudes.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return $user->can('citas.solicitar');
    }

    /**
     * Ver la bandeja de citación de un centro.
     *
     * @param User $user
     * @param Centro $centro
     * @return bool
     */
    public function verBandeja(User $user, Centro $centro): bool
    {
        return $user->can('citas.gestionar') && AlcanceCentro::incluye($user, $centro->id);
    }

    /**
     * Tomar, soltar, citar y desistir.
     *
     * @param User $user
     * @param SolicitudCita $solicitud
     * @return bool
     */
    public function gestionar(User $user, SolicitudCita $solicitud): bool
    {
        return $user->can('citas.gestionar') && AlcanceCentro::incluye($user, $solicitud->centro_id);
    }

    /**
     * Anular: quien gestiona las del centro o quien la pidió.
     *
     * @param User $user
     * @param SolicitudCita $solicitud
     * @return bool
     */
    public function anular(User $user, SolicitudCita $solicitud): bool
    {
        return $solicitud->solicitante_id === $user->id || $this->gestionar($user, $solicitud);
    }
}
