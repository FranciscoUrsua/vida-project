<?php

namespace Modules\Supervision\Http\Livewire;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Intervencion\Models\AsignacionProfesional;
use Modules\Usuarios\Models\Cargo;
use Modules\Usuarios\Models\Profesional;
use Modules\Usuarios\Models\TipoRelacionProfesional;
use Modules\Usuarios\Models\Titulacion;

/**
 * Pantalla «Mi equipo» para el módulo de Supervisión.
 *
 * Gestión del ciclo de vida de los profesionales adscritos a la UO del supervisor:
 * alta, edición, baja (soft delete), cambio de perfil horario y suplencias.
 * Solo gestiona la entidad Profesional, no la cuenta de Usuario (que gestiona adm_usuarios).
 *
 * @property bool   $modalAltaAbierto
 * @property string $nuevoNombre
 * @property int|null $nuevoCargo
 * @property string $nuevaFechaIncorporacion
 * @property int|null $profesionalSeleccionadoId
 * @property bool   $modalBajaAbierto
 * @property string $fechaBaja
 * @property bool   $confirmarBajaConCasos
 * @property string $tabActiva
 * @property bool   $modalEdicionAbierto
 * @property int|null $editandoProfesionalId
 * @property string $editNombre
 * @property string $editApellido1
 * @property string|null $editApellido2
 * @property string $editSexo
 * @property int|null $editCargoId
 * @property int|null $editTipoRelacionId
 * @property int|null $editTitulacionId
 * @property string|null $editCategoriaProfesional
 * @property string|null $editOrganizacion
 * @property string|null $editEmailProfesional
 * @property string|null $editTelefonoProfesional
 * @property string|null $editExtension
 * @property string $editFechaInicio
 */
#[Layout('layouts.supervision')]
class EquipoPage extends Component
{
    /** @var string Tab activa: resumen | horario | suplencias */
    public string $tabActiva = 'resumen';

    /** @var bool Estado del modal de alta de profesional */
    public bool $modalAltaAbierto = false;

    /** @var string Nombre completo del nuevo profesional */
    public string $nuevoNombre = '';

    /** @var int|null ID del cargo seleccionado para el nuevo profesional */
    public ?int $nuevoCargo = null;

    /** @var string Fecha de incorporación del nuevo profesional */
    public string $nuevaFechaIncorporacion = '';

    /** @var int|null ID del profesional en proceso de baja */
    public ?int $profesionalSeleccionadoId = null;

    /** @var bool Estado del modal de confirmación de baja */
    public bool $modalBajaAbierto = false;

    /** @var string Fecha efectiva de la baja */
    public string $fechaBaja = '';

    /** @var bool Confirmación explícita de baja cuando hay casos activos */
    public bool $confirmarBajaConCasos = false;

    /** @var string|null Aviso posterior al alta (cuenta de usuario pendiente de vinculación) */
    public ?string $avisoAlta = null;

    /** @var bool Estado del modal de edición de profesional */
    public bool $modalEdicionAbierto = false;

    /** @var int|null ID del profesional en edición */
    public ?int $editandoProfesionalId = null;

    public string $editNombre = '';
    public string $editApellido1 = '';
    public ?string $editApellido2 = null;
    public string $editSexo = 'D';
    public ?int $editCargoId = null;
    public ?int $editTipoRelacionId = null;
    public ?int $editTitulacionId = null;
    public ?string $editCategoriaProfesional = null;
    public ?string $editOrganizacion = null;
    public ?string $editEmailProfesional = null;
    public ?string $editTelefonoProfesional = null;
    public ?string $editExtension = null;
    public string $editFechaInicio = '';

