<?php

namespace Modules\Supervision\Http\Livewire;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use LogicException;
use Modules\Centro\Services\Asignacion\CentroDeUsuario;
use Modules\Intervencion\Enums\EstadoRepartoCasos;
use Modules\Intervencion\Models\RepartoCasos;
use Modules\Intervencion\Models\RepartoCasosLinea;
use Modules\Intervencion\Services\Asignacion\RepartoCasosService;

/**
 * Revisión de un reparto por salida de un profesional (docs/modulo-asignacion.md §5).
 *
 * Muestra la propuesta agrupada por profesional de destino, con los casos con
 * actividad y los dormidos por separado. El supervisor puede cambiar el destino
 * de cada caso y, al final, confirmar o descartar. Hasta la confirmación no
 * cambia ninguna asignación (RN-08). Solo la supervisión del centro del
 * reparto puede abrirlo: los de otro centro reciben 404.
 *
 * @property-read RepartoCasos $reparto
 * @property-read Collection<int, array{destino: User, lineas: Collection<int, RepartoCasosLinea>, con_actividad: int, dormidos: int}> $porDestino
 * @property-read Collection<int, User> $destinosPosibles
 * @property int $repartoId
 * @property string|null $aviso
 */
#[Layout('layouts.supervision')]
class RepartoCasosPage extends Component
{
    /** @var int Reparto que se revisa. */
    public int $repartoId;

    /** @var string|null Resultado de la última acción. */
    public ?string $aviso = null;

    /**
     * Comprueba que el reparto es del centro que supervisa el usuario.
     *
     * @param RepartoCasos $reparto
     * @return void
     */
    public function mount(RepartoCasos $reparto): void
    {
        $this->repartoId = $reparto->id;
        $this->reparto;
    }

    /**
     * Reparto, cargado siempre con el alcance del centro del supervisor.
     *
     * @return RepartoCasos
     */
    #[Computed]
    public function reparto(): RepartoCasos
    {
        $reparto = RepartoCasos::with(['centro', 'profesionalOrigen.profesional', 'iniciadoPor.profesional'])->findOrFail($this->repartoId);

        abort_unless(app(CentroDeUsuario::class)->supervisa(auth()->user(), $reparto->centro), 404);

        return $reparto;
    }

    /**
     * Casos agrupados por destino; primero los que tienen actividad.
     *
     * @return Collection<int, array{destino: User, lineas: Collection<int, RepartoCasosLinea>, con_actividad: int, dormidos: int}>
     */
    #[Computed]
    public function porDestino(): Collection
    {
        return $this->reparto->lineas()
            ->with(['historia.ciudadano', 'profesionalDestino.profesional'])
            ->orderByDesc('con_actividad')
            ->orderBy('id')
            ->get()
            ->groupBy('profesional_destino_id')
            ->map(fn (Collection $lineas) => [
                'destino' => $lineas->first()->profesionalDestino,
                'lineas' => $lineas,
                'con_actividad' => $lineas->where('con_actividad', true)->count(),
                'dormidos' => $lineas->where('con_actividad', false)->count(),
            ])
            ->sortBy(fn (array $grupo) => $grupo['destino']->nombre_completo)
            ->values()
            ->toBase();
    }

    /**
     * Profesionales a los que se puede mover un caso.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function destinosPosibles(): Collection
    {
        return app(RepartoCasosService::class)->destinosPosibles($this->reparto);
    }

    /**
     * Si el reparto aún se puede cambiar, confirmar o descartar.
     *
     * @return bool
     */
    public function esPropuesto(): bool
    {
        return $this->reparto->estado === EstadoRepartoCasos::Propuesto;
    }

    /**
     * Cambia el destino de un caso de la propuesta.
     *
     * @param int $lineaId
     * @param int|string $destinoId
     * @return void
     */
    public function cambiarDestino(int $lineaId, int|string $destinoId): void
    {
        $linea = $this->reparto->lineas()->findOrFail($lineaId);
        $destino = $this->destinosPosibles->firstWhere('id', (int) $destinoId);

        if ($destino === null) {
            $this->addError('reparto', 'Ese profesional no está en el reparto del centro.');

            return;
        }

        $this->ejecutar(fn (RepartoCasosService $s) => $s->modificarLinea($linea, $destino, auth()->user()));
    }

    /**
     * Aplica el reparto: cierra las referencias del profesional de origen y crea las nuevas.
     *
     * @return void
     */
    public function confirmar(): void
    {
        if ($this->ejecutar(fn (RepartoCasosService $s) => $s->confirmar($this->reparto, auth()->user()))) {
            $this->aviso = 'Reparto confirmado. Cada profesional de destino ha recibido un aviso con sus casos.';
        }
    }

    /**
     * Descarta la propuesta sin cambiar ninguna asignación.
     *
     * @return void
     */
    public function descartar(): void
    {
        if ($this->ejecutar(fn (RepartoCasosService $s) => $s->descartar($this->reparto, auth()->user()))) {
            $this->aviso = 'Propuesta descartada. Ninguna asignación ha cambiado.';
        }
    }

    /**
     * @return View
     */
    public function render(): View
    {
        return view('supervision::livewire.reparto-casos-page');
    }

    /**
     * Ejecuta una acción del servicio, muestra su error si lo hay y refresca los datos.
     *
     * @param callable(RepartoCasosService): mixed $accion
     * @return bool Si se aplicó.
     */
    private function ejecutar(callable $accion): bool
    {
        $this->resetErrorBag();

        try {
            $accion(app(RepartoCasosService::class));
        } catch (AuthorizationException|LogicException $e) {
            $this->addError('reparto', $e->getMessage());

            return false;
        } finally {
            unset($this->reparto, $this->porDestino);
        }

        return true;
    }
}
