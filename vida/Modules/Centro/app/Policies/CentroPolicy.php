<?php

namespace Modules\Centro\Policies;

use App\Models\User;
use Modules\Centro\Models\Centro;
use Modules\Centro\Services\Asignacion\CentroDeUsuario;

/**
 * Permisos sobre los centros (docs/modulo-tipo-centro.md §9).
 *
 * El administrador del sistema (permiso `centro.gestionar`) lo hace todo. El
 * director vigente del centro (`DirectorCentro`) solo edita su centro y solo
 * lo de §9: contacto y descripción, horario, cierres propios, protocolo y
 * exclusiones del catálogo si el tipo lo permite. Los campos concretos los
 * valida CentroService. Las propiedades «solo tipo» no las cambia nadie desde
 * el centro. Supervisión ve los centros que supervisa (D1.8).
 */
class CentroPolicy
{
    /**
     * Ver el listado de centros.
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
     * Ver un centro: el administrador, su director o quien lo supervisa.
     *
     * @param User $usuario
     * @param Centro $centro
     *
     * @return bool
     */
    public function view(User $usuario, Centro $centro): bool
    {
        return $this->administra($usuario)
            || $centro->esDirigidoPor($usuario)
            || app(CentroDeUsuario::class)->supervisa($usuario, $centro);
    }

    /**
     * Dar de alta un centro.
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
     * Editar el centro: el administrador, o su director con los campos limitados de §9.
     *
     * @param User $usuario
     * @param Centro $centro
     *
     * @return bool
     */
    public function update(User $usuario, Centro $centro): bool
    {
        return $this->administra($usuario) || $centro->esDirigidoPor($usuario);
    }

    /**
     * Personalizar horario, cierres propios y protocolo del centro.
     *
     * @param User $usuario
     * @param Centro $centro
     *
     * @return bool
     */
    public function gestionarCalendario(User $usuario, Centro $centro): bool
    {
        return $this->update($usuario, $centro);
    }

    /**
     * Excluir elementos del catálogo del tipo (además, el tipo debe permitirlo: RN-08).
     *
     * @param User $usuario
     * @param Centro $centro
     *
     * @return bool
     */
    public function excluirCatalogo(User $usuario, Centro $centro): bool
    {
        return $this->update($usuario, $centro);
    }

    /**
     * Programar una denominación nueva del centro.
     *
     * @param User $usuario
     * @param Centro $centro
     *
     * @return bool
     */
    public function renombrar(User $usuario, Centro $centro): bool
    {
        return $this->administra($usuario);
    }

    /**
     * Dar de baja el centro (baja lógica).
     *
     * @param User $usuario
     * @param Centro $centro
     *
     * @return bool
     */
    public function darDeBaja(User $usuario, Centro $centro): bool
    {
        return $this->administra($usuario);
    }

    /**
     * Los centros no se borran: solo se dan de baja.
     *
     * @param User $usuario
     * @param Centro $centro
     *
     * @return bool
     */
    public function delete(User $usuario, Centro $centro): bool
    {
        return false;
    }

    /**
     * Si el usuario es el administrador del sistema a efectos de centros.
     *
     * @param User $usuario
     *
     * @return bool
     */
    public function administra(User $usuario): bool
    {
        return $usuario->can('centro.gestionar');
    }
}
