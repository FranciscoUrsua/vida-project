<?php

namespace Modules\Mensajes\Livewire;

use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
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

    /**
     * Verifica que exista sesión autenticada antes de mostrar la bandeja.
     *
     * @return void
     */
    public function mount(): void
    {
        abort_unless(auth()->check(), 401);
    }

    #[Computed]
    /**
     * Hilos activos del usuario autenticado.
     *
     * @return Collection<int, MensajeParticipante>
     */
    public function hilos(): Collection
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

    /**
     * Abre un hilo de mensajes en la bandeja.
     *
     * @param int $hiloId ID del hilo.
     *
     * @return void
     */
    public function abrirHilo(int $hiloId): void
    {
        $this->hiloActivoId = $hiloId;
        $this->mostrarNuevoMensaje = false;
    }

    /**
     * Archiva el hilo seleccionado para el usuario actual.
     *
     * @param int $hiloId ID del hilo.
     * @param MensajeriaService $mensajeriaService Servicio de mensajería.
     *
     * @return void
     */
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

    /**
     * Abre el formulario de creación de un nuevo mensaje.
     *
     * @return void
     */
    public function nuevaMensaje(): void
    {
        $this->mostrarNuevoMensaje = true;
        $this->hiloActivoId = null;
    }

    /**
     * Renderiza la bandeja de mensajes.
     *
     * @return View
     */
    public function render(): View
    {
        return view('mensajes::livewire.bandeja-mensajes');
    }
}
