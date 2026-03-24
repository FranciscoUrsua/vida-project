<?php

namespace Modules\Mensajes\Livewire;

use Livewire\Attributes\Computed;
use Livewire\Component;
use Modules\Mensajes\Models\MensajeHilo;
use Modules\Mensajes\Models\MensajeParticipante;
use Modules\Mensajes\Services\MensajeriaService;

/**
 * Bandeja de mensajes del profesional autenticado.
 *
 * Muestra la lista de hilos activos (no archivados) con indicador
 * de mensajes no leídos. Al seleccionar un hilo carga HiloMensajes.
 */
class BandejaMensajes extends Component
{
    public ?int $hiloActivoId = null;
    public bool $mostrarNuevoMensaje = false;

    public function mount(): void
    {
        abort_unless(auth()->check(), 401);
    }

    #[Computed]
    public function hilos(): \Illuminate\Support\Collection
    {
        return MensajeParticipante::where('usuario_id', auth()->id())
            ->whereNull('archivado_en')
            ->with([
                'hilo.ultimoMensaje',
                'hilo.participantes.usuario',
            ])
            ->get()
            ->sortByDesc(fn (MensajeParticipante $p) => $p->hilo->ultimoMensaje?->created_at)
            ->values();
    }

    public function abrirHilo(int $hiloId): void
    {
        $this->hiloActivoId = $hiloId;
        $this->mostrarNuevoMensaje = false;
    }

    public function archivarHilo(int $hiloId, MensajeriaService $mensajeriaService): void
    {
        MensajeParticipante::where('hilo_id', $hiloId)
            ->where('usuario_id', auth()->id())
            ->update(['archivado_en' => now()]);

        if ($this->hiloActivoId === $hiloId) {
            $this->hiloActivoId = null;
        }

        unset($this->hilos);
    }

    public function nuevaMensaje(): void
    {
        $this->mostrarNuevoMensaje = true;
        $this->hiloActivoId = null;
    }

    public function render(): \Illuminate\View\View
    {
        return view('mensajes::livewire.bandeja-mensajes');
    }
}
