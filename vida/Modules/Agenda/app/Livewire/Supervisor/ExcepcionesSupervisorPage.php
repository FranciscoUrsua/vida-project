<?php

namespace Modules\Agenda\Livewire\Supervisor;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Agenda\Enums\OrigenExcepcion;
use Modules\Agenda\Enums\TipoExcepcion;
use Modules\Agenda\Models\ExcepcionProfesional;
use Modules\Agenda\Models\PerfilHorarioProfesional;
use Modules\Centro\Models\Centro;

/**
 * Página de excepciones de profesionales para el supervisor de centro.
 *
 * Permite registrar y eliminar vacaciones, bajas, reducciones de jornada
 * y otros cambios en la disponibilidad del equipo. Al guardar, el Observer
 * de ExcepcionProfesional gestiona el impacto sobre cuadrante y citas publicados.
 *
 * @property-read Collection<int, ExcepcionProfesional> $excepcionesActivas
 * @property-read Collection<int, User> $profesionalesDelCentro
 * @property-read array<string, string> $tiposExcepcion
 * @property array{usuario_id: string, tipo: string, fecha_inicio: string, fecha_fin: string, notas: string} $form
 */
#[Layout('agenda::layouts.agenda-supervisor')]
class ExcepcionesSupervisorPage extends Component
{
    /**
     * Campos del formulario de nueva excepción.
     *
     * @var array{usuario_id: string, tipo: string, fecha_inicio: string, fecha_fin: string, notas: string}
     */
    public array $form = [
        'usuario_id'   => '',
        'tipo'         => '',
        'fecha_inicio' => '',
        'fecha_fin'    => '',
        'notas'        => '',
    ];

    // -------------------------------------------------------------------------
    // Computed
    // -------------------------------------------------------------------------

    /**
     * Excepciones activas y próximas del centro del supervisor.
     *
     * Incluye las que empiezan hoy o en el futuro y las ya activas sin fecha de fin.
     *
     * @return Collection<int, ExcepcionProfesional>
     */
    #[Computed]
    public function excepcionesActivas(): Collection
    {
        $centro = $this->centroDelSupervisor();
        if ($centro === null) {
            return new Collection();
        }

        $hoy = now()->toDateString();

        return ExcepcionProfesional::where('centro_id', $centro->id)
            ->where(function ($q) use ($hoy) {
                $q->whereNull('fecha_fin')->orWhere('fecha_fin', '>=', $hoy);
            })
            ->with(['usuario', 'usuario.profesional'])
            ->orderBy('fecha_inicio')
            ->get();
    }

    /**
     * Usuarios del centro disponibles para asignar excepciones.
     *
     * Solo usuarios con perfil horario activo en el centro del supervisor.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function profesionalesDelCentro(): Collection
    {
        $centro = $this->centroDelSupervisor();
        if ($centro === null) {
            return new Collection();
        }

        $userIds = PerfilHorarioProfesional::where('centro_id', $centro->id)
            ->where('activo', true)
            ->pluck('usuario_id');

        return User::whereIn('id', $userIds)
            ->with('profesional')
            ->orderBy('name')
            ->get();
    }

    /**
     * Valores disponibles de TipoExcepcion para el selector del formulario.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function tiposExcepcion(): array
    {
        return collect(TipoExcepcion::cases())
            ->mapWithKeys(fn (TipoExcepcion $t) => [$t->value => $t->label()])
            ->all();
    }

    // -------------------------------------------------------------------------
    // Acciones
    // -------------------------------------------------------------------------

    /**
     * Crea la excepción con los datos del formulario.
     *
     * Si hay cuadrante publicado con líneas afectadas, el Observer de
     * ExcepcionProfesional las anulará automáticamente.
     *
     * @return void
     */
    public function guardar(): void
    {
        $this->validate([
            'form.usuario_id'   => ['required', 'integer', 'exists:users,id'],
            'form.tipo'         => ['required', 'string', 'in:' . implode(',', array_column(TipoExcepcion::cases(), 'value'))],
            'form.fecha_inicio' => ['required', 'date'],
            'form.fecha_fin'    => ['nullable', 'date', 'after_or_equal:form.fecha_inicio'],
            'form.notas'        => ['nullable', 'string', 'max:500'],
        ]);

        $centro = $this->centroDelSupervisor();
        if ($centro === null) {
            return;
        }

        // Verificar que el profesional pertenece a este centro
        $perteneceAlCentro = PerfilHorarioProfesional::where('centro_id', $centro->id)
            ->where('usuario_id', (int) $this->form['usuario_id'])
            ->where('activo', true)
            ->exists();

        if (! $perteneceAlCentro) {
            $this->addError('form.usuario_id', 'El profesional no pertenece al centro.');
            return;
        }

        ExcepcionProfesional::create([
            'usuario_id'            => (int) $this->form['usuario_id'],
            'centro_id'             => $centro->id,
            'tipo'                  => $this->form['tipo'],
            'fecha_inicio'          => $this->form['fecha_inicio'],
            'fecha_fin'             => $this->form['fecha_fin'] ?: $this->form['fecha_inicio'],
            'afecta_disponibilidad' => true,
            'origen'                => OrigenExcepcion::Manual->value,
            'creado_por_id'         => auth()->id(),
            'notas'                 => filled($this->form['notas']) ? trim($this->form['notas']) : null,
        ]);

        $this->form = ['usuario_id' => '', 'tipo' => '', 'fecha_inicio' => '', 'fecha_fin' => '', 'notas' => ''];
        unset($this->excepcionesActivas);
    }

    /**
     * Elimina la excepción indicada si pertenece al centro del supervisor.
     *
     * @param int $excepcionId
     * @return void
     */
    public function eliminar(int $excepcionId): void
    {
        $centro = $this->centroDelSupervisor();
        if ($centro === null) {
            return;
        }

        $excepcion = ExcepcionProfesional::where('id', $excepcionId)
            ->where('centro_id', $centro->id)
            ->first();

        if ($excepcion === null) {
            return;
        }

        $excepcion->delete();
        unset($this->excepcionesActivas);
    }

    /**
     * Renderiza la pantalla de excepciones.
     *
     * @return View
     */
    public function render(): View
    {
        return view('agenda::livewire.supervisor.excepciones-supervisor-page');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Resuelve el Centro del supervisor autenticado.
     *
     * @return Centro|null
     */
    private function centroDelSupervisor(): ?Centro
    {
        $uoId = auth()->user()?->uosActivas()->first()?->id;

        return $uoId ? Centro::where('unidad_organizativa_id', $uoId)->first() : null;
    }
}
