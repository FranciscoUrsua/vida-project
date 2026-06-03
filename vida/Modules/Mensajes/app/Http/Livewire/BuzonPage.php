<?php

namespace Modules\Mensajes\Http\Livewire;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Mensajes\Enums\DestinatarioType;
use Modules\Mensajes\Enums\EstadoAlerta;
use Modules\Mensajes\Enums\TipoAlerta;
use Modules\Mensajes\Models\Alerta;
use Modules\Mensajes\Models\MensajeParticipante;
use Modules\Mensajes\Services\MensajeriaService;

/**
 * Buzón unificado de alertas, avisos y mensajes del interfaz operativo.
 *
 * Tres pestañas:
 *   - Alertas: notificaciones que requieren reconocimiento explícito.
 *   - Avisos: informativos sin plazo de reconocimiento.
 *   - Mensajes: hilos de conversación con otros profesionales.
 *
 * @see docs/instrucciones-cli/ui-intervencion-entrega2.md §5
 */
#[Layout('layouts.operativo')]
class BuzonPage extends Component
{
    /** @var string Pestaña activa: 'alertas' | 'avisos' | 'mensajes' */
    public string $pestana = 'alertas';

    /** @var int|null ID del ítem seleccionado en la lista activa */
    public ?int $itemSeleccionado = null;

    /** @var string Texto del mensaje de respuesta en curso */
    public string $respuesta = '';

    /** @var bool Modal de nuevo mensaje visible */
    public bool $modalNuevoMensaje = false;

    // -------------------------------------------------------------------------
    // Propiedades computadas
    // -------------------------------------------------------------------------

    /**
     * Alertas (tipo alerta) directas al usuario en estado pendiente.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Alerta>
     */
    #[Computed]
    public function alertas(): \Illuminate\Database\Eloquent\Collection
    {
        return Alerta::where('tipo', TipoAlerta::Alerta)
            ->where('estado', EstadoAlerta::Pendiente)
            ->where('destinatario_type', DestinatarioType::Usuario)
            ->where('destinatario_usuario_id', Auth::id())
            ->orderBy('expira_en')
            ->get();
    }

    /**
     * Avisos (tipo aviso) directos al usuario en estado pendiente.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Alerta>
     */
    #[Computed]
    public function avisos(): \Illuminate\Database\Eloquent\Collection
    {
        return Alerta::where('tipo', TipoAlerta::Aviso)
            ->where('estado', EstadoAlerta::Pendiente)
            ->where('destinatario_type', DestinatarioType::Usuario)
            ->where('destinatario_usuario_id', Auth::id())
            ->latest()
            ->get();
    }

    /**
     * Hilos de mensajes activos en los que participa el usuario.
     *
     * @return Collection<int, MensajeParticipante>
     */
    #[Computed]
    public function hilos(): Collection
    {
        return MensajeParticipante::where('usuario_id', Auth::id())
            ->whereNull('archivado_en')
            ->with(['hilo.ultimoMensaje', 'hilo.participantes.usuario'])
            ->get()
            ->sortByDesc(fn (MensajeParticipante $p) => $p->hilo->ultimoMensaje?->created_at)
            ->values();
    }

    /**
     * Alerta seleccionada actualmente en la pestaña Alertas/Avisos.
     */
    #[Computed]
    public function alertaSeleccionada(): ?Alerta
    {
        if ($this->itemSeleccionado === null || $this->pestana === 'mensajes') {
            return null;
        }

        return Alerta::where('destinatario_usuario_id', Auth::id())
            ->find($this->itemSeleccionado);
    }

    /**
     * Participación seleccionada en la pestaña Mensajes.
     */
    #[Computed]
    public function hiloSeleccionado(): ?MensajeParticipante
    {
        if ($this->itemSeleccionado === null || $this->pestana !== 'mensajes') {
            return null;
        }

        return MensajeParticipante::where('usuario_id', Auth::id())
            ->with(['hilo.mensajes.usuario'])
            ->find($this->itemSeleccionado);
    }

    // -------------------------------------------------------------------------
    // Acciones
    // -------------------------------------------------------------------------

    /**
     * Cambia la pestaña activa y deselecciona el ítem actual.
     */
    public function cambiarPestana(string $pestana): void
    {
        $this->pestana = $pestana;
        $this->itemSeleccionado = null;
        $this->respuesta = '';
    }

    /**
     * Selecciona un ítem de la lista.
     */
    public function seleccionar(int $id): void
    {
        $this->itemSeleccionado = $id;
        $this->respuesta = '';
    }

    /**
     * Reconoce una alerta del usuario autenticado.
     * Actualiza su estado a 'reconocida' y la retira del listado.
     */
    public function reconocerAlerta(int $alertaId): void
    {
        $alerta = Alerta::where('destinatario_usuario_id', Auth::id())
            ->where('destinatario_type', DestinatarioType::Usuario)
            ->findOrFail($alertaId);

        $alerta->update(['estado' => EstadoAlerta::Reconocida]);

        $this->itemSeleccionado = null;
        unset($this->alertas);

        $this->dispatch('alerta-reconocida');
    }

    /**
     * Envía una respuesta al hilo seleccionado.
     */
    public function enviarRespuesta(int $hiloId, MensajeriaService $mensajeriaService): void
    {
        if (strlen(trim($this->respuesta)) === 0) {
            return;
        }

        $participacion = MensajeParticipante::where('usuario_id', Auth::id())
            ->where('hilo_id', $hiloId)
            ->firstOrFail();

        $mensajeriaService->responder($participacion->hilo, Auth::user(), $this->respuesta);

        $this->respuesta = '';
        unset($this->hilos, $this->hiloSeleccionado);
    }

    public function render(): View
    {
        return view('mensajes::livewire.buzon-page');
    }
}
