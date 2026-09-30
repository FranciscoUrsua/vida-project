<?php

namespace Modules\Supervision\Http\Livewire;

use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Modules\Agenda\Models\Cita;
use Modules\Centro\Models\Centro;
use Modules\Centro\Services\Asignacion\CentroDeUsuario;
use Modules\Intervencion\Services\Asignacion\BandejaAsignacionesService;
use Modules\Mensajes\Models\AlertaDestinatario;
use Modules\Mensajes\Services\ContadoresBandejaService;
use Modules\Organizacion\Models\Configuracion;
use Modules\Organizacion\Services\ConfiguracionService;
use Modules\Supervision\Services\SupervisionSidebarDataService;

/**
 * Sidebar unificado del interfaz operativo de Supervisión y Agenda.
 *
 * Cubre toda la navegación del supervisor: páginas de Supervisión y
 * páginas de Agenda (cuadrante, ausencias, excepciones, eventos).
 * Incluye las entradas de la bandeja (Alertas, Avisos, Mensajes).
 * Badge de aprobaciones pendientes e ítems condicionales (Plazas) según
 * configuración del centro. Se actualiza cada 60 segundos.
 *
 * @property array{alertas: int, avisos: int, mensajes: int} $contadoresBandeja
 * @property int $escaladasAbiertas
 * @property int $asignacionesPendientes
 * @property int $aprobacionesPendientes
 * @property int $citasPendientesBadge
 * @property bool $tienePlazas
 */
class Sidebar extends Component
{
    /**
     * Actualiza los contadores en cuanto se reconoce una alerta o un aviso,
     * sin esperar al siguiente polling.
     *
     * @var array<string, string>
     */
    protected $listeners = ['alerta-reconocida' => '$refresh'];

    /**
     * Contadores de las entradas Alertas, Avisos y Mensajes de la bandeja.
     *
     * @return array{alertas: int, avisos: int, mensajes: int}
     */
    #[Computed]
    public function contadoresBandeja(): array
    {
        if (! auth()->check()) {
            return ['alertas' => 0, 'avisos' => 0, 'mensajes' => 0];
        }

        return app(ContadoresBandejaService::class)->para(auth()->user());
    }

    /**
     * Personas, historias y repartos por decidir en la bandeja de asignaciones del centro.
     *
     * @return int
     */
    #[Computed]
    public function asignacionesPendientes(): int
    {
        $usuario = auth()->user();
        $centros = app(CentroDeUsuario::class);
        $centro = $centros->centroActivo($usuario);

        if ($centro === null || ! $centros->supervisa($usuario, $centro)) {
            return 0;
        }

        return app(BandejaAsignacionesService::class)->total($centro);
    }

    /**
     * Partes de alertas escaladas al supervisor que aún no ha cerrado.
     *
     * @return int
     */
    #[Computed]
    public function escaladasAbiertas(): int
    {
        if (! auth()->check()) {
            return 0;
        }

        return AlertaDestinatario::escaladasA(auth()->user())->count();
    }

    /**
     * Número de aprobaciones pendientes en el ámbito del supervisor.
     *
     * @return int
     */
    #[Computed]
    public function aprobacionesPendientes(): int
    {
        if (! auth()->check()) {
            return 0;
        }

        return app(SupervisionSidebarDataService::class)
            ->aprobacionesPendientes(auth()->id());
    }

    /**
     * Indica si el centro tiene plazas configuradas.
     * Determina la visibilidad del ítem «Plazas» en el sidebar.
     *
     * @return bool
     */
    #[Computed]
    public function tienePlazas(): bool
    {
        return (bool) app(ConfiguracionService::class)->get('tiene_plazas', false);
    }

    /**
     * Citas canceladas por ausencia pendientes de gestionar hoy en el centro.
     *
     * @return int
     */
    #[Computed]
    public function citasPendientesBadge(): int
    {
        if (! auth()->check()) {
            return 0;
        }

        $uoId   = auth()->user()?->uosActivas()->first()?->id;
        $centro = $uoId ? Centro::where('unidad_organizativa_id', $uoId)->first() : null;

        if ($centro === null) {
            return 0;
        }

        return Cita::canceladasPorAusenciaSinGestionar($centro->id)->count();
    }

    /**
     * Datos de identidad visual configurados en el backoffice.
     *
     * @return array{logoUrl: string|null, nombreAplicacion: string|null}
     */
    #[Computed]
    public function branding(): array
    {
        return [
            'logoUrl' => Configuracion::logoUrl(),
            'nombreAplicacion' => Configuracion::nombreAplicacion(),
        ];
    }

    /**
     * Renderiza el sidebar del módulo.
     *
     * @return View
     */
    public function render(): View
    {
        return view('supervision::livewire.sidebar');
    }
}
