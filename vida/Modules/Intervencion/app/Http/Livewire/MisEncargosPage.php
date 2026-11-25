<?php

namespace Modules\Intervencion\Http\Livewire;

use App\Models\Ciudadano;
use App\Models\Scopes\AmbitoUoScope;
use App\Models\User;
use App\Services\PresentacionPersona;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Intervencion\Enums\EstadoDerivacion;
use Modules\Intervencion\Models\Derivacion;

/**
 * «Mis derivaciones»: página de inicio del profesional de encargo (`derivacion.ver_asignadas`,
 * EP-10 de docs/instrucciones-cli/2026-11-encargo-profesional.md).
 *
 * Lista los encargos profesionales asignados al usuario: los activos (aceptados) y el
 * histórico (cerrados), con la persona, el centro, el TSR que encarga, el motivo y la
 * antigüedad. Solo los suyos (scope `deProfesional`).
 *
 * Restricción de dominio (EP-7, RN-17 ampliada): una persona protegida de un encargo
 * cerrado aparece como «Persona con protección especial», sin enlace al encargo ni a su
 * ficha (PresentacionPersona decide con CiudadanoPolicy).
 *
 * @property-read Collection<int, array{encargo: Derivacion, persona: array{visible: bool, id: int|null, nombre: string, url: string|null, ciudadano: Ciudadano|null}}> $activos
 * @property-read Collection<int, array{encargo: Derivacion, persona: array{visible: bool, id: int|null, nombre: string, url: string|null, ciudadano: Ciudadano|null}}> $historico
 */
#[Layout('layouts.operativo')]
class MisEncargosPage extends Component
{
    /**
     * Encargos en curso (aceptados), los más antiguos primero.
     *
     * @return Collection<int, array{encargo: Derivacion, persona: array{visible: bool, id: int|null, nombre: string, url: string|null, ciudadano: Ciudadano|null}}>
     */
    #[Computed]
    public function activos(): Collection
    {
        return $this->filas(EstadoDerivacion::Aceptada, 'asc');
    }

    /**
     * Encargos cerrados, los más recientes primero.
     *
     * @return Collection<int, array{encargo: Derivacion, persona: array{visible: bool, id: int|null, nombre: string, url: string|null, ciudadano: Ciudadano|null}}>
     */
    #[Computed]
    public function historico(): Collection
    {
        return $this->filas(EstadoDerivacion::Cerrada, 'desc');
    }

    /**
     * Renderiza la página.
     *
     * @return View
     */
    public function render(): View
    {
        return view('intervencion::livewire.mis-encargos-page');
    }

    /**
     * Encargos del usuario en un estado, con la persona presentada según lo que puede ver.
     *
     * Las personas se cargan sin AmbitoUoScope: el profesional de encargo no tiene ámbito de
     * UO sobre ellas; lo que se muestra lo decide PresentacionPersona con la policy.
     *
     * @param EstadoDerivacion $estado
     * @param string $orden 'asc' | 'desc'
     *
     * @return Collection<int, array{encargo: Derivacion, persona: array{visible: bool, id: int|null, nombre: string, url: string|null, ciudadano: Ciudadano|null}}>
     */
    private function filas(EstadoDerivacion $estado, string $orden): Collection
    {
        /** @var User $usuario */
        $usuario = auth()->user();

        $encargos = Derivacion::query()
            ->deProfesional($usuario)
            ->where('estado', $estado->value)
            ->with(['centroDestino', 'derivadaPor.profesional'])
            ->orderBy($estado === EstadoDerivacion::Cerrada ? 'cerrada_en' : 'resuelta_en', $orden)
            ->get();

        $ciudadanoDe = DB::table('historias_sociales')
            ->whereIn('id', $encargos->pluck('historia_id')->unique())
            ->pluck('ciudadano_id', 'id');
        $personas = Ciudadano::withoutGlobalScope(AmbitoUoScope::class)
            ->whereKey($ciudadanoDe->values()->unique())
            ->get()
            ->keyBy('id');

        return $encargos->map(fn (Derivacion $encargo) => [
            'encargo' => $encargo,
            'persona' => PresentacionPersona::paraListado($personas->get($ciudadanoDe->get($encargo->historia_id)), $usuario),
        ])->toBase();
    }
}
