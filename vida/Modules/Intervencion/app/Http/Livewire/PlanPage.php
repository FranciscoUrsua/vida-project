<?php

namespace Modules\Intervencion\Http\Livewire;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Modules\Intervencion\Enums\EstadoPlan;
use Modules\Intervencion\Models\FirmaPlan;
use Modules\Intervencion\Models\PlanCambio;
use Modules\Intervencion\Models\PlanDeIntervencion;
use App\Models\Ciudadano;
use Modules\Ciudadania\Models\CiudadanoRelacion;
use Modules\Ciudadania\Models\TipoRelacion;
use Modules\Ciudadania\Models\UnidadConvivencia;
use Modules\Intervencion\Services\PlanPdfService;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Página completa del Plan de Intervención Social (PISO).
 *
 * Gestiona diagnóstico, objetivos, actuaciones, participantes, firmas y
 * generación de PDF. Requiere motivo explícito para cualquier cambio sobre
 * un plan ya firmado (estado activo).
 *
 * @property int|null $planId
 * @property int|null $historiaId
 * @property int|null $ucId
 * @property PlanDeIntervencion|null $plan
 * @property bool $drawerAbierto
 * @property array $fichasSeleccionadas
 * @property string $drawerFiltroTipo
 * @property string $drawerFiltroFecha
 * @property bool $modalMotivoAbierto
 * @property string $motivoTexto
 * @property string $motivoAccionPendiente
 * @property array $motivoAccionParams
 * @property string $diagnosticoTexto
 * @property string $periodicidadSeguimiento
 * @property string $observacionesSeguimiento
 * @property bool $profesionalFirmado
 * @property bool $ciudadanoFirmado
 * @property string|null $fechaFirmaPresencial
 * @property string $mensajeExito
 */
class PlanPage extends Component
{
    use AuthorizesRequests;

    // --- Parámetros de ruta ---
    public ?int $planId = null;
    public ?int $historiaId = null;
    public ?int $ucId = null;

    // --- Plan cargado ---
    public ?PlanDeIntervencion $plan = null;

    // --- Estado del drawer ---
    public bool $drawerAbierto = false;
    public array $fichasSeleccionadas = [];
    public string $drawerFiltroTipo = 'todas';
    public string $drawerFiltroFecha = 'todas';

    // --- Modal de motivo ---
    public bool $modalMotivoAbierto = false;
    public string $motivoTexto = '';
    public string $motivoAccionPendiente = '';
    public array $motivoAccionParams = [];

    // --- Edición inline ---
    public string $diagnosticoTexto = '';
    public string $periodicidadSeguimiento = 'trimestral';
    public string $observacionesSeguimiento = '';

    // --- Firmas ---
    public bool $profesionalFirmado = false;
    public bool $ciudadanoFirmado = false;
    public ?string $fechaFirmaPresencial = null;

    // --- Feedback ---
    public string $mensajeExito = '';

    /**
     * Inicializa el componente con el plan si se accede en modo edición,
     * o prepara el estado para creación si no hay plan.
     *
     * @param PlanDeIntervencion|null $plan
     * @param int|null $historia
     * @param int|null $uc
     * @return void
     */
    public function mount(?PlanDeIntervencion $plan = null, ?int $historia = null, ?int $uc = null): void
    {
        if ($plan && $plan->exists) {
            $this->authorize('view', $plan);
            $this->plan = $plan;
            $this->planId = $plan->id;
            $this->diagnosticoTexto = $plan->diagnostico_social ?? '';
            $this->periodicidadSeguimiento = $plan->periodicidad_seguimiento ?? 'trimestral';

            $firma = FirmaPlan::where('plan_id', $plan->id)
                ->where('version', $plan->version)
                ->first();
            if ($firma) {
                $this->profesionalFirmado = $firma->profesional_firmado;
                $this->ciudadanoFirmado   = $firma->ciudadano_firmado;
                $this->fechaFirmaPresencial = $firma->fecha_firma?->format('Y-m-d');
                $this->observacionesSeguimiento = $firma->observaciones_seguimiento ?? '';
            }

            $this->fichasSeleccionadas = $plan->fichasDiagnostico()
                ->pluck('ficha_id')
                ->toArray();
        } else {
            $this->historiaId = $historia;
            $this->ucId = $uc;
        }
    }

