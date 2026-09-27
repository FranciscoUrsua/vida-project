<?php

namespace Modules\Mensajes\Livewire;

use App\Models\HistoriaSocial;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Intervencion\Models\Ficha;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Mensajes\Enums\TipoAlerta;
use Modules\Mensajes\Enums\TipoContextoMensaje;
use Modules\Mensajes\Models\Alerta;
use Modules\Mensajes\Services\AlertaService;
use Modules\Mensajes\Services\ContextoMensajeService;
use Modules\Usuarios\Models\UsuarioRol;

/**
 * Toasts persistentes de las alertas pendientes del usuario, visibles en
 * cualquier pantalla del interfaz operativo (`modulo-mensajes.md` §4.2).
 *
 * Solo alertas: los avisos no generan toasts. No desaparecen solos; el
 * usuario reconoce la alerta (con confirmación) o minimiza el toast, que
 * vuelve a aparecer a los 30 minutos. La minimización vive en el navegador
 * (`sessionStorage`); el servidor solo decide qué alertas siguen pendientes.
 *
 * Las alertas nuevas se detectan por polling cada 60 segundos, el mismo
 * ciclo que los contadores del menú.
 *
 * @property-read Collection<int, Alerta> $alertas
 */
class AlertaToast extends Component
{
    /** Número máximo de toasts apilados; el resto se resume en una línea. */
    public const MAXIMO_VISIBLES = 3;

    /**
     * IDs de las alertas pendientes, en el orden de los toasts. El navegador
     * los usa para decidir cuáles mostrar según lo que se haya minimizado.
     *
     * @var list<int>
     */
    #[Locked]
    public array $alertaIds = [];

    /** Bandeja del interfaz en el que se montó el componente, si el usuario tiene acceso. */
    #[Locked]
    public ?string $urlBandeja = null;

    /** Alerta cuyo reconocimiento está pendiente de confirmar. */
    public ?int $alertaConfirmandoId = null;

    /**
     * Refresca los toasts cuando se reconoce una alerta desde otro componente
     * (la bandeja).
     *
     * @var array<string, string>
     */
    protected $listeners = ['alerta-reconocida' => '$refresh'];

    /**
     * Fija el enlace a la bandeja según el interfaz de la página (Supervisión
     * o Intervención). Se calcula aquí porque en las peticiones de polling
     * la ruta actual es la de Livewire.
     *
     * @return void
     */
    public function mount(): void
    {
        $usuario = auth()->user();

        if ($usuario === null) {
            return;
        }

        $enSupervision = request()->routeIs('supervision.*', 'agenda.supervisor.*', 'agenda.cuadrante');

        $this->urlBandeja = match (true) {
            $usuario->hasRole('supervision') && ($enSupervision || ! $usuario->hasRole('intervencion')) => route('supervision.bandeja', 'alertas'),
            $usuario->hasRole('intervencion') => route('intervencion.mensajes.index', 'alertas'),
            default => null,
        };
    }

    /**
     * Alertas que el usuario tiene pendientes, las de plazo más cercano primero.
     *
     * @return Collection<int, Alerta>
     */
    #[Computed]
    public function alertas(): Collection
    {
        if (! auth()->check()) {
            return new Collection();
        }

        return Alerta::pendientesPara(auth()->user())
            ->where('tipo', TipoAlerta::Alerta->value)
            ->orderBy('expira_en')
            ->get();
    }

    /**
     * Enlace al elemento que originó la alerta, solo si el usuario puede abrirlo.
     *
     * Planes, fichas e Historias Sociales se autorizan con
     * `ContextoMensajeService` (misma regla que los mensajes); las solicitudes
     * de rol llevan a Aprobaciones si el usuario supervisa.
     *
     * @param Alerta $alerta Alerta del toast.
     * @return string|null
     */
    public function enlaceOrigen(Alerta $alerta): ?string
    {
        $usuario = auth()->user();

        $contexto = match ($alerta->origen_type) {
            PlanDeIntervencion::class => TipoContextoMensaje::Plan,
            Ficha::class => TipoContextoMensaje::Ficha,
            HistoriaSocial::class => TipoContextoMensaje::Historia,
            default => null,
        };

        if ($contexto !== null) {
            $url = app(ContextoMensajeService::class)
                ->resolver($contexto->value, (int) $alerta->origen_id, $usuario)['url'] ?? '';

            return $url !== '' ? $url : null;
        }

        if ($alerta->origen_type === UsuarioRol::class && $usuario->hasRole('supervision')) {
            return route('supervision.aprobaciones');
        }

        return null;
    }

    /**
     * Pide confirmación antes de reconocer una alerta desde su toast.
     *
     * @param int $alertaId ID de la alerta.
     * @return void
     */
    public function confirmarReconocimiento(int $alertaId): void
    {
        $this->alertaConfirmandoId = $alertaId;
    }

    /**
     * Cancela el diálogo de confirmación.
     *
     * @return void
     */
    public function cancelarReconocimiento(): void
    {
        $this->alertaConfirmandoId = null;
    }

    /**
     * Reconoce la alerta confirmada y avisa al resto de componentes (menú,
     * bandeja) para que actualicen sus contadores.
     *
     * @param AlertaService $alertaService Servicio de ciclo de vida de alertas.
     * @return void
     *
     * @throws AuthorizationException Si el usuario no la tiene pendiente.
     */
    public function reconocer(AlertaService $alertaService): void
    {
        if (! $this->alertaConfirmandoId) {
            return;
        }

        $alerta = Alerta::pendientesPara(auth()->user())
            ->where('tipo', TipoAlerta::Alerta->value)
            ->find($this->alertaConfirmandoId);

        if (! $alerta) {
            throw new AuthorizationException('No estás autorizado para reconocer esta alerta.');
        }

        $alertaService->reconocer($alerta, auth()->user(), request()->ip() ?? '');

        $this->alertaConfirmandoId = null;
        unset($this->alertas);
        $this->dispatch('alerta-reconocida');
    }

    /**
     * Renderiza la pila de toasts.
     *
     * @return View
     */
    public function render(): View
    {
        $this->alertaIds = $this->alertas->pluck('id')->all();

        return view('mensajes::livewire.alerta-toast');
    }
}
