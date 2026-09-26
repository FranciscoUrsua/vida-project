<?php

namespace Modules\Mensajes\Http\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Mensajes\Services\ContadoresBandejaService;

/**
 * Bandeja unificada del profesional: pestañas Alertas, Avisos y Mensajes.
 *
 * Las tres entradas del menú lateral abren esta misma pantalla con la
 * pestaña correspondiente. Está publicada en Intervención
 * (`intervencion.mensajes.index`) y en Supervisión (`supervision.bandeja`),
 * con el layout de cada interfaz. Cada pestaña es un subcomponente:
 * `BandejaAlertas` (alertas y avisos) y `BandejaMensajes`.
 *
 * @property-read array{alertas: int, avisos: int, mensajes: int} $contadores
 * @property-read string $rutaBandeja
 */
class BandejaAlertasYMensajes extends Component
{
    /** Pestañas válidas, en el orden en que se muestran. */
    public const PESTANAS = ['alertas' => 'Alertas', 'avisos' => 'Avisos', 'mensajes' => 'Mensajes'];

    /** Pestaña activa; la fija la ruta y cambia navegando a otra ruta. */
    #[Locked]
    public string $pestana = 'alertas';

    /** Nombre de la ruta de la bandeja en el interfaz actual, para los enlaces de las pestañas. */
    #[Locked]
    public string $ruta = 'intervencion.mensajes.index';

    /**
     * Fija la pestaña activa y el interfaz desde el que se abre.
     *
     * @param string $pestana Pestaña pedida por la ruta.
     * @return void
     */
    public function mount(string $pestana = 'alertas'): void
    {
        abort_unless(array_key_exists($pestana, self::PESTANAS), 404);

        $this->pestana = $pestana;
        $this->ruta = request()->routeIs('supervision.*') ? 'supervision.bandeja' : 'intervencion.mensajes.index';
    }

    /**
     * Pendientes de cada pestaña, para sus contadores.
     *
     * @return array{alertas: int, avisos: int, mensajes: int}
     */
    #[Computed]
    public function contadores(): array
    {
        return app(ContadoresBandejaService::class)->para(Auth::user());
    }

    /**
     * Renderiza la bandeja con el layout del interfaz desde el que se abre.
     *
     * @return View
     */
    public function render(): View
    {
        return view('mensajes::livewire.bandeja-alertas-y-mensajes')
            ->layout($this->ruta === 'supervision.bandeja' ? 'layouts.supervision' : 'layouts.operativo');
    }
}