    // =========================================================
    // COMPUTEDS
    // =========================================================

    /**
     * Ciudadano titular de la historia social del plan.
     *
     * @return Ciudadano|null
     */
    #[Computed]
    public function ciudadano(): ?Ciudadano
    {
        return $this->plan?->historia?->ciudadano;
    }

    /**
     * Unidad de convivencia vigente del ciudadano o la del plan.
     *
     * @return UnidadConvivencia|null
     */
    #[Computed]
    public function ucVigente(): ?UnidadConvivencia
    {
        return $this->plan?->unidadConvivencia
            ?? $this->ciudadano?->unidadesConvivenciaActivas()->first();
    }

    /**
     * Miembros activos de la unidad de convivencia con relación y verificación.
     *
     * @return \Illuminate\Support\Collection
     */
    #[Computed]
    public function miembrosUc(): \Illuminate\Support\Collection
    {
        if (! $this->ucVigente) {
            return collect();
        }

        $slugsEtiquetas = TipoRelacion::activos()->pluck('etiqueta', 'slug');

        return $this->ucVigente->miembrosActivos()
            ->with('ciudadano')
            ->get()
            ->map(function ($m) use ($slugsEtiquetas) {
                $relacion = CiudadanoRelacion::where(
                    'ciudadano_id', $this->ciudadano?->id
                )->where('ciudadano_relacionado_id', $m->ciudadano_id)
                 ->whereNull('fecha_fin')
                 ->value('tipo_relacion');

                return [
                    'ciudadano'  => $m->ciudadano,
                    'relacion'   => $relacion ? ($slugsEtiquetas[$relacion] ?? null) : null,
                    'verificado' => $m->verificado,
                ];
            });
    }

    /**
     * Fichas de valoración incluidas en el diagnóstico del plan.
     *
     * @return \Illuminate\Support\Collection
     */
    #[Computed]
    public function fichasDiagnostico(): \Illuminate\Support\Collection
    {
        if (! $this->plan) {
            return collect();
        }
        return $this->plan->fichasDiagnostico()->with('ficha.tipoFicha')->get();
    }

    /**
     * Objetivos generales del plan con sus específicos anidados.
     *
     * @return \Illuminate\Support\Collection
     */
    #[Computed]
    public function objetivosGenerales(): \Illuminate\Support\Collection
    {
        if (! $this->plan) {
            return collect();
        }
        return $this->plan->objetivosGenerales()
            ->with('objetivosEspecificos')
            ->get();
    }

    /**
     * Actuaciones del Ayuntamiento en el plan.
     *
     * @return \Illuminate\Support\Collection
     */
    #[Computed]
    public function actuacionesAyuntamiento(): \Illuminate\Support\Collection
    {
        if (! $this->plan) {
            return collect();
        }
        return $this->plan->actuacionesAyuntamiento()
            ->with(['prestacion', 'responsable'])
            ->get();
    }

    /**
     * Compromisos del ciudadano en el plan.
     *
     * @return \Illuminate\Support\Collection
     */
    #[Computed]
    public function actuacionesCiudadano(): \Illuminate\Support\Collection
    {
        if (! $this->plan) {
            return collect();
        }
        return $this->plan->actuacionesCiudadano()
            ->with('prestacion')
            ->get();
    }

    /**
     * Profesionales participantes en el plan.
     *
     * @return \Illuminate\Support\Collection
     */
    #[Computed]
    public function participantes(): \Illuminate\Support\Collection
    {
        if (! $this->plan) {
            return collect();
        }
        return $this->plan->participantes()
            ->with(['profesional', 'servicio'])
            ->get();
    }

