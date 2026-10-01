<?php

namespace Modules\Agenda\Observers;

use App\Models\UsuarioUo;
use Modules\Agenda\Services\PerfilHorarioPorDefectoService;

/**
 * Da el horario del centro a quien se adscribe a la UO de un centro, para que
 * ningún profesional quede sin perfil horario (docs/modulo-agenda.md §2.3).
 */
class UsuarioUoObserver
{
    /**
     * @param PerfilHorarioPorDefectoService $perfiles
     */
    public function __construct(private readonly PerfilHorarioPorDefectoService $perfiles) {}

    /**
     * Crea el perfil horario por defecto y avisa a la supervisión del centro.
     *
     * @param UsuarioUo $model
     * @return void
     */
    public function created(UsuarioUo $model): void
    {
        $this->perfiles->alAdscribir($model);
    }
}
