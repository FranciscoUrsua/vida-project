<?php

namespace Modules\Intervencion\Http\Livewire;

use App\Models\HistoriaSocial;
use App\Services\AccesoExpediente;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Agenda\Services\Citas\AtencionCitaService;
use Modules\Centro\Services\Asignacion\CentroDeUsuario;
use Modules\Escalas\Models\TipoEscala;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Intervencion\Services\Actuaciones\ContextoActuacion;
use Modules\Intervencion\Services\Actuaciones\PaseEscalaService;

/**
 * Pantalla completa para aplicar un instrumento de escala.
 *
 * Carga el schema del TipoEscala y presenta las secciones con sus ítems.
 * Al guardar, delega en PaseEscalaService, como la herramienta de la Historia Social.
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

    /**
     * Inicializa la pantalla con la historia y el tipo de escala opcional.
     *
     * @param HistoriaSocial $historia Historia social del ciudadano.
     */
    public function mount(HistoriaSocial $historia): void
    {
        // El binding de {historia} carga sin scope: policy y auditoría antes de nada (como la valoración)
        $this->historia = app(AccesoExpediente::class)->historia(auth()->user(), $historia->id);

        // R1: un pase de escala es una actuación; sin centro no se abre el formulario
        app(CentroDeUsuario::class)->exigirActuacionAlAbrir(auth()->user());

        // Livewire 4 full-page components no reciben query string en mount(); se lee directamente.
        $tipoEscala = request()->query('tipo_escala');
        $this->tipoEscalaId = $tipoEscala ? (int) $tipoEscala : null;
    }

    /**
     * Devuelve el instrumento de escala seleccionado.
     */
    public function getTipoEscalaProperty(): ?TipoEscala
    {
        return $this->tipoEscalaId ? TipoEscala::find($this->tipoEscalaId) : null;
    }

    /**
     * Guarda el pase de escala y redirige de vuelta a la pantalla del ciudadano.
     *
     * @return void
     */
    public function guardar(): void
    {
        if (! $this->tipoEscalaId) {
            $this->addError('tipoEscalaId', 'Selecciona un instrumento.');

            return;
        }

        // Como desde la Historia: el plan en curso y la cita de hoy con este profesional, si la hay
        $usuario = auth()->user();
        $plan = PlanDeIntervencion::enCursoParaApuntes($this->historia->id, $usuario->id);
        $cita = app(AtencionCitaService::class)->citaVinculable($this->historia->ciudadano_id, $usuario);

        app(PaseEscalaService::class)->registrar(
            ContextoActuacion::desdeHistoria($this->historia, $usuario, $plan?->id, $cita?->id),
            TipoEscala::findOrFail($this->tipoEscalaId),
            $this->respuestas,
        );

        $this->redirect(route('intervencion.ciudadano.show', $this->historia->id));
    }

    /**
     * Renderiza la pantalla de registro de escala.
     */
    public function render(): View
    {
        return view('intervencion::livewire.registrar-escala-page');
    }
}
