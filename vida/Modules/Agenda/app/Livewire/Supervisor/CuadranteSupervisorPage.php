<?php

namespace Modules\Agenda\Livewire\Supervisor;

use Carbon\Carbon;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Agenda\Enums\EstadoCuadrante;
use Modules\Agenda\Models\CuadranteMes;
use Modules\Agenda\Models\LineaCuadrante;
use Modules\Agenda\Models\PerfilHorarioProfesional;
use Modules\Agenda\Services\CuadranteGeneratorService;
use Modules\Agenda\Services\CuadrantePublicadorService;
use Modules\Centro\Models\Centro;

/**
 * Página de cuadrante mensual para el supervisor de centro.
 *
 * Permite visualizar el cuadrante del mes actual en vista semanal o mensual,
 * publicarlo y regenerar el borrador a partir de los perfiles horarios vigentes.
 * Solo disponible en modo de agenda estándar o avanzado.
 *
 * @property string $vistaActiva
 * @property string|null $errorPublicacion
 */
#[Layout('agenda::layouts.agenda-supervisor')]
class CuadranteSupervisorPage extends Component
{
    /** @var string Vista activa del grid: 'semana' o 'mes' */
    public string $vistaActiva = 'semana';

    /** @var string|null Mensaje de error al intentar publicar */
    public ?string $errorPublicacion = null;

    // -------------------------------------------------------------------------
    // Computed
    // -------------------------------------------------------------------------

    /**
     * Cuadrante del mes actual para el centro del supervisor.
     *
     * Devuelve el borrador o el publicado; prioriza el borrador si ambos existen.
     *
     * @return CuadranteMes|null
     */
    #[Computed]
    public function cuadrante(): ?CuadranteMes
    {
        $centro = $this->centroDelSupervisor();
        if ($centro === null) {
            return null;
        }

        return CuadranteMes::where('centro_id', $centro->id)
            ->where('anyo', now()->year)
            ->where('mes', now()->month)
            ->orderByRaw("CASE WHEN estado = 'borrador' THEN 0 ELSE 1 END")
            ->first();
    }

    /**
     * Usuarios con perfil horario activo en el centro del supervisor.
     *
     * @return Collection<int, \App\Models\User>
     */
    #[Computed]
    public function profesionales(): Collection
    {
        $centro = $this->centroDelSupervisor();
        if ($centro === null) {
            return new Collection();
        }

        $userIds = PerfilHorarioProfesional::where('centro_id', $centro->id)
            ->where('activo', true)
            ->pluck('usuario_id');

        return \App\Models\User::whereIn('id', $userIds)
            ->with('profesional')
            ->orderBy('name')
            ->get();
    }

    /**
     * Días laborables que se deben mostrar según la vista activa.
     *
     * En modo 'semana': días L-V de la semana actual.
     * En modo 'mes': todos los días L-V del mes del cuadrante.
     *
     * @return array<int, Carbon>
     */
    #[Computed]
    public function diasEnVista(): array
    {
        $cuadrante = $this->cuadrante;

        $inicio = $this->vistaActiva === 'semana'
            ? now()->startOfWeek(Carbon::MONDAY)
            : Carbon::create($cuadrante?->anyo ?? now()->year, $cuadrante?->mes ?? now()->month, 1);

        $fin = $this->vistaActiva === 'semana'
            ? now()->startOfWeek(Carbon::MONDAY)->addDays(4)
            : Carbon::create($cuadrante?->anyo ?? now()->year, $cuadrante?->mes ?? now()->month, 1)->endOfMonth();

        $dias = [];
        $cursor = $inicio->copy()->startOfDay();
        while ($cursor->lte($fin)) {
            if ($cursor->isWeekday()) {
                $dias[] = $cursor->copy();
            }
            $cursor->addDay();
        }

        return $dias;
    }

    /**
     * Líneas del cuadrante indexadas por 'usuarioId_fecha' para búsqueda O(1) en la vista.
     *
     * @return array<string, LineaCuadrante>
     */
    #[Computed]
    public function lineasIndexadas(): array
    {
        $cuadrante = $this->cuadrante;
        if ($cuadrante === null) {
            return [];
        }

        return $cuadrante->lineas()
            ->with('excepcion')
            ->get()
            ->keyBy(fn (LineaCuadrante $l) => $l->usuario_id . '_' . $l->fecha->toDateString())
            ->all();
    }

    /**
     * Indica si el modo de agenda del centro permite gestión manual del cuadrante.
     *
     * @return bool
     */
    #[Computed]
    public function modoManual(): bool
    {
        $centro = $this->centroDelSupervisor();
        if ($centro === null) {
            return false;
        }

        $horario = $centro->horarioVigente();

        return $horario !== null && ! $horario->esModoBasico();
    }

    // -------------------------------------------------------------------------
    // Acciones
    // -------------------------------------------------------------------------

    /**
     * Publica el cuadrante en borrador y materializa los slots.
     *
     * @return void
     */
    public function publicar(): void
    {
        $cuadrante = $this->cuadrante;

        if ($cuadrante === null || $cuadrante->estado === EstadoCuadrante::Publicado) {
            return;
        }

        try {
            app(CuadrantePublicadorService::class)->publicar($cuadrante, auth()->id());
            $this->errorPublicacion = null;
            unset($this->cuadrante);
        } catch (DomainException $e) {
            $this->errorPublicacion = $e->getMessage();
        }
    }

    /**
     * Regenera el borrador del cuadrante a partir de los perfiles horarios actuales.
     *
     * Elimina las líneas existentes y recalcula desde cero. Solo disponible en borrador.
     *
     * @return void
     */
    public function regenerar(): void
    {
        $cuadrante = $this->cuadrante;

        if ($cuadrante === null || $cuadrante->estado !== EstadoCuadrante::Borrador) {
            return;
        }

        $cuadrante->lineas()->delete();
        app(CuadranteGeneratorService::class)->generarBorrador($cuadrante);
        unset($this->cuadrante);
        unset($this->lineasIndexadas);
    }

    /**
     * Renderiza la pantalla del cuadrante.
     *
     * @return View
     */
    public function render(): View
    {
        return view('agenda::livewire.supervisor.cuadrante-supervisor-page');
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
