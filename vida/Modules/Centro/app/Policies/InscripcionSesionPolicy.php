<?php

namespace Modules\Centro\Policies;

use App\Models\User;
use Modules\Centro\Models\Actividad;
use Modules\Centro\Models\Centro;
use Modules\Centro\Models\InscripcionSesion;
use Modules\Centro\Models\SesionActividad;
use Modules\Centro\Services\Asignacion\CentroDeUsuario;

/**
 * Quién inscribe, da de baja y ve inscritos en las actividades de un centro (fase 6, paso 5).
 *
 * La inscripción por acceso libre es atención individual: solo la hace el personal
 * adscrito al centro de la actividad (RN-15) con `intervencion`, `consulta_basica` o
 * `supervision` (la supervisión que llega desde una UO superior, no). La prescripción no
 * pasa por aquí: sigue el flujo del plan y puede hacerse desde otro centro (F6-2). La
 * gestión de la actividad y sus sesiones (baja, cancelación, celebrada) es de la
 * supervisión con el centro en su ámbito, como el resto de la pantalla de Actividades.
 */
class InscripcionSesionPolicy
{
    /**
     * Arranca con el cálculo de centros del usuario.
     *
     * @param CentroDeUsuario $centros
     */
    public function __construct(private readonly CentroDeUsuario $centros) {}

    /**
     * Si el usuario puede inscribir por acceso libre en una sesión.
     *
     * @param User $usuario
     * @param SesionActividad $sesion
     *
     * @return bool
     */
    public function inscribir(User $usuario, SesionActividad $sesion): bool
    {
        $centro = $sesion->actividad->centro;

        return $centro !== null && $this->inscribirEnCentro($usuario, $centro);
    }

    /**
     * Si el usuario puede inscribir por acceso libre en las actividades de un centro: está
     * adscrito a él (RN-15) con `intervencion`, `consulta_basica` o `supervision`. La
     * supervisión que llega al centro desde una UO superior no está adscrita y no inscribe.
     *
     * @param User $usuario
     * @param Centro $centro
     *
     * @return bool
     */
    public function inscribirEnCentro(User $usuario, Centro $centro): bool
    {
        return $usuario->hasAnyRole(['intervencion', 'consulta_basica', 'supervision'])
            && $this->centros->adscritoA($usuario, $centro);
    }

    /**
     * Si el usuario puede dar de baja una inscripción: quien puede inscribir en la sesión
     * o quien la hizo (el autor de una prescripción desde otro centro).
     *
     * @param User $usuario
     * @param InscripcionSesion $inscripcion
     *
     * @return bool
     */
    public function darDeBaja(User $usuario, InscripcionSesion $inscripcion): bool
    {
        return $inscripcion->inscrita_por_id === $usuario->id || $this->inscribir($usuario, $inscripcion->sesion);
    }

    /**
     * Si el usuario ve la lista de inscritos de una sesión: el personal del centro de la
     * actividad y su supervisión.
     *
     * @param User $usuario
     * @param SesionActividad $sesion
     *
     * @return bool
     */
    public function verInscritos(User $usuario, SesionActividad $sesion): bool
    {
        $centro = $sesion->actividad->centro;

        return $centro !== null
            && ($this->centros->adscritoA($usuario, $centro) || $this->centros->supervisa($usuario, $centro));
    }

    /**
     * Si el usuario gestiona la actividad y sus sesiones (baja, cancelación, celebrada,
     * borrado de sesiones vacías): la supervisión del centro.
     *
     * @param User $usuario
     * @param Actividad $actividad
     *
     * @return bool
     */
    public function gestionarActividad(User $usuario, Actividad $actividad): bool
    {
        return $actividad->centro !== null && $this->centros->supervisa($usuario, $actividad->centro);
    }
}
