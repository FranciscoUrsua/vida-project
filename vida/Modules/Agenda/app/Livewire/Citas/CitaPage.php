<?php

namespace Modules\Agenda\Livewire\Citas;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Component;
use LogicException;
use Modules\Agenda\Enums\EstadoCita;
use Modules\Agenda\Enums\PedidoPor;
use Modules\Agenda\Livewire\Citas\Concerns\ConLayoutDeCitas;
use Modules\Agenda\Models\Cita;
use Modules\Agenda\Models\CitaEvento;
use Modules\Agenda\Models\Slot;
use Modules\Agenda\Services\Citas\BusquedaHuecosService;
use Modules\Agenda\Services\Citas\CitacionService;
use Modules\Agenda\Services\Citas\PropuestaHueco;

/**
 * Detalle e historial de una cita (docs/modulo-citas.md §2.4 y §3.4).
 *
 * Muestra la cita, su solicitud, la cadena de reprogramaciones y el historial
 * inmutable de eventos. A quien gestiona las citas del centro le ofrece
 * reprogramar y cancelar, siempre a través de CitacionService, que aplica la
 * regla de citas propias (RN-05): el profesional de la cita no ve esas acciones.
 * El motivo de la solicitud y el nombre interno del tipo solo los ve quien
 * accede a la Historia Social.
 *
 * @property-read Collection<int, Cita> $cadena
 * @property-read Collection<int, CitaEvento> $eventos
 * @property-read bool $puedeReprogramar
 * @property-read bool $puedeCancelar
 */
class CitaPage extends Component
{
    use ConLayoutDeCitas;

    /** @var Cita Cita mostrada. */
    public Cita $cita;

    /** @var string|null Acción abierta: 'reprogramar' o 'cancelar'. */
    public ?string $accion = null;

    /** @var array{alcance: string, hasta: string, pedido_por: string, motivo: string} Formulario de reprogramación. */
    public array $formReprogramar = ['alcance' => 'mismo', 'hasta' => '', 'pedido_por' => 'ciudadano', 'motivo' => ''];

    /** @var array{pedido_por: string, motivo: string, abrir_solicitud: bool} Formulario de cancelación. */
    public array $formCancelar = ['pedido_por' => 'ciudadano', 'motivo' => '', 'abrir_solicitud' => false];

    /** @var list<array{slot_id: int, fecha: string, hora: string, profesional: string, modo: string}>|null Huecos propuestos para reprogramar. */
    public ?array $propuestas = null;

    /** @var string|null Resultado de la última acción, para el aviso. */
    public ?string $aviso = null;

    /**
     * Carga la cita (la ruta ya ha comprobado `view`).
     *
     * @param Cita $cita
     * @return void
     */
    public function mount(Cita $cita): void
    {
        $this->cita = $cita;
        $this->aviso = session('aviso-cita');
    }

    /**
     * Citas de la cadena de reprogramaciones, de la primera a la vigente.
     *
     * @return Collection<int, Cita>
     */
    #[Computed]
    public function cadena(): Collection
    {
        $cadena = $this->cita->cadenaReprogramaciones();
        $actual = $cadena->last();

        while (($siguiente = $actual->reprogramacion()->first()) !== null) {
            $cadena->push($siguiente);
            $actual = $siguiente;
        }

        return $cadena->each->loadMissing(['profesional.profesional']);
    }

    /**
     * Historial de la cadena y de su solicitud, en orden cronológico.
     *
     * @return Collection<int, CitaEvento>
     */
    #[Computed]
    public function eventos(): Collection
    {
        return CitaEvento::with('actor.profesional')
            ->where(function ($q) {
                $q->whereIn('cita_id', $this->cadena->pluck('id'));

                if ($this->cita->solicitud_cita_id !== null) {
                    $q->orWhere('solicitud_cita_id', $this->cita->solicitud_cita_id);
                }
            })
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->unique('id')
            ->values();
    }

    /**
     * Si el usuario puede reprogramar esta cita ahora (política y estado).
     *
     * @return bool
     */
    #[Computed]
    public function puedeReprogramar(): bool
    {
        return $this->cita->estado === EstadoCita::Confirmada && Auth::user()->can('reprogramar', $this->cita);
    }

