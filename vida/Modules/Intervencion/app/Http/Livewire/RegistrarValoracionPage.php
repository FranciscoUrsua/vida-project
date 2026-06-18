<?php

namespace Modules\Intervencion\Http\Livewire;

use App\Models\HistoriaSocial;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Intervencion\Models\Ficha;
use Modules\Intervencion\Models\TipoFicha;

/**
 * Pantalla completa para registrar una ficha de valoración sobre la Historia Social.
 *
 * Carga el schema del TipoFicha seleccionado y renderiza los campos dinámicamente
 * según su tipo (texto, numero, select, booleano, fecha, escala).
 * Persiste los datos en `fichas` vinculada directamente a la historia mediante
 * historia_id (sin requerir Valoracion formal previa — TODO: vincular cuando esté completo).
 *
 * @see docs/instrucciones-cli/valoracion-page-implementacion.md
 */
#[Layout('layouts.operativo')]
class RegistrarValoracionPage extends Component
{
    /**
     * ID de la HistoriaSocial. Se usa int en lugar del modelo para evitar
     * que AmbitoUoScope interfiera durante la serialización de Livewire.
     *
     * @var int
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

    /** @var string|null Estado tras guardar: 'borrador', 'completado' o null */
    public ?string $estadoGuardado = null;

    /**
     * Inicializa la pantalla con la historia y los parámetros de ficha/entrevista.
     *
     * @param HistoriaSocial $historia Historia social del ciudadano.
     *
     * @return void
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
     *
     * @return TipoFicha|null
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
     *
     * @return void
     */
    public function seleccionarFicha(int $id): void
    {
        $this->tipoFichaId    = $id;
        $this->datos          = [];
        $this->notas          = '';
        $this->estadoGuardado = null;
        $this->inicializarDatos();
    }

    /**
     * Guarda la ficha como borrador (completada = false). No redirige.
     *
     * @return void
     */
    public function guardar(): void
    {
        $this->persistirFicha(completada: false);
        $this->estadoGuardado = 'borrador';
    }

    /**
     * Valida campos obligatorios, marca la ficha como completada y vuelve a la historia.
     *
     * @return \Livewire\Features\SupportRedirects\Redirector|\Illuminate\Http\RedirectResponse
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

        $this->persistirFicha(completada: true);

        return $this->redirect(route('intervencion.ciudadano.show', $this->historiaId), navigate: true);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Persiste la ficha (crea o actualiza el borrador vigente) con el estado indicado.
     * Si completada = true, crea un nuevo registro independiente del borrador existente.
     *
     * @param bool $completada
     *
     * @return void
     */
    private function persistirFicha(bool $completada): void
    {
        if (! $this->tipoFichaId) {
            return;
        }

        if ($completada) {
            // Cierra el borrador si existe y crea la ficha definitiva como registro nuevo
            Ficha::where('historia_id', $this->historiaId)
                ->where('tipo_ficha_id', $this->tipoFichaId)
                ->where('completada', false)
                ->update([
                    'schema_snapshot' => $this->tipoFicha?->schema,
                    'datos'           => $this->datos ?: null,
                    'notas'           => $this->notas ?: null,
                    'completada'      => true,
                    'profesional_id'  => auth()->id(),
                ]);
        } else {
            // TODO: vincular a Valoracion cuando ese flujo esté completo
            Ficha::updateOrCreate(
                [
                    'historia_id'   => $this->historiaId,
                    'tipo_ficha_id' => $this->tipoFichaId,
                    'completada'    => false,
                ],
                [
                    'schema_snapshot' => $this->tipoFicha?->schema,
                    'datos'           => $this->datos ?: null,
                    'notas'           => $this->notas ?: null,
                    'completada'      => false,
                    'profesional_id'  => auth()->id(),
                ]
            );
        }
    }

    /**
     * Inicializa $datos con null para cada campo del schema y carga los
     * valores guardados previamente si existe una Ficha en BD para esta
     * historia y tipo de ficha.
     */
    private function inicializarDatos(): void
    {
        foreach ($this->tipoFicha?->schema['campos'] ?? [] as $campo) {
            if (! array_key_exists($campo['id'], $this->datos)) {
                $this->datos[$campo['id']] = null;
            }
        }

        if (! $this->tipoFichaId || ! $this->historiaId) {
            return;
        }

        $ficha = Ficha::where('historia_id', $this->historiaId)
            ->where('tipo_ficha_id', $this->tipoFichaId)
            ->where('completada', false)
            ->first();

        if (! $ficha) {
            return;
        }

        foreach ($ficha->datos ?? [] as $key => $valor) {
            $this->datos[$key] = $valor;
        }

        if ($ficha->notas !== null) {
            $this->notas = $ficha->notas;
        }
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
