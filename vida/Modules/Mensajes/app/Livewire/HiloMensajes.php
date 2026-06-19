<?php

namespace Modules\Mensajes\Livewire;

use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Modules\Mensajes\Enums\VisibilidadMensaje;
use Modules\Mensajes\Models\Mensaje;
use Modules\Mensajes\Models\MensajeHilo;
use Modules\Mensajes\Services\MensajeriaService;

/**
 * Vista detalle de un hilo de mensajes.
 *
 * Muestra los mensajes en orden cronológico y permite:
 * - Responder al hilo
 * - Adjuntar archivos
 * - Registrar un mensaje en la Historia Social (solo si el usuario
 *   es TSR del ciudadano referenciado)
 */
class HiloMensajes extends Component
{
    use WithFileUploads;

    public int $hiloId;

    public string $respuesta = '';

    public array $adjuntos = [];

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

        $this->hiloId = $hiloId;

        // Marcar como leído al abrir
        $hilo = MensajeHilo::findOrFail($hiloId);
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
     * Envía una respuesta al hilo actual.
     *
     * @param MensajeriaService $mensajeriaService Servicio de mensajería.
     */
    public function enviarRespuesta(MensajeriaService $mensajeriaService): void
    {
        $this->validate([
            'respuesta' => 'required|string|max:10000',
            'adjuntos.*' => 'file|max:10240',
        ]);

        $mensajeriaService->responder(
            $this->hilo,
            auth()->user(),
            $this->respuesta,
            $this->adjuntos
        );

        $this->respuesta = '';
        $this->adjuntos = [];

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
