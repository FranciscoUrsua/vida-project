<?php

namespace Modules\Mensajes\Livewire;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Mensajes\Enums\TipoAlerta;
use Modules\Mensajes\Models\Alerta;
use Modules\Mensajes\Services\AlertaService;

/**
 * Pestaña de alertas o de avisos de la bandeja del profesional.
 *
 * - Alertas: ordenadas por vencimiento; se reconocen con confirmación
 *   (acción deliberada, `modulo-mensajes.md` §2.1).
 * - Avisos: los más recientes primero; se descartan sin confirmación. Los
 *   avisos manuales del supervisor llevan su etiqueta y no admiten respuesta.
 *
 * Solo lista lo que el usuario tiene pendiente (`Alerta::pendientesPara`).
 */
class BandejaAlertas extends Component
{
    /** Valor de `origen_type` de los avisos creados a mano por un supervisor. */
    public const ORIGEN_SUPERVISOR = 'supervisor_manual';

    /** Tipo que muestra la pestaña: 'alerta' o 'aviso'. */
    #[Locked]
    public string $tipo = 'alerta';

    /** Alerta cuyo reconocimiento está pendiente de confirmar. */
    public ?int $alertaConfirmandoId = null;

    /**
     * Comprueba la sesión y el tipo de la pestaña.
     *
     * @param string $tipo Tipo que muestra la pestaña.
     * @return void
     */
    public function mount(string $tipo = 'alerta'): void
    {
        abort_unless(auth()->check(), 401);
        abort_unless(TipoAlerta::tryFrom($tipo) !== null, 404);

        $this->tipo = $tipo;
    }

    /**
     * Alertas o avisos que el usuario tiene pendientes.
     *
     * @return Collection<int, Alerta>
     */
    #[Computed]
    public function alertas(): Collection
    {
        $consulta = Alerta::pendientesPara(auth()->user())->where('tipo', $this->tipo);

        return $this->tipo === TipoAlerta::Alerta->value
            ? $consulta->orderBy('expira_en')->get()
            : $consulta->latest()->get();
    }

    /**
     * Pide confirmación antes de reconocer una alerta.
     *
     * @param int $alertaId ID de la alerta.
     * @return void
     */
    public function confirmarReconocimiento(int $alertaId): void
    {
        $this->alertaConfirmandoId = $alertaId;
    }

    /**
     * Reconoce la alerta confirmada.
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

        $alertaService->reconocer($this->pendiente($this->alertaConfirmandoId), auth()->user(), request()->ip() ?? '');

        $this->alertaConfirmandoId = null;
        unset($this->alertas);
    }

    /**
     * Descarta un aviso sin confirmación. Las alertas no se pueden descartar:
     * exigen reconocimiento.
     *
     * @param int $alertaId ID del aviso.
     * @param AlertaService $alertaService Servicio de ciclo de vida de alertas.
     * @return void
     *
     * @throws AuthorizationException Si no es un aviso pendiente del usuario.
     */
    public function descartar(int $alertaId, AlertaService $alertaService): void
    {
        $aviso = $this->pendiente($alertaId);

        if ($aviso->tipo !== TipoAlerta::Aviso) {
            throw new AuthorizationException('Las alertas se reconocen, no se descartan.');
        }

        $alertaService->reconocer($aviso, auth()->user(), request()->ip() ?? '');

        unset($this->alertas);
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
     * Renderiza la pestaña.
     *
     * @return View
     */
    public function render(): View
    {
        return view('mensajes::livewire.bandeja-alertas');
    }

    /**
     * Devuelve una alerta que el usuario tiene pendiente o lanza 403.
     *
     * @throws AuthorizationException
     */
    private function pendiente(int $alertaId): Alerta
    {
        $alerta = Alerta::pendientesPara(auth()->user())->find($alertaId);

        if (! $alerta) {
            throw new AuthorizationException('No estás autorizado para reconocer esta alerta.');
        }

        return $alerta;
    }
}