    /**
     * Indica si el plan puede activarse (ambas firmas marcadas y en borrador).
     *
     * @return bool
     */
    #[Computed]
    public function puedeActivarse(): bool
    {
        return $this->profesionalFirmado && $this->ciudadanoFirmado
            && $this->plan?->estado === EstadoPlan::Borrador;
    }

    /**
     * Indica si el plan está en estado activo (firmado y vigente).
     *
     * @return bool
     */
    #[Computed]
    public function planFirmado(): bool
    {
        return $this->plan?->estado === EstadoPlan::Activo;
    }

    /**
     * Valoraciones del historial de la historia social, filtradas por fecha.
     *
     * @return \Illuminate\Support\Collection
     */
    #[Computed]
    public function valoracionesTimeline(): \Illuminate\Support\Collection
    {
        if (! $this->plan) {
            return collect();
        }

        $query = \Modules\Intervencion\Models\Valoracion::where(
            'historia_id', $this->plan->historia_id
        )->with(['fichas.tipoFicha']);

        if ($this->drawerFiltroFecha === 'mes') {
            $query->where('created_at', '>=', now()->subMonth());
        } elseif ($this->drawerFiltroFecha === 'anio') {
            $query->where('created_at', '>=', now()->subYear());
        }

        return $query->orderByDesc('created_at')->get();
    }

    /**
     * Nombre corto del plan según la configuración de la UO del profesional.
     *
     * @return string
     */
    #[Computed]
    public function planNombreCorto(): string
    {
        return auth()->user()?->unidadOrganizativa?->plan_nombre_corto ?? 'Plan';
    }

    // =========================================================
    // ACCIONES — DRAWER
    // =========================================================

    /**
     * Abre el drawer de selección de fichas del historial.
     *
     * @return void
     */
    public function abrirDrawer(): void
    {
        $this->drawerAbierto = true;
    }

    /**
     * Cierra el drawer de selección de fichas.
     *
     * @return void
     */
    public function cerrarDrawer(): void
    {
        $this->drawerAbierto = false;
    }

    /**
     * Aplica la selección de fichas del drawer al diagnóstico del plan.
     * Pide motivo si el plan ya está firmado.
     *
     * @param array $fichasNuevas
     * @return void
     */
    public function aplicarSeleccionFichas(array $fichasNuevas): void
    {
        if (! $this->plan) {
            return;
        }

        $cambios = array_diff($fichasNuevas, $this->fichasSeleccionadas)
                 + array_diff($this->fichasSeleccionadas, $fichasNuevas);

        if (empty($cambios)) {
            $this->cerrarDrawer();
            return;
        }

        if ($this->planFirmado) {
            $this->encolarAccion('aplicarFichas', ['fichas' => $fichasNuevas]);
            $this->cerrarDrawer();
            return;
        }

        $this->_aplicarFichas($fichasNuevas);
        $this->cerrarDrawer();
        unset($this->fichasDiagnostico);
    }

    /**
     * Sincroniza las fichas seleccionadas en la tabla pivote.
     *
     * @param array $fichas
     * @return void
     */
    private function _aplicarFichas(array $fichas): void
    {
        $eliminar = array_diff($this->fichasSeleccionadas, $fichas);
        if ($eliminar) {
            $this->plan->fichasDiagnostico()
                ->whereIn('ficha_id', $eliminar)
                ->delete();
        }

        $anadir = array_diff($fichas, $this->fichasSeleccionadas);
        foreach ($anadir as $fichaId) {
            $this->plan->fichasDiagnostico()->firstOrCreate(['ficha_id' => $fichaId]);
        }

        $this->fichasSeleccionadas = $fichas;
        unset($this->fichasDiagnostico);
    }

    /**
     * Elimina una ficha del diagnóstico. Pide motivo si el plan está firmado.
     *
     * @param int $fichaId
     * @return void
     */
    public function eliminarFichaDiagnostico(int $fichaId): void
    {
        if (! $this->plan) {
            return;
        }

        if ($this->planFirmado) {
            $this->encolarAccion('eliminarFicha', ['ficha_id' => $fichaId]);
            return;
        }

        $this->plan->fichasDiagnostico()->where('ficha_id', $fichaId)->delete();
        $this->fichasSeleccionadas = array_values(
            array_filter($this->fichasSeleccionadas, fn ($id) => $id !== $fichaId)
        );
        unset($this->fichasDiagnostico);
    }

