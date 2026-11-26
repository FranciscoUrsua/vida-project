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
use Modules\Intervencion\Http\Livewire\Concerns\FormularioValoracion;
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
 * de la Historia Social para dejar constancia en el timeline. El formulario lo comparte con
 * la valoración desde un encargo (FormularioValoracion).
 *
 * @see docs/instrucciones-cli/valoracion-page-implementacion.md
 */
#[Layout('layouts.operativo')]
class RegistrarValoracionPage extends Component
{
    use FormularioValoracion;

    /**
     * ID de la HistoriaSocial. Se usa int en lugar del modelo para evitar
     * que AmbitoUoScope interfiera durante la serialización de Livewire.
     */
    public int $historiaId;

    /** @var int|null Entrevista que origina esta valoración */
    public ?int $entrevistaId = null;

    /** @var int|null Cita que atiende la valoración (?cita=, desde la ficha o la agenda). */
    #[Url(as: 'cita', except: null)]
    public ?int $citaId = null;

    /**
     * Inicializa la pantalla con la historia y los parámetros de ficha/entrevista.
     *
     * @param HistoriaSocial $historia Historia social del ciudadano.
     *
     * @return void
     */
    public function mount(HistoriaSocial $historia): void
    {
        // El binding de {historia} carga sin scope: policy y auditoría antes de leer nada
        $this->historiaId = app(AccesoExpediente::class)->historia(auth()->user(), $historia->id)->id;

        // R1: una valoración es una actuación; sin centro no se abre el formulario
        app(CentroDeUsuario::class)->exigirActuacionAlAbrir(auth()->user());

        $entrevista = request()->query('entrevista');
        $this->entrevistaId = $entrevista ? (int) $entrevista : null;

        $this->iniciarFormularioValoracion();
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

    /**
     * URL de vuelta: la Historia Social.
     *
     * @return string
     */
    public function volverUrl(): string
    {
        return route('intervencion.ciudadano.show', $this->historiaId);
    }

    /**
     * Texto del enlace de vuelta.
     *
     * @return string
     */
    public function volverTexto(): string
    {
        return 'Volver a la Historia Social';
    }

    /**
     * Valida campos obligatorios, guarda la ficha completada con su apunte en la Historia
     * Social (ValoracionService) y redirige al expediente del ciudadano.
     *
     * @return mixed
     */
    public function guardarDefinitivo(): mixed
    {
        if (! $this->validarFormularioValoracion('Esa ficha de valoración no está disponible en este centro.')) {
            return null;
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

        return $this->redirect($this->volverUrl(), navigate: true);
    }

    /**
     * Renderiza la pantalla de registro de valoración.
     *
     * @return View
     */
    public function render(): View
    {
        return view('intervencion::livewire.registrar-valoracion-page');
    }
}
