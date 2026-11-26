<?php

namespace Modules\Intervencion\Http\Livewire;

use App\Models\HistoriaSocial;
use App\Services\AccesoExpediente;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Intervencion\Http\Livewire\Concerns\MuestraFicha;
use Modules\Intervencion\Models\Ficha;

/**
 * Pantalla de solo lectura para visualizar una ficha de valoración completada.
 *
 * Renderiza los campos usando schema_snapshot (MuestraFicha), por lo que es coherente aunque
 * el TipoFicha haya evolucionado desde que se guardó la ficha.
 *
 * @property Ficha $ficha
 * @property HistoriaSocial $historia
 */
#[Layout('layouts.operativo')]
class VerFichaPage extends Component
{
    use MuestraFicha;

    /** @var Ficha Ficha que se visualiza */
    public Ficha $ficha;

    /** @var int ID de la Historia Social, para construir el enlace de vuelta */
    public int $historiaId;

    /**
     * Inicializa la página con la historia y la ficha solicitadas.
     *
     * Una ficha que no es de la historia de la ruta responde 404, sin datos: la
     * autorización es de la historia, así que no basta con conocer el id de la ficha.
     *
     * @param HistoriaSocial $historia Historia Social propietaria de la ficha.
     * @param Ficha $ficha Ficha que se desea visualizar.
     *
     * @return void
     */
    public function mount(HistoriaSocial $historia, Ficha $ficha): void
    {
        // El binding de {historia} carga sin scope: policy y auditoría antes de leer nada
        $this->historiaId = app(AccesoExpediente::class)->historia(auth()->user(), $historia->id)->id;

        abort_unless($ficha->perteneceAHistoria($this->historiaId), 404);

        $this->ficha = $ficha;
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
     * URL de vuelta: la Historia Social.
     *
     * @return string
     */
    public function volverUrl(): string
    {
        return route('intervencion.ciudadano.show', $this->historiaId);
    }

    /**
     * Texto del enlace de vuelta.
     *
     * @return string
     */
    public function volverTexto(): string
    {
        return 'Volver a la Historia Social';
    }

    /**
     * Desde la Historia se puede escribir un mensaje sobre la ficha.
     *
     * @return bool
     */
    public function conMensaje(): bool
    {
        return true;
    }

    /**
     * Renderiza la vista de solo lectura de la ficha.
     *
     * @return View
     */
    public function render(): View
    {
        return view('intervencion::livewire.ver-ficha-page');
    }
}
