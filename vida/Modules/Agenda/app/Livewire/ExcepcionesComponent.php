<?php

namespace Modules\Agenda\Livewire;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Agenda\Enums\EstadoCita;
use Modules\Agenda\Enums\OrigenExcepcion;
use Modules\Agenda\Enums\TipoExcepcion;
use Modules\Agenda\Models\Cita;
use Modules\Agenda\Models\ExcepcionProfesional;
use Modules\Centro\Models\Centro;

/**
 * Componente Livewire para gestionar las excepciones horarias de un profesional.
 *
 * Se integra como pestaña en la ficha del profesional. Permite registrar, editar
 * y eliminar vacaciones, bajas y otros cambios de disponibilidad.
 *
 * @property User $profesional
 * @property Centro $centro
 * @property bool $modalAbierto
 * @property array $form
 * @property int|null $editandoId
 * @property int $citasAfectadas
 */
#[Layout('agenda::layouts.agenda-supervisor')]
class ExcepcionesComponent extends Component
{
    /** @var User Profesional al que pertenecen las excepciones */
    public User $profesional;

    /** @var Centro Centro al que aplican las excepciones */
    public Centro $centro;

    /** @var bool Controla la visibilidad del modal de registro */
    public bool $modalAbierto = false;

    /**
     * Datos del formulario del modal.
     *
     * @var array{tipo: string, fecha_inicio: string, fecha_fin: string, afecta_disponibilidad: bool, notas: string}
     */
    public array $form = [
        'tipo'                  => '',
        'fecha_inicio'          => '',
        'fecha_fin'             => '',
        'afecta_disponibilidad' => true,
        'notas'                 => '',
    ];

    /** @var int|null ID de la excepción que se está editando; null si es creación */
    public ?int $editandoId = null;

    /** @var int Número de citas confirmadas afectadas por la excepción en curso */
    public int $citasAfectadas = 0;

    /**
     * Inicializa el componente con el profesional y su centro.
     *
     * @param User $profesional
     * @param Centro $centro
     * @return void
     */
    public function mount(User $profesional, Centro $centro): void
    {
        $this->profesional = $profesional;
        $this->centro      = $centro;
    }

    /**
     * Abre el modal de registro o edición de una excepción.
     *
     * @param int|null $excepcionId ID a editar; null para creación
     * @return void
     */
    public function abrirModal(?int $excepcionId = null): void
    {
        $this->resetErrorBag();
        $this->citasAfectadas = 0;
        $this->editandoId     = $excepcionId;

        if ($excepcionId !== null) {
            $excepcion = ExcepcionProfesional::find($excepcionId);
            if ($excepcion !== null) {
                $this->form = [
                    'tipo'                  => $excepcion->tipo->value,
                    'fecha_inicio'          => $excepcion->fecha_inicio->toDateString(),
                    'fecha_fin'             => $excepcion->fecha_fin->toDateString(),
                    'afecta_disponibilidad' => $excepcion->afecta_disponibilidad,
                    'notas'                 => $excepcion->notas ?? '',
                ];
            }
        } else {
            $this->form = ['tipo' => '', 'fecha_inicio' => '', 'fecha_fin' => '', 'afecta_disponibilidad' => true, 'notas' => ''];
        }

        $this->modalAbierto = true;
    }

    /**
     * Persiste la excepción y cierra el modal si no hay citas afectadas pendientes de confirmación.
     *
     * Si hay citas confirmadas en el período y aún no se ha mostrado el aviso,
     * establece $citasAfectadas y detiene el guardado para mostrar la advertencia.
     *
     * @return void
     */
    public function guardar(): void
    {
        $this->validate([
            'form.tipo'         => ['required', 'in:' . implode(',', array_column(TipoExcepcion::cases(), 'value'))],
            'form.fecha_inicio' => ['required', 'date'],
            'form.fecha_fin'    => ['required', 'date', 'after_or_equal:form.fecha_inicio'],
            'form.notas'        => ['nullable', 'string', 'max:500'],
        ]);

        if ($this->form['afecta_disponibilidad'] && $this->citasAfectadas === 0) {
            $count = Cita::where('profesional_id', $this->profesional->id)
                ->where('centro_id', $this->centro->id)
                ->where('fecha', '>=', $this->form['fecha_inicio'])
                ->where('fecha', '<=', $this->form['fecha_fin'])
                ->where('estado', EstadoCita::Confirmada->value)
                ->count();

            if ($count > 0) {
                $this->citasAfectadas = $count;
                return;
            }
        }

        $datos = [
            'usuario_id'            => $this->profesional->id,
            'centro_id'             => $this->centro->id,
            'tipo'                  => $this->form['tipo'],
            'fecha_inicio'          => $this->form['fecha_inicio'],
            'fecha_fin'             => $this->form['fecha_fin'],
            'afecta_disponibilidad' => $this->form['afecta_disponibilidad'],
            'origen'                => OrigenExcepcion::Manual->value,
            'creado_por_id'         => auth()->id(),
            'notas'                 => filled($this->form['notas']) ? trim($this->form['notas']) : null,
        ];

        if ($this->editandoId !== null) {
            ExcepcionProfesional::where('id', $this->editandoId)->update($datos);
        } else {
            ExcepcionProfesional::create($datos);
        }

        $this->modalAbierto    = false;
        $this->editandoId      = null;
        $this->citasAfectadas  = 0;
        unset($this->proximas, $this->historial);
        $this->dispatch('toast', message: 'Excepción guardada correctamente.', type: 'success');
    }

    /**
     * Elimina la excepción indicada si pertenece al profesional y centro actuales.
     *
     * @param int $id ID de la excepción a eliminar
     * @return void
     */
    public function eliminar(int $id): void
    {
        ExcepcionProfesional::where('id', $id)
            ->where('usuario_id', $this->profesional->id)
            ->where('centro_id', $this->centro->id)
            ->delete();

        unset($this->proximas, $this->historial);
    }

    /**
     * Excepciones futuras o actualmente en curso del profesional.
     *
     * @return Collection<int, ExcepcionProfesional>
     */
    #[Computed]
    public function proximas(): Collection
    {
        return ExcepcionProfesional::where('usuario_id', $this->profesional->id)
            ->where('centro_id', $this->centro->id)
            ->where('fecha_fin', '>=', now()->toDateString())
            ->orderBy('fecha_inicio')
            ->get();
    }

    /**
     * Excepciones pasadas del profesional.
     *
     * @return Collection<int, ExcepcionProfesional>
     */
    #[Computed]
    public function historial(): Collection
    {
        return ExcepcionProfesional::where('usuario_id', $this->profesional->id)
            ->where('centro_id', $this->centro->id)
            ->where('fecha_fin', '<', now()->toDateString())
            ->orderByDesc('fecha_inicio')
            ->get();
    }

    /**
     * Renderiza la vista del componente.
     *
     * @return View
     */
    public function render(): View
    {
        return view('agenda::livewire.excepciones');
    }
}
