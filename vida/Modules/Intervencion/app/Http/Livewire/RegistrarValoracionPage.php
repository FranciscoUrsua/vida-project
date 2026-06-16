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

    /** @var string|null Estado tras guardar: 'guardado' o null */
    public ?string $estadoGuardado = null;

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
     * Valida los campos obligatorios y persiste la ficha vinculada a la historia.
     * Si ya existe una ficha para esta historia y tipo, la actualiza (idempotente).
     */
    public function guardar(): void
    {
        if (! $this->tipoFichaId) {
            $this->addError('tipoFichaId', 'Selecciona un tipo de ficha.');

            return;
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

        // TODO: vincular a Valoracion cuando ese flujo esté completo
        Ficha::updateOrCreate(
            [
                'historia_id'   => $this->historiaId,
                'tipo_ficha_id' => $this->tipoFichaId,
            ],
            [
                'datos'      => $this->datos ?: null,
                'notas'      => $this->notas ?: null,
                'completada' => false,
            ]
        );

        $this->estadoGuardado = 'guardado';
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Inicializa $datos con null para cada campo del schema.
     * Solo añade claves nuevas; preserva valores ya introducidos.
     */
    private function inicializarDatos(): void
    {
        foreach ($this->tipoFicha?->schema['campos'] ?? [] as $campo) {
            if (! array_key_exists($campo['id'], $this->datos)) {
                $this->datos[$campo['id']] = null;
            }
        }
    }

    public function render(): View
    {
        return view('intervencion::livewire.registrar-valoracion-page');
    }
}
