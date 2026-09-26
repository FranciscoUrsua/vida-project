<?php

namespace Modules\Mensajes\Livewire;

use App\Models\UnidadOrganizativa;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Modules\Mensajes\Services\AlertaService;

/**
 * Formulario del supervisor para enviar un aviso a todo su equipo.
 *
 * Solo para el rol de supervisión y solo a una UO en la que el supervisor
 * tenga adscripción vigente (`modulo-mensajes.md` §2.3). El aviso es
 * unidireccional: el equipo lo descarta, no lo responde.
 *
 * @property-read Collection<int, UnidadOrganizativa> $uosPropias
 */
class NuevoAvisoSupervisor extends Component
{
    /** UO destinataria; se elige entre las del supervisor. */
    public ?int $uoId = null;

    /** Título del aviso. */
    public string $titulo = '';

    /** Texto del aviso. */
    public string $cuerpo = '';

    /** Confirmación del último envío. */
    public ?string $confirmacion = null;

    /**
     * Exige el rol de supervisión y preselecciona la UO del supervisor.
     *
     * @return void
     */
    public function mount(): void
    {
        abort_unless(auth()->user()?->hasRole('supervision'), 403);

        $this->uoId = $this->uosPropias->first()?->id;
    }

    /**
     * UO en las que el supervisor tiene adscripción vigente.
     *
     * @return Collection<int, UnidadOrganizativa>
     */
    #[Computed]
    public function uosPropias(): Collection
    {
        $ids = auth()->user()->adscripcionesVigentes()->pluck('unidad_organizativa_id');

        return UnidadOrganizativa::whereIn('id', $ids)->orderBy('nombre')->get();
    }

    /**
     * Envía el aviso a todo el equipo de la UO elegida.
     *
     * @param AlertaService $alertaService Servicio de ciclo de vida de alertas.
     * @return void
     */
    public function enviar(AlertaService $alertaService): void
    {
        abort_unless(auth()->user()->hasRole('supervision'), 403);

        $this->validate([
            'uoId' => ['required', 'integer', Rule::in($this->uosPropias->pluck('id')->all())],
            'titulo' => ['required', 'string', 'max:255'],
            'cuerpo' => ['required', 'string', 'max:5000'],
        ], [
            'uoId.in' => 'Solo puedes enviar avisos al equipo de tu propia unidad.',
        ]);

        $aviso = $alertaService->crearAvisoSupervisor(
            auth()->user(),
            UnidadOrganizativa::findOrFail($this->uoId),
            $this->titulo,
            $this->cuerpo,
        );

        $total = $aviso->destinatarios()->count();
        $this->confirmacion = $total === 1
            ? 'Aviso enviado a 1 persona del equipo.'
            : "Aviso enviado a {$total} personas del equipo.";

        $this->reset(['titulo', 'cuerpo']);
        $this->dispatch('aviso-enviado');
    }

    /**
     * Renderiza el formulario.
     *
     * @return View
     */
    public function render(): View
    {
        return view('mensajes::livewire.nuevo-aviso-supervisor');
    }
}
