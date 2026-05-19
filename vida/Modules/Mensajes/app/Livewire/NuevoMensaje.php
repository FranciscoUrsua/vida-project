<?php

namespace Modules\Mensajes\Livewire;

use App\Models\Ciudadano;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Modules\Mensajes\Models\MensajeHilo;
use Modules\Mensajes\Services\MensajeriaService;

/**
 * Formulario de redacción de un mensaje nuevo.
 *
 * Permite buscar el destinatario por nombre o filtrar por rol y UO.
 * También permite referenciar ciudadanos y adjuntar archivos.
 */
class NuevoMensaje extends Component
{
    use WithFileUploads;

    // Destinatario
    public string $busquedaDestinatario = '';
    public ?int $destinatarioId = null;
    public string $filtroPorRol = '';
    public ?int $filtroUoId = null;

    // Mensaje
    public string $asunto = '';
    public string $cuerpo = '';

    // Ciudadanos referenciados
    public string $busquedaCiudadano = '';
    public array $ciudadanosSeleccionados = [];

    // Adjuntos
    public array $adjuntos = [];

    public function mount(): void
    {
        abort_unless(auth()->check(), 401);
    }

    /**
     * Resultados de búsqueda de destinatarios por nombre.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, User>
     */
    #[Computed]
    public function resultadosDestinatario(): \Illuminate\Database\Eloquent\Collection
    {
        if (strlen($this->busquedaDestinatario) < 2) {
            return new \Illuminate\Database\Eloquent\Collection();
        }

        $query = User::where('id', '!=', auth()->id())
            ->where('name', 'ilike', '%' . $this->busquedaDestinatario . '%');

        // Si hay filtro por rol+UO, aplicarlo
        if ($this->filtroPorRol) {
            $query->role($this->filtroPorRol);
        }

        if ($this->filtroUoId) {
            $query->whereHas('adscripciones', function ($q) {
                $q->where('unidad_organizativa_id', $this->filtroUoId)
                    ->whereNull('fecha_fin');
            });
        }

        return $query->with('adscripciones.unidadOrganizativa')->limit(20)->get();
    }

    public function seleccionarDestinatario(int $usuarioId): void
    {
        $this->destinatarioId        = $usuarioId;
        $this->busquedaDestinatario = '';
        unset($this->resultadosDestinatario);
    }

    public function limpiarDestinatario(): void
    {
        $this->destinatarioId = null;
    }

    /**
     * Resultados de búsqueda de ciudadanos para referenciar.
     */
    #[Computed]
    public function resultadosCiudadano(): \Illuminate\Database\Eloquent\Collection
    {
        if (strlen($this->busquedaCiudadano) < 2) {
            return new \Illuminate\Database\Eloquent\Collection();
        }

        // Solo ciudadanos con historia social accesible por el usuario
        return Ciudadano::where('activo', true)
            ->whereNotIn('id', $this->ciudadanosSeleccionados)
            ->limit(10)
            ->get();
    }

    public function agregarCiudadano(int $ciudadanoId): void
    {
        if (! in_array($ciudadanoId, $this->ciudadanosSeleccionados, true)) {
            $this->ciudadanosSeleccionados[] = $ciudadanoId;
        }

        $this->busquedaCiudadano = '';
        unset($this->resultadosCiudadano);
    }

    public function quitarCiudadano(int $ciudadanoId): void
    {
        $this->ciudadanosSeleccionados = array_values(
            array_filter($this->ciudadanosSeleccionados, fn ($id) => $id !== $ciudadanoId)
        );
    }

    public function enviar(MensajeriaService $mensajeriaService): void
    {
        $this->validate([
            'destinatarioId' => [
                'required',
                'integer',
                'exists:users,id',
                function ($attribute, $value, $fail) {
                    if ((int) $value === auth()->id()) {
                        $fail('No puedes enviarte un mensaje a ti mismo.');
                    }
                },
            ],
            'asunto'     => 'required|string|max:255',
            'cuerpo'     => 'required|string|max:10000',
            'adjuntos.*' => 'file|max:10240',
        ]);

        $destinatario = User::findOrFail($this->destinatarioId);

        $hilo = $mensajeriaService->crearHilo(
            remitente:     auth()->user(),
            destinatario:  $destinatario,
            asunto:        $this->asunto,
            cuerpo:        $this->cuerpo,
            ciudadanoIds:  $this->ciudadanosSeleccionados,
            adjuntos:      $this->adjuntos,
        );

        $this->dispatch('hilo-creado', hiloId: $hilo->id);

        $this->reset(['asunto', 'cuerpo', 'destinatarioId', 'ciudadanosSeleccionados', 'adjuntos']);
    }

    public function render(): \Illuminate\View\View
    {
        return view('mensajes::livewire.nuevo-mensaje');
    }
}
