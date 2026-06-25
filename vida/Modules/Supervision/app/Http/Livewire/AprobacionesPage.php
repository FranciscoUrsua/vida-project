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
use Modules\Mensajes\Enums\EstadoAlerta;
use Modules\Mensajes\Enums\TipoAlerta;
use Modules\Mensajes\Models\Alerta;
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

        return UsuarioRol::where('estado', 'pendiente_aprobacion')
            ->whereHas('usuario', function ($q) use ($uoIds) {
                $q->whereHas('adscripcionesVigentes', function ($q2) use ($uoIds) {
                    $q2->whereIn('unidad_organizativa_id', $uoIds);
                });
            })
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
            $solicitud->usuario_id,
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
            $solicitud->usuario_id,
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
     * Verifica que la solicitud pertenece al ámbito de UO del supervisor.
     *
     * @param UsuarioRol $solicitud Solicitud a verificar
     *
     * @throws AuthorizationException si está fuera del ámbito
     */
    private function verificarAmbito(UsuarioRol $solicitud): void
    {
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
     * Crea una alerta informativa para el usuario afectado por la decisión.
     *
     * @param int $destinatarioId ID del usuario a notificar
     * @param string $titulo Título corto de la alerta
     */
    private function notificarUsuario(int $destinatarioId, string $titulo): void
    {
        Alerta::create([
            'tipo' => TipoAlerta::Aviso,
            'origen_type' => UsuarioRol::class,
            'origen_id' => 0,
            'titulo' => $titulo,
            'cuerpo' => $titulo,
            'estado' => EstadoAlerta::Pendiente,
            'destinatario_type' => DestinatarioType::Usuario,
            'destinatario_usuario_id' => $destinatarioId,
        ]);
    }
}
