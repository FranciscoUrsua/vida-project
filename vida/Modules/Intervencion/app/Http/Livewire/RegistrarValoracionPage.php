<?php

namespace Modules\Intervencion\Http\Livewire;

use App\Models\HistoriaSocial;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Intervencion\Models\TipoFicha;

/**
 * Pantalla completa para registrar una valoración sobre la Historia Social.
 *
 * Carga el schema del TipoFicha y renderiza los campos dinámicamente.
 * Al guardar, delega en CiudadanoPage::guardarValoracion() para
 * mantener la lógica de creación en un único lugar.
 *
 * @see docs/instrucciones-cli/ui-intervencion-entrega3.md §5.5
 */
#[Layout('layouts.operativo')]
class RegistrarValoracionPage extends Component
{
    public HistoriaSocial $historia;

    /** @var int|null ID del TipoFicha seleccionado */
    public ?int $tipoFichaId = null;

    /** @var int|null Entrevista que origina esta valoración */
    public ?int $entrevistaId = null;

    /** @var array<string, mixed> Respuestas del formulario dinámico */
    public array $respuestas = [];

    public function mount(HistoriaSocial $historia): void
    {
        $this->historia = $historia;
        // Livewire 4 full-page components no reciben query string en mount(); se lee directamente.
        $tipoFicha = request()->query('tipo_ficha');
        $this->tipoFichaId = $tipoFicha ? (int) $tipoFicha : null;
        $entrevista = request()->query('entrevista');
        $this->entrevistaId = $entrevista ? (int) $entrevista : null;
    }

    /**
     * Devuelve el TipoFicha cargado si hay un ID seleccionado.
     */
    public function getTipoFichaProperty(): ?TipoFicha
    {
        return $this->tipoFichaId ? TipoFicha::find($this->tipoFichaId) : null;
    }

    /**
     * Guarda la valoración y redirige de vuelta a la pantalla del ciudadano.
     */
    public function guardar(): void
    {
        if (! $this->tipoFichaId) {
            $this->addError('tipoFichaId', 'Selecciona un tipo de ficha.');

            return;
        }

        // Construir el componente CiudadanoPage en memoria para reutilizar su lógica
        $page = new CiudadanoPage;
        $page->historia = $this->historia;
        $page->guardarValoracion($this->tipoFichaId, $this->respuestas, $this->entrevistaId);

        $this->redirect(route('intervencion.ciudadano.show', $this->historia->id));
    }

    public function render(): View
    {
        return view('intervencion::livewire.registrar-valoracion-page');
    }
}
