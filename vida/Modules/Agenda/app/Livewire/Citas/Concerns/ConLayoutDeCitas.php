<?php

namespace Modules\Agenda\Livewire\Citas\Concerns;

use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Las pantallas de citas las usan supervisión, quien da citas y los
 * profesionales: cada uno las ve dentro de su propio entorno (barra lateral de
 * Supervisión o del operativo), en lugar de un entorno nuevo solo para citas.
 */
trait ConLayoutDeCitas
{
    /**
     * Pinta la vista con el layout que corresponde al usuario.
     *
     * @param string $vista
     *
     * @return View
     */
    protected function vistaConLayout(string $vista): View
    {
        // Cada rol trabaja la citación dentro de su superficie (User::layoutOperativo())
        $layout = Auth::user()?->layoutOperativo() ?? 'layouts.operativo';

        return view($vista)->layout($layout);
    }
}
