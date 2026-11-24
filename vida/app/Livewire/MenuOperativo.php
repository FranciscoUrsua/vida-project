<?php

namespace App\Livewire;

use App\Models\User;
use App\Support\Menu\EntradaMenu;
use App\Support\Menu\RegistroMenu;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Modules\Agenda\Models\Cita;
use Modules\Centro\Services\Asignacion\CentroDeUsuario;
use Modules\Intervencion\Services\Asignacion\BandejaAsignacionesService;
use Modules\Intervencion\Services\IntervencionSidebarDataService;
use Modules\Mensajes\Models\AlertaDestinatario;
use Modules\Mensajes\Services\ContadoresBandejaService;
use Modules\Prestaciones\Enums\EstadoSolicitudPrestacion;
use Modules\Prestaciones\Models\SolicitudPrestacion;

/**
 * Menú lateral único de la superficie operativa (R4.1 y R4.2): el mismo en todas las pantallas,
 * con las entradas que dan los permisos del usuario (`RegistroMenu`). Sustituye a los menús
 * propios de Intervención, Supervisión y Tramitación. Se refresca cada 60 segundos para
 * mantener los contadores al día.
 *
 * @property-read array<string, list<EntradaMenu>> $secciones
 */
class MenuOperativo extends Component
{
    /**
     * Actualiza los contadores en cuanto se reconoce una alerta o un aviso, sin esperar al
     * siguiente sondeo.
     *
     * @var array<string, string>
     */
    protected $listeners = ['alerta-reconocida' => '$refresh'];

    /** @var array<string, int> Contadores ya calculados en esta petición. */
    private array $contadores = [];

    /**
     * Secciones con sus entradas visibles para el usuario.
     *
     * @return array<string, list<EntradaMenu>>
     */
    #[Computed]
    public function secciones(): array
    {
        return RegistroMenu::para($this->usuario());
    }

    /**
     * Contador de una entrada; solo se calcula si la entrada se pinta.
     *
     * @param string $clave
     *
     * @return int
     */
    public function contador(string $clave): int
    {
        if (! array_key_exists($clave, $this->contadores)) {
            $this->contadores += $this->calcular($clave);
        }

        return $this->contadores[$clave] ?? 0;
    }

    /**
     * Pinta el menú.
     *
     * @return View
     */
    public function render(): View
    {
        return view('livewire.menu-operativo', ['usuario' => $this->usuario()]);
    }

    /**
     * Calcula un contador (los de la bandeja salen juntos).
     *
     * @param string $clave
     *
     * @return array<string, int>
     */
    private function calcular(string $clave): array
    {
        $usuario = $this->usuario();
        $centros = app(CentroDeUsuario::class);

        return match ($clave) {
            'alertas', 'avisos', 'mensajes' => app(ContadoresBandejaService::class)->para($usuario),
            'casos' => ['casos' => app(IntervencionSidebarDataService::class)->misCasosCount()],
            'tramitacion' => ['tramitacion' => SolicitudPrestacion::where('estado', EstadoSolicitudPrestacion::EnBandeja->value)
                ->whereIn('unidad_tramitadora_id', $usuario->uoSubtreeIds())
                ->count()],
            'escaladas' => ['escaladas' => AlertaDestinatario::escaladasA($usuario)->count()],
            'asignaciones' => ['asignaciones' => (function () use ($usuario, $centros): int {
                $centro = $centros->centroSupervisado($usuario);

                return $centro !== null && $centros->supervisa($usuario, $centro)
                    ? app(BandejaAsignacionesService::class)->total($centro)
                    : 0;
            })()],
            // Citas canceladas por ausencia pendientes de gestionar hoy en el centro supervisado
            'ausencias' => ['ausencias' => ($centro = $centros->centroSupervisado($usuario)) !== null
                ? Cita::canceladasPorAusenciaSinGestionar($centro->id)->count()
                : 0],
            default => [$clave => 0],
        };
    }

    /**
     * Usuario autenticado (el menú solo se pinta con sesión).
     *
     * @return User
     */
    private function usuario(): User
    {
        /** @var User */
        return Auth::user();
    }
}
