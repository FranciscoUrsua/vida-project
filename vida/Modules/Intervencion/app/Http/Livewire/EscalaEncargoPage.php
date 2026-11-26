<?php

namespace Modules\Intervencion\Http\Livewire;

use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Agenda\Services\Citas\AtencionCitaService;
use Modules\Escalas\Models\TipoEscala;
use Modules\Intervencion\Http\Livewire\Concerns\ConEncargoAutorizado;
use Modules\Intervencion\Services\Actuaciones\ContextoActuacion;
use Modules\Intervencion\Services\Actuaciones\PaseEscalaService;
use Modules\Intervencion\Services\CajaHerramientasEncargo;

/**
 * Pase de escala desde un encargo profesional (bloque D de
 * docs/instrucciones-cli/2026-11-encargo-actuaciones.md).
 *
 * Autoriza por el encargo (ConEncargoAutorizado y EA-4), no por la Historia Social. Solo
 * ofrece y admite las escalas del área del encargo (EA-2); una escala forjada fuera del área
 * no se muestra ni se guarda. El pase queda en la Historia vinculado al encargo, sin plan
 * (EA-5), y, con `?cita=`, completa la cita (EA-10). No muestra pases anteriores (EA-8).
 * Usa la vista de la escala desde la Historia.
 *
 * @property-read TipoEscala|null $tipoEscala
 * @property-read array<int, string> $escalasArea
 */
#[Layout('layouts.operativo')]
class EscalaEncargoPage extends Component
{
    use ConEncargoAutorizado;

    /** @var int|null ID del TipoEscala seleccionado */
    public ?int $tipoEscalaId = null;

    /** @var array<string, mixed> Respuestas del pase [item_id => valor] */
    public array $respuestas = [];

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

        $tipoEscala = request()->query('tipo_escala');
        $this->tipoEscalaId = is_numeric($tipoEscala) ? (int) $tipoEscala : null;
    }

    /**
     * Escalas del área del encargo, indexadas por id.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function escalasArea(): array
    {
        return app(CajaHerramientasEncargo::class)->tiposEscala($this->encargo)->pluck('nombre', 'id')->all();
    }

    /**
     * Escalas del selector de la vista: las del área.
     *
     * @return array<int, string>
     */
    public function escalasDisponibles(): array
    {
        return $this->escalasArea;
    }

    /**
     * Escala elegida, solo si es del área: una forjada no se pinta.
     *
     * @return TipoEscala|null
     */
    #[Computed]
    public function tipoEscala(): ?TipoEscala
    {
        if ($this->tipoEscalaId === null || ! array_key_exists($this->tipoEscalaId, $this->escalasArea)) {
            return null;
        }

        return TipoEscala::find($this->tipoEscalaId);
    }

    /**
     * Al cambiar de escala se descartan las respuestas de la anterior.
     *
     * @return void
     */
    public function updatedTipoEscalaId(): void
    {
        $this->respuestas = [];
        $this->resetErrorBag('tipoEscalaId');
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
     * Guarda el pase desde el encargo y vuelve a él. Quién actúa, la cita, RN-15 y el área los
     * comprueban también ContextoActuacion y PaseEscalaService.
     *
     * @return void
     */
    public function guardar(): void
    {
        if ($this->tipoEscalaId === null) {
            $this->addError('tipoEscalaId', 'Selecciona una escala.');

            return;
        }

        // En el servidor, no solo en el selector
        if ($this->tipoEscala === null) {
            $this->addError('tipoEscalaId', 'Esa escala no es del área del encargo.');

            return;
        }

        $citaId = $this->citaId !== null
            ? app(AtencionCitaService::class)->citaVinculable($this->persona->id, auth()->user(), $this->citaId)?->id
            : null;

        app(PaseEscalaService::class)->registrar(
            ContextoActuacion::desdeEncargo($this->encargo, auth()->user(), $citaId),
            $this->tipoEscala,
            $this->respuestas,
        );

        $this->redirect($this->volverUrl(), navigate: true);
    }

    /**
     * Renderiza la pantalla (la misma vista que la escala desde la Historia).
     *
     * @return View
     */
    public function render(): View
    {
        return view('intervencion::livewire.registrar-escala-page');
    }
}
