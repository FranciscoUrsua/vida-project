<?php

namespace Modules\Supervision\Http\Livewire;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Mensajes\Enums\DestinatarioType;
use Modules\Mensajes\Enums\TipoAlerta;
use Modules\Mensajes\Services\AlertaService;
use Modules\Organizacion\Services\ConfiguracionService;
use Modules\Usuarios\Models\UsuarioRol;

/**
 * Pantalla de aprobaciones para el módulo de Supervisión.
 *
 * Bandeja unificada de solicitudes pendientes de acción del supervisor:
 * asignaciones de rol con estado=pendiente_aprobacion (en ámbito de la UO)
 * y, si el centro tiene colectivos protegidos, accesos a expedientes protegidos.
 *
 * @property string $tabActiva
 * @property string $motivoDenegacion
 * @property-read Collection<int, UsuarioRol> $solicitudesRol
 * @property int|null $solicitudActivaId
 */
#[Layout('layouts.supervision')]
class AprobacionesPage extends Component
{
    /** @var string Pestaña activa: todas | roles | accesos */
    public string $tabActiva = 'todas';

    /** @var string Motivo de denegación al denegar una solicitud */
    public string $motivoDenegacion = '';

    /** @var int|null ID de la solicitud de rol expandida/activa */
    public ?int $solicitudActivaId = null;

    /**
     * Indica si el centro tiene colectivos protegidos configurados.
     * Condiciona la visibilidad de la pestaña «Accesos a expedientes».
     */
    #[Computed]
    public function tieneColectivosProtegidos(): bool
    {
        return (bool) app(ConfiguracionService::class)->get('tiene_colectivos_protegidos', false);
    }

    /**
     * Solicitudes de rol pendientes en el ámbito del supervisor.
     *
     * @return Collection<int, UsuarioRol>
     */
    #[Computed]
    public function solicitudesRol(): Collection
    {
        $uoIds = auth()->user()?->uoSubtreeIds() ?? [];

        if (empty($uoIds)) {
            return collect();
        }

        return UsuarioRol::resolublesPor(auth()->user())
            ->with(['usuario', 'rol'])
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Aprueba una solicitud de asignación de rol.
     *
     * Cambia el estado a activo y Spatie sincroniza en model_has_roles
     * a través del UsuarioRolObserver. Verifica que la solicitud esté
     * en el ámbito de UO del supervisor.
     *
     * @param int $solicitudId ID del registro UsuarioRol
     */
    public function aprobarSolicitud(int $solicitudId): void
    {
        $solicitud = UsuarioRol::findOrFail($solicitudId);

        $this->verificarAmbito($solicitud);

        $solicitud->update(['estado' => 'activo']);

        // Spatie observer sincroniza; no duplicar aquí.
        $this->notificarUsuario(
            $solicitud,
            'Tu solicitud de rol «'.$solicitud->rol?->name.'» ha sido aprobada.'
        );
    }

    /**
     * Deniega una solicitud de asignación de rol con motivo obligatorio.
     *
     * @param int $solicitudId ID del registro UsuarioRol
     * @param string $motivo Motivo de la denegación (requerido)
     *
     * @throws ValidationException si el motivo es vacío
     */
    public function denegarSolicitud(int $solicitudId, string $motivo): void
    {
        if (trim($motivo) === '') {
            throw ValidationException::withMessages([
                'motivoDenegacion' => 'El motivo es obligatorio para denegar una solicitud.',
            ]);
        }

        $solicitud = UsuarioRol::findOrFail($solicitudId);

        $this->verificarAmbito($solicitud);

        $solicitud->update(['estado' => 'denegado']);

        // Revocar el rol Spatie para que el acceso quede efectivamente bloqueado
        if ($solicitud->rol !== null) {
            $solicitud->usuario?->removeRole($solicitud->rol->name);
        }

        $this->notificarUsuario(
            $solicitud,
            'Tu solicitud de rol «'.$solicitud->rol?->name.'» ha sido denegada. Motivo: '.$motivo
        );

        $this->solicitudActivaId = null;
        $this->motivoDenegacion = '';
    }

    /**
     * Renderiza la pantalla de aprobaciones.
     */
    public function render(): View
    {
        return view('supervision::livewire.aprobaciones-page');
    }

    /**
     * Verifica que la solicitud pertenece al ámbito de UO del supervisor y no es suya.
     *
     * @param UsuarioRol $solicitud Solicitud a verificar
     *
     * @throws AuthorizationException si está fuera del ámbito o es del propio supervisor
     */
    private function verificarAmbito(UsuarioRol $solicitud): void
    {
        // Sin esta comprobación, quien tenga una solicitud propia pendiente y el
        // rol supervision podría aprobársela y anular la aprobación previa (2.8).
        if ($solicitud->usuario_id === auth()->id()) {
            abort(403, 'No puedes resolver tus propias solicitudes de rol.');
        }

        $uoIds = auth()->user()?->uoSubtreeIds() ?? [];
        $uoSolicitud = $solicitud->usuario?->adscripcionesVigentes()
            ->pluck('unidad_organizativa_id')
            ->toArray() ?? [];

        $enAmbito = ! empty(array_intersect($uoIds, $uoSolicitud));

        if (! $enAmbito) {
            abort(403, 'La solicitud está fuera del ámbito de supervisión.');
        }
    }

    /**
     * Envía al solicitante un aviso con el resultado de su solicitud de rol.
     *
     * @param UsuarioRol $solicitud Solicitud resuelta; es el origen del aviso.
     * @param string $titulo Título corto del aviso
     */
    private function notificarUsuario(UsuarioRol $solicitud, string $titulo): void
    {
        app(AlertaService::class)->crear([
            'tipo' => TipoAlerta::Aviso,
            'origen_type' => UsuarioRol::class,
            'origen_id' => $solicitud->id,
            'titulo' => $titulo,
            'cuerpo' => $titulo,
            'destinatario_type' => DestinatarioType::Usuario,
            'destinatario_usuario_id' => $solicitud->usuario_id,
        ]);
    }
}
