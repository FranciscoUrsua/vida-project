<?php

namespace Modules\Supervision\Http\Livewire;

use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Usuarios\Models\Profesional;
use Modules\Usuarios\Models\TipoRelacionProfesional;

/**
 * Pantalla «Mi equipo» para el módulo de Supervisión.
 *
 * Gestión del ciclo de vida de los profesionales adscritos a la UO del supervisor:
 * alta, baja (soft delete), cambio de perfil horario y suplencias.
 * Solo gestiona la entidad Profesional, no la cuenta de Usuario (que gestiona adm_usuarios).
 *
 * @property bool $modalAltaAbierto
 * @property string $nuevoNombre
 * @property string $nuevoCargo
 * @property string $nuevaFechaIncorporacion
 * @property int|null $profesionalSeleccionadoId
 * @property bool $modalBajaAbierto
 * @property string $fechaBaja
 * @property bool $confirmarBajaConCasos
 */
#[Layout('layouts.supervision')]
class EquipoPage extends Component
{
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

    /**
     * Profesionales adscritos a la UO del supervisor.
     *
     * Incluye tanto los que tienen cuenta de usuario (con adscripciones vigentes)
     * como los creados directamente por el supervisor (sin cuenta, unidad_organizativa_id directo).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Profesional>
     */
    #[Computed]
    public function profesionales(): \Illuminate\Database\Eloquent\Collection
    {
        $uoIds = auth()->user()?->uoSubtreeIds() ?? [];

        if (empty($uoIds)) {
            return collect();
        }

        return Profesional::where(function ($q) use ($uoIds) {
            // Profesionales sin cuenta de usuario pero con UO directa
            $q->whereIn('unidad_organizativa_id', $uoIds)
                ->orWhereHas('usuario', function ($q2) use ($uoIds) {
                    $q2->whereHas('adscripcionesVigentes', function ($q3) use ($uoIds) {
                        $q3->whereIn('unidad_organizativa_id', $uoIds);
                    });
                });
        })->get();
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

        return \Modules\Intervencion\Models\AsignacionProfesional::where('profesional_id', $profesional->usuario->id)
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
        $this->avisoAlta        = null;
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
            'nuevoNombre'              => 'required|string|max:255',
            'nuevoCargo'               => 'required|integer|exists:cargos,id',
            'nuevaFechaIncorporacion'  => 'required|date',
        ]);

        $uoActiva = auth()->user()?->uosActivas()->first();

        if ($uoActiva === null) {
            $this->addError('nuevoNombre', 'El supervisor no tiene UO activa asignada.');
            return;
        }

        // Verificar que el supervisor tiene ámbito sobre la UO activa
        $uoIds = auth()->user()?->uoSubtreeIds() ?? [];
        if (! in_array($uoActiva->id, $uoIds, true)) {
            abort(403, 'Sin permisos de gestión sobre esta unidad organizativa.');
        }

        $partes = explode(' ', trim($this->nuevoNombre), 3);

        // Usar el primer tipo de relación activo como predeterminado para profesionales creados sin cuenta
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
        $this->avisoAlta        = 'Este profesional no tiene cuenta de usuario en VIDA360 todavía. '
                                . 'Comunica al administrador de usuarios que vincule la cuenta cuando esté disponible.';
    }

    /**
     * Abre el modal de confirmación de baja para el profesional indicado.
     *
     * @param int $profesionalId ID del profesional a dar de baja
     * @return void
     */
    public function iniciarBaja(int $profesionalId): void
    {
        $this->profesionalSeleccionadoId = $profesionalId;
        $this->modalBajaAbierto          = true;
        $this->confirmarBajaConCasos     = false;
        $this->fechaBaja                 = now()->toDateString();
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

        // Verificar ámbito del supervisor
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
            // Profesional sin cuenta: verificar por UO directa
            if (! in_array($profesional->unidad_organizativa_id, $uoIds, true)) {
                $this->addError('fechaBaja', 'No tiene permisos para dar de baja a este profesional.');
                return;
            }
        }

        $casosActivos = $this->casosActivosProfesionalSeleccionado;

        if ($casosActivos > 0 && ! $this->confirmarBajaConCasos) {
            // El modal mostrará el aviso; el usuario debe marcar la confirmación
            return;
        }

        $profesional->delete();

        $this->modalBajaAbierto          = false;
        $this->profesionalSeleccionadoId = null;
    }

    /**
     * Renderiza la pantalla de equipo.
     */
    public function render(): View
    {
        return view('supervision::livewire.equipo-page');
    }
}
