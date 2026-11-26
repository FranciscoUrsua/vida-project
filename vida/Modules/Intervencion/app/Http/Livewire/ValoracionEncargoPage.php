<?php

namespace Modules\Intervencion\Http\Livewire;

use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Agenda\Services\Citas\AtencionCitaService;
use Modules\Intervencion\Http\Livewire\Concerns\ConEncargoAutorizado;
use Modules\Intervencion\Http\Livewire\Concerns\FormularioValoracion;
use Modules\Intervencion\Services\Actuaciones\ContextoActuacion;
use Modules\Intervencion\Services\Actuaciones\ValoracionService;
use Modules\Intervencion\Services\CajaHerramientasEncargo;

/**
 * Ficha de valoración desde un encargo profesional (bloque D de
 * docs/instrucciones-cli/2026-11-encargo-actuaciones.md).
 *
 * Autoriza por el encargo (ConEncargoAutorizado y EA-4), no por la Historia Social, a la que
 * el profesional de encargo no tiene acceso. Solo ofrece las fichas del área del encargo
 * (EA-2, `CajaHerramientasEncargo`), y lo vuelve a comprobar al guardar, igual que
 * `ValoracionService`. La ficha queda en la Historia vinculada al encargo, sin plan (EA-5),
 * y, con `?cita=`, completa la cita (EA-10). No propone datos de fichas anteriores (EA-8).
 */
#[Layout('layouts.operativo')]
class ValoracionEncargoPage extends Component
{
    use ConEncargoAutorizado;
    use FormularioValoracion;

    /** @var int|null Cita propuesta desde el encargo (?cita=). */
    #[Url(as: 'cita', except: null)]
    public ?int $citaId = null;

    /**
     * Autoriza la apertura: el encargo es suyo y puede actuar en él (si no, 403).
     *
     * @param int $encargo Id del encargo.
     *
     * @return void
     */
    public function mount(int $encargo): void
    {
        $this->encargoId = $encargo;
        abort_unless($this->puedeActuar, 403);

        $this->iniciarFormularioValoracion();
    }

    /**
     * Fichas de valoración del área del encargo, indexadas por id.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function fichasDisponibles(): array
    {
        return app(CajaHerramientasEncargo::class)->tiposFicha($this->encargo)->pluck('nombre', 'id')->all();
    }

    /**
     * URL de vuelta: el encargo.
     *
     * @return string
     */
    public function volverUrl(): string
    {
        return route('intervencion.encargos.show', $this->encargoId);
    }

    /**
     * Texto del enlace de vuelta.
     *
     * @return string
     */
    public function volverTexto(): string
    {
        return 'Volver al encargo';
    }

    /**
     * Guarda la ficha desde el encargo y vuelve a él. Quién actúa, la cita y RN-15 los
     * comprueban ContextoActuacion y ValoracionService (sus DomainException se avisan como
     * siempre, sin perder el formulario).
     *
     * @return mixed
     */
    public function guardarDefinitivo(): mixed
    {
        if (! $this->validarFormularioValoracion('Esa ficha de valoración no es del área del encargo.')) {
            return null;
        }

        // Como desde la Historia: la cita pedida si sigue siendo vinculable (o la de hoy)
        $citaId = $this->citaId !== null
            ? app(AtencionCitaService::class)->citaVinculable($this->persona->id, auth()->user(), $this->citaId)?->id
            : null;

        app(ValoracionService::class)->registrar(
            ContextoActuacion::desdeEncargo($this->encargo, auth()->user(), $citaId),
            $this->tipoFicha,
            $this->datos,
            $this->notas,
        );

        return $this->redirect($this->volverUrl(), navigate: true);
    }

    /**
     * Renderiza la pantalla (la misma vista que la valoración desde la Historia).
     *
     * @return View
     */
    public function render(): View
    {
        return view('intervencion::livewire.registrar-valoracion-page');
    }
}
