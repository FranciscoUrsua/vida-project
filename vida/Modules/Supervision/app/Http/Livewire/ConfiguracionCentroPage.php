<?php

namespace Modules\Supervision\Http\Livewire;

use App\Models\UnidadOrganizativa;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Organizacion\Services\ConfiguracionService;

/**
 * Pantalla de configuración del centro para el módulo de Supervisión.
 *
 * Permite al supervisor modificar los parámetros del centro bajo su ámbito
 * sin acceder al backoffice Filament: nombre, plan, horario, modo agenda
 * y umbrales de alerta del dashboard.
 *
 * @property string $nombreCorto
 * @property string $modoAgenda
 * @property float  $umbralRatio
 * @property int    $umbralEsperaDias
 * @property bool   $mostrarAdvertenciaModoAgenda
 */
#[Layout('layouts.supervision')]
class ConfiguracionCentroPage extends Component
{
    /** @var string Nombre corto del centro (badge y cabeceras) */
    public string $nombreCorto = '';

    /** @var string Modo de agenda: basico | estandar | avanzado */
    public string $modoAgenda = 'basico';

    /** @var string Modo de agenda anterior al cambio (para comparación) */
    private string $modoAgendaOriginal = '';

    /** @var float Umbral de ratio personas/profesional para alerta en el dashboard */
    public float $umbralRatio = 5;

    /** @var int Umbral de espera media para alerta en el dashboard (días) */
    public int $umbralEsperaDias = 14;

    /** @var bool Indica que el modo agenda ha cambiado y debe mostrarse la advertencia */
    public bool $mostrarAdvertenciaModoAgenda = false;

    /**
     * Indica si el centro tiene plazas configuradas.
     * Condiciona la sección «Plazas» del formulario.
     *
     * @return bool
     */
    #[Computed]
    public function tienePlazas(): bool
    {
        return (bool) app(ConfiguracionService::class)->get('tiene_plazas', false);
    }

    /**
     * Carga los valores actuales del centro al montar el componente.
     *
     * @return void
     */
    public function mount(): void
    {
        $uo = auth()->user()?->uosActivas()->first();

        if ($uo !== null) {
            $this->nombreCorto = $uo->nombre_corto ?? '';
        }

        $config = app(ConfiguracionService::class);

        $this->modoAgenda          = $config->get('modo_agenda', 'basico');
        $this->modoAgendaOriginal  = $this->modoAgenda;
        $this->umbralRatio         = (float) $config->get('umbral_ratio_carga', 5);
        $this->umbralEsperaDias    = (int) $config->get('umbral_espera_dias', 14);
    }

    /**
     * Detecta el cambio de modo de agenda para mostrar la advertencia de impacto.
     *
     * @return void
     */
    public function updatedModoAgenda(): void
    {
        $this->mostrarAdvertenciaModoAgenda = ($this->modoAgenda !== $this->modoAgendaOriginal);
    }

    /**
     * Persiste los cambios de configuración del centro.
     *
     * Solo actúa sobre la UO del supervisor autenticado; lanza 403
     * si se intenta modificar una UO fuera del ámbito.
     *
     * @return void
     */
    public function guardar(): void
    {
        $this->validate([
            'nombreCorto'     => 'nullable|string|max:50',
            'modoAgenda'      => 'required|in:basico,estandar,avanzado',
            'umbralRatio'     => 'required|numeric|min:1|max:100',
            'umbralEsperaDias' => 'required|integer|min:1|max:365',
        ]);

        $uo = auth()->user()?->uosActivas()->first();

        if ($uo === null) {
            $this->addError('nombreCorto', 'El supervisor no tiene UO activa asignada.');
            return;
        }

        // Verificar que la UO es accesible por el supervisor
        $enAmbito = in_array($uo->id, auth()->user()?->uoSubtreeIds() ?? []);

        if (! $enAmbito) {
            abort(403, 'No tiene permisos para modificar la configuración de esta UO.');
        }

        $uo->update(['nombre_corto' => $this->nombreCorto ?: null]);

        $config = app(ConfiguracionService::class);
        $config->set('modo_agenda', $this->modoAgenda);
        $config->set('umbral_ratio_carga', $this->umbralRatio);
        $config->set('umbral_espera_dias', $this->umbralEsperaDias);

        $this->modoAgendaOriginal           = $this->modoAgenda;
        $this->mostrarAdvertenciaModoAgenda = false;
    }

    /**
     * Renderiza la pantalla de configuración del centro.
     */
    public function render(): View
    {
        return view('supervision::livewire.configuracion-centro-page');
    }
}
