<?php

namespace Modules\Supervision\Http\Livewire;

use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Organizacion\Services\ConfiguracionService;

/**
 * Pantalla de auditoría de accesos para el módulo de Supervisión.
 *
 * Muestra los accesos «inesperados» a datos de ciudadanos del centro:
 * profesionales externos (de otra UO) que han consultado historias sociales
 * asignadas al centro. Reutiliza AccesosExpedienteQuery con filtro de externo.
 *
 * @property string $fechaDesde
 * @property string $fechaHasta
 * @property int|null $profesionalFiltro
 * @property bool $soloProtegidos
 * @property bool $soloSinAutorizacion
 */
#[Layout('layouts.supervision')]
class AuditoriaPage extends Component
{
    /** @var string Fecha de inicio del rango de filtro (por defecto: 30 días atrás) */
    public string $fechaDesde = '';

    /** @var string Fecha de fin del rango de filtro */
    public string $fechaHasta = '';

    /** @var int|null ID de profesional para filtrar accesos */
    public ?int $profesionalFiltro = null;

    /** @var bool Filtrar solo accesos a ciudadanos de colectivos protegidos */
    public bool $soloProtegidos = false;

    /** @var bool Filtrar solo accesos sin autorización (relevante con colectivos protegidos) */
    public bool $soloSinAutorizacion = false;

    /**
     * Inicializa las fechas del filtro por defecto (últimos 30 días).
     *
     * @return void
     */
    public function mount(): void
    {
        $this->fechaHasta = now()->toDateString();
        $this->fechaDesde = now()->subDays(30)->toDateString();
    }

    /**
     * Indica si el centro tiene colectivos protegidos configurados.
     * Condiciona la visibilidad de columnas y filtros de acceso protegido.
     *
     * @return bool
     */
    #[Computed]
    public function tieneColectivosProtegidos(): bool
    {
        return (bool) app(ConfiguracionService::class)->get('tiene_colectivos_protegidos', false);
    }

    /**
     * Renderiza la pantalla de auditoría de accesos.
     */
    public function render(): View
    {
        return view('supervision::livewire.auditoria-page');
    }
}