    // =========================================================
    // ACCIONES — DIAGNÓSTICO TEXTO
    // =========================================================

    /**
     * Guarda el texto de síntesis del diagnóstico social.
     * Si el plan está firmado, abre el modal de motivo.
     *
     * @return void
     */
    public function guardarDiagnostico(): void
    {
        if (! $this->plan) {
            return;
        }

        if ($this->planFirmado && $this->diagnosticoTexto !== $this->plan->diagnostico_social) {
            $this->encolarAccion('guardarDiagnostico', []);
            return;
        }

        $this->plan->update(['diagnostico_social' => $this->diagnosticoTexto]);
        $this->plan = $this->plan->fresh();
        $this->mensajeExito = 'Diagnóstico guardado.';
    }

    // =========================================================
    // ACCIONES — SEGUIMIENTO Y FIRMAS
    // =========================================================

    /**
     * Guarda la periodicidad y observaciones del seguimiento.
     * Si el plan está firmado, abre el modal de motivo.
     *
     * @return void
     */
    public function guardarSeguimiento(): void
    {
        if (! $this->plan) {
            return;
        }

        if ($this->planFirmado) {
            $this->encolarAccion('guardarSeguimiento', []);
            return;
        }

        $this->plan->update([
            'periodicidad_seguimiento' => $this->periodicidadSeguimiento,
        ]);

        $this->_actualizarOServicioFirma(['observaciones_seguimiento' => $this->observacionesSeguimiento]);
        $this->mensajeExito = 'Condiciones de seguimiento guardadas.';
    }

    /**
     * Registra o revoca la firma del profesional responsable.
     *
     * @param bool $valor
     * @return void
     */
    public function marcarFirmaProfesional(bool $valor): void
    {
        if (! $this->plan || $this->plan->estado === EstadoPlan::Cerrado) {
            return;
        }

        $this->profesionalFirmado = $valor;
        $this->_actualizarOServicioFirma([
            'profesional_firmado'    => $valor,
            'profesional_firmado_en' => $valor ? now() : null,
        ]);
    }

    /**
     * Registra o revoca la firma del ciudadano.
     *
     * @param bool $valor
     * @return void
     */
    public function marcarFirmaCiudadano(bool $valor): void
    {
        if (! $this->plan || $this->plan->estado === EstadoPlan::Cerrado) {
            return;
        }

        $this->ciudadanoFirmado = $valor;
        $this->_actualizarOServicioFirma([
            'ciudadano_firmado'    => $valor,
            'ciudadano_firmado_en' => $valor ? now() : null,
        ]);
    }

    /**
     * Guarda la fecha de la firma presencial en el registro de firmas.
     *
     * @return void
     */
    public function guardarFechaFirma(): void
    {
        if (! $this->plan) {
            return;
        }
        $this->_actualizarOServicioFirma(['fecha_firma' => $this->fechaFirmaPresencial]);
    }

    /**
     * Crea o actualiza el registro FirmaPlan para la versión actual del plan.
     *
     * @param array $datos
     * @return void
     */
    private function _actualizarOServicioFirma(array $datos): void
    {
        FirmaPlan::updateOrCreate(
            ['plan_id' => $this->plan->id, 'version' => $this->plan->version],
            array_merge(['metodo_firma' => 'manuscrita'], $datos)
        );
    }

    /**
     * Activa el plan cuando ambas firmas están marcadas.
     *
     * @return void
     */
    public function activarPlan(): void
    {
        if (! $this->plan || ! $this->puedeActivarse) {
            return;
        }
        $this->authorize('update', $this->plan);

        $this->plan->update([
            'estado'      => 'activo',
            'fecha_firma' => $this->fechaFirmaPresencial ?? now()->toDateString(),
        ]);
        $this->plan = $this->plan->fresh();
        $this->mensajeExito = 'Plan activado correctamente.';
    }