    /**
     * Profesionales adscritos a la UO del supervisor.
     *
     * Incluye tanto los que tienen cuenta de usuario (con adscripciones vigentes)
     * como los creados directamente por el supervisor (sin cuenta, unidad_organizativa_id directo).
     *
     * @return Collection<int, Profesional>
     */
    #[Computed]
    public function profesionales(): Collection
    {
        $uoIds = auth()->user()?->uoSubtreeIds() ?? [];

        if (empty($uoIds)) {
            return collect();
        }

        return Profesional::where(function ($q) use ($uoIds) {
            $q->whereIn('unidad_organizativa_id', $uoIds)
                ->orWhereHas('usuario', function ($q2) use ($uoIds) {
                    $q2->whereHas('adscripcionesVigentes', function ($q3) use ($uoIds) {
                        $q3->whereIn('unidad_organizativa_id', $uoIds);
                    });
                });
        })->with('cargo', 'usuario')->get();
    }

    /**
     * Catálogo de cargos activos para los selectores del modal.
     *
     * @return Collection<int, Cargo>
     */
    #[Computed]
    public function cargos(): Collection
    {
        return Cargo::where('activo', true)->orderBy('nombre')->get();
    }

    /**
     * Catálogo de tipos de relación profesional activos para el selector de edición.
     *
     * @return Collection<int, TipoRelacionProfesional>
     */
    #[Computed]
    public function tiposRelacion(): Collection
    {
        return TipoRelacionProfesional::where('activo', true)->orderBy('nombre')->get();
    }

    /**
     * Catálogo de titulaciones activas para el selector de edición.
     *
     * @return Collection<int, Titulacion>
     */
    #[Computed]
    public function titulaciones(): Collection
    {
        return Titulacion::where('activo', true)->orderBy('nombre')->get();
    }

    /**
     * Cuenta planes activos asignados al profesional seleccionado.
     *
     * @return int
     */
    #[Computed]
    public function casosActivosProfesionalSeleccionado(): int
    {
        if ($this->profesionalSeleccionadoId === null) {
            return 0;
        }

        $profesional = Profesional::find($this->profesionalSeleccionadoId);

        if ($profesional === null || $profesional->usuario === null) {
            return 0;
        }

        return AsignacionProfesional::where('profesional_id', $profesional->usuario->id)
            ->whereNull('fecha_fin')
            ->whereNull('deleted_at')
            ->count();
    }

    /**
     * Abre el modal de alta de profesional.
     *
     * @return void
     */
    public function abrirModalAlta(): void
    {
        $this->modalAltaAbierto = true;
        $this->avisoAlta = null;
        $this->reset(['nuevoNombre', 'nuevoCargo', 'nuevaFechaIncorporacion']);
    }

    /**
     * Crea un nuevo profesional en la UO del supervisor sin cuenta de usuario vinculada.
     *
     * @return void
     */
    public function crearProfesional(): void
    {
        $this->validate([
            'nuevoNombre'             => 'required|string|max:255',
            'nuevoCargo'              => 'required|integer|exists:cargos,id',
            'nuevaFechaIncorporacion' => 'required|date',
        ]);

        $uoActiva = auth()->user()?->uosActivas()->first();

        if ($uoActiva === null) {
            $this->addError('nuevoNombre', 'El supervisor no tiene UO activa asignada.');
            return;
        }

        $uoIds = auth()->user()?->uoSubtreeIds() ?? [];
        if (! in_array($uoActiva->id, $uoIds, true)) {
            abort(403, 'Sin permisos de gestión sobre esta unidad organizativa.');
        }

        $partes = explode(' ', trim($this->nuevoNombre), 3);

        $tipoRelacionDefault = TipoRelacionProfesional::where('activo', true)->orderBy('id')->value('id') ?? 1;

        Profesional::create([
            'nombre'                  => $partes[0] ?? $this->nuevoNombre,
            'apellido1'               => $partes[1] ?? '',
            'apellido2'               => $partes[2] ?? null,
            'sexo'                    => 'D',
            'cargo_id'                => $this->nuevoCargo,
            'tipo_relacion_id'        => $tipoRelacionDefault,
            'fecha_inicio'            => $this->nuevaFechaIncorporacion,
            'activo'                  => true,
            'unidad_organizativa_id'  => $uoActiva->id,
        ]);

        $this->modalAltaAbierto = false;
        unset($this->profesionales);
        $this->avisoAlta = 'Este profesional no tiene cuenta de usuario en VIDA360 todavía. '
            . 'Comunica al administrador de usuarios que vincule la cuenta cuando esté disponible.';
    }

