<?php

namespace Modules\Centro\Policies;

use App\Models\User;
use Modules\Centro\Models\TipoCentro;

/**
 * Permisos sobre los tipos de centro (docs/modulo-tipo-centro.md §9).
 *
 * Toda la escritura (crear, modificar, renombrar, horario, cierres comunes y
 * baja) es solo del administrador del sistema, identificado por el permiso
 * `centro.gestionar` (rol `adm_sistema`). Leer, cualquier usuario del backoffice.
 */
class TipoCentroPolicy
{
    /**
     * Ver el listado de tipos.
     *
     * @param User $usuario
     *
     * @return bool
     */
    public function viewAny(User $usuario): bool
    {
        return $usuario->accedeAlBackoffice();
    }

    /**
     * Ver un tipo.
     *
     * @param User $usuario
     * @param TipoCentro $tipo
     *
     * @return bool
     */
    public function view(User $usuario, TipoCentro $tipo): bool
    {
        return $usuario->accedeAlBackoffice();
    }

    /**
     * Crear un tipo.
     *
     * @param User $usuario
     *
     * @return bool
     */
    public function create(User $usuario): bool
    {
        return $this->administra($usuario);
    }

    /**
     * Modificar un tipo (configuración, catálogo, horario, protocolo).
     *
     * @param User $usuario
     * @param TipoCentro $tipo
     *
     * @return bool
     */
    public function update(User $usuario, TipoCentro $tipo): bool
    {
        return $this->administra($usuario);
    }

    /**
     * Programar una denominación nueva.
     *
     * @param User $usuario
     * @param TipoCentro $tipo
     *
     * @return bool
     */
    public function renombrar(User $usuario, TipoCentro $tipo): bool
    {
        return $this->administra($usuario);
    }

    /**
     * Gestionar los cierres de calendario comunes del tipo.
     *
     * @param User $usuario
     * @param TipoCentro $tipo
     *
     * @return bool
     */
    public function gestionarCalendario(User $usuario, TipoCentro $tipo): bool
    {
        return $this->administra($usuario);
    }

    /**
     * Dar de baja un tipo (además, sin centros activos: RN-11).
     *
     * @param User $usuario
     * @param TipoCentro $tipo
     *
     * @return bool
     */
    public function darDeBaja(User $usuario, TipoCentro $tipo): bool
    {
        return $this->administra($usuario);
    }

    /**
     * Los tipos no se borran: solo se dan de baja.
     *
     * @param User $usuario
     * @param TipoCentro $tipo
     *
     * @return bool
     */
    public function delete(User $usuario, TipoCentro $tipo): bool
    {
        return false;
    }

    /**
     * Administrador del sistema a efectos de centros y tipos.
     *
     * @param User $usuario
     *
     * @return bool
     */
    private function administra(User $usuario): bool
    {
        return $usuario->can('centro.gestionar');
    }
}
