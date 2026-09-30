<?php

namespace Modules\Agenda\Livewire\Citas\Concerns;

use App\Models\Ciudadano;
use Illuminate\Support\Facades\Auth;
use Modules\Agenda\Services\Citas\BuscadorPersonasCita;

/**
 * Búsqueda de persona en las pantallas de citación (cita directa e
 * identificación de citas externas). Pinta con el parcial
 * `agenda::livewire.citas.partials.buscar-persona`.
 */
trait BuscaPersonas
{
    /** @var string Documento o nombre buscado. */
    public string $busquedaPersona = '';

    /** @var list<array{id: int, nombre: string, documento: string|null, telefono: string|null}>|null Resultados; null si no se ha buscado. */
    public ?array $personas = null;

    /**
     * Busca personas por documento o nombre.
     *
     * @return void
     */
    public function buscarPersona(): void
    {
        $buscador = app(BuscadorPersonasCita::class);
        $usuario = Auth::user();

        $this->personas = $buscador->buscar($this->busquedaPersona, $usuario)
            ->map(fn (Ciudadano $c) => [
                'id' => $c->id,
                'nombre' => $c->nombre_completo,
                'documento' => $c->documentoVigente?->valor,
                'telefono' => $buscador->telefonoVisible($c, $usuario),
            ])
            ->all();
    }

    /**
     * Limpia la búsqueda.
     *
     * @return void
     */
    public function limpiarBusquedaPersona(): void
    {
        $this->busquedaPersona = '';
        $this->personas = null;
    }
}
