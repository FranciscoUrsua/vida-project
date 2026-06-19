<?php

namespace Modules\Intervencion\Http\Livewire;

use App\Models\HistoriaSocial;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Intervencion\Models\Ficha;
use Modules\Intervencion\Models\TipoFicha;

/**
 * Pantalla de solo lectura para visualizar una ficha de valoración completada.
 *
 * Renderiza los campos usando schema_snapshot, por lo que es coherente aunque
 * el TipoFicha haya evolucionado desde que se guardó la ficha.
 *
 * @property Ficha $ficha
 * @property HistoriaSocial $historia
 */
#[Layout('layouts.operativo')]
class VerFichaPage extends Component
{
    /** @var Ficha Ficha que se visualiza */
    public Ficha $ficha;

    /** @var int ID de la Historia Social, para construir el enlace de vuelta */
    public int $historiaId;

    /**
     * Inicializa la página con la historia y la ficha solicitadas.
     *
     * @param HistoriaSocial $historia Historia Social propietaria de la ficha.
     * @param Ficha $ficha Ficha que se desea visualizar.
     */
    public function mount(HistoriaSocial $historia, Ficha $ficha): void
    {
        $this->historiaId = $historia->id;
        $this->ficha = $ficha;
    }

    /**
     * Devuelve los campos del schema_snapshot con su valor correspondiente
     * en datos[], listos para renderizar.
     *
     * @return array<int, array{etiqueta: string, tipo: string, valor: mixed, unidad: string|null}>
     */
    public function camposConValor(): array
    {
        $campos = $this->ficha->schema_snapshot['campos'] ?? [];
        $datos = $this->ficha->datos ?? [];

        return array_map(function (array $campo) use ($datos): array {
            return [
                'etiqueta' => $campo['etiqueta'] ?? $campo['id'],
                'tipo' => $campo['tipo'] ?? 'texto',
                'valor' => $datos[$campo['id']] ?? null,
                'unidad' => $campo['unidad'] ?? null,
                'obligatorio' => $campo['obligatorio'] ?? false,
            ];
        }, $campos);
    }

    /**
     * Nombre del TipoFicha: usa schema_snapshot si está disponible, si no busca en BD.
     */
    public function nombreFicha(): string
    {
        return TipoFicha::find($this->ficha->tipo_ficha_id)?->nombre ?? 'Valoración';
    }

    /**
     * Renderiza la vista de solo lectura de la ficha.
     */
    public function render(): View
    {
        return view('intervencion::livewire.ver-ficha-page');
    }
}