    // =========================================================
    // ACCIONES — MODAL DE MOTIVO
    // =========================================================

    /**
     * Encola la acción pendiente y abre el modal de motivo obligatorio.
     *
     * @param string $accion
     * @param array $params
     * @return void
     */
    private function encolarAccion(string $accion, array $params): void
    {
        $this->motivoAccionPendiente = $accion;
        $this->motivoAccionParams    = $params;
        $this->motivoTexto           = '';
        $this->modalMotivoAbierto    = true;
    }

    /**
     * Confirma el cambio con motivo, registra en historial y ejecuta la acción.
     *
     * @return void
     */
    public function confirmarCambioConMotivo(): void
    {
        if (empty(trim($this->motivoTexto))) {
            return;
        }
        if (! $this->plan) {
            return;
        }

        $this->plan->registrarCambio(
            auth()->id(),
            trim($this->motivoTexto),
            'discrecional'
        );

        match ($this->motivoAccionPendiente) {
            'eliminarFicha'      => $this->_eliminarFichaDirecto($this->motivoAccionParams['ficha_id']),
            'aplicarFichas'      => $this->_aplicarFichas($this->motivoAccionParams['fichas']),
            'guardarDiagnostico' => $this->plan->update(['diagnostico_social' => $this->diagnosticoTexto]),
            'guardarSeguimiento' => $this->_guardarSeguimientoDirecto(),
            default              => null,
        };

        $this->modalMotivoAbierto    = false;
        $this->motivoAccionPendiente = '';
        $this->motivoAccionParams    = [];
        $this->motivoTexto           = '';
        $this->mensajeExito          = 'Cambio registrado.';
        $this->plan = $this->plan->fresh();
        unset($this->fichasDiagnostico);
    }

    /**
     * Cancela el cambio pendiente y cierra el modal sin persistir.
     *
     * @return void
     */
    public function cancelarCambio(): void
    {
        $this->modalMotivoAbierto    = false;
        $this->motivoAccionPendiente = '';
        $this->motivoAccionParams    = [];
        $this->motivoTexto           = '';
    }

    /**
     * Elimina directamente una ficha del diagnóstico sin verificar estado del plan.
     *
     * @param int $fichaId
     * @return void
     */
    private function _eliminarFichaDirecto(int $fichaId): void
    {
        $this->plan->fichasDiagnostico()->where('ficha_id', $fichaId)->delete();
        $this->fichasSeleccionadas = array_values(
            array_filter($this->fichasSeleccionadas, fn ($id) => $id !== $fichaId)
        );
    }

    /**
     * Persiste periodicidad y observaciones de seguimiento directamente.
     *
     * @return void
     */
    private function _guardarSeguimientoDirecto(): void
    {
        $this->plan->update(['periodicidad_seguimiento' => $this->periodicidadSeguimiento]);
        $this->_actualizarOServicioFirma(['observaciones_seguimiento' => $this->observacionesSeguimiento]);
    }

    // =========================================================
    // GENERACIÓN PDF
    // =========================================================

    /**
     * Genera y descarga el PDF del plan de intervención.
     *
     * @return StreamedResponse
     */
    public function generarPdf(): StreamedResponse
    {
        if (! $this->plan) {
            abort(404);
        }
        $this->authorize('view', $this->plan);

        $service = app(PlanPdfService::class);
        return response()->streamDownload(
            fn () => print($service->generar($this->plan)),
            $service->nombre($this->plan),
            ['Content-Type' => 'application/pdf']
        );
    }

    // =========================================================
    // RENDER
    // =========================================================

    /**
     * Renderiza la vista del plan de intervención con el layout operativo.
     *
     * @return View
     */
    public function render(): View
    {
        return view('intervencion::livewire.plan-page')
            ->layout('layouts.operativo');
    }
}