    /**
     * Si el usuario puede cancelar esta cita ahora (política y estado).
     *
     * @return bool
     */
    #[Computed]
    public function puedeCancelar(): bool
    {
        return $this->cita->estado === EstadoCita::Confirmada && Auth::user()->can('cancelar', $this->cita);
    }

    /**
     * Abre o cierra el formulario de una acción.
     *
     * @param string|null $accion 'reprogramar', 'cancelar' o null.
     * @return void
     */
    public function abrir(?string $accion): void
    {
        $this->accion = in_array($accion, ['reprogramar', 'cancelar'], true) ? $accion : null;
        $this->propuestas = null;
        $this->resetErrorBag();
    }

    /**
     * Busca huecos para mover la cita (el sistema propone; la persona elige).
     *
     * @return void
     *
     * @throws AuthorizationException
     */
    public function buscarHuecos(): void
    {
        $this->authorize('reprogramar', $this->cita);
        $this->validate(['formReprogramar.hasta' => ['nullable', 'date', 'after_or_equal:today']], [], ['formReprogramar.hasta' => 'hasta']);

        $hasta = filled($this->formReprogramar['hasta']) ? Carbon::parse($this->formReprogramar['hasta']) : null;

        $this->propuestas = app(BusquedaHuecosService::class)
            ->paraReprogramar($this->cita, $this->formReprogramar['alcance'] === 'mismo', $hasta)
            ->map(fn (PropuestaHueco $p) => [
                'slot_id' => $p->slot->id,
                'fecha' => $p->slot->fecha->locale('es')->isoFormat('ddd D MMM YYYY'),
                'hora' => substr((string) $p->slot->hora_inicio, 0, 5),
                'profesional' => $p->profesional->nombre_completo,
                'modo' => $p->modo->label(),
            ])
            ->all();
    }

    /**
     * Mueve la cita al hueco elegido. La original queda reprogramada y se pasa a
     * mostrar la nueva.
     *
     * @param int $slotId
     * @return void
     */
    public function reprogramar(int $slotId): void
    {
        $this->validate([
            'formReprogramar.pedido_por' => ['required', 'in:ciudadano,centro,profesional'],
            'formReprogramar.motivo' => ['required', 'string', 'max:1000'],
        ], ['formReprogramar.motivo.required' => 'Indica el motivo del cambio.']);

        try {
            $nueva = app(CitacionService::class)->reprogramar(
                $this->cita,
                Slot::findOrFail($slotId),
                PedidoPor::from($this->formReprogramar['pedido_por']),
                $this->formReprogramar['motivo'],
                Auth::user(),
            );
        } catch (LogicException|InvalidArgumentException $e) {
            $this->addError('reprogramar', $e->getMessage());

            return;
        }

        $this->redirectRoute('agenda.citas.show', $nueva, navigate: true);
    }

    /**
     * Cancela la cita y, si se ha marcado, abre una solicitud nueva enlazada.
     *
     * @return void
     */
    public function cancelar(): void
    {
        $this->validate([
            'formCancelar.pedido_por' => ['required', 'in:ciudadano,centro,profesional'],
            'formCancelar.motivo' => ['required', 'string', 'max:1000'],
        ], ['formCancelar.motivo.required' => 'Indica el motivo de la cancelación.']);

        try {
            $solicitud = app(CitacionService::class)->cancelar(
                $this->cita,
                PedidoPor::from($this->formCancelar['pedido_por']),
                $this->formCancelar['motivo'],
                Auth::user(),
                (bool) $this->formCancelar['abrir_solicitud'],
            );
        } catch (LogicException|InvalidArgumentException|ValidationException $e) {
            $this->addError('cancelar', $e->getMessage());

            return;
        }

        $this->cita->refresh();
        $this->accion = null;
        $this->aviso = $solicitud !== null
            ? 'Cita cancelada. Se ha abierto una solicitud nueva en la bandeja de citación.'
            : 'Cita cancelada.';
        unset($this->eventos, $this->cadena, $this->puedeReprogramar, $this->puedeCancelar);
    }

    /**
     * Pinta la página.
     *
     * @return View
     */
    public function render(): View
    {
        return $this->vistaConLayout('agenda::livewire.citas.cita-page');
    }
}
