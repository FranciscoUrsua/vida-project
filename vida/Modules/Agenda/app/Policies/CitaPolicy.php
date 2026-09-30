<?php

namespace Modules\Agenda\Policies;

use App\Models\User;
use Modules\Agenda\Models\Cita;
use Modules\Agenda\Models\Slot;

/**
 * Permisos sobre citas (docs/modulo-citas.md §7).
 *
 * Dan, reprogramar y cancelar citas `consulta_basica` y supervisión del centro
 * (`citas.gestionar`); el profesional atiende las suyas (`citas.atender`).
 * Regla de citas propias (RN-05, RN-05 bis): nadie reprograma ni cancela citas
 * de su propia agenda salvo supervisión; dar citas en slots propios sí se
 * permite a quien gestiona citas (auxiliares con doble rol).
 *
 * El alcance es el centro: el usuario debe estar adscrito a la UO del centro.
 */
class CitaPolicy
{
    /**
     * Ver la cita: su profesional o quien gestiona o supervisa las del centro.
     *
     * @param User $user
     * @param Cita $cita
     * @return bool
     */
    public function view(User $user, Cita $cita): bool
    {
        return $cita->profesional_id === $user->id
            || ($this->delCentro($user, $cita->centro_id)
                && ($user->can('citas.gestionar') || $user->can('citas.supervisar')));
    }

    /**
     * Dar una cita en un slot (también propio, RN-05 bis).
     *
     * @param User $user
     * @param Slot $slot
     * @return bool
     */
    public function citar(User $user, Slot $slot): bool
    {
        return $user->can('citas.gestionar') && $this->delCentro($user, $slot->centro_id);
    }

    /**
     * Reprogramar: gestión del centro y no en la agenda propia (salvo supervisión).
     *
     * @param User $user
     * @param Cita $cita
     * @return bool
     */
    public function reprogramar(User $user, Cita $cita): bool
    {
        return $this->gestionaAjena($user, $cita);
    }

    /**
     * Cancelar: igual que reprogramar.
     *
     * @param User $user
     * @param Cita $cita
     * @return bool
     */
    public function cancelar(User $user, Cita $cita): bool
    {
        return $this->gestionaAjena($user, $cita);
    }

    /**
     * Cancelación retroactiva (la hora ya pasó): solo supervisión.
     *
     * @param User $user
     * @param Cita $cita
     * @return bool
     */
    public function cancelarRetroactiva(User $user, Cita $cita): bool
    {
        return $user->can('citas.supervisar') && $this->delCentro($user, $cita->centro_id);
    }

    /**
     * Marcar incomparecencia y registrar acompañantes: el profesional de la
     * cita, o supervisión del centro.
     *
     * @param User $user
     * @param Cita $cita
     * @return bool
     */
    public function atender(User $user, Cita $cita): bool
    {
        return ($cita->profesional_id === $user->id && $user->can('citas.atender'))
            || $this->supervisa($user, $cita);
    }

    /**
     * Pedir un cambio al supervisor sobre una cita propia (RN-05).
     *
     * @param User $user
     * @param Cita $cita
     * @return bool
     */
    public function pedirCambio(User $user, Cita $cita): bool
    {
        return $cita->profesional_id === $user->id && $user->can('citas.atender');
    }

    /**
     * Identificar a la persona de una cita externa.
     *
     * @param User $user
     * @param Cita $cita
     * @return bool
     */
    public function identificar(User $user, Cita $cita): bool
    {
        return $user->can('citas.gestionar') && $this->delCentro($user, $cita->centro_id);
    }

    /**
     * Vincular un apunte a una cita con incomparecencia o cancelada, para
     * corregir un error de marcado: solo supervisión del centro.
     *
     * @param User $user
     * @param Cita $cita
     * @return bool
     */
    public function corregirCierre(User $user, Cita $cita): bool
    {
        return $this->supervisa($user, $cita);
    }

    /**
     * @param User $user
     * @param Cita $cita
     * @return bool
     */
    private function gestionaAjena(User $user, Cita $cita): bool
    {
        if (! $user->can('citas.gestionar') || ! $this->delCentro($user, $cita->centro_id)) {
            return false;
        }

        // RN-05 / RN-05 bis: las citas de la propia agenda solo las mueve supervisión
        return $cita->profesional_id !== $user->id || $user->can('citas.supervisar');
    }

    /**
     * @param User $user
     * @param Cita $cita
     * @return bool
     */
    private function supervisa(User $user, Cita $cita): bool
    {
        return $user->can('citas.supervisar') && $this->delCentro($user, $cita->centro_id);
    }

    /**
     * Si el usuario está adscrito a la UO del centro.
     *
     * @param User $user
     * @param int $centroId
     * @return bool
     */
    private function delCentro(User $user, int $centroId): bool
    {
        return AlcanceCentro::incluye($user, $centroId);
    }
}
