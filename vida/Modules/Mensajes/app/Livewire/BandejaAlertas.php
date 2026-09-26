<?php

namespace Modules\Mensajes\Livewire;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Modules\Mensajes\Models\Alerta;
use Modules\Mensajes\Services\AlertaService;

/**
 * Bandeja de alertas del profesional autenticado.
 *
 * Muestra alertas pendientes ordenadas por prioridad:
 * primero alertas (requieren reconocimiento), luego avisos.
 * Dentro de cada grupo, ordenadas por expiración ascendente.
 */
class BandejaAlertas extends Component
{
    public ?int $alertaConfirmandoId = null;

    /**
     * Verifica que exista sesión autenticada antes de mostrar la bandeja.
     */
    public function mount(): void
    {
        abort_unless(auth()->check(), 401);
    }

    /**
     * Alertas pendientes visibles para el usuario autenticado.
     *
     * @return Collection<int, Alerta>
     */
    #[Computed]
    public function alertas(): Collection
    {
        return Alerta::visiblesPara(auth()->user())
            ->pendientes()
            ->orderByRaw("CASE WHEN tipo = 'alerta' THEN 0 ELSE 1 END")
            ->orderBy('expira_en')
            ->with(['destinatarioUo'])
            ->get();
    }

    /**
     * Solicita confirmación antes de reconocer una alerta.
     */
    public function confirmarReconocimiento(int $alertaId): void
    {
        $this->alertaConfirmandoId = $alertaId;
    }

    /**
     * Reconoce (o descarta) la alerta confirmada.
     */
    public function reconocer(AlertaService $alertaService): void
    {
        if (! $this->alertaConfirmandoId) {
            return;
        }

        // Solo se reconocen alertas visibles para el usuario: antes las rol_uo
        // de cualquier UO pasaban sin comprobación.
        $alerta = Alerta::visiblesPara(auth()->user())
            ->pendientes()
            ->find($this->alertaConfirmandoId);

        if (! $alerta) {
            throw new AuthorizationException('No estás autorizado para reconocer esta alerta.');
        }

        $alertaService->reconocer(
            $alerta,
            auth()->user(),
            request()->ip() ?? ''
        );

        $this->alertaConfirmandoId = null;

        // Invalidar computed para refrescar la lista
        unset($this->alertas);
    }

    /**
     * Cancela el diálogo de confirmación.
     */
    public function cancelarReconocimiento(): void
    {
        $this->alertaConfirmandoId = null;
    }

    /**
     * Renderiza la bandeja de alertas.
     */
    public function render(): View
    {
        return view('mensajes::livewire.bandeja-alertas');
    }
}
