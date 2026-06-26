<?php

namespace App\Livewire\Centros;

use App\Models\CatalogoSistema;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Modules\Centro\Models\Centro;
use Modules\Prestaciones\Models\Prestacion;

/**
 * Selector interactivo de prestaciones para un centro.
 *
 * Muestra el catálogo completo de prestaciones activas con filtros por
 * segmento de población y búsqueda por texto. Las prestaciones se agrupan
 * por objetivo general usando las etiquetas de catalogos_sistema.
 * La selección se persiste en la tabla pivote centro_prestacion al guardar.
 *
 * @property-read Collection<string, Collection<int, Prestacion>> $prestacionesFiltradas
 * @property-read array<string, string> $segmentosFiltro
 */
class SelectorPrestacionesCentro extends Component
{
    /** @var int ID del centro que se está editando */
    public int $centroId;

    /** @var array<int> IDs de prestaciones actualmente seleccionadas */
    public array $seleccionadas = [];

    /** @var string Texto de búsqueda libre */
    public string $busqueda = '';

    /**
     * Segmento de población activo como filtro.
     * 'todos' significa sin filtro de segmento.
     */
    public string $segmentoActivo = 'todos';

    /** @var int|null ID de la prestación cuya ficha está abierta */
    public ?int $prestacionDetalle = null;

    /**
     * Inicializa el componente cargando las prestaciones ya asociadas al centro.
     *
     * @param int $centroId Identificador del centro.
     */
    public function mount(int $centroId): void
    {
        $this->centroId = $centroId;

        $centro = Centro::findOrFail($centroId);
        $this->seleccionadas = $centro->prestaciones()->pluck('prestaciones.id')->toArray();
    }

    /**
     * Devuelve las opciones de filtro por segmento de población.
     * Se derivan de los segmentos actualmente asociados al centro.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function segmentosFiltro(): array
    {
        $centro = Centro::with('segmentosPoblacion')->find($this->centroId);

        $segmentos = ['todos' => 'Todos'];

        if ($centro && $centro->segmentosPoblacion->isNotEmpty()) {
            foreach ($centro->segmentosPoblacion as $seg) {
                $segmentos[(string) $seg->id] = $seg->nombre;
            }
        }

        return $segmentos;
    }

    /**
     * Devuelve las prestaciones filtradas y agrupadas por nombre de objetivo general.
     * Las etiquetas de objetivo se obtienen de catalogos_sistema.
     *
     * @return Collection<string, Collection<int, Prestacion>>
     */
    #[Computed]
    public function prestacionesFiltradas(): Collection
    {
        $etiquetasObjetivo = CatalogoSistema::opcionesParaSelect('prestacion.objetivo_general');

        $query = Prestacion::activas()
            ->orderBy('objetivo_general')
            ->orderBy('nombre');

        if ($this->busqueda !== '') {
            $busqueda = $this->busqueda;
            $query->where(function ($q) use ($busqueda) {
                $q->where('codigo', 'ilike', "%{$busqueda}%")
                    ->orWhere('nombre', 'ilike', "%{$busqueda}%");
            });
        }

        // TODO (BACKLOG): filtro por segmento de población no implementado.
        // La relación Prestacion <-> SegmentoPoblacion no existe en el modelo actual.
        // El campo poblacion_destinataria es un array JSONB de claves de catalogos_sistema,
        // no una FK a segmentos_poblacion. El filtro requiere mapear claves de catalogos_sistema
        // a segmentos del centro. Ver BACKLOG.md.

        /** @var Collection<string, Collection<int, Prestacion>> $prestacionesAgrupadas */
        $prestacionesAgrupadas = $query->get()
            ->groupBy(function (Prestacion $prestacion) use ($etiquetasObjetivo): string {
                $clave = $prestacion->objetivo_general;

                return $etiquetasObjetivo[$clave] ?? ($clave ?? 'Sin clasificar');
            });

        return $prestacionesAgrupadas;
    }

    /**
     * Alterna la selección de una prestación.
     *
     * @param int $prestacionId Identificador de la prestación.
     */
    public function togglePrestacion(int $prestacionId): void
    {
        if (in_array($prestacionId, $this->seleccionadas, true)) {
            $this->seleccionadas = array_values(
                array_filter($this->seleccionadas, fn ($id) => $id !== $prestacionId)
            );
        } else {
            $this->seleccionadas[] = $prestacionId;
        }
    }

    /**
     * Elimina una prestación del panel de seleccionadas.
     *
     * @param int $prestacionId Identificador de la prestación.
     */
    public function deseleccionar(int $prestacionId): void
    {
        $this->seleccionadas = array_values(
            array_filter($this->seleccionadas, fn ($id) => $id !== $prestacionId)
        );
    }

    /**
     * Activa el filtro de segmento de población.
     *
     * @param string $segmento Segmento seleccionado.
     */
    public function setSegmento(string $segmento): void
    {
        $this->segmentoActivo = $segmento;
    }

    /**
     * Abre la ficha de detalle de una prestación.
     *
     * @param int $prestacionId Identificador de la prestación.
     */
    public function verDetalle(int $prestacionId): void
    {
        $this->prestacionDetalle = $prestacionId;
    }

    /**
     * Cierra la ficha de detalle.
     */
    public function cerrarDetalle(): void
    {
        $this->prestacionDetalle = null;
    }

    /**
     * Persiste la selección de prestaciones en la tabla pivote centro_prestacion.
     * Usa sync() para gestionar altas y bajas en una sola operación.
     */
    public function guardar(): void
    {
        $centro = Centro::findOrFail($this->centroId);
        $centro->prestaciones()->sync($this->seleccionadas);

        Notification::make()
            ->title('Prestaciones actualizadas correctamente.')
            ->success()
            ->send();
    }

    /**
     * Renderiza la vista del componente.
     */
    public function render(): View
    {
        return view('livewire.centros.selector-prestaciones-centro');
    }
}
