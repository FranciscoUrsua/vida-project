<?php

namespace App\Livewire;

use App\Models\UnidadOrganizativa;
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
 * Centro de trabajo en la barra superior (paso 8 ter.4 de Tipo de Centro, D1.6).
 *
 * Muestra «Tipo · Centro» del centro activo con un selector si el usuario está
 * adscrito a más de un centro, o el nombre de su UO si no tiene ninguno. Elegir
 * un centro lo guarda en la sesión (CentroDeUsuario::elegir()) y recarga la
 * pantalla para que todo se recalcule con él.
 *
 * @property-read Collection<int, Centro> $centros
 * @property-read Centro|null $centroActivo
 * @property-read UnidadOrganizativa|null $uoSinCentro
 */
class CentroTrabajo extends Component
{
    /** Pantalla a la que se vuelve tras elegir centro. */
    #[Locked]
    public string $volverA = '';

    /**
     * Recuerda la pantalla actual; el cuadrante de un centro concreto vuelve al
     * cuadrante genérico, que redirige al del centro nuevo.
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
     * Centros del usuario (adscripciones directas vigentes).
     *
     * @return Collection<int, Centro>
     */
    #[Computed]
    public function centros(): Collection
    {
        return app(CentroDeUsuario::class)->centros($this->usuario())->load('tipoCentro');
    }

    /**
     * Centro en que trabaja ahora el usuario, o null.
     *
     * @return Centro|null
     */
    #[Computed]
    public function centroActivo(): ?Centro
    {
        $activo = app(CentroDeUsuario::class)->centroActivo($this->usuario());

        return $activo === null ? null : $this->centros->firstWhere('id', $activo->id);
    }

    /**
     * UO que se muestra cuando el usuario no está adscrito a ningún centro.
     *
     * @return UnidadOrganizativa|null
     */
    #[Computed]
    public function uoSinCentro(): ?UnidadOrganizativa
    {
        return $this->centros->isEmpty() ? $this->usuario()?->uosActivas()->first() : null;
    }

    /**
     * Texto «Siglas del tipo · Nombre corto del centro» (denominaciones vigentes).
     *
     * @param Centro $centro
     *
     * @return string
     */
    public function etiqueta(Centro $centro): string
    {
        $nombre = $centro->nombre_corto ?: $centro->nombre;

        return $centro->tipoCentro?->siglas ? $centro->tipoCentro->siglas.' · '.$nombre : $nombre;
    }

    /**
     * Cambia el centro de trabajo y recarga la pantalla.
     *
     * @param int $centroId
     *
     * @return void
     */
    public function elegir(int $centroId): void
    {
        $usuario = $this->usuario();
        $centro = $this->centros->firstWhere('id', $centroId);

        try {
            // elegir() rechaza un centro ajeno aunque el navegador lo mande
            app(CentroDeUsuario::class)->elegir($usuario, $centro ?? Centro::findOrFail($centroId));
        } catch (DomainException) {
            return;
        }

        $this->redirect($this->volverA ?: url('/'));
    }

    /**
     * Pinta el centro de trabajo.
     *
     * @return View
     */
    public function render(): View
    {
        return view('livewire.centro-trabajo');
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
