<?php

namespace Modules\Intervencion\Http\Livewire;

use App\Models\HistoriaSocial;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Intervencion\Enums\TipoApunte;
use Modules\Intervencion\Enums\TipoPlan;
use Modules\Intervencion\Enums\VisibilidadApunte;
use Modules\Intervencion\Models\Apunte;
use Modules\Intervencion\Models\Ficha;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Intervencion\Models\TipoFicha;

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
        $this->historiaId = $historia->id;

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
        return TipoFicha::activos()->orderBy('nombre')->pluck('nombre', 'id')->toArray();
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
     * Valida campos obligatorios, marca la ficha como completada, registra el apunte
     * en la Historia Social y redirige al expediente del ciudadano.
     */
    public function guardarDefinitivo(): mixed
    {
        if (! $this->tipoFichaId) {
            $this->addError('tipoFichaId', 'Selecciona un tipo de ficha.');

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

        $ficha = $this->persistirFicha();
        $this->registrarApunte($ficha);

        return $this->redirect(route('intervencion.ciudadano.show', $this->historiaId), navigate: true);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Crea una nueva Ficha completada y la devuelve.
     */
    private function persistirFicha(): Ficha
    {
        return Ficha::create([
            'historia_id' => $this->historiaId,
            'tipo_ficha_id' => $this->tipoFichaId,
            'schema_snapshot' => $this->tipoFicha?->schema,
            'datos' => $this->datos ?: null,
            'notas' => $this->notas ?: null,
            'completada' => true,
            'profesional_id' => auth()->id(),
        ]);
    }

    /**
     * Crea un apunte de tipo Valoración en la Historia Social.
     * Si hay un plan en curso, se vincula también al plan.
     *
     * @param Ficha $ficha Ficha recién guardada como definitiva.
     */
    private function registrarApunte(Ficha $ficha): void
    {
        $plan = PlanDeIntervencion::withoutGlobalScopes()
            ->where('historia_id', $this->historiaId)
            ->where('tipo', TipoPlan::GeneralAsp)
            ->whereIn('estado', ['borrador', 'activo', 'en_revision'])
            ->latest()
            ->first();

        Apunte::create([
            'historia_id' => $this->historiaId,
            'plan_id' => $plan?->id,
            'autor_id' => auth()->id(),
            'fecha' => today()->toDateString(),
            'tipo' => TipoApunte::Valoracion,
            'apuntable_type' => Ficha::class,
            'apuntable_id' => $ficha->id,
            'contenido' => $this->tipoFicha?->nombre,
            'visibilidad' => VisibilidadApunte::Profesionales,
        ]);
    }

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
