<?php

namespace Modules\Intervencion\Http\Livewire;

use App\Models\HistoriaSocial;
use App\Services\AccesoExpediente;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Agenda\Services\Citas\AtencionCitaService;
use Modules\Centro\Services\Asignacion\CentroDeUsuario;
use Modules\Centro\Services\ConfiguracionEfectivaCentro;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Intervencion\Models\TipoFicha;
use Modules\Intervencion\Services\Actuaciones\ContextoActuacion;
use Modules\Intervencion\Services\Actuaciones\ValoracionService;

/**
 * Pantalla completa para registrar una ficha de valoración sobre la Historia Social.
 *
 * Carga el schema del TipoFicha seleccionado y renderiza los campos dinámicamente
 * según su tipo (texto, numero, select, booleano, fecha, escala).
 * Al guardar definitivamente, crea un apunte de tipo Valoración en el plan activo
 * de la Historia Social para dejar constancia en el timeline.
 *
 * @see docs/instrucciones-cli/valoracion-page-implementacion.md
 *
 * @property-read TipoFicha|null $tipoFicha
 */
#[Layout('layouts.operativo')]
class RegistrarValoracionPage extends Component
{
    /**
     * ID de la HistoriaSocial. Se usa int en lugar del modelo para evitar
     * que AmbitoUoScope interfiera durante la serialización de Livewire.
     */
    public int $historiaId;

    /** @var int|null ID del TipoFicha seleccionado */
    public ?int $tipoFichaId = null;

    /** @var int|null Entrevista que origina esta valoración */
    public ?int $entrevistaId = null;

    /** @var int|null Cita que atiende la valoración (?cita=, desde la ficha o la agenda). */
    #[Url(as: 'cita', except: null)]
    public ?int $citaId = null;

    /** @var array<string, mixed> Valores del formulario dinámico, indexados por campo id */
    public array $datos = [];

    /** @var string Notas libres del profesional sobre la valoración */
    public string $notas = '';

    /**
     * Inicializa la pantalla con la historia y los parámetros de ficha/entrevista.
     *
     * @param HistoriaSocial $historia Historia social del ciudadano.
     */
    public function mount(HistoriaSocial $historia): void
    {
        // El binding de {historia} carga sin scope: policy y auditoría antes de leer nada
        $this->historiaId = app(AccesoExpediente::class)->historia(auth()->user(), $historia->id)->id;

        // R1: una valoración es una actuación; sin centro no se abre el formulario
        app(CentroDeUsuario::class)->exigirActuacionAlAbrir(auth()->user());

        // Livewire 4 full-page: query string no llega a mount(), se lee directamente.
        $tipoFicha = request()->query('tipo_ficha');
        if ($tipoFicha && is_numeric($tipoFicha)) {
            $this->tipoFichaId = (int) $tipoFicha;
        }

        $entrevista = request()->query('entrevista');
        $this->entrevistaId = $entrevista ? (int) $entrevista : null;

        $this->inicializarDatos();
    }

    // -------------------------------------------------------------------------
    // Computed
    // -------------------------------------------------------------------------

    /**
     * TipoFicha actualmente seleccionado, null si no hay selección.
     */
    #[Computed]
    public function tipoFicha(): ?TipoFicha
    {
        if (! $this->tipoFichaId) {
            return null;
        }

        return TipoFicha::find($this->tipoFichaId);
    }

    /**
     * Fichas activas disponibles para el selector, indexadas por id.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function fichasDisponibles(): array
    {
        // Solo las fichas del centro en que se atiende (docs/modulo-tipo-centro.md §6.2)
        $centro = app(CentroDeUsuario::class)->centroActivo(auth()->user());
        if ($centro !== null) {
            return app(ConfiguracionEfectivaCentro::class)->tiposFicha($centro)->sortBy('nombre')->pluck('nombre', 'id')->toArray();
        }

        return TipoFicha::activos()->deValoracion()->orderBy('nombre')->pluck('nombre', 'id')->toArray();
    }

    // -------------------------------------------------------------------------
    // Acciones
    // -------------------------------------------------------------------------

    /**
     * Cambia la ficha seleccionada y reinicializa el formulario.
     *
     * @param int $id ID del tipo de ficha seleccionado.
     */
    public function seleccionarFicha(int $id): void
    {
        $this->tipoFichaId = $id;
        $this->datos = [];
        $this->notas = '';
        $this->inicializarDatos();
    }

    /**
     * Valida campos obligatorios, guarda la ficha completada con su apunte en la Historia
     * Social (ValoracionService) y redirige al expediente del ciudadano.
     *
     * @return mixed
     */
    public function guardarDefinitivo(): mixed
    {
        if (! $this->tipoFichaId) {
            $this->addError('tipoFichaId', 'Selecciona un tipo de ficha.');

            return null;
        }

        // En el servidor, no solo en el selector
        if (! array_key_exists((int) $this->tipoFichaId, $this->fichasDisponibles)) {
            $this->addError('tipoFichaId', 'Esa ficha de valoración no está disponible en este centro.');

            return null;
        }

        $campos = $this->tipoFicha?->schema['campos'] ?? [];
        $reglas = [];

        foreach ($campos as $campo) {
            if ($campo['obligatorio'] ?? false) {
                $reglas["datos.{$campo['id']}"] = ['required'];
            }
        }

        if (! empty($reglas)) {
            $this->validate($reglas);
        }

        $historia = app(AccesoExpediente::class)->historia(auth()->user(), $this->historiaId, registrar: false);

        // Si hay un plan principal en curso, el apunte se vincula a él (por su tipo de plan, F3-1)
        $plan = PlanDeIntervencion::withoutGlobalScopes()
            ->where('historia_id', $this->historiaId)
            ->principal()
            ->whereIn('estado', ['borrador', 'activo', 'en_revision'])
            ->latest()
            ->first();

        // Solo si sigue siendo una cita vinculable de esta persona con este profesional
        $citaId = $this->citaId !== null
            ? app(AtencionCitaService::class)->citaVinculable($historia->ciudadano_id, auth()->user(), $this->citaId)?->id
            : null;

        // RN-15 se avisa con el toast de siempre (DomainException) y no queda nada a medias
        app(ValoracionService::class)->registrar(
            ContextoActuacion::desdeHistoria($historia, auth()->user(), $plan?->id, $citaId),
            $this->tipoFicha,
            $this->datos,
            $this->notas,
        );

        return $this->redirect(route('intervencion.ciudadano.show', $this->historiaId), navigate: true);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Inicializa $datos con null para cada campo del schema del tipo seleccionado.
     */
    private function inicializarDatos(): void
    {
        foreach ($this->tipoFicha?->schema['campos'] ?? [] as $campo) {
            if (! array_key_exists($campo['id'], $this->datos)) {
                $this->datos[$campo['id']] = null;
            }
        }
    }

    /**
     * Renderiza la pantalla de registro de valoración.
     */
    public function render(): View
    {
        return view('intervencion::livewire.registrar-valoracion-page');
    }
}
