<?php

namespace Modules\Intervencion\Http\Livewire;

use App\Models\HistoriaSocial;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Escalas\Models\TipoEscala;

/**
 * Pantalla completa para aplicar un instrumento de escala.
 *
 * Carga el schema del TipoEscala y presenta las secciones con sus ítems.
 * Al guardar, delega en CiudadanoPage::guardarEscala().
 *
 * @see docs/instrucciones-cli/ui-intervencion-entrega3.md §5.6
 */
#[Layout('layouts.operativo')]
class RegistrarEscalaPage extends Component
{
    public HistoriaSocial $historia;

    /** @var int|null ID del TipoEscala seleccionado */
    public ?int $tipoEscalaId = null;

    /** @var array<string, mixed> Respuestas del pase [item_id => valor] */
    public array $respuestas = [];

    public function mount(HistoriaSocial $historia, ?int $tipo_escala = null): void
    {
        $this->historia = $historia;
        $this->tipoEscalaId = $tipo_escala;
    }

    public function getTipoEscalaProperty(): ?TipoEscala
    {
        return $this->tipoEscalaId ? TipoEscala::find($this->tipoEscalaId) : null;
    }

    /**
     * Guarda el pase de escala y redirige de vuelta a la pantalla del ciudadano.
     */
    public function guardar(): RedirectResponse
    {
        if (! $this->tipoEscalaId) {
            $this->addError('tipoEscalaId', 'Selecciona un instrumento.');

            return redirect()->back();
        }

        $page = new CiudadanoPage;
        $page->historia = $this->historia;
        $page->guardarEscala($this->tipoEscalaId, $this->respuestas);

        return redirect()->route('intervencion.ciudadano.show', $this->historia->id);
    }

    public function render(): View
    {
        return view('intervencion::livewire.registrar-escala-page');
    }
}
