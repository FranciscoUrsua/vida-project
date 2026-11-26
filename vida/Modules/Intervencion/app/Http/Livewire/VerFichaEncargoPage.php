<?php

namespace Modules\Intervencion\Http\Livewire;

use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Intervencion\Http\Livewire\Concerns\ConEncargoAutorizado;
use Modules\Intervencion\Http\Livewire\Concerns\MuestraFicha;
use Modules\Intervencion\Models\Ficha;

/**
 * Ficha de valoración en lectura desde un encargo profesional (bloque D de
 * docs/instrucciones-cli/2026-11-encargo-actuaciones.md), también con el encargo cerrado (EA-11).
 *
 * Primero autoriza el encargo (ConEncargoAutorizado: suyo, persona visible; si no, 403). Una
 * ficha que no es de este encargo responde 404, sin datos: conocer el id de una ficha del TSR
 * no basta (EA-7). Además, `FichaPolicy::view` por la vía del encargo (autor y encargo suyo).
 *
 * @property-read Ficha $ficha
 */
#[Layout('layouts.operativo')]
class VerFichaEncargoPage extends Component
{
    use ConEncargoAutorizado;
    use MuestraFicha;

    /** @var int Ficha (parámetro de ruta). */
    #[Locked]
    public int $fichaId;

    /**
     * Autoriza el encargo y la ficha.
     *
     * @param int $encargo Id del encargo.
     * @param int $ficha Id de la ficha.
     *
     * @return void
     */
    public function mount(int $encargo, int $ficha): void
    {
        $this->encargoId = $encargo;
        $this->fichaId = $ficha;

        $this->ficha;
    }

    /**
     * Ficha autorizada en cada petición.
     *
     * @return Ficha
     */
    #[Computed]
    public function ficha(): Ficha
    {
        $encargo = $this->encargo;
        $ficha = Ficha::find($this->fichaId);

        abort_unless($ficha !== null && $ficha->derivacion_id === $encargo->id, 404);
        abort_unless(Gate::allows('view', $ficha), 403);

        return $ficha;
    }

    /**
     * Ficha que se muestra.
     *
     * @return Ficha
     */
    protected function fichaMostrada(): Ficha
    {
        return $this->ficha;
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
     * Sin mensaje con la ficha como contexto: el contexto `ficha` se autoriza por la Historia;
     * desde el encargo se escribe al TSR con el contexto del encargo.
     *
     * @return bool
     */
    public function conMensaje(): bool
    {
        return false;
    }

    /**
     * Renderiza la ficha (la misma vista que desde la Historia).
     *
     * @return View
     */
    public function render(): View
    {
        return view('intervencion::livewire.ver-ficha-page', ['ficha' => $this->ficha]);
    }
}
