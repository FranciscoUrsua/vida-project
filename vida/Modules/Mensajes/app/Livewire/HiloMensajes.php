<?php

namespace Modules\Mensajes\Livewire;

use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Mensajes\Enums\TipoContextoMensaje;
use Modules\Mensajes\Enums\VisibilidadMensaje;
use Modules\Mensajes\Models\Mensaje;
use Modules\Mensajes\Models\MensajeHilo;
use Modules\Mensajes\Services\ContextoMensajeService;
use Modules\Mensajes\Services\MensajeriaService;

/**
 * Vista detalle de un hilo de mensajes.
 *
 * Muestra los mensajes en orden cronológico y permite:
 * - Responder al hilo
 * - Registrar un mensaje en la Historia Social (solo si el usuario
 *   es TSR del ciudadano referenciado)
 */
class HiloMensajes extends Component
{
    /** Hilo abierto. Bloqueado: si el navegador pudiera cambiarlo, leería hilos ajenos. */
    #[Locked]
    public int $hiloId;

    public string $respuesta = '';

    // Estado del modal "Registrar en Historia Social"
    public bool $mostrarModalHistoria = false;

    public ?int $mensajeParaHistoriaId = null;

    public ?int $ciudadanoSeleccionadoId = null;

    public string $cuerpoEditado = '';

    public string $visibilidadSeleccionada = 'profesionales';

    /**
     * Inicializa la vista del hilo y marca sus mensajes como leídos.
     *
     * @param int $hiloId ID del hilo.
     */
    public function mount(int $hiloId): void
    {
        abort_unless(auth()->check(), 401);

        $hilo = MensajeHilo::findOrFail($hiloId);

        // Solo los participantes pueden abrir el hilo.
        abort_unless($hilo->tieneParticipante(auth()->id()), 403);

        $this->hiloId = $hiloId;

        // Marcar como leído al abrir
        app(MensajeriaService::class)->marcarComoLeido($hilo, auth()->user());
    }

    #[Computed]
    /**
     * Hilo de mensajes cargado con sus relaciones.
     */
    public function hilo(): MensajeHilo
    {
        return MensajeHilo::with(['mensajes.remitente', 'mensajes.referenciasCiudadano.ciudadano'])
            ->findOrFail($this->hiloId);
    }

    /**
     * Elemento vinculado al hilo, para la cabecera. Solo lleva enlace si quien
     * lee puede ver el elemento; si no, solo la etiqueta (sin datos personales).
     *
     * @return array{etiqueta: string, url: string|null}|null
     */
    #[Computed]
    public function contexto(): ?array
    {
        $hilo = $this->hilo;
        $tipo = TipoContextoMensaje::tryFrom((string) $hilo->contexto_tipo);

        if ($tipo === null || $hilo->contexto_id === null) {
            return null;
        }

        $resuelto = app(ContextoMensajeService::class)->resolver($tipo->value, $hilo->contexto_id, auth()->user());

        return [
            'etiqueta' => $tipo->etiqueta().' #'.$hilo->contexto_id,
            'url' => $resuelto['url'] ?? null,
        ];
    }

    /**
     * Envía una respuesta al hilo actual.
     *
     * @param MensajeriaService $mensajeriaService Servicio de mensajería.
     */
    public function enviarRespuesta(MensajeriaService $mensajeriaService): void
    {
        $this->validate([
            'respuesta' => 'required|string|max:10000',
        ]);

        $mensajeriaService->responder(
            $this->hilo,
            auth()->user(),
            $this->respuesta
        );

        $this->respuesta = '';

        unset($this->hilo);
    }

    /**
     * Abre el modal para registrar un mensaje en la Historia Social.
     */
    public function abrirModalHistoria(int $mensajeId, int $ciudadanoId): void
    {
        $mensaje = Mensaje::findOrFail($mensajeId);

        $this->mensajeParaHistoriaId = $mensajeId;
        $this->ciudadanoSeleccionadoId = $ciudadanoId;
        $this->cuerpoEditado = $mensaje->cuerpo;
        $this->visibilidadSeleccionada = 'profesionales';
        $this->mostrarModalHistoria = true;
    }

    /**
     * Cierra el modal de registro en la Historia Social.
     */
    public function cerrarModalHistoria(): void
    {
        $this->mostrarModalHistoria = false;
        $this->mensajeParaHistoriaId = null;
        $this->ciudadanoSeleccionadoId = null;
        $this->cuerpoEditado = '';
    }

    /**
     * Confirma el registro del mensaje en la Historia Social.
     */
    public function confirmarRegistroHistoria(MensajeriaService $mensajeriaService): void
    {
        $this->validate([
            'cuerpoEditado' => 'required|string|max:10000',
            'visibilidadSeleccionada' => 'required|in:privada,profesionales',
        ]);

        $mensaje = Mensaje::findOrFail($this->mensajeParaHistoriaId);
        $ciudadano = Ciudadano::findOrFail($this->ciudadanoSeleccionadoId);

        $mensajeriaService->registrarEnHistoria(
            $mensaje,
            $ciudadano,
            auth()->user(),
            $this->cuerpoEditado,
            $this->visibilidadSeleccionada
        );

        $this->cerrarModalHistoria();

        $this->dispatch('registro-historia-creado');
    }

    /**
     * Comprueba si el usuario autenticado es TSR responsable del ciudadano.
     *
     * Se entiende como TSR el profesional cuya UO tiene asignada la Historia Social
     * del ciudadano y el profesional tiene adscripción activa a esa UO.
     */
    public function esTsrDeCiudadano(int $ciudadanoId): bool
    {
        return HistoriaSocial::where('ciudadano_id', $ciudadanoId)
            ->whereHas('unidadOrganizativa', function ($query) {
                $query->whereHas('usuarios', function ($q) {
                    $q->where('usuario_id', auth()->id());
                });
            })
            ->exists();
    }

    /**
     * Opciones de visibilidad disponibles.
     *
     * @return array<string, string>
     */
    public function opcionesVisibilidad(): array
    {
        return [
            VisibilidadMensaje::Profesionales->value => 'Profesionales',
            VisibilidadMensaje::Privada->value => 'Privada (solo yo)',
        ];
    }

    /**
     * Renderiza la vista del hilo de mensajes.
     */
    public function render(): View
    {
        return view('mensajes::livewire.hilo-mensajes');
    }
}
