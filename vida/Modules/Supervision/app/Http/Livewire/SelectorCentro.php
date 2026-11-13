<?php

namespace Modules\Supervision\Http\Livewire;

use App\Models\User;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Centro\Models\Centro;
use Modules\Centro\Services\Asignacion\CentroDeUsuario;

/**
 * Selector del centro supervisado en el menú de Supervisión (paso 8 ter.2 de Tipo de Centro).
 *
 * Solo aparece si el usuario puede supervisar más de un centro (los de su ámbito
 * por las dos jerarquías, D1.8). La elección vale para todas las pantallas de
 * supervisión (Mi equipo, cuadrante, asignaciones…) hasta que cambie de centro
 * de trabajo en la barra superior.
 *
 * @property-read Collection<int, Centro> $centros
 * @property-read Centro|null $centroSupervisado
 */
class SelectorCentro extends Component
{
    /** Pantalla a la que se vuelve tras elegir centro. */
    #[Locked]
    public string $volverA = '';

    /**
     * Recuerda la pantalla actual; el cuadrante de un centro concreto vuelve al
     * cuadrante genérico, que redirige al del centro elegido.
     *
     * @return void
     */
    public function mount(): void
    {
        $this->volverA = request()->routeIs('agenda.cuadrante')
            ? route('supervision.cuadrante')
            : request()->fullUrl();
    }

    /**
     * Centros que el usuario puede supervisar.
     *
     * @return Collection<int, Centro>
     */
    #[Computed]
    public function centros(): Collection
    {
        return app(CentroDeUsuario::class)->centrosSupervisables($this->usuario());
    }

    /**
     * Centro con el que trabajan ahora las pantallas de supervisión.
     *
     * @return Centro|null
     */
    #[Computed]
    public function centroSupervisado(): ?Centro
    {
        return app(CentroDeUsuario::class)->centroSupervisado($this->usuario());
    }

    /**
     * Cambia el centro supervisado y recarga la pantalla.
     *
     * @param int|string $centroId
     *
     * @return void
     */
    public function elegir(int|string $centroId): void
    {
        $centro = Centro::find((int) $centroId);
        $usuario = $this->usuario();

        if ($centro === null || $usuario === null) {
            return;
        }

        try {
            // elegirSupervisado() rechaza un centro fuera de su ámbito aunque el navegador lo mande
            app(CentroDeUsuario::class)->elegirSupervisado($usuario, $centro);
        } catch (DomainException) {
            return;
        }

        $this->redirect($this->volverA ?: route('supervision.inicio'));
    }

    /**
     * Pinta el selector.
     *
     * @return View
     */
    public function render(): View
    {
        return view('supervision::livewire.selector-centro');
    }

    /**
     * Usuario autenticado.
     *
     * @return User|null
     */
    private function usuario(): ?User
    {
        $usuario = Auth::user();

        return $usuario instanceof User ? $usuario : null;
    }
}
