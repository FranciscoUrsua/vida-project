<?php

namespace Modules\Intervencion\Http\Livewire;

use App\Models\HistoriaSocial;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Centro\Models\Centro;
use Modules\Centro\Services\Asignacion\CentroDeUsuario;
use Modules\Intervencion\Enums\MotivoCambioReferencia;
use Modules\Intervencion\Services\Asignacion\AsignacionReferenciaService;
use Modules\Intervencion\Services\Asignacion\PoolReferenciaService;

/**
 * Acción «Cambiar referencia» de la supervisión del centro de la historia (fase 3, bloque E).
 *
 * Se incrusta en el bloque «Centro y referencia» de la ficha. Solo la ve la supervisión
 * adscrita al centro (no la de UO superiores; con persona protegida, RN-17/RN-19);
 * AsignacionReferenciaService lo vuelve a comprobar en el servidor.
 *
 * @property-read HistoriaSocial $historia
 * @property-read Centro|null $centro
 * @property-read bool $puedeCambiar
 * @property-read Collection<int, User> $profesionales
 */
class CambioReferencia extends Component
{
    /** Historia cuya referencia se cambia. */
    #[Locked]
    public int $historiaId;

    public bool $abierto = false;

    public ?int $profesionalId = null;

    public string $motivo = '';

    public string $motivoTexto = '';

    /**
     * Historia, sin scope (la ficha ya ha autorizado la vista).
     *
     * @return HistoriaSocial
     */
    #[Computed]
    public function historia(): HistoriaSocial
    {
        return HistoriaSocial::withoutGlobalScopes()->findOrFail($this->historiaId);
    }

    /**
     * Centro de la referencia vigente o, si no hay, el de la historia.
     *
     * @return Centro|null
     */
    #[Computed]
    public function centro(): ?Centro
    {
        $vigente = app(AsignacionReferenciaService::class)->vigente($this->historia);

        return $vigente?->centro_id !== null ? Centro::find($vigente->centro_id) : $this->historia->centro;
    }

    /**
     * Si el usuario puede cambiar la referencia (para mostrar la acción).
     *
     * @return bool
     */
    #[Computed]
    public function puedeCambiar(): bool
    {
        $usuario = auth()->user();

        // La supervisión adscrita al centro (la de UO superiores no cambia referencias, TF-CIR-37)
        return $this->centro !== null
            && $usuario->hasRole('supervision')
            && app(CentroDeUsuario::class)->adscritoA($usuario, $this->centro);
    }

    /**
     * Profesionales que pueden ser referencia en el centro, sin el actual.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function profesionales(): Collection
    {
        if ($this->centro === null) {
            return collect();
        }

        $actual = app(AsignacionReferenciaService::class)->vigente($this->historia)?->profesional_id;

        return app(PoolReferenciaService::class)->profesionalesReferencia($this->centro, today())
            ->reject(fn (User $usuario) => $usuario->id === $actual)
            ->sortBy(fn (User $usuario) => $usuario->nombreVisible())
            ->values();
    }

    /**
     * Abre el formulario.
     *
     * @return void
     */
    public function abrir(): void
    {
        if (! $this->puedeCambiar) {
            return;
        }

        $this->reset(['profesionalId', 'motivo', 'motivoTexto']);
        $this->resetErrorBag();
        $this->abierto = true;
    }

    /**
     * Cambia la referencia con AsignacionReferenciaService::cambiarManual().
     *
     * @return void
     */
    public function cambiar(): void
    {
        $motivo = MotivoCambioReferencia::tryFrom($this->motivo);

        $this->validate([
            'profesionalId' => ['required', 'integer'],
            'motivo' => ['required'],
            'motivoTexto' => [$motivo?->requiereTexto() ? 'required' : 'nullable', 'string', 'max:500'],
        ], [], ['profesionalId' => 'profesional', 'motivo' => 'motivo', 'motivoTexto' => 'explicación']);

        if ($motivo === null) {
            $this->addError('motivo', 'Elige un motivo de la lista.');

            return;
        }

        $textoMotivo = $motivo->requiereTexto() ? "{$motivo->label()}: ".trim($this->motivoTexto) : $motivo->label();

        try {
            app(AsignacionReferenciaService::class)->cambiarManual(
                $this->historia,
                User::findOrFail($this->profesionalId),
                $textoMotivo,
                auth()->user(),
                $this->centro,
            );
        } catch (AuthorizationException|InvalidArgumentException $e) {
            $this->addError('profesionalId', $e->getMessage());

            return;
        }

        $this->abierto = false;
        $this->dispatch('referencia-cambiada');
    }

    /**
     * Pinta la acción y su formulario.
     *
     * @return View
     */
    public function render(): View
    {
        return view('intervencion::livewire.cambio-referencia');
    }
}
