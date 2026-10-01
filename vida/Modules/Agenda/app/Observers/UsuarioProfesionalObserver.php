<?php

namespace Modules\Agenda\Observers;

use App\Models\User;
use Modules\Agenda\Services\PerfilHorarioPorDefectoService;

/**
 * Da el horario del centro a una cuenta ya adscrita en cuanto se vincula a una
 * ficha de profesional, para que no quede sin perfil horario.
 */
class UsuarioProfesionalObserver
{
    /**
     * @param PerfilHorarioPorDefectoService $perfiles
     */
    public function __construct(private readonly PerfilHorarioPorDefectoService $perfiles) {}

    /**
     * Crea los perfiles por defecto si el usuario acaba de recibir profesional.
     *
     * @param User $model
     * @return void
     */
    public function updated(User $model): void
    {
        if ($model->wasChanged('profesional_id') && $model->profesional_id !== null) {
            $this->perfiles->alVincularProfesional($model);
        }
    }
}
