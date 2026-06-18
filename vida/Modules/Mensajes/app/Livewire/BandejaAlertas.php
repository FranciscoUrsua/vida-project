<?php

namespace Modules\Mensajes\Livewire;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Modules\Mensajes\Enums\DestinatarioType;
use Modules\Mensajes\Enums\EstadoAlerta;
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
     *
     * @return void
     */
    public function mount(): void
    {
        abort_unless(auth()->check(), 401);
    }

    #[Computed]
    /**
     * Alertas pendientes visibles para el usuario autenticado.
     *
     * @return Collection<int, Alerta>
     */
    public function alertas(): Collection
    {
        $usuario = auth()->user();

        return Alerta::where('estado', EstadoAlerta::Pendiente)
            ->where(function ($query) use ($usuario) {
                // Alertas directas
                $query->where(function ($q) use ($usuario) {
                    $q->where('destinatario_type', DestinatarioType::Usuario)
                        ->where('destinatario_usuario_id', $usuario->id);
                })
                // Alertas por rol+UO del usuario
                    ->orWhere(function ($q) use ($usuario) {
                        $q->where('destinatario_type', DestinatarioType::RolUo)
                            ->whereIn('destinatario_rol', $usuario->getRoleNames()->all())
                            ->whereHas('destinatarioUo', function ($uoQuery) use ($usuario) {
                                $uoQuery->whereHas('usuarios', function ($uq) use ($usuario) {
                                    $uq->where('usuario_id', $usuario->id);
                                });
                            });
                    });
            })
            ->orderByRaw("CASE WHEN tipo = 'alerta' THEN 0 ELSE 1 END")
            ->orderBy('expira_en')
            ->with(['destinatarioUo'])
            ->get();
    }

    /**
     * Solicita confirmación antes de reconocer una alerta.
     *
     * @param int $alertaId
     *
     * @return void
     */
    public function confirmarReconocimiento(int $alertaId): void
    {
        $this->alertaConfirmandoId = $alertaId;
    }

    /**
     * Reconoce (o descarta) la alerta confirmada.
     *
     * @param AlertaService $alertaService
     *
     * @return void
     */
    public function reconocer(AlertaService $alertaService): void
    {
        if (! $this->alertaConfirmandoId) {
            return;
        }

        $alerta = Alerta::findOrFail($this->alertaConfirmandoId);

        // Solo el destinatario original o el usuario al que fue escalada puede reconocerla
        if ($alerta->destinatario_type === DestinatarioType::Usuario
            && $alerta->destinatario_usuario_id !== auth()->id()
            && $alerta->escalada_a_usuario_id !== auth()->id()
        ) {
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
     *
     * @return void
     */
    public function cancelarReconocimiento(): void
    {
        $this->alertaConfirmandoId = null;
    }

    /**
     * Renderiza la bandeja de alertas.
     *
     * @return View
     */
    public function render(): View
    {
        return view('mensajes::livewire.bandeja-alertas');
    }
}
