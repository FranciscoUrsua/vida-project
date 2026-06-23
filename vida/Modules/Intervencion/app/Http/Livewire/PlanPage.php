<?php

namespace Modules\Intervencion\Http\Livewire;

use App\Models\Ciudadano;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Modules\Ciudadania\Models\CiudadanoRelacion;
use Modules\Ciudadania\Models\TipoRelacion;
use Modules\Ciudadania\Models\UnidadConvivencia;
use Modules\Intervencion\Enums\EstadoPlan;
use Modules\Intervencion\Models\Ficha;
use Modules\Intervencion\Models\FirmaPlan;
use Modules\Intervencion\Models\ObjetivoCatalogo;
use Modules\Intervencion\Models\PlanActuacionAyuntamiento;
use Modules\Intervencion\Models\PlanActuacionCiudadano;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Intervencion\Models\PlanObjetivo;
use Modules\Intervencion\Models\PlanObjetivoIndicador;
use Modules\Intervencion\Models\PlanParticipante;
use Modules\Intervencion\Services\PlanPdfService;
use Modules\Prestaciones\Models\Prestacion;
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
 * @property bool $modalObjetivoAbierto
 * @property string $modoObjetivo
 * @property array $objetivosCatalogoSeleccionados
 * @property string $nuevoObjetivoTexto
 * @property bool $modalCompromisoAbierto
 * @property string $nuevoCompromisoDescripcion
 * @property bool $modalActuacionAytoAbierto
 * @property int|null $nuevaActuacionPrestacionId
 * @property string $nuevaActuacionDescripcion
 * @property bool $modalParticipanteAbierto
 * @property int|null $nuevoParticipanteUserId
 * @property string $nuevoParticipanteRol
 * @property bool $modalCierreAbierto
 * @property string $motivoCierre
 * @property string $notasCierre
 * @property array $valoracionesIndicadores
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

    // --- Modales de creación ---
    public bool $modalObjetivoAbierto = false;

    /** @var string Modo activo del modal de objetivo: 'catalogo' | 'libre' */
    public string $modoObjetivo = 'catalogo';

    /** @var array<int> IDs de ObjetivoCatalogo seleccionados en el modal */
    public array $objetivosCatalogoSeleccionados = [];

    public string $nuevoObjetivoTexto = '';

    public bool $modalCompromisoAbierto = false;

    public string $nuevoCompromisoDescripcion = '';

    public bool $modalActuacionAytoAbierto = false;

    public ?int $nuevaActuacionPrestacionId = null;

    public string $nuevaActuacionDescripcion = '';

    public bool $modalParticipanteAbierto = false;

    public ?int $nuevoParticipanteUserId = null;

    public string $nuevoParticipanteRol = '';

    // --- Cierre del plan ---
    public bool $modalCierreAbierto = false;

    public string $motivoCierre = '';

    public string $notasCierre = '';

    // --- Valoración de indicadores: [plan_objetivo_indicador_id => valor] ---
    public array $valoracionesIndicadores = [];

    /**
     * Inicializa el componente con el plan si se accede en modo edición,
     * o prepara el estado para creación si no hay plan.
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
                $this->ciudadanoFirmado = $firma->ciudadano_firmado;
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
     */
    #[Computed]
    public function ciudadano(): ?Ciudadano
    {
        return $this->plan?->historia?->ciudadano;
    }

    /**
     * Unidad de convivencia vigente del ciudadano o la del plan.
     */
    #[Computed]
    public function ucVigente(): ?UnidadConvivencia
    {
        return $this->plan?->unidadConvivencia
            ?? $this->ciudadano?->unidadesConvivenciaActivas()->first();
    }

    /**
     * Miembros activos de la unidad de convivencia con relación y verificación.
     */
    #[Computed]
    public function miembrosUc(): Collection
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
                    'ciudadano' => $m->ciudadano,
                    'relacion' => $relacion ? ($slugsEtiquetas[$relacion] ?? null) : null,
                    'verificado' => $m->verificado,
                ];
            });
    }

    /**
     * Fichas de valoración incluidas en el diagnóstico del plan.
     */
    #[Computed]
    public function fichasDiagnostico(): Collection
    {
        if (! $this->plan) {
            return collect();
        }

        return $this->plan->fichasDiagnostico()->with('ficha.tipoFicha')->get();
    }

    /**
     * Objetivos generales del plan con sus específicos anidados.
     */
    #[Computed]
    public function objetivosGenerales(): Collection
    {
        if (! $this->plan) {
            return collect();
        }

        return $this->plan->objetivosGenerales()
            ->with('objetivosEspecificos')
            ->get();
    }

    /**
     * Objetivos generales con sus específicos e indicadores cargados para la vista.
     *
     * @return \Illuminate\Support\Collection
     */
    #[Computed]
    public function objetivosConIndicadores(): \Illuminate\Support\Collection
    {
        if (! $this->plan) {
            return collect();
        }

        return $this->plan->objetivosGenerales()
            ->with(['objetivosEspecificos.indicador', 'objetivosEspecificos.tipoFicha', 'indicador'])
            ->get();
    }

    /**
     * Etiquetas de los motivos de cierre del plan.
     *
     * @return array<string,string>
     */
    #[Computed]
    public function motivosCierre(): array
    {
        return [
            'negativa_firma'             => 'Cerrado por negativa a la firma / falta de colaboración',
            'consecucion_objetivos'      => 'Cerrado por consecución de objetivos',
            'cambio_residencia'          => 'Cerrado por cambio de residencia',
            'imposibilidad_localizacion' => 'Cerrado por imposibilidad de localizar a la familia',
            'fallecimiento'              => 'Cerrado por fallecimiento',
            'fin_intervencion'           => 'Cerrado por finalización de la intervención',
        ];
    }

    /**
     * Actuaciones del Ayuntamiento en el plan.
     */
    #[Computed]
    public function actuacionesAyuntamiento(): Collection
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
     */
    #[Computed]
    public function actuacionesCiudadano(): Collection
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
     */
    #[Computed]
    public function participantes(): Collection
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
     */
    #[Computed]
    public function puedeActivarse(): bool
    {
        return $this->profesionalFirmado && $this->ciudadanoFirmado
            && $this->plan?->estado === EstadoPlan::Borrador;
    }

    /**
     * Indica si el plan está en estado activo (firmado y vigente).
     */
    #[Computed]
    public function planFirmado(): bool
    {
        return $this->plan?->estado === EstadoPlan::Activo;
    }

    /**
     * Fichas del historial de la historia social disponibles para añadir al diagnóstico.
     *
     * Consulta Ficha directamente por historia_id porque valoracion_id es nullable:
     * las fichas creadas sin valoración formal no se recuperarían vía Valoracion→fichas.
     *
     * @return Collection<int, Ficha>
     */
    #[Computed]
    public function fichasHistorial(): Collection
    {
        if (! $this->plan) {
            return collect();
        }

        $query = Ficha::where('historia_id', $this->plan->historia_id)
            ->with('tipoFicha');

        if ($this->drawerFiltroFecha === 'mes') {
            $query->where('created_at', '>=', now()->subMonth());
        } elseif ($this->drawerFiltroFecha === 'anio') {
            $query->where('created_at', '>=', now()->subYear());
        }

        return $query->orderByDesc('created_at')->get();
    }

    /**
     * Nombre corto del plan según la configuración de la UO del profesional.
     */
    #[Computed]
    public function planNombreCorto(): string
    {
        return auth()->user()?->unidadOrganizativa?->plan_nombre_corto ?? 'Plan';
    }

    /**
     * Objetivos generales del catálogo disponibles para el tipo de plan, con sus específicos e indicadores.
     * Solo se consulta cuando el modal está abierto en modo catálogo.
     *
     * @return Collection<int, ObjetivoCatalogo>
     */
    #[Computed]
    public function objetivosCatalogo(): Collection
    {
        if (! $this->modalObjetivoAbierto || ! $this->plan?->tipo_plan_id) {
            return collect();
        }

        return ObjetivoCatalogo::where('tipo_plan_id', $this->plan->tipo_plan_id)
            ->where('nivel', 'general')
            ->where('activo', true)
            ->with(['objetivosEspecificos', 'indicador'])
            ->orderBy('orden')
            ->get();
    }

    /**
     * Catálogo de prestaciones activas para el selector de actuaciones del Ayuntamiento.
     *
     * @return Collection<int, Prestacion>
     */
    #[Computed]
    public function prestacionesCatalogo(): Collection
    {
        if (! $this->modalActuacionAytoAbierto) {
            return collect();
        }

        return Prestacion::activas()->orderBy('nombre')->get(['id', 'nombre', 'codigo']);
    }

    /**
     * Listado de profesionales disponibles para el selector de participantes.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function usuariosProfesionales(): Collection
    {
        if (! $this->modalParticipanteAbierto) {
            return collect();
        }

        return User::orderBy('name')->get(['id', 'name']);
    }

    // =========================================================
    // ACCIONES — DRAWER
    // =========================================================

    /**
     * Abre el drawer de selección de fichas del historial.
     */
    public function abrirDrawer(): void
    {
        $this->drawerAbierto = true;
    }

    /**
     * Cierra el drawer de selección de fichas.
     */
    public function cerrarDrawer(): void
    {
        $this->drawerAbierto = false;
    }

    /**
     * Aplica la selección de fichas al diagnóstico del plan.
     * wire:model actualiza fichasSeleccionadas antes de que este método se ejecute.
     * Pide motivo si el plan ya está firmado.
     *
     * @return void
     */
    public function aplicarSeleccionFichas(): void
    {
        if (! $this->plan) {
            return;
        }

        // wire:model devuelve strings desde el value del checkbox; normalizar a int
        $fichasNuevas = array_map('intval', $this->fichasSeleccionadas);
        $fichasEnBd   = $this->plan->fichasDiagnostico()->pluck('ficha_id')->map(fn ($id) => (int) $id)->toArray();

        $anadir   = array_diff($fichasNuevas, $fichasEnBd);
        $eliminar = array_diff($fichasEnBd, $fichasNuevas);

        if (empty($anadir) && empty($eliminar)) {
            $this->cerrarDrawer();

            return;
        }

        $this->_aplicarFichas($fichasNuevas);
        $this->cerrarDrawer();
    }

    /**
     * Sincroniza las fichas seleccionadas en la tabla pivote comparando contra el estado en BD.
     * wire:model ya actualiza fichasSeleccionadas antes de que se ejecute este método,
     * por eso la comparación se hace contra la BD y no contra la propiedad en memoria.
     *
     * @param array<int> $fichas IDs de fichas a vincular (como enteros).
     * @return void
     */
    private function _aplicarFichas(array $fichas): void
    {
        $fichasInt  = array_map('intval', $fichas);
        $fichasEnBd = $this->plan->fichasDiagnostico()
            ->pluck('ficha_id')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        $eliminar = array_diff($fichasEnBd, $fichasInt);
        if ($eliminar) {
            $this->plan->fichasDiagnostico()
                ->whereIn('ficha_id', $eliminar)
                ->delete();
        }

        $anadir = array_diff($fichasInt, $fichasEnBd);
        foreach ($anadir as $fichaId) {
            $this->plan->fichasDiagnostico()->firstOrCreate(['ficha_id' => $fichaId]);
        }

        $this->fichasSeleccionadas = $fichasInt;
        unset($this->fichasDiagnostico);
    }

    /**
     * Elimina una ficha del diagnóstico del plan.
     *
     * @param int $fichaId
     * @return void
     */
    public function eliminarFichaDiagnostico(int $fichaId): void
    {
        if (! $this->plan) {
            return;
        }

        $this->plan->fichasDiagnostico()->where('ficha_id', $fichaId)->delete();
        $this->fichasSeleccionadas = array_values(
            array_filter($this->fichasSeleccionadas, fn ($id) => (int) $id !== $fichaId)
        );
        unset($this->fichasDiagnostico);
    }

    // =========================================================
    // ACCIONES — DIAGNÓSTICO TEXTO
    // =========================================================

    /**
     * Guarda el texto de síntesis del diagnóstico social.
     *
     * @return void
     */
    public function guardarDiagnostico(): void
    {
        if (! $this->plan) {
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
     *
     * @return void
     */
    public function guardarSeguimiento(): void
    {
        if (! $this->plan) {
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
     */
    public function marcarFirmaProfesional(bool $valor): void
    {
        if (! $this->plan || $this->plan->estado === EstadoPlan::Cerrado) {
            return;
        }

        $this->profesionalFirmado = $valor;
        $this->_actualizarOServicioFirma([
            'profesional_firmado' => $valor,
            'profesional_firmado_en' => $valor ? now() : null,
        ]);
    }

    /**
     * Registra o revoca la firma del ciudadano.
     */
    public function marcarFirmaCiudadano(bool $valor): void
    {
        if (! $this->plan || $this->plan->estado === EstadoPlan::Cerrado) {
            return;
        }

        $this->ciudadanoFirmado = $valor;
        $this->_actualizarOServicioFirma([
            'ciudadano_firmado' => $valor,
            'ciudadano_firmado_en' => $valor ? now() : null,
        ]);
    }

    /**
     * Guarda la fecha de la firma presencial en el registro de firmas.
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
     */
    public function activarPlan(): void
    {
        if (! $this->plan || ! $this->puedeActivarse) {
            return;
        }
        $this->authorize('update', $this->plan);

        $this->plan->update([
            'estado' => 'activo',
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
     */
    private function encolarAccion(string $accion, array $params): void
    {
        $this->motivoAccionPendiente = $accion;
        $this->motivoAccionParams = $params;
        $this->motivoTexto = '';
        $this->modalMotivoAbierto = true;
    }

    /**
     * Confirma el cambio con motivo, registra en historial y ejecuta la acción.
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
            'guardarPlan' => $this->_guardarPlanDirecto(),
            default       => null,
        };

        $this->modalMotivoAbierto = false;
        $this->motivoAccionPendiente = '';
        $this->motivoAccionParams = [];
        $this->motivoTexto = '';
        $this->mensajeExito = 'Cambio registrado.';
        $this->plan = $this->plan->fresh();
        unset($this->fichasDiagnostico);
    }

    /**
     * Cancela el cambio pendiente y cierra el modal sin persistir.
     */
    public function cancelarCambio(): void
    {
        $this->modalMotivoAbierto = false;
        $this->motivoAccionPendiente = '';
        $this->motivoAccionParams = [];
        $this->motivoTexto = '';
    }



    // =========================================================
    // ACCIONES — GUARDAR PLAN COMPLETO
    // =========================================================

    /**
     * Guarda en bloque el diagnóstico y las condiciones de seguimiento.
     * Si el plan está firmado y hay cambios, abre el modal de motivo.
     *
     * @return void
     */
    public function guardarPlan(): void
    {
        if (! $this->plan) {
            return;
        }

        $diagnosticoCambiado  = $this->diagnosticoTexto !== ($this->plan->diagnostico_social ?? '');
        $seguimientoCambiado  = $this->periodicidadSeguimiento !== ($this->plan->periodicidad_seguimiento ?? '');

        if ($this->planFirmado && ($diagnosticoCambiado || $seguimientoCambiado)) {
            $this->encolarAccion('guardarPlan', []);

            return;
        }

        $this->_guardarPlanDirecto();
        $this->mensajeExito = 'Plan guardado.';
    }

    /**
     * Persiste diagnóstico y condiciones de seguimiento sin verificar estado del plan.
     *
     * @return void
     */
    private function _guardarPlanDirecto(): void
    {
        $this->plan->update([
            'diagnostico_social'       => $this->diagnosticoTexto,
            'periodicidad_seguimiento' => $this->periodicidadSeguimiento,
        ]);
        $this->_actualizarOServicioFirma(['observaciones_seguimiento' => $this->observacionesSeguimiento]);
        $this->plan = $this->plan->fresh();
    }

    // =========================================================
    // ACCIONES — OBJETIVOS
    // =========================================================

    /**
     * Abre el modal de creación de un objetivo general, priorizando el modo catálogo si hay objetivos disponibles.
     *
     * @return void
     */
    public function abrirModalObjetivo(): void
    {
        $this->nuevoObjetivoTexto = '';
        $this->objetivosCatalogoSeleccionados = [];
        $this->modoObjetivo = 'catalogo';
        $this->resetErrorBag();
        $this->modalObjetivoAbierto = true;
        unset($this->objetivosCatalogo);
    }

    /**
     * Instancia en el plan los objetivos generales seleccionados del catálogo,
     * incluyendo sus específicos e indicadores.
     *
     * @return void
     */
    public function guardarObjetivosDesdeCatalogo(): void
    {
        if (! $this->plan || empty($this->objetivosCatalogoSeleccionados)) {
            return;
        }

        // IDs de catálogo ya presentes en el plan para evitar duplicados
        $yaEnPlan = PlanObjetivo::where('plan_id', $this->plan->id)
            ->whereNotNull('objetivo_catalogo_id')
            ->pluck('objetivo_catalogo_id')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        $orden = $this->objetivosGenerales->count();

        foreach ($this->objetivosCatalogoSeleccionados as $catalogoId) {
            $catalogoId = (int) $catalogoId;

            if (in_array($catalogoId, $yaEnPlan, true)) {
                continue;
            }

            $objCatalogo = ObjetivoCatalogo::with(['objetivosEspecificos.indicador', 'indicador'])
                ->find($catalogoId);

            if (! $objCatalogo || $objCatalogo->nivel !== 'general') {
                continue;
            }

            $planObjetivo = PlanObjetivo::create([
                'plan_id'              => $this->plan->id,
                'objetivo_catalogo_id' => $objCatalogo->id,
                'nivel'                => 'general',
                'texto'                => $objCatalogo->texto,
                'estado'               => 'pendiente',
                'orden'                => ++$orden,
            ]);

            // Heredar indicador del catálogo si existe
            if ($objCatalogo->indicador) {
                $planObjetivo->setRelation('objetivoCatalogo', $objCatalogo);
                $planObjetivo->instanciarIndicador();
            }

            // Instanciar específicos
            foreach ($objCatalogo->objetivosEspecificos as $esp) {
                $planEsp = PlanObjetivo::create([
                    'plan_id'              => $this->plan->id,
                    'objetivo_catalogo_id' => $esp->id,
                    'objetivo_general_id'  => $planObjetivo->id,
                    'nivel'                => 'especifico',
                    'tipo_ficha_id'        => $esp->tipo_ficha_id,
                    'texto'                => $esp->texto,
                    'estado'               => 'pendiente',
                    'orden'                => $esp->orden,
                ]);

                if ($esp->indicador) {
                    $planEsp->setRelation('objetivoCatalogo', $esp);
                    $planEsp->instanciarIndicador();
                }
            }
        }

        $añadidos = count($this->objetivosCatalogoSeleccionados);
        $this->modalObjetivoAbierto = false;
        $this->objetivosCatalogoSeleccionados = [];
        unset($this->objetivosGenerales, $this->objetivosConIndicadores);
        $this->mensajeExito = $añadidos === 1 ? 'Objetivo añadido.' : 'Objetivos añadidos.';
    }

    /**
     * Persiste un nuevo objetivo general de texto libre en el plan.
     *
     * @return void
     */
    public function guardarObjetivo(): void
    {
        if (! $this->plan) {
            return;
        }

        $this->validate(['nuevoObjetivoTexto' => 'required|string|min:3|max:500']);

        PlanObjetivo::create([
            'plan_id' => $this->plan->id,
            'nivel'   => 'general',
            'texto'   => trim($this->nuevoObjetivoTexto),
            'estado'  => 'pendiente',
            'orden'   => $this->objetivosGenerales->count() + 1,
        ]);

        $this->modalObjetivoAbierto = false;
        $this->nuevoObjetivoTexto = '';
        unset($this->objetivosGenerales, $this->objetivosConIndicadores);
        $this->mensajeExito = 'Objetivo añadido.';
    }

    // =========================================================
    // ACCIONES — COMPROMISOS CIUDADANO
    // =========================================================

    /**
     * Abre el modal de creación de un compromiso del ciudadano.
     *
     * @return void
     */
    public function abrirModalCompromiso(): void
    {
        $this->nuevoCompromisoDescripcion = '';
        $this->resetErrorBag();
        $this->modalCompromisoAbierto = true;
    }

    /**
     * Persiste un nuevo compromiso del ciudadano en el plan.
     *
     * @return void
     */
    public function guardarCompromisoCiudadano(): void
    {
        if (! $this->plan) {
            return;
        }

        $this->validate(['nuevoCompromisoDescripcion' => 'required|string|min:3|max:500']);

        PlanActuacionCiudadano::create([
            'plan_id'     => $this->plan->id,
            'descripcion' => trim($this->nuevoCompromisoDescripcion),
            'estado'      => 'pendiente',
            'orden'       => $this->actuacionesCiudadano->count() + 1,
        ]);

        $this->modalCompromisoAbierto = false;
        $this->nuevoCompromisoDescripcion = '';
        unset($this->actuacionesCiudadano);
        $this->mensajeExito = 'Compromiso añadido.';
    }

    // =========================================================
    // ACCIONES — ACTUACIONES AYUNTAMIENTO
    // =========================================================

    /**
     * Abre el modal de creación de una actuación del Ayuntamiento.
     *
     * @return void
     */
    public function abrirModalActuacionAyto(): void
    {
        $this->nuevaActuacionPrestacionId = null;
        $this->nuevaActuacionDescripcion = '';
        $this->resetErrorBag();
        $this->modalActuacionAytoAbierto = true;
    }

    /**
     * Persiste una nueva actuación del Ayuntamiento vinculada a una prestación.
     *
     * @return void
     */
    public function guardarActuacionAyto(): void
    {
        if (! $this->plan) {
            return;
        }

        $this->validate(['nuevaActuacionPrestacionId' => 'required|exists:prestaciones,id']);

        PlanActuacionAyuntamiento::create([
            'plan_id'                => $this->plan->id,
            'prestacion_id'          => $this->nuevaActuacionPrestacionId,
            'descripcion_especifica' => $this->nuevaActuacionDescripcion ?: null,
            'estado'                 => 'pendiente',
            'orden'                  => $this->actuacionesAyuntamiento->count() + 1,
        ]);

        $this->modalActuacionAytoAbierto = false;
        $this->nuevaActuacionPrestacionId = null;
        $this->nuevaActuacionDescripcion = '';
        unset($this->actuacionesAyuntamiento);
        $this->mensajeExito = 'Actuación añadida.';
    }

    // =========================================================
    // ACCIONES — PARTICIPANTES
    // =========================================================

    /**
     * Abre el modal de adición de un profesional participante.
     *
     * @return void
     */
    public function abrirModalParticipante(): void
    {
        $this->nuevoParticipanteUserId = null;
        $this->nuevoParticipanteRol = '';
        $this->resetErrorBag();
        $this->modalParticipanteAbierto = true;
    }

    /**
     * Persiste un nuevo participante profesional en el plan.
     *
     * @return void
     */
    public function guardarParticipante(): void
    {
        if (! $this->plan) {
            return;
        }

        $this->validate([
            'nuevoParticipanteUserId' => 'required|exists:users,id',
            'nuevoParticipanteRol'    => 'required|string|min:2|max:200',
        ]);

        PlanParticipante::create([
            'plan_id'      => $this->plan->id,
            'user_id'      => $this->nuevoParticipanteUserId,
            'rol_en_plan'  => trim($this->nuevoParticipanteRol),
            'fecha_inicio' => now()->toDateString(),
        ]);

        $this->modalParticipanteAbierto = false;
        $this->nuevoParticipanteUserId = null;
        $this->nuevoParticipanteRol = '';
        unset($this->participantes);
        $this->mensajeExito = 'Participante añadido.';
    }

    // =========================================================
    // ACCIONES — CIERRE DEL PLAN
    // =========================================================

    /**
     * Abre el modal de cierre del plan limpiando el estado previo.
     *
     * @return void
     */
    public function abrirModalCierre(): void
    {
        $this->modalCierreAbierto = true;
        $this->motivoCierre = '';
        $this->notasCierre = '';
    }

    /**
     * Cierra el modal de cierre sin persistir ningún cambio.
     *
     * @return void
     */
    public function cerrarModalCierre(): void
    {
        $this->modalCierreAbierto = false;
    }

    /**
     * Cierra el plan con el motivo seleccionado y registra el cambio en el historial.
     * Si hay notas y el plan tiene historia_id, crea un apunte privado.
     *
     * @return void
     */
    public function confirmarCierrePlan(): void
    {
        if (! $this->plan || empty($this->motivoCierre)) {
            return;
        }
        $this->authorize('update', $this->plan);

        $this->plan->registrarCambio(
            auth()->id(),
            'Cierre del plan: ' . ($this->motivosCierre[$this->motivoCierre] ?? $this->motivoCierre)
                . ($this->notasCierre ? ". {$this->notasCierre}" : ''),
            'discrecional'
        );

        $this->plan->update([
            'estado'        => 'cerrado',
            'fecha_cierre'  => now()->toDateString(),
            'motivo_cierre' => $this->motivoCierre,
        ]);

        if ($this->notasCierre && $this->plan->historia_id) {
            \Modules\Intervencion\Models\Apunte::create([
                'historia_id'    => $this->plan->historia_id,
                'profesional_id' => auth()->id(),
                'tipo'           => 'nota',
                'contenido'      => "Cierre del plan: {$this->notasCierre}",
            ]);
        }

        $this->modalCierreAbierto = false;
        $this->mensajeExito = 'Plan cerrado correctamente.';
        $this->plan = $this->plan->fresh();
        unset($this->objetivosGenerales, $this->objetivosConIndicadores);
    }

    // =========================================================
    // ACCIONES — VALORACIÓN DE INDICADORES
    // =========================================================

    /**
     * Guarda la valoración de un indicador del plan.
     * Verifica que el indicador pertenece al plan antes de persistir.
     *
     * @param  int    $indicadorId ID de PlanObjetivoIndicador
     * @param  string $valor       Valor de la valoración
     * @return void
     */
    public function guardarValoracionIndicador(int $indicadorId, string $valor): void
    {
        if (! $this->plan) {
            return;
        }

        $indicador = PlanObjetivoIndicador::findOrFail($indicadorId);

        if ($indicador->planObjetivo->plan_id !== $this->plan->id) {
            return;
        }

        $indicador->registrarValoracion($valor);
        $this->valoracionesIndicadores[$indicadorId] = $valor;
        $this->mensajeExito = 'Valoración guardada.';
    }

    // =========================================================
    // GENERACIÓN PDF
    // =========================================================

    /**
     * Genera y descarga el PDF del plan de intervención.
     */
    public function generarPdf(): StreamedResponse
    {
        if (! $this->plan) {
            abort(404);
        }
        $this->authorize('view', $this->plan);

        $service = app(PlanPdfService::class);

        return response()->streamDownload(
            fn () => print ($service->generar($this->plan)),
            $service->nombre($this->plan),
            ['Content-Type' => 'application/pdf']
        );
    }

    // =========================================================
    // RENDER
    // =========================================================

    /**
     * Renderiza la vista del plan de intervención con el layout operativo.
     */
    public function render(): View
    {
        return view('intervencion::livewire.plan-page')
            ->layout('layouts.operativo');
    }
}
