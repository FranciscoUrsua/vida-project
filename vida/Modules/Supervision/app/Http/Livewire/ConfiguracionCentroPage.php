<?php

namespace Modules\Supervision\Http\Livewire;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Centro\Models\Centro;
use Modules\Centro\Models\Sala;
use Modules\Organizacion\Services\ConfiguracionService;

/**
 * Pantalla de configuración del centro para el módulo de Supervisión.
 *
 * Permite al supervisor modificar los parámetros del centro bajo su ámbito
 * sin acceder al backoffice Filament: nombre, plan, horario, modo agenda
 * y umbrales de alerta del dashboard.
 *
 * También gestiona las salas físicas del centro (CRUD completo).
 *
 * @property string $nombreCorto
 * @property string $modoAgenda
 * @property float $umbralRatio
 * @property int $umbralEsperaDias
 * @property bool $mostrarAdvertenciaModoAgenda
 * @property bool $modalSalaAbierto
 * @property int|null $editandoSalaId
 * @property string $salaNombre
 * @property int|null $salaCapacidad
 * @property string $salaDescripcion
 * @property bool $salaAccesible
 * @property bool $salaActiva
 * @property string $salaNotes
 */
#[Layout('layouts.supervision')]
class ConfiguracionCentroPage extends Component
{
    // -------------------------------------------------------------------------
    // Configuración general
    // -------------------------------------------------------------------------

    /** @var string Nombre corto del centro (badge y cabeceras) */
    public string $nombreCorto = '';

    /** @var string Modo de agenda: basico | estandar | avanzado */
    public string $modoAgenda = 'basico';

    /** @var string Modo de agenda anterior al cambio (para comparación) */
    private string $modoAgendaOriginal = '';

    /** @var float Umbral de ratio personas/profesional para alerta en el dashboard */
    public float $umbralRatio = 5;

    /** @var int Umbral de espera media para alerta en el dashboard (días) */
    public int $umbralEsperaDias = 14;

    /** @var bool Indica que el modo agenda ha cambiado y debe mostrarse la advertencia */
    public bool $mostrarAdvertenciaModoAgenda = false;

    // -------------------------------------------------------------------------
    // Salas
    // -------------------------------------------------------------------------

    /** @var bool Estado del modal de alta/edición de sala */
    public bool $modalSalaAbierto = false;

    /** @var int|null ID de la sala en edición; null cuando se está creando */
    public ?int $editandoSalaId = null;

    public string $salaNombre = '';
    public ?int $salaCapacidad = null;
    public string $salaDescripcion = '';
    public bool $salaAccesible = false;
    public bool $salaActiva = true;
    public string $salaNotes = '';

    // -------------------------------------------------------------------------
    // Computed
    // -------------------------------------------------------------------------

    /**
     * Indica si el centro tiene plazas configuradas.
     * Condiciona la sección «Plazas» del formulario.
     *
     * @return bool
     */
    #[Computed]
    public function tienePlazas(): bool
    {
        return (bool) app(ConfiguracionService::class)->get('tiene_plazas', false);
    }

    /**
     * Salas activas e inactivas del centro del supervisor, ordenadas por nombre.
     *
     * @return Collection<int, Sala>
     */
    #[Computed]
    public function salas(): Collection
    {
        $centro = $this->centroDelSupervisor();

        if ($centro === null) {
            return new Collection();
        }

        return Sala::where('centro_id', $centro->id)
            ->orderBy('nombre')
            ->get();
    }

    // -------------------------------------------------------------------------
    // Ciclo de vida
    // -------------------------------------------------------------------------

    /**
     * Carga los valores actuales del centro al montar el componente.
     */
    public function mount(): void
    {
        $uo = auth()->user()?->uosActivas()->first();

        if ($uo !== null) {
            $this->nombreCorto = $uo->nombre_corto ?? '';
        }

        $config = app(ConfiguracionService::class);

        $this->modoAgenda = $config->get('modo_agenda', 'basico');
        $this->modoAgendaOriginal = $this->modoAgenda;
        $this->umbralRatio = (float) $config->get('umbral_ratio_carga', 5);
        $this->umbralEsperaDias = (int) $config->get('umbral_espera_dias', 14);
    }

    /**
     * Detecta el cambio de modo de agenda para mostrar la advertencia de impacto.
     */
    public function updatedModoAgenda(): void
    {
        $this->mostrarAdvertenciaModoAgenda = ($this->modoAgenda !== $this->modoAgendaOriginal);
    }

    // -------------------------------------------------------------------------
    // Configuración general — acciones
    // -------------------------------------------------------------------------

