<?php

namespace Modules\Intervencion\Http\Livewire;

use App\Models\HistoriaSocial;
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
    /** @var HistoriaSocial */
    public HistoriaSocial $historia;

    /** @var int|null ID del TipoFicha seleccionado */
    public ?int $tipoFichaId = null;

    /** @var int|null Entrevista que origina esta valoración */
    public ?int $entrevistaId = null;

    /** @var array<string, mixed> Respuestas del formulario dinámico */
    public array $respuestas = [];

    /**
     * @param HistoriaSocial $historia
     * @param int|null $tipo_ficha
     * @param int|null $entrevista
     * @return void
     */
    public function mount(HistoriaSocial $historia, ?int $tipo_ficha = null, ?int $entrevista = null): void
    {
        $this->historia    = $historia;
        $this->tipoFichaId = $tipo_ficha;
        $this->entrevistaId = $entrevista;
    }

    /**
     * Devuelve el TipoFicha cargado si hay un ID seleccionado.
     *
     * @return TipoFicha|null
     */
    public function getTipoFichaProperty(): ?TipoFicha
    {
        return $this->tipoFichaId ? TipoFicha::find($this->tipoFichaId) : null;
    }

    /**
     * Guarda la valoración y redirige de vuelta a la pantalla del ciudadano.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function guardar(): \Illuminate\Http\RedirectResponse
    {
        if (! $this->tipoFichaId) {
            $this->addError('tipoFichaId', 'Selecciona un tipo de ficha.');
            return redirect()->back();
        }

        // Construir el componente CiudadanoPage en memoria para reutilizar su lógica
        $page = new CiudadanoPage();
        $page->historia = $this->historia;
        $page->guardarValoracion($this->tipoFichaId, $this->respuestas, $this->entrevistaId);

        return redirect()->route('intervencion.ciudadano.show', $this->historia->id);
    }

    /**
     * @return \Illuminate\View\View
     */
    public function render(): \Illuminate\View\View
    {
        return view('intervencion::livewire.registrar-valoracion-page');
    }
}