    /**
     * Abre el modal de confirmación de baja para el profesional indicado.
     *
     * Rechaza la baja si el profesional es el propio supervisor autenticado.
     *
     * @param int $profesionalId ID del profesional a dar de baja.
     * @return void
     */
    public function iniciarBaja(int $profesionalId): void
    {
        $profesional = Profesional::find($profesionalId);

        if ($profesional?->usuario?->id === auth()->id()) {
            return;
        }

        $this->profesionalSeleccionadoId = $profesionalId;
        $this->modalBajaAbierto = true;
        $this->confirmarBajaConCasos = false;
        $this->fechaBaja = now()->toDateString();
    }

    /**
     * Confirma la baja lógica (soft delete) del profesional.
     *
     * Requiere confirmación explícita si el profesional tiene casos activos.
     *
     * @return void
     */
    public function confirmarBaja(): void
    {
        $this->validate(['fechaBaja' => 'required|date']);

        $profesional = Profesional::findOrFail($this->profesionalSeleccionadoId);

        // Impedir baja del propio supervisor como segunda línea de defensa
        if ($profesional->usuario?->id === auth()->id()) {
            return;
        }

        $uoIds = auth()->user()?->uoSubtreeIds() ?? [];

        if ($profesional->usuario !== null) {
            $enAmbito = ! empty($uoIds)
                && collect($uoIds)->intersect(
                    $profesional->usuario->adscripcionesVigentes()->pluck('unidad_organizativa_id')
                )->isNotEmpty();

            if (! $enAmbito) {
                $this->addError('fechaBaja', 'No tiene permisos para dar de baja a este profesional.');
                return;
            }
        } elseif ($profesional->unidad_organizativa_id !== null) {
            if (! in_array($profesional->unidad_organizativa_id, $uoIds, true)) {
                $this->addError('fechaBaja', 'No tiene permisos para dar de baja a este profesional.');
                return;
            }
        }

        $casosActivos = $this->casosActivosProfesionalSeleccionado;

        if ($casosActivos > 0 && ! $this->confirmarBajaConCasos) {
            return;
        }

        $profesional->delete();

        $this->modalBajaAbierto = false;
        $this->profesionalSeleccionadoId = null;
        unset($this->profesionales);
    }

    /**
     * Abre el modal de edición con los datos actuales del profesional indicado.
     *
     * Solo actúa si el profesional está dentro del ámbito de la UO del supervisor.
     *
     * @param int $profesionalId ID del profesional a editar.
     * @return void
     */
    public function abrirEdicion(int $profesionalId): void
    {
        $profesional = $this->profesionalEnAmbito($profesionalId);

        if ($profesional === null) {
            return;
        }

        $this->editandoProfesionalId   = $profesional->id;
        $this->editNombre              = $profesional->nombre;
        $this->editApellido1           = $profesional->apellido1;
        $this->editApellido2           = $profesional->apellido2;
        $this->editSexo                = $profesional->sexo;
        $this->editCargoId             = $profesional->cargo_id;
        $this->editTipoRelacionId      = $profesional->tipo_relacion_id;
        $this->editTitulacionId        = $profesional->titulacion_id;
        $this->editCategoriaProfesional = $profesional->categoria_profesional;
        $this->editOrganizacion        = $profesional->organizacion;
        $this->editEmailProfesional    = $profesional->email_profesional;
        $this->editTelefonoProfesional = $profesional->telefono_profesional;
        $this->editExtension           = $profesional->extension;
        $this->editFechaInicio         = $profesional->fecha_inicio->toDateString();
        $this->modalEdicionAbierto     = true;
    }

