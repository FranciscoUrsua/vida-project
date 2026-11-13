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

    /** @var list<array{id: int, nombre: string|null, documento: string|null, telefono: string|null, restringido: bool}>|null Resultados; null si no se ha buscado. */
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

        // Un protegido que no se puede ver sale sin datos personales: solo la restricción
        $this->personas = $buscador->buscar($this->busquedaPersona, $usuario)
            ->map(fn (Ciudadano $c) => $buscador->visible($c, $usuario)
                ? [
                    'id' => $c->id,
                    'nombre' => $c->nombre_completo,
                    'documento' => $c->documentoVigente?->valor,
                    'telefono' => $c->telefono,
                    'restringido' => false,
                ]
                : ['id' => $c->id, 'nombre' => null, 'documento' => null, 'telefono' => null, 'restringido' => true])
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