    /**
     * Persiste los cambios de configuración del centro.
     *
     * Solo actúa sobre la UO del supervisor autenticado; lanza 403
     * si se intenta modificar una UO fuera del ámbito.
     */
    public function guardar(): void
    {
        $this->validate([
            'nombreCorto'      => 'nullable|string|max:50',
            'modoAgenda'       => 'required|in:basico,estandar,avanzado',
            'umbralRatio'      => 'required|numeric|min:1|max:100',
            'umbralEsperaDias' => 'required|integer|min:1|max:365',
        ]);

        $uo = auth()->user()?->uosActivas()->first();

        if ($uo === null) {
            $this->addError('nombreCorto', 'El supervisor no tiene UO activa asignada.');
            return;
        }

        if (! in_array($uo->id, auth()->user()?->uoSubtreeIds() ?? [])) {
            abort(403, 'No tiene permisos para modificar la configuración de esta UO.');
        }

        $uo->update(['nombre_corto' => $this->nombreCorto ?: null]);

        $config = app(ConfiguracionService::class);
        $config->set('modo_agenda', $this->modoAgenda);
        $config->set('umbral_ratio_carga', $this->umbralRatio);
        $config->set('umbral_espera_dias', $this->umbralEsperaDias);

        $this->modoAgendaOriginal = $this->modoAgenda;
        $this->mostrarAdvertenciaModoAgenda = false;
    }

    // -------------------------------------------------------------------------
    // Salas — acciones
    // -------------------------------------------------------------------------

    /**
     * Abre el modal en modo alta con el formulario de sala limpio.
     *
     * @return void
     */
    public function abrirModalSala(): void
    {
        $this->reset(['editandoSalaId', 'salaNombre', 'salaCapacidad', 'salaDescripcion', 'salaNotes']);
        $this->salaAccesible = false;
        $this->salaActiva    = true;
        $this->modalSalaAbierto = true;
    }

    /**
     * Abre el modal en modo edición cargando los datos de la sala indicada.
     *
     * Solo actúa si la sala pertenece al centro del supervisor.
     *
     * @param int $salaId ID de la sala a editar.
     * @return void
     */
    public function abrirEdicionSala(int $salaId): void
    {
        $sala = $this->salaEnAmbito($salaId);

        if ($sala === null) {
            return;
        }

        $this->editandoSalaId  = $sala->id;
        $this->salaNombre      = $sala->nombre;
        $this->salaCapacidad   = $sala->capacidad;
        $this->salaDescripcion = $sala->descripcion ?? '';
        $this->salaAccesible   = $sala->accesible;
        $this->salaActiva      = $sala->activa;
        $this->salaNotes       = $sala->notas ?? '';
        $this->modalSalaAbierto = true;
    }

    /**
     * Crea o actualiza la sala con los datos del formulario.
     *
     * @return void
     */
    public function guardarSala(): void
    {
        $this->validate([
            'salaNombre'      => ['required', 'string', 'max:100'],
            'salaCapacidad'   => ['nullable', 'integer', 'min:1'],
            'salaDescripcion' => ['nullable', 'string', 'max:500'],
            'salaNotes'       => ['nullable', 'string', 'max:500'],
        ]);

        $datos = [
            'nombre'      => trim($this->salaNombre),
            'capacidad'   => $this->salaCapacidad,
            'descripcion' => filled($this->salaDescripcion) ? trim($this->salaDescripcion) : null,
            'accesible'   => $this->salaAccesible,
            'activa'      => $this->salaActiva,
            'notas'       => filled($this->salaNotes) ? trim($this->salaNotes) : null,
        ];

        if ($this->editandoSalaId !== null) {
            $sala = $this->salaEnAmbito($this->editandoSalaId);
            if ($sala === null) {
                return;
            }
            $sala->update($datos);
        } else {
            $centro = $this->centroDelSupervisor();

            if ($centro === null) {
                $this->addError('salaNombre', 'No se ha encontrado un centro asociado a tu unidad organizativa.');
                return;
            }

            Sala::create(array_merge($datos, ['centro_id' => $centro->id]));
        }

        $this->modalSalaAbierto = false;
        unset($this->salas);
    }

    /**
     * Elimina (soft-delete) la sala indicada si pertenece al centro del supervisor.
     *
     * @param int $salaId ID de la sala a eliminar.
     * @return void
     */
    public function eliminarSala(int $salaId): void
    {
        $sala = $this->salaEnAmbito($salaId);

        if ($sala === null) {
            return;
        }

        $sala->delete();
        unset($this->salas);
    }

    // -------------------------------------------------------------------------
    // Render
    // -------------------------------------------------------------------------

    /**
     * Renderiza la pantalla de configuración del centro.
     *
     * @return View
     */
    public function render(): View
    {
        return view('supervision::livewire.configuracion-centro-page');
    }

    // -------------------------------------------------------------------------
    // Helpers privados
    // -------------------------------------------------------------------------

    /**
     * Devuelve la Sala solo si pertenece al centro del supervisor.
     *
     * @param int $salaId
     * @return Sala|null
     */
    private function salaEnAmbito(int $salaId): ?Sala
    {
        $centro = $this->centroDelSupervisor();

        if ($centro === null) {
            return null;
        }

        return Sala::where('id', $salaId)
            ->where('centro_id', $centro->id)
            ->first();
    }

    /**
     * Resuelve el Centro asociado a la primera UO activa del supervisor autenticado.
     *
     * @return Centro|null
     */
    private function centroDelSupervisor(): ?Centro
    {
        $uoId = auth()->user()?->uosActivas()->first()?->id;

        if ($uoId === null) {
            return null;
        }

        return Centro::where('unidad_organizativa_id', $uoId)->first();
    }
}