    /**
     * Valida y persiste los cambios sobre el profesional en edición.
     *
     * @return void
     */
    public function guardarEdicion(): void
    {
        $this->validate([
            'editNombre'               => ['required', 'string', 'max:100'],
            'editApellido1'            => ['required', 'string', 'max:100'],
            'editApellido2'            => ['nullable', 'string', 'max:100'],
            'editSexo'                 => ['required', 'in:M,F,D'],
            'editCargoId'              => ['required', 'integer', 'exists:cargos,id'],
            'editTipoRelacionId'       => ['required', 'integer', 'exists:tipos_relacion_profesional,id'],
            'editTitulacionId'         => ['nullable', 'integer', 'exists:titulaciones,id'],
            'editCategoriaProfesional' => ['nullable', 'string', 'max:150'],
            'editOrganizacion'         => ['nullable', 'string', 'max:200'],
            'editEmailProfesional'     => ['nullable', 'email', 'max:150'],
            'editTelefonoProfesional'  => ['nullable', 'string', 'max:30'],
            'editExtension'            => ['nullable', 'string', 'max:10'],
            'editFechaInicio'          => ['required', 'date'],
        ]);

        $profesional = $this->profesionalEnAmbito($this->editandoProfesionalId);

        if ($profesional === null) {
            $this->addError('editNombre', 'No tiene permisos para editar este profesional.');
            return;
        }

        $profesional->update([
            'nombre'                => trim($this->editNombre),
            'apellido1'             => trim($this->editApellido1),
            'apellido2'             => filled($this->editApellido2) ? trim($this->editApellido2) : null,
            'sexo'                  => $this->editSexo,
            'cargo_id'              => $this->editCargoId,
            'tipo_relacion_id'      => $this->editTipoRelacionId,
            'titulacion_id'         => $this->editTitulacionId,
            'categoria_profesional' => filled($this->editCategoriaProfesional) ? trim($this->editCategoriaProfesional) : null,
            'organizacion'          => filled($this->editOrganizacion) ? trim($this->editOrganizacion) : null,
            'email_profesional'     => $this->editEmailProfesional ?: null,
            'telefono_profesional'  => $this->editTelefonoProfesional ?: null,
            'extension'             => $this->editExtension ?: null,
            'fecha_inicio'          => $this->editFechaInicio,
        ]);

        $this->modalEdicionAbierto = false;
        unset($this->profesionales);
    }

    /**
     * Renderiza la pantalla de equipo.
     *
     * @return View
     */
    public function render(): View
    {
        return view('supervision::livewire.equipo-page');
    }

    /**
     * Devuelve el Profesional solo si pertenece al ámbito de UO del supervisor.
     *
     * Comprueba primero la adscripción vigente del usuario vinculado y,
     * si el profesional no tiene cuenta, verifica el campo unidad_organizativa_id.
     *
     * @param int|null $profesionalId
     * @return Profesional|null
     */
    private function profesionalEnAmbito(?int $profesionalId): ?Profesional
    {
        if ($profesionalId === null) {
            return null;
        }

        $uoIds = auth()->user()?->uoSubtreeIds() ?? [];

        if (empty($uoIds)) {
            return null;
        }

        $profesional = Profesional::find($profesionalId);

        if ($profesional === null) {
            return null;
        }

        if ($profesional->usuario !== null) {
            $enAmbito = collect($uoIds)->intersect(
                $profesional->usuario->adscripcionesVigentes()->pluck('unidad_organizativa_id')
            )->isNotEmpty();

            return $enAmbito ? $profesional : null;
        }

        if ($profesional->unidad_organizativa_id !== null) {
            return in_array($profesional->unidad_organizativa_id, $uoIds, true) ? $profesional : null;
        }

        return null;
    }
}
